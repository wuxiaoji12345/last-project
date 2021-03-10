<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%survey_sub_activity}}".
 *
 * @property string $id
 * @property string $survey_code 走访号
 * @property int $sub_activity_id 子活动表ID
 * @property int $standard_id 成功图像标准id
 * @property int $activation_id 生动化编号
 * @property string $activation_name 生动化名称
 * @property array $scenes_type_id 主场景id组
 * @property array $scenes_code 次场景code组
 * @property string $question_manual_ir IR问卷组
 * @property string $question_manual 非IR问卷组
 * @property string $image 标准示例图片
 * @property string $describe 描述
 * @property string $is_standard_disable 是否是禁用的检查计划 0不是 1是禁用的（用于区分两种快照）
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SurveySubActivity extends baseModel
{
    const IS_STANDARD_DISABLE_YES = 1;       //是禁用状态的检查项目
    const IS_STANDARD_DISABLE_NO = 0;      //不是

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey_sub_activity}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sub_activity_id', 'standard_id', 'activation_id', 'status', 'created_at', 'updated_at', 'is_standard_disable'], 'integer'],
            [['scenes_type_id', 'scenes_code', 'question_manual_ir', 'question_manual', 'image', 'created_at', 'updated_at'], 'required'],
            [['scenes_type_id', 'scenes_code', 'update_time'], 'safe'],
            [['question_manual_ir', 'question_manual', 'image', 'describe'], 'string'],
            [['survey_code'], 'string', 'max' => 100],
            [['activation_name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'survey_code' => '走访号',
            'sub_activity_id' => '子活动表ID',
            'standard_id' => '成功图像标准id',
            'activation_id' => '生动化编号',
            'activation_name' => '生动化名称',
            'scenes_type_id' => '主场景id组',
            'scenes_code' => '次场景code组',
            'question_manual_ir' => 'IR问卷组',
            'question_manual' => '非IR问卷组',
            'image' => '标准示例图片',
            'describe' => '描述',
            'is_standard_disable' => '是否是禁用的检查计划 0不是 1是禁用的（用于区分两种快照）',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 保存禁用规则引擎时的子活动快照
     * @param $value
     * @param $standard_id
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveSubSnapshot($value, $standard_id)
    {
        //首先要删除上一次的存储
        self::deleteAll(['standard_id' => $standard_id, 'is_standard_disable' => self::IS_STANDARD_DISABLE_YES]);
        $key = ['standard_id', 'activation_id', 'activation_name', 'scenes_type_id', 'scenes_code', 'question_manual_ir', 'question_manual', 'image', 'describe', 'status', 'created_at', 'updated_at', 'update_time', 'is_standard_disable', 'sub_activity_id'];
        $model = \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '子活动快照存储失败，请检查'];
        }
    }
}