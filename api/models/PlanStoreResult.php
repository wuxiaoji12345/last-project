<?php

namespace api\models;


/**
 * This is the model class for table "{{%plan_store_result}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property int $plan_id 检查计划id
 * @property int $plan_time 检查次数
 * @property int $fail_time 不合格次数
 * @property int $shop_id 售点id
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class PlanStoreResult extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_store_result}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'created_at', 'updated_at'], 'required'],
            [['project_id', 'plan_id', 'plan_time', 'fail_time', 'shop_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
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
            'plan_id' => '检查计划id',
            'plan_time' => '检查次数',
            'fail_time' => '不合格次数',
            'shop_id' => '售点id',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
