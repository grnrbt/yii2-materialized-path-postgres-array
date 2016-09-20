<?php

namespace unit\models;

use grnrbt\materializedPath\MaterializedPathQueryTrait;
use yii\db\ActiveQuery;

class TreeQuery extends ActiveQuery
{
    use MaterializedPathQueryTrait;
}