<?php

namespace DonkeyCode\RestBundle\Controller;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RestController\Event\ModelCollectionEvent;
use App\Services\RestController\Event\ModelEvent;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use JMS\Serializer\SerializationContext;

class DoctrineRestController extends Controller
{
    /**
     * @param $object
     *
     * @return JsonResponse
     */
    protected function createGetResponse($object)
    {
        $this->get('event_dispatcher')->dispatch('rest_get', new ModelEvent($object));

        return new JsonResponse($this->serialize($object), Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return JsonResponse
     */
    protected function createPostResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isValid()) {
            $object = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($object);
            $em->flush();

            $this->get('event_dispatcher')->dispatch('rest_post', new ModelEvent($object));

            $objectSerialized = $this->serialize($object);

            return new JsonResponse($objectSerialized, Response::HTTP_CREATED);
        }

        $translator = $this->container->get('translator');

        return new JsonResponse(
            [
                'status' => 'error',
                'errors' => $this->convertFormToArray($form, $translator),
            ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return JsonResponse
     */
    protected function createPutResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isValid()) {
            $object = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($object);
            $em->flush();

            $this->get('event_dispatcher')->dispatch('rest_put', new ModelEvent($object));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $translator = $this->container->get('translator');

        return new JsonResponse(
            [ 'status' => 'error',
              'errors' => $this->convertFormToArray($form, $translator)
            ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return JsonResponse
     */
    protected function createPatchResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit(json_decode($request->getContent(), true), false);

        if ($form->isValid()) {
            $object = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($object);
            $em->flush();

            $this->get('event_dispatcher')->dispatch('rest_patch', new ModelEvent($object));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $translator = $this->container->get('translator');

        return new JsonResponse(
            [ 'status' => 'error',
              'errors' => $this->convertFormToArray($form, $translator)
            ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param QueryBuilder $query
     *
     * @return JsonResponse
     */
    protected function createCgetResponse(ServiceEntityRepository $repository, array $options = []): JsonResponse
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        if ($this->hasSortableParams($request)) {
            $options['sort'] = $request->get('sort');
        } else if (method_exists($repository, "getDefaultSortOption")) {
            $options['sort'] = $repository->getDefaultSortOption();
        }

        $query = $repository->cgetQuery($request->get('filter') ?? [], $options);

        $range = $request->headers->get('range');
        list($size, $from) = $this->parseRangeHeader($range, $request);

        $page = floor($from/$size) + 1;
        $adapter = new DoctrineORMAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($size);
        $pagerfanta->setCurrentPage($page);
        $results = $pagerfanta->getCurrentPageResults();

        $total = $pagerfanta->getNbResults();
        $count = $query->setMaxResults($size)->setFirstResult($size*($page - 1))->select('count(p.id)')->getQuery()->getSingleScalarResult();

        $rangeStart = ($page - 1) * $size;
        $rangeEnd   = ($count > 0) ? ($rangeStart + $count - 1) : $count;

        $this->get('event_dispatcher')->dispatch('rest_cget', new ModelCollectionEvent($query->getRootEntities(), $page));

        return new JsonResponse($this->serialize((array) $results), Response::HTTP_PARTIAL_CONTENT, [
            'Access-Control-Expose-Headers' => ['Accept-Ranges', 'Content-Range', 'X-Sort-Order', 'X-Sort-By'],
            'Accept-Ranges' => 'objects',
            'Content-Range'=> 'objects '.$rangeStart.'-'.$rangeEnd.'/'.$total,
            'X-Sort-Order' => (isset($options['sort']) && isset($options['sort']['order'])) ? $options['sort']['order'] : null,
            'X-Sort-By' => (isset($options['sort']) && isset($options['sort']['by'])) ? $options['sort']['by'] : null,
        ]);
    }

    private function hasSortableParams($request) {
        if ($request->get('sort') &&
            isset($request->get('sort')['by']) &&
            isset($request->get('sort')['order']) &&
            $request->get('sort')['order'] === 'asc' ||
            $request->get('sort')['order'] === 'desc') {
                return true;
            }
            return false;
    }

    /**
     * @param mixed $object
     *
     * @return JsonResponse
     */
    protected function createDeleteResponse($object)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($object);
        $em->flush();

        $this->get('event_dispatcher')->dispatch('rest_delete', new ModelEvent($object));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Parse Range header
     * @param  string $rangeHeader Range header to be parsed
     * @return array[int size, int from]
     */
    protected function parseRangeHeader($rangeHeader = 'objects 0-9', $request): array
    {
        if (!$rangeHeader) { // Try in query params
            if ($range = $request->get('range')) {
                list($start, $end) = json_decode($range, 1);
            } else {
                $start = 0;
                $end = 9;
            }
        } else {
            $rangeHeader = $rangeHeader ?: 'objects 0-9';

            $rangeHeader = explode(' ', $rangeHeader);
            $rangeHeader = explode('-', isset($rangeHeader[1]) ? $rangeHeader[1] : '');

            $start = isset($rangeHeader[0]) ? $rangeHeader[0] : 0;
            $end   = isset($rangeHeader[1]) ? $rangeHeader[1] : $start + 9;
        }

        $size = $end - $start + 1;
        $from = $start;

        return [
            $size,
            $from,
        ];
    }

    private function serialize($object)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        if (preg_match('/[^.]+post$/', $request->get('_route'))) {
            $group = substr_replace($request->get('_route'), 'get', -4);
        } else {
            $group = $request->get('_route');
        }

        $context = SerializationContext::create()->setGroups([ $group ]);

        return $this->container->get('jms_serializer')->toArray($object, $context);
    }

    /*
    ** From https://codereviewvideos.com/course/beginners-guide-back-end-json-api-front-end-2018/video/nicer-symfony-4-form-errors
    */
    private function convertFormToArray(FormInterface $data, TranslatorInterface $translator)
    {
        $form = $errors = [];

        foreach ($data->getErrors() as $error) {
            $errors[] = $this->getErrorMessage($error, $translator);
        }

        if ($errors) {
            $form['errors'] = $errors;
        }

        $children = [];
        foreach ($data->all() as $child) {
            if ($child instanceof FormInterface) {
                $children[$child->getName()] = $this->convertFormToArray($child, $translator);
            }
        }

        if ($children) {
            $form['children'] = $children;
        }

        return $form;
    }

    private function getErrorMessage(FormError $error, TranslatorInterface $translator)
    {
        if (null !== $error->getMessagePluralization()) {
            return $translator->transChoice(
                $error->getMessageTemplate(),
                $error->getMessagePluralization(),
                $error->getMessageParameters(),
                'validators'
            );
        }

        return $translator->trans($error->getMessageTemplate(), $error->getMessageParameters(), 'validators');
    }
}
