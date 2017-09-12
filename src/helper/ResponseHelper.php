<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/12
 * Time: 下午2:51
 */

namespace yak\framework\helper;


use yii\base\Model;

class ResponseHelper
{

    /**
     * 当module或controller没有对action的返回值进行处理时,可以通过该方法处理
     * @param $data
     * @return array
     */
    static function formatApiData($data = [], $errors = [], $extensions = null)
    {
        $result = [];
        $result['code'] = 200;
        !$data ?: $result['data'] = $data;
        !$errors ?: $result['errors'] = $errors;
        !$extensions ?: $result['extensions'];
        return $result;
    }
    /**
     * 将Model类型的异常格式化为标准响应输出
     *
     * @param Model $model
     * @return \yii\base\Response
     * @throws \Exception
     */
    static function formatModelErrors($model)
    {
        if(!$errors = $model->getErrors()){
            throw new \Exception('unknown Error:errors is empty');
        }
        $resErrors = [];
        foreach ($errors as $field=>$fielsErrors)
        {
            foreach ($fielsErrors as $error){
                $resErrors[] = ['code'=>$field,'message'=>$error];
            }
        }
        $res = \Yii::$app->getResponse();
        $res->setStatusCode(500);
        $res->data = ['errors'=>$resErrors];
        return $res;
    }
}