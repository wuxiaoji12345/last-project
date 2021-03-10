<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%snapshot_rule_output_info}}".
 *
 * @property int $id
 * @property int $rule_output_info_id 规则引擎输出项表id
 * @property int $standard_id 标准id
 * @property int $statistical_id 统计项目id
 * @property int $node_index 规则引擎id
 * @property string $node_name 输出项名称
 * @property int $output_type 输出项类型 0待定 1 数值型 2布尔型 
 * @property int $sub_activity_id 子活动id
 * @property int $is_all_scene 是否全场景 0 否 1 是
 * @property string $scene_type 输出项场景类型
 * @property string $scene_code 输出项场景code
 * @property int $is_main 是否主输出项 0 否 1 是
 * @property int $is_score 是否得分项 0 否 1是
 * @property int $is_vividness 是否是生动化项 0否 1是
 * @property int $sort_id 大中台排序id
 * @property int $tag 最新变动状态 0正常 1最近新增 2最近删除
 * @property string $formats 输出项格式化参数
 * @property int $standard_status 输出项删除时规则的状态 0未启用 1已启用
 * @property int $status 删除状态 0删除 1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SnapshotRuleOutputInfo extends \api\models\baseModel
{
    const STANDARD_START_YES = 1;
    const STANDARD_START_NO = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%snapshot_rule_output_info}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['rule_output_info_id', 'standard_id', 'statistical_id', 'node_index', 'output_type', 'sub_activity_id', 'is_all_scene', 'is_main', 'is_score', 'is_vividness', 'sort_id', 'tag', 'standard_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['node_index', 'node_name', 'created_at', 'updated_at'], 'required'],
            [['node_name'], 'string'],
            [['update_time'], 'safe'],
            [['scene_type', 'formats'], 'string', 'max' => 255],
            [['scene_code'], 'string', 'max' => 1000],
            [['standard_id', 'statistical_id', 'node_index', 'sub_activity_id'], 'unique', 'targetAttribute' => ['standard_id', 'statistical_id', 'node_index', 'sub_activity_id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'rule_output_info_id' => '规则引擎输出项表id',
            'standard_id' => '标准id',
            'statistical_id' => '统计项目id',
            'node_index' => '规则引擎id',
            'node_name' => '输出项名称',
            'output_type' => '输出项类型 0待定 1 数值型 2布尔型 ',
            'sub_activity_id' => '子活动id',
            'is_all_scene' => '是否全场景 0 否 1 是',
            'scene_type' => '输出项场景类型',
            'scene_code' => '输出项场景code',
            'is_main' => '是否主输出项 0 否 1 是',
            'is_score' => '是否得分项 0 否 1是',
            'is_vividness' => '是否是生动化项 0否 1是',
            'sort_id' => '大中台排序id',
            'tag' => '最新变动状态 0正常 1最近新增 2最近删除',
            'formats' => '输出项格式化参数',
            'standard_status' => '输出项删除时规则的状态 0未启用 1已启用',
            'status' => '删除状态 0删除 1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 保存禁用规则引擎时的输出项快照
     * @param $value
     * @param $standard_id
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveOutputSnapshot($value, $standard_id)
    {
        //首先要删除上一次的存储
        self::deleteAll(['standard_id' => $standard_id]);
        $key = ['standard_id', 'statistical_id', 'node_index', 'node_name', 'output_type', 'sub_activity_id',
            'is_all_scene', 'scene_type', 'scene_code', 'is_main', 'is_score', 'is_vividness',
            'sort_id', 'tag', 'formats', 'standard_status', 'status', 'created_at', 'updated_at', 'update_time', 'rule_output_info_id'];
        $model = \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '输出项快照存储失败，请检查'];
        }
    }
}
