<?php

namespace api\models\apiModels;


/**
 * 获取单个走访号及门店配置信息 接口的入参
 * Class askSurveyOneModel
 * @property $tool_id
 * @property $store_id
 * @property $date
 * @package api\models\apiModels
 */

class askSurveyOneModel extends apiBaseModel
{
    public $tool_id;
    public $store_id;
    public $date;

    public function rules()
    {
        return [
            [['tool_id', 'store_id', 'date'], 'required']
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id'=> '执行工具id',
            'store_id'=> '售点',
            'date'=> '日期',
        ];
    }
}