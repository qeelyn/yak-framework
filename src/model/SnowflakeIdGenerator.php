<?php

namespace yak\framework\model;


use Yii;
use yii\base\Component;
use yii\di\Instance;
use yii\mutex\Mutex;

/**
 * snowflake ID生成算法,支持Yii mutex方式生成
 *
 * 如果ID生成机制出现严重的并发冲突时,可采用mutex方式配合.
 * @see [[/yii/base/Mutex]]
 *
 * > 注: 在32 PHP 环境下,采用GMP库来实现精度运算,需要安装该库.
 *
 * 配置如下:
 * 'component'=>[
 *      'class'=>'yak\framework\model\SnowflakeIdGenerator',
 *      ''
 *      'mutex'=>[
 *          'class' => 'yii\mutex\FileMutex'
 *      ],
 * ]
 * 1 1bit 标记不可用
 * 2-42共41bit存放时间，毫秒数 2^41/(365*24*3600*1000),大概可以用69.7年
 * 43-52共10bit存放机器的id,大约1024个机器
 * 53-64共12bit，每毫秒可提供 2^12=4096个id
 *
 * +----+------------+--------+---------+
 * |1bit|   41bit    | 10bit  |  12bit  |
 * +----+------------+------ -+---------+
 *   不用    毫秒数	       机器	    序列号
 *
 * @see https://github.com/twitter/snowflake
 */
class SnowflakeIdGenerator extends Component implements IdGeneratorInterface
{
    /**
     * @var Mutex
     */
    public $mutex;

    const MUTEX_NAME = 'idGenerate';
    /*
     * 64位里面各个位置的使用情况，剩下未做说明的就是时间毫秒数的存放了，64-3-8-3-9-1=40位
     */
    const ALL_BITS = 64;
    const WORKER_ID_BITS = 10; //机器标识位数
    const SEQUENCE_BITS = 12; //毫秒内自增位

    public $epoch = 1451577600000;  //从这个时候开始使用发号器，定为 2016年1月1号0时0分
    public $workerId = 1; //机器id

    private $_maxWorkerId; //机器ID最大值 255 = -1 ^ (-1 << WORKER_ID_BITS)
    private $_maxTimestamp; //最大支持时间戳 255 = -1 ^ (-1 << WORKER_ID_BITS)
    private $_workerIdShift; //机器ID偏左移位数，12位 = SEQUENCE_BITS(9) + PLACEHOLDER_BITS(3)
    private $_timestampLeftShift; //数据中心ID偏左移位数，23位 = SEQUENCE_BITS(9) + PLACEHOLDER_BITS(3) + WORKER_ID_BITS(8) + DATA_CENTER_ID_BITS(3)
    private $_sequence = 0;  //毫秒时间内总共生成了多少个
    private $_sequenceMask; //每毫秒最多可以生成多少个id 511 = -1 ^ (-1 << SEQUENCE_BITS)
    private $_lastTimestamp = -1;
    //32位兼容有关
    private $_isCompatibility32 = false;
    const max41bit = 1099511627775;
    public function init()
    {
        parent::init();
        if($this->mutex != null){
            $this->mutex = Instance::ensure($this->mutex,Mutex::className());
        }
        if(PHP_INT_SIZE == 4){
            //32位
            $this->_isCompatibility32 = true;
        }
    }
    /**
     * @param $workerId [机器id]
     * @throws \Exception
     */
    public function setWorkId($workerId)
    {
        if ($workerId > $this->getMaxWorkerId() || $workerId < 0) {
            throw new \Exception("workerId can't be greater than {$workerId} or less than 0");
        }
        $this->workerId = $workerId;
    }
    /**
     * 获取最大的机房数值
     */
    public function getMaxWorkerId()
    {
        if (is_null($this->_maxWorkerId)) {
            $this->_maxWorkerId = -1 ^ (-1 << self::WORKER_ID_BITS);
        }
        return $this->_maxWorkerId;
    }
    /**
     * 获取每毫秒最多可以生成多少个id
     */
    public function getSequenceMask()
    {
        if (is_null($this->_sequenceMask)) {
            $this->_sequenceMask = -1 ^ (-1 << self::SEQUENCE_BITS);
        }
        return $this->_sequenceMask;
    }

