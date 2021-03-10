<?php

namespace api\models;

use api\models\share\OrganizationRelation;
use common\libs\ding\Ding;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\web\Request;

/**
 * This is the model class for table "{{%user}}".
 *
 * @property int $id 主键id
 * @property string $user_id 太古用户id
 * @property string $display_name 显示姓名
 * @property string $token 太古用户token
 * @property string $company_code 厂房code
 * @property string $bu_code 厂房下的bu_code
 * @property string $swire_bu_code 太古bu_code
 * @property string $last_login_ip 最后一次登录ip
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class User extends baseModel
{
    public $swire_bu_code = '';

    const TOKEN_EXPIRE_TIME = 300;
    const SWIRE_USER_INFO_URL = '/user/getUserInfo?token=';
    const SWIRE_USER_FUNCTION_URL = '/user/system/hasAccessPermission';
    const COMPANY_CODE_FLAG = 'COMPANY_CODE_FLAG';
    const COMPANY_CODE_ALL = '3004';
    const COMPANY_CODE_ALL_LABEL = '央服';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'token', 'company_code'], 'required'],
            [['status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['user_id', 'last_login_ip'], 'string', 'max' => 32],
            [['display_name'], 'string', 'max' => 64],
            [['token'], 'string', 'max' => 255],
            [['company_code', 'bu_code'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'user_id' => '太古用户id',
            'display_name' => '显示姓名',
            'token' => '太古用户token',
            'company_code' => '厂房code',
            'bu_code' => '厂房下的bu_code',
            'last_login_ip' => '最后一次登录ip',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 获取太古用户信息
     * @param $token
     * @return User|ActiveRecord|null
     */
    public static function getSwireUser($token)
    {
        // 先判断缓存有没有，没有再从太古接口获取
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user_info'];
        $queue_name .= $token;
        $user_cache = Yii::$app->remq::getString($queue_name);
        if ($user_cache != null) {
            // 拿到缓存的话，需要创建个对象
            $user = new User();
            $user->id = $user_cache['id'];
            $user->load($user_cache, '');
            Yii::$app->remq::setExpire($queue_name, User::TOKEN_EXPIRE_TIME);
            return $user;
        }
        $url = Yii::$app->params['swire_user']['url'] . self::SWIRE_USER_INFO_URL . $token;
        $header = [
            'account:' . Yii::$app->params['swire_user']['account'],
            'api-key:' . Yii::$app->params['swire_user']['api-key'],
        ];
        $result = \Helper::curlQueryLog($url, [], true, 5, $header);

        $ding = Ding::getInstance();
        // 成功判断 code, 失败判断status
        if (isset($result['code']) && $result['code'] == 200) {
            $result = $result['data'];
            // 用户如果不在我们的user表里，需要新增记录
            $user_id = $result['user_name'];
//            $display_name = $result['display_name'];
            $company_code = $result['company_code'];
            $swire_bu_code = $result['bu_code'] != null ? $result['bu_code'] : '';
            $bu_code = $result['bu_code'] != null ? self::mapBu($result['bu_code']) : '';
            // 如果是广东厂房，太古系统问题，bu_code要调换
            if ($company_code == '3013') {
                $bu_code = $bu_code == '0001' ? '0002' : '0001';
            }
            $display_name = $result['display_name'] != null ? $result['display_name'] : '';
            $user_arr = [
                'user_id' => $user_id,
                'bu_code' => $bu_code,
                'swire_bu_code' => $swire_bu_code,
                'display_name' => $display_name,
                'company_code' => $company_code,
                'token' => $token,
            ];
            $user = User::findOne(['user_id' => $user_id]);
            if ($user == null) {
                $user = new User();
            }
            $user->user_id = $user_id;
            $user->bu_code = $bu_code;
//            $user->bu_code = $swire_bu_code;
            $user->display_name = $display_name;
            $user->company_code = $company_code;
            $user->token = $token;
            if (Yii::$app->request->getUserIP() != null)
                $user->last_login_ip = Yii::$app->request->getUserIP();
            if (!$user->save()) {
                $ding->sendTxt("【权限】保存用户失败 \n" . $user->getErrStr());
            }
            $user_arr['id'] = $user->id;
            Yii::$app->remq::setString($queue_name, $user_arr);
            Yii::$app->remq::setExpire($queue_name, User::TOKEN_EXPIRE_TIME);
            return $user;
        } else if ($result == null) {
            $ding->sendTxt("【太古接口】权限获取用户信息失败 \n" . json_encode($result, JSON_UNESCAPED_UNICODE));
            return null;
        } else {
            // 用户未配置权限
            return null;
        }
    }

    /**
     * 获取菜单 function_id 列表
     * @return array|ActiveRecord[]
     */
    public static function getMenuFunction()
    {
        $query = FunctionPermission::find()
            ->select(['menu_function_id'])
            ->where([FunctionPermission::DEL_FIELD => FunctionPermission::DEL_STATUS_NORMAL])
            ->andWhere(['<>', 'menu_function_id', ''])
            ->groupBy('menu_function_id')->asArray();

        return $query->all();
    }

    /**
     * 获取用户权限列表
     * @param $token
     * @return array|mixed
     */
    public static function getFunctionList($token)
    {
        // 不校验权限，全部返回
        if (!SITE_ACCESS_CONTROL) {
            $function = FunctionPermission::find()->select(['id', 'function_id'])->groupBy('function_id')->asArray()->all();
            return array_column($function, 'function_id');
        }
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user']['user_function_list_queue'];
        $queue_name .= $token;
        // 获取用户的权限列表
        $function_list = Yii::$app->remq::getString($queue_name);
        if ($function_list == null) {
            // 如果缓存没有，从太古接口获取
            $user = self::getSwireUser($token);
            if ($user != null) {
                // 循环调取
                $menus = self::getMenuFunction();
                $function_list = [];
                foreach ($menus as $menu) {
                    $function_list = array_merge([$menu['menu_function_id']], $function_list, $user->getSwireFunctionList($menu['menu_function_id']));
                }

                $user->saveToken($function_list);
            }
        }
        Yii::$app->remq::setExpire($queue_name, User::TOKEN_EXPIRE_TIME);
        return $function_list;
    }

    /**
     * 获取太古权限列表
     * @return array
     * @var $fid
     */
    private function getSwireFunctionList($fid)
    {
        $function_list = [];
        $ding = Ding::getInstance();
        $url = Yii::$app->params['swire_user']['url'] . self::SWIRE_USER_FUNCTION_URL;
        $header = [
            'account:' . Yii::$app->params['swire_user']['account'],
            'api-key:' . Yii::$app->params['swire_user']['api-key'],
            'company_code:' . $this->swire_bu_code,
            'token:' . $this->token,
        ];
        $result = \Helper::curlQueryLog($url, ['fid' => $fid], true, 5, $header);
        if (isset($result['code']) && $result['code'] == 200) {
            $function_list = $result['data'];
        } else if ($result == null) {
            $ding->sendTxt("【太古接口】获取用户权限列表失败 \n" . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $function_list;
    }

    /**
     * 保存user_id 对应的权限列表
     * @param $func_list
     */
    public function saveToken($func_list)
    {
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user']['user_function_list_queue'];
        $queue_name .= $this->token;
        Yii::$app->remq::setString($queue_name, $func_list);
        Yii::$app->remq::setExpire($queue_name, self::TOKEN_EXPIRE_TIME);
    }

    /**
     * 将url对应的权限码放在cache缓存
     * 1个action_id 可能对应多个太古function_id ，这里待优化
     */
    public static function cacheFunctionList()
    {
        $function = FunctionPermission::findAllArray([], ['id', 'action_url', 'function_id']);
        $function = ArrayHelper::map($function, 'action_url', 'function_id');

        // 入缓存
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_function_map'];
        Yii::$app->remq::setString($queue_name, $function);

        return $function;
    }

    public static function getFunctionId($action)
    {
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_function_map'];
        $function_list = Yii::$app->remq::getString($queue_name);
        if ($function_list == null) {
            // url映射无缓存
            $function_list = self::cacheFunctionList();
        } elseif (!isset($function_list[$action])) {
            // 有可能是数据库更新了，缓存未更新，这里需要手动刷新下缓存，如果仍然没有，就需要发告警
            $function_list = self::cacheFunctionList();
            if (!isset($function_list[$action])) {
                // 库中不存在该控制器
                $ding = Ding::getInstance();
                $ding->sendTxt('【权限】有url未配置到权限表' . $action);
                return null;
            }
        }
        return $function_list[$action];
        // 不在缓存中，需要从数据库重新取一次

    }

    /**
     * 清除用户缓存
     */
    public function resetCache()
    {
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user']['user_function_list_queue'];
        $queue_name .= $this->token;
        Yii::$app->remq::del($queue_name);

        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user_info'];
        $queue_name .= $this->token;
        Yii::$app->remq::del($queue_name);
    }

    /**
     * @param $class baseModel|string
     * @param $company_code
     * @param $bu_code
     * @param bool $validate_3004
     * @return array
     */
    public static function getBuCondition($class, $company_code, $bu_code, $validate_3004 = true, $alias = '')
    {
        $table_name = $alias ? $alias : $class::tableName();
        // 没有启用禁用的状态字段
        if (empty($class::ENABLE_STATUS_FIELD)) {
            // 如果是央服3004，不需要控制数据
            if (Yii::$app->params['user_is_3004']) {
                return [];
            }
            // 非3004用户，3004建的，也可以查出来
            if ($validate_3004 && $class::HAS_3004) {
                $result = ['or',
                    [$table_name . '.company_code' => User::COMPANY_CODE_ALL],
                    ['and', [$table_name . '.company_code' => $company_code], [$table_name . '.bu_code' => $bu_code]]
                ];
            } else {
                $result = ['and', [$table_name . '.company_code' => $company_code], [$table_name . '.bu_code' => $bu_code]];
            }
        } else {
            //初始化的状态无法互相查看
            if (Yii::$app->params['user_is_3004']) {
                return ['or',
                    [$table_name . '.company_code' => User::COMPANY_CODE_ALL],
                    ['<>', $class::ENABLE_STATUS_FIELD, $class::ENABLE_STATUS_DEFAULT]
                ];
            }
            // 非3004用户，3004建的，也可以查出来
            if ($validate_3004 && $class::HAS_3004) {
                $result = ['or',
                    ['and', [$table_name . '.company_code' => User::COMPANY_CODE_ALL], ['<>', $class::ENABLE_STATUS_FIELD, $class::ENABLE_STATUS_DEFAULT]],
                    ['and', [$table_name . '.company_code' => $company_code], [$table_name . '.bu_code' => $bu_code]]
                ];
            } else {
                // 本表没有3004创建的数据
                $result = ['and', [$table_name . '.company_code' => $company_code], [$table_name . '.bu_code' => $bu_code]];
            }
        }
        return $result;
    }

    /**
     * bu筛选项
     * @param $query Query
     * @param $company_bu array
     * @param $class baseModel|string
     */
    public static function buFilterSearch(&$query, $company_bu, $class = null)
    {
        $filter = ['or'];
        foreach ($company_bu as $bu) {
            $tmp = explode('_', $bu);
            if (count($tmp) == 1) {
                if ($class == null)
                    $filter[] = ['company_code' => $tmp[0]];
                else
                    $filter[] = [$class::tableName() . '.company_code' => $tmp[0]];
            } else if (count($tmp) == 2) {
                if ($class == null) {
                    $filter[] = ['company_code' => $tmp[0], 'bu_code' => $tmp[1]];
                } else {
                    $filter[] = [$class::tableName() . '.company_code' => $tmp[0], $class::tableName() . '.bu_code' => $tmp[1]];
                }
            }
        }
        $query->andFilterWhere($filter);
    }

    /**
     * bu_code转换
     * @param $bu
     * @return string
     */
    private static function mapBu($bu)
    {
        $reg = '/\d+$/';
        preg_match($reg, $bu, $match);
        return str_pad($match[0], 4, '0', STR_PAD_LEFT);
    }

    /**
     * 返回登录用户的厂房和BU
     * @return array
     */
    public static function getCompanyBu()
    {
        $user = Yii::$app->params['user_info'];
        $result = OrganizationRelation::findOneArray(['company_code' => $user['company_code'], 'bu_code' => $user['bu_code']]);
        $company_bu = $user['company_code'] . '_' . $user['bu_code'];
        $bu_name = $user['company_code'] != self::COMPANY_CODE_ALL ? $result['bu_name'] : User::COMPANY_CODE_ALL_LABEL;
        return [
            'company_code' => $user['company_code'],
            'bu_code' => $user['bu_code'],
            'company_bu' => $company_bu,
            'bu_name' => $bu_name
        ];
    }
}
