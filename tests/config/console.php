<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2016/12/29
 * Time: 下午3:21
 */
$params=[];
$db = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/db.php'),
    require(__DIR__ . '/db-local.php')
);
return [
    'id' => 'yak-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'components' => [
        'log' => [
            'flushInterval' => 1,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => [],
                    //控制台一般不需要服务器信息
                    'logVars' => [],
                    'exportInterval' => 1,
                ],
            ],
        ],
        'db' => $db,
        'idGenerator'=>[
            'class'=>'yak\framework\model\SnowflakeIdGenerator',
            'workerId'=>'1',
            'mutex'=>[
                'class' => 'yii\mutex\FileMutex'
            ],
        ],
    ],
    'modules'=>[
    ],
    'params' => $params,
];