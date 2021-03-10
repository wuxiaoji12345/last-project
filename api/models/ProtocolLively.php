<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "sys_protocol_lively".
 *
 * @property int $id 主键id
 * @property int $standard_id 规则id
 * @property int $activation_id 生动化编号
 * @property string $activation_name 生动化名称
 * @property string $activation_desc 生动化描述
 * @property int $is_standard 是否基础生动化 1:是, 0:否
 * @property string $output_list 规则引擎输出项列表
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ProtocolLively extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_protocol_lively';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['standard_id', 'activation_id', 'is_standard', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['activation_name'], 'string', 'max' => 20],
            [['activation_desc'], 'string', 'max' => 50],
            [['output_list'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'standard_id' => '规则id',
            'activation_id' => '生动化编号',
            'activation_name' => '生动化名称',
            'activation_desc' => '生动化描述',
            'is_standard' => '是否基础生动化 1:是, 0:否',
            'output_list' => '规则引擎输出项列表',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 批量更新生动化项
     * @param $key
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveAllLively($key, $value)
    {
        $model = Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false];
        }
    }
}
