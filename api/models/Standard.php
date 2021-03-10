<?php

namespace api\models;

use api\models\share\Scene;
use api\models\share\SceneType;
use api\service\plan\PlanService;
use api\service\zft\Protocol;
use yii\data\Pagination;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use Yii;
use yii\helpers\BaseInflector;

/**
 * This is the model class for table "{{%standard}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property int $check_type_id 检查类型
 * @property string $user_id 用户id
 * @property string $protocol_id 协议模板id
 * @property string $company_code 厂房code
 * @property string $bu_code bu_code
 * @property string $title 标题别名
 * @property string $image 图片
 * @property string $description 检查要求描述
 * @property string $engine_rule_code 规则配置id
 * @property int $set_rule 是否已经设置过规则 0 未设置 1 已设置
 * @property string $question_manual_ir ir问卷
 * @property string $question_manual 非ir问卷
 * @property string $scenes_ir_id 场景id
 * @property string $scenes 场景配置
 * @property int $setup_step 设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置完成
 * @property int $standard_status 启用状态0未启用，1启用，2禁用
 * @property int $is_change 规则问卷修改状态，0无修改，1有修改
 * @property int $pos_score 标准满分
 * @property int $set_main 是否设置主检查项 0 初始状态 1 是 2 否
 * @property int $set_vividness 是否设置生动化项 0 初始状态 1 是 2 否
 * @property int $photo_type 拍照类别：0、普通模式，1随报随拍
 * @property int $is_deleted 是否被用户删除，0:否 1:是
 * @property int $is_need_qc 是否需要qc：0初始状态，1需要qc，2不需要
 * @property int $need_qc_data 需要qc的生动化数据
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class Standard extends baseModel
{
    const HAS_3004 = true;
    const BU_FLAG = true;
    const ENABLE_STATUS_FIELD = 'standard_status';

    const STATUS_DEFAULT = 0;       // 初始状态
    const STATUS_AVAILABLE = 1;     // 启用
    const STATUS_DISABLED = 2;      // 禁用

    const NOT_CHANGE = 0;       // 没有修改
    const IS_CHANGE = 1;     // 有修改

    const SETUP_STEP_DEFAULT = 0;     // 初始化
    const SETUP_STEP_BUILD = 1;     // 创建检查项目
    const SETUP_STEP_PHOTO = 2;     // 配置拍照
    const SETUP_STEP_SET_RULE = 3;     // 设置规则
    const SETUP_STEP_TRANSFORM = 4;     // 设置整改
    const SETUP_STEP_DONE = 5;     // 完成设置
    const SETUP_STEP_LIVELY = 6;     // 生动化映射

    const SET_RULE_NO = 0;     // 未设置规则
    const SET_RULE_YES = 1;     // 已设置规则

    const SET_MAIN_NO = 0;     // 未设置主检查项
    const SET_MAIN_YES = 1;     // 已设置主检查项

    const SET_VIVIDNESS_NO = 0;     // 未设置生动化项
    const SET_VIVIDNESS_YES = 1;     // 已设置生动化项

    const PHOTO_TYPE_DEFAULT = 0;     // 普通模式
    const PHOTO_TYPE_FREE = 1;     // 随报随拍

    const IS_DELETED = 1;       //已被用户删除
    const NOT_DELETED = 0;      //未被用户删除

    const IS_NEED_QC_DEFAULT = 1;       //初始状态
    const IS_NEED_QC_YES = 1;       //需要qc
    const IS_NEED_QC_NO = 2;      //不需要qc

    const CHECK_IS_INE = 1;      //检查项目的类型是ine

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%standard}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['company_code', 'user_id'], 'required'],
            [['project_id', 'check_type_id', 'setup_step', 'standard_status',
                'status', 'created_at', 'updated_at', 'set_rule', 'pos_score', 'set_main', 'set_vividness',
                'protocol_id', 'photo_type', 'is_deleted', 'is_need_qc', 'origin_id'], 'integer'],
            [['description'], 'string'],
            [['update_time'], 'safe'],
            [['title'], 'string', 'max' => 32],
//            [['user_id'], 'string', 'max' => 32],
            [['company_code'], 'string', 'max' => 16],
            [['bu_code'], 'string', 'max' => 16],
            [['engine_rule_code'], 'string', 'max' => 64],
            [['need_qc_data'], 'string', 'max' => 1000],
            ['project_id', 'default', 'value' => 0],
            ['question_manual_ir', 'default', 'value' => ''],
            ['question_manual', 'default', 'value' => ''],
            ['scenes_ir_id', 'default', 'value' => ''],
            ['scenes', 'default', 'value' => ''],
            ['created_at', 'default', 'value' => time()],
            ['updated_at', 'default', 'value' => time()]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'project_id' => '项目id',
            'check_type_id' => '检查类型',
            'user_id' => '用户id',
            'company_code' => '厂房code',
            'bu_code' => 'bu_code',
            'protocol_id' => '协议模板id',
            'title' => '标题别名',
            'image' => '图片',
            'description' => '检查要求描述',
            'engine_rule_code' => '规则配置id',
            'question_manual_ir' => 'ir问卷',
            'question_manual' => '非ir问卷',
            'scenes_ir_id' => '场景id',
            'scenes' => '场景配置',
            'setup_step' => '设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置完成',
            'standard_status' => '启用状态0未启用，1启用，2禁用',
            'is_change' => '规则问卷修改状态，0无修改，1有修改',
            'pos_score' => '标准满分',
            'set_main' => '是否设置主检查项 0 初始状态 1 是 2 否',
            'set_vividness' => '是否设置生动化项 0 初始状态 1 是 2 否',
            'status' => '删除标识：1有效，0无效',
            'set_rule' => '是否已经设置过规则 0 未设置 1 已设置',
            'photo_type' => '拍照类别：0、普通模式，1随报随拍',
            'is_deleted' => '是否被用户删除，0:否 1:是',
            'is_need_qc' => '是否需要qc：0初始状态，1需要qc，2不需要',
            'need_qc_data' => '需要qc的生动化数据',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public function getCheckType()
    {
        return $this->hasOne(CheckType::class, ['id' => 'check_type_id']);
    }

    /**
     * 获取检查项目对应的协议信息
     * @return \yii\db\ActiveQuery
     */
    public function getProtocol()
    {
        return $this->hasOne(ProtocolTemplate::class, ['id' => 'protocol_id']);
    }

    public function getSubActivity()
    {
        return $this->hasMany(SubActivity::class, ['standard_id' => 'id']);
    }

    public function getProtocolPlan()
    {
        return $this->hasMany(Plan::class, ['standard_id' => 'id'])->onCondition(Plan::tableName() . "." . Plan::DEL_FIELD . ' = ' . Plan::DEL_STATUS_NORMAL);
    }

    public function getOutput()
    {
        return $this->hasMany(RuleOutputInfo::class, ['standard_id' => 'id']);
    }

    /**
     * 全量查询检查项目
     * @return array|ActiveRecord[]
     */
    public static function getStandardAll($where, $pageSize, $page)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        $table = self::tableName();
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        if (empty($page) || $page < 0) {
            $query = self::find();
            $list = $query
                ->leftJoin('sys_check_type c', $table . '.check_type_id=c.id')
                ->select([$table . '.created_at create_time', $table . '.id standard_id', $table . '.title', $table . '.setup_step',
                    $table . '.standard_status', 'c.title check_type_title', 'c.type', $table . '.company_code', $table . '.bu_code'])
                ->andWhere($where)
                ->orderBy($table . '.created_at DESC')
                ->asArray()
                ->all();
            $count = count($list);
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        } else {
            $page -= 1;
            $pages = new Pagination(['pageSize' => $pageSize, 'page' => $page]);
            $query = self::find();
            $list = $query
                ->leftJoin('sys_check_type c', $table . '.check_type_id=c.id')
                ->select([$table . '.created_at create_time', $table . '.id standard_id', $table . '.title', $table . '.setup_step',
                    $table . '.standard_status', 'c.title check_type_title', 'c.type', $table . '.company_code', $table . '.bu_code'])
                ->andWhere($where)
                ->orderBy($table . '.created_at DESC');
            $count = $list->count();
            $list = $list->offset($pages->offset)->limit($pages->limit)
                ->asArray()
                ->all();
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        }
    }

    /**
     *查询单条检查项目详情
     * @param $where
     * @return array|ActiveRecord|null
     */
    public static function getStandardDetail($where)
    {
//        return $data = self::find()
//            ->select(['created_at create_time', 'id standard_id', 'title',
//                'check_type_id', 'image', 'question_manual',
//                'question_manual_ir', 'scenes_ir_id', 'setup_step', 'standard_status', 'description', 'engine_rule_code', 'scenes'])
//            ->where($where)
//            ->asArray()
//            ->one();
        return self::findOneArray($where, ['created_at create_time', 'id standard_id', 'title',
            'check_type_id', 'image', 'question_manual',
            'question_manual_ir', 'scenes_ir_id', 'setup_step', 'standard_status', 'description', 'engine_rule_code', 'scenes'], true);
    }

    /**
     * 查询标准是否有改变
     * @param $id
     * @return Replan|array|null|ActiveRecord
     */
    public static function getIsChange($id)
    {
        return $data = self::find()
            ->select(['is_change', 'engine_rule_code'])
            ->where(['id' => $id])
            ->asArray()
            ->one();
    }

    /**
     * 修改检查项目的启用状态
     * @param $where
     * @param $standard_status
     * @param $standard_id
     * @param $params
     * @return array
     * @throws \yii\db\Exception
     */
    public static function doStandardSwitch($where, $standard_status, $standard_id, $params)
    {
        $model = self::findOne($where, true);
        $user = Yii::$app->params['user_info'];
        if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
            return [false, '并非本区隔创建的检查项目，不允许开启/禁用'];
        }
        if ($model) {
            if ($model->setup_step != 5) {
                return [false, '檢查項目沒有设计完成，不允许开启/禁用'];
            }
            $model->standard_status = $standard_status;
            if ($model->save()) {
                //如果是禁用检查项目，那么要把当时的详情保存在快照里，以便对外接口调用
                //并且要把输出项与子活动都保存在快照表里
                if ($standard_status == self::STATUS_DISABLED) {
                    $survey_standard = SurveyStandard::findOne(['standard_id' => $standard_id, 'is_standard_disable' => SurveyStandard::IS_STANDARD_DISABLE_YES])
                        ?: new SurveyStandard();
                    $data = $model->attributes;
                    $data['standard_id'] = $data['id'];
                    $data['is_standard_disable'] = SurveyStandard::IS_STANDARD_DISABLE_YES;
                    unset($data['id']);
                    $survey_standard->load($data, '');
                    if (!$survey_standard->save()) {
                        return [false, $survey_standard->errors];
                    }

                    $standard_sub = SubActivity::findAllArray(['standard_id' => $standard_id]);
                    foreach ($standard_sub as &$v) {
                        $v['is_standard_disable'] = SurveySubActivity::IS_STANDARD_DISABLE_YES;
                        $v['sub_activity_id'] = $v['id'];
                        unset($v['id']);
                    }
                    $re = SurveySubActivity::saveSubSnapshot($standard_sub, $standard_id);
                    if (!$re[0]) {
                        return [false, $re[1]];
                    }

                    $standard_output = RuleOutputInfo::findAllArray(['standard_id' => $standard_id]);
                    foreach ($standard_output as &$v) {
                        $v['rule_output_info_id'] = $v['id'];
                        unset($v['id']);
                    }
                    $re = SnapshotRuleOutputInfo::saveOutputSnapshot($standard_output, $standard_id);
                    if (!$re[0]) {
                        return [false, $re[1]];
                    }
                }
//                else {
//                    //检查项目启用时要通知规则引擎
//                    $add_url = Yii::$app->params['url']['rule_host'] . 'api/engine/release-rule';
//                    $token[] = 'token:' . $params['token'];
//                    \Helper::curlQueryLog($add_url, ['rule_code' => $model->engine_rule_code], true, 300, $token);
//                }
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'standard_id不存在'];
        }
    }


    /**
     * 删除单个检查项目
     * @param $where
     * @param $is_deleted
     * @return array
     */
    public static function doStandardDelete($where, $is_deleted)
    {
        $model = self::findOne($where, true);
        $user = Yii::$app->params['user_info'];
        if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
            return [false, '并非本区隔创建的检查项目，不允许删除'];
        }
        if ($model) {
            $model->is_deleted = $is_deleted;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'standard_id不存在'];
        }
    }

    /**
     * 接收引擎规则配置后的输出项（废弃）
     * @param $where
     * @param $rule_output_field
     * @return array
     */
