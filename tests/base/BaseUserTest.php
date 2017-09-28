<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/14
 * Time: 下午2:36
 */

namespace yakunit\framework\captcha;


use yakunit\framework\TestCase;
use yak\framework\base\BaseUser;

class BaseUserTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    public function testGetAssignments()
    {
        $as = [
            ['user_id' => 1,'item_id'=>1,],
            ['user_id' => 1,'item_id'=>1,'organization_id'=>2],
            ['user_id' => 1,'item_id'=>2,'organization_id'=>2],
            ['user_id' => 1,'item_id'=>1,'organization_id'=>1],
        ];

        $a = new class extends BaseUser{
            public static function findIdentity($id)
            {
                // TODO: Implement findIdentity() method.
            }
            public static function findIdentityByAccessToken($token, $type = null)
            {
                // TODO: Implement findIdentityByAccessToken() method.
            }
        };
        $a->setAssignments($as);
        $d = $a->getAssignments();
        $this->assertCount(1,$d);
        $d = $a->getAssignments(1);
        $this->assertCount(2,$d);
        $d = $a->getAssignments(2);
        $this->assertCount(3,$d);
    }
}