    /**
     * 获取机器id位置偏移位置
     */
    public function getWorkerIdShift()
    {
        if (is_null($this->_workerIdShift)) {
            $this->_workerIdShift = self::SEQUENCE_BITS;
        }
        return $this->_workerIdShift;
    }

    /**
     * 获取数据中心位置偏移位置
     */
    public function getTimestampLeftShift()
    {
        if (is_null($this->_timestampLeftShift)) {
            $this->_timestampLeftShift = self::SEQUENCE_BITS + self::WORKER_ID_BITS;
        }
        return $this->_timestampLeftShift;
    }
    /**
     * 根据id获取当时的时间戳,毫秒
     * @param $id
     * @return number
     */
    public function getTimestampFromId($id)
    {
        /*
        * Return time
        */
        return bindec(substr(decbin($id), 0, -$this->getTimestampLeftShift())) + $this->epoch;
    }
    /**
     * 获取当前机器的时间戳
     * @return float
     */
    protected function timeGen()
    {
        return floor(microtime(true) * 1000);
    }
    /**
     * 当前毫秒时间内的id用完了,需要调用此方法等待下一毫秒的到来,然而.我相信 php 1毫秒内生成不了这么多的
     * @param $lastTimestamp
     * @return float
     */
    protected function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }

    /**
     * 获取id
     * @return string
     * @throws \Exception
     */
    public function nextId()
    {
        try{
            if($this->mutex && !$this->mutex->acquire(self::MUTEX_NAME,5)){
                //启用锁机制时,超时
                throw new \RuntimeException('id generate request lock time out!');
            }
            return $this->nextIdInternal();
        }finally{
            if ($this->mutex && $this->mutex->autoRelease) {
                $this->mutex->release(self::MUTEX_NAME);
            }
        }
    }

    /**
     * 获取id
     * @return string
     * @throws \Exception
     */
    public function nextIdInternal()
    {
        $timestamp = $this->timeGen();
        //时间错误,系统时钟出问题了
        if ($timestamp < $this->_lastTimestamp) {
            throw new \Exception('Clock moved backwards.  Refusing to generate id for ' . ($this->_lastTimestamp - $timestamp) . ' milliseconds');
        }

        //当前毫秒时间内
        if ($this->_lastTimestamp == $timestamp) {
            //当前毫秒内，则+1
            $this->_sequence = ($this->_sequence + 1) & $this->getSequenceMask();
            if ($this->_sequence == 0) {
                //当前毫秒内计数满了，则等待下一秒
                $timestamp = $this->tilNextMillis($this->_lastTimestamp);
            }
        } else { //不在当前毫秒时间内
            //在跨毫秒时，序列号总是归0，会使得序列号为0的ID比较多，导致ID取模不均匀。所以使用随机0-9的方式，代价是消耗小量id
            $this->_sequence = 0;
        }
        //更新最后生成的时间
        $this->_lastTimestamp = $timestamp;
        //ID偏移组合生成最终的ID，并返回ID
        return $this->getCompatibilityResult();
    }

    /**
     * 位移运算在规则的32位INT下运算会出现非预期
     * @return string
     */
    private function getCompatibilityResult()
    {
        $time = $this->_lastTimestamp - $this->epoch;
        if($this->_isCompatibility32){
            $timestamp = gmp_mul((string)$time, gmp_pow(2, 22));
            $machine = gmp_mul((string)$this->workerId, gmp_pow(2, 12));
            $sequence = gmp_init((string)$this->_sequence, 10);
            $value = gmp_or(gmp_or($timestamp, $machine), $sequence);
            return gmp_strval($value, 10);
        }else{
            $value = ($time << $this->getTimestampLeftShift())|
                ($this->workerId << $this->getWorkerIdShift()) |
                $this->_sequence;
            return strval($value);
        }
    }
}