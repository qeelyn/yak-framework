<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/22
 * Time: 上午9:59
 */

namespace yak\framework\helper;


class TreeHelper
{
    /**
     * 构建父子关系的数据结构
     * 该数据结构关系由3个字段维护,'id','parent_id','children'
     *
     * @param array $data 需要包含'id','parent_id'的数据
     * @param callable $filter 数据过滤器,过滤掉不需要进入树的数据
     * @param callable $clear 数据过滤器,过滤掉不需要进入树的数据
     * ```php
     *      function($item){}
     * ```
     * @return array
     */
    public static function buildTree($data,callable $filter = null,callable $clear)
    {
        $tree = [];
        foreach ($data as $key => &$item) {
            if ($filter && !$filter($item)) {
                unset($data[$key]);
                continue;
            }
            if ($item['parent_id'] != 0 && isset($data[$item['parent_id']])) {
                $data[$item['parent_id']]['children'][] = &$data[$item['id']];
            } else {
                $tree[] = &$data[$item['id']];
            }
        }
        self::clearTree($tree,$clear);
        return $tree;
    }

    /**
     * @param $tree
     * @param callable|null $clear
     * @return bool
     */
    private static function clearTree(&$tree, callable $clear = null)
    {
        foreach ($tree as $key => &$item) {
            if (isset($item['children'])) {
                if (self::clearTree($item['children'],$clear)) {
                    unset($tree[$key]);
                }
            } elseif (call_user_func($clear,$item)) {
                //无子级,而且没有资料指向
                unset($tree[$key]);
            }
        }
        return empty($tree);
    }
}