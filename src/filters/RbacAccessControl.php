<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/4/19
 * Time: 下午4:34
 */

namespace yak\framework\filters;

use Yii;
use yii\base\Action;
use yii\base\Application;
use yii\filters\AccessRule;


/**
 * RBAC过滤器,利用YAK RBAC,对控制器MVC的权限控制
 *
 *
 * @package yak\framework\filters
 */
class RbacAccessControl extends \yii\filters\AccessControl
{
    /**
     *
     */
    public function init()
    {
        $this->rules[] = $this->createDefaultRule();
        parent::init();

    }

    /**
     * 创建基于RBAC的验证规则
     */
    protected function createDefaultRule()
    {
        /**
         * @param $rule AccessRule
         * @param $action Action
         * @return bool
         */
        $matchCallback = function ($rule, $action) {
            if (Yii::$app->user->isGuest) {
                return false;
            }
            $moduleId = $action->controller->module instanceof Application ? '' : $action->controller->module->id;
            $name = $moduleId . '.' . $action->controller->id . '.' . $action->id;
            return Yii::$app->getAuthManager()->checkAccess(Yii::$app->user->id, $name, Yii::$app->requestedParams);
        };
        return [
            'allow' => true,
            'matchCallback' => $matchCallback,
        ];
    }
}