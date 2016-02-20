<?php

namespace app\tests\unit;

use app\mat\tests\unit\models\TestTree;
use yii\db\Query;

class MaterializedPathBehaviorTest extends DbTestCase
{
    public function testMakeRoot()
    {
        TestTree::deleteAll();
        $node = new TestTree(['name' => 'test name']);
        $node->makeRoot()->save();
        $this->assertTrue($node->isRoot());
        $this->assertEquals($node->getLevel(), 1);
        $this->assertEquals(TestTree::find()->count(), 1);

        $records = TestTree::find()->roots()->all();
        $this->assertEquals(count($records), 1);
        $this->assertEquals($records[0]->id, $node->id);
    }
}