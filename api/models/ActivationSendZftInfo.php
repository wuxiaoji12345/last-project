<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%activation_send_zft_info}}".
 *
 * @property int $id
 * @property int $standard_id 成功图像标准id
 * @property string $survey_code 走访code
 * @property int $activation_id 生动化编号
 * @property string $activation_name 生动化名称
 * @property string $store_id 售点id
 * @property string $output_list 绑定的输出项组
 * @property int $protocol_id 协议模板id
 * @property int $is_standard ZFT的isStandard字段
 * @property int $outlet_contract_id zft客户协议id
 * @property string $check_count_field 额外检查的字段（排面数，地堆数，层数）
 * @property int $activation_status 生动化检查结果的状态： 2未检查 1检查成功 0检查失败
 * @property int $all_activation_status 该检查项目所有生动化的状态： 2未检查 1检查成功 0检查失败
 * @property int $is_send_zft 是否已推送zft：0未推送 1推送成功 2推送失败
 * @property int $send_zft_time 发送zft的时间
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ActivationSendZftInfo extends baseModel
{
    const ACTIVATION_STATUS_DEFAULT = 2; //生动化检查结果的状态： 2未检查
    const ACTIVATION_STATUS_SUCCESS = 1; //1检查成功
    const ACTIVATION_STATUS_FAIL = 0; //0检查失败

    const ALL_ACTIVATION_STATUS_DEFAULT = 2; //该检查项目所有生动化的状态： 2未检查
    const ALL_ACTIVATION_STATUS_SUCCESS = 1; //1检查成功
    const ALL_ACTIVATION_STATUS_FAIL = 0; //0检查失败

    const SEND_ZFT_DEFAULT = 0; //是否已推送zft：0未推送
    const SEND_ZFT_SUCCESS = 1; //1推送成功
    const SEND_ZFT_FAIL = 2; //2推送失败

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%activation_send_zft_info}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['standard_id', 'activation_id', 'protocol_id', 'is_standard', 'outlet_contract_id', 'activation_status', 'all_activation_status', 'is_send_zft', 'send_zft_time', 'status', 'created_at', 'updated_at'], 'integer'],
            [['output_list', 'created_at', 'updated_at'], 'required'],
            [['output_list'], 'string'],
            [['update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['activation_name', 'check_count_field'], 'string', 'max' => 255],
            [['store_id'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'standard_id' => '成功图像标准id',
            'survey_code' => '走访code',
            'activation_id' => '生动化编号',
            'activation_name' => '生动化名称',
            'store_id' => '售点id',
            'output_list' => '绑定的输出项组',
            'protocol_id' => '协议模板id',
            'is_standard' => 'ZFT的isStandard字段',
            'outlet_contract_id' => 'zft客户协议id',
            'check_count_field' => '额外检查的字段（排面数，地堆数，层数）',
            'activation_status' => '生动化检查结果的状态： 0未检查 1检查成功 2检查失败',
            'all_activation_status' => '该检查项目所有生动化的状态： 0未检查 1检查成功 2检查失败',
            'is_send_zft' => '是否已推送zft：0未推送 1推送成功 2推送失败',
            'send_zft_time' => '发送zft的时间',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 批量插入生动化发送zft详情表
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveActivationInfo($value)
    {
        $key = ['standard_id','survey_code','activation_id','activation_name','store_id','output_list','protocol_id'
            ,'is_standard','outlet_contract_id','activation_status','is_send_zft','all_activation_status','created_at','updated_at'];
        $model= \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '存储失败，请检查'];
        }
    }
}
