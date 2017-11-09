<?php

use yii\web\Response;

$params = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);
$db = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/db.php'),
    require(__DIR__ . '/db-local.php')
);

$config = [
    'id' => 'message',
    'params' => $params,
    'basePath' => dirname(__DIR__),
    'defaultRoute'=>'admin/index',
    'language' => 'en',
    'sourceLanguage' => 'zh-CN',
    'timeZone'=>'Asia/Shanghai',
    'bootstrap' => [
        'log',
        [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'text/html' => Response::FORMAT_HTML,
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ],
            'languages' => [
                'en',
                'de',
            ],
        ],
    ],
    'components' => [
        'db' => $db,
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'iOcTLfWcevXSGqTMuxcHlBYcdhzmQOIw',
            'enableCookieValidation' => true,
            'enableCsrfValidation' => false, //nodejs开发时为false
            'parsers'=>[
                'application/json' => 'yii\web\JsonParser'
            ],
        ],
        'response' => [
            'format' => Response::FORMAT_HTML,
        ],
        'user' => [
            'identityClass' => 'yakunit\framework\ContextUser',
            'enableAutoLogin' => true,//if web you can set true,api must set false
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'maxFileSize' => 200,
                    'levels' => [],
                    'logVars' => [],
                    'logFile' => '@runtime/logs/' . date('ymd') . '.log',
                ],

            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => require (__DIR__ . '/routes.php'),
        ],
        'assetManager' => [
            'forceCopy' => true
        ],
        'idGenerator'=>[
            'class'=>'yak\framework\model\SnowflakeIdGenerator',
        ],
        'cache' =>[
            'class'=>\yii\caching\FileCache::className(),
        ],
    ],
    'aliases' => [
        '@bower' => __DIR__ . '/../../vendor/bower',
    ],
    'modules' => [
        'yakmessage' => [
            'class' => 'yak\message\Module',
        ]
    ],
];
return $config;