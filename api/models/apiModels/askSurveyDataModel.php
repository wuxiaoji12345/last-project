<?php

namespace api\models\apiModels;


/**
 * 获取走访号及门店配置信息 接口的入参
 * Class askSurveyDataModel
 * @package api\models\apiModels
 */

class askSurveyDataModel extends apiBaseModel
{
    public $tool_id;
    public $store_id_list;
    public $callback_url;
    public $task_id;
    public $date;

    public function rules()
    {
        return [
            [['tool_id', 'store_id_list', 'callback_url', 'task_id', 'date'], 'required'],
            ['callback_url', 'string'],
            ['callback_url', 'url'],
            [['store_id_list'], 'arrayCountLimit', 'params'=> ['max'=> 1000]]
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id'=> '执行工具id',
            'store_id_list'=> '售点列表',
            'callback_url'=> '回调地址',
            'task_id'=> '批次号',
            'date'=> '日期',
        ];
    }
}