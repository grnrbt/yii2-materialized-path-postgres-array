<?php

namespace grnrbt\yii2\materializedPath;

use yii\base\Event;
use yii\db\ActiveRecord;

class ChildrenOrderChangedEvent extends Event
{
    /** @var MaterializedPathBehavior|ActiveRecord */
    public $parent;
}