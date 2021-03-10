<?php

namespace api\models;


use api\models\share\ChannelMain;
use api\models\share\ChannelSub;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\SceneType;
use common\libs\file_log\LOG;
use yii\data\Pagination;
use yii\db\Expression;
use common\libs\engine\Format;
use Yii;
use yii\db\Query;

/**
 * This is the model class for table "{{%engine_result}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property int $survey_id 走访号id
 * @property string $survey_code 走访号
 * @property int $plan_id 检查计划id
 * @property int $standard_id 规则id
 * @property int $replan_id 规则重跑id
 * @property int $statistical_id 统计项目id
 * @property string $merge_answer 合并后的问卷答案
 * @property string $result 引擎计算结果
 * @property string $result_time 引擎结果存入时间
 * @property int $result_status 返回结果状态 0初始状态，1识别中，2完成
 * @property int $is_rectify 是否整改拍照：0、非整改，1是整改
 * @property int $p_survey_code 父走访号
 * @property int $send_zft_status ZFT推送状态：0、默认未推送，1推送中，2推送成功，3推送失败
 * @property int $send_zft_fail ZFT推送失败原因
 * @property int $send_cp_status 推送cp状态
 * @property int $qc_result 引擎计算结果qc后的结果
 * @property int $qc_status qc状态：0初始状态，1qc已完成，2放弃qc
 * @property int $is_need_qc 是否需要qc：0初始状态 1需要qc 2不需要qc
 * @property int $ine_total_points ine总分
 * @property int $ine_config_timestamp_id ine配置时间戳
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 * @property string $review_reason QC修改原因
 */
class EngineResult extends baseModel
{
    /**
     * 返回结果状态
     */
    const RESULT_STATUS_DEFAULT = 0;
    const RESULT_STATUS_ING = 1;
    const RESULT_STATUS_DONE = 2;

    /**
     * 是否整改拍照
     */
    const IS_RECTIFY_NO = 0;
    const IS_RECTIFY_YES = 1;
    const IS_RECTIFY_EXPLAIN = [
        self::IS_RECTIFY_NO => '否',
        self::IS_RECTIFY_YES => '是'
    ];

    /**
     * 发送智付通状态
     */
    const SEND_ZFT_STATUS_DEFAULT = 0;
    const SEND_ZFT_STATUS_ING = 1;
    const SEND_ZFT_STATUS_SUCCESS = 2;
    const SEND_ZFT_STATUS_FAIL = 3;

    /**
     * qc状态：0初始状态，1qc已完成, 2忽略
     */
    const ENGINE_RESULT_QC_DEFAULT = 0;
    const ENGINE_RESULT_QC_DOWN = 1;
    const ENGINE_RESULT_QC_IGNORE = 2;

    const ENGINE_PASS_STATUS_NO = 0;
    const ENGINE_PASS_STATUS_YES = 1;

    const IS_NEED_QC_DEFAULT = 0;
    const IS_NEED_QC_YES = 1;
    const IS_NEED_QC_NO = 2;

