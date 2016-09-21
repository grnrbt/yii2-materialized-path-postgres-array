<?php

namespace grnrbt\unit\fixtures;

use grnrbt\unit\models\Tree;
use yii\test\ActiveFixture;

class TreeFixture extends ActiveFixture
{
    public $modelClass = Tree::class;

    /**
     * @inheritdoc
     */
    public function load()
    {
        parent::load();
        $this->db->createCommand("ALTER SEQUENCE tree_id_seq RESTART 100;")->execute();
    }
}