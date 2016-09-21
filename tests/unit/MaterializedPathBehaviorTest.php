<?php

namespace grnrbt\tests\unit;

use grnrbt\materializedPath\MaterializedPathBehavior;
use grnrbt\unit\fixtures\TreeFixture;
use grnrbt\unit\models\Tree;
use yii\base\Event;

/**
 * @method Tree tree($key)
 */
class MaterializedPathBehaviorTest extends DbTestCase
{
    /**
     * @inheritdoc
     */
    public function fixtures()
    {
        return [
            'tree' => TreeFixture::class,
        ];
    }

    public function testCreateNewNode()
    {
        Tree::deleteAll();
        $node = new Tree(['name' => 'test node']);
        $this->assertTrue($node->save());
        $this->assertEquals($node->position, 0);
        $this->assertTrue($node->isRoot());
    }

    public function testCreateRootNode()
    {
        Tree::deleteAll();
        $node = new Tree(['name' => 'test node']);
        $node->makeRoot();
        $this->assertTrue($node->save());
        $this->assertEquals($node->position, 0);
        $this->assertTrue($node->isRoot());
    }

    public function testAppendTo()
    {
        $root = $this->tree('1');
        $node1 = new Tree(['name' => 'node 1']);
        $node1->appendTo($root)->save();
        $node2 = new Tree(['name' => 'node 2']);
        $node2->appendTo($root)->save();
        $this->assertGreaterThan($node1->position, $node2->position);
    }

    public function testAppendNodeToSibling()
    {
        $root = $this->tree('1');
        $node1 = new Tree(['name' => 'node 1']);
        $node1->appendTo($root)->save();
        $node2 = new Tree(['name' => 'node 2']);
        $node2->appendTo($root)->save();
        $node2->appendTo($node1)->save();
        $this->assertEquals($node2->parent->id, $node1->id);
        $this->assertEquals($node2->position, 0);
    }

    public function testPrependTo()
    {
        $root = $this->tree('1');
        $node1 = new Tree(['name' => 'node 1']);
        $node1->prependTo($root)->save();
        $node2 = new Tree(['name' => 'node 2']);
        $node2->prependTo($root)->save();
        $this->assertLessThan($node1->position, $node2->position);
    }

    public function testInsertBefore()
    {
        $root = $this->tree('1');
        $node1 = new Tree(['name' => 'node 1']);
        $node1->appendTo($root)->save();
        $node2 = new Tree(['name' => 'node 2']);
        $node2->insertBefore($node1)->save();
        $this->assertEquals($node2->parent->id, $root->id);
        $this->assertLessThan($node1->position, $node2->position);
    }

    public function testInsertAfter()
    {
        $root = $this->tree('1');
        $node1 = new Tree(['name' => 'node 1']);
        $node1->appendTo($root)->save();
        $node2 = new Tree(['name' => 'node 2']);
        $node2->insertAfter($node1)->save();
        $this->assertEquals($node2->parent->id, $root->id);
        $this->assertGreaterThan($node1->position, $node2->position);
    }

    public function testEventFiring()
    {
        Tree::deleteAll();
        $root = new Tree(['name' => 'root']);
        $root->save();
        $eventParentId = null;
        Event::on(
            Tree::class,
            MaterializedPathBehavior::EVENT_CHILDREN_ORDER_CHANGED,
            function ($event) use (&$eventParentId) {
                $eventParentId = $event->parent->id;
            }
        );
        $node1 = new Tree(['name' => 'node 1']);
        $node1->appendTo($root)->save();
        $this->assertNull($eventParentId);
        $node2 = new Tree(['name' => 'node 2']);
        $node2->prependTo($root)->save();
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;
        $node3 = new Tree(['name' => 'node 3']);
        $node3->insertBefore($node1)->save();
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;
        $node4 = new Tree(['name' => 'node 4']);
        $node4->insertAfter($node1)->save();
        $this->assertEquals($eventParentId, $root->id);
        $eventParentId = null;
    }

    public function testGetParent()
    {
        $root = $this->tree('1');
        $node = $this->tree('1.1');
        $this->assertEquals($node->parent->id, $root->id);
    }

    public function testGetParents()
    {
        $root = $this->tree('1');
        $node1 = $this->tree('1.1');
        $node2 = $this->tree('1.1.1');
        $parents = $node2->parents;
        $this->assertEquals(2, count($parents));
        $this->assertEquals($root->id, $parents[0]->id);
        $this->assertEquals($node1->id, $parents[1]->id);
    }

    public function testGetRoots()
    {
        $roots = Tree::find()->roots()->all();
        $this->assertEquals(2, count($roots));
        $this->assertEquals($roots[0]->level, 1);
        $this->assertEquals($roots[1]->level, 1);
        $this->assertGreaterThan($roots[0]->position, $roots[1]->position);
    }
}