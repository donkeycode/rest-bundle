<?php

namespace DonkeyCode\RestBundle\Propel;

use Propel\Runtime\ActiveQuery\Criteria;
use Doctrine\Common\Inflector\Inflector;

trait SearchableTrait {

    public function search(string $q)
    {
        $this->_and();
        $needOr = false;

        $tableMap = $this->getTableMap();

        foreach ($tableMap->getColumns() as $column) {
            if ($column->isPrimaryKey()) {
                continue;
            }

            if ($column->isText() || $column->isLob()) {
                if ($needOr) {
                    $this->_or();
                }

                $method = "filterBy{$column->getPhpName()}";

                $this->$method("%".$q."%", Criteria::LIKE);
                $needOr = true;

                continue;
            }

            $method = "search{$column->getPhpName()}";

            if (method_exists($this, $method)) {
                if ($needOr) {
                    $this->_or();
                }

                $this->$method($q);
                $needOr = true;
            }
        }
        
        if (method_exists($this, "moreSearch")) {
            if ($needOr) {
                $this->_or();
            }
            
            $this->moreSearch($q);
        }

        return $this;
    }
}