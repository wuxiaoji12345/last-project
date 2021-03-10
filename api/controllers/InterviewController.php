<?php


namespace api\controllers;

use api\models\EngineResult;
use api\models\IneConfigSnapshot;
use api\models\RuleOutputInfo;
use api\models\share\ChannelMain;
use api\models\IneConfig;
use api\models\share\ChannelSub;
use api\models\Survey;
use api\service\ine\IneConfigService;
use api\service\ine\InterviewService;
use common\libs\engine\Format;
use api\models\Tools;
use Yii;
use yii\db\Expression;

class InterviewController extends BaseApi
{
    const ACCESS_ANY = [
        'ine-channel',
    ];

    /**
     * 获取走访列表
     * @return array
     */
    public function actionList()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['page', 'page_size'])) {
            return $this->error();
        }
        $where_data = [
            [['survey_time_start' => 's.survey_time'], '>='],
            [['survey_time_end' => 's.survey_time'], '<='],
            [['tool_id' => 's.tool_id', 'survey_code' => 's.survey_code', 'is_inventory' => 's.is_inventory','channel_id' => 'c.channel_id'], '='],
            [['store_id' => 's.store_id'], 'like'],
        ];
        $where = $this->makeWhere($where_data, $params);
        $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
        $where[] = ['>', 'i.ine_channel_id', 0];//过滤掉没有ine渠道的数据
        Survey::setBu($where);
        Survey::setUserInfo($where);
        $data = Survey::getInterview($where, $params['page'], $params['page_size']);

        if (isset($data)) {
            return $this->success($data);
        } else {
            return $this->error("查询异常，请检查");
        }
    }

    /**
     * 售点详情
     * @return array
     */
    public function actionDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['survey_code', 'ine_channel_id'])) {
            return $this->error();
        }
        $where[] = 'and';
        $where[] = ['=', 's.survey_code', $params['survey_code']];
        $where[] = ['=', 'i.ine_channel_id', $params['ine_channel_id']];
        $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
        $select = ['s.id', 's.survey_code', 's.examiner', 's.survey_time', 's.store_id', 's.store_name', 's.store_address', 's.sub_channel_id', 'c.year',
            'r.ine_total_points', 's.is_inventory', 's.is_ine', 'c.channel_name'];
        Survey::setUserInfo($where);
        $data = Survey::getInterview($where, 1, 1, $select);
        if (isset($data['list'][0])) {
            return $this->success($data['list'][0]);
        } else {
            return $this->error("查询异常，请检查");
        }
    }

    /**
     * 获取评分情况
     */
    public function actionScoreList()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['survey_code','ine_channel_id'])) {
            return $this->error();
        }
        $survey_code = $params['survey_code'];
        $ine_channel_id = $params['ine_channel_id'];
       $snapshots = IneConfigSnapshot::find()->alias('s')
            ->leftJoin('sys_engine_result r', 'r.ine_config_timestamp_id=s.ine_config_timestamp_id')
            ->select(['s.id', 's.title', 's.max_score', 's.p_id', 's.level', 's.node_index', 'ine_config_id', 's.display_style', 'r.standard_id', 'r.result'])
            ->indexBy('ine_config_id')->where(['r.survey_code' => $survey_code, 's.ine_channel_id' => $ine_channel_id, 's.display' => 1])->andWhere(['<', 's.level', 4])
            ->orderBy('s.sort')->asArray()->all();
        if (empty($snapshots)) {
            return $this->error('没有对应的ine_config_snapshot配置！');
        }
        //随意取一个元素获得检查项目id,因为条件取出时引擎结果是唯一的
        $result = $snapshots;
        $engineResult = array_shift($result);
        if (empty($engineResult['result'])) {
            return $this->error('走访没有引擎结果！');
        }
        $ruleOutput = RuleOutputInfo::getResultMapByStandardId($engineResult['standard_id']);

        $tmp = [];
        if ($engineResult['result']) {
            $nodes = json_decode($engineResult['result'], true);
            foreach ($nodes as $node) {
                // 小数转换
                if (is_float($node['output'])) {
                    $node['output'] = round($node['output'], 2);
                }
                if (is_array($node['output'])) {
                    $node['output'] = json_encode($node['output'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($ruleOutput[$node['node_index']])) {
                    $node_index = $node['node_index'];
                    $v = $ruleOutput[$node_index];
                    $tmp[$node_index] = $node['output'];
                    if ($v['formats'] === '') {
                        $tmp[$node_index] = $node['output'];
                    } else {
                        $tmp[$node_index] = Format::outputFormat($node['output'], json_decode($v['formats'], true));
                    }
                    if ($node['output'] === false) {
                        $tmp[$node_index] = '不合格';
                    }
                    if ($node['output'] === true) {
                        $tmp[$node_index] = '合格';
                    }
                }
            }
        }
        $outputs = array_combine(array_column($nodes, 'node_index'), array_column($nodes, 'output'));

        foreach ($snapshots as &$snapshot) {
            $snapshot['ine_score'] = isset($outputs[$snapshot['node_index']]) ? $outputs[$snapshot['node_index']] : '';
            $snapshot['output'] = isset($tmp[$snapshot['node_index']]) ? $tmp[$snapshot['node_index']] : '';
        }
        foreach ($snapshots as &$snapshot) {
            if (isset($snapshots[$snapshot['p_id']]) && $snapshots[$snapshot['p_id']]['display_style'] == 0) {
                $snapshot['display_value'] = $snapshot['ine_score'] > 0 ? '是' : '否';
            }
        }
        $snapshots = IneConfigService::getTree($snapshots, 0, 'children', 'ine_config_id');
        return $this->success(['list' => $snapshots]);
    }

    /**
     * 获取评分情况
     */
    public function actionTargetList()
    {
        $params = Yii::$app->request->bodyParams;
        //增加ine渠道id的传入
        if (!$this->isPost() || !$this->validateParam($params, ['survey_code','ine_channel_id'])) {
            return $this->error();
        }
        $survey_code = $params['survey_code'];
        $ine_channel_id = $params['ine_channel_id'];
        $snapshots = IneConfigSnapshot::find()->alias('s')
            ->leftJoin('sys_engine_result r', 'r.ine_config_timestamp_id=s.ine_config_timestamp_id')
            ->select(['s.id', 's.title', 's.max_score', 's.p_id', 's.level','s.node_index', 's.display_style', 'ine_config_id', 's.display_style', 'r.standard_id', 'r.result'])
            ->indexBy('ine_config_id')->where(['r.survey_code' => $survey_code, 's.ine_channel_id' => $ine_channel_id, 's.display' => 1, 's.tree_display' => 1])
            ->orderBy('s.sort')->asArray()->all();
        //随意取一个元素获得检查项目id,因为条件取出时引擎结果是唯一的
        $result = $snapshots;
        $engineResult = array_shift($result);
        if (empty($engineResult['result'])) {
            return $this->error('走访没有引擎结果！');
        }
        $ruleOutput = RuleOutputInfo::getResultMapByStandardId($engineResult['standard_id']);

        $tmp = [];
        if ($engineResult['result']) {
            $nodes = json_decode($engineResult['result'], true);
            foreach ($nodes as $node) {
                // 小数转换
                if (is_float($node['output'])) {
                    $node['output'] = round($node['output'], 2);
                }
                if (is_array($node['output'])) {
                    $node['output'] = json_encode($node['output'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($ruleOutput[$node['node_index']])) {
                    $node_index = $node['node_index'];
                    $v = $ruleOutput[$node_index];
                    $tmp[$node_index] = $node['output'];
                    if ($v['formats'] === '') {
                        $tmp[$node_index] = $node['output'];
                    } else {
                        $tmp[$node_index] = Format::outputFormat($node['output'], json_decode($v['formats'], true));
                    }
                    if ($node['output'] === false) {
                        $tmp[$node_index] = '无';
                    }
                    if ($node['output'] === true) {
                        $tmp[$node_index] = '有';
                    }
                }
            }
        }
        $outputs = array_combine(array_column($nodes, 'node_index'), array_column($nodes, 'output'));
        foreach ($snapshots as &$snapshot) {
            $snapshot['ine_score'] = isset($outputs[$snapshot['node_index']]) ? $outputs[$snapshot['node_index']] : '';
            $snapshot['output'] = isset($tmp[$snapshot['node_index']]) ? $tmp[$snapshot['node_index']] : '';
        }
        foreach ($snapshots as &$snapshot) {
            if (isset($snapshots[$snapshot['p_id']]) && $snapshots[$snapshot['p_id']]['display_style'] == 0) {
                $snapshot['display_value'] = $snapshot['ine_score'] > 0 ? '是' : '否';
            }
        }
        $snapshots = IneConfigService::getTree($snapshots, 0, 'children', 'ine_config_id');
        return $this->success(['list' => $snapshots]);
    }

    /**
     * 历史得分图表接口
     * @return array
     */
    public function actionHistoricalScore()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->check($params, ['store_id', 'ine_channel_id', 'limit'])) {
            return $this->error();
        }

        $re = EngineResult::getHistoricalScore($params);
        //构建一个默认的时间数组，以便处理不连续月数据的情况
        for ($i = 0; $i < $params['limit']; $i++) {
            $month = $i == 0 ? time() : strtotime('-' . $i . 'month');
            $year = date('Y', $month);
            $month = (string)((int)date('m', $month));
            $index = $year . '-' . $month;
            $data[$index] = isset($re[$index]) ? $re[$index] : ['year' => $year, 'month' => $month, "avg_score" => "0", "time" => $index];
        }
        $data = array_values(array_reverse($data));
        return $this->success($data);
    }

    /**
     * 月度走访得分明细
     * @return array
     */
    public function actionHistoricalScoreInfo()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->check($params, ['store_id', 'time', 'ine_channel_id', 'page_size', 'page'])) {
            return $this->error();
        }
        $params['tool_list'] = $params['tool_list'] ?: [Tools::ALL_INE_TOOL_ID];
        $data = [];
        $alias = 's';
        $join = [
            ['type' => 'LEFT JOIN',
                'table' => EngineResult::tableName() . ' e',
                'on' => 's.survey_code = e.survey_code'],
        ];
        $select = ['s.survey_time', 's.survey_code', 's.tool_id', new Expression("CASE tool_id WHEN 1 THEN '检出员' WHEN 10 THEN '高管' END AS tool_role"),
            's.examiner', 'e.ine_total_points', 'e.result', 'e.ine_config_timestamp_id'
        ];
        $where = ['s.store_id' => $params['store_id'],
            's.ine_channel_id' => $params['ine_channel_id'],
            's.tool_id' => $params['tool_list'],
            's.is_ine' => Survey::IS_INE_YES,
            "date_format(result_time, '%Y-%m')" => $params['time'],
            's.plan_id' => 0,
        ];
        $with = [['ineConfigSnapshot' => function ($query) {
            $query->select('ine_config_timestamp_id, ine_config_id, node_index')->where(['level' => [1, 2]]);
        }]];
        $pages = [
            'page_size' => $params['page_size'],
            'page' => $params['page'] - 1,
        ];
        $list = Survey::findJoin($alias, $join, $select, $where, true, true, '', '', '', $with, $pages);
        foreach ($list['list'] as &$v) {
            $check_list = [];
            foreach (json_decode($v['result'], true) as $v1) {
                foreach ($v['ineConfigSnapshot'] as $v2) {
                    if ($v2['node_index'] == $v1['node_index']) {
                        $check_list[$v2['ine_config_id']] = $v1['output'];
                    }
                }
            }
            $v['check_list'] = $check_list;
            unset($v['ineConfigSnapshot']);
            unset($v['result']);
            unset($v['ine_config_timestamp_id']);
        }

        $map = IneConfig::findAllArray(['level' => [1, 2], 'ine_channel_id' => $params['ine_channel_id']], ['id detail_id', 'title label']);
        $data = [
            'map' => $map,
            'list' => $list['list'],
            'count' => $list['count']
        ];

        return $this->success($data);

    }

    public function actionIneChannel()
    {
        $data = ChannelMain::find()->where(['<', 'id', 7])->select(['id', 'name', 'code'])->asArray()->all();
        $data[] = [
            'id' => '999',
            'name' => '非INE渠道',
            'code' => 'NotIne'
        ];
        return $this->success(['list' => $data]);
    }

}