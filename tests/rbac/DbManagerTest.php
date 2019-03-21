<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/8/30
 * Time: 下午7:05
 */

namespace yakunit\framework\components\rbac;

use yak\framework\rbac\DbManager;
use yakunit\framework\TestCase;
use yii\rbac\Permission;
use yii\rbac\Role;
use yii\rbac\Rule;

class DbManagerTest extends TestCase
{
    /**
     * @var DbManager
     */
    private $yca;

    protected function setUp()
    {
        $this->mockWebApplication();
        $this->yca = new DbManager([]);
//        \Yii::$app->set('authManager',new DbManager([]));
    }

    function testGetRolesByUser(){
        $this->yca->getRolesByUser(2);
    }

    function testLoadFromCache(){
        $this->yca->invalidateCache();
        $this->yca->loadFromCache();
        $this->yca->invalidateCache();
    }

    function testCheckAccessFromCache(){
        $items = new \ReflectionProperty($this->yca,'items');
        $items->setAccessible(true);
        $data = [
            'P1'=>new Permission(['type'=>'P','name'=>'P1','description'=>'']),
            'P2'=>new Permission(['type'=>'P','name'=>'P2','description'=>'createPost']),
            'P3'=>new Permission(['type'=>'P','name'=>'P3','ruleName'=>'OwnDataUpdateRule','description' => 'updateOwnPost']),
            'P4'=>new Permission(['type'=>'P','name'=>'P4','description'=>'/']),
            'P5'=>new Permission(['type'=>'P','name'=>'p5','description' => '/user']),
            'R1'=>new Role(['type'=>'R','name'=>'R1','description' => 'user']),
            'R2'=>new Role(['type'=>'R','name'=>'R2','description' => 'author']),
        ];
        $items->setValue($this->yca,$data);
        $data = [
            'P1'=>['R1','P3'],
            'P2'=>['R2'],
            'P3'=>['R2'],
            'P5'=>['P1'],
        ];
        $parents = new \ReflectionProperty($this->yca,'parents');
        $parents->setAccessible(true);
        $parents->setValue($this->yca,$data);

        $data = [
            'OwnDataUpdateRule'=>new class extends Rule{
                public function execute($user, $item, $params)
                {
                    return true;
                }
            },
        ];
        $rules = new \ReflectionProperty($this->yca,'rules');
        $rules->setAccessible(true);
        $rules->setValue($this->yca,$data);
        $assignment = [
            'R1'=>[
                'user_id'=>1,
            ],
        ];
        $bool = $this->invokeMethod($this->yca,'checkAccessFromCache',[1,'P1',[],$assignment]);
        $this->assertTrue($bool);
        $bool = $this->invokeMethod($this->yca,'checkAccessFromCache',[1,'P2',[],$assignment]);
        $this->assertFalse($bool);
        $bool = $this->invokeMethod($this->yca,'checkAccessFromCache',[1,'P5',[],$assignment]);
        $this->assertTrue($bool);
        $assignment = [
            'R2'=>[
                'user_id'=>2,
            ],
        ];
        $bool = $this->invokeMethod($this->yca,'checkAccessFromCache',[2,'P1',[],$assignment]);
        $this->assertTrue($bool);

    }


    function testCheckAccessRecursive()
    {
        $assignment = [
            'R4'=>[
                'user_id'=>4,
            ],
        ];
        $bool = $this->invokeMethod($this->yca,'checkAccessRecursive',[4,'/rbac/role/index',[],$assignment]);
        $this->assertTrue($bool);
    }

    function testGetPermissionsByRole()
    {
        $ret = $this->yca->getPermissionsByRole(4);
        $this->assertEquals(8,count($ret));
    }

    function testGetInheritedPermissionsByUser()
    {
        $ret = $this->invokeMethod($this->yca,'getInheritedPermissionsByUser',[4]);
        $this->assertEquals(12,count($ret));
    }

    function testCanAddChild()
    {
        $parent = new Permission(['id'=>12]);
        $child = new Permission(['id'=>20]);
        $bool = $this->yca->canAddChild($parent,$child);
        $this->assertFalse($bool);
    }

    function testGetMenuTreeByPermission()
    {
        $ret = $this->yca->getAssignedMenu('yak.web','1');
        $this->assertNotEmpty($ret);
    }

    function testGetChildren()
    {
        $val = $this->yca->getChildren('R4');
        $this->assertNotEmpty($val);
    }
}
