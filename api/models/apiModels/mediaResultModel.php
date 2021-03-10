<?php


namespace api\models\apiModels;

/**
 * 上传照片及问卷内容验证model
 * Class transferImageStoreModel
 * @package api\models\apiModels
 */
class mediaResultModel extends apiBaseModel
{
    public $tool_id;
    public $survey_id;
    public $store_id;
    public $scene_code;
    public $scene_type;
    public $plan_id;
    public $result;
    public $photo_url;
    public $result_img;
    public $scene_id_name;
    public $scene_id;
    public $survey_status;
//    public $examiner;
//    public $examiner_id;
    public $survey_time;

//    public $survey_date;


    public function rules()
    {
        return [
            [['tool_id', 'survey_id', 'store_id', 'survey_time', 'scene_code', 'result_img', 'photo_url', 'survey_status', 'scene_id', 'scene_id_name', 'scene_type'], 'required'],
            [['plan_id', 'scene_type','survey_status'], 'integer'],
            [['tool_id', 'survey_id'], 'string', 'max' => 100],
            [['scene_code'], 'string', 'max' => 100],
            [['scene_id'], 'string', 'max' => 50],
            [['survey_time'], 'string', 'max' => 20],
            [['scene_id_name'], 'string', 'max' => 50],
            [['store_id'], 'string', 'max' => 50],
            ['survey_status', 'in', 'range' => [0, 1]],
//            [['photo_url'], 'string', 'max' => 5000],
            [['result_img'], 'string', 'max' => 200],
            [['survey_time'], 'safe'],
            [['result'], 'safe'],
//            ['survey_time', 'type', 'datetime'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'store_id' => '售点编号',
            'scene_code' => '提交场景类型',
            'scene_type' => '图像识别返回的场景类型',
            'plan_id' => '计划id',
            'result' => '图像识别结果',
            'photo_url' => 'Media端图片原URL地址',
            'result_img' => 'Media端识别结果URL地址',
            'scene_id_name' => '场景唯一名称',
            'scene_id' => '场景唯一id',
            'survey_status' => '走访状态',
//            'examiner' => '检查人',
//            'examiner_id' => '检查人编号',
            'survey_time' => '实际走访时间',
//            'survey_date' => '走访日期'
        ];
    }
}