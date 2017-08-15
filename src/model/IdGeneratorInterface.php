<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/4/20
 * Time: 下午5:56
 */

namespace yak\framework\model;


/**
 * ID生成接口,该接口主要提供ActiveRecord主键生成能力.
 *
 * 相当的配置方式:
 * ```php
 *  'idGenerator'=>[
 *     'class'=>'',
 *  ]
 * ```
 * 如果采用了数据库自增主键的方式
 * @package yak\platform\components
 */
interface IdGeneratorInterface
{
    /**
     * 获取ID值,由于32/64架构的差异,建议接口返回结果为string,以获得最大的兼容性
     * @return string
     */
    public function nextId();
}