<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/14
 * Time: 下午2:36
 */

namespace yakunit\framework\captcha;


use yakunit\framework\TestCase;

class GeeCaptchaActionTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }
}
