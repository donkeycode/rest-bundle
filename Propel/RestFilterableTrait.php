<?php

namespace DonkeyCode\RestBundle\Propel;

use Propel\Runtime\ActiveQuery\Criteria;
use Doctrine\Common\Inflector\Inflector;

trait RestFilterableTrait {

    public function filterByFilter(string $jsonFilter)
    {
        $params = json_decode($jsonFilter, true);

        foreach ($params as $key => $like) {
            if ($this->lookLikeABoolean($like)) {
                $this->filterBy(Inflector::classify($key), $this->asBoolean($like));

                continue;
            }

            if (is_string($like)) {
                $this->filterBy(Inflector::classify($key), '%'.$like.'%', Criteria::LIKE);

                continue;
            }

            $this->filterBy(Inflector::classify($key), $like);
            
        }

        return $this;
    }

    public function sortBySort(string $jsonSort)
    {
        list($col, $order) = json_decode($jsonSort, true);

        if ($col) {
            $this->orderBy(Inflector::classify($col), $order);
        }

        return $this;
    }

    protected function lookLikeABoolean(string $value)
    {
        return $value === "true" || $value === "false";
    }

    protected function asBoolean(string $value)
    {
        return $value === "true";
    }
}