    /**
     * 推送cp状态：0默认未推送，1正常推送成功，2正常推送失败，3正常与qc推送都成功，4正常与qc推送都失败，5正常成功qc失败，6正常失败qc成功
     */
    const SEND_CP_STATUS_DEFAULT = 0;
    const SEND_CP_STATUS_ONCE_YES = 1;
    const SEND_CP_STATUS_ONCE_NO = 2;
    const SEND_CP_STATUS_ALL_YES = 3;
    const SEND_CP_STATUS_ALL_NO = 4;
    const SEND_CP_STATUS_SECOND_NO = 5;
    const SEND_CP_STATUS_SECOND_YES = 6;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%engine_result}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'survey_id', 'plan_id', 'standard_id', 'result_status', 'status', 'created_at', 'updated_at', 'replan_id', 'statistical_id', 'is_rectify', 'qc_status', 'is_need_qc', 'send_zft_status', 'send_cp_status', 'ine_config_timestamp_id'], 'integer'],
            [['merge_answer', 'result', 'p_survey_code', 'qc_result'], 'string'],
            [['result_time', 'update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['review_reason', 'send_zft_fail'], 'string', 'max' => 200],
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
            'survey_id' => '走访号id',
            'survey_code' => '走访号',
            'plan_id' => '检查计划id',
            'standard_id' => '规则id',
            'replan_id' => '规则重跑id',
            'statistical_id' => '统计项目id',
            'merge_answer' => '合并后的问卷答案',
            'result' => '引擎计算结果',
            'result_time' => '引擎结果存入时间',
            'result_status' => '返回状态',
            'is_rectify' => '是否整改拍照：0、非整改，1是整改',
            'p_survey_code' => '父走访号',
            'ine_total_points' => 'ine总分',
            'ine_config_timestamp_id' => 'ine配置时间戳',
            'status' => '删除标识',
            'send_zft_status' => 'ZFT推送状态：0、默认未推送，1推送中，2推送成功，3推送失败',
            'send_zft_fail' => 'ZFT推送失败原因',
            'send_cp_status' => '推送cp状态',
            'qc_result' => '引擎计算结果qc后的结果',
            'qc_status' => 'qc状态：0初始状态，1qc已完成，2放弃qc',
            'is_need_qc' => '是否需要qc：0初始状态 1需要qc 2不需要qc',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
            'review_reason' => 'QC修改原因',
        ];
    }

    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_UPDATE, [$this, "checkPassStatus"]);
    }

    public function checkPassStatus()
    {
        //QC结果发生改变时 重新判断是否合格
        if ($this->qc_result && $this->qc_result != $this->oldAttributes['qc_result']) {
            $qc_result = json_decode($this->qc_result, true);
            $this->pass_status = 1;
            foreach ($qc_result as $result) {
                //只要有一个输出项不合格 整个状态就改成不合格
                if ($result['output'] === false) {
                    $this->pass_status = 0;
                    break;
                }
            }
        }
    }

    public function getSurvey()
    {
        return $this->hasOne(Survey::class, ['survey_code' => 'survey_code']);
    }

    public function getPlan()
    {
        return $this->hasOne(Plan::class, ['id' => 'plan_id']);
    }

    public function getStandard()
    {
        return $this->hasOne(Standard::class, ['id' => 'standard_id']);
    }

    public function getStatics()
    {
        return $this->hasOne(StatisticalItem::class, ['id' => 'statistical_id']);
    }


    /**
     * 创建引擎计算结果条目
     * @param $data
     * @return array
     */
    public static function createEngineResult($data)
    {
        $model = new self();
        $model->result_status = EngineResult::RESULT_STATUS_ING;
        $model->survey_code = (string)$data['survey_code'];
        $model->plan_id = $data['plan_id'];
        $model->standard_id = $data['standard_id'];
        if ($model->save()) {
            return [true, ['id' => $model->attributes['id']]];
        } else {
            return [false, $model->errors];
        }
    }

    /**
     * 更新引擎计算结果
     * @param $where
     * @param $result
     * @param $standard_id
     * @return array
     */
    public static function saveEngineResult($where, $result)
    {
        $model = self::findOne($where);
        if ($model) {
            if (isset($result['context']['ine_channel_id']) && $result['context']['ine_channel_id']) {
                //取出总分映射的输出项的node_index,对比输出结果得到总分存入引擎结果表
                $alias = 'i';
                $join = [
                    ['type' => 'LEFT JOIN',
                        'table' => IneChannel::tableName() . ' ic',
                        'on' => 'i.ine_channel_id = ic.id'],
                ];
                $select = ['node_index'];
                $where = ['ic.standard_id' => $result['context']['standard_id'], 'i.level' => IneConfig::SUBDIVISION_LEVEL_ONE];
                $ine_config = IneConfig::findJoin($alias, $join, $select, $where, true, false);

                $ine_total_points = 0;
                foreach ($result['output_list'] as $v) {
                    if ($v['node_index'] == $ine_config['node_index']) {
                        $ine_total_points = $v['output'];
                    }
                }
                $model->ine_total_points = round($ine_total_points, 2);
                //将ine映射表的快照时间存入引擎结果表
                $ine_config_snapshot = IneConfigSnapshot::find()
                    ->where(['ine_channel_id' => $result['context']['ine_channel_id']])
                    ->orderBy('ine_config_timestamp_id DESC')
                    ->one();
                $model->ine_config_timestamp_id = $ine_config_snapshot->ine_config_timestamp_id;
            }

            $model->result = json_encode($result['output_list']);
            $model->result_time = date('Y-m-d H:i:s');
            $model->result_status = self::RESULT_STATUS_DONE;
            if ($model->save()) {
                return [true, ['id' => $model->attributes['id']]];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, '没有该条数据'];
        }
    }

    public static function getEngineResultData($bodyForm)
    {
        $query = self::resultListQuery($bodyForm);

        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);
        $query->orderby(['survey_time' => SORT_DESC]);

        $data = $query->all();

//        $channelMain = ChannelMain::getAll();
//        $location = StoreBelong::getAll(StoreBelong::TYPE_LOCATION);
//        $supervisor = StoreBelong::getAll(StoreBelong::TYPE_SUPERVISOR);
//        $route = StoreBelong::getAll(StoreBelong::TYPE_ROUTE);
        // 需要考虑删除的情况
