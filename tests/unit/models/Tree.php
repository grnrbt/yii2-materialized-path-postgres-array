<?php

namespace grnrbt\unit\models;

use grnrbt\yii2\materializedPath\MaterializedPathBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $position
 * @property string $path
 * @mixin MaterializedPathBehavior
 */
class Tree extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new TreeQuery(static::class);
    }

    public function behaviors()
    {
        return [
            'mp' => MaterializedPathBehavior::className(),
        ];
    }
}