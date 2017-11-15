<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/11/15
 * Time: 下午5:55
 */

namespace yak\framework\helper;


use yii\caching\TagDependency;
use yii\db\Query;

class UserHelper
{
    public static function invalidateCache()
    {
        TagDependency::invalidate(\Yii::$app->cache, __CLASS__);
    }

    public static function getUser($userId)
    {
        $key = ['user_', $userId];
        if (!($data = \Yii::$app->cache->get($key))) {
            $data = (new Query())->from('opm_user')->where('id=' . $userId)->one(\Yii::$app->db);
            \Yii::$app->cache->set($key, $data, 3600, new TagDependency([
                'tags' => __CLASS__
            ]));
        }
        return $data;
    }
}