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
     * @param array $pagination
     * @param Connection $db
     * @return array
     * @throws \Exception
     */
    public static function getPagingResult(Query $query,array $pagination,$db = null)
    {
        $limit = 10;
        if (!(isset($pagination['after']) || isset($pagination['before']))) {
            throw new \Exception('参数错误');
        } elseif (isset($pagination['after'])) {
            $isAfterCursor = true;
            $cursor = $pagination['after'];
            $limit = $pagination['first'];
        } elseif (isset($pagination['before'])) {
            $cursor = $pagination['before'];
            $isAfterCursor = false;
            $limit = $pagination['last'];
        }

        $count = intval($query->count());
        $rows = $query->offset(new CursorBasedExpression($cursor, $isAfterCursor))
            ->limit($limit)
            ->createCommand($db)
            ->queryAll();

        if (count($rows)) {
            if (!$isAfterCursor) {
                $rows = array_reverse($rows);
            }
            $end = $rows[count($rows) - 1];
            $start = $rows[0];
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
        $result = [
            'pageInfo' => [
                'endCursor' => $end['cursor_row_number'],
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
                'startCursor' => $start['cursor_row_number'],
            ],
            'nodes' => $query->populate($rows),
            'count' => $count,
        ];
        return $result;
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