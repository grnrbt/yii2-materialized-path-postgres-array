<?php

namespace grnrbt\unit\models;

use grnrbt\yii2\materializedPath\MaterializedPathQueryTrait;
use yii\db\ActiveQuery;

class TreeQuery extends ActiveQuery
{
    use MaterializedPathQueryTrait;
}