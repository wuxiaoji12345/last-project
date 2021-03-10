<?php
/**
 * 重跑统计任务
 */

namespace api\controllers;

use api\models\Replan;
use api\models\share\ChannelSub;
use api\models\share\OrganizationRelation;
use api\models\StatisticalItem;
use api\models\Tools;
use api\models\User;
use api\models\Standard;
use Yii;

class ReplanController extends BaseApi
{
    const ACCESS_ANY = [
        'sub-channel-list'
    ];

    /**
     * 重跑计划列表查询
     * @return array
     */
    public function actionList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['page', 'page_size'])) {
            return $this->error();
        }
        $query = Replan::searchQuery($searchForm);
        $query->orderBy(['created_at' => SORT_DESC]);
        $data = $query->page($searchForm)->asArray()->all();
        $sta_id = array_column($data, 'statistical_id');
        $tool_id = array_column($data, 'tool_id');
        $bu = OrganizationRelation::companyBu();
        $tools = Tools::findAllArray(['id' => $tool_id], ['*'], 'id');
        $statistic = StatisticalItem::findAllArray(['id' => $sta_id], ['*'], 'id');
        $standard_ids = array_column($data, 'standard_id');
        $standards = Standard::findAllArray(['id'=> $standard_ids], ['id', 'title'], 'id');
        foreach ($data as &$datum) {
            // bu 字段要处理下
            $tmp_bu = explode(',', $datum['filter_company_bu']);
            foreach ($tmp_bu as $company_bu) {
                $datum['bu_name'][] = isset($bu[$company_bu]) ? $bu[$company_bu] : User::COMPANY_CODE_ALL_LABEL;
            }
            $datum['tool_name'] = $tools[$datum['tool_id']]['name'];
            $datum['statistical_name'] = $statistic[$datum['statistical_id']]['title'];
            // 检查范围，如果设置了检查项目id，显示检查项目名称
            $datum['check_scope_label'] = Replan::CHECK_LABEL_ARR[$datum['check_scope']];
            if($datum['standard_id'] != 0){
                $datum['check_scope_label'] = isset($standards[$datum['standard_id']])?$standards[$datum['standard_id']]['title']:'';
            }
            $datum['create_time'] = date('Y-m-d H:i:s', $datum['created_at']);
        }
        $count = $query->count();

        return $this->success(['count' => $count, 'list' => $data]);
    }

    /**
     * 创建重跑统计计划
     */
    public function actionCreate()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['start_time', 'end_time', 'statistical_id', 'tool_id', 'check_scope', 'company_bu'])) {
            return $this->error();
        }

        $model = new Replan();
        $searchForm['filter_company_bu'] = implode(',', $searchForm['company_bu']);
        // 如果次渠道为空，代表全部
        if (empty($searchForm['sub_channel_code'])) {
            $sub = ChannelSub::findAllArray([], ['id', 'code']);
            $sub_all = array_column($sub, 'code');
            $searchForm['sub_channel_code'] = $sub_all;
        }
        $model->load($searchForm, '');
        // 数据总数字段
        $query = Replan::findSurvey($searchForm);
        $model->total_number = (int)$query->count();
        if ($model->save()) {
            // 创建成功，关联的走访数据，由脚本来生成
            $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'replan_create_list');
            Yii::$app->remq->enqueue($QueueName, $model->id);

            return $this->success(['id' => $model->id]);
        } else {
            $err = $model->getErrStr();
            return $this->error($err, -1);
        }
    }

    /**
     * 根据条件搜索出符合条件范围的走访数量
     */
    public function actionMatchCount()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['start_time', 'end_time', 'statistical_id', 'tool_id', 'check_scope', 'company_bu'])) {
            return $this->error();
        }
        $query = Replan::findSurvey($searchForm);
        $count = (int)$query->count();
        return $this->success(['count' => $count]);
    }

    /**
     * 次渠道下拉列表
     * @return array
     */
    public function actionSubChannelList()
    {
        $data = ChannelSub::findAllArray([], ['code', 'name']);
        return $this->success(['list' => $data]);
    }
}
