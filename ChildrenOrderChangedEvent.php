<?php

namespace grnrbt\materializedPath;

use yii\base\Event;

class ChildrenOrderChangedEvent extends Event
{
    /** ActiveRecord|MaterializedPathBehavior @var */
    public $parent;
}