<?php

namespace api\models;

use api\service\zft\Protocol;
use Yii;

/**
 * This is the model class for table "sys_protocol_template".
 *
 * @property int $id 主键id
 * @property string $contract_code 协议编号
 * @property string $company_code 厂房code
 * @property int $contract_id 协议Id 唯一Key
 * @property string $contract_name 协议名称
 * @property int $contract_year 协议年份
 * @property int $contract_type 类型
 * @property int $excute_count 检查次数
 * @property int $interval_day 间隔天数
 * @property string $sign_from_date 签约开始时间
 * @property string $sign_to_date 签约结束时间
 * @property string $excute_from_date 执行检查开始时间 
 * @property string $excute_to_date 执行检查结束时间
 * @property string $create_date 协议创建的时间
 * @property string $activation_list 生动化列表
 * @property string $excute_cycle_list 执行周期时间段列表
 * @property int $protocol_status 协议状态 10:启用 90:禁用
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ProtocolTemplate extends baseModel
{
    /**
     * 协议状态
     */
    const PROTOCOL_STATUS_ENABLE = 10;       // 10 启用
    const PROTOCOL_STATUS_DISABLE = 90;      // 90 禁用

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_protocol_template';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['contract_id', 'contract_year', 'contract_type', 'excute_count', 'interval_day', 'protocol_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['create_date', 'activation_list', 'excute_cycle_list'], 'required'],
            [['activation_list', 'excute_cycle_list'], 'string'],
            [['update_time'], 'safe'],
            [['contract_code'], 'string', 'max' => 255],
            [['contract_name'], 'string', 'max' => 50],
            [['sign_from_date', 'sign_to_date', 'excute_from_date', 'excute_to_date'], 'string', 'max' => 10],
            [['create_date'], 'string', 'max' => 20],
            [['company_code'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'contract_code' => '协议编号',
            'company_code' => '厂房code',
            'contract_id' => '协议Id 唯一Key',
            'contract_name' => '协议名称',
            'contract_year' => '协议年份',
            'contract_type' => '类型',
            'excute_count' => '检查次数',
            'interval_day' => '间隔天数',
            'sign_from_date' => '签约开始时间',
            'sign_to_date' => '签约结束时间',
            'excute_from_date' => '执行检查开始时间 ',
            'excute_to_date' => '执行检查结束时间',
            'create_date' => '协议创建的时间',
            'activation_list' => '生动化列表',
            'excute_cycle_list' => '执行周期时间段列表',
            'protocol_status' => '协议状态 10:启用 90:禁用',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
//        Protocol::syncPlanTime($this->id);
        parent::afterSave($insert, $changedAttributes);
    }

}
