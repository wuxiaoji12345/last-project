<?php

namespace api\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Expression;

/**
 * This is the model class for table "sys_plan_batch".
 *
 * @property int $id 主键id
 * @property string $batch_name 批量名称
 * @property int $tool_id 执行工具
 * @property int $company_code 厂房
 * @property int $bu_code       BU Code
 * @property string $file_name 导入文件名
 * @property string $start_time 开始时间
 * @property string $end_time 结束时间
 * @property string $rectification_model 整改模式
 * @property string $rectification_option 整改配置
 * @property int $batch_status 状态0默认，1启用，2禁用
 * @property string $note 备注
 * @property int $status 删除标识：1有效，0无效
 * @property int $is_push_zft 是否推送ZFT，默认2，1推送、2不推送
 * @property int $is_qc 是否人工复核，默认2，1需要、2不需要
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class PlanBatch extends baseModel
{
    const BU_FLAG = true;

    const BATCH_STATUS_DEFAULT = 0;
    const BATCH_STATUS_ENABLE = 1;
    const BATCH_STATUS_DISABLE = 2;

    const BATCH_STATUS_ARR_LABEL = [
        self::BATCH_STATUS_DEFAULT => '-',
        self::BATCH_STATUS_ENABLE => '已启用',
        self::BATCH_STATUS_DISABLE => '已禁用',
    ];

    // 是否推送ZFT
    const IS_PUSH_ZFT_YES = 1; //推送
    const IS_PUSH_ZFT_NO = 2; //不推送

    // 是否人工复核
    const IS_QC_YES = 1; //需要
    const IS_QC_NO = 2; //不需要

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_batch}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['file_name'], 'required', 'on'=> 'create'],
            [['file_name'], 'safe', 'on'=> 'update'],
            [['tool_id', 'batch_status', 'status', 'is_push_zft', 'is_qc'], 'integer'],
            [['note'], 'string'],
            [['batch_name', 'file_name'], 'string', 'max' => 255],
            [['start_time', 'end_time'], 'string', 'max' => 10],
            [['tool_id'], 'validateTool'],
            [['company_code', 'bu_code', 'rectification_option', 'rectification_model'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'batch_name' => '批量名称',
            'tool_id' => '执行工具',
            'company_code' => '厂房',
            'bu_code' => 'buCode',
            'file_name' => '导入文件名',
            'start_time' => '开始时间',
            'end_time' => '结束时间',
            'rectification_model' => '整改模式',
            'rectification_option' => '整改配置',
            'batch_status' => '状态',
            'note' => '备注',
            'status' => '删除标识',
            'is_push_zft' => '是否推送ZFT',
            'is_qc' => '是否人工复核',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public function getTool()
    {
        return $this->hasOne(Tools::class, ['id' => 'tool_id']);
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['create'] = ['batch_name', 'created_at', 'updated_at', 'batch_status', 'start_time', 'end_time', 'tool_id',
            'file_name', 'company_code', 'bu_code', 'is_push_zft', 'is_qc'];
        $scenarios['update'] = ['batch_name', 'created_at', 'updated_at', 'batch_status', 'start_time', 'end_time', 'tool_id',
            'file_name', 'is_push_zft', 'is_qc'];
        return $scenarios;
    }

    /**
     * 检查计划批次列表
     * @param $where
     * @return bQuery|ActiveQuery
     */
    public static function getListQuery($where = [])
    {
        $query = self::find()->select([self::tableName() . '.*', Tools::tableName() . '.name'])->joinWith('tool')->asArray();

        $query->filterWhere(['=', new Expression(self::tableName() . '.id'), $where['id']]);
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
                ['<=', 'start_time', $where['end_time'] . ' 00:00:00']
            ]);
        }

        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        // company_bu 字段要特殊处理
        User::buFilterSearch($query, $where['company_bu'], PlanBatch::class);

        return $query;
    }

    /**
     * @param $where
     * @return bQuery|ActiveQuery
     */
    public function getPlanQuery($where)
    {
        $query = Plan::find();
        $query->Where([
            'plan_batch_id' => $this->id,
        ]);

        if ($where['contract_code'] != '') {
            $protocolTemplate = ProtocolTemplate::findAllArray(['contract_code' => $where['contract_code']]);
            $protocol_ids = array_column($protocolTemplate, 'id');
            $standards = Standard::findAllArray(['protocol_id' => $protocol_ids], ['id'], '', true);
            $standard_ids = array_column($standards, 'id');
            $where['standard_id'] = $standard_ids;
            $query->andWhere(['standard_id' => $where['standard_id']]);
        } else {
            $query->andFilterWhere(['standard_id' => $where['standard_id']]);
        }
        return $query;
    }

    public function validateTool()
    {
        if ($this->tool_id != Tools::TOOL_ID_SEA) {
            $this->addError('tool_id', "执行工具只能选择 SEA");
        }
    }

    /**
     * @param bool $runValidation
     * @param null $attributeNames
     * @return bool
     * @throws Exception
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $tran = Yii::$app->db->beginTransaction();
        $plans = Plan::findAll(['plan_batch_id'=> $this->id]);
        $hasErr = false;
        foreach ($plans as $plan) {
            /* @var $plan Plan */
            $plan->start_time = $this->start_time. ' 00:00:00';
            $plan->end_time = $this->end_time. ' 23:59:59';
            if(!$plan->save()){
                $hasErr = true;
                $this->addError('start_time', $plan->getErrStr());
                break;
            }
        }
        if($hasErr){
            $tran->rollBack();
            $flag = false;
        } else {
            $flag = parent::save($runValidation, $attributeNames);
            $tran->commit();
        }

        return $flag;
    }
}
