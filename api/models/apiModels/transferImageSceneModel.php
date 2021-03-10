<?php


namespace api\models\apiModels;

/**
 * 上传照片及问卷内容验证model
 * Class transferImageStoreModel
 * @package api\models\apiModels
 */
class transferImageSceneModel extends apiBaseModel
{
    public $tool_id;
    public $survey_id;
    public $scene_code;
    public $scene_id;
    public $plan_id;
    public $images;
    public $questionnaires;
    public $img_type;
    public $scene_id_name;
    public $asset_name;
    public $asset_code;
    public $asset_type;
    public $store_id;
    public $examiner;
    public $examiner_id;
    public $survey_time;
    public $survey_date;
    public $is_list_store;

    public function rules()
    {
        return [
            [['tool_id', 'survey_id', 'scene_code', 'scene_id', 'img_type', 'scene_id_name',
                'store_id', 'examiner_id', 'survey_time', 'survey_date'], 'required'],
            [['examiner_id', 'img_type', 'tool_id', 'plan_id', 'is_list_store'], 'integer'],
            [['asset_name'], 'default', 'value' => ''],
            [['asset_code'], 'default', 'value' => ''],
            [['asset_type'], 'default', 'value' => ''],
            [['questionnaires'], 'safe'],
            [['images'], 'safe'],
            [['survey_id'], 'string', 'max' => 100],
            [['scene_code'], 'string', 'max' => 100],
            [['scene_id_name'], 'string', 'max' => 50],
            [['store_id', 'scene_id'], 'string', 'max' => 50],
            [['asset_name'], 'string', 'max' => 100],
            [['asset_code'], 'string', 'max' => 100],
            [['asset_type'], 'string', 'max' => 100],
            [['examiner'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'scene_code' => '工具端场景类型',
            'scene_id' => '工具端场景id',
            'questionnaires' => '问卷列表',
            'images' => '图片列表',
            'img_type' => '图片类型',
            'plan_id' => '计划id',
            'scene_id_name' => '工具端场景名称',
            'asset_name' => '设备名称',
            'asset_code' => '资产编码',
            'asset_type' => '设备型号',
            'store_id' => '售点id',
            'examiner' => '检查人',
            'examiner_id' => '检查人编号',
            'survey_time' => '实际走访时间',
            'survey_date' => '走访日期',
            'is_list_store' => '是否清单店'
        ];
    }
}