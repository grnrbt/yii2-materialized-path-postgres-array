<?php

namespace app\tests\unit;

use app\mat\tests\unit\models\TestTree;
use grnrbt\materializedPath\MaterializedPathBehavior;
use yii\base\Event;

class MaterializedPathBehaviorTest extends DbTestCase
{
    public function testReorderChildren()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->prependTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $node2->prependTo($root)->save();

        $node3 = new TestTree(['name' => 'node 3']);
        $node3->prependTo($root)->save();

        $root->reorderChildren(true);

        $i = 0;
        foreach ($root->getChildren()->each() as $record) {
            $this->assertEquals($record->position, 100 * $i++);
        }
    }

    public function testMakeRoot()
    {
        TestTree::deleteAll();
        $node = new TestTree(['name' => 'test name']);
        $this->assertTrue($node->makeRoot()->save());

        $this->assertTrue($node->isRoot());
        $this->assertEquals($node->getLevel(), 1);
        $this->assertEquals(TestTree::find()->count(), 1);

        $records = TestTree::find()->roots()->all();
        $this->assertEquals(count($records), 1);
        $this->assertEquals($records[0]->id, $node->id);
    }

    public function testGetParent()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $this->assertTrue($root->makeRoot()->save());

        $node = new TestTree(['name' => 'node']);
        $this->assertTrue($node->appendTo($root)->save());

        $this->assertEquals($node->parent->id, $root->id);
    }

    public function testGetParents()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $this->assertTrue($node2->appendTo($node1)->save());

        $parents = $node2->parents;
        $this->assertEquals(2, count($parents));
        $this->assertEquals($root->id, $parents[0]->id);
        $this->assertEquals($node1->id, $parents[1]->id);
    }

    public function testAppendTo()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $this->assertTrue($node2->appendTo($root)->save());

        $this->assertGreaterThan($node1->position, $node2->position);
    }

    public function testPrependTo()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $this->assertTrue($node2->prependTo($root)->save());

        $this->assertLessThan($node1->position, $node2->position);
    }

    public function testInsertBefore()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $node2->insertBefore($node1)->save();

        $this->assertEquals($node2->parent->id, $root->id);
        $this->assertLessThan($node1->position, $node2->position);
    }

    public function testInsertAfter()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $root->makeRoot()->save();

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());

        $node2 = new TestTree(['name' => 'node 2']);
        $node2->appendTo($root)->save();

        $node3 = new TestTree(['name' => 'node 3']);
        $node3->insertAfter($node1)->save();

        $this->assertEquals($node3->parent->id, $root->id);
        $this->assertGreaterThan($node1->position, $node3->position);
        $this->assertGreaterThan($node3->position, $node2->position);
    }

    public function testEventFiring()
    {
        TestTree::deleteAll();
        $root = new TestTree(['name' => 'root']);
        $this->assertTrue($root->makeRoot()->save());

        $eventParentId = null;
        Event::on(TestTree::class,
            MaterializedPathBehavior::EVENT_CHILDREN_ORDER_CHANGED,
            function ($event) use (&$eventParentId) {
                $eventParentId=$event->parent->id;
            }
        );

        $node1 = new TestTree(['name' => 'node 1']);
        $this->assertTrue($node1->appendTo($root)->save());
        $this->assertNull($eventParentId);

        $node2 = new TestTree(['name' => 'node 2']);
        $this->assertTrue($node2->prependTo($root)->save());
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;

        $node3 = new TestTree(['name' => 'node 3']);
        $this->assertTrue($node3->insertBefore($node1)->save());
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;

        $node4 = new TestTree(['name' => 'node 4']);
        $this->assertTrue($node4->insertAfter($node1)->save());
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;
    }
}