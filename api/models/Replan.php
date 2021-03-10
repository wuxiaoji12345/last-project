<?php

namespace api\models;

use common\libs\file_log\LOG;
use Yii;
use yii\db\Expression;

/**
 * This is the model class for table "{{%replan}}".
 *
 * @property int $id 主键id
 * @property int $statistical_id 统计项目id
 * @property int $standard_id 指定检查项目id
 * @property int $tool_id 执行工具id
 * @property string $company_code 厂房
 * @property string $bu_code bu
 * @property int $user_id 用户id
 * @property string $filter_company_bu 筛选项厂房
 * @property string $sub_channel_code 次渠道
 * @property int $gather_type 采集方式
 * @property int $check_scope 检查范围 0所有，1全店检查，2非全店检查（非INE）
 * @property string $start_time 检查开始时间
 * @property string $end_time 检查结束时间
 * @property int $total_number 总数
 * @property int $finished_number 已完成数量
 * @property int $replan_status 统计任务状态0：默认，1已开始，2已暂停，3已结束
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class Replan extends baseModel
{
    const BU_FLAG = true;
    const HAS_3004 = false;     // 有3004的数据，但是这些数据也是不给其他厂房看的

    const STATUS_DEFAULT = 0;
    const STATUS_RUNNING = 1;
    const STATUS_PAUSE = 2;
    const STATUS_FINISH = 3;

    const CHECK_SCOPE_DEFAULT = 0;  // 所有检查
    const CHECK_SCOPE_ALL = 1;  // 全店检查（INE）
    const CHECK_SCOPE_NOT_ALL = 2;  // 非全店检查（非INE）
    const CHECK_SCOPE_STANDARD = 3;  // 非全店检查（非INE）

    const CHECK_LABEL_ARR = [
        self::CHECK_SCOPE_DEFAULT => '所有检查',
        self::CHECK_SCOPE_ALL => '全店检查（INE）',
        self::CHECK_SCOPE_NOT_ALL => '非全店检查（非INE）',
        self::CHECK_SCOPE_STANDARD => '指定检查项目',
    ];

    const CHECK_SCOPE_INE_MAP = [
        self::CHECK_SCOPE_ALL => Survey::IS_IR_YES,
        self::CHECK_SCOPE_NOT_ALL => Survey::IS_IR_NO,
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%replan}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['statistical_id', 'tool_id', 'sub_channel_code'], 'required'],
            [['statistical_id', 'tool_id', 'user_id', 'gather_type', 'check_scope', 'total_number',
                'finished_number', 'replan_status', 'status', 'created_at', 'updated_at', 'standard_id'], 'integer'],
            [['update_time'], 'safe'],
            [['filter_company_bu', 'sub_channel_code'], 'string'],
            [['company_code', 'bu_code', 'start_time', 'end_time'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'statistical_id' => '检查项目id',
            'tool_id' => '执行工具id',
            'company_code' => '厂房',
            'bu_code' => 'bu',
            'user_id' => '用户id',
            'filter_company_bu' => '筛选项bu',
            'sub_channel_code' => '次渠道',
            'gather_type' => '采集方式',
            'check_scope' => '检查范围',
            'start_time' => '检查开始时间',
            'end_time' => '检查结束时间',
            'total_number' => '总数',
            'finished_number' => '已完成数量',
            'replan_status' => '统计任务状态',
            'status' => '删除标识',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ReplanQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReplanQuery(get_called_class());
    }

    public function getReplanSurvey()
    {
        return $this->hasMany(ReplanSurvey::class, ['replan_id' => 'id']);
    }

    /**
     * 列表搜索
     * @param $searchForm
     * @return ReplanQuery
     */
    public static function searchQuery($searchForm)
    {
        $query = Replan::find();
        $query->select([
            'id', 'company_code', 'bu_code', 'filter_company_bu', 'standard_id',
            'created_at', 'start_time', 'end_time', 'statistical_id', 'tool_id',
            'check_scope', 'total_number', 'replan_status', 'finished_number'])
            ->andFilterWhere([
                'statistical_id' => $searchForm['statistical_id'],
                'tool_id' => $searchForm['tool_id'],
            ]);
        // 检查时间
        if ($searchForm['check_start_time'] != '' && $searchForm['check_end_time'] == '') {
            $query->andFilterWhere(['>=', 'end_time', $searchForm['check_start_time']]);
        }
        if ($searchForm['check_end_time'] != '' && $searchForm['check_start_time'] == '') {
            $query->andFilterWhere(['<=', 'start_time', $searchForm['check_end_time']]);
        }
        if ($searchForm['check_end_time'] != '' && $searchForm['check_start_time'] != '') {
            $query->andFilterWhere([
                'and',
                ['>=', 'end_time', $searchForm['check_start_time']],
                ['<=', 'start_time', $searchForm['check_end_time']]
            ]);
        }

        if ($searchForm['create_start_time'] != '' && $searchForm['create_end_time'] == '') {
            $query->andFilterWhere(['>=', 'created_at', strtotime($searchForm['create_start_time'] . ' 00:00:00')]);
        }
        if ($searchForm['create_end_time'] != '' && $searchForm['create_start_time'] == '') {
            $query->andFilterWhere(['<=', 'created_at', strtotime($searchForm['create_end_time'] . ' 23:59:59')]);
        }
        if ($searchForm['create_start_time'] != '' && $searchForm['create_end_time'] != '') {
            $query->andFilterWhere(['between', 'created_at', strtotime($searchForm['create_start_time'] . ' 00:00:00'), strtotime($searchForm['create_end_time'] . ' 23:59:59')]);
        }

        $bu_condition = User::getBuCondition(Replan::class,
            Yii::$app->params['user_info']['company_code'],
            $searchForm['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        // bu筛选
        if (!empty($searchForm['company_bu'])) {
            $bu_filter = ['or'];
            foreach ($searchForm['company_bu'] as $bu) {
                $bu_filter[] = ['like', 'filter_company_bu', $bu];
            }
            $query->andFilterWhere($bu_filter);
        }

        return $query;
    }

    /**
     * 符合范围的走访数据
     * 执行工具、BU多选、次渠道多选、采集方式、检查范围、检查时间
     * @param $searchForm
     * @return bQuery
     */
    public static function findSurvey($searchForm)
    {
        $query = Survey::find()->alias('su')
            ->where(new Expression('su.status = ' . Survey::DEL_STATUS_NORMAL))->groupBy('su.id');
        $query->select([
            new Expression('su.id'),
            new Expression('su.store_id'),
            'su.survey_code', 'sub_activity_id', 'sub_channel_id', 'tool_id', 'survey_time', 'survey_status',
            'is_ir', 'is_ine'
        ]);
        $query->leftJoin(Store::tableName() . ' st', 'st.store_id = su.store_id');
        // 检查时间
        $query->andWhere(['survey_status' => Survey::SURVEY_END]);
        $query->andWhere(['between', 'survey_time', $searchForm['start_time']. ' 00:00:00', $searchForm['end_time']. ' 23:59:59']);
        $query->andWhere([
            'tool_id' => $searchForm['tool_id'],
            'is_ir' => Survey::IS_IR_YES,   // 采集方式
        ]);

        // 检查范围
        switch ($searchForm['check_scope']) {
            case self::CHECK_SCOPE_ALL:
            case self::CHECK_SCOPE_NOT_ALL:
                $query->andWhere(['is_ine' => Replan::CHECK_SCOPE_INE_MAP[$searchForm['check_scope']]]);
                break;
            case self::CHECK_SCOPE_STANDARD:
                $query->select[] = new Expression('er.plan_id');
                $query->leftJoin(EngineResult::tableName() . ' er', 'er.survey_code = su.survey_code');
                $query->andWhere(['er.standard_id' => $searchForm['standard_id']]);
                break;
            default:
        }
        // bu
        if (!empty($searchForm['company_bu'])) {
            $bu_filter = ['or'];
            foreach ($searchForm['company_bu'] as $item) {
                $tmp_bu = explode('_', $item);
                $bu_filter[] = ['su.company_code' => $tmp_bu[0], 'su.bu_code' => $tmp_bu[1]];
            }
            $query->andWhere($bu_filter);
        }
        // 次渠道
        if (!empty($searchForm['sub_channel_code']))
            $query->andWhere(['sub_channel_code' => $searchForm['sub_channel_code']]);

        LOG::log($query->createCommand()->getRawSql());
        return $query;
    }

    /**
     * 更新重跑已完成数量
     * @param $id
     * @throws \yii\db\Exception
     */
    public static function saveFinishedNumber($id)
    {
        Replan::updateAll([
            'finished_number' => new Expression('finished_number+1'),
            'replan_status' => new Expression('if(finished_number>=total_number,' . self::STATUS_FINISH . ',' . self::STATUS_RUNNING . ')')
        ], ['id' => $id]);

//        $sql = 'update ' . self::tableName() . ' set finished_number = finished_number+1,replan_status = if(finished_number>=total_number,' . self::STATUS_FINISH . ',' . self::STATUS_RUNNING . ') where id = ' . $id;
//        Yii::$app->db->createCommand($sql)->execute();
    }

    public function beforeValidate()
    {
        if (is_array($this->sub_channel_code)) {
            $this->sub_channel_code = implode(',', $this->sub_channel_code);
        }
        return parent::beforeValidate();
    }

    public function beforeSave($insert)
    {
        $this->standard_id = $this->standard_id == '' ? 0 : $this->standard_id;
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }
}
