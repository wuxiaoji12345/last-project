<?php


namespace api\models;


use yii\redis\ActiveRecord;

class ProgressRedis extends ActiveRecord
{
    /**
     * 主键
     *
     * @return string[]
     */
    public static function primaryKey()
    {
        return ['search_task_id'];
    }

    /**
     * 模型对应记录的属性列表
     *
     * @return array|string[]
     */
    public function attributes()
    {
        return ['search_task_id', 'count', 'progress', 'data'];
    }

}