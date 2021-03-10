<?php


namespace api\models\apiModels;

/**
 * 上传照片及问卷内容验证model
 * Class transferImageStoreModel
 * @package api\models\apiModels
 */
class transferImageStoreModel extends apiBaseModel
{
    public $tool_id;
    public $survey_id;
    public $images;
    public $questionnaires;
    public $plan_id;
    public $store_id;
    public $examiner;
    public $examiner_id;
    public $survey_time;
    public $survey_date;
    public $is_list_store;


    public function rules()
    {
        return [
            [['tool_id', 'survey_id', 'store_id', 'examiner_id', 'survey_time', 'survey_date'], 'required'],
            [['questionnaires'], 'safe'],
            [['images'], 'safe'],
            [['examiner_id', 'tool_id', 'is_list_store'], 'integer'],
            [['survey_id'], 'string', 'max' => 100],
            [['store_id'], 'string', 'max' => 50],
            [['examiner'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具id',
            'survey_id' => '走访号',
            'questionnaires' => '问卷列表',
            'images' => '图片列表',
            'plan_id' => '计划id',
            'store_id' => '售点id',
            'examiner' => '检查人',
            'examiner_id' => '检查人编号',
            'survey_time' => '实际走访时间',
            'survey_date' => '走访日期',
            'is_list_store' => '是否清单店'
        ];
    }
}