<?php

namespace app\mat\tests\unit;

use grnrbt\materializedPath\MaterializedPathQueryTrait;
use yii\db\ActiveQuery;

class TestTreeQuery extends ActiveQuery
{
    use MaterializedPathQueryTrait;
}