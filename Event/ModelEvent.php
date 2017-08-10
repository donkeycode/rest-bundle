<?php

namespace DonkeyCode\RestBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ModelEvent extends Event
{
    /**
     * @var mixed
     */
    protected $object;

    /**
     * @param mixed $object
     */
    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }
}