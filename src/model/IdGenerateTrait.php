<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/4/20
 * Time: 下午3:44
 */

namespace yak\framework\model;

use Yii;
use yii\base\ModelEvent;

/**
 * ActiveRecord ID Generate extend
 * 配合config配置文件
 * ```php
 *   'compnents'=>[
 *      'idGenerator'=>[
 *         'class'=>'',
 *      ]
 *   ],
 * ```
 */
trait IdGenerateTrait
{
    public $idAttribute = 'id';
    /**
     * @var string specify the component key
     */
    public $idGenerator = 'idGenerator';

    public function init()
    {
        parent::init();
        $this->initGenerator();
    }

    /**
     * attach to ActiveRecord event list
     */
    public function initGenerator()
    {
        /* @var $this ActiveRecord */
        if (Yii::$app->get($this->idGenerator, false)) {
            $this->on(self::EVENT_BEFORE_INSERT, [$this, 'evaluateIdAttributes']);
        }
    }

    /**
     * before insert event callback,it will set id attribute
     * @param ModelEvent $event
     */
    public function evaluateIdAttributes($event)
    {
        if (strstr($this::getDb()->dsn, 'mongodb')) {
            if ($this['_id'] === null) {
                $this['_id'] = $this->nextId();
            }
        } else {
            if ($this['id'] === null) {
                $this['id'] = $this->nextId();
            }
        }

    }

    /**
     * @return string | int
     */
    private function nextId()
    {
        /** @var IdGeneratorInterface $idg */
        $idg = Yii::$app->get($this->idGenerator);
        return $idg->nextId();
    }

    /**
     * provide a manual create id method
     */
    public function generateId()
    {
        /* @var $this ActiveRecord */
        if ($this->isNewRecord) {
            $idAttribute = $this->idAttribute;
            $this->$idAttribute = $this->nextId();
        }
    }
}