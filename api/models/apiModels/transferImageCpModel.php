<?php


namespace api\models\apiModels;

/**
 * Cp上传照片及问卷内容验证model
 * Class transferImageStoreModel
 * @package api\models\apiModels
 */
class transferImageCpModel extends apiBaseModel
{
    public $tool_id;
    public $survey_id;
    public $images;
    public $plan_id;
    public $store_id;
    public $sub_activity_id;
    public $survey_time;
    public $survey_date;
    public $token;

    public function rules()
    {
        return [
            [['tool_id', 'survey_id', 'store_id', 'sub_activity_id', 'survey_time', 'survey_date'], 'required'],
            [['token'], 'safe'],
            [['images'], 'safe'],
            [['tool_id', 'sub_activity_id', 'plan_id'], 'integer'],
            [['survey_id'], 'string', 'max' => 100],
            [['store_id'], 'string', 'max' => 50],
            [['token'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'images' => '图片列表',
            'plan_id' => '计划id',
            'store_id' => '售点id',
            'sub_activity_id' => '子活动id',
            'token' => 'token验证',
            'survey_time' => '实际走访时间',
            'survey_date' => '走访日期'
        ];
    }
}