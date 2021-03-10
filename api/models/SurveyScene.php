<?php

namespace api\models;

/**
 * This is the model class for table "sys_survey_scene".
 *
 * @property int $id 主键id
 * @property int $tool_id 执行工具id
 * @property int $survey_id 走访号
 * @property string $scene_code 场景id
 * @property int $scene_id 工具端场景id
 * @property string $scene_id_name 工具端场景名称
 * @property string $asset_name 设备名称
 * @property string $asset_code 资产编码
 * @property string $asset_type 设备型号
 * @property string $remark_1 扩展字段
 * @property string $remark_2 扩展字段
 * @property string $remark_3 扩展字段
 * @property string $remark_4 扩展字段
 * @property string $remark_5 扩展字段
 * @property string $remark_6 扩展字段
 * @property string $remark_7 扩展字段
 * @property string $remark_8 扩展字段
 * @property string $remark_9 扩展字段
 * @property string $remark_10 扩展字段
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SurveyScene extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_survey_scene';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tool_id',  'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['scene_id'], 'safe'],
            [['scene_code', 'asset_name', 'asset_code', 'asset_type', 'remark_1', 'remark_2', 'remark_3', 'remark_4', 'remark_5', 'remark_6', 'remark_7', 'remark_8', 'remark_9', 'remark_10'], 'string', 'max' => 100],
            [['scene_id_name'], 'string', 'max' => 50],
            [['survey_id'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'scene_code' => '场景id',
            'scene_id' => '工具端场景id',
            'scene_id_name' => '工具端场景名称',
            'asset_name' => '设备名称',
            'asset_code' => '资产编码',
            'asset_type' => '设备型号',
            'remark_1' => '扩展字段',
            'remark_2' => '扩展字段',
            'remark_3' => '扩展字段',
            'remark_4' => '扩展字段',
            'remark_5' => '扩展字段',
            'remark_6' => '扩展字段',
            'remark_7' => '扩展字段',
            'remark_8' => '扩展字段',
            'remark_9' => '扩展字段',
            'remark_10' => '扩展字段',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 拍照场景信息入库
     * @param $param
     * @return array
     */
    public static function saveSurveyScene($param)
    {
        $model = self::findOne(['survey_id' => $param['survey_id'],'scene_id' => $param['scene_id']]);
        if(!$model){
            $model = new self();
        }
        $model->load($param, '');
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false,$model->getErrors()];
        }
    }
}
