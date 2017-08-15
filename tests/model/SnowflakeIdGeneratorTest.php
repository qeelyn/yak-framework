<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/8/11
 * Time: 下午5:10
 */

namespace yakunit\framework\model;

use yak\framework\model\SnowflakeIdGenerator;
use yakunit\framework\TestCase;

class SnowflakeIdGeneratorTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    function testNextId()
    {
        $idg = new SnowflakeIdGenerator([]);
        $id1 = $idg->nextId();
        $id2 = $idg->nextId();
        $this->assertNotEquals($id1,$id2);
        $this->assertGreaterThan($id1,$id2);
    }

    function testIdSort()
    {
        $gn = \Yii::$app->get('idGenerator');
//        $gn->useLock = false;
        $i = 10;
        $ids = [];
        while ($i >0){
            $ids[] = $gn->nextId();
            $i--;
        }
        $sids = $ids;
        sort($sids);
        foreach ($sids as $key=>$value){
            $this->assertEquals($ids[$key],$value);
            $this->assertEquals(strlen($value),18);
        }
    }
}
