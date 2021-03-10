<?php
// SEA

namespace api\service\tools;

use api\models\apiModels\storeCheckDataModel;
use api\models\share\Store;
use api\models\Tools;

class SEA extends Tools
{
    /**
     * 获取数据
     * @param $model storeCheckDataModel
     * @return array
     */
    public static function getStoreCheckData(storeCheckDataModel $model)
    {
        $store = Store::findOne(['store_id' => $model->store_id]);
        if ($store == null) {
            return [];
        }
        $data = ['all_question' => [], 'all_scenes' => []];
        $query = $store->getCheckQuestionDataQuery($model->start_date, $model->task_id, $model->tool_id);
        $data['all_question'] = $query->all();
        foreach ($data['all_question'] as &$datum) {
            $datum['question_options'] = json_decode($datum['question_options']);
        }
        $query = $store->getCheckSceneDataQuery($model->start_date, $model->task_id, $model->tool_id);
        $data['all_scenes'] = $query->all();
        return [$data];
    }
}