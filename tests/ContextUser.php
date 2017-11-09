<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2016/11/21
 * Time: 上午11:46
 */

namespace yakunit\framework;

use yak\framework\base\BaseUser;
use Yii;
use yii\web\IdentityInterface;

/**
 * 用户上下文信息
 * @property array $organizations 顶级组织信息,一个用户可能属于多个顶级组织
 * @property array $currentOrganization 当前顶级组织信息,当用户发生组织切换时,需要变更
 * @property array $departOrganization 部门级别组织信息
 * @package yak\platform\components
 */
class ContextUser extends BaseUser
{
    public function __construct(array $config = [], array $data = [])
    {
        parent::__construct($config, $data);

    }

    /**
     * 根据给到的ID查询身份。
     *
     * @param string|integer $id 被查询的ID
     * @return IdentityInterface|null 通过ID匹配到的身份对象
     */
    public static function findIdentity($id)
    {
        $user = Yii::$app->cache->get(Yii::$app->params['identity_prefix'] . $id);
        if ($user) {
            return new ContextUser([],$user);
        }
        return self::getTestUser();
    }

    /**
     * 根据 token 查询身份。
     *
     * @param string $token 被查询的 token
     * @return IdentityInterface|null 通过 token 得到的身份对象
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        if ($token == 'test') {
            return self::getTestUser();
        }
        $attrData = Yii::$app->cache->get($token);
        if ($attrData) {
            return new ContextUser([],$attrData);
        }
        return null;
    }

    public static function getTestUser()
    {
        $uid = 2;
        $userAttr = Yii::$app->cache->get(Yii::$app->params['identity_prefix'] . $uid);
        if ($userAttr) {
            return new ContextUser([],$userAttr);
        }
        $userAttr = ['id' => $uid, 'avatar' => '', 'nickname' => '测试用户', 'auth_key' => ''];
        $assignments = Yii::$app->getAuthManager()->getAssignments($uid);
        $userAttr['assignments'] = $assignments;
        $orgs = Yii::$app->getAuthManager()->getOrganizations($uid);
        $userAttr['organizations'] = $orgs;
        if (count($orgs) == 1) {
            $userAttr['currentOrganization'] = reset($orgs);
        }
        Yii::$app->cache->set(Yii::$app->params['identity_prefix'] . $uid,$userAttr,3600);
        return new ContextUser([],$userAttr);
    }

    /**
     *
     * 登陆成功后设置auth_key及缓存
     *
     * @param UserEvent $userEvent
     * BaseUser $identity the user identity information
     * bool $cookieBased whether the login is cookie-based
     * int $duration number of seconds that the user can remain in logged-in status.
     * If 0, it means login till the user closes the browser or the session is manually destroyed.
     */
    public static function onAfterLogin($userEvent)
    {
        /** @var BaseUser $identity */
        $identity = $userEvent->identity;
        $uid = $identity->getId();
        $duration = Yii::$app->params['identity_cache_duration'] ?? 3600;
        $identity['auth_key'] = md5(Yii::$app->params['identity_prefix'] . $uid . time());
        $key = $identity['auth_key'];
        Yii::$app->cache->set($key, $identity->getAttributes(),$duration);
    }

    public static function onAfterLogout($userEvent)
    {
        /** @var BaseUser $identity */
        $identity = $userEvent->identity;
        $key = Yii::$app->params['identity_prefix'] . $identity->getId();
        Yii::$app->cache->delete($key);
    }
}