//    public static function saveEngineOutput($where, $rule_output_field)
//    {
//        $model = self::find()
//            ->where($where)
//            ->one();
//        if ($model) {
//            if ($model->standard_status == 0) {
//                //未启用前的标准
//                $rule_output_field_arr = json_decode($rule_output_field, true);
//                $output = [];
//                foreach ($rule_output_field_arr as $k => $v) {
//                    $output[$k + 1] = ['name' => $v, 'isNew' => false];
//                }
//                $rule_output_info['rule_output_field'] = $output;
//                $rule_output_info['delete'] = [];
//                $model->rule_output_info = json_encode($rule_output_info);
//                $model->rule_output_field = $rule_output_field;
//                $model->setup_step = 3;
//            } else {
//                //启用后修改的标准
//                $old_rule_output_field = json_decode($model->rule_output_field, true);
//                $rule_output_info = json_decode($model->rule_output_info, true);
//                $new_rule_output_field = json_decode($rule_output_field, true);
//                $new_add = array_diff($new_rule_output_field, $old_rule_output_field);
//                foreach ($rule_output_info['rule_output_field'] as &$v) {
//                    $v['isNew'] = false;
//                }
//                if (!empty($new_add)) {
//                    foreach ($new_add as $v) {
//                        $rule_output_info['rule_output_field'][] = ['name' => $v, 'isNew' => true];
//                    }
//                }
//                $new_delete = array_diff($old_rule_output_field, $new_rule_output_field);
//                $delete = $rule_output_info['delete'];
//                foreach ($delete as &$v) {
//                    $v['isNew'] = false;
//                }
//                if (!empty($new_delete)) {
//                    foreach ($new_delete as $v) {
//                        foreach ($rule_output_info['rule_output_field'] as $key => $val) {
//                            if ($v == $val['name']) {
//                                $delete[$key] = ['name' => $val, 'isNew' => true];
//                                unset($rule_output_info['rule_output_field'][$key]);
//                            }
//                        }
//                    }
//                }
//                $rule_output_info['delete'] = $delete;
//                $model->rule_output_info = json_encode($rule_output_info);
//                $model->rule_output_field = $rule_output_field;
//                $model->setup_step = 3;
//            }
//            if ($model->save()) {
//                return [true, $model->attributes['id']];
//            } else {
//                return [false, $model->errors];
//            }
//        } else {
//            return [false, 'standard_id不存在'];
//        }
//    }

    /**
     * 新建检查项目
     * @param $data
     * @return array
     */
    public static function addStandard($data)
    {
        $protocol_id = isset($data['protocol_id']) ? $data['protocol_id'] : 0;
        $photo_type = 0;
        if ($protocol_id) {
            $where = [
                'and',
                ['s.company_code' => $data['company_code']],
                ['s.bu_code' => $data['bu_code']],
                ['p.contract_code' => $data['protocol_code']]
            ];
            $check = self::checkStandard($where);
            if ($check) {
                return [false, '同一个厂房同一个协议不能创建两个检查项目'];
            }
            if ($data['company_code'] == 3004) {
                return [false, '央服不能创建协议类项目'];
            }

            $protocol = ProtocolTemplate::findOneArray(['id' => $protocol_id]);
            // 签约日期和执行日期有时间交互，即为随报随拍
            if (($protocol['excute_from_date'] <= $protocol['sign_from_date'] and $protocol['sign_from_date'] <= $protocol['excute_to_date'])
                || ($protocol['excute_from_date'] <= $protocol['sign_to_date'] and $protocol['sign_to_date'] <= $protocol['excute_to_date'])) {
                $photo_type = self::PHOTO_TYPE_FREE;
            } else {
                $photo_type = self::PHOTO_TYPE_DEFAULT;
            }
        }
        $model = new self();
        $model->title = $data['title'];
        $model->check_type_id = $data['check_type_id'];
        $model->description = isset($data['description']) ? $data['description'] : '';
        $model->company_code = $data['company_code'];
        $model->bu_code = $data['bu_code'];
        $model->user_id = $data['user_id'];
        $model->protocol_id = $protocol_id;
        $model->photo_type = $photo_type;
//        $model->image = isset($data['image']) ? $data['image'] : '';
        $model->setup_step = self::SETUP_STEP_BUILD;
        if ($model->save()) {
            return [true, ['id' => $model->attributes['id']]];
        } else {
            return [false, $model->errors];
        }
    }

    /**
     * 设置检查项目的问卷与拍照场景
     * @param $where
     * @param $data
     * @return array
     */
    public static function editStandard($where, $data, $type = 2)
    {
        $model = self::findOne($where, true);
        $user = Yii::$app->params['user_info'];
        //tpye为1的话是修改第一步，为2修改第二步
        if ($model) {
            if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
                return [false, '并非本区隔创建的检查项目，不允许修改'];
            }
            if ($model->standard_status == 1) {
                return [false, '检查项目已启用，无法改变步骤'];
            }
            if ($type == 2) {
                $scenes = $data['scenes'];
                if ($data['is_change'] == self::IS_CHANGE) {
                    $is_change = $data['is_change'];
                    $model->is_change = $is_change;
                }
                if ($model->set_rule == self::SET_RULE_NO) {
                    $model->setup_step = self::SETUP_STEP_PHOTO;
                    $model->is_change = self::NOT_CHANGE;
                }
                if ($model->is_change == self::IS_CHANGE) {
                    $model->setup_step = self::SETUP_STEP_PHOTO;
                    $model->set_main = self::SET_MAIN_NO;
                    $model->set_vividness = self::SET_VIVIDNESS_NO;
                }
                $scenes = json_decode($scenes, true);
                foreach ($scenes as $k => $v) {
                    $code_id = Scene::findAllArray(['in', 'scene_code', $v['scenes_code']], ['scene_code']);
                    $code_array = array_column($code_id, 'scene_code');
                    if (isset($v['scenes_type_id'][0])) {
                        if ($v['scenes_type_id'][0] !== SceneType::SCENE_TYPE_ALL) {
                            $type_code = Scene::findAllArray(['in', 'scene_type', $v['scenes_type_id']], ['scene_code']);
                        } else {
                            $type_code = Scene::findAllArray([], ['scene_code']);
                        }
                        $type_code = array_column($type_code, 'scene_code');
                        $code_array = array_merge($code_array, $type_code);
                    }
                    $code_array = array_unique($code_array);
                    //将所有的scene_code再复制回scenes里
                    $scenes[$k]['scenes_code'] = $code_array;
                }
                $scenes = json_encode($scenes);
                $model->scenes = $scenes;
                $model->is_need_qc = isset($data['is_need_qc']) ? $data['is_need_qc'] : self::IS_NEED_QC_NO;
                $model->need_qc_data = isset($data['need_qc_data']) ? $data['need_qc_data'] : '';
                if ($model->save()) {
                    return [true, ['id' => $model->attributes['id']], $model->is_change];
                } else {
                    return [false, $model->errors];
                }
//                RuleOutputInfo::deleteAllOutput($data['standard_id']);
            } else {
                $protocol_change = false;
                if (isset($data['protocol_id']) && isset($data['protocol_code'])) {
                    $where = [
                        'and',
                        ['!=', 's.id', $model->id],
                        ['s.company_code' => $data['company_code']],
                        ['s.bu_code' => $data['bu_code']],
                        ['p.contract_code' => $data['protocol_code']],
                        ['p.company_code' => $data['company_code']]
                    ];
                    $check = self::checkStandard($where);
                    if ($check && $check['id'] != $data['standard_id']) {
                        return [false, '同一个厂房同一个协议不能创建两个检查项目'];
                    }
                    $protocol = ProtocolTemplate::findOneArray(['id' => $model->protocol_id]);
                    $protocol_change = (!empty($protocol) && $data['protocol_code'] != $protocol['contract_code']) ? true : false;
                    if ($protocol_change) {
                        $model->setup_step = self::SETUP_STEP_BUILD;
                        $model->protocol_id = $data['protocol_id'];
                    }
                    // 签约日期和执行日期有时间交互，即为随报随拍
                    if (($protocol['excute_from_date'] <= $protocol['sign_from_date'] and $protocol['sign_from_date'] <= $protocol['excute_to_date'])
                        || ($protocol['excute_from_date'] <= $protocol['sign_to_date'] and $protocol['sign_to_date'] <= $protocol['excute_to_date'])) {
                        $photo_type = self::PHOTO_TYPE_FREE;
                    } else {
                        $photo_type = self::PHOTO_TYPE_DEFAULT;
                    }
                    if ($photo_type != $model->photo_type) {
                        $model->photo_type = $photo_type;
                        Plan::updateAll(['plan_status' => Plan::PLAN_STATUS_DISABLE, 'editable' => Plan::EDITABLE_NO], ['standard_id' => $model->id]);
                        $plan_id = array_column(Plan::findAllArray(['standard_id' => $model->id]), 'id');
                    }
                }
                $model->title = $data['title'];
                $model->check_type_id = $data['check_type_id'];
                $model->description = isset($data['description']) ? $data['description'] : $model->description;
//                $model->image = isset($data['image']) ? $data['image'] : $model->image;
                $plan_id = isset($plan_id) ? $plan_id : [];
                if ($model->save()) {
                    return [true, ['id' => $model->attributes['id'], 'plan_id' => $plan_id], $protocol_change];
                } else {
                    return [false, $model->errors];
                }
            }
        } else {
            return [false, 'standard_id不存在或者无权限修改'];
        }
    }

    /**
     * 修改检查项目的执行步骤
     * @param $where
     * @param $setup_step
     * @return array
     */
    public static function doReviseSetupStep($where, $setup_step)
    {
        $model = self::findOne($where, true);
        $user = Yii::$app->params['user_info'];
        if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
            return [false, '并非本区隔创建的检查项目，不允许修改执行步骤'];
        }
        if ($model) {
            if (!in_array($model->setup_step, [3, 4, 5, 6])) {
                return [false, '请先进行规则设置'];
            }
            if ($model->standard_status == 1) {
                return [false, '检查项目已启用，无法改变步骤'];
            }
            $model->setup_step = $setup_step;
            if ($model->save()) {
                return [true, ['id' => $model->attributes['id']]];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'standard_id不存在'];
        }
    }

    /**
     * 获得规则配置id
     * @param $id
     * @return array|null|ActiveRecord
     */
    public static function getEngineRuleCode($id)
    {
        return $data = self::find()
            ->select(['engine_rule_code'])
            ->where(['id' => $id])
            ->asArray()
            ->one();
    }

    /**
     * 保存规则配置id
     * @param $id
     * @param $rule_code
     * @return array
     */
    public static function saveRuleCode($id, $rule_code)
    {
        $model = self::findOne(['id' => $id], true);
        $user = Yii::$app->params['user_info'];
        if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
            return [false, '并非本区隔创建的检查项目，不允许保存规则配置'];
        }
        if ($model) {
            $model->engine_rule_code = $rule_code;
            if ($model->save()) {
                return [true];
            } else {
                return [false, $model->getErrors()];
            }
        } else {
            return [false, '规则id不存在'];
        }
    }

    /**
     * 获取检查项目的问卷的场景
     * @return array
     */
    public function getSceneAndQuestion()
    {
        $scenes = json_decode($this->scenes, true);
        // 多个场景
        $result = [];
        if (empty($scenes)) {
            return $result;
        }
        $question_manual_ir = [];
        $question_manual = [];
        foreach ($scenes as $scene) {
            $question_manual_ir = array_merge($question_manual_ir, $scene['question_manual_ir']);
            $question_manual = array_merge($question_manual, $scene['question_manual']);
        }

        $question_manual_ir = array_column($question_manual_ir, 'id');
        $question_manual = array_column($question_manual, 'id');
        $scene_result = [];
        $question_list_ids = array_merge($question_manual, $question_manual_ir);
        // 将场景和问卷的场景id合并去重
        if (!empty($question_list_ids)) {
//            $question_list = Question::findAllArray(['id' => $question_list_ids], ['id', 'scene_type_id']);
            $question_list = Question::find()->where(['id' => $question_list_ids])->select( ['id', 'scene_type_id'])->asArray()->all();
            $scene_list_id = array_column($question_list, 'scene_type_id');
            $scene_ids = array_unique($scene_list_id);
//            $scene_ids = array_unique(array_merge($scene_ids, $scene_list_id));
        }

        // 场景
        if (!empty($scene_ids)) {
            $scene_result = Scene::findAllArray(['id' => $scene_ids], ['id', 'scene_code', 'scene_code_name']);
        }
        return $scene_result;
    }

    /**
     * 获取检查项目的场景分组
     * @return array
     */
    public function getSceneTypeList()
    {
        $scenes = json_decode($this->scenes, true);
        // 多个场景
        $result = [];
        if (empty($scenes)) {
            return $result;
        }
        foreach ($scenes as $index => $scene) {
            $scene_type_id = $scene['scenes_type_id'];
            $scenes_code = $scene['scenes_code'];
            $sub_activity_id = $scene['sub_activity_id'];
            $scene_type = SceneType::findAllArray(['id' => $scene_type_id]);
            $scene_ir = Scene::findAllArray(['scene_code' => $scenes_code]);
            $scene_ir = ArrayHelper::index($scene_ir, null, 'scene_type');
            $labelResult = [];
            $labelArr = [];
            if ($scene_type_id == [SceneType::SCENE_TYPE_ALL]) {
                $labelResult[] = SceneType::SCENE_TYPE_ALL_LABEL;
            } else {
                if (!empty($scene_type)) {
                    foreach ($scene_type as $item) {
                        $labelArr[] = $item['name'];
                    }
                    $labelResult[] = implode('；', $labelArr);
                }

                if (!empty($scene_ir)) {
                    $labelGroupArr = [];
                    foreach ($scene_ir as $tmp => $item) {
                        $labelArr = [];
                        foreach ($item as $one) {
                            $labelArr[] = $one['scene_code_name'];
                        }
                        $labelGroupArr[] = implode('、', $labelArr);
                    }
                    $labelResult[] = implode('；', $labelGroupArr);
                }
            }
            $result[] = ['sub_activity_id' => $sub_activity_id, 'index' => $index, 'label' => implode('；', $labelResult), 'scene_type_id' => $scene_type_id, 'scene_code' => $scenes_code];
        }
        return $result;
    }

    /**
     * 获取问卷和拍照场景
     * @param $data
     * @param $protocols
     * @return array
     */
    public static function getQuestionAndScenes($data, $protocols = [])
    {
        $result = [];
        $question_manual_ir = [];
        $question_manual = [];
        $scenes = [];
        $scenes_type_id = [];
        $question_property = [];
        foreach ($data as $standard_id => $datum) {
            $is_ine = $datum['check_type_id'] == CheckType::INE_AGREEMENTS['check_type_id'] ?: false;
            $activationIds = [];
            if (isset($protocols[$datum['protocol_id']])) {
                $tmpActivation = json_decode($protocols[$datum['protocol_id']]['activation_list'], true);
                $activationIds = array_column($tmpActivation, 'activationID');
            }
            $scenes_tmp = json_decode($datum['scenes'], true);
            // 多个场景
            if (!empty($scenes_tmp)) {
                foreach ($scenes_tmp as $scene) {
                    if (empty($activationIds) || in_array($scene['activationID'], $activationIds)) {
                        $question_manual_ir = array_merge($question_manual_ir, $scene['question_manual_ir']);
                        $question_manual = array_merge($question_manual, $scene['question_manual']);
                        $scenes = array_merge($scenes, $scene['scenes_code']);
                        $scenes_type_id = array_merge($scenes_type_id, [['scenes_type_id' => $scene['scenes_type_id'], 'is_ine' => $is_ine]]);

                        // 设置问卷属性
                        foreach ($question_manual as $qTmp) {
                            $question_property[$qTmp['id']] = ['id' => $qTmp['id'], 'is_required' => $qTmp['is_required'] ?? '0', 'must_take_photo' => $qTmp['must_take_pictures'] ?? '0'];
                        }
                    }
                }
            }
            //如果是ine检查项目，要删除需要删除的场景，目前只有一个是招牌
            if ($is_ine) {
                $scenes = array_unique($scenes);
                if ($key = array_search(Scene::INE_NEED_DELETE[0], $scenes)) {
                    unset($scenes[$key]);
                }
            }
        }

        $result['question_manual_ir'] = array_column($question_manual_ir, 'id');
        $result['question_manual'] = array_column($question_manual, 'id');
        $result['questions'] = array_merge($question_manual_ir, $question_manual);
        $result['scenes_type_id'] = $scenes_type_id;
        $result['scenes_code'] = array_unique($scenes);
        // 问卷的属性
        $result['question_property'] = $question_property;

        return $result;
    }

    /**
     * 获取检查项目下拉列表
     * @param $searchForm
     * @param $select
     * @return array
     */
    public static function getWithCheckType($searchForm, $select)
    {
        $query = Standard::find()->asArray()->select($select)
            ->innerJoin(CheckType::tableName(), 'check_type_id = ' . CheckType::tableName() . '.id');

        if (isset($searchForm['standard_id'])) {
            $query->andFilterWhere(['or', ['=', 'standard_status', $searchForm['standard_status']], ['=', new Expression(Standard::tableName() . '.id'), $searchForm['standard_id']]]);
        } else {
            $query->andFilterWhere(['standard_status' => $searchForm['standard_status']]);
        }
        if (isset($searchForm['check_type_id']) && $searchForm['check_type_id'] != '')
            $query->andFilterWhere(['check_type_id' => $searchForm['check_type_id']]);

        if (isset($searchForm['standard_status']) && $searchForm['standard_status'] == Standard::STATUS_AVAILABLE)
            $query->andWhere(['<>', 'check_type_id', CheckType::LONG_AGREEMENTS['check_type_id']]);
        if (isset($searchForm['is_deleted'])) {
            $query->andFilterWhere(['is_deleted' => $searchForm['is_deleted']]);
        }
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(static::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        //排序
        $order_by = [Standard::tableName() . '.created_at' => SORT_DESC, Standard::tableName() . '.id' => SORT_DESC];

        return $query->orderBy($order_by)->all();
    }

    /**
     * 按走访号、执行工具搜索检查项目
     * @param $searchForm
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function searchStandard($searchForm)
    {
        $select = [
            new Expression(Standard::tableName() . '.id'),
            new Expression(Standard::tableName() . '.title')
        ];
        $query = Standard::find()->asArray()->select($select);
        if (isset($searchForm['tool_id']) && $searchForm['tool_id'] != '') {
            if ($searchForm['tool_id'] != Tools::TOOL_ID_SEA_LEADER) {
                $query->innerJoin(Plan::tableName(), Plan::tableName() . '.standard_id = ' . Standard::tableName() . '.id')
                    ->andWhere(['tool_id' => $searchForm['tool_id']]);
            } else {
                // 高管巡店搜索逻辑不一样
                $query->andWhere(['check_type_id' => CheckType::INE['check_type_id']]);
            }
        }

        if (isset($searchForm['survey_code']) && $searchForm['survey_code'] != '')
            $query->innerJoin(EngineResult::tableName(), EngineResult::tableName() . '.standard_id = ' . Standard::tableName() . '.id')
                ->andWhere([EngineResult::tableName() . '.survey_code' => $searchForm['survey_code']]);

        if (isset($searchForm['is_need_qc'])) {
            $query->andFilterWhere([Standard::tableName() . '.is_need_qc' => $searchForm['is_need_qc']]);
        }

        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(static::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        $query->groupBy(Standard::tableName() . '.id');
        return $query->orderby([Standard::tableName() . '.created_at' => SORT_DESC])->all();
    }

    /**
     * 按走访号搜索执行工具
     * @param $searchForm
     * @return array
     */
    public static function searchTool($searchForm)
    {
        $select = [
            new Expression(Tools::tableName() . '.id'),
            new Expression(Tools::tableName() . '.name')
        ];
        $query = Tools::find()->asArray()->select($select)->groupBy(Tools::tableName() . '.id');
        if (isset($searchForm['survey_code']) && $searchForm['survey_code'] != '') {
            $query->innerJoin(Survey::tableName(), 'tool_id = ' . Tools::tableName() . '.id')
                ->andWhere(['survey_code' => $searchForm['survey_code']]);
        }
        return ['list' => $query->all()];
    }

    /**
     * 每个下一步都要验证是否协议数据有变动
     * @param $params
     * @param bool $change
     * @return array
     */
    public static function checkProtocol($params, $change = false)
    {
        $standard_model = self::findOne(['id' => $params['standard_id']], true);
        if ($standard_model) {
            if (empty($standard_model->protocol_id)) {
                $standard_model = $standard_model->attributes;
                return [false, '', $standard_model];
            }
            $model = ProtocolTemplate::findOne(['id' => $standard_model->protocol_id]);
            if ($standard_model->standard_status == 1) {
                $standard_model = $standard_model->attributes;
                $standard_model['contract_name'] = $model->contract_name;
                $standard_model['contract_code'] = $model->contract_code;
                return [false, '检查项目已启用，无法改变步骤', $standard_model];
            }
            //如果只是查看或者检查项目已启用点设置不再验证后面的
            if (isset($params['is_look']) && $params['is_look']) {
                $standard_model = $standard_model->attributes;
                $standard_model['contract_name'] = $model->contract_name;
                $standard_model['contract_code'] = $model->contract_code;
                return [true, $change, $standard_model];
            }
            //curl 调用SamrtMEDI系统，获取协议信息
            $request_url = Yii::$app->params['zft_url'] . '/api/getContractList';
            $request_params = [
                'companyCode' => $standard_model->company_code,
                'contractCode' => isset($params['protocol_code']) ? $params['protocol_code'] : $model->contract_code
            ];
            $request_url = $request_url . '?' . http_build_query($request_params);
//            $request_header = [
//                'Content-type: text/json',
//                'x-access-token: ABC'
//            ];
            $request_header = Protocol::getZftToken(time());
            $request_header[] = 'Content-type: text/json';
            $curl_response = \Helper::curlQueryLog($request_url, [], false, 300, $request_header);
            $convert_contract = [];
            if (!empty($curl_response)) {
                $curl_response_array = $curl_response;
                if (!empty($curl_response_array['resultCode']) && $curl_response_array['resultCode'] == 200
                    && !empty($curl_response_array['contractList']) && isset($curl_response_array['contractList'][0])) {
                    $contract = $curl_response_array['contractList'][0];
                    //驼峰转小写加下划线
                    foreach ($contract as $key => $val) {
                        $convert_contract[BaseInflector::camel2id($key, '_')] = $val;
                    }
                    if (isset($contract['status'])) {
                        //状态字段处理
                        $convert_contract['protocol_status'] = $contract['status'];
                        unset($convert_contract['status']);
                    }
                    $activation_list = $convert_contract['activation_list'];
                    //json 格式字段处理
                    if (isset($convert_contract['activation_list']) && isset($convert_contract['excute_cycle_list'])) {
                        $convert_contract['activation_list'] = json_encode($convert_contract['activation_list']);
                        $convert_contract['excute_cycle_list'] = json_encode($convert_contract['excute_cycle_list']);
                    }
                    //如果协议执行时间有变的话，也算是改变，需要回到第一步以进行性质变化判断
                    $change = ($model->excute_from_date != $convert_contract['excute_from_date'] ||
                        $model->excute_to_date != $convert_contract['excute_to_date'] ||
                        $model->activation_list != $convert_contract['activation_list'] ||
                        $model->excute_cycle_list != $convert_contract['excute_cycle_list']) ? true : $change;
                    //保存协议详情
                    $model->load($convert_contract, '');
                    $model->company_code = $standard_model->company_code;
                    $model->save();

                    //验证standard表里的scenes字段
                    $scenes = json_decode($standard_model->scenes, true);
                    //scenes字段为空说明是第一次，那么直接用协议信息代入。否则要对比。
                    if (!$scenes) {
                        $standard_model->scenes = json_encode($activation_list);
                        $standard_model->setup_step = Standard::SETUP_STEP_BUILD;
                        $standard_model->save();
                    } else {
                        foreach ($scenes as $k => $v) {
                            $is_repeat = false;
                            foreach ($activation_list as $item) {
                                if ($v['activationID'] == $item['activationID']) {
                                    $is_repeat = true;
                                    if ($v['activationName'] != $item['activationName'] || $v['isStandard'] != $item['isStandard']) {
                                        $scenes[$k]['activationName'] = $item['activationName'];
                                        $scenes[$k]['isStandard'] = $item['isStandard'];
                                        $change = true;
                                    }
                                    if ($v['activationDesc'] != $item['activationDesc']) {
                                        $scenes[$k]['activationDesc'] = $item['activationDesc'];
                                    }
                                    break;
                                }
                            }
                            //老的生动化与新的对比没有匹配的就删除
                            if (!$is_repeat) {
                                unset($scenes[$k]);
                            }
                        }
                        foreach ($activation_list as $v) {
                            $is_repeat = false;
                            foreach ($scenes as $item) {
                                if ($v['activationID'] == $item['activationID']) {
                                    $is_repeat = true;
                                    break;
                                }
                            }
                            //新的生动化没有重复的就添加
                            if (!$is_repeat) {
                                $change = true;
                                $scenes[] = $v;
                            }
                        }

                        //如果协议有改变就存入新的scenes
                        if ($change) {
                            $standard_model->scenes = json_encode($scenes);
                            $standard_model->setup_step = Standard::SETUP_STEP_DEFAULT;
                            $standard_model->save();
                        }
                    }
                }
                $standard_model = $standard_model->attributes;
                if ($convert_contract) {
                    $standard_model['contract_name'] = $convert_contract['contract_name'];
                    $standard_model['contract_code'] = $convert_contract['contract_code'];
                    //zft数据有起始时间和终止时间才更新plan
                    if ($convert_contract['excute_from_date'] && $convert_contract['excute_to_date']) {
                        PlanService::syncPlanTime($params['standard_id'], $convert_contract['excute_from_date'], $convert_contract['excute_to_date'], json_decode($convert_contract['excute_cycle_list'], true));
                    }
                    return [true, $change, $standard_model];
                } else {
                    return [false, '', $standard_model];
                }
            } else {
                $standard_model = $standard_model->attributes;
                return [false, '', $standard_model];
            }
        } else {
            return [false];
        }
    }

    /**
     * 查询规则引擎rule_code
     * @param $where
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function findRuleCode($where)
    {
        return self::find()->alias('s')
            ->leftJoin('sys_plan p', 's.id = p.standard_id')
            ->select(['s.engine_rule_code', 'p.standard_id', 's.company_code', 's.id', 's.protocol_id', 's.check_type_id', 'p.id plan_id', 'p.rectification_model'])
            ->where($where)
            ->indexBy('id')
            ->asArray()
            ->all();
    }

    /**
     * 查询检查项目是否是协议类
     * @param $where
     * @return Replan|array|null|ActiveRecord
     */
    public static function getStandardCheckType($where)
    {
        return self::find()->alias('s')
            ->leftJoin('sys_check_type c', 's.check_type_id = c.id')
            ->select(['c.type', 'c.id check_type_id', 's.protocol_id', 's.company_code'])
            ->where($where)
            ->asArray()
            ->one();
    }

    /**
     * 输出项有变化就清空生动化与输出项的绑定内容
     * @param $where
     * @return bool
     */
    public static function clearOutput($where)
    {
        $model = self::findOne($where);
        if ($model) {
            $scenes = json_decode($model->scenes, true);
            foreach ($scenes as &$v) {
                $v['outputList'] = [];
            }
            $model->scenes = json_encode($scenes);
            if ($model->save()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 验证协议是否重复使用
     * @param $where
     * @return Replan|array|null|ActiveRecord
     */
    public static function checkStandard($where)
    {
        return self::find()->alias('s')
            ->join('join', 'sys_protocol_template p', 's.protocol_id = p.id')
            ->select(['s.*'])
            ->andWhere($where)
            ->asArray()
            ->one();
    }

    /**
     * 返回检查项目的启用状态
     * @param $standard_id
     * @return array
     */
    public static function isStandardStart($standard_id)
    {
        $model = self::findOne(['id' => $standard_id], false);
        if ($model) {
            if ($model->standard_status == 1) {
                return [false, '检查项目已启用，无法改变步骤'];
            }
        } else {
            return [false, 'standard_id不存在'];
        }
        return [true, ''];
    }

    /**
     * 根据ID获取检查项目名称
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午12:00
     *
     * @param $standard_ids
     *
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getTitleByIds($standard_ids)
    {
        return self::find()->select(['id', 'title', 'need_qc_data'])->where(['id' => $standard_ids])->indexBy('id')->asArray()->all();
    }

    /**
     * 获取检查类型名称
     *
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午1:56
     *
     * @param $standard_ids
     *
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getCheckTypeNameByIds($standard_ids)
    {
        return self::find()
            ->select(['s.id', 't.title'])
            ->from('sys_standard s')
            ->leftJoin('sys_check_type t', 's.check_type_id=t.id')
            ->where(['s.id' => $standard_ids])
            ->indexBy('id')
            ->asArray()
            ->all();
    }

    /**
     * 获取问卷列表
     * @param $standardIds
     */
    public static function getStandardQuestion($standardIds)
    {
        $subActivity = SubActivity::find()->where(['standard_id' => $standardIds])
            ->select(['question_manual_ir', 'question_manual', 'standard_id'])->asArray()->all();
        $questions = [];
        foreach ($subActivity as $activity) {
            if (!isset($questions['standard_id'])) {
                $questions[$activity['standard_id']] = [];
            }
            $questions[$activity['standard_id']] = array_merge($questions[$activity['standard_id']], json_decode($activity['question_manual_ir'], true));
            $questions[$activity['standard_id']] = array_merge($questions[$activity['standard_id']], json_decode($activity['question_manual'], true));
        }
        return $questions;
    }

}
