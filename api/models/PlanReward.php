<?php

namespace api\models;


/**
 * This is the model class for table "{{%plan_reward}}".
 *
 * @property int $id 主键id
 * @property int $plan_id 检查计划id
 * @property int $scene_index 检查项目配置的场景索引
 * @property int $sub_activity_id 子活动id
 * @property int $scene_type 场景类型
 * @property string $scene_code 场景code
 * @property string $reward_amount 奖励金额
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class PlanReward extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_reward}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['plan_id', 'scene_type'], 'required'],
            [['plan_id', 'scene_index', 'sub_activity_id', 'scene_type', 'status', 'created_at', 'updated_at'], 'integer'],
            [['reward_amount'], 'number'],
            [['update_time'], 'safe'],
            [['scene_code'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'plan_id' => '检查计划id',
            'sub_activity_id' => '子活动id',
            'scene_index' => '检查项目配置的场景索引',
            'scene_type' => '场景类型',
            'scene_code' => '场景code',
            'reward_amount' => '奖励金额',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