//        $ruleOutput = RuleOutputInfo::findAllArray(['standard_id'=> $bodyForm['standard_id']], ['id', 'node_index', 'node_name']);
        // 如果检查项目禁用的话，输出项要从快照表里拿
        $s_model = !empty($bodyForm['standard_id']) ? Standard::findone(['id' => $bodyForm['standard_id']]) : '';
        if ($s_model && $s_model->standard_status == Standard::STATUS_DISABLED) {
            $rule_query = SnapshotRuleOutputInfo::find()
                ->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id' => new Expression('max(sort_id)'), 'status', 'formats'])
                ->groupBy('node_index')
                ->orderBy(['max(status)' => SORT_DESC, 'sort_id' => SORT_ASC])
                ->asArray();
            //        $ruleOutput = array_column($ruleOutput, 'node_index');

            //输出项删除时规则还未启用不展示在规则引擎结果里
            $rule_query->where(['standard_status' => SnapshotRuleOutputInfo::STANDARD_START_YES, 'standard_id' => $bodyForm['standard_id']]);
        } else {
            $rule_query = RuleOutputInfo::find()
                ->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id' => new Expression('max(sort_id)'), 'status', 'formats'])
                ->groupBy('node_index')
                ->orderBy(['max(status)' => SORT_DESC, 'sort_id' => SORT_ASC])
                ->asArray();
            //        $ruleOutput = array_column($ruleOutput, 'node_index');

            //输出项删除时规则还未启用不展示在规则引擎结果里
            if (isset($bodyForm['standard_id']) && $bodyForm['standard_id'] != '') {
                $rule_query->where(['standard_status' => RuleOutputInfo::STANDARD_START_YES, 'standard_id' => $bodyForm['standard_id']]);
            } else if (isset($bodyForm['replan_id']) && $bodyForm['replan_id'] != '') {
                $replan = Replan::findOneArray(['id' => $bodyForm['replan_id']]);
                $rule_query->where(['standard_status' => RuleOutputInfo::STANDARD_START_YES, 'statistical_id' => $replan['statistical_id']]);
            }
        }

        $ruleOutput = $rule_query->all();

        $map = self::getMap($data, $ruleOutput);

        return ['count' => $count, 'map' => $map, 'list' => $data];
    }

    public static function getMap(&$data, $ruleOutput, $result_flag = 'result', $qc_flag = false)
    {
        $channelSub = ChannelSub::getAll(['id', 'code', 'name'], 'code');
        $map = [];
        $mapIndex = [];
        $bu = OrganizationRelation::companyBu();
        $all_type = SceneType::findAllArray([], ['*'], 'id');
        $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
        foreach ($data as &$datum) {
            $tmp = [];
            $datum['bu_name'] = '';
            if (isset($datum['company_code'])) {
                $key = $datum['company_code'] . '_' . $datum['bu_code'];
                $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            }
            if(!isset($datum['channel_type_label'])){
                $datum['channel_type_label'] = isset($channelSub[$datum['sub_channel_code']]) ? $channelSub[$datum['sub_channel_code']]['name'] : '';
            }
            if ($datum[$result_flag] != null) {
                if (is_array($datum[$result_flag])) {
                    $datum['check_list'] = $datum[$result_flag];
                } else {
                    $datum['check_list'] = json_decode($datum[$result_flag], true);
                }
                foreach ($datum['check_list'] as $node) {
//                    if(in_array($node['node_index'], $ruleOutput) && !in_array($node['node_index'], $mapIndex)){
//                        $map[] = ['node_index'=> $node['node_index'], 'label'=> $node['node_name']];
//                        $mapIndex[] = $node['node_index'];
//                    }
                    // 小数转换
                    if (is_float($node['output'])) {
                        $node['output'] = round($node['output'], 2);
                    }
                    if (is_array($node['output'])) {
                        $node['output'] = json_encode($node['output'], JSON_UNESCAPED_UNICODE);
                    }
                    foreach ($ruleOutput as $v) {
                        // 如果设置了qc标识，但不在qc的输出项里，直接跳过
                        if ($qc_flag && !in_array($v['node_index'], $datum['need_qc_data'])) {
                            continue;
                        }
                        if ($node['node_index'] == $v['node_index']) {

                            //此处改为已设置的排序作为展示顺序
//                            $map[$v['sort_id']] = ['node_index' => $v['sort_id'], 'label' => $v['node_name']];
//                            $mapIndex[] = $node['node_index'];
                            $tmp[$v['node_index']] = $node['output'];
                            if ($v['formats'] === '') {
                                $tmp[$v['node_index']] = $node['output'];
                            } else {
                                $tmp[$v['node_index']] = Format::outputFormat($node['output'], json_decode($v['formats'], true));
                            }
                            if ($node['output'] === false) {
                                $tmp[$v['node_index']] = '不合格';
                            }
                            if ($node['output'] === true) {
                                $tmp[$v['node_index']] = '合格';
                            }
                        }
                        $name = '';
                        if ($v['is_all_scene'] == 1) {
                            $name = '全场景';
                        }
                        $scene_type = json_decode($v['scene_type'], true);
                        $scene_code = json_decode($v['scene_code'], true);
                        if (!empty($scene_type)) {
                            foreach ($scene_type as $item) {
                                if (!$name) {
                                    $name .= $all_type[$item]['name'];
                                } else {
                                    $name .= '、' . $all_type[$item]['name'];
                                }
                            }
                        }
                        if (!empty($scene_code)) {
                            $code_name = '';
                            foreach ($scene_code as $item) {
                                if (!$code_name) {
                                    $code_name .= $all_scene[$item]['scene_code_name'];
                                } else {
                                    $code_name .= '、' . $all_scene[$item]['scene_code_name'];
                                }
                            }
                            if ($name != '') {
                                $name = $name . ';' . $code_name;
                            } else {
                                $name = $code_name;
                            }
                        }
                        $v['node_name'] = $name . ':' . $v['node_name'];
                        if (!in_array($v['node_index'], $mapIndex)) {
                            $map[] = ['node_index' => $v['node_index'], 'label' => $v['node_name']];
                            $mapIndex[] = $v['node_index'];
                        }
                    }
                }
//                sort($map);
////                $datum['check_list'] = array_column($datum['check_list'], 'output', 'sort_id');
                $datum['check_list'] = $tmp;
//                $tmp = array_keys($datum['check_list']);
            } else {
                $datum['check_list'] = [];
            }
            if (isset($datum['is_rectify'])) {
                $datum['is_rectify'] = $datum['is_rectify'] == self::IS_RECTIFY_YES;
            }
            unset($datum['merge_answer']);
            unset($datum['result']);
            unset($datum['survey']);
            unset($datum['plan']);
            unset($datum['standard']);
        }
        return $map;
    }

    // 数据校验只校验 plan 表的company_code + bu_code
    public static function resultListQuery($bodyForm)
    {
        $search_type = isset($bodyForm['standard_id']) && $bodyForm['standard_id'] != '' ? 'standard' : 'statics';
//        $query = new baseModel();
        $query = EngineResult::find()->select(['survey_time', 'survey_id', 'merge_answer',
            'result', 'result_status', 'channel_type_label'=> 'sub_channel_name', 'is_rectify',
            new Expression(Survey::tableName() . '.tool_id'),
            new Expression(Survey::tableName() . '.is_inventory'),
            new Expression(Survey::tableName() . '.company_code'),
            new Expression(Survey::tableName() . '.bu_code'),
            new Expression(Survey::tableName() . '.store_id'),
            new Expression(Survey::tableName() . '.store_name name'),
            new Expression(Store::tableName() . '.location_code'),
            new Expression(Store::tableName() . '.location_name'),
            new Expression(Store::tableName() . '.supervisor_name'),
            new Expression(Store::tableName() . '.route_code'),
            new Expression(EngineResult::tableName() . '.id'),
            new Expression(EngineResult::tableName() . '.survey_code'),
//            new Expression(EngineResult::tableName() . '.plan_id'),
            new Expression(EngineResult::tableName() . '.standard_id'),
            new Expression(EngineResult::tableName() . '.statistical_id'),
            'standard_name' => new Expression($search_type == 'standard' ? Standard::tableName() . '.title' : StatisticalItem::tableName() . '.title'),
            'tool_name' => new Expression(Tools::tableName() . '.name')
        ])->asArray();

        // start_time\end_time:
        if ($bodyForm['start_time'] != '' && $bodyForm['end_time'] != '') {
            $query->andFilterWhere(['between', 'survey_time', $bodyForm['start_time'], $bodyForm['end_time'] . ' 23:59:59']);
        }
        if ($bodyForm['start_time'] != '' && $bodyForm['end_time'] == '') {
            $query->andFilterWhere(['>=', 'survey_time', $bodyForm['start_time']]);
        }
        if ($bodyForm['start_time'] == '' && $bodyForm['end_time'] != '') {
            $query->andFilterWhere(['<=', 'survey_time', $bodyForm['end_time'] . ' 23:59:59']);
        }

        // 和售点相关，先在共享库查出store_id列表 营业所\线路\主任\渠道类型
        //channel_id_main: 渠道类型
        $query->andFilterWhere([
            'check_type_id' => $bodyForm['check_type_id'],
            'is_rectify' => isset($bodyForm['is_rectify']) ? $bodyForm['is_rectify'] : ''
        ]);

        $query->andFilterWhere(['=', new Expression(Store::tableName() . '.store_id'), $bodyForm['store_id']]);
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.survey_code'), $bodyForm['survey_code']]);
//        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.location_code'), $bodyForm['location_code']]);
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.route_code'), $bodyForm['route_code']]);
        //增加是否清单店的选择项
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.is_inventory'), $bodyForm['is_inventory']]);
        $query->andFilterWhere(['in', new Expression(Store::tableName() . '.location_code'), $bodyForm['location_code']]);
        $query->andFilterWhere(['=', new Expression(Store::tableName() . '.supervisor_name'), $bodyForm['supervisor_name']]);
        if ($bodyForm['channel_id_main'] != '' && !empty($bodyForm['channel_id_main'])) {
            $subChannel = ChannelSub::findAllArray(['main_id' => $bodyForm['channel_id_main']], ['id', 'code']);
            $tmpChannelId = array_column($subChannel, 'id');
            $query->andWhere(['sub_channel_id' => $tmpChannelId]);
        }

        if (isset($bodyForm['standard_id']) && $bodyForm['standard_id'] != '') {
            $query->andFilterWhere(['=', new Expression(EngineResult::tableName() . '.standard_id'), $bodyForm['standard_id']]);
        } else if (isset($bodyForm['statistical_id'])) {
            $query->andFilterWhere(['=', new Expression(EngineResult::tableName() . '.statistical_id'), $bodyForm['statistical_id']]);
        }
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.tool_id'), $bodyForm['tool_id']]);

        // 还要加售点的过滤
        $bu_condition = User::getBuCondition(Store::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        $query->joinWith('survey');
        $query->joinWith('survey.tool');
        $query->joinWith('survey.store');

        if (isset($bodyForm['standard_id']) && $bodyForm['standard_id'] != '') {
//            $query->joinWith('plan');
            $query->joinWith('standard');
            $query->joinWith('standard.checkType');
            $query->select['check_type_id_label'] = new Expression(CheckType::tableName() . '.title');
            $query->select[] = 'check_type_id';

            // bu过滤
            $bu_condition = User::getBuCondition(Standard::class,
                Yii::$app->params['user_info']['company_code'],
                $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
                !Yii::$app->params['user_is_3004']);
            // company_bu 字段要特殊处理
            User::buFilterSearch($query, $bodyForm['company_bu'], Survey::class);
            if (!empty($bu_condition))
                $query->andWhere($bu_condition);
        } else if (isset($bodyForm['replan_id']) && $bodyForm['replan_id'] != '') {
            $query->joinWith('statics');

            // bu过滤
            $bu_condition = User::getBuCondition(StatisticalItem::class,
                Yii::$app->params['user_info']['company_code'],
                $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
                !Yii::$app->params['user_is_3004']);
            if (!empty($bu_condition))
                $query->andWhere($bu_condition);
            $query->andFilterWhere(['replan_id' => $bodyForm['replan_id']]);
        }

        LOG::log($query->createCommand()->getRawSql());
        return $query;
    }

    /**
     * 获取统计项目规则引擎结果
     * @param $params
     * @return array
     */
    public static function getStatisticalEngineResultData($params)
    {
        $where[] = 'and';
        if (!empty($params['survey_time_start'])) {
            $where[] = ['>=', 's.survey_time', $params['survey_time_start']];
        }
        if (!empty($params['survey_time_end'])) {
            $where[] = ['<=', 's.survey_time', $params['survey_time_end']];
        }
        if (!empty($params['statistical_id'])) {
            $where[] = ['=', 'e.statistical_id', $params['statistical_id']];
        }
        if (!empty($params['check_scope'])) {
            $where[] = ['=', 'r.check_scope', $params['check_scope']];
        }
        if (!empty($params['survey_code'])) {
            $where[] = ['=', 's.survey_code', $params['survey_code']];
        }
        if (!empty($params['sub_channel_id'])) {
            $where[] = ['in', 's.sub_channel_id', $params['sub_channel_id']];
        }
        if (!empty($params['store_id'])) {
            $where[] = ['like', 's.store_id', $params['store_id']];
        }
        if (!empty($params['location_code'])) {
            $where[] = ['=', 'sys_store.location_code', $params['location_code']];
        }
        if (!empty($params['route_code'])) {
            $where[] = ['like', 'sys_store.route_code', $params['route_code']];
        }
        if (!empty($params['supervisor_name'])) {
            $where[] = ['like', 'sys_store.supervisor_name', $params['supervisor_name']];
        }
        if (!empty($params['company_bu'])) {
            foreach ($params['company_bu'] as $v) {
                $company_bu = explode('_', $v);
                $company_code[] = $company_bu[0];
                if (isset($company_bu[1])) {
                    $bu_code[] = $company_bu[1];
                }
            }
            $where[] = ['in', 'sys_store.company_code', $company_code];
            $where[] = ['in', 'sys_store.bu_code', $bu_code];
        }
        $where[] = ['=', 's.status', Survey::DEL_STATUS_NORMAL];
        $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
//        preg_match("/dbname=([^;]*)/", \Yii::$app->db2->dsn, $matches);
//        $database = $matches[1];
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(Store::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        if (empty($params['page']) || $params['page'] < 0) {
            $data = self::find()->alias('e')
                ->leftJoin('sys_survey s', 'e.survey_code = s.survey_code')
                ->leftJoin('sys_tools t', 't.id = s.tool_id')
                ->leftJoin('sys_statistical_item si', 'si.id = e.statistical_id')
                ->leftJoin('sys_store', 'sys_store.store_id = s.store_id')
                ->leftJoin('sys_replan r', 'r.id = e.replan_id')
                //                ->leftJoin($database.'.sys_organization_relation o', 'o.company_code = sys_store.company_code and o.bu_code = sys_store.bu_code')
                ->select(['s.survey_time', 's.survey_code', 't.name tool_name', 'si.title statistical_name', 's.sub_channel_name', 's.store_id',
                    'sys_store.location_name', 'sys_store.supervisor_name', 'sys_store.route_name', 'e.result', 'sys_store.company_code', 'sys_store.bu_code', 'r.check_scope'])
                ->andWhere($where)
                ->orderBy('s.survey_time DESC')
                ->asArray()
                ->all();
            $count = count($data);
        } else {
            $page = $params['page'] - 1;
            $pages = new Pagination(['pageSize' => $params['page_size'], 'page' => $page]);
            $query = self::find();
            $data = $query->offset($pages->offset)->limit($pages->limit)
                ->alias('e')
                ->leftJoin('sys_survey s', 'e.survey_code = s.survey_code')
                ->leftJoin('sys_tools t', 't.id = s.tool_id')
                ->leftJoin('sys_statistical_item si', 'si.id = e.statistical_id')
                ->leftJoin('sys_store', 'sys_store.store_id = s.store_id')
                ->leftJoin('sys_replan r', 'r.id = e.replan_id')
//                ->leftJoin($database.'.sys_organization_relation o', 'o.company_code = sys_store.company_code and o.bu_code = sys_store.bu_code')
                ->select(['s.survey_time', 's.survey_code', 't.name tool_name', 'si.title statistical_name', 's.sub_channel_name', 's.store_id',
                    'sys_store.location_name', 'sys_store.supervisor_name', 'sys_store.route_name', 'e.result', 'sys_store.company_code', 'sys_store.bu_code', 'r.check_scope'])
                ->andWhere($where)
                ->orderBy('s.survey_time DESC');
            $count = $data->count();
            $data = $data->asArray()
                ->all();
        }
//        print_r($data->createCommand()->getRawSql());die;
        $ruleOutput = RuleOutputInfo::find()->where(['statistical_id' => $params['statistical_id'], 'status' => RuleOutputInfo::DEL_STATUS_NORMAL])->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id', 'formats'])->asArray()->all();
        $map = [];
        $mapIndex = [];
        $tmp = [];
        $bu = OrganizationRelation::companyBu();
        $all_type = SceneType::findAllArray([], ['*'], 'id');
        $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
        foreach ($data as &$datum) {
            $key = $datum['company_code'] . '_' . $datum['bu_code'];
            $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            if ($datum['result'] != null) {
                $datum['check_list'] = json_decode($datum['result'], true);
                foreach ($datum['check_list'] as $node) {
//                    if(in_array($node['node_index'], $ruleOutput) && !in_array($node['node_index'], $mapIndex)){
//                        $map[] = ['node_index'=> $node['node_index'], 'label'=> $node['node_name']];
//                        $mapIndex[] = $node['node_index'];
//                    }
                    // 小数转换
                    if (is_float($node['output'])) {
                        $node['output'] = round($node['output'], 2);
                    }
                    foreach ($ruleOutput as $v) {
                        if ($node['node_index'] == $v['node_index'] && !in_array($node['node_index'], $mapIndex)) {
                            $name = '';
                            if ($v['is_all_scene'] == 1) {
                                $name = '全场景';
                            }
                            $scene_type = json_decode($v['scene_type'], true);
                            $scene_code = json_decode($v['scene_code'], true);
                            if (!empty($scene_type)) {
                                foreach ($scene_type as $item) {
                                    if (!$name) {
                                        $name .= $all_type[$item]['name'];
                                    } else {
                                        $name .= '、' . $all_type[$item]['name'];
                                    }
                                }
                            }
                            if (!empty($scene_code)) {
                                $code_name = '';
                                foreach ($scene_code as $item) {
                                    if (!$code_name) {
                                        $code_name .= $all_scene[$item]['scene_code_name'];
                                    } else {
                                        $code_name .= '、' . $all_scene[$item]['scene_code_name'];
                                    }
                                }
                                if ($name != '') {
                                    $name = $name . ';' . $code_name;
                                } else {
                                    $name = $code_name;
                                }
                            }
                            $v['node_name'] = $name . ':' . $v['node_name'];
                            //此处改为已设置的排序作为展示顺序
                            $map[$v['sort_id']] = ['node_index' => $v['sort_id'], 'label' => $v['node_name']];
                            $mapIndex[] = $node['node_index'];
                            $tmp[(string)$v['sort_id']] = Format::outputFormat($node['output'], json_decode($v['formats'], true));;
                        }
                    }
                }
                sort($map);
//                $datum['check_list'] = array_column($datum['check_list'], 'output', 'sort_id');
                $datum['check_list'] = $tmp;
//                $tmp = array_keys($datum['check_list']);
            } else {
                $datum['check_list'] = [];
            }
            unset($datum['result']);
        }

        return ['count' => $count, 'map' => $map, 'list' => $data];
    }

    /**
     * 批量创建引擎计算结果条目
     * @param $key
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveAllEngineResult($key, $value)
    {
        $model = Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false];
        }
    }

    public static function findAllReport($where, $select)
    {
        $query = self::find()->alias('e')
            ->leftJoin('sys_standard s', 's.id = e.standard_id')
            ->leftJoin('sys_protocol_template p', 'p.id = s.protocol_id')
            ->andWhere($where)
            ->select($select)
            ->asArray();
        return $query->all();
    }

    /**
     * 获取检查计划任务总数
     *
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午6:15
     *
     * @param     $plan_id_arr
     *
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getPlanCountByIds($plan_id_arr)
    {
        $query = self::find();

        return $query->select([
            'plan_id',
            new Expression("COUNT(id) AS total"),
        ])
            ->where(['plan_id' => $plan_id_arr, 'is_need_qc' => EngineResult::IS_NEED_QC_YES])
            ->groupBy('plan_id')
            ->indexBy('plan_id')
            ->asArray()
            ->all();
    }

    /**
     * 获取检查计划任务已复核总数
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午4:27
     *
     * @param     $plan_id_arr
     *
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getPlanFinishByIds($plan_id_arr)
    {
        $query = self::find();

        $query->select([
            'plan_id',
            new Expression("COUNT(id) AS total"),
        ])
            ->where(['plan_id' => $plan_id_arr, 'is_need_qc' => EngineResult::IS_NEED_QC_YES, 'qc_status' => [EngineResult::ENGINE_RESULT_QC_DOWN, EngineResult::ENGINE_RESULT_QC_IGNORE]])
            ->groupBy('plan_id')
            ->indexBy('plan_id')
            ->asArray();
        return $query->all();
    }

    /**
     * 获取人工复核结果列表
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午7:14
     *
     * @param $data
     *
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getManualCheckResultList($data)
    {
        $query = self::find()->where(['<>', 'qc_status', EngineResult::ENGINE_RESULT_QC_DEFAULT]);

        // 检查时间
        if (!empty($data['start_time']) and empty($data['end_time'])) {
            $query->andWhere(['>=', 's.survey_time', $data['start_time'] . ' 23:59:59']);
        }
        if (!empty($data['end_time']) and empty($data['start_time'])) {
            $query->andWhere(['<=', 's.survey_time', $data['end_time'] . ' 00:00:00']);
        }
        if (!empty($data['start_time']) and !empty($data['end_time'])) {
            $query->andWhere([
                'and',
                ['>=', 's.survey_time', $data['start_time'] . ' 00:00:00'],
                ['<=', 's.survey_time', $data['end_time'] . ' 23:59:59'],
            ]);
        }
        //执行工具
        if (!empty($data['tool_id'])) {
            $query->andWhere(['s.tool_id' => $data['tool_id']]);
        }
        //检查项目名称
        if (!empty($data['standard_id'])) {
            $query->andWhere(['r.standard_id' => $data['standard_id']]);
        }
        //走访号
        if (isset($data['survey_code']) and !empty($data['survey_code'])) {
            $query->andWhere(['r.survey_code' => $data['survey_code']]);
        }
        //渠道类型
//        if (isset($data['sub_channel_arr']) and !empty($data['sub_channel_id'])) {
//            $query->andWhere(['s.sub_channel_id' => array_column($data['sub_channel_arr'], 'code')]);
//        }
        $query->andFilterWhere(['s.sub_channel_id' => $data['sub_channel_id']]);
        //售点编号
        if (isset($data['store_id']) and !empty($data['store_id'])) {
            $query->andWhere(['s.store_id' => $data['store_id']]);
        }
        //大区
        if (isset($data['region_code']) and !empty($data['region_code'])) {
            $query->andWhere(['t.region_code' => $data['region_code']]);
        }
        //营业所
        if (!empty($data['location_code']) && is_array($data['location_code'])) {
            $query->andWhere(['t.location_code' => $data['location_code']]);
        }
        //主任
        if (isset($data['supervisor_name']) and !empty($data['supervisor_name'])) {
            $query->andWhere(['t.supervisor_name' => $data['supervisor_name']]);
        }
        //线路
        if (isset($data['route_name']) and !empty($data['route_name'])) {
            $query->andWhere(['t.route_name' => $data['route_name']]);
        }
        //复核状态
        if (isset($data['qc_status'])) {
            $query->andWhere(['r.qc_status' => $data['qc_status']]);
        }

        $query->from('sys_engine_result r');
        $query->leftJoin('sys_survey s', 'r.survey_code = s.survey_code');
        $query->leftJoin('sys_store t', 's.store_id = t.store_id');

        $bu_condition = User::getBuCondition(Survey::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004'], 's');
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        $count = $query->count();

        $data = $query->select([
            'r.id',
            's.survey_time',
            's.company_code',
            's.bu_code',
            's.tool_id',
            'r.standard_id',
            'r.survey_code',
            't.sub_channel_code',
            's.sub_channel_name',
            's.store_id',
            't.region_code',
            't.location_name',
            't.supervisor_name',
            't.route_name',
            'r.qc_status',
            'r.review_reason',
            'qc_result' => new Expression('if(r.qc_status = ' . EngineResult::ENGINE_RESULT_QC_DOWN . ', r.qc_result, r.result)'),
        ])
            ->orderBy('s.survey_time DESC')
            ->offset(($data['page'] - 1) * $data['page_size'])
            ->limit($data['page_size'])
            ->asArray()
            ->all();

        return ['list' => $data, 'count' => (int)$count];
    }

    public function getOutputInfoValue($result)
    {
        if (!$result) return [];
        $result = json_decode($result, true);
        foreach ($result as $output) {
            $output_info[] = RuleOutputInfo::find()
                ->with('subActivity')
                ->where(['standard_id' => $this->standard_id])
                ->andWhere(['node_index' => $output['node_index']])
                ->asArray()
                ->one();
            //index和value的映射关系
            $outputValues[$output['node_index']] = $output['output'];
        }
        //获取need_qc_data
        $need_qc_indexes = [];
        $need_qc_data = $this->standard->need_qc_data ? json_decode($this->standard->need_qc_data, true) : [];
        foreach ($need_qc_data as $node_indexes) {
            foreach ($node_indexes as $node_index) {
                $need_qc_indexes[] = $node_index;
            }
        }
        //返回结果
        $sub_activity_ouputs = [];
        foreach ($output_info as $info) {
            //开启qc且索引不在里面，跳过
            if ($this->standard->is_need_qc && !in_array($info['node_index'], $need_qc_indexes)) continue;
            //生动化信息
            $sub_activity_name = $info['subActivity']['activation_name'] ?? '场景组';
            $success_images = $info['subActivity']['image'] ?? [];
            if (isset($sub_activity_ouputs[$info['sub_activity_id']])) {
                $sub_activity_ouputs[$info['sub_activity_id']]['outputs'][] = [
                    'node_index' => $info['node_index'],
                    'node_name' => $info['node_name'],
                    'output' => $outputValues[$info['node_index']] ?? false
                ];
            } else {
                $sub_activity_ouputs[$info['sub_activity_id']] = [
                    'sub_activity_id' => $info['sub_activity_id'],
                    'sub_activity_name' => $sub_activity_name,
                    'success_images' => $success_images,
                    'outputs' => [[
                        'node_index' => $info['node_index'],
                        'node_name' => $info['node_name'],
                        'output' => $outputValues[$info['node_index']] ?? false
                    ]]
                ];
            }
        }
        //key排成自然序
        return array_merge($sub_activity_ouputs);
    }

    /**
     * 获取历史按月的平均得分
     * @param $params
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getHistoricalScore($params)
    {
        $params['tool_list'] = $params['tool_list'] ?: Tools::ALL_INE_TOOL_ID;
        $query = self::find()->alias('e')
            ->leftJoin(Survey::tableName() . ' s', 's.survey_code = e.survey_code')
            ->andWhere(['s.store_id' => $params['store_id'],
                's.ine_channel_id' => $params['ine_channel_id'],
                's.tool_id' => $params['tool_list'],
                's.is_ine' => Survey::IS_INE_YES,
                's.plan_id' => 0,
            ])
            ->select([new Expression('YEAR(result_time) year'),
                new Expression('MONTH(result_time) month'),
                new Expression('AVG(ine_total_points) avg_score'),
                new Expression('concat(YEAR(result_time),"-",MONTH(result_time)) time'),
            ])
            ->groupBy(new Expression('year,month'))
            ->orderBy('result_time DESC')
            ->limit($params['limit'])
            ->indexBy('time')
            ->asArray();
        return $query->all();
    }

//    public static function getHistoricalScoreInfo($params)
//    {
//        $query = self::find()->alias('e')
//            ->leftJoin(Survey::tableName() . ' s', 's.survey_code = e.survey_code')
//            ->andWhere(['s.store_id' => $params['store_id'],
//                'e.ine_channel_id' => $params['ine_channel_id'],
//                's.tool_id' => $params['tool_list'],
//                's.is_ine' => Survey::IS_INE_YES,
//                new Expression('Convert ( VARCHAR(7),ComeDate,120)') => $params['time'],
//                's.plan_id' => 0,
//            ])
//            ->select(['s.survey_time','s.survey_code','s.tool_id',new Expression('CASE tool_id WHEN 1 THEN "检出员" WHEN 10 THEN "高管" END AS tool_role'),
//                's.examiner','e.ine_total_points','e.result'
//            ])
//            ->asArray();
//        return $query->all();
//    }
}