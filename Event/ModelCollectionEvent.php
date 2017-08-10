<?php

namespace DonkeyCode\RestBundle\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class ModelCollectionEvent extends GenericEvent
{
    /**
     * @var int
     */
    protected $page;

    /**
     * @param null $modelName
     * @param null $page
     */
    public function __construct($modelName, $page = null)
    {
        $this->page = $page;

        return parent::__construct($modelName);
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->getSubject();
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }
}