<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "sys_result_node".
 *
 * @property int $id 主键id
 * @property int $rule_output_node_id 检查项id
 * @property int $store_id 售点id
 * @property int $fail_count 失败次数
 * @property int $total_count 检查总次数
 * @property int $tool_id 执行工具id
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ResultNode extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_result_node';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['rule_output_node_id'], 'required'],
            [['rule_output_node_id', 'store_id', 'fail_count', 'total_count', 'status', 'created_at', 'updated_at', 'tool_id'], 'integer'],
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
            'rule_output_node_id' => '检查项id',
            'store_id' => '售点id',
            'fail_count' => '失败次数',
            'total_count' => '检查总次数',
            '$tool_id' => '执行工具id',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 更新/新增主检查项失败次数
     * @param $store_id
     * @param $rule_output_node_id
     * @param $output
     * @return array
     */
    public static function saveFailCount($tool_id,$store_id, $rule_output_node_id, $output)
    {
        $model = self::findOne(['rule_output_node_id' => $rule_output_node_id]);
        if (!$model) {
            $model = new self;
        }
        $model->store_id = $store_id;
        $model->tool_id = $tool_id;
        $model->rule_output_node_id = $rule_output_node_id;
        $model->total_count = $model->total_count + 1;
        if ($output) {
            $model->fail_count = 0;
        } else {
            $model->fail_count = $model->fail_count + 1;
        }
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false, $model->errors];
        }

    }

}
