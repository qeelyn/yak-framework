<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/14
 * Time: 上午10:22
 */

namespace yak\framework\captcha;

use yii\base\Action;
use Yii;

/**
 * 基于geetest的验证方式
 *
 * @see http://www.geetest.com
 *
 * @package app\components
 */
class GeeCaptchaAction extends Action
{
    const GT_SDK_VERSION = 'php_3.0.0';

    public static $connectTimeout = 1;
    public static $socketTimeout  = 1;
    /**
     * @var string 公钥,在geetest上的id
     */
    public $appId;
    /**
     * @var string 私钥,在geetest上的key
     */
    public $appKey;

    public function run()
    {
        $userId = Yii::$app->user->getId();
        $get = \Yii::$app->request->get();
        $params = [
            'user_id'=>$userId? md5($userId):'',
            'client_type'=>$get['client_type'],
            'ip_address'=>Yii::$app->request->getUserIP(),
        ];
        $result = $this->preProcess($params);
        if($result['success']){
            Yii::$app->session->set('gt_server', $result['success']);
        }
        return $result;
    }

    /**
     * @param $value
     * @return bool
     */
    public function validate($value)
    {
        $post = \Yii::$app->request->post();
        $userId = Yii::$app->user->getId();
        $params = [
            'user_id'=>$userId? md5($userId):'',
            'client_type'=>$value,
            'ip_address'=>Yii::$app->request->getUserIP(),
        ];
        if(Yii::$app->session->get('gt_server') == 1){
            //服务器正常
            $result = $this->successValidate($post['geetest_challenge'],$post['geetest_validate'],$post['geetest_seccode'],$params);

        }else{
            //服务器宕机,走failback模式
            $result = $this->failValidate($post['geetest_challenge'], $post['geetest_validate'], $post['geetest_seccode']);
        }
        return $result == 1;
    }

    /**
     * 判断极验服务器是否down机
     *
     * @param array $param
     * @param int $newCaptcha
     * @return array 包含了状态信息了.success = 1|0
     */
    private function preProcess($param,$newCaptcha = 1)
    {
        $data = [
            'gt'=>$this->appId,
            'new_captcha'=>$newCaptcha
        ];
        $data = array_merge($data,$param);
        $query = http_build_query($data);
        $url = "http://api.geetest.com/register.php?" . $query;
        $challenge = $this->send_request($url);
        if (strlen($challenge) != 32) {
            return $this->failbackProcess();
        }
        return $this->successProcess($challenge);

    }

    /**
     * @param $challenge
     */
    private function successProcess($challenge) {
        $challenge      = md5($challenge . $this->appKey);
        $result         = array(
            'success'   => 1,
            'gt'        => $this->appId,
            'challenge' => $challenge,
            'new_captcha'=>1
        );
        return $result;
    }

    /**
     *
     */
    private function failbackProcess() {
        $rnd1           = md5(rand(0, 100));
        $rnd2           = md5(rand(0, 100));
        $challenge      = $rnd1 . substr($rnd2, 0, 2);
        $result         = array(
            'success'   => 0,
            'gt'        => $this->appId,
            'challenge' => $challenge,
            'new_captcha'=>1
        );
        return $result;
    }

    /**
     * 正常模式获取验证结果
     *
     * @param string $challenge
     * @param string $validate
     * @param string $seccode
     * @param array $param
     * @return int
     */
    public function successValidate($challenge, $validate, $seccode, $param, $json_format=1) {
        if (!$this->checkValidate($challenge, $validate)) {
            return 0;
        }
        $query = array(
            "seccode" => $seccode,
            "timestamp"=>time(),
            "challenge"=>$challenge,
            "captchaid"=>$this->appId,
            "json_format"=>$json_format,
            "sdk"     => self::GT_SDK_VERSION
        );
        $query = array_merge($query,$param);
        $url          = "http://api.geetest.com/validate.php";
        $codevalidate = $this->post_request($url, $query);
        $obj = json_decode($codevalidate,true);
        if ($obj === false){
            return 0;
        }
        if ($obj['seccode'] == md5($seccode)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 宕机模式获取验证结果
     *
     * @param $challenge
     * @param $validate
     * @param $seccode
     * @return int
     */
    public function failValidate($challenge, $validate, $seccode) {
        if(md5($challenge) == $validate){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * @param $challenge
     * @param $validate
     * @return bool
     */
    private function checkValidate($challenge, $validate) {
        if (strlen($validate) != 32) {
            return false;
        }
        if (md5($this->appKey . 'geetest' . $challenge) != $validate) {
            return false;
        }

        return true;
    }

    /**
     * GET 请求
     *
     * @param $url
     * @return mixed|string
     */
    private function send_request($url) {

        if (function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::$socketTimeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $curl_errno = curl_errno($ch);
            $data = curl_exec($ch);
            curl_close($ch);
            if ($curl_errno >0) {
                return 0;
            }else{
                return $data;
            }
        } else {
            $opts    = array(
                'http' => array(
                    'method'  => "GET",
                    'timeout' => self::$connectTimeout + self::$socketTimeout,
                )
            );
            $context = stream_context_create($opts);
            $data    = @file_get_contents($url, false, $context);
            if($data){
                return $data;
            }else{
                return 0;
            }
        }
    }

    /**
     *
     * @param       $url
     * @param array $postdata
     * @return mixed|string
     */
    private function post_request($url, $postdata = '') {
        if (!$postdata) {
            return false;
        }

        $data = http_build_query($postdata);
        if (function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::$socketTimeout);

            //不可能执行到的代码
            if (!$postdata) {
                curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $data = curl_exec($ch);

            if (curl_errno($ch)) {
                $err = sprintf("curl[%s] error[%s]", $url, curl_errno($ch) . ':' . curl_error($ch));
                $this->triggerError($err);
            }

            curl_close($ch);
        } else {
            if ($postdata) {
                $opts    = array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($data) . "\r\n",
                        'content' => $data,
                        'timeout' => self::$connectTimeout + self::$socketTimeout
                    )
                );
                $context = stream_context_create($opts);
                $data    = file_get_contents($url, false, $context);
            }
        }

        return $data;
    }



    /**
     * @param $err
     */
    private function triggerError($err) {
        trigger_error($err);
    }




}