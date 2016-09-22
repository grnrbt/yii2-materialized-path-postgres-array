<?php

namespace grnrbt\yii2\materializedPath;

use \yii\db\ActiveRecord;

/**
 * Trait for ActiveRecords uses MaterializedPathBehavior.
 *
 * @property \yii\db\ActiveRecord|MaterializedPathBehavior $owner
 */
trait MaterializedPathQueryTrait
{
    /**
     * @return \yii\db\ActiveQuery
     */
    public function roots()
    {
        /** @var \yii\db\ActiveQuery $this */
        $class = $this->modelClass;
        /** @var MaterializedPathBehavior|ActiveRecord $model */
        $model = new $class;
        return $this->andWhere(['=', "array_length({$model->getPathColumn()}, 1)", 1]);
    }
}
