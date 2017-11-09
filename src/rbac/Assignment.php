<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/11/8
 * Time: 下午5:31
 */

namespace yak\framework\rbac;


use yii\rbac\Assignment as NativeAssignment;

class Assignment extends NativeAssignment
{
    public $organizationId;
}