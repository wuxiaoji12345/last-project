<?php


namespace api\controllers;

use api\models\ImageSimilarity;
use api\models\share\Store;
use api\models\share\StoreBelong;
use api\models\Standard;
use Yii;

class SimilarController extends BaseApi
{
    const ACCESS_ANY = [
        'cause-list',
        'next-id'
    ];

    const SIMILAR_CAUSE = [
        1 => '不同线路，不同售点，不同时间',
        2 => '不同线路，不同售点，相同时间',
        3 => '相同线路，不同售点，不同时间',
        4 => '相同线路，不同售点，相同时间',
        5 => '相同线路，相同售点，不同时间',
		6 => '相同线路，相同售点，相同时间'
    ];

    public function actionCauseList()
    {
        return $this->success([
            /*
            暂时不显示不同线路
            ["causename" => '不同线路，不同售点，不同时间', "value" => 1],
            ["causename" => '不同线路，不同售点，相同时间', "value" => 2],
            */
            ["causename" => '相同线路，不同售点，不同时间', "value" => 3],
            ["causename" => '相同线路，不同售点，相同时间', "value" => 4],
            ["causename" => '相同线路，相同售点，不同时间', "value" => 5]
        ]);
    }

    public function actionList()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['page', 'page_size'])) {
            return $this->error();
        }
        $params['start_time'] = $params['start_time'] ? $params['start_time'] . ' 00:00:00' : '';
        $params['end_time'] = $params['end_time'] ? $params['end_time'] . ' 23:59:59' : '';
        $where_data = [
            [['start_time' => 's.survey_time'], '>='],
            [['end_time' => 's.survey_time'], '<='],
            [
                [
                    'route_code' => 's.route_code',
                    'location_code' => 's.location_code',
                ], 'in'
            ],
            [
                [
                    'tool_id' => 's.tool_id',
                    'survey_code' => 's.survey_code',
                    'sub_channel_id' => 's.sub_channel_id',
                    'standard_id' => 'img.standard_id',
                    'similarity_cause' => 'sim.similarity_cause',
                    'store_id' => 's.store_id'
                ], '='
            ],
            [
                [
                    'store_id' => 's.store_id',
                    'supervisor_name' => 's.supervisor_name',
                    'region_code' => 's.region_code',
                ], 'like'
            ],
        ];
        $where = $this->makeWhere($where_data, $params);
        $where[] = ['>', 'sim.similarity_cause', 2]; //暂时不显示不同线路
        $data = ImageSimilarity::getList($where, $params['page'] - 1, $params['page_size']);
        //售点信息
        $store_id_list = array_column($data['list'], 'store_id');
        $similarity_store_id_list = array_column($data['list'], 'similarity_store_id');
        $store_ids = array_unique(array_merge($store_id_list, $similarity_store_id_list));
        $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_ids], true, true, '', 'store_id');
        //检查项目
        $standard_id_list = array_column($data['list'], 'standard_id');
        $similarity_standard_id = array_column($data['list'], 'similarity_standard_id');
        $standard_ids = array_unique(array_merge($standard_id_list, $similarity_standard_id));
        $standard_list = Standard::findJoin('', [], ['id standard_id', 'title standard_name'], ['in', 'id', $standard_ids], true, true, '', 'standard_id');

        foreach ($data['list'] as &$v) {
            $v['store_name'] = $store_list[$v['store_id']]['store_name'];
            $v['similarity_store_name'] = $store_list[$v['similarity_store_id']]['store_name'];
            $v['standard_name'] = $standard_list[$v['standard_id']]['standard_name'] ?? '';
            $v['similarity_standard_name'] = $standard_list[$v['similarity_standard_id']]['standard_name'] ?? '';
            $v['similarity_cause'] = self::SIMILAR_CAUSE[$v['similarity_cause']];
        }

        if (isset($data)) {
            return $this->success($data);
        } else {
            return $this->error("查询异常，请检查");
        }
    }

    public function actionReport()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['id'])) {
            return $this->error();
        }
        $where = [];
        $where[] = 'and';
        $where[] = ['>', 'sim.similarity_cause', 2];//暂时不显示不同线路
        $where[] = ['sim.similarity_status' => 1];
        $order = '';
        if (isset($params['model'])) {
            if ($params['model'] == 'last') {
                $where[] = ['>', 'sim.id', $params['id']];
            } elseif ($params['model'] == 'next') {
                $where[] = ['<', 'sim.id', $params['id']];
                $order = 'sim.created_at DESC';
            } else {
                $where[] = ['sim.id' => $params['id']];
            }
        }

        $data = ImageSimilarity::getOneByID($where,$order);
        if (isset($data)) {
            //售点信息
            $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', [$data['store_id'], $data['similarity_store_id']]], true, true, '', 'store_id');
            //检查项目
            $standard_list = Standard::findJoin('', [], ['id standard_id', 'title standard_name'], ['in', 'id', [$data['standard_id'], $data['similarity_standard_id']]], true, true, '', 'standard_id');

            $data['store_name'] = $store_list[$data['store_id']]['store_name'] ?? '';
            $data['similarity_store_name'] = $store_list[$data['similarity_store_id']]['store_name'] ?? '';
            $data['standard_name'] = $standard_list[$data['standard_id']]['standard_name'] ?? '';
            $data['similarity_standard_name'] = $standard_list[$data['similarity_standard_id']]['standard_name'] ?? '';
            $data['similarity_cause'] = self::SIMILAR_CAUSE[$data['similarity_cause']];

            return $this->success($data);
        } else {
            return $this->error("没有数据了");
        }

    }

    /**
     * 下一条相似图id
     * @return array
     */
    public function actionNextId(){
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost()) {
            return $this->error();
        }
        $params['start_time'] = $params['start_time'] ? $params['start_time'] . ' 00:00:00' : '';
        $params['end_time'] = $params['end_time'] ? $params['end_time'] . ' 23:59:59' : '';
        $where_data = [
            [['start_time' => 's.survey_time'], '>='],
            [['end_time' => 's.survey_time'], '<='],
            [['route_code' => 's.route_code'], 'in'],
            [
                [
                    'tool_id' => 's.tool_id',
                    'survey_code' => 's.survey_code',
                    'sub_channel_id' => 's.sub_channel_id',
                    'standard_id' => 'img.standard_id',
                    'similarity_cause' => 'sim.similarity_cause',
                    'store_id' => 's.store_id'
                ], '='
            ],
            [
                [
                    'store_id' => 's.store_id',
                    'location_code' => 's.location_name',
                    'route_code' => 's.route_code',
                    'supervisor_name' => 's.supervisor_name',
                    'region_code' => 's.region_code',
                ], 'like'
            ],
        ];
        $where = $this->makeWhere($where_data, $params);
        $where[] = ['>', 'sim.similarity_cause', 2]; //暂时不显示不同线路
        $data = ImageSimilarity::getList($where, $params['offset'], 1);
        //售点信息
        $store_id_list = array_column($data['list'], 'store_id');
        $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_id_list], true, true, '', 'store_id');
        //相似图售点信息
        $similarity_store_id_list = array_column($data['list'], 'similarity_store_id');
        $similarity_store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $similarity_store_id_list], true, true, '', 'store_id');
        //检查项目
        $standard_id_list = array_column($data['list'], 'standard_id');
        $standard_list = Standard::findJoin('', [], ['id standard_id', 'title standard_name'], ['in', 'id', $standard_id_list], true, true, '', 'standard_id');
        //相似图检查项目
        $similarity_standard_id_list = array_column($data['list'], 'similarity_standard_id');
        $similarity_standard_list = Standard::findJoin('', [], ['id standard_id', 'title standard_name'], ['in', 'id', $similarity_standard_id_list], true, true, '', 'standard_id');

        foreach ($data['list'] as &$v) {
            $v['store_name'] = $store_list[$v['store_id']]['store_name'];
//            $v['image_url'] = Yii::$app->params['cos_url'] . $v['image_key'];
            $v['similarity_store_name'] = $similarity_store_list[$v['similarity_store_id']]['store_name'];
//            $v['similarity_image_url'] = Yii::$app->params['cos_url'] . $v['similarity_image_key'];
            $v['standard_name'] = $standard_list[$v['standard_id']]['standard_name'] ?? '';
            $v['similarity_standard_name'] = $similarity_standard_list[$v['similarity_standard_id']]['standard_name'] ?? '';
            $v['similarity_cause'] = self::SIMILAR_CAUSE[$v['similarity_cause']];
        }

        if (isset($data)) {
            return $this->success($data['list']);
        } else {
            return $this->error("查询异常，请检查");
        }
    }
}