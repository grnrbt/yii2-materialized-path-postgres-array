<?php

namespace grnrbt\materializedPath;

use yii\base\Event;

class ChildrenOrderChangedEvent extends Event
{
    /** @var MaterializedPathBehavior */
    public $parent;
}