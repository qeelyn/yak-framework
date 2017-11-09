<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/11/8
 * Time: 下午4:54
 */

namespace yak\framework\rbac;

use yak\framework\base\BaseUser;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\base\NotSupportedException;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use yii\db\Expression;
use yii\db\Query;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;
use Yii;

/**
 * yak的RBAC管理,与YII较接近的管理方式.
 * 对于permission和role的分表,将方法中需要 name 认为是ItemTYPE . 'id' 这样就基本不会搞混乱了
 *
 * @package yak\framework\rbac
 */
class DbManager extends \yii\rbac\DbManager
{
    //角色
    const ITEM_ROLE = 'R';
    //权限
    const ITEM_PERMISSION = 'P';
    /**
     * @var array 名称映射为数据库ID,$name=>$id
     */
    private $nameMappers;

    public $itemTable = '{{%auth_permission}}';

    public $roleItemTable = '{{%auth_role}}';

    public function invalidateCache()
    {
        if ($this->cache !== null) {
            $user = \Yii::$app->user->identity;
            if ($user instanceof BaseUser) {
                $orgId = $user->getCurrentOrganizationId();
            } else {
                $orgId = 0;
            }
            $key = [__CLASS__, $this->cacheKey, $orgId];

            $this->cache->delete($key);
            $this->items = null;
            $this->rules = null;
            $this->parents = null;
            $this->nameMappers = null;
            TagDependency::invalidate($this->cache, $this->cacheKey);
        }
    }

