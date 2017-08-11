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
        $idg = \Yii::$app->get('idGenerator');
        $id1 = $idg->nextId();
        $id2 = $idg->nextId();
        $this->assertNotEquals($id1,$id2);
        $this->assertGreaterThan($id2,$id1);
    }

    function testIdSort()
    {
        $gn = new SnowflakeIdGenerator();
        $i = 10;
        $ids = [];
        while ($i >0){
            $ids[] = $gn->nextId();
            $i--;
        }
        $sids = $ids;
        asort($sids);
        $this->assertEquals($ids,$sids);
    }
}
