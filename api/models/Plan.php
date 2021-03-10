<?php

namespace api\models;

use api\models\share\CheckStoreQuestion;
use api\models\share\CheckStoreScene;
use api\models\share\Scene;
use api\models\share\SceneType;
use common\libs\ding\Ding;
use common\libs\file_log\LOG;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%plan}}".
 *
 * @property int $id                   主键id
 * @property int $project_id           项目id
 * @property int $standard_id          标准id
 * @property string $title                标题
 * @property string $company_code         厂房
 * @property string $bu_code              BU
 * @property string $plan_batch_id        批次id
 * @property string $user_id              用户
 * @property string $description          描述
 * @property string $rate_type            检查频率'one'检查期间只拍一次，week每周，month每月一次
 * @property string $rate_value           频率对应的值，如果rate_type是week，value是3 代表每周三，如果是month，代表每个月3号
 * @property int $max_reward_time      最大奖励次数
 * @property int $reward_is_auto       是否自动发 暂时不用
 * @property int $reward_type          奖励类型0现金，1优惠券，2积分 暂时不用
 * @property string $reward_option        奖励配置 格式{field: "aaa", value: "3"} 暂时不用
 * @property string $reward_value         奖励金或积分数，优惠券券码 暂时不用
 * @property string $rectification_model  整改模式
 * @property string $rectification_option 整改配置数组["aaa", "bbb"]
 * @property string $start_time           检查开始时间
 * @property string $end_time             结束时间
 * @property string $tool_id              执行工具
 * @property string $store_url            售点url文件
 * @property int $store_url_status     url文件状态，0:初始状态，1已生效，2失败
 * @property string $push_tool_option     推送执行工具设置
 * @property string $must_take_photo      必须拍照留底的场景id
 * @property int $reward_time          有奖拍摄次数
 * @property int $reward_mode          奖励发放模式: 0默认，1全店模式，2场景模式
 * @property string $reward_amount        全店模式奖励金额
 * @property int $set_store_type       售点设置方式：0默认配置、1、手工配置，2、ZFT同步
 * @property int $re_photo_time        整改拍摄次数
 * @property int $plan_status          检查执行状态 0初始状态、1启用、2禁用
 * @property int $editable             是否可以编辑 0不可以 1可以
 * @property int $screen_store_option 售点筛选条件
 * @property int $delete_store_option 售点删除条件
 * @property string $update_time          db更新时间
 * @property string $short_cycle 短周期模式的配置时间
 * @property int $status               删除标识：1有效，0无效
 * @property int $is_push_zft          是否推送ZFT，默认2，1推送、2不推送
 * @property int $is_qc                是否人工复核，默认2，1需要、2不需要
 * @property int $created_at           添加时间
 * @property int $updated_at           业务更新时间
 * @property Standard $standard_one         检查项目
 * @property Standard $question_model         问卷填写模式
 * @property Standard $need_question_qc       是否需要qc
 */
class Plan extends baseModel
{
    const HAS_3004 = false;
    const BU_FLAG = true;
    const ENABLE_STATUS_FIELD = 'plan_status';

    const PLAN_STATUS_DEFAULT = 0;      // 0 初始
    const PLAN_STATUS_ENABLE = 1;       // 1 启用
    const PLAN_STATUS_DISABLE = 2;      // 2 禁用

    const REWARD_MODE_DEFAULT = 0;
    const REWARD_MODE_ALL = 1;          // 全店
    const REWARD_MODE_SCENE = 2;        // 场景

    const SET_STORE_DEFAULT = 0;        // 默认未配置
    const SET_STORE_EXCEL = 1;          // 手工配置
    const SET_STORE_ZFT = 2;            // ZFT同步

    const EDITABLE_NO = 0;          // 是否可以变更，0不可以
    const EDITABLE_YES = 1;            // 是否可以变更，1可以

    const PLAN_STATUS_LABEL = [                 // 状态中文
        self::PLAN_STATUS_DEFAULT => '初始状态',
        self::PLAN_STATUS_ENABLE => '启用',
        self::PLAN_STATUS_DISABLE => '禁用',
    ];


    // 只拍一次，week每周，month每月一次
    const RATE_TYPE_ARR = [
        'one' => '只拍一次',
        'week' => '每周一次',
        'month' => '每月一次',
    ];

    //两种整改模式
//    const RECTIFICATION_MODEL_WITH_SINGLE = 1;
    const RECTIFICATION_MODEL_WITH_CYCLE = 2;
    const RECTIFICATION_MODEL_NONE = 3;

    //整改模式对应的含义
    const RECTIFICATION_MODEL_EXPLAIN = [
//        self::RECTIFICATION_MODEL_WITH_SINGLE => '按检查时间',
        self::RECTIFICATION_MODEL_WITH_CYCLE => '按检查周期',
        self::RECTIFICATION_MODEL_NONE => '无整改模式',
    ];

    const IS_PUSH_ZFT_YES = 1;
    const IS_PUSH_ZFT_NO = 2;

    const IS_QC_YES = 1;
    const IS_QC_NO = 2;

    public $standard_one;

    /*
     * 1.前端必填、后端选填(支持SFA、SEA)
     * 2.前端必填、后端不需填(支持SFA、SEA)
     * 3.前端选填、后端必填(支持SFA)
     * 4.前端不需填、后端必填(支持SFA)
     * 5.无（此模式不显示，后端默认处理）
     */
    const FRONT_REQUIRED_BACK_SAFE = 1;
    const FRONT_REQUIRED_BACK_NOT = 2;
    const FRONT_SAFE_BACK_REQUIRED = 3;
    const FRONT_NOT_BACK_REQUIRED = 4;
    const NONE_MODEL = 5;

    const NEED_QC_DEFAULT = 0; // 不需要
    const NEED_QC_YES = 1;  // 需要

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['start_time', 'end_time', 'company_code', 'user_id'], 'required'],
            [
                [
                    'project_id', 'standard_id', 'max_reward_time', 'reward_is_auto', 'reward_type', 'set_store_type', 're_photo_time',
                    'plan_status', 'store_url_status', 'reward_time', 'reward_mode', 'status', 'is_push_zft', 'is_qc',
                    'created_at', 'updated_at', 'question_model',
                ], 'integer',
            ],
            [['reward_option', 'rectification_option', 'push_tool_option'], 'string'],
            [['start_time', 'end_time', 'update_time', 'must_take_photo', 'bu_code', 'plan_batch_id'], 'safe'],
            [['reward_amount'], 'number'],
            [['title'], 'string', 'max' => 64],
            [['description'], 'string', 'max' => 255],
            [['screen_store_option', 'delete_store_option'], 'string', 'max' => 1000],
            [['rate_type', 'rate_value'], 'string', 'max' => 16],
            [['reward_value'], 'string', 'max' => 32],
            [['tool_id', 'rectification_model', 'rectification_option'], 'safe'],
            [['re_photo_time'], 'integer', 'max' => 20, 'min' => 0],
            [['store_url'], 'string', 'max' => 150],
            [['short_cycle'], 'string', 'max' => 1000],
