<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/6/20
 * Time: 上午11:10
 */

return [
    [
        'name'=>'后台管理界面',
        'pattern'=> 'message/d/<controller>/<action>',
        'route'=>'ucenter/home/index',
        'defaults' => ['action' => 'index'],
    ],
    [
        'name'=>'api请求',
        'pattern'=> 'message/api/<controller>/<action>',
        'route'=>'message/<controller>/<action>',
        'defaults' => ['action' => 'index'],
    ],
];