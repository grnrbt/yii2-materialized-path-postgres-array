<?php

namespace app\mat\tests\unit\models;

use app\mat\tests\unit\TestTreeQuery;
use grnrbt\materializedPath\MaterializedPathBehavior;
use yii\db\ActiveRecord;

/**
 * @mixin \grnrbt\materializedPath\MaterializedPathBehavior
 */
class TestTree extends ActiveRecord
{
    public static function find()
    {
        return (new TestTreeQuery(static::class));
    }

    public function behaviors()
    {
        return [
            [
                'class' => MaterializedPathBehavior::class,
                'keyAttribute' => 'id',
            ],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}