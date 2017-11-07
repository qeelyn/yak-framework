<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/8/21
 * Time: 下午4:29
 */

namespace yak\framework\base;


use ArrayAccess;
use yii\base\InvalidParamException;
use yii\helpers\ArrayHelper;
use yii\rbac\Assignment;
use yii\web\IdentityInterface;

/**
 * 用户抽象类,站点应用还需要继承实现IdentityInterface方法
 *
 *
 * @property array $organizations 顶级组织信息,一个用户可能属于多个顶级组织
 * @property array $assignments default键表示用户的所有角色,组织ID键包含了特定组织下的角色及未分配组织的角色.
 * @property array $departOrganization 部门级别组织信息
 * @package yak\platform\components
 */
abstract class BaseUser implements IdentityInterface, ArrayAccess
{
    /**
     * ContextUser constructor.
     * @param $config
     * @param $data
     */
    public function __construct($config = [], $data = [])
    {
        $this->attributes = $data;
        if(isset($data['assignments']) && !isset($data['assignments']['default'])){
            $this->attributes['assignments'] = self::setAssignments($data['assignments']);
        }
    }

    protected $attributes = [];

    /**
     * @return int|string 当前用户ID
     */
    public function getId()
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * @return string 当前用户的（cookie）认证密钥
     */
    public function getAuthKey()
    {
        return $this->attributes['auth_key'] ?? null;
    }

    /**
     * @param string $authKey
     * @return boolean if auth key is valid for current user
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * 获取当前作业的组织ID
     * @param bool $throwException 是否抛出异常
     * @return array|int|mixed
     */
    public function getCurrentOrganizationId($throwException = true)
    {
        $id = isset($this->attributes['currentOrganizationId']) ? $this->attributes['currentOrganizationId'] : null;
        if($id == null){
            $id = \Yii::$app->request->get('token_oid') ?? \Yii::$app->request->post('token_oid',0);
            if(!$id){
                if(\Yii::$app->user->enableSession){
                    $id = \Yii::$app->session->get('currentOrganizationId');
                }elseif($throwException){
                    throw new InvalidParamException('miss or empty params: token_oid');
                }
            }
            $this->attributes['currentOrganizationId'] = $id;
        }

        return $id;
    }

    /**
     * 获取用户的参与的组织;
     * @return mixed
     */
    public function getOrganizations()
    {
        return $this->attributes['organizations'] ?? [];
    }

    /**
     * 获取用户角色,如果不指定组织,则为用户的所有角色
     * @param int $organizationId 指定组织的角色
     * @return array
     */
    public function getAssignments($organizationId = 0)
    {
        if ($organizationId && ($this->attributes['assignments'][$organizationId]??false)) {
            return $this->attributes['assignments'][$organizationId];
        }
        return $this->attributes['assignments']['default'] ?? [];
    }

    /**
     * 将assignments数组转化为按组织id为key的数组
     * @param array $assignments 包含
     * @return array
     */
    public static function setAssignments($assignments)
    {
        $result = [];
        $orgIds = array_unique(array_filter(ArrayHelper::getColumn($assignments,'organization_id')));
        foreach ($assignments as $key=>$item){
            if($item['organization_id'] ?? false){
                $result[$item['organization_id']][] = $item;
                $result['default'][$key] = $item;
            } else {
                if($orgIds){
                    foreach ($orgIds as $orgId){
                        $result[$orgId][$key] = $item;
                    }
                }
                $result['default'][$key] = $item;
            }
        }
        return $result;
    }


    //below is implement method as array
    public function getAttributes()
    {
        return $this->attributes;
    }

    public function __get($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]) ? $attributes[$key] : null;
    }

    public function __isset($name)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$name]);
    }


    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __unset($name)
    {
        unset($this->attributes[$name]);
    }

    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }


}