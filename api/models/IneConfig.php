<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%ine_config}}".
 *
 * @property string $id 主键id
 * @property int $ine_channel_id ine渠道id
 * @property string $p_id 父级id
 * @property int $channel_id 渠道id
 * @property string $group 展示分组
 * @property string $group_title 展示分组名称
 * @property string $title 指标项标题
 * @property int $output_type 输出项类型 0待定 1 数值型 2布尔型 3百分比
 * @property int $level 层级，1-4
 * @property int $report_examer 检查员报表是否展示 ，0不展示 ，1展示不
 * @property int $report_admin 高管报表是否展示  0不展示 ，1展示
 * @property int $rule_output_id 检查项目输出项id
 * @property int $node_index 引擎输出项id
 * @property string $max_score 满分
 * @property int $sort 排序,值小在前
 * @property int $sub_level 子级层级，如果2级项此字段为4，代表页面展示时，2级下面直接展示4级指标项
 * @property int $display 是否展示 0不展示 ，1展示 ，详情直接不展示
 * @property int $tree_display 详情树形结构下，是否展示（如果不展示 ，会直接展示下一级），0不展示 ，1展示
 * @property int $display_style 详情展示样式 0tab展示, 1分组展示4组
 * @property string $note 备注
 * @property int $update_user 更新用户id
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class IneConfig extends baseModel
{
    /**
     * 层级，1-4
     */
    const SUBDIVISION_LEVEL_ONE = 1;
    const SUBDIVISION_LEVEL_TWO = 2;
    const SUBDIVISION_LEVEL_THREE = 3;
    const SUBDIVISION_LEVEL_FOUR = 4;

    /**
     * 是否展示 0不展示 ，1展示
     */
    const DISPLAY_YES = 1;
    const DISPLAY_NO = 0;

    /**
     * INE细分项类型 1：数值 2：布尔 3：百分比
     */
    const OUTPUT_TYPE_DEFAULT = 0;
    const OUTPUT_TYPE_NUMBER = 1;
    const OUTPUT_TYPE_BOOL = 2;
    const OUTPUT_TYPE_OTHER = 3;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%ine_config}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ine_channel_id', 'p_id', 'channel_id', 'output_type', 'level', 'report_examer', 'report_admin', 'rule_output_id', 'node_index', 'sort', 'sub_level', 'display', 'tree_display', 'display_style', 'update_user', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['group'], 'string', 'max' => 10],
            [['group_title', 'note'], 'string', 'max' => 255],
            [['title'], 'string', 'max' => 64],
            [['max_score'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'ine_channel_id' => 'ine渠道id',
            'p_id' => '父级id',
            'channel_id' => '渠道id',
            'group' => '展示分组',
            'group_title' => '展示分组名称',
            'title' => '指标项标题',
            'output_type' => '输出项类型 0待定 1 数值型 2布尔型 3百分比',
            'level' => '层级，1-4',
            'report_examer' => '检查员报表是否展示 ，0不展示 ，1展示不',
            'report_admin' => '高管报表是否展示  0不展示 ，1展示 ',
            'rule_output_id' => '检查项目输出项id',
            'node_index' => '引擎输出项id',
            'max_score' => '满分',
            'sort' => '排序,值小在前',
            'sub_level' => '子级层级，如果2级项此字段为4，代表页面展示时，2级下面直接展示4级指标项',
            'display' => '是否展示 0不展示 ，1展示 ，详情直接不展示',
            'tree_display' => '详情树形结构下，是否展示（如果不展示 ，会直接展示下一级），0不展示 ，1展示',
            'display_style' => '详情展示样式 0tab展示, 1分组展示4组',
            'note' => '备注',
            'update_user' => '更新用户id',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
