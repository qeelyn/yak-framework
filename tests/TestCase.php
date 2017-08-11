<?php

namespace yakunit\framework;

use yii\console\Application;
use yii\helpers\ArrayHelper;

/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public static $params;

    /**
     * Clean up after test.
     * By default the application created with [[mockApplication]] will be destroyed.
     */
    protected function tearDown()
    {
        parent::tearDown();
        $this->destroyApplication();
    }

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name params name
     * @param  mixed $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null)
    {
        if (static::$params === null) {
            static::$params = require(__DIR__ . '/config/params.php');
        }

        return isset(static::$params[$name]) ? static::$params[$name] : $default;
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\yii\console\Application')
    {
        $cf = ArrayHelper::merge(
            $c1 = require(__DIR__ . '/config/console.php'),
            $c2 = require(__DIR__ . '/config/console-local.php')
        );

        new $appClass(ArrayHelper::merge($cf,[
            'id' => 'testapp',
            'vendorPath' => $this->getVendorPath(),
        ], $config));
    }

    protected function mockWebApplication($configs = [], $appClass = '\yii\web\Application')
    {
        $cf = ArrayHelper::merge(
            $c1 = require(__DIR__ . '/config/main.php'),
            $c2 = require(__DIR__ . '/config/main-local.php')
        );
        foreach($cf['bootstrap'] as $key=>$item){
            if($item == 'log1' || $item == 'debug'){
                unset($cf['bootstrap'][$key]);
            }
        }
        unset($cf['modules']['debug']);
//        $cf['components']['log'] = [
//            'targets'=>[
//                [
//                    'class' => 'yii\log\FileTarget',
//                    'maxFileSize'=> 200,
//                    'levels' => ['trace'],
//                    'logVars' => [],
//                    'logFile' => '@runtime/logs/'.date('ymd').'.log',
//                ],
//            ],
//        ];
//        unset($cf['modules']['debug']);
        new $appClass(ArrayHelper::merge($cf, [
            'id' => 'testapp',
            'vendorPath' => $this->getVendorPath(),
            'components' => [
                'request' => [
//                    'cookieValidationKey' => 'wefJDF8sfdsfSDefwqdxj9oq',
                    'scriptFile' => __DIR__ .'/index.php',
                    'scriptUrl' => '/index.php',
                ],
            ]
        ],$configs));
        $this->setCurrentUser();
    }

    protected function setCurrentUser(){
        \Yii::$app->user->setIdentity(new ContextUser([
            'id'=>'1',
            'nickname'=>'admin',
            'first_name'=>'',
            'last_name'=>'',
            'gender'=>'1',
            'avatar'=>'bd',
            'currentOrganization'=>[
                'id'=> 1000,
            ],
        ]));
    }

    protected function getVendorPath()
    {
        $vendor = dirname(dirname(__DIR__)) . '/vendor';
        if (!is_dir($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(__DIR__))));
        }
        return $vendor;
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        if (\Yii::$app && \Yii::$app->has('session', true)) {
            \Yii::$app->session->close();
        }
        \Yii::$app = null;
    }

    /**
     * Asserting two strings equality ignoring line endings
     *
     * @param string $expected
     * @param string $actual
     */
    public function assertEqualsWithoutLE($expected, $actual)
    {
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Invokes a inaccessible method
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     * @return mixed
     * @since 2.0.11
     */
    protected function invokeMethod($object, $method, $args = [], $revoke = true)
    {
        $reflection = new \ReflectionClass($object->className());
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        $result = $method->invokeArgs($object, $args);
        if ($revoke) {
            $method->setAccessible(false);
        }
        return $result;
    }

    /**
     * Sets an inaccessible object property to a designated value
     * @param $object
     * @param $propertyName
     * @param $value
     * @param bool $revoke whether to make property inaccessible after setting
     * @since 2.0.11
     */
    protected function setInaccessibleProperty($object, $propertyName, $value, $revoke = true)
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($value);
        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    /**
     * Gets an inaccessible object property
     * @param $object
     * @param $propertyName
     * @param bool $revoke whether to make property inaccessible after getting
     * @return mixed
     */
    protected function getInaccessibleProperty($object, $propertyName, $revoke = true)
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $result = $property->getValue($object);
        if ($revoke) {
            $property->setAccessible(false);
        }
        return $result;
    }
}
