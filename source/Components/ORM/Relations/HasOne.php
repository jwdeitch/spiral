<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\ORM\Relations;

use Spiral\Components\ORM\ActiveRecord;
use Spiral\Components\ORM\Relation;
use Spiral\Components\ORM\Selector;

class HasOne extends Relation
{
    const RELATION_TYPE = ActiveRecord::HAS_ONE;

    /**
     * @return mixed
     */
    public function getContent()
    {
        if (is_object($this->data))
        {
            return $this->data;
        }

        $class = $this->definition[static::RELATION_TYPE];
        if (!$this->parent->isLoaded())
        {
            return $this->data = new $class([], false, $this->orm);
        }

        if ($this->data === null)
        {
            $this->loadData();
        }

        if ($this->data === null)
        {
            //optimize
            return null;
        }

        return $this->data = new $class($this->data, true, $this->orm);
    }

    protected function loadData()
    {
        $selector = $this->createSelector();

        //We have to configure where conditions

        dump($selector);
    }
}