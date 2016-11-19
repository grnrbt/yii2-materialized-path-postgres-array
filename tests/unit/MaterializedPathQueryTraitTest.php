<?php

namespace grnrbt\tests\unit;

use grnrbt\unit\fixtures\TreeFixture;
use grnrbt\unit\models\Tree;

class MaterializedPathQueryTraitTest extends DbTestCase
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

    public function testDescendantsOf()
    {
        $parentId = 8;
        $descIds = [9, 10, 11, 12, 13, 14];
        /** @var Tree $parent */
        $parent = Tree::find()->andWhere(['id' => $parentId])->one();
        $modelDescs = $parent->getDescendants()->indexBy('id')->all();
        $this->assertEquals(count($descIds), count($modelDescs));
        $ids = array_keys($modelDescs);
        sort($ids);
        $this->assertEquals($descIds, $ids);
        $queryDescs = Tree::find()->descendantsOf($parentId)->indexBy('id')->all();
        $this->assertEquals($modelDescs, $queryDescs);
    }

    public function testDescendantsOfWithDepth()
    {
        $parentId = 8;
        $descIds = [9, 12];
        /** @var Tree $parent */
        $parent = Tree::find()->andWhere(['id' => $parentId])->one();
        $modelDescs = $parent->getDescendants(1)->indexBy('id')->asArray()->all();
        $this->assertEquals(count($descIds), count($modelDescs));
        $ids = array_keys($modelDescs);
        sort($ids);
        $this->assertEquals($descIds, $ids);
        $queryDescs = Tree::find()->descendantsOf($parentId, 1)->indexBy('id')->asArray()->all();
        $this->assertEquals($modelDescs, $queryDescs);
    }

    public function testParentsOf()
    {
        $nodeId = 14;
        $parentIds = [8, 12];
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelParents = $node->getParents()->indexBy('id')->all();
        $this->assertEquals(array_keys($modelParents), $parentIds);
        $queryParents = Tree::find()->parentsOf($nodeId)->indexBy('id')->all();
        $this->assertEquals($modelParents, $queryParents);
    }

    public function testParentsOfWithDepth()
    {
        $nodeId = 14;
        $parentIds = [12];
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelParents = $node->getParents(1)->indexBy('id')->all();
        $this->assertEquals(array_keys($modelParents), $parentIds);
        $queryParents = Tree::find()->parentsOf($nodeId, 1)->indexBy('id')->all();
        $this->assertEquals($modelParents, $queryParents);
    }

    public function testNextOf()
    {
        $nodeId = 10;
        $nextId = 11;
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelNext = $node->getNext()->one();
        $this->assertInstanceOf(Tree::class, $modelNext);
        $this->assertEquals($modelNext->id, $nextId);
        $queryNext = Tree::find()->nextOf($nodeId)->one();
        $this->assertEquals($modelNext, $queryNext);
    }

    public function testNextOfRoot()
    {
        $nodeId = 1;
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelNext = $node->getNext()->one();
        $this->assertNull($modelNext);
        $this->assertNull(Tree::find()->nextOf($nodeId)->one());
    }

    public function testPrevOf()
    {
        $nodeId = 11;
        $nextId = 10;
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelPrev = $node->getPrev()->one();
        $this->assertInstanceOf(Tree::class, $modelPrev);
        $this->assertEquals($modelPrev->id, $nextId);
        $queryPrev = Tree::find()->prevOf($nodeId)->one();
        $this->assertEquals($modelPrev, $queryPrev);
    }

    public function testPrevOfRoot()
    {
        $nodeId = 1;
        /** @var Tree $node */
        $node = Tree::find()->andWhere(['id' => $nodeId])->one();
        $modelNext = $node->getNext()->one();
        $this->assertNull($modelNext);
        $this->assertNull(Tree::find()->nextOf($nodeId)->one());
    }
}