    /**
     *
     * @param int|string $userId
     * @param string $permissionName
     * @param array $params
     * @return bool
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $assignments = $this->getAssignments($userId, $params['organization_id'] ?? 0);

        if ($this->hasNoAssignments($assignments)) {
            return false;
        }

        $this->loadFromCache();
        if ($this->items !== null) {
            return $this->checkAccessFromCache($userId, $permissionName, $params, $assignments);
        } else {
            return $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
        }
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        if (($item = $this->getItem($itemName)) === null) {
            return false;
        }

        Yii::trace($item instanceof Role ? "Checking role: $itemName" : "Checking permission: $itemName", __METHOD__);

        if (!$this->executeRule($user, $item, $params)) {
            return false;
        }

        if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
            return true;
        }
        $dItem = $this->convertNameToId($item->name);
        $query = new Query();
        $parents = $query->select(['parent_type', 'parent_id'])
            ->from($this->itemChildTable)
            ->where(['child_type' => $item->type, 'child_id' => $dItem[1]])
            ->column($this->db);
        foreach ($parents as $parent) {
            $pItem = $parent['parent_type'] . $parent['parent_id'];
            if ($this->checkAccessRecursive($user, $pItem, $params, $assignments)) {
                return true;
            }
        }

        return false;
    }

    public function loadFromCache()
    {
        if ($this->items !== null || !$this->cache instanceof CacheInterface) {
            return;
        }
        $user = \Yii::$app->user->identity;
        if ($user instanceof BaseUser) {
            $orgId = $user->getCurrentOrganizationId();
        } else {
            $orgId = 0;
        }
        $key = [__CLASS__, $this->cacheKey, $orgId];
        $data = $this->cache->get($key);
        if (is_array($data) && isset($data[0], $data[1], $data[2], $data[3], $data[4])) {
            list ($this->items, $this->rules, $this->parents, $this->nameMappers) = $data;
            return;
        }
        //权限项相关数据获取
        //1.permission
        $query = (new Query())->select('id,name,rule_id')->from($this->itemTable)
            ->where(['status' => 1]);
        $this->items = [];
        $this->nameMappers = [];
        foreach ($query->all($this->db) as $row) {
            $key = self::ITEM_PERMISSION . $row['id'];
            $row['type'] = self::ITEM_PERMISSION;
            $this->items[$key] = $this->populateItem($row);
            $this->nameMappers[$row['name']] = $key;
        }
        //2.role
        //角色数据,name不唯一,以id做为key
        $query = (new Query())->select('id,name,rule_id')->from($this->roleItemTable)
            ->where(['status' => 1]);

        foreach ($query->all() as $row) {
            $row['type'] = self::ITEM_ROLE;
            $this->items[self::ITEM_ROLE . $row['id']] = $row;
        }
        //规则
        $query = (new Query())->from($this->ruleTable);
        $this->rules = [];
        foreach ($query->all($this->db) as $row) {
            $data = $row['data'];
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            $this->rules[$row['name']] = unserialize($data);
        }
        //角色与权限,角色与角色之间的关系才进入parent
        $types = [self::ITEM_PERMISSION, self::ITEM_ROLE];
        $query = (new Query())->from($this->itemChildTable)->Where(['child_type' => $types, 'parent_type' => $types]);
        $this->parents = [];
        foreach ($query->all($this->db) as $row) {
            $childKey = $row['child_type'] . $row['child_id'];
            $parentKey = $row['parent_type'] . $row['parent_id'];
            if (isset($this->items[$childKey])) {
                $this->parents[$childKey][] = $parentKey;
            }
        }

        $this->cache->set($key, [$this->items, $this->rules, $this->parents, $this->nameMappers]);
    }

    protected function getItem($name)
    {
        if (empty($name)) {
            return null;
        }

        if (!empty($this->items[$name])) {
            return $this->items[$name];
        }
        $info = $this->convertNameToId($name);
        if ($info[0] == self::ITEM_ROLE) {
            $table = $this->roleItemTable;
        } else {
            $table = $this->itemTable;
        }
        $where = is_int($info[0]) ? ['id' => $info[1]] : ['name' => $info[1]];
        $row = (new Query())->from($table)
            ->where($where)
            ->one($this->db);

        if ($row === false) {
            return null;
        }

        return $this->populateItem($row);
    }

    protected function getItems($type)
    {
        if ($type == Item::TYPE_PERMISSION) {
            $table = $this->itemTable;
            $prefix = self::ITEM_PERMISSION;
        } else {
            $table = $this->roleItemTable;
            $prefix = self::ITEM_ROLE;
        }
        $query = (new Query())
            ->from($table);

        $items = [];
        foreach ($query->all($this->db) as $row) {
            $row['type'] = $prefix;
            $items[$prefix . $row['id']] = $this->populateItem($row);
        }

        return $items;
    }

    protected function populateItem($row)
    {
        $class = $row['type'] == self::ITEM_PERMISSION ? Permission::className() : Role::className();
        $data = null;
        if (isset($row['data'])) {
            $data = json_decode($row['data']);
        }
        $data['name'] = $row['name'] ?? null;
        $data['kind'] = $row['kind'] ?? null;
        $data['app_id'] = $row['app_id'] ?? null;
        $data['status'] = $row['status'] ?? null;
        $data['display_sort'] = $row['display_sort'] ?? null;
        $data['is_grant_user'] = $row['is_grant_user'] ?? null;
        $data['organization_id'] = $row['organization_id'] ?? null;
        $data['data'] = $row['data'] ?? null;
        return new $class([
            'name' => $row['type'] . $row['id'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_id'],
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }

    private function convertNameToId($name)
    {
        if (is_int($name[0])) {
            return [$name, $name];
        }
        return [$name[0], substr($name, 1)];
    }

    public function getAssignment($roleName, $userId)
    {
        if (empty($userId)) {
            return null;
        }
        $roleId = $this->convertNameToId($roleName)[1];
        $row = (new Query())->from($this->assignmentTable)
            ->where(['user_id' => (string)$userId, 'item_type' => self::ITEM_ROLE, 'item_id' => $roleId])
            ->one($this->db);

        if ($row === false) {
            return null;
        }

        return new Assignment([
            'userId' => $row['user_id'],
            'roleName' => $row['item_type'] . $row['item_id'],
            'organizationId' => $row['organization_id'],
            'createdAt' => $row['created_at'],
        ]);
    }

    public function getAssignments($userId, $organizationId = false)
    {
        if (empty($userId)) {
            return [];
        }

        if (\Yii::$app->user->getId() == $userId) {
            //TODO 当前用户的assignment对象
            /** @var BaseUser $identity */
            $identity = Yii::$app->user->getIdentity();
            return $identity->getAssignments($organizationId);
        } else {


            $where = ['user_id' => (string)$userId, 'item_type' => self::ITEM_ROLE,];
            if ($organizationId) {
                $where['organization_id'] = $organizationId;
            }
            $query = (new Query())
                ->select('user_id,item_id,item_type,organization_id,created_at')
                ->where($where)
                ->from($this->assignmentTable);

            $assignments = [];
            foreach ($query->all($this->db) as $row) {
                $key = $row['item_type'] . $row['item_id'];
                $assignments[$key] = new Assignment([
                    'userId' => $row['user_id'],
                    'roleName' => $key,
                    'organizationId' => $row['organization_id'],
                    'createdAt' => $row['created_at'],
                ]);
            }
        }
        return $assignments;
    }

    /**
     * @inheritdoc
     */
    protected function addItem($item)
    {
        $time = time();

        if ($item->createdAt === null) {
            $item->createdAt = $time;
        }
        if ($item->updatedAt === null) {
            $item->updatedAt = $time;
        }

        $columns = [
            'app_id' => $item->data['app_id'] ?? null,
            'name' => $item->name,
            'kind' => $item->data['kind'] ?? 1,
            'description' => $item->description,
            'rule_id' => $item->ruleName,
            'status' => $item->data['status'] ?? 0,
            'data' => json_encode($item->data['data']),
            'created_at' => $item->createdAt,
            'created_by' => Yii::$app->user->id,
            'updated_at' => $item->updatedAt,
            'updated_by' => Yii::$app->user->id,
        ];
        if ($item instanceof Permission) {
            $table = $this->itemTable;
        } else {
            $table = $this->roleItemTable;
            $columns['kind'] = $columns['kind'] ?? 3;
            $columns['display_sort'] = $item->data['display_sort'] ?? 0;
            $columns['is_grant_user'] = $item->data['is_grant_user'] ?? 0;
            $columns['organization_id'] = $item->data['organization_id'] ?? 0;;
        }

        $this->db->createCommand()
            ->insert($table, $columns)->execute();

        $this->invalidateCache();

        return true;
    }

    protected function removeItem($item)
    {
        if ($item instanceof Permission) {
            $table = $this->itemTable;
        } else {
            $table = $this->roleItemTable;
        }
        $key = $this->convertNameToId($item->name);
        if (!$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->delete($table, ['or', ['child_type' => $key[0], 'child_id' => $key[1]], ['parent_type' => $key[0], 'parent_id' => $key[1]]])
                ->execute();
            $this->db->createCommand()
                ->delete($this->assignmentTable, ['item_type' => $key[0], 'item_id' => $key[1]])
                ->execute();
        }

        $this->db->createCommand()
            ->delete($table, ['id' => $key[1]])
            ->execute();

        $this->invalidateCache();

        return true;
    }

    protected function updateItem($name, $item)
    {
        // TODO: Change the autogenerated stub
        throw new NotSupportedException();
    }
    /**
     * @inheritdoc
     */
    public function getRolesByUser($userId)
    {
        if (!isset($userId) || $userId === '') {
            return [];
        }

        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->roleItemTable])
            ->where('{{a}}.[[item_id]]={{b}}.[[id]]')
            ->andWhere(['a.user_id' => (string)$userId])
            ->andWhere(['a.item_type' => self::ITEM_ROLE]);

        $roles = $this->getDefaultRoleInstances();
        foreach ($query->all($this->db) as $row) {
            $row['type'] = self::ITEM_ROLE;
            $roles[self::ITEM_ROLE . $row['id']] = $this->populateItem($row);
        }
        return $roles;
    }

    /**
     * @inheritdoc
     */
    public function getChildRoles($roleName)
    {

        $role = $this->getRole($roleName);

        if ($role === null) {
            throw new InvalidParamException("Role \"$roleName\" not found.");
        }

        $result = [];
        $this->getChildrenRecursive($roleName, $this->getChildrenList([self::ITEM_ROLE]), $result);

        $roles = [$roleName => $role];

        $roles += array_filter($this->getRoles(), function (Role $roleItem) use ($result) {
            return array_key_exists($roleItem->name, $result);
        });

        return $roles;
    }


    /**
     * @param array $itemTypes
     * @return array
     */
    protected function getChildrenList($itemTypes = [])
    {
        $query = (new Query())->from($this->itemChildTable);
        if ($itemTypes) {
            $query->andWhere(['child_type' => $itemTypes, 'parent_type' => $itemTypes]);
        }

        $parents = [];
        foreach ($query->all($this->db) as $row) {
            $parents[$row['parent_type'] . $row['parent_id']][] = $row['child_type'] . $row['child_id'];
        }
        return $parents;
    }
    /**
     * @inheritdoc
     */
    public function getPermissionsByRole($roleName)
    {
        $childrenList = $this->getChildrenList([self::ITEM_ROLE, self::ITEM_PERMISSION]);
        $result = [];
        $this->getChildrenRecursive($roleName, $childrenList, $result);
        if (empty($result)) {
            return [];
        }
        $result = array_keys($result);
        array_walk($result, function (&$item) {
            $item = substr($item, 1);
        });
        $permissions = [];
        $query = (new Query())->from($this->itemTable)->where(['id' => $result,]);
        foreach ($query->all($this->db) as $row) {
            $row['type'] = self::ITEM_PERMISSION;
            $permissions[$row['name']] = $this->populateItem($row);
        }
        return $permissions;
    }
    /**
     * @inheritdoc
     */
    protected function getDirectPermissionsByUser($userId)
    {
        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_id]]={{b}}.[[id]]')
            ->andWhere(['a.user_id' => (string)$userId])
            ->andWhere(['a.item_type' => self::ITEM_PERMISSION]);

        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $row['type'] = self::ITEM_PERMISSION;
            $permissions[$row['name']] = $this->populateItem($row);
        }
        return $permissions;
    }

    /**
     * @inheritdoc
     */
    protected function getInheritedPermissionsByUser($userId)
    {
        $query = (new Query())->select('item_id')
            ->from($this->assignmentTable)
            ->where(['user_id' => (string)$userId, 'item_type' => self::ITEM_ROLE]);

        $childrenList = $this->getChildrenList([self::ITEM_PERMISSION, self::ITEM_ROLE]);
        $result = [];
        foreach ($query->column($this->db) as $roleName) {
            $this->getChildrenRecursive(self::ITEM_ROLE . $roleName, $childrenList, $result);
        }

        if (empty($result)) {
            return [];
        }

        $result = array_keys($result);
        array_walk($result, function (&$item) {
            $item = substr($item, 1);
        });

        $query = (new Query())->from($this->itemTable)->where(['id' => $result]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $row['type'] = self::ITEM_PERMISSION;
            $permissions[$row['name']] = $this->populateItem($row);
        }
        return $permissions;
    }

    public function addChild($parent, $child)
    {
        if ($parent->name === $child->name) {
            throw new InvalidParamException("Cannot add '{$parent->name}' as a child of itself.");
        }

        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidParamException('Cannot add a role as a child of a permission.');
        }

        if ($this->detectLoop($parent, $child)) {
            throw new InvalidCallException("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
        }
        $parentKey = $this->convertNameToId($parent->name);
        $childKey = $this->convertNameToId($parent->name);
        $now = time();
        $data = [
            'parent_type' => $parentKey[0],
            'parent_id' => $parentKey[1],
            'child_type' => $childKey[0],
            'child_id' => $childKey[1],
            'created_at' => $now,
            'created_by' => Yii::$app->user->id,
            'updated_at' => $now,
            'updated_by' => Yii::$app->user->id,
        ];
        $this->db->createCommand()
            ->insert($this->itemChildTable, $data)
            ->execute();

        $this->invalidateCache();

        return true;
    }

    public function removeChild($parent, $child)
    {
        $parentKey = $this->convertNameToId($parent->name);
        $childKey = $this->convertNameToId($parent->name);

        $result = $this->db->createCommand()
                ->delete($this->itemChildTable, [
                    'parent_type' => $parentKey[0],
                    'parent_id' => $parentKey[1],
                    'child_type' => $childKey[0],
                    'child_id' => $childKey[1],
                ])
                ->execute() > 0;

        $this->invalidateCache();

        return $result;
    }

    public function removeChildren($parent)
    {
        $parentKey = $this->convertNameToId($parent->name);
        $result = $this->db->createCommand()
                ->delete($this->itemChildTable, ['parent_type' => $parentKey[0], 'parent_id' => $parentKey[1]])
                ->execute() > 0;

        $this->invalidateCache();

        return $result;
    }

    public function hasChild($parent, $child)
    {
        $parentKey = $this->convertNameToId($parent->name);
        $childKey = $this->convertNameToId($parent->name);
        return (new Query())
                ->from($this->itemChildTable)
                ->where([
                    'parent_type' => $parentKey[0],
                    'parent_id' => $parentKey[1],
                    'child_type' => $childKey[0],
                    'child_id' => $childKey[1],
                ])
                ->one($this->db) !== false;
    }

    public function getChildren($name)
    {
        $parentKey = $this->convertNameToId($name);

        $query = (new Query())
            ->select(['a.id', 'name', new Expression('"P" as type'), 'description', 'rule_id', 'data', 'a.created_at', 'a.updated_at'])
            ->from(['a' => $this->itemTable, 'b' => $this->itemChildTable])
            ->where(['parent_type' => $parentKey[0], 'parent_id' => $parentKey[1],
                'a.id' => new Expression('[[child_id]]')])
            ->union((new Query())
                ->select(['a.id', 'name', new Expression('"R" as type'), 'description', 'rule_id', 'data', 'a.created_at', 'a.updated_at'])
                ->from(['a' => $this->roleItemTable, 'b' => $this->itemChildTable])
                ->where(['parent_type' => $parentKey[0], 'parent_id' => $parentKey[1],
                    'a.id' => new Expression('[[child_id]]')]));
        $children = [];
        foreach ($query->all($this->db) as $row) {
            $children[$row['type'] . $row['id']] = $this->populateItem($row);
        }

        return $children;
    }

    public function assign($role, $userId)
    {
        $orgId = $role->data['organization_id'] ?? 0;
        $key = $this->convertNameToId($role->name);
        $assignment = new Assignment([
            'userId' => $userId,
            'roleName' => $key[1],
            'createdAt' => time(),
            'organizationId' => $orgId,
        ]);
        $query = (new Query())->from($this->assignmentTable)->where([
            'user_id' => $userId,
            'item_type' => $key[0],
            'item_id' => $key[1],
        ]);
        if (!$query->exists($this->db)) {
            $this->db->createCommand()
                ->insert($this->assignmentTable, [
                    'user_id' => $assignment->userId,
                    'item_type' => $key[0],
                    'item_id' => $key[1],
                    'organization_id' => $assignment->organizationId,
                    'created_at' => $assignment->createdAt,
                    'created_by' => Yii::$app->user->id,
                    'updated_at' => $assignment->createdAt,
                    'updated_by' => Yii::$app->user->id,
                ])->execute();
        }
        return $assignment;
    }

    public function revoke($role, $userId)
    {
        if (empty($userId)) {
            return false;
        }
        $orgId = $role->data['organization_id'] ?? 0;
        $key = $this->convertNameToId($role->name);
        return $this->db->createCommand()
                ->delete($this->assignmentTable, [
                        'user_id' => (string) $userId,
                        'item_type' => $key[0],
                        'item_id' => $key[1],
                        'organization_od' => $orgId,
                    ]
                )->execute() > 0;
    }

    public function removeAll()
    {
        $this->db->createCommand()->delete($this->roleItemTable)->execute();
        parent::removeAll();
    }

    protected function removeAllItems($type)
    {
        if($type == Item::TYPE_PERMISSION){
            $table =$this->itemTable;
            $where = ['child_type'=>self::ITEM_PERMISSION];
            $awhere = ['item_type'=>self::ITEM_PERMISSION];
        }else{
            $table =$this->roleItemTable;
            $where = ['parent_type'=>self::ITEM_ROLE];
            $awhere = ['item_type'=>self::ITEM_ROLE];
        }
        if (!$this->supportsCascadeUpdate()) {
            $names = (new Query())
                ->select(['id'])
                ->from($table)
                ->column($this->db);
            if (empty($names)) {
                return;
            }
            $this->db->createCommand()
                ->delete($this->itemChildTable, $where)
                ->execute();
            $this->db->createCommand()
                ->delete($this->assignmentTable, $awhere)
                ->execute();
        }
        $this->db->createCommand()
            ->delete($this->itemTable)
            ->execute();

        $this->invalidateCache();
    }

    public function getUserIdsByRole($roleName)
    {
        if (empty($roleName)) {
            return [];
        }
        $key = $this->convertNameToId($roleName);
        return (new Query())->select('[[user_id]]')
            ->from($this->assignmentTable)
            ->where(['item_type'=>self::ITEM_ROLE,'item_id' => $key[1]])->column($this->db);
    }

    /**
     * 根据
     * @param $appCode
     * @param $userId
     * @param bool $refresh
     * @return array
     */
    public function getAssignedMenu($appCode, $userId,$refresh = false)
    {
        if (!$userId) {
            return [];
        }
        $key = [__METHOD__,$appCode,$userId];

        if($refresh || ($result = Yii::$app->cache->get($key)) === false){
            $menu = (new Query())->from(['a'=>'auth_menu','b'=>'auth_app'])
                ->select('a.*')
                ->where(['b.code' => $appCode,'a.app_id'=>new Expression('[[b.id]]')])
                ->orderBy('a.display_sort')
                ->indexBy('id')
                ->all($this->db);
            if($userId !== null){
                foreach ($this->getPermissionsByUser($userId) as $name=>$value){
                    if ($name[0] === '/') {
                        $routes[] = $name;
                    }
                }
            }

            foreach ($this->defaultRoles as $role) {
                foreach ($this->getPermissionsByRole($role) as $name => $value) {
                    if ($name[0] === '/') {
                        $routes[] = $name;
                    }
                }
            }
            $routes = array_unique($routes);
            sort($routes);

            $filter = function ($menu) use ($routes) {
                if ($menu['route'] && in_array($menu['route'],$routes)) {
                    return true;
                } elseif ($menu['route']) {
                    foreach ($routes as $route){
                        if (substr($route, -2) === '/*' && fnmatch($route,$menu['route'])) {
                            return true;
                        }
                    }
                    return false;
                } else {
                    return true;
                }
            };
            $result = self::buildMenuTree($menu, $filter);
            self::clearMenuTree($result);
            Yii::$app->cache->set($key,$result,0,new TagDependency([
                'tags'=>$this->cacheKey,
            ]));

        }
        return $result;
    }

    /**
     * 构建菜单树
     * @param $menus
     * @param callable $filterCallback
     * @return array
     */
    private static function buildMenuTree($menus, $filterCallback)
    {
        $tree = [];
        foreach ($menus as $key => &$menu) {
            if ($filterCallback && !$filterCallback($menu)) {
                unset($menus[$key]);
                continue;
            }
            if ($menu['parent_id'] != 0 && isset($menus[$menu['parent_id']])) {
                $menus[$menu['parent_id']]['children'][] = &$menus[$menu['id']];
            } else {
                $tree[] = &$menus[$menu['id']];
            }

        }
        return $tree;
    }

    private static function clearMenuTree(&$menuTree)
    {
        foreach ($menuTree as $key => &$item) {
            if (isset($item['children'])) {
                if (self::clearMenuTree($item['children'])) {
                    unset($menuTree[$key]);
                }
            } elseif (empty($item['route'])) {
                //无子级,而且没有资料指向
                unset($menuTree[$key]);
            }
        }
        return empty($menuTree);
    }

    /**
     * 返回用户所属
     * @param $userId
     * @return array
     */
    public function getOrganizations($userId)
    {
        $query = (new Query())->from(['b'=>'auth_organization','a'=>'auth_organization_user'])
            ->select('b.*')
            ->where("a.organization_id = b.id and b.parent_id = 0")
            ->andWhere(['a.user_id' => $userId]);
        return $query->all($this->db);
    }
}