<?php

namespace grnrbt\yii2\materializedPath;

use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Trait for ActiveRecords uses MaterializedPathBehavior.
 */
trait MaterializedPathQueryTrait
{
    /**
     * @var MaterializedPathBehavior|ActiveRecord
     * */
    private $model;

    /**
     * @return \yii\db\ActiveQuery|MaterializedPathQueryTrait
     */
    public function roots()
    {
        return $this->andWhere(['=', $this->getModel()->getPathColumn(), '{}']);
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Parent node or its key.
     * @param int $depth = null
     * @param bool $andSelf = false
     * @return \yii\db\ActiveQuery|MaterializedPathQueryTrait
     */
    public function descendantsOf($node, $depth = null, $andSelf = false)
    {
        $keyValue = is_object($node)
            ? $node->{$this->getModel()->keyAttribute}
            : $node;
        $this->andWhere("{$this->getModel()->getPathColumn()} && array[{$keyValue}]");
        if (!$andSelf) {
            $this->andWhere(["!=", "{$this->getModel()->getKeyColumn()}", $keyValue]);
        }
        if ($depth !== null) {
            if (is_object($node)) {
                $maxLevel = $depth + $node->getLevel();
                $this->andWhere("{$this->getLevelExpression()} <= {$maxLevel}");
            } else {
                $this->andWhere([
                    "<=",
                    $this->getLevelExpression(),
                    $this->getQuery()
                        ->select(new Expression("{$this->getLevelExpression()} + {$depth}"))
                        ->andWhere([$this->getModel()->getKeyColumn() => $keyValue]),
                ]);
            }
        }
        $this
            ->addOrderBy([
                $this->getLevelExpression() => SORT_ASC,
                "{$this->getModel()->getPositionColumn()}" => SORT_ASC,
                "{$this->getModel()->getKeyColumn()}" => SORT_ASC,
            ]);
        $this->multiple = true;
        return $this;
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Parent node or its key.
     * @return \yii\db\ActiveQuery|MaterializedPathQueryTrait
     */
    public function childrenOf($node)
    {
        return $this->descendantsOf($node, 1);
    }

    /**
     * @param MaterializedPathBehavior|mixed $node Specified node or its key.
     * @param int $depth = null
     * @return MaterializedPathQueryTrait|\yii\db\ActiveQuery
     */
    public function parentsOf($node, $depth = null)
    {
        if (is_object($node)) {
            $path = $node->getPath();
            if ($path === null) {
                $path = [];
            } elseif ($depth !== null) {
                $path = array_slice($path, -$depth);
            }
            $this->andWhere([$this->getModel()->getKeyColumn() => $path]);
        } else {
            /*
            SELECT *
            FROM "tree"
            WHERE (
              ARRAY["tree"."id"] && (
                SELECT "tree"."path"[(array_length("tree"."path", 1) - :depth ):array_length("tree"."path", 1)]
                FROM "tree"
                WHERE "tree"."id" = :id
              )
            )
            ORDER BY "tree"."path"
            */
            $lowerBound = $depth === null
                ? 0
                : "({$this->getLevelExpression()} - {$depth} + 1)";
            $upperBound = $this->getLevelExpression();
            $nodePathQuery = $this->getQuery()
                ->select(new Expression("{$this->getModel()->getPathColumn()}[{$lowerBound}:({$upperBound})]"))
                ->andWhere([$this->getModel()->getKeyColumn() => $node]);
            $this->andWhere([
                "&&",
                "ARRAY[{$this->getModel()->getKeyColumn()}]",
                $nodePathQuery,
            ]);
        }
        $this->addOrderBy([$this->getModel()->getPathColumn() => SORT_ASC]);
        $this->multiple = true;
        return $this;
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Specified node or its key.
     * @return MaterializedPathQueryTrait|\yii\db\ActiveQuery
     */
    public function parentOf($node)
    {
        $query = $this->parentsOf($node, 1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Specified node or its key.
     * @return MaterializedPathQueryTrait|\yii\db\ActiveQuery
     */
    public function nextOf($node)
    {
        return $this->nearestSiblingOf($node, "next");
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Specified node or its key.
     * @return MaterializedPathQueryTrait|\yii\db\ActiveQuery
     */
    public function prevOf($node)
    {
        return $this->nearestSiblingOf($node, "prev");
    }

    /**
     * @param MaterializedPathQueryTrait|mixed $node Specified node or its key.
     * @param string $direction next|prev
     * @return MaterializedPathQueryTrait|\yii\db\ActiveQuery
     */
    private function nearestSiblingOf($node, $direction)
    {
        /*
        FOR KEY:
            SELECT *
            FROM "tree"
            WHERE "tree"."path" && (
              SELECT "tree"."path" [1 :(array_length("tree"."path", 1) - 1)]
              FROM "tree"
              WHERE "tree"."id" = :id
            ) AND array_length("tree"."path", 1) = (
              SELECT array_length("tree"."path", 1)
              FROM "tree"
              WHERE "tree"."id" = :id
            ) AND "tree"."position" > (
              SELECT "tree"."position"
              FROM "tree"
              WHERE "tree"."id" = :id
            )
            ORDER BY "position"
            LIMIT 1

        FOR OBJECT:
            SELECT *
            FROM "tree"
            WHERE "tree"."path" && :nodeParentPath
            AND array_length("tree"."path", 1) = :nodeLevel
            AND "tree"."position" > :ndePosition
            ORDER BY "position"
            LIMIT 1
         */
        $pathCol = $this->getModel()->getPathColumn();
        $keyCol = $this->getModel()->getKeyColumn();
        $positionCol = $this->getModel()->getPositionColumn();
        $positionAttr = $this->getModel()->positionAttribute;

        if (is_object($node) && $node->getParentPath() === null) {
            return $this->getQuery()->where("false"); // `false` must be a string
        }

        $pathCondition = is_object($node)
            ? $node->getParentPath(false)
            : $this->getQuery()
                ->select(new Expression("{$pathCol}[1:({$this->getLevelExpression()} - 1)]"))
                ->andWhere([$keyCol => $node]);
        $levelCondition = is_object($node)
            ? $node->getLevel()
            : $this->getQuery()
                ->select(new Expression($this->getLevelExpression()))
                ->andWhere([$keyCol => $node]);
        $positionCondition = is_object($node)
            ? $node->{$positionAttr}
            : $this->getQuery()
                ->select($positionCol)
                ->andWhere([$keyCol => $node]);

        $this
            ->andWhere(['&&', $pathCol, $pathCondition])
            ->andWhere(['=', $this->getLevelExpression(), $levelCondition])
            ->andWhere([($direction == 'prev' ? '<' : '>'), $positionCol, $positionCondition])
            ->orderBy([$positionAttr => ($direction == 'prev' ? SORT_DESC : SORT_ASC)])
            ->limit(1);
        $this->multiple = false;
        return $this;
    }

    /**
     * @return \yii\db\ActiveQuery|MaterializedPathQueryTrait
     */
    private function getQuery()
    {
        $model = $this->getModel();
        return $model::find();
    }

    /**
     * @return MaterializedPathBehavior|mixed|ActiveRecord
     */
    private function getModel()
    {
        if ($this->model === null) {
            /** @var \yii\db\ActiveQuery $this */
            $class = $this->modelClass;
            $this->model = new $class;

        }
        return $this->model;
    }

    /**
     * @return string
     */
    private function getLevelExpression()
    {
        return "coalesce(array_length({$this->getModel()->getPathColumn()}, 1), 0)";
    }
}
