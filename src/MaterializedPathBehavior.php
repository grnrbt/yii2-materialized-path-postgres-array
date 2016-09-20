<?php

namespace grnrbt\materializedPath;

use yii\base\Behavior;
use yii\base\Event;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Materialized Path behavior for Yii2 uses postgres arrays.
 *
 * @property ActiveRecord|MaterializedPathBehavior $owner
 */
class MaterializedPathBehavior extends Behavior
{
    const OPERATION_MAKE_ROOT = 1;
    const OPERATION_PREPEND_TO = 2;
    const OPERATION_APPEND_TO = 3;
    const OPERATION_INSERT_BEFORE = 4;
    const OPERATION_INSERT_AFTER = 5;

    /**
     * Event which fire on changing of children's order.
     */
    const EVENT_CHILDREN_ORDER_CHANGED = 'childrenOrderChanged';

    /**
     * @var string
     */
    public $pathAttribute = 'path';

    /**
     * @var string
     */
    public $keyAttribute = 'id';

    /**
     * @var string
     */
    public $positionAttribute = 'position';

    /**
     * @var int
     */
    public $step = 100;

    /**
     * Child nodes will be reordering on changes.
     *
     * @var bool
     */
    public $autoChildrenReorder = true;

    /**
     * @var int
     */
    protected $operation;

    /**
     * @var ActiveRecord|MaterializedPathBehavior
     */
    protected $node;

    /**
     * @var string
     */
    protected $keyColumn;

    /**
     * @var string
     */
    protected $pathColumn;

    /**
     * @var string
     */
    protected $positionColumn;

