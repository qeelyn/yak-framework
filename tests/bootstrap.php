<?php

// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../tests/config/main.php'),
    require(__DIR__ . '/../tests/config/main-local.php')
);

Yii::setAlias('@yak/framework', __DIR__.'/../src');
Yii::setAlias('@yakunit/framework', __DIR__);
//(new yii\web\Application($config))->run();
