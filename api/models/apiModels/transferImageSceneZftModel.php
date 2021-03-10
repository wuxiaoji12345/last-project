<?php


namespace api\models\apiModels;

/**
 * ZFT上传照片及问卷内容验证model
 * Class transferImageStoreModel
 * @package api\models\apiModels
 */
class transferImageSceneZftModel extends apiBaseModel
{
    public $tool_id;
    public $survey_id;
    public $scene_code;
    public $scene_id;
    public $scene_id_name;
    public $standard_id;
    public $images;
    public $sub_activity_id;
    public $store_id;
    public $questionnaires;
//    public $examiner;
//    public $examiner_id;
//    public $survey_time;

//    public $survey_date;

    public function rules()
    {
        return [
            [['tool_id', 'survey_id', 'scene_code', 'scene_id',
                'store_id', 'standard_id'], 'required'],
            [['tool_id'], 'integer'],
            [['images'], 'safe'],
            [['questionnaires'], 'safe'],
            [['survey_id'], 'string', 'max' => 100],
            [['scene_code'], 'string', 'max' => 100],
            [['scene_id', 'store_id', 'scene_id_name'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'scene_code' => '工具端场景类型',
            'scene_id' => '工具端场景id',
            'scene_id_name' => '工具端场景名称',
            'images' => '图片列表',
//            'img_type' => '图片类型',
            'standard_id' => '检查项目id',
            'store_id' => '售点id',
            'sub_activity_id' => '子活动ID',
            'questionnaires' => '问卷列表',
        ];
    }
}