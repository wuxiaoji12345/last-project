<?php


namespace api\models\apiModels;

/**
 * Class apiIneChannelModel
 * @property int year 年份
 * @property int channel_id 渠道id
 * @package api\models\apiModels
 */
class apiIneChannelModel extends apiBaseModel
{
    public $year;
    public $channel_id;

    public function rules()
    {
        return [
            [['year', 'channel_id'], 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'year' => '年份',
            'channel_id' => '渠道ID',
        ];
    }

}