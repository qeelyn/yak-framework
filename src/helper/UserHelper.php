<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/11/15
 * Time: 下午5:55
 */

namespace yak\framework\helper;


use yii\caching\TagDependency;
use yii\db\Expression;
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

    public static function getOrgUsers($orgId)
    {
        $data = (new Query())
            ->select('a.*')
            ->from(['a'=>'opm_user','o'=>'auth_organization_user'])
            ->where(['a.id'=>new Expression('[[o.user_id]]'),'organization_id'=>$orgId])
            ->all(\Yii::$app->db);
        return $data;
    }

    public static function getOrgRoles($orgId,$appCode)
    {
        $data = (new Query())
            ->from(['a'=>'auth_role','b'=>'auth_item_child','c'=>'auth_app'])
            ->where(['a.id'=>new Expression('[[b.child_id]]'),'b.child_type'=>'R'])
            ->andWhere(['b.parent_type'=>'R','b.parent_id'=>$orgId])
            ->andWhere(['a.app_id'=>new Expression('[[c.id]]'),'c.code'=>$appCode])
            ->all(\Yii::$app->db);
        return $data;
    }
}