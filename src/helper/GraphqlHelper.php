<?php
/**
 * Created by PhpStorm.
 * User: hxn
 * Date: 2016/10/31
 * Time: 17:38
 */

namespace yak\framework\helper;

use Yii;

use yii\db\CursorBasedExpression;
use yii\db\Query;


class GraphqlHelper
{

    /**
     * @param Query $query
     * @param $conditions
     * @return array
     * @throws \Exception
     */
    public static function getPagingResult($query, $conditions)
    {
        $limit = 10;
        if (!(isset($conditions['after']) || isset($conditions['before']))) {
            throw new \Exception('参数错误');
        } elseif (isset($conditions['after'])) {
            $isAfterCursor = true;
            $cursor = $conditions['after'];
            $limit = $conditions['first'];
        } elseif (isset($conditions['before'])) {
            $cursor = $conditions['before'];
            $isAfterCursor = false;
            $limit = $conditions['last'];
        }

        $count = intval($query->count());
        $result = $query->offset(new CursorBasedExpression($cursor, $isAfterCursor))
            ->limit($limit)
            ->all();

        if (count($result)) {
            if (!$isAfterCursor) {
                $result = array_reverse($result);
            }
            $end = $result[count($result) - 1];
            $start = $result[0];
        } else {
            $end = 0;
            $start = 0;
        }
        $hasNextPage = false;
        $hasPreviousPage = false;
        if ($count > $limit * $cursor) {
            $hasNextPage = true;
        }
        if ($cursor > 1) {
            $hasPreviousPage = true;
        }
        return [
            'pageInfo' => [
                'endCursor' => $end['cursor_row_number'],
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
                'startCursor' => $start['cursor_row_number'],
            ],
            'nodes' => $result,
            'count' => $count,
        ];
    }

    /**
     *  gid 进行 编码
     * @param $id
     * @param $code
     * @return null|string
     */
    public static function encodeGlobalId($id, $code)
    {
        if (!empty($id) && !empty($code)) {
            return base64_encode($code . ':' . $id);
        } else {
            return $id;
        }
    }

    /**
     *  gid 进行 解码
     * @param $globalId
     * @return array|null
     */
    public static function decodeGlobalId($globalId)
    {
        if (empty($globalId)) {
            return [
                'code' => null,
                'id' => $globalId,
            ];
        } else {
            $decode = explode(':', base64_decode($globalId));
            return [
                'code' => $decode[0],
                'id' => $decode[1],
            ];
        }
    }
}