//            ['reward_time', 'validateRewardTime', 'on' => ['default', 'update']],
            ['start_time', 'validateTimeConflict', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validateStandard', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validateStore', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validateTool', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validateEnded', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validateEditable', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['standard_id', 'validatePlanEnable', 'on' => ['enable']],
            ['standard_id', 'validateProtocol', 'on' => ['enable']],
            ['short_cycle', 'validateShortCycle', 'on' => ['default', 'update', 'update-time', 'enable']],
            ['rectification_model', 'validateRectificationModel', 'on' => ['default', 'update', 'update-time', 'enable']],
        ];
    }

    /**
     * @return array
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['update'] = [
            'start_time', 'end_time', 'standard_id', 'tool_id', 'rate_type', 'set_store_type', 're_photo_time',
            'reward_time', 'reward_amount', 'reward_mode', 'is_push_zft', 'is_qc', 'short_cycle', 'rectification_model', 'question_model'
        ];  // 启用状态只能修改更新 时间
        $scenarios['update-time'] = ['start_time', 'end_time', 'standard_id', 'short_cycle'];
        $scenarios['enable'] = ['plan_status', 'standard_id'];
        $scenarios['update-store'] = ['store_url', 'store_url_status', 'set_store_type'];  // 修改只能更新 售点
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'project_id' => '项目id',
            'standard_id' => '检查项目',
            'title' => '标题',
            'company_code' => '厂房',
            'bu_code' => 'BU',
            'user_id' => '用户',
            'description' => '描述',
            'rate_type' => '检查频率',
            'rate_value' => '频率值',
            'max_reward_time' => '最大奖励次数',
            'reward_is_auto' => '是否自动发',
            'reward_type' => '奖励类型',
            'reward_option' => '奖励配置',
            'reward_value' => '奖励金或积分数，优惠券券码',
            'rectification_option' => '整改配置数组',
            'start_time' => '检查开始时间',
            'end_time' => '结束时间',
            'tool_id' => '执行工具',
            'store_url' => '售点url文件',
            'store_url_status' => '售点文件状态',
            'push_tool_option' => '推送执行工具设置',
            'must_take_photo' => '拍照留底',
            'reward_time' => '有奖拍摄次数',
            'reward_mode' => '奖励发放模式',
            'reward_amount' => '奖励金额',
            'set_store_type' => '售点设置方式',
            're_photo_time' => '整改次数',
            'plan_status' => '检查执行状态',
            'rectification_model' => '整改模式',
            'screen_store_option' => '售点筛选条件',
            'delete_store_option' => '售点删除条件',
            'short_cycle' => '短周期模式的配置时间',
            'update_time' => 'db更新时间',
            'status' => '删除标识：1有效，0无效',
            'is_push_zft' => '是否推送ZFT',
            'is_qc' => '是否人工复核',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'question_model' => '问卷填写模式',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStandard()
    {
        return $this->hasOne(Standard::class, ['id' => 'standard_id']);
    }

    public function getStoreRelation()
    {
        return $this->hasMany(PlanStoreRelation::class, ['plan_id' => 'id']);
    }

    public function getTool()
    {
        return $this->hasOne(Tools::class, ['id' => 'tool_id']);
    }

    public function load($data, $formName = null)
    {
        $result = parent::load($data, $formName);
        if ($this->reward_mode == '') {
            $this->reward_mode = 0;
        }
        if ($this->re_photo_time != null && $this->rectification_option == null) {
            $this->rectification_option = (string)$this->re_photo_time;
        }
        if (isset($data['delete_store_option'])) {
            $this->delete_store_option = $data['delete_option'];
        }
        if (isset($data['screen_store_option'])) {
            $this->screen_store_option = $data['screen_option'];
        }
        return $result;
    }


    /**
     * 查询检查计划列表
     * pager = ['page'=> 3, 'pageSize'=> 20] pageSize 尽量不要大于2000
     *
     * @param       $select
     * @param       $where
     * @param       $pager
     * @param bool $arr_flag
     * @param array $order
     *
     * @return array
     */
    public static function getList($select, $where, $pager, $arr_flag = true, $order = [])
    {

        $query = self::find();
        $query->select($select);
        $query->andFilterWhere(['standard_id' => $where['standard_id']]);
        $query->andFilterWhere(['tool_id' => $where['tool_id']]);
        $query->andFilterWhere(['=', 'plan_batch_id', 0]);

        if ($where['created_start'] != '') {
            $query->andFilterWhere(['>=', new Expression(self::tableName() . '.created_at'), $where['created_start']]);
        }
        if ($where['created_end'] != '') {
            $query->andFilterWhere(['<=', new Expression(self::tableName() . '.created_at'), $where['created_end']]);
        }
        // 检查时间
        if ($where['start_time'] != '' && $where['end_time'] == '') {
            $query->andFilterWhere(['>=', 'end_time', $where['start_time'] . ' 23:59:59']);
        }
        if ($where['end_time'] != '' && $where['start_time'] == '') {
            $query->andFilterWhere(['<=', 'start_time', $where['end_time'] . ' 00:00:00']);
        }
        if ($where['end_time'] != '' && $where['start_time'] != '') {
            $query->andFilterWhere([
                'and',
                ['>=', 'end_time', $where['start_time'] . ' 23:59:59'],
                ['<=', 'start_time', $where['end_time'] . ' 00:00:00'],
            ]);
        }

        $where['start_time'] = $where['start_time'] . ' 00:00:00';
        $where['end_time'] = $where['end_time'] . ' 23:59:59';

        $query->andWhere(['=', new Expression(self::tableName() . '.status'), self::DEL_STATUS_NORMAL]);

        $query->joinWith('standard');
        $query->joinWith('tool');
        if ($arr_flag) {
            $query->asArray();
        }

        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        // company_bu 字段要特殊处理
        User::buFilterSearch($query, $where['company_bu'], Plan::class);

        if (!empty($order)) {
            $query->orderBy($order);
        }
        $count = $query->count();

        $page = $pager['page'];
        $pageSize = $pager['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);
        $data = $query->all();
        return ['list' => $data, 'count' => (int)$count];
    }

    /**
     * 新增和修改时，验证时间是否有冲突
     * 不考虑状态是否启用
     */
    public function validateTimeConflict()
    {
        // 先找到其他检查计划
        $query = Plan::find()->where([self::DEL_FIELD => self::DEL_STATUS_NORMAL,])->asArray();
//        $user = Yii::$app->params['user_info'];
//        $query->where(['status' => Plan::DEL_STATUS_NORMAL, 'company_code' => $user['company_code'], 'bu_code' => $user['bu_code']]);
        $query->where(['status' => Plan::DEL_STATUS_NORMAL]);
        $query->andWhere([
            'or',
            [
                'between', 'start_time', $this->start_time . ' 00:00:00', $this->end_time . ' 23:59:59',
            ],
            [
                'between', 'end_time', $this->start_time . ' 00:00:00', $this->end_time . ' 23:59:59',
            ],
        ]);
        $query->andWhere(['tool_id' => $this->tool_id, 'standard_id' => $this->standard_id]);

        //
        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }
        $one = $query->one();
        if ($one !== null) {
            $this->addError('start_time', '检查计划时间有冲突，检查计划id：' . $one['id']);
        }
    }

    /**
     * 执行工具为cp时，需要校验必填
     * 执行工具为cp，且非协议类检查项目才校验
     */
    public function validateRewardTime()
    {
        $standard = $this->standard_one;
        if ($this->tool_id == Tools::TOOL_ID_CP && $standard->protocol_id == 0 && $this->reward_time <= 0) {
            $this->addError('reward_time', '执行工具为CP时，必须填写有奖拍摄次数');
        }
    }

    /**
     * 检查计划必须是启用状态
     */
    public function validateStandard()
    {
        $this->validateStandardStatus();
        $this->validateSetStoreType();
//        $this->validateProtocol();
    }

    private function validateStandardStatus()
    {
        $standard = $this->standard_one;
        if (empty($standard)) {
            $this->addError('standard_id', '检查项目不存在');
        } else if ($standard['standard_status'] != Standard::STATUS_AVAILABLE) {
            $this->standard_one = $standard;
            $this->addError('standard_id', '检查项目未启用');
        }
        // 如果是批量创建，需要校验检查项目是长期协议
        if ($this->plan_batch_id != 0 && $standard->check_type_id != CheckType::LONG_AGREEMENTS['check_type_id']) {
            $protocol = ProtocolTemplate::findOneArray(['id' => $standard->protocol_id]);
            $this->addError('standard_id', 'ZFT协议“' . $protocol['contract_code'] . '”对应的检查项目不是长期协议类');
        }
    }

    /**
     * 协议类，且非随报随拍，售点导入可选 zft 或excel
     * zft 只能创建1个检查计划，excel可以多个检查计划
     * 协议类，随报随拍，只能选excel，只能1个执行工具
     * @return bool
     */
    public function validateProtocol()
    {
        $standard = $this->standard_one;
        if ($standard == null) {
            $standard = Standard::findOne(['id' => $this->standard_id]);
        }
        /* @var $standard Standard */
        if ($standard == null) {
            $this->addError('standard_id', '检查项目不存在');
            return false;
        }

        if ($standard->protocol_id == 0) {
            return true;
        }

        // 创建的时候就需要校验，所以这里如果是创建，是有1个就冲突了
        $plans = Plan::findAllArray(['standard_id' => $this->standard_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE, 'plan_batch_id' => 0]);

        if (count($plans) > 0) {
            // 非随报随拍，混合配置（excel导入+筛选项）只能有1个检查计划，或者全部是纯手工导入（只有excel导入）
            // 创建时不需要校验，这里注释掉
            if ($standard->photo_type == Standard::PHOTO_TYPE_DEFAULT) {
                if ($this->set_store_type == Plan::SET_STORE_EXCEL && (
                        Plan::hasFilter($this['screen_store_option'], PlanStoreTmp::IMPORT_TYPE_ADD)
                        || Plan::hasFilter($this['delete_store_option'], PlanStoreTmp::IMPORT_TYPE_DELETE))) {
                    $this->addError('standard_id', '当前检查项目（协议类 非随报随拍），不可同时有纯导入售点和混合配置售点的检查计划：' . $plans[0]['id']);
                    return false;
                }
            }
            if ($standard->photo_type == Standard::PHOTO_TYPE_FREE) {
                $this->addError('standard_id', '当前检查项目（协议类 随报随拍），只能有1个检查计划');
                return false;
            }
            foreach ($plans as $plan) {
                if ($standard->photo_type == Standard::PHOTO_TYPE_DEFAULT) {
                    if ($plan['set_store_type'] == Plan::SET_STORE_EXCEL && (
                            Plan::hasFilter($plan['screen_store_option'], PlanStoreTmp::IMPORT_TYPE_ADD)
                            || Plan::hasFilter($plan['delete_store_option'], PlanStoreTmp::IMPORT_TYPE_DELETE))) {
                        $this->addError('standard_id', '当前检查项目（协议类 非随报随拍），不可同时有纯导入售点和混合配置售点的检查计划：' . $plan['id']);
                        return false;
                    }
                }
                if ($plan['set_store_type'] != Plan::SET_STORE_EXCEL) {
//                        $str = '';
                    if ($plan['set_store_type'] == Plan::SET_STORE_ZFT) {
                        $str = '当前检查项目（协议类 非随报随拍）， 售点若同步ZFT则只可创建一个检查计划';
                        $this->addError('standard_id', $str);
                    }
//                        if($plan['set_store_type'] == Plan::SET_STORE_DEFAULT){
//                            $str = '当前检查项目（协议类 非随报随拍）， 有检查计划未配置售点导入方式';
//                        }
//                        $this->addError('standard_id', $str);
//                        $this->addError('standard_id', '当前检查项目（协议类 非随报随拍） 售点配置选择了ZFT同步，只能配置1个检查计划');
//                        return false;
                }
            }
        }
        $this->validateProtocolCheckTime();
        return true;
    }

    public function validatePlanEnable()
    {
        $standard = $this->standard_one;
        if ($standard == null) {
            $standard = Standard::findOne(['id' => $this->standard_id]);
        }
        // 协议类，时间都是相同的，只考虑检查计划的数量
        if ($standard->protocol_id > 0) {
            if ($standard->photo_type == Standard::PHOTO_TYPE_DEFAULT && $this->set_store_type == Plan::SET_STORE_DEFAULT) {
                $this->addError('standard_id', '还未配置售点导入方式');
                return false;
            }
        }
        return true;
    }

    /**
     * ZFT协议中的获取的“检查次数”
     * 限制：只能是1次，0或大于1次都不让进行到下一步
     * （提示：检查次数只能为1次，请先调整检查项目
     * 关联的ZFT协议后重新调整检查项目）
     * 协议类 长期协议 不校验检查次数必须为1
     */
    private function validateProtocolCheckTime()
    {
        $protocol = ProtocolTemplate::findOne(['id' => $this->standard_one->protocol_id]);
        if ($protocol != null && $this->standard_one->check_type_id != CheckType::LONG_AGREEMENTS['check_type_id']) {
            if ($protocol->excute_count != 1) {
                $this->addError('standard_id', '检查次数只能为1次，请先调整检查项目关联的ZFT协议后重新调整检查项目');
            }
        }
    }

    /**
     * 协议类检查计划，且随报随拍，只能excel导入售点
     */
    private function validateSetStoreType()
    {
        $standard = $this->standard_one;
        if ($standard == null) {
            $standard = Standard::findOne(['id' => $this->standard_id]);
        }
        /* @var $standard Standard */
        if ($standard == null) {
            $this->addError('standard_id', '检查项目不存在');
            // 初始状态也不需要状态
        } else if ($standard->photo_type == Standard::PHOTO_TYPE_FREE
            && !$this->isNewRecord
            && ($this->set_store_type != Plan::SET_STORE_EXCEL && $this->set_store_type != Plan::SET_STORE_DEFAULT)) {
            $this->addError('standard_id', '随报随拍类检查项目，只能excel导入售点');
        }

    }

    /**
     * 验证执行工具的特殊需求
     * cp时，检查项目的生动化标准必选“是”
     * 协议类检查项目，执行工具必须为sfa
     */
    public function validateTool()
    {
        $standard = $this->standard_one;
//        $check_type = $standard->getCheckType()->one();
//        if ($check_type->type == CheckType::IS_PROTOCOL_YES && $this->tool_id != Tools::TOOL_ID_SFA) {
//            $this->addError('standard_id', '检查项目为“协议类”，执行工具只能为SFA');
//        }
        switch ($this->tool_id) {
            case Tools::TOOL_ID_SEA:
                break;
            case Tools::TOOL_ID_CP:
                // 协议类肯定有生动化，不需要校验
//                if ($standard->protocol_id == 0 && $standard->set_vividness != Standard::SET_VIVIDNESS_YES) {
//                    $this->addError('standard_id', '执行工具为CP，所选检查项目的生动化标准不可为空');
//                }
                $this->validateRewardTime();
                break;
            case Tools::TOOL_ID_SFA:
                // 41.1.1	去掉“是否设置生动化标准”（同时非协议类检查计划中SFA和CP去掉必须有此项的判断）
//                if ($standard->protocol_id == 0 && $standard->set_vividness != Standard::SET_VIVIDNESS_YES) {
//                    $this->addError('standard_id', '非协议类检查项目分发给该执行工具需要设置生动化标准');
//                }
                // 同一bu，同一检查项目不能创建多个检查计划
//                $where = ['and', ['standard_id' => $this->standard_id]];
//                if ($this->id != null) {
//                    $where[] = ['<>', 'id', $this->id];
//                }
//                $plan = Plan::findOneArray($where, ['id'], true);
//                if (!empty($plan)) {
//                    $this->addError('standard_id', '执行工具为SFA，同一检查项目不能创建多个检查计划');
//                }
//                $this->validateProtocol();
                break;
            default:
        }

    }

    public function validateReward($config)
    {
        $result = true;
        // 协议类CP不需要校验
        $standard = $this->standard_one;
        if ($this->tool_id == Tools::TOOL_ID_CP && $standard->protocol_id == 0 && $this->reward_mode == Plan::REWARD_MODE_SCENE) {
            if (empty($config)) {
                $this->addError('reward_mode', 'CP场景模式下，必须配置场景奖励金额');
            } else {
                foreach ($config as $item) {
                    if ($item['reward_amount'] == 0) {
                        $result = $result && $this->addError('reward_mode', 'CP场景模式下，必须配置场景奖励金额');
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 校验售点
     * 启用时
     */
    public function validateStore()
    {
        // 非协议类检查计划，售点必须先配置
        $standard = $this->getStandard()->one();
        $check = $standard->getCheckType()->one();
        if ($this->plan_status == self::PLAN_STATUS_ENABLE && ($check->type == CheckType::IS_PROTOCOL_NO || $this->set_store_type != Plan::SET_STORE_ZFT)) {
            $query = PlanStoreRelation::find()->where(['plan_id' => $this->id]);
            $count = $query->count();
            if ($count == 0) {
                $this->addError('standard_id', '还未配置售点，不能启用');
            }
        }
    }

    /**
     * 小周期字段校验
     */
    public function validateShortCycle()
    {
        $short_cycle_list = json_decode($this->short_cycle, true);
        if (!empty($short_cycle_list)) {
            //小周期的时间范围必须在检查时间范围内
            foreach ($short_cycle_list as $short_cycle) {
                if (strtotime($short_cycle['start_time']) < strtotime($this->start_time) || strtotime($short_cycle['end_time']) > strtotime($this->end_time)) {
                    $this->addError('short_cycle', '小周期的时间范围必须在检查时间范围内');
                    return;
                }
            }
            //每个小周期的时间范围不可以有交叉
            $is_time_cross = $this->isTimeCross($short_cycle_list, 'start_time', 'end_time');
            if ($is_time_cross) {
                $this->addError('short_cycle', '每个小周期的时间范围不可以有交叉');
                return;
            }
        }
    }

    /**
     * 整改模式字段校验
     */
    public function validateRectificationModel()
    {
        if (!in_array($this->rectification_model, [self::RECTIFICATION_MODEL_WITH_CYCLE, self::RECTIFICATION_MODEL_NONE])) {
            $this->addError('rectification_model', '整改模式非法');
            return;
        } else {
            $standard_new = Standard::findOneArray(['id' => $this->standard_id]);
            //非协议类检查项目
            if ($standard_new['protocol_id'] == 0) {
                //1、执行工具为SFA时，
                //无生动化映射：整改模式，默认为“无”，不可更改
                //有生动化映射：整改模式，默认为“最小周期内有限次整改”，不可更改
                if ($this->tool_id == Tools::TOOL_ID_SFA) {
                    $scene = json_decode($standard_new['scenes'], true);
                    $output_list = ArrayHelper::getColumn($scene, 'outputList', []);
                    if (!empty($output_list[0]) && $this->rectification_model != self::RECTIFICATION_MODEL_WITH_CYCLE) {
//                        $this->addError('rectification_model', '整改模式必须为最小周期内有限次整改');
                        $this->rectification_model = self::RECTIFICATION_MODEL_WITH_CYCLE;
//                        return;
                    }
                    if (empty($output_list[0]) && $this->rectification_model != self::RECTIFICATION_MODEL_NONE) {
                        $this->rectification_model = self::RECTIFICATION_MODEL_NONE;
//                        $this->addError('rectification_model', '整改模式必须为无');
//                        return;
                    }
                } //2、执行工具为CP时，整改模式，默认为“最小周期内有限次整改”，不可更改
                else if ($this->tool_id == Tools::TOOL_ID_CP) {
                    if ($this->rectification_model != self::RECTIFICATION_MODEL_WITH_CYCLE) {
                        $this->rectification_model = self::RECTIFICATION_MODEL_WITH_CYCLE;
//                        $this->addError('rectification_model', '整改模式必须为最小周期内有限次整改');
//                        return;
                    }
                }
                //3、执行工具为SEA时，整改模式可支持两种无需校验
            } else {
                //协议类短期检查项目（执行工具SFA、CP、SEA），整改模式默认为“最小周期内有限次整改”，不可更改
                if (in_array($this->tool_id, [Tools::TOOL_ID_SEA, Tools::TOOL_ID_CP, Tools::TOOL_ID_SFA])
                    && $this->rectification_model != self::RECTIFICATION_MODEL_WITH_CYCLE) {
                    $this->rectification_model = self::RECTIFICATION_MODEL_WITH_CYCLE;
//                    $this->addError('rectification_model', '整改模式必须为最小周期内有限次整改');
//                    return;
                }
            }
        }
    }

    public function beforeValidate()
    {
        if ($this->standard_one == null)
            $this->getOne('standard_one', Standard::class, $this->standard_id);
        return parent::beforeValidate();
    }

    public function beforeSave($insert)
    {
        if (is_array($this->must_take_photo)) {
            $this->must_take_photo = implode(',', $this->must_take_photo);
        }
        // 截止时间保存为 xxxx-xx-xx 23:59:59
        if (strlen($this->end_time) > 10) {
            $this->end_time = substr($this->end_time, 0, 10) . ' 23:59:59';
        } else {
            $this->end_time .= ' 23:59:59';
        }
        return parent::beforeSave($insert);
    }


    /**
     * 上传售点列表的excel有没有异常
     * #暂时不校验：校验第一列是否为id，售点id是否在主数据中
     * @param $plan_id int 检查计划id
     * @param $file array 文件url
     * @return array
     */
    public static function checkStoreUploadFile($plan_id, $file)
    {
        preg_match('/.(xlsx?)$/', $file['name'], $matchs);
        if (empty($matchs)) {
            return ['status' => false, 'msg' => '文件格式错误'];
        }
        $ext = $matchs[1];
//
//        $res = get_headers($url,true);
//        $filesize = round($res['Content-Length']/1024/1024,2);
        $filesize = round($file['size'] / 1024 / 1024, 2);
        if ($filesize > 8) {
            return ['status' => false, 'msg' => '文件大小不能超过8M'];
        }

//        $s = file_get_contents($url);
//        $s = file_get_contents($file['tmp_name']);
        $path = Yii::getAlias('@runtime');  //文件路径和文件名
        $file_name = '/' . $plan_id . '_' . time() . '.' . $ext;

        if (!is_writeable($path)) {
            return ['status' => false, 'msg' => '文件上传失败，目录没有权限'];
        }
        $path .= $file_name;
//        file_put_contents($path, $s);

        if (!file_exists($file['tmp_name'])) {
            return ['status' => false, 'msg' => '上传的临时文件已经丢失'];
        }
        move_uploaded_file($file['tmp_name'], $path);
        if (strtoupper($ext) == 'XLS') {
            $ext = 'Xls';
        }
        if (strtoupper($ext) == 'XLSX') {
            $ext = 'Xlsx';
        }
//
////        $spreadsheet = $reader->load($path); // 载入excel文件
////        $worksheet = $spreadsheet->getActiveSheet();
////        $highestRow = $worksheet->getHighestRow(); // 总行数
//
//        $reader = IOFactory::createReader($ext);
//        $chunkFilter = new ChunkReadFilter();
//        $reader->setReadFilter($chunkFilter);
//        $chunkSize = 2;
//        // Create a new Instance of our Read Filter
//
//        // Tell the Reader that we want to use the Read Filter that we've Instantiated
//        // Loop to read our worksheet in "chunk size" blocks
//        for ($startRow = 1; $startRow <= 500000; $startRow += $chunkSize) {
//            // Tell the Read Filter, the limits on which rows we want to read this iteration
//            $chunkFilter->setRows($startRow, $chunkSize);
//            // Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
//            $spreadsheet = $reader->load($path);
//            $worksheet = $spreadsheet->getActiveSheet();
//            $data = $worksheet->toArray(null, true, false, false);
////            $data = $worksheet->toArray();
//            if ($startRow == 1 && empty($data)) {
//                unlink($path);
//                return ['status' => false, 'msg' => '文件中没有数据'];
//            }
//            unset($data[0]);
//
//            $data = array_column($data, 0);
//
//            $data = array_unique($data);
//            // 每行售点id都检测是否在售点表中
//            $storeArr = Store::findAllArray(['store_id' => $data], ['id', 'store_id']);
//            $storeId = array_column($storeArr, 'store_id');
//            if (count($data) != count($storeArr)) {
//                $diff = array_diff_assoc($data, $storeId);
//                unlink($path);
//                return ['status' => false, 'msg' => '售点id不存在：' . $diff[array_keys($diff)[0]]];
//            }
//            foreach ($data as $i => $tmp) {
//                // 必须为int
//                if (!is_numeric($tmp) && $tmp != '') {
//                    unlink($path);
//                    return ['status' => false, 'msg' => '第' . ($i + $startRow) . '行 格式错误'];
//                }
//            }
//
//        }
        // 删除临时文件
//        unlink($path); 上传成功不删除，前端以文件流的形式上传，不提供url
        return ['status' => true, 'msg' => '', 'path' => $path, 'ext' => $ext];
    }

    /**
     * 通过条件查找规则配置与规则id
     * @param $where
     * @param $select
     * @return array|ActiveRecord[]
     */
    public static function getRuleCode($where, $select)
    {
        $query = self::find()->alias('p')
            ->leftJoin('sys_plan_store_relation ps', 'ps.plan_id = p.id')
            ->leftJoin('sys_standard s', 's.id = p.standard_id')
            ->groupBy(['ps.plan_id'])
            ->select($select)
            ->andWhere($where)
            ->asArray();
        return $query->all();
    }

    /**
     * 获取奖励配置
     * @return array
     */
    public function getRewardConfig()
    {
        $plan_config = PlanReward::find()
            ->where(['plan_id' => $this->id])
            ->andWhere([PlanReward::DEL_FIELD => PlanReward::DEL_STATUS_NORMAL])
            ->asArray()->all();
        $result = [];
        if (!empty($plan_config)) {
            $scene_type_ids = array_column($plan_config, 'scene_type');
            $scene_type = SceneType::findAllArray(['id' => $scene_type_ids], ['id', 'name'], 'id');
            $scene_codes = array_column($plan_config, 'scene_code');
            $scene = Scene::findAllArray(['scene_code' => $scene_codes], ['id', 'scene_code', 'scene_code_name'], 'scene_code');
            $plan_config = ArrayHelper::index($plan_config, null, 'scene_index');
            foreach ($plan_config as $index => $item) {
                $reward_amount = '';
                $sub_activity_id = '';
                $scene_type_id = [];
                $scene_code = [];
                $labelSceneTypeArr = [];
                $labelSceneArr = [];
                foreach ($item as $reward) {
                    if ($reward['scene_code'] == '') {
                        $scene_type_id[] = $reward['scene_type'];
                        $labelSceneTypeArr[] = $reward['scene_type'] == SceneType::SCENE_TYPE_ALL ? SceneType::SCENE_TYPE_ALL_LABEL : $scene_type[$reward['scene_type']]['name'];
                    } else {
                        $scene_code[] = $reward['scene_code'];
                        $labelSceneArr[] = $scene[$reward['scene_code']]['scene_code_name'];
                    }
                    $reward_amount = $reward['reward_amount'];
                    $sub_activity_id = (int)$reward['sub_activity_id'];
                }
                $labelGroup = [implode('；', $labelSceneTypeArr), implode('；', $labelSceneArr)];
                $tmp = [
                    'index' => $index,
                    'scene_type_id' => $scene_type_id,
                    'sub_activity_id' => $sub_activity_id,
                    'scene_code' => $scene_code,
                    'label' => implode('；', array_filter($labelGroup)),
                    'reward_amount' => $reward_amount
                ];
                $result[] = $tmp;
            }
        }

        return $result;
    }

    /**
     * @param $model Plan
     * @param $bodyForm
     * @return array
     * @throws Exception
     */
    public static function savePlan($model, $bodyForm)
    {
        //新增短周期
        $bodyForm['short_cycle'] = isset($bodyForm['short_cycle']) && $bodyForm['short_cycle'] ? json_encode($bodyForm['short_cycle']) : '{}';
        $model->load($bodyForm, '');
        $model->getOne('standard_one', Standard::class, $model->standard_id);
        if ($model->validate() && $model->validateReward($bodyForm['reward_config'])) {
            $tran = Yii::$app->db->beginTransaction();
            $sync_flag = false;
            $standard_new = Standard::findOneArray(['id' => $model->standard_id]);
            if (!$model->isNewRecord) {
//                $model->setScenario('update');
                PlanReward::updateAll([PlanReward::DEL_FIELD => PlanReward::DEL_STATUS_DELETE], ['plan_id' => $model->id]);
                // 修改检查计划，如果涉及到了协议类检查项目变更，需要自动更新检查计划售点
                if ($model->getOldAttribute('standard_id') != $model->standard_id) {
                    $standard_old = Standard::findOneArray(['id' => $model->getOldAttribute('standard_id')]);
                    $check_type_old = CheckType::findOneArray(['id' => $standard_old['check_type_id']]);
                    $check_type_new = CheckType::findOneArray(['id' => $standard_new['check_type_id']]);
                    // 其中1个是协议类的，就需要重新关联售点
                    // 协议类，且非随报随拍,且ZFT导入
                    $sync_flag = ($check_type_old['type'] == CheckType::IS_PROTOCOL_YES || $check_type_new['type'] == CheckType::IS_PROTOCOL_YES) && $model->set_store_type == Plan::SET_STORE_ZFT;
                }
            }
            // 1.协议类短期检查项目（执行工具SFA、CP、SEA），整改模式默认为“最小周期内有限次整改”，不可更改
            // 2.非协议类检查项目，执行工具为SFA时，
            // 无生动化映射：整改模式，默认为“无”，不可更改
            // 有生动化映射：整改模式，默认为“最小周期内有限次整改”，不可更改
            // 3.非协议类检查项目，执行工具为CP时，整改模式，默认为“最小周期内有限次整改”，不可更改
            // 注：非协议类检查计划，对执行工具CP的支持功能先开发，但是测试完上线时，需暂时限制CP不可选
            // 4.非协议类检查项目，执行工具为SEA时，整改模式可支持两种：
            // 1）整改模式-无
            // 2）整改模式-最小周期内有限次整改
            // 注意：SEA的聚合API暂不考虑“整改模式”设置的的影响，即只要plan启用且检查时间在最小周期内（空档期不生成门店任务）就都正常聚合，finish时中台也正常接收并进行下面的流程
            /*if ($standard_new['protocol_id'] == 0) {
                // 非协议，走2、3、4
                switch ($model->tool_id) {
                    case Tools::TOOL_ID_SFA:
                        // todo 是否有生动化
                        if ($model->standard_one->aa) {
                            $model->rectification_model = self::RECTIFICATION_MODEL_NONE;
                            $model->rectification_option = '';
                        } else {
                            $model->rectification_model = self::RECTIFICATION_MODEL_WITH_CYCLE;
                        }
                        break;
                    case Tools::TOOL_ID_CP:
                        $model->rectification_model = self::RECTIFICATION_MODEL_NONE;
                        $model->rectification_option = '';
                        break;
                    case Tools::TOOL_ID_SEA:
                        // 不做强制校验
                        break;
                    default:
                }
            } else {
                // 协议，走1
                $check_type_new = CheckType::findOneArray(['id' => $standard_new['check_type_id']]);
                if ($check_type_new['id'] == CheckType::SHORT_AGREEMENTS['check_type_id']) {
                    $model->rectification_model = self::RECTIFICATION_MODEL_WITH_CYCLE;
//                    $model->rectification_option = '';
                }
            }*/
//            else {
//                $check_type_new = CheckType::findOneArray(['id' => $standard_new['check_type_id']]);
//                $sync_flag = $check_type_new['type'] == CheckType::IS_PROTOCOL_YES;
            // 新增不需要直接同步，要选到售点导入方式为 ZFT导入 时才同步
            // 新增时，协议类检查项目，售点列表自动从签约客户表获取
//            }
            $model->re_photo_time = $model->rectification_option != '' ? $model->rectification_option : '0';
            $model->save(false);
            if ($sync_flag) {
                $model->syncStoreFromProtocol($standard_new);
            }
            // 场景模式才需要单独保存奖励
            // 非协议类才需要保存
            if ($model->reward_mode == Plan::REWARD_MODE_SCENE && $model->standard_one->protocol_id == 0 && $model->tool_id == Tools::TOOL_ID_CP) {
                foreach ($bodyForm['reward_config'] as $item) {
                    foreach ($item['scene_type_id'] as $scene_type) {
                        $reward = new PlanReward();
                        $reward->plan_id = $model->id;
                        $reward->scene_index = $item['index'];
                        $reward->sub_activity_id = $item['sub_activity_id'];
                        $reward->scene_type = $scene_type;
                        $reward->scene_code = '';
                        $reward->reward_amount = $item['reward_amount'];
                        $reward->save();
                    }

                    $scenes = Scene::findAllArray(['scene_code' => $item['scene_code']], ['*'], 'scene_code');
                    foreach ($item['scene_code'] as $scene) {
                        $reward = new PlanReward();
                        $reward->plan_id = $model->id;
                        $reward->scene_index = $item['index'];
                        $reward->sub_activity_id = $item['sub_activity_id'];
                        $reward->scene_type = $scenes[$scene]['scene_type'];
                        $reward->scene_code = $scenes[$scene]['scene_code'];
                        $reward->reward_amount = $item['reward_amount'];
                        $reward->save();
                    }
                }
            }

            $tran->commit();
            return ['success' => true, 'id' => $model->id];
        } else {
            $err = $model->getErrStr();
            return ['success' => false, 'msg' => $err];
        }
    }

    /**
     * 检查计划售点去重
     * 导入时不实时去重，等导完后，再对db数据去重
     * @param $plan_id
     * @throws Exception
     */
    public static function removeDuplicate($plan_id)
    {
        $sql = 'DELETE 
                FROM
                    ' . PlanStoreRelation::tableName() . ' 
                WHERE
                    plan_id = :plan_id
                    AND id NOT IN ( SELECT id FROM ( SELECT min( id ) AS id FROM ' . PlanStoreRelation::tableName() . '  WHERE plan_id = :plan_id GROUP BY store_id ) AS b )';
        Yii::$app->db->createCommand($sql, ['plan_id' => $plan_id])->execute();
        // 更新检查计划的线路字段
        self::updatePlanRouteCode($plan_id);
    }

    public static function updatePlanRouteCode($plan_id)
    {
        $sql = "update sys_plan p set route_code_str = (
                SELECT  GROUP_CONCAT(distinct route_code) route_code_str 
                from sys_plan_store_relation r
                LEFT JOIN sys_store s on s.store_id = r.store_id
                where plan_id = $plan_id
                GROUP BY plan_id)
                where id = $plan_id
                ;";
//        Yii::$app->db->createCommand($sql, ['plan_id' => $plan_id])->execute();
    }

    /**
     * 删除检查计划的售点
     * @param $plan_id
     * @param $store_id array 为空，全部删除
     * @throws Exception
     */
    public static function removeStore($plan_id, $store_id = [])
    {
        $sql = 'DELETE 
                FROM
                    ' . PlanStoreRelation::tableName() . ' 
                WHERE
                    plan_id = :plan_id';
        $param = ['plan_id' => $plan_id];
        if (!empty($store_id)) {
            $sql .= ' AND store_id in (';
            foreach ($store_id as $key => $item) {
                $sql .= ':store_id_' . $key . ',';
                $param[':store_id_' . $key] = $item;
            }
            // 去除最后一个逗号
            $sql = substr($sql, 0, -1);
            $sql .= ')';
        }
        Yii::$app->db->createCommand($sql, $param)->execute();
    }

    /**
     * 检查计划匹配的售点
     * @param $bodyForm
     * @return array
     */
    public static function getStoreList($bodyForm)
    {
        $query = PlanStoreRelation::find()->where(['plan_id' => $bodyForm['plan_id']])->asArray();
        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => $count, 'list' => $data];
    }

    /**
     * 检查计划excel导入的售点
     * @param $bodyForm
     * @return array
     */
    public static function getExcelImportList($bodyForm)
    {
        $query = self::getExcelImportQuery($bodyForm);
        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => $count, 'list' => $data];
    }

    /**
     * 已导入售点的query
     * @param $bodyForm
     * @return bQuery|\yii\db\ActiveQuery
     */
    public static function getExcelImportQuery($bodyForm)
    {
        return PlanStoreTmp::find()->where(['plan_id' => $bodyForm['plan_id'],
            'import_type' => $bodyForm['import_type'],
            'check_status' => [
                PlanStoreTmp::CHECK_STATUS_FILTER_PASS,
                PlanStoreTmp::CHECK_STATUS_FILTER_FAIL,
                PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS,
                PlanStoreTmp::CHECK_STATUS_PASS,
            ]])->asArray();
    }

    /**
     * 检查计划excel导入的失败售点
     * @param $bodyForm
     * @return array
     */
    public static function getExcelImportFailList($bodyForm)
    {
        $query = self::getExcelImportFailQuery($bodyForm);
        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => $count, 'list' => $data];
    }

    /**
     * 已导入失败售点的query
     * @param $bodyForm
     * @return bQuery|\yii\db\ActiveQuery
     */
    public static function getExcelImportFailQuery($bodyForm)
    {
        return PlanStoreTmp::find()->where(['plan_id' => $bodyForm['plan_id'],
            'import_type' => $bodyForm['import_type'],
            'check_status' => [
//                PlanStoreTmp::CHECK_STATUS_FAIL,
//                PlanStoreTmp::CHECK_STATUS_REAL_FAIL,
                PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL,
            ]])->asArray();
    }

    /**
     * 推送执行工具，数据已生成
     * @param $tmp
     */
    public static function sendPost($tmp)
    {
        $url = Yii::$app->params['tools']['update_question'];
//        LOG::log($url);
        $res = \Helper::curlQueryLog($url, $tmp, true, 5);
//        LOG::log($res);
    }

    /**
     * 生成售点检查数据入库
     * @param $store_ids
     * @param $tool_id
     * @param $task_id
     * @param $date
     * @param array $plan_ids
     * @param array $plansAll
     * @param array $standardsAll
     * @param array $scenesAll
     * @param array $scenesCodeAll
     * @param array $scenesTypeAll
     * @param array $tmp
     * @param array $protocolAll
     */
    public static function generateStoreCheckData($store_ids, $tool_id, $task_id, $date, $plan_ids = [], $plansAll = [], $standardsAll = [], $scenesAll = [], $scenesCodeAll = [], $scenesTypeAll = [], $tmp = [], $protocolAll = [])
    {
        $time = time();
        $ding = Ding::getInstance();
        if (empty($plan_ids)) {
            $plansAll = Plan::find()->where(['tool_id' => $tool_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE])
                ->andWhere(['<=', 'start_time', $date])
                ->andWhere(['>=', 'end_time', $date])
                ->asArray()
                ->select(['id', 'standard_id', 'rate_type', 'rate_value', 'must_take_photo', 'short_cycle'])
                ->indexBy('id')
                ->all();
            //去除在短周期的空档的计划
            foreach ($plansAll as $k => $v) {
                $flag = false;
                $dateTmp = date('Y-m-d H:i:s');
                $v['short_cycle'] = json_decode($v['short_cycle'], true);
                if ($v['short_cycle'] == null || empty($v['short_cycle'])) {
                    continue;
                } else if ($v['short_cycle'] && !empty($v['short_cycle'])) {
                    foreach ($v['short_cycle'] as $item) {
                        $start_time = $item['start_time'] . ' 00:00:00';
                        $end_time = $item['end_time'] . ' 23:59:59';
                        if ($start_time <= $dateTmp && $end_time >= $dateTmp) $flag = true;
                    }
                }
                if (!$flag) unset($plansAll[$k]);
            }
            if (empty($plansAll)) {
                $msg = "【检查计划】该执行工具没有匹配到检查计划，task_id:$task_id, date:$date, tool_id:$tool_id";
                $ding->sendTxt($msg);
                if (!empty($tmp))
                    Plan::sendPost($tmp);
                return;
            }
            LOG::log('检查计划开始生成售点检查数据');
            $plan_ids = array_column($plansAll, 'id');
        }
        if (empty($standardsAll)) {
            $standard_ids = array_column($plansAll, 'standard_id');
            // , 'standard_status' => Standard::STATUS_AVAILABLE 检查项目的状态不影响检查计划生成数据
            //取出检查项目类型用于ine项目剔除招牌
            $standardsAll = Standard::findAllArray(['id' => $standard_ids], ['id', 'scenes', 'question_manual_ir', 'question_manual', 'scenes_ir_id', 'protocol_id', 'check_type_id', 'standard_status'], 'id');
            //禁用状态的检查项目的详情要从快照表里取
            $disable_standards = [];
            foreach ($standardsAll as $k => $v) {
                if ($v['standard_status'] == Standard::STATUS_DISABLED) {
                    $disable_standards[] = $v['id'];
                    unset($standardsAll[$k]);
                }
            }
            if ($disable_standards) {
                $disable_standards = SurveyStandard::findAllArray(['standard_id' => $disable_standards, 'is_standard_disable' => SurveyStandard::IS_STANDARD_DISABLE_YES],
                    ['standard_id', 'scenes', 'question_manual_ir', 'question_manual', 'scenes_ir_id', 'standard_status', 'protocol_id'], 'standard_id');
                $standardsAll = $standardsAll + $disable_standards;
            }
        }
        if (empty($protocolAll)) {
            $protocol_ids = array_column($standardsAll, 'protocol_id');
            $protocolAll = ProtocolTemplate::findAllArray(['id' => $protocol_ids], ['id', 'activation_list'], 'id');
        }
        if (empty($scenesAll)) {
            $scenesAll = Scene::find()->select(['id', 'scene_type', 'scene_code'])->indexBy('id')->asArray()->all();
        }
        if (empty($scenesCodeAll)) {
            $scenesCodeAll = Scene::find()->select(['id', 'scene_type', 'scene_code'])->indexBy('id')->asArray()->all();
        }
        if (empty($scenesTypeAll)) {
            $scenesTypeAll = ArrayHelper::index($scenesCodeAll, 'scene_code', 'scene_type');
        }

        $query = PlanStoreRelation::find()->innerJoinWith('checkStore', false)
            ->andWhere(['plan_id' => $plan_ids, 'tool_id' => $tool_id, 'task_id' => $task_id])
            ->andWhere([
                'in', new Expression(PlanStoreRelation::tableName() . '.store_id'), $store_ids
            ]);

        $store_plan_data = $query->asArray()
            ->groupBy([new Expression(PlanStoreRelation::tableName() . '.store_id'),
                new Expression(PlanStoreRelation::tableName() . '.plan_id')])->all();

        $store_plan_data = ArrayHelper::index($store_plan_data, 'plan_id', 'store_id');
        $ding = Ding::getInstance();
        $tran = Yii::$app->db2->beginTransaction();
        $required_question_ids = [];
        foreach ($store_plan_data as $store_id => $datum) {
//                        LOG::log($store_id);
//                        $store_id = $datum['store_id'];
            $store_plan_ids = array_column($datum, 'plan_id');
            $store_plan = self::getData($plansAll, $store_plan_ids);
            $store_standard_ids = array_column($store_plan, 'standard_id');
            // 是否必须拍照需要合并几个检查计划的配置
            $store_must_take_photo = array_column($store_plan, 'must_take_photo');
            $store_must_take_photo = explode(',', trim(implode(',', $store_must_take_photo), ','));

            // 找出所有的检查项目
            $standards = self::getData($standardsAll, $store_standard_ids);
            if (empty($standards)) {
                $msg = '售点没有对应的检查项目 store_id:' . $store_id . ' task_id: ' . $task_id;
                $ding->sendTxt($msg);
                Yii::error($msg);
                continue;
            }
            $protocol_ids = array_column($standards, 'protocol_id');
            $protocols = self::getData($protocolAll, $protocol_ids);
            // 将所有检查项目的3个字段分别合并
//                        $tmp_arr = array_column($standards, 'question_manual_ir');
//                        $question_ids = [];
//                        foreach ($tmp_arr as $item) {
//                            $question_ids = array_merge($question_ids, json_decode($item, true));
//                        }
//                        $tmp_arr = array_column($standards, 'question_manual');
//
//                        foreach ($tmp_arr as $item) {
//                            $question_ids = array_merge($question_ids, json_decode($item, true));
//                        }
//                        $tmp_arr = array_column($standards, 'scenes_ir_id');
//                        $scenes_ir_id = [];
//                        foreach ($tmp_arr as $item) {
//                            $scenes_ir_id = array_merge($scenes_ir_id, json_decode($item, true));
//                        }
            $questionAndScenes = Standard::getQuestionAndScenes($standards, $protocols);

            $questionProperty = $questionAndScenes['question_property'];
            unset($questionAndScenes['question_property']);

            $question_manual = $questionAndScenes['question_manual'];
            $question_manual_ir = $questionAndScenes['question_manual_ir'];
            $question_ids = array_merge($question_manual, $question_manual_ir);

            $scenes_type_id = $questionAndScenes['scenes_type_id'];

            $scenes_tmp = [];
            foreach ($scenes_type_id as $item) {
                // 这里$item有999的情况
                // 而且还要考虑是不是ine
                foreach ($item['scenes_type_id'] as $type_id) {
                    if ($type_id == SceneType::SCENE_TYPE_ALL) {
                        $scenes_tmp = $scenesCodeAll;
                        //如果是ine检查项目，要删除需要删除的场景，目前只有一个是招牌
                        if ($item['is_ine']) {
                            $tmp = ArrayHelper::getColumn($scenes_tmp, 'scene_code');
                            if ($key = array_search(Scene::INE_NEED_DELETE[0], $tmp)) {
                                unset($scenes_tmp[$key]);
                            }
                        }
                        break;
                    }
                    $scenes_tmp = array_merge($scenes_tmp, $scenesTypeAll[$type_id]);
                }

            }
            $scenes_code = array_column($scenes_tmp, 'scene_code');

            $scenes_code = array_merge($scenes_code, $questionAndScenes['scenes_code']);
            $scenes_code = array_unique($scenes_code);
            if (!empty($scenes_code)) {
                //按照场景顺序排序
                $scenes_code = array_values(array_intersect(array_column($scenesCodeAll, 'scene_code'), $scenes_code));
//                            $scenes = $this->getData($scenesCodeAll, $scenes_code);
                $db_insert = [];
                foreach ($scenes_code as $scene_code) {
                    if ($scene_code === null) continue;
                    $one = [
                        'tool_id' => $tool_id,
                        'store_id' => $store_id,
                        'task_id' => $task_id,
                    ];
                    $one['scene_code'] = $scene_code;
                    $one['date'] = $date;
                    $one['created_at'] = $time;
                    $one['updated_at'] = $time;
                    $db_insert[] = array_values($one);

                }
                $field = ['tool_id', 'store_id', 'task_id', 'scene_code', 'date', 'created_at', 'updated_at'];
                Yii::$app->db2->createCommand()->batchInsert(CheckStoreScene::tableName(), $field, $db_insert)->execute();//执行批量添加
            }

            // 问卷
            // question表存的场景是id，需要转换为共享库的 scene_code
            if (!empty($question_ids)) {
//                $questions = Question::findAllArray(['id' => $question_ids], ['id', 'title', 'business_type_id', 'question_type', 'scene_type_id', 'type', 'is_ir']);
                $questions = Question::find()->where(['id' => $question_ids])->select(['id', 'title', 'business_type_id', 'question_type', 'scene_type_id', 'type', 'is_ir'])->asArray()->all();
                $db_insert = [];
                $business_types = QuestionBusinessType::findAllArray([], ['*'], 'id');
                $question_options = QuestionOption::findAllArray(['question_id' => $question_ids], ['*'], '', false, 'option_index');
                $question_options = ArrayHelper::index($question_options, 'id', 'question_id');
                foreach ($questions as $item) {
                    $one = [
                        'tool_id' => $tool_id,
                        'store_id' => $store_id,
                        'task_id' => $task_id
                    ];
                    $business_type = ['sort' => '0', 'title' => ''];
                    if (isset($business_types[$item['business_type_id']]))
                        $business_type = $business_types[$item['business_type_id']];

                    $question_option = [];
                    if (isset($question_options[$item['id']]))
                        $question_option = array_values($question_options[$item['id']]);

                    $one['date'] = $date;
                    // 其他字段
                    $one['question_id'] = $item['id'];
                    $one['business_type_id'] = $item['business_type_id'];
                    $one['business_type_sort'] = $business_type['sort'];
                    $one['business_type_label'] = $business_type['title'];
                    $one['question_options'] = json_encode($question_option, JSON_UNESCAPED_UNICODE);
                    $one['question_title'] = $item['title'];
                    $one['question_type'] = $item['question_type'];
                    $one['scene_code'] = isset($scenesAll[$item['scene_type_id']]) ? $scenesAll[$item['scene_type_id']]['scene_code'] : '';
                    $one['type'] = $item['type'];
                    $one['is_ir'] = $item['is_ir'];
                    $one['must_take_photo'] = $questionProperty[$item['id']]['must_take_photo'] ?? '0'; // 是否必拍
                    $one['is_required'] = $questionProperty[$item['id']]['is_required'] ?? '0';

                    $one['created_at'] = $time;
                    $one['updated_at'] = $time;
                    $db_insert[] = array_values($one);
                }
                $field = ['tool_id', 'store_id', 'task_id', 'date', 'question_id',
                    'business_type_id', 'business_type_sort', 'business_type_label', 'question_options',
                    'question_title', 'question_type', 'scene_code', 'type', 'is_ir', 'must_take_photo', 'is_required', 'created_at', 'updated_at'];
                Yii::$app->db2->createCommand()->batchInsert(CheckStoreQuestion::tableName(), $field, $db_insert)->execute();//执行批量添加
            }
        }

        $tran->commit();
    }

    /**
     * 获取对应的数组
     * @param $data array 数组，且key为plan_id
     * @param $ids
     * @return array
     */
    private static function getData($data, $ids)
    {
        $result = [];
        foreach ($ids as $key) {
            if (isset($data[$key])) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }

    /**
     * 更新检查计划的售点数据
     * @param array $standard_new
     * @throws Exception
     */
    public function syncStoreFromProtocol($standard_new = [])
    {
        // 同步签约售点数据时，如果检查计划是excel导入的
        // 不更新检查计划售点关联表
        if ($this->set_store_type != Plan::SET_STORE_ZFT) {
            return;
        }
        Plan::removeStore($this->id);
        $sql = 'insert into sys_plan_store_relation (plan_id, store_id)
                SELECT :plan_id, outlet_id
                FROM sys_protocol_store
                WHERE contract_id = :contract_id and store_status = 10';
        if (empty($standard_new)) {
            $standard_new = Standard::findOneArray(['id' => $this->standard_id]);
        }
        $protocol = ProtocolTemplate::findOneArray(['id' => $standard_new['protocol_id']]);
        $param = ['plan_id' => $this->id, 'contract_id' => $protocol['contract_id']];
        Yii::$app->db->createCommand($sql, $param)->execute();
        Plan::removeDuplicate($this->id);
    }

    /**
     * 根据售点、执行工具、日期获取售点检查内容
     * @param $tool_id
     * @param $start_date string 必传 开始时间
     * @param $end_date
     * @param array $store_ids
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getPlanByStore($tool_id, $start_date, $end_date, $store_ids = [])
    {
        $query = Plan::find()
            ->select([
                new Expression(PlanStoreRelation::tableName() . '.id'),
                new Expression(Standard::tableName() . '.title'),
                new Expression(Standard::tableName() . '.description'),
                //取出check_type_id用于判断删除场景
                new Expression(Standard::tableName() . '.check_type_id'),
                new Expression(Plan::tableName() . '.standard_id'),
                new Expression(Plan::tableName() . '.tool_id'),
                'contract_id' => new Expression('if(contract_id is null, "", contract_id)'),
                'contract_name' => new Expression('if(contract_name is null, "", contract_name)'),
                'store_id', 'plan_id', 'contract_id', 'contract_name', 'rate_type', 'scenes', 're_photo_time', 'rectification_model',
                'rate_value', 'must_take_photo', 'activation_list', 'start_time', 'end_time', 'short_cycle'])
            ->innerJoin(PlanStoreRelation::tableName(), Plan::tableName() . '.id = plan_id')
//            ->innerJoin(Standard::tableName(), Standard::tableName() . '.id = standard_id')
            ->leftJoin(ProtocolTemplate::tableName(), ProtocolTemplate::tableName() . '.id = protocol_id')
            ->joinWith('standard.subActivity')
            //检查项目状态要过滤没有启用的
//            ->where(['tool_id' => $tool_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE, 'standard_status' => Standard::STATUS_AVAILABLE])
            //不能直接过滤了，要用11月小版本的逻辑，此处依赖11月小版本
            ->where(['tool_id' => $tool_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE])
            ->andFilterWhere(['store_id' => $store_ids])
            ->asArray();
        if ($end_date) {
            $end_date .= ' 23:59:59';
            $query->andWhere(['or',
                ['between', 'start_time', $start_date, $end_date],
                ['between', 'end_time', $start_date, $end_date],
                ['and', ['<=', 'start_time', $start_date], ['>=', 'end_time', $end_date]],
            ]);
        } else {
            // 需要改回来
//            $query->andWhere(['>=', 'end_time', $start_date]);
            //
            $query->andWhere(
                ['and', ['<=', 'start_time', $start_date], ['>=', 'end_time', $start_date]]);
        }
        $query->groupBy(
            new Expression(PlanStoreRelation::tableName() . '.id'));
//            ->indexBy('id')
        return $query->all();
    }

    /**
     * 通过使用过该规则的plan的状态判断规则是否使用过
     * @param $params
     * @return array|null|ActiveRecord
     */
    public static function getStandardStatus($params)
    {
        return self::find()
            ->andWhere(['and', ['standard_id' => $params['standard_id']], ['<>', 'plan_status', Plan::PLAN_STATUS_DEFAULT]])
            ->one();
    }

    public static function findOne($condition, $bu_filter_flag = false)
    {
        $one = parent::findOne($condition, $bu_filter_flag);
        /* @var $one Plan */
        if ($one != null)
            $one->getOne('standard_one', Standard::class, $one->standard_id);
        return $one;
    }

    public function allOne($condition, $db = null)
    {
        return Plan::find()->where($condition)->one(null, false);
    }

    /**
     * 验证检查计划是否已经结束
     */
    public function validateEnded()
    {
        if ($this->getOldAttribute('end_time') < date('Y-m-d H:i:s') && $this->plan_status != self::PLAN_STATUS_DEFAULT) {
            $this->addError('end_time', '检查计划已经结束无法修改');
        }
    }

    /**
     * 验证检查计划是否可以编辑
     */
    public function validateEditable()
    {
        if (!$this->isNewRecord && !$this->editable) {
            $this->addError('editable', '当前检查计划已经无法编辑，请删除后重新创建');
        }
    }

    /**
     * 时间段重合判断
     * @param array $data 日期数组
     * @param string $fieldStart 开始日期字段名
     * @param string $fieldEnd 结束日期字段名
     * @return bool true为重合，false为不重合
     */
    public function isTimeCross(array $data, string $fieldStart = 'start_day', string $fieldEnd = 'end_day')
    {
        $columns = array_column($data, $fieldStart);
        // 按开始日期排序
        array_multisort(
            $columns,
            SORT_ASC,
            $data
        );

        // 冒泡判断是否满足时间段重合的条件
        $num = count($data);
        for ($i = 1; $i < $num; $i++) {
            $pre = $data[$i - 1];
            $current = $data[$i];
            if (strtotime($pre[$fieldStart]) <= strtotime($current[$fieldEnd]) && strtotime($current[$fieldStart]) <= strtotime($pre[$fieldEnd])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取冲突的售点列表 协议非随报随拍
     * @param $plan_id
     * @param string[] $select
     * @return bQuery|\yii\db\ActiveQuery|null
     */
    public static function getConflictStoreQuery($plan_id, $select = ['t.*'])
    {
        $plan = Plan::findOne(['id' => $plan_id]);
        $standard = Standard::findOne(['id' => $plan['standard_id']]);
        if ($standard->protocol_id > 0 && $standard->photo_type == Standard::PHOTO_TYPE_DEFAULT) {
            $plans = Plan::findAllArray(['standard_id' => $plan['standard_id']]);
            if (count($plans) > 1) {
                $planIds = array_column($plans, 'id');
                $planIdsStr = implode(',', $planIds);
                $query = PlanStoreTmp::find()->alias('t')
                    ->select($select)
                    ->leftJoin(PlanStoreRelation::tableName() . ' r',
                        'r.store_id = t.store_id and r.plan_id <> t.plan_id and r.plan_id in (' . $planIdsStr . ')')
                    ->where(['and', ['t.plan_id' => $plan_id], ['not', ['r.id' => null]]]);
                $query->asArray();
                return $query;
            }
        }
        return null;
    }

    /**
     * 返回是否设置了筛选项
     * @param $screen
     * @param $type
     * @return bool
     */
    public static function hasFilter($screen, $type = PlanStoreTmp::IMPORT_TYPE_ADD)
    {
        if (is_string($screen)) {
            $screen = json_decode($screen, true);
        }
        if ($type == PlanStoreTmp::IMPORT_TYPE_ADD) {
            unset($screen['up_file']);
        }

        return !empty($screen);
    }

    /*
     * 获取一条数据
     * */
    public static function getOneById($id)
    {
        $model = self::find()->where(['id' => $id])->asArray()->one();
        return $model;
    }

    /**
     * 人工复核任务列表
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午4:34
     *
     * @param $data
     *
     * @return array
     */
    public static function getManualReviewList($data)
    {
        $query = self::find()->where(['is_qc' => self::IS_QC_YES])->andWhere(['<>', 'plan_status', Plan::PLAN_STATUS_DEFAULT]);

        if (isset($data['standard_id']) and !empty($data['standard_id'])) {
            $query->andWhere(['standard_id' => $data['standard_id']]);
        }
        if (isset($data['tool_id']) and !empty($data['tool_id'])) {
            $query->andWhere(['tool_id' => $data['tool_id']]);
        }

        if (!empty($data['create_start_time'])) {
            $query->andWhere(['>=', new Expression(self::tableName() . '.created_at'), strtotime($data['create_start_time'])]);
        }
        if (!empty($data['create_end_time'])) {
            $query->andWhere(['<=', new Expression(self::tableName() . '.created_at'), strtotime($data['create_end_time'] . ' 23:59:59')]);
        }
        // 检查时间
        if (!empty($data['start_time']) and empty($data['end_time'])) {
            $query->andWhere(['>=', 'end_time', $data['start_time'] . ' 23:59:59']);
        }
        if (!empty($data['end_time']) and empty($data['start_time'])) {
            $query->andWhere(['<=', 'start_time', $data['end_time'] . ' 00:00:00']);
        }
        if (!empty($data['start_time']) and !empty($data['end_time'])) {
            $query->andWhere([
                'and',
                ['>=', 'end_time', $data['start_time'] . ' 23:59:59'],
                ['<=', 'start_time', $data['end_time'] . ' 00:00:00'],
            ]);
        }
        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        $count = $query->count('*', null, false);

        $data = $query->select(['id', 'tool_id', 'standard_id', 'start_time', 'end_time', 'created_at'])
            ->orderBy('created_at DESC')
            ->offset(($data['page'] - 1) * $data['page_size'])
            ->limit($data['page_size'])
            ->asArray()
            ->all(null, false);

        return ['list' => $data, 'count' => (int)$count];
    }
}

