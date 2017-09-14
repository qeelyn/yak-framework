Yak-framework
======

Yak的框架项目,本项目主要提供YAK微服务平台所需要的基础服务类.

## 安装

```php
    composer require qeelyn/yak-framework
```
## 组件

### 验证码
采用了GeeTest的云服务,开发时,采用GeeCaptchaAction与GeeCaptchaValidator结合使用,使用方式上类型Yii自带的
Captcha组件,具体可查看Yak的注册模块
```php
    /**
     * 站点的主控制器
     * @package app\controllers
     */
    class SiteController extends Controller
    {
        public function actions()
        {
            return array(
                // captcha action renders the CAPTCHA image displayed on the contact page
                'captcha' => array(
                    'class' => 'yii\captcha\CaptchaAction',
                    'backColor' => 0xFFFFFF,  //背景颜色
                    'minLength' => 4,  //最短为4位
                    'maxLength' => 4,   //是长为4位
                    'transparent' => true,  //显示为透明
                    'fixedVerifyCode' => YII_ENV_DEV ? 'test' : null,
                ),
                //无验证码方式
                'nocaptcha'=>[
                    'class'=> 'yak\framework\captcha\GeeCaptchaAction',
                    'appId'=>Yii::$app->params['geeCaptcha']['id'],
                    'appKey'=>Yii::$app->params['geeCaptcha']['key'],
                ],                
            );
        }
    
    }
```