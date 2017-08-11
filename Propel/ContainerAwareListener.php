<?php

namespace DonkeyCode\RestBundle\Propel;

use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ContainerAwareListener implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function onPropelConstruct(GenericEvent $event)
    {
        if ($event->getSubject() instanceof ContainerAwareInterface) {
            $event->getSubject()->setContainer($this->container);
        }
    }
}
