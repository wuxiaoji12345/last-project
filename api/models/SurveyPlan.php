<?php

namespace api\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%survey_plan}}".
 *
 * @property int $id 主键id
 * @property string $survey_code 走访code
 * @property int $plan_id 指定检查计划id
 * @property int $tool_id 执行工具id
 * @property string $store_id 售点id
 * @property int $need_question_qc 问卷是否需要qc：0不需要，1需要
 * @property int $question_qc_status 问卷qc状态：0.未复核 1.已复核 2.批量复核
 * @property string $description 修改原因
 * @property int $user_id qc人员信息
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SurveyPlan extends \api\models\baseModel
{
    /**
     * 是否需要复核
     */
    const NEED_QC_DEFAULT = 0; // 不需要
    const NEED_QC_YES = 1;  // 需要

    /**
     * 复核状态
     */
    const QUESTION_QC_STATUS_DEFAULT = 0;  // 默认未复核
    const QUESTION_QC_STATUS_DONE = 1;  // 已复核
    const QUESTION_QC_STATUS_BATCH_DONE = 2;  // 批量复核

    const QUESTION_QC_STATUS_CN = [
        self::QUESTION_QC_STATUS_DEFAULT => '未复核',
        self::QUESTION_QC_STATUS_DONE => '已复核',
        self::QUESTION_QC_STATUS_BATCH_DONE => '批量复核',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey_plan}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['plan_id', 'need_question_qc', 'question_qc_status', 'tool_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['description'], 'string'],
            [['store_id'], 'string', 'max' => 16],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'survey_code' => '走访code',
            'plan_id' => '指定检查计划id （针对特定计划走访）',
            'tool_id' => '执行工具id',
            'store_id' => '售点id',
            'need_question_qc' => '问卷是否需要qc：0不需要，1需要',
            'question_qc_status' => '问卷qc状态：0.未复核 1.已复核 2.批量复核',
            'description' => '修改原因',
            'user_id' => 'qc人员信息',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * QC问卷结果
     * @return \yii\db\ActiveQuery
     */
    public function getQuestionAnswerQc()
    {
        return $this->hasMany(QuestionAnswerQc::class, ['survey_code' => 'survey_code', 'plan_id' => 'plan_id']);
    }

    /**
     * 检查计划
     * @return \yii\db\ActiveQuery
     */
    public function getPlan()
    {
        return $this->hasOne(Plan::class, ['id' => 'plan_id']);
    }

    /**
     * 获得问卷qc任务条目详情
     * @param $plan_id
     * @param $tool_id
     * @return array
     */
    public static function getPlanTotalInfo($plan_id, $tool_id)
    {
        $model = SurveyPlan::find()->where(['plan_id' => $plan_id, 'tool_id' => $tool_id, 'need_question_qc' => self::NEED_QC_YES]);
        $plan_total = $model->count();
        $plan_finish_total = $model->andwhere(['question_qc_status' => self::QUESTION_QC_STATUS_DONE])->count();
        $remain_total = $plan_total - $plan_finish_total;
        return ['plan_total' => $plan_total, 'plan_finish_total' => $plan_finish_total, 'remain_total' => $remain_total];
    }

    /**
     * 从走访计划表里取出计划列表的任务
     * @param $data
     * @return array|string|ActiveRecord|ActiveRecord[]|null
     */
    public static function getQuestionQcPlanList($data)
    {
        $where = [
            [
                ['standard_id' => 'pl.standard_id',
                    'tool_id' => 'pl.tool_id',
                    'question_model' => 'pl.question_model',
                    'need_question_qc' => 'sp.need_question_qc'],
                '='
            ],
//            [
//                ['create_start_time' => 'pl.created_at',],
//                '>=',
//                'start_time'
//            ],
//            [
//                ['create_end_time' => 'pl.created_at',],
//                '<=',
//                'end_time'
//            ],
            [
                ['pl.created_at' => ['create_start_time', 'create_end_time'],],
                'between',
                'timestamp'
            ]
        ];
        $where = \Helper::makeWhere($where, $data);
        $where_or = [
            [
                ['pl.start_time' => ['check_start_time', 'check_end_time'],
                    'pl.end_time' => ['check_start_time', 'check_end_time'],],
                'between'
            ],
            [
                ['check_start_time' => ['pl.start_time', 'pl.end_time'],],
                'tweenbe',
                'start_time'
            ],
            [
                ['check_end_time' => ['pl.start_time', 'pl.end_time'],],
                'tweenbe',
                'end_time'
            ],
        ];
        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004'],
            'st');
        if (!empty($bu_condition))
            $where[] = $bu_condition;
        $where_or = \Helper::makeWhere($where_or, $data, true);
        $where[] = $where_or;
        $join = [
            [
                'type' => 'LEFT JOIN',
                'table' => Plan::tableName() . ' pl',
                'on' => 'pl.id = sp.plan_id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Standard::tableName() . ' st',
                'on' => 'st.id = pl.standard_id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Tools::tableName() . ' t',
                'on' => 'pl.tool_id = t.id'
            ],];
        $select = ['sp.plan_id', 'pl.start_time', 'pl.end_time', 'pl.created_at create_time', 't.id tool_id',
            't.name tool_name', 'st.title standard_name', 'pl.question_model'];
        return self::findJoin('sp', $join, $select, $where, $asArray = true, $all = true, $order = 'sp.plan_id desc',
            $index = '', $group = 'sp.plan_id', $with = '', $pages = ['page' => $data['page'] - 1, 'page_size' => $data['page_size']]);
    }

    /**
     * 从走访计划表里取出单个计划的所有走访的列表的数据
     * @param $data
     * @return array|string|ActiveRecord|ActiveRecord[]|null
     */
    public static function getQuestionQcSurveyList($data)
    {
        $where = [
            [
                [
                    'plan_id' => 'sp.plan_id',
                    'survey_code' => 'pl.survey_code',
                    'store_id' => 's.store_id',
                    'question_qc_status' => 'sp.question_qc_status',
                    'need_question_qc' => 'sp.need_question_qc'
                ],
                '='
            ],
//            [
//                ['create_start_time' => 'pl.created_at',],
//                '>=',
//                'start_time'
//            ],
//            [
//                ['create_end_time' => 'pl.created_at',],
//                '<=',
//                'end_time'
//            ],
            [
                ['pl.created_at' => ['create_start_time', 'create_end_time'],],
                'between',
                'timestamp'
            ],
            [
                [
                    'channel_id_main' => 's.sub_channel_id',
                    'region_code' => 'sto.region_code',
                    'location_code' => 'sto.location_code',
                    'supervisor_name' => 's.supervisor_name',
                    'route_code' => 's.route_code',
                ],
                'in'
            ],
        ];
        $where = \Helper::makeWhere($where, $data);
        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's');
        if (!empty($bu_condition))
            unset($bu_condition[0]);
        $where = array_merge($where, $bu_condition);
        $join = [
            [
                'type' => 'LEFT JOIN',
                'table' => Plan::tableName() . ' pl',
                'on' => 'pl.id = sp.plan_id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Standard::tableName() . ' st',
                'on' => 'st.id = pl.standard_id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Tools::tableName() . ' t',
                'on' => 'pl.tool_id = t.id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Store::tableName() . ' sto',
                'on' => 'sto.store_id = sp.store_id'
            ], [
                'type' => 'LEFT JOIN',
                'table' => Survey::tableName() . ' s',
                'on' => 's.survey_code = sp.survey_code'
            ],];
        $select = [
            's.survey_time', 's.survey_code', 't.id tool_id', 't.name tool_name', 'st.title standard_name', 's.sub_channel_name channel_name',
            's.store_id', 's.region_code', 'sto.location_code', 's.location_name', 's.supervisor_name', 's.route_code', 'sto.route_name',
            'sp.question_qc_status', 'sp.description question_qc_describe', 'sp.id qc_task_id'
        ];
        $order = 's.survey_time desc, s.id desc';
        $pages = ['page' => $data['page'] - 1, 'page_size' => $data['page_size']];
        return self::findJoin('sp', $join, $select, $where, true, true, $order,'', '', '', $pages);
    }

    /**
     * 批量保存走访计划
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveSurveyPlan($value)
    {
        $key = self::getModelKey();
        unset($key[0]);
        array_pop($key);
        $key = array_values($key);
        return self::batchSave($value, $key);
    }
}
