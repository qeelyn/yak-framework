<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yak\framework\assets;

use yii\web\AssetBundle;
use yii\web\View;
use yii;

/**
 * @since 2.0
 */
class VueAsset extends AssetBundle
{
    public $sourcePath = '@webroot/../node_modules/vue/dist';


    public $css = [
    ];

    public $js = [
        'vue.min.js',
    ];


}
