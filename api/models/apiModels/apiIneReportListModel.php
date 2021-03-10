<?php


namespace api\models\apiModels;

/**
 * Class apiIneReportListModel
 * @property array survey_list 走访号列表
 * @property string store_id 售点id
 * @property int channel_id 渠道id
 * @property string start_date 开始时间
 * @property string end_date 结束时间
 * @package api\models\apiModels
 */
class apiIneReportListModel extends apiBaseModel
{
    public $survey_list;
    public $store_id;
    public $channel_id;
    public $start_date;
    public $end_date;

    public function rules()
    {
        return [
            [['survey_list'], 'required'],
            [['store_id', 'channel_id', 'start_date', 'end_date'], 'safe']
        ];
    }

    public function attributeLabels()
    {
        return [
            'survey_list' => '走访号列表',
            'store_id' => '售点id',
            'channel_id' => '渠道id',
            'start_date' => '开始时间',
            'end_date' => '结束时间',
        ];
    }

}