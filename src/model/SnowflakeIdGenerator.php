<?php

namespace yak\framework\model;


use Yii;
use yii\base\Object;

/**
 * Twitter的Snowflake算法的实现, 该算法的PHP实现无法保证结果绝对的唯一性, 因此还提供了异常恢复机制
 */
class SnowflakeIdGenerator extends Object implements IdGeneratorInterface  
{
    const EPOCH = 1479533469598;  //change your EPOCH
    const max12bit = 4095;
    const max41bit = 1099511627775;
    /**
     * @var bool 机器或线程相关
     */
    public $workdId = false;

    /**
     * 生成一个id, 可以在32bit的PHP环境下运行
     * @param  int|false $workid 工作id,如果为false则随机生成
     * @return array
     */
    public function nextId()
    {
        $workid = $this->workdId === false ? mt_rand(1, 1023) : $this->workdId;

        $time = floor(microtime(true) * 1000);
        $time -= self::EPOCH;
        $base = bcadd(self::max41bit, $time);
        $movebit = pow(2, 22);
        $base = bcmul($base, $movebit);
        $movebit = pow(2, 12);
        $workid = $workid * $movebit;
        $random = mt_rand(0, self::max12bit);
        $idstep1 = bcadd($base, $workid);
        $id = bcadd($idstep1, $random);
        return $id;
    }

    /**
     * 解析id的时间戳部分
     * @param  string $id [getId]生成的id
     * @return string 时间戳
     */
    public Static function getTimeStamp($id)
    {
        $movebit = pow(2, 22);
        $time = bcdiv($id, $movebit);
        $time = $time - self::max41bit + self::EPOCH;
        return $time;
    }

    /**
     * 解析id的workid部分
     * @param  string $id [getId]生成的id
     * @return string Workid
     */
    public static function getWorkid($id)
    {
        $movebit = pow(2, 22);
        $time = bcdiv($id, $movebit); //bit move right 22
        $time = bcmul($time, $movebit); //bit move left 22
        $step1 = bcsub($id, $time); //workid + random
        $movebit = pow(2, 12);
        $workid = bcdiv($step1, $movebit); //bit move right 12
        return $workid;
    }

    /**
     * 解析id的随机数部分
     * @param  string $id [getId]生成的id
     * @return string 随机数
     */
    public static function getRandom($id)
    {
        $movebit = pow(2, 22);
        $time = bcdiv($id, $movebit);
        $time = bcmul($time, $movebit);
        $step1 = bcsub($id, $time); //workid+random
        $movebit = pow(2, 12);
        $workid = bcdiv($step1, $movebit);  //workid

        $workid = bcmul($workid, $movebit);  //bit move left 12
        $random = bcsub($step1, $workid);
        return $random;
    }
}