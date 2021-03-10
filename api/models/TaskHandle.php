<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%task_handle}}".
 *
 * @property int $id 主键id
 * @property int $engine_result_id 检查结果id
 * @property int $type 奖励类型：0.现金；1.优惠券；2.积分；
 * @property string $value 奖励数值
 * @property int $reward_status 奖励状态：0.未发放，1.发放中，2.已发放，3.发放失败；
 * @property string $note 备注
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class TaskHandle extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%task_handle}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['engine_result_id', 'type', 'reward_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['value'], 'string', 'max' => 32],
            [['note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'engine_result_id' => '检查结果id',
            'type' => '奖励类型：0.现金；1.优惠券；2.积分；',
            'value' => '奖励数值',
            'reward_status' => '奖励状态：0.未发放，1.发放中，2.已发放，3.发放失败；',
            'note' => '备注',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