    /**
     * @return string
     */
    public function getPathColumn()
    {
        return $this->pathColumn;
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        $handlers = [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
        if ($this->autoChildrenReorder === true) {
            $handlers[static::EVENT_CHILDREN_ORDER_CHANGED] = 'onChildrenOrderChanged';
        }
        return $handlers;
    }

    /**
     * Returns path of self node.
     *
     * @param bool $asArray = true Return array instead string
     * @return array|string
     */
    public function getPath($asArray = true)
    {
        $str = $this->owner->{$this->pathAttribute};
        return $asArray ? $this->pathStrToArray($str) : $str;
    }

    /**
     * Returns path from root to parent node.
     *
     * @param bool $asArray = true
     * @return null|string|array
     */
    public function getParentPath($asArray = true)
    {
        if ($this->owner->isRoot()) {
            return null;
        }
        $path = $this->owner->getPath();
        array_pop($path);
        return $asArray ? $path : $this->pathArrayToStr($path);
    }

    /**
     * Return Level of self node.
     *
     * @return int
     */
    public function getLevel()
    {
        return count($this->getPath());
    }

    /**
     * @inheritdoc
     * @param ActiveRecord $owner
     */
    public function attach($owner)
    {
        if ($this->keyAttribute === null) {
            $primaryKey = $owner->primaryKey();
            if (!isset($primaryKey[0])) {
                throw new Exception('"' . $owner->className() . '" must have a primary key.');
            }
            $this->keyAttribute = $primaryKey[0];
        }

        $table = $owner->tableName();
        $this->keyColumn = "{$table}.{$this->keyAttribute}";
        $this->pathColumn = "{$table}.{$this->pathAttribute}";
        $this->positionColumn = "{$table}.{$this->positionAttribute}";

        parent::attach($owner);
    }

    /**
     * Returns list of parents from root to self node.
     *
     * @param int $depth = null
     * @return \yii\db\ActiveQuery
     */
    public function getParents($depth = null)
    {
        $path = $this->getParentPath();
        if ($path === null) {
            $path = [];
        } elseif ($depth !== null) {
            $path = array_slice($path, -$depth);
        }

        /** @var \yii\db\ActiveQuery $query */
        $query = $this->owner->find();
        $query
            ->andWhere([$this->keyColumn => $path])
            ->addOrderBy([$this->pathColumn => SORT_ASC]);
        $query->multiple = true;
        return $query;
    }

    /**
     * Return closest parent.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        $query = $this->getParents(1)->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * Returns root node in self node's subtree.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRoot()
    {
        $path = $this->owner->getPath();
        $path = array_shift($path);
        $query = $this->owner->find();
        /** @var \yii\db\ActiveQuery $query */
        $query
            ->andWhere([$this->keyColumn => $path])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * Returns descendants as plane list.
     *
     * @param int $depth = null
     * @param bool $andSelf = false
     * @return \yii\db\ActiveQuery
     */
    public function getDescendants($depth = null, $andSelf = false)
    {
        $keyValue = $this->owner->{$this->keyAttribute};
        /** @var \yii\db\ActiveQuery $query */
        $query = $this->owner->find();
        $query->andWhere("{$this->pathColumn} && array[{$keyValue}]");
        if (!$andSelf) {
            $query->andWhere(["!=", "{$this->keyColumn}", $keyValue]);
        }
        if ($depth !== null) {
            $maxLevel = $depth + $this->getLevel();
            $query->andWhere($this->getLevelCondition($maxLevel, "<="));
        }
        $query
            ->addOrderBy([
                "array_length({$this->pathColumn}, 1)" => SORT_ASC,
                "{$this->positionColumn}" => SORT_ASC,
                "{$this->keyColumn}" => SORT_ASC,
            ]);
        $query->multiple = true;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->getDescendants(1);
    }

    /**
     * Return previous sibling.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPrev()
    {
        return $this->getNearestSibling("prev");
    }

    /**
     * Return next sibling.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getNext()
    {
        return $this->getNearestSibling("next");
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->owner->getLevel() == 1;
    }

    /**
     * @param MaterializedPathBehavior $node
     * @return bool
     */
    public function isDescendantOf($node)
    {
        $key = $node->{$this->keyAttribute};
        $path = $this->owner->getPath();
        $result = in_array($key, $path);
        return $result;
    }

    /**
     * @param ActiveRecord|MaterializedPathBehavior $node
     * @return bool
     */
    public function isChildOf($node)
    {
        if ($node->getIsNewRecord()) {
            return false;
        }

        $result = $node->{$this->keyAttribute} == $this->owner->getParentKey();
        return $result;
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return count($this->owner->children) === 0;
    }

    /**
     * @return ActiveRecord
     */
    public function makeRoot()
    {
        $this->operation = static::OPERATION_MAKE_ROOT;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function prependTo($node)
    {
        $this->operation = static::OPERATION_PREPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function appendTo($node)
    {
        $this->operation = static::OPERATION_APPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertBefore($node)
    {
        $this->operation = static::OPERATION_INSERT_BEFORE;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertAfter($node)
    {
        $this->operation = static::OPERATION_INSERT_AFTER;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeSave()
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }
        if ($this->owner->getIsNewRecord()) {
            $this->owner->{$this->pathAttribute} = $this->pathArrayToStr([]);
        }
        switch ($this->operation) {
            case static::OPERATION_MAKE_ROOT:
                $this->makeRootInternal();
                break;
            case static::OPERATION_PREPEND_TO:
                $this->insertIntoInternal(false);
                break;
            case static::OPERATION_APPEND_TO:
                $this->insertIntoInternal(true);
                break;
            case static::OPERATION_INSERT_BEFORE:
                $this->insertNearInternal(false);
                break;
            case static::OPERATION_INSERT_AFTER:
                $this->insertNearInternal(true);
                break;
            default:
                if ($this->owner->getIsNewRecord()) {
                    throw new NotSupportedException('Method "' . $this->owner->className() . '::insert" is not supported for inserting new nodes.');
                }
                $path = $this->owner->getParentPath();
                $path[] = $this->owner->{$this->keyAttribute};
                $this->owner->{$this->pathAttribute} = $this->pathArrayToStr($path);
        }
    }

    public function afterInsert()
    {
        if ($this->owner->{$this->pathAttribute} === $this->pathArrayToStr([])) {
            $key = $this->owner->{$this->keyAttribute};
            if ($this->operation === static::OPERATION_MAKE_ROOT) {
                $path = $this->pathArrayToStr([$key]);
            } else {
                if ($this->operation === static::OPERATION_INSERT_BEFORE || $this->operation === static::OPERATION_INSERT_AFTER) {
                    $path = $this->node->getParentPath();
                } else {
                    $path = $this->node->getPath();
                }
                $path[] = $key;
                $path = $this->pathArrayToStr($path);
            }
            $this->owner->{$this->pathAttribute} = $path;
            $this->owner->updateAll([$this->pathAttribute => $path], [$this->keyAttribute => $key]);
        }

        $this->fireEvent();
        $this->operation = null;
        $this->node = null;
    }

    /**
     * @param \yii\db\AfterSaveEvent $event
     */
    public function afterUpdate($event)
    {
        $this->moveNode($event->changedAttributes);
        $this->fireEvent();
        $this->operation = null;
        $this->node = null;
    }

    /**
     * @throws Exception
     */
    public function beforeDelete()
    {
        if ($this->owner->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }
    }

    public function afterDelete()
    {
        $this->owner->deleteAll($this->getDescendants(null, true)->where);
    }

    /**
     * Reorders children with values of $sortAttribute begin from zero.
     *
     * @param bool $asSingleQuery = true Run reordering in single query bypassing models.
     * NOTE: position will be not update in models. Only in database.
     * @throws \Exception
     */
    public function reorderChildren($asSingleQuery = true)
    {
        \Yii::$app->getDb()->transaction(function () use ($asSingleQuery) {
            if ($asSingleQuery) {
                $table = $this->owner->tableName();
                $keyField = $this->keyAttribute;
                $pathField = $this->pathAttribute;
                $positionField = $this->positionAttribute;
                $parentKey = $this->owner->{$this->keyAttribute};
                $step = $this->step;
                \Yii::$app->getDb()->createCommand("
                    DO $$
                      DECLARE
                        pos integer := 0;
                        row record;
                      BEGIN
                        FOR row in
                          SELECT {$keyField}
                          FROM {$table}
                          WHERE {$pathField} && array[{$parentKey}] AND {$keyField} != {$parentKey}
                        LOOP
                          UPDATE {$table} set {$positionField} = pos * {$step}
                          WHERE {$keyField} = row.{$keyField};
                          pos := pos + 1;
                        END LOOP;
                    END $$;
                ")->execute();
            } else {
                foreach ($this->getChildren()->each() as $i => $child) {
                    $child->{$this->positionAttribute} = ($i - 1) * $this->step;
                    $child->save(false, [$this->positionAttribute]);
                }
            }
        });
    }

    /**
     * Returns descendants nodes as tree with self node in the root.
     *
     * @param int $depth = null
     * @return MaterializedPathBehavior|ActiveRecord
     */
    public function populateTree($depth = null)
    {
        /** @var ActiveRecord|MaterializedPathBehavior $nodes */
        $nodes = $this
            ->getDescendants($depth)
            ->indexBy($this->keyAttribute)
            ->all();
        $relates = [];
        foreach ($nodes as $key => $node) {
            $path = $node->getParentPath();
            $parentKey = array_pop($path);
            if (!isset($relates[$parentKey])) {
                $relates[$parentKey] = [];
            }
            $relates[$parentKey][] = $node;
        }
        $nodes[$this->owner->{$this->keyAttribute}] = $this->owner;
        foreach ($relates as $key => $children) {
            $nodes[$key]->populateRelation('children', $children);
        }
        return $this->owner;
    }

    /**
     * Returns key of parent.
     *
     * @return mixed|null
     */
    public function getParentKey()
    {
        if ($this->owner->isRoot()) {
            return null;
        }
        $path = $this->getParentPath();
        return array_pop($path);
    }

    /**
     * @param bool $forInsertNear
     * @throws Exception
     */
    protected function checkNode($forInsertNear = false)
    {
        if ($forInsertNear && $this->node->isRoot()) {
            throw new Exception('Can not move a node before/after root.');
        }
        if ($this->node->getIsNewRecord()) {
            throw new Exception('Can not move a node when the target node is new record.');
        }
        if ($this->owner->equals($this->node)) {
            throw new Exception('Can not move a node when the target node is same.');
        }
        if ($this->node->isDescendantOf($this->owner)) {
            throw new Exception('Can not move a node when the target node is child.');
        }
    }

    /**
     * Make root operation internal handler
     */
    protected function makeRootInternal()
    {
        $key = $this->owner->{$this->keyAttribute};
        $this->owner->{$this->pathAttribute} = $key !== null
            ? $this->pathArrayToStr([$key])
            : $this->pathArrayToStr([]);
        if ($this->positionAttribute !== null) {
            $this->owner->{$this->positionAttribute} = 0;
        }
    }

    /**
     * Append to operation internal handler
     *
     * @param bool $append
     * @throws Exception
     */
    protected function insertIntoInternal($append)
    {
        $this->checkNode(false);
        $key = $this->owner->{$this->keyAttribute};
        if ($key !== null) {
            $path = $this->node->{$this->pathAttribute};
            $path[] = $key;
            $this->owner->{$this->pathAttribute} = $this->pathArrayToStr($path);
        }
        if ($this->positionAttribute !== null) {
            $to = $this->node->getChildren()->orderBy(null);
            $to = $append ? $to->max($this->positionAttribute) : $to->min($this->positionAttribute);
            if (
                !$this->owner->getIsNewRecord() && (int)$to === $this->owner->{$this->positionAttribute}
                && !$this->owner->getDirtyAttributes([$this->pathAttribute])
            ) {
            } elseif ($to !== null) {
                $to += $append ? $this->step : -$this->step;
            } else {
                $to = 0;
            }
            $this->owner->{$this->positionAttribute} = $to;
        }
    }

    /**
     * Insert operation internal handler
     *
     * @param bool $forward
     * @throws Exception
     */
    protected function insertNearInternal($forward)
    {
        $this->checkNode(true);
        $key = $this->owner->{$this->keyAttribute};
        if ($key !== null) {
            $path = $this->node->getParentPath($this->pathAttribute);
            $path[] = $key;
            $this->owner->{$this->pathAttribute} = $this->pathArrayToStr($path);
        }
        if ($this->positionAttribute !== null) {
            $position = $this->node->{$this->positionAttribute};
            if ($forward) {
                $position++;
            } else {
                $position--;
            }
            $this->owner->{$this->positionAttribute} = $position;
        }
    }

    /**
     * @param array $changedAttributes
     * @throws Exception
     */
    protected function moveNode($changedAttributes)
    {
        $oldPath = isset($changedAttributes[$this->pathAttribute])
            ? $changedAttributes[$this->pathAttribute]
            : $this->owner->{$this->pathAttribute};
        $update = [];
        $params = [];

        $condition = ['and', "{$this->pathColumn} && array[{$this->owner->{$this->keyAttribute}}]"];

        if (isset($changedAttributes[$this->pathAttribute])) {
            $newParentPath = $this->owner->getParentPath(false);
            $oldParentLevel = count($this->pathStrToArray($oldPath)) - 1;
            $update['path'] = new Expression("{$newParentPath} || {$this->pathAttribute}[{$oldParentLevel}:array_length({$this->pathAttribute}, 1)]");
            $params[':pathOld'] = $oldPath;
            $params[':pathNew'] = $this->owner->getAttribute($this->pathAttribute);
        }
        if (!empty($update)) {
            $this->owner->updateAll($update, $condition, $params);
        }
    }

    /**
     * @param string $order "prev"|"next"
     * @return \yii\db\ActiveQuery
     * @throws Exception
     */
    protected function getNearestSibling($order)
    {
        $path = $this->owner->getParentPath();
        if ($path === null) {
            // TODO: fix it.
            return [];
        }
        $keysStr = implode(",", $path);

        /** @var \yii\db\ActiveQuery $query */
        $query = $this->owner->find();
        $query
            ->andWhere("{$this->pathColumn} && array[{$keysStr}]")
            ->andWhere($this->getLevelCondition($this->owner->getLevel()))
            ->limit(1);

        if ($order === "prev") {
            $query
                ->andWhere(['<', $this->positionColumn, $this->owner->{$this->positionAttribute}])
                ->orderBy([$this->positionAttribute => SORT_DESC]);
        } elseif ($order === "next") {
            $query
                ->andWhere(['>', $this->positionColumn, $this->owner->{$this->positionAttribute}])
                ->orderBy([$this->positionAttribute => SORT_ASC]);

        } else {
            throw new Exception("Invalid value of \$order argument.");
        }
        $query->multiple = false;
        return $query;
    }

    /**
     * @param int $level
     * @param string $sign
     * @param string $tableName = null
     * @return string
     */
    protected function getLevelCondition($level, $sign = "=", $tableName = null)
    {
        $pathColumn = $tableName === null ? $this->pathColumn : "{$tableName}.{$this->pathAttribute}";
        return "array_length({$pathColumn}, 1) {$sign} {$level}";
    }

    /**
     * @param int $parentKey
     * @param string $tableName = null
     * @return string
     */
    protected function getChildrenCondition($parentKey, $tableName = null)
    {
        $pathColumn = $tableName === null ? $this->pathColumn : "{$tableName}.{$this->pathAttribute}";
        return "{$pathColumn} && array[{$parentKey}]";
    }

    /**
     * Convert path values from string to array
     *
     * @param string $path
     * @return array
     */
    protected function pathStrToArray($path)
    {
        return explode(",", trim($path, "{}"));
    }

    /**
     * Convert path values from  array to string
     *
     * @param array $path
     * @return string
     */
    protected function pathArrayToStr($path)
    {
        return "{" . implode(",", $path) . "}";
    }

    protected function fireEvent()
    {
        if (
            $this->operation === static::OPERATION_PREPEND_TO ||
            $this->operation === static::OPERATION_INSERT_AFTER ||
            $this->operation === static::OPERATION_INSERT_BEFORE
        ) {
            Event::trigger(get_class($this->owner), static::EVENT_CHILDREN_ORDER_CHANGED, new ChildrenOrderChangedEvent([
                'parent' => $this->owner->parent,
            ]));
        }
    }

    public function onChildrenOrderChanged(ChildrenOrderChangedEvent $event)
    {
        $event->parent->reorderChildren(true);
    }
}