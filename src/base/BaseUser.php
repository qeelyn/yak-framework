<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/8/21
 * Time: 下午4:29
 */

namespace yak\framework\base;



use ArrayAccess;
use Yii;
use yii\web\IdentityInterface;

/**
 * 用户抽象类,站点应用还需要继承实现IdentityInterface方法
 *
 * @property array $organizations 顶级组织信息,一个用户可能属于多个顶级组织
 * @property array $currentOrganization 当前顶级组织信息,当用户发生组织切换时,需要变更
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
    public function __construct($config = [],$data = [])
    {
        $this->attributes = $data;
    }

    protected $attributes = [];

    /**
     * @return int|string 当前用户ID
     */
    public function getId()
    {
        return $this->attributes['id'];
    }

    /**
     * @return string 当前用户的（cookie）认证密钥
     */
    public function getAuthKey()
    {
        return $this->attributes['auth_key'];
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
     * 获取当前组织id;
     * @return mixed
     */
    public function getOrganizations()
    {
        return $this->attributes['organizations'] ?? [];
    }

    /**
     * 获取当前组织id;
     * @return mixed
     */
    public function getCurrentOrganization()
    {
        return $this->attributes['currentOrganization'] ?? [];
    }

    /**
     * 获取用户角色
     * @return string[]
     */
    public function getRoles()
    {
        return $this->attributes['roles'];
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