<?php

use yii\db\Migration;

class m160220_142612_create_tree_tbl extends Migration
{
    protected $tableName = 'test_tree';

    public function safeUp()
    {
        $this->createTable($this->tableName, [
            'id' => 'serial primary key',
            'path' => 'int[] not null',
            'position' => 'int not null',
            'tree' => 'int',
            'name' => 'varchar(255) not null',
        ]);
    }

    public function safeDown()
    {
        $this->dropTable($this->tableName);
    }
}
