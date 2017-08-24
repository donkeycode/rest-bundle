<?php

namespace DonkeyCode\RestBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use Pagerfanta\Adapter\Propel2Adapter;
use Pagerfanta\Pagerfanta;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DonkeyCode\RestBundle\Event\ModelCollectionEvent;
use DonkeyCode\RestBundle\Event\ModelEvent;

class RestController extends FOSRestController
{
    /**
     * @param $object
     *
     * @return mixed
     */
    protected function createGetResponse($object)
    {
        $this->get('event_dispatcher')->dispatch('rest_get', new ModelEvent($object));

        return $object;
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return array|\FOS\RestBundle\View\View
     */
    protected function createPostResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit($request->request->all());

        if ($form->isValid()) {
            $object = $form->getData();
            $object->save();
            $this->get('event_dispatcher')->dispatch('rest_post', new ModelEvent($object));

            return $this->view($object, Response::HTTP_CREATED);
        }

        return $this->view(['form' => $form], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return array|\FOS\RestBundle\View\View
     */
    protected function createPutResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit($request->request->all());

        if ($form->isValid()) {
            $object = $form->getData();
            $object->save();
            $this->get('event_dispatcher')->dispatch('rest_put', new ModelEvent($object));

            return $this->view(null, Response::HTTP_NO_CONTENT);
        }

        return $this->view(['form' => $form], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param array   $options
     * @param mixed   $object
     *
     * @return array|\FOS\RestBundle\View\View
     */
    protected function createPatchResponse(Request $request, $type, array $options = [], $object = null)
    {
        $form = $this->createForm($type, $object, $options);
        $form->submit($request->request->all(), false);

        if ($form->isValid()) {
            $object = $form->getData();
            $object->save();
            $this->get('event_dispatcher')->dispatch('rest_patch', new ModelEvent($object));

            return $this->view(null, Response::HTTP_NO_CONTENT);
        }

        return $this->view(['form' => $form], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param ModelCriteria $query
     *
     * @return array
     */
    protected function createCgetResponse(ModelCriteria $query)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $aggregates = [];
        if (method_exists($query, "getAggregates")) {
            $aggregates = $query->getAggregates();
        }

        if (method_exists($query, "filterByAggregates")) {
            $query
                ->_if($request->get('aggregates'))
                    ->filterByAggregates($request->get('aggregates'))
                ->_endif()
            ;
        }

        if (method_exists($query, "filterByFilter")) {
            $query
                ->_if($request->get('filter'))
                    ->filterByFilter($request->get('filter'))
                ->_endif()
                ->_if($request->get('sort'))
                    ->sortBySort($request->get('sort'))
                ->_endif();
        }

        if ($request->get('q') && method_exists($query, "search")) {
            $query->search($request->get('q'));
        }

        $range = $request->headers->get('range');
        list($size, $from) = $this->parseRangeHeader($range, $request);

        $page = floor($from/$size) + 1;
        $adapter = new Propel2Adapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($size);
        $pagerfanta->setCurrentPage($page);
        $results = $pagerfanta->getCurrentPageResults();

        $total = $pagerfanta->getNbResults();
        $count      = $query->limit($size)->offset($size*($page - 1))->count();

        $rangeStart = ($page - 1) * $size;
        $rangeEnd   = ($count > 0) ? ($rangeStart + $count - 1) : $count;

        $this->get('event_dispatcher')->dispatch('rest_cget', new ModelCollectionEvent($query->getModelName(), $page));
        if ($request->get('env') == 'admin') {
            $view = $this->view([
                "aggregations" => $aggregates,
                "hits" => [
                    "total" => $total,
                    "hits" => iterator_to_array($results)
                ]], Response::HTTP_PARTIAL_CONTENT);
        } else {
            $view = $this->view(iterator_to_array($results), Response::HTTP_PARTIAL_CONTENT);
        }
        $view->setHeader('Accept-Ranges', 'objects');
        $view->setHeader('Content-Range', 'objects '.$rangeStart.'-'.$rangeEnd.'/'.$total);

        return $view;
    }

    /**
     * @param ActiveRecordInterface $object
     *
     * @return \FOS\RestBundle\View\View
     */
    protected function createDeleteResponse(ActiveRecordInterface $object)
    {
        $object->delete();
        $this->get('event_dispatcher')->dispatch('rest_delete', new ModelEvent($object));

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    public function addSerializerGroup($groupToAdd, $request)
    {
        $groups = $request->attributes->get('_template')->getSerializerGroups();
        $groups[] = $groupToAdd;
        $x = $request->attributes->get('_template')->setSerializerGroups($groups);
    }

    public function setSerializerGroup($groupToSet, $request)
    {
        $groups[] = $groupToSet;
        $x = $request->attributes->get('_template')->setSerializerGroups($groups);
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
}
