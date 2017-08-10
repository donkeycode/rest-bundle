<?php

namespace DonkeyCode\RestBundle\Propel;

use Propel\Runtime\ActiveQuery\Criteria;
use Doctrine\Common\Inflector\Inflector;

trait RestAggregableTrait {
    protected $aggregates = false;

    public function getAggregates() {
        if (!$this->aggregates) {
            $this->aggregates = [];
            if ($this->aggregateQueryParameters) {
                foreach($this->aggregateQueryParameters as $parameters) {

                    if (isset($parameters['query_model']) && $parameters['query_model']) {
                        $aggregates = $parameters['query_model']::create()
                            ->select(array(
                                $parameters['key'],
                                $parameters['label']
                            ))
                            ->find()
                        ;
                        $aggregates = array_map(function($aggregate) use($parameters){
                            return array(
                                'key'   => $aggregate[$parameters['key']],
                                'label' => $aggregate[$parameters['label']]
                            );
                        }, iterator_to_array($aggregates));
                    } else {
                        $aggregates = $parameters['values'];
                    }

                    $this->aggregates[] = [
                        "label"   => $parameters['mainLabel'],
                        "key"     => $parameters['mainKey'],
                        "buckets" => $aggregates
                    ];
                }
            }
        }
        return $this->aggregates;
    }

    public function filterByAggregates($aggregates) {
        forEach($aggregates as $filter => $values) {
            $filter = Inflector::classify($filter);
            $functionName = 'filterBy'.$filter;
            $values = array_keys($values);

            if (method_exists($this, $functionName)) {
                $this->$functionName($values, Criteria::IN);
            }
        }
        $this->groupBy('id');
        return $this;
    }
}
