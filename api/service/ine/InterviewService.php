<?php


namespace api\service\ine;


use api\models\EngineResult;
use api\models\IneConfigSnapshot;
use api\models\RuleOutputInfo;
use common\libs\engine\Format;

class InterviewService
{
    private static function getIneConfig($ine_channel_id)
    {
        $where = ['and'];
        $where[] = ['=', 'ine_channel_id', $ine_channel_id];
        $where[] = ['=', 'status', 1];
        $where[] = ['!=', 'title', '其他指标'];
        $maxTimestampId = IneConfigSnapshot::find()->where($where)->max('ine_config_timestamp_id');
        $where[] = ['=', 'ine_config_timestamp_id', $maxTimestampId];
        $snapshots = IneConfigSnapshot::find()->where($where)->select(['id', 'title', 'max_score', 'ine_config_id', 'p_id', 'level', 'node_index', 'display_style'])->asArray()->all();
//        $sql = IneConfigSnapshot::find()->where($where)->select(['id', 'title', 'max_score', 'p_id', 'level','node_index', 'display_style'])->createCommand()->getRawSql();
        return $snapshots;
    }

    /**
     * 获取表头
     * @param $ine_channel_id
     * @return array
     */
    public static function getHeaders($ine_channel_id)
    {
        $headers = [
            'survey_code' => '走访号',
            'examiner' => '走访人员',
            'survey_time' => '检查时间',
            'store_id' => '售点编号',
            'store_name' => '售点名称',
            'store_address' => '地址',
            'channel_name' => 'INE渠道',
            'year' => '报告年份',
            'ine_total_points' => '得分',
        ];
        $row1 = [];
        $row2 = [];
        $row3 = [];
        $index = 0;
        foreach ($headers as $key => $val) {
            $index++;
            $row1[] = [
                'down' => 2,
                'value' => $val,
                'index' => $index,
            ];
            $headers[$key] = [
                'value' => $val,
                'index' => $index,
            ];
        }
        $children = 'children';
        $snapshots = self::getIneConfig($ine_channel_id);
        self::getCount($snapshots, 0, 'ine_config_id');
        $snapshots = IneConfigService::getTree($snapshots, 0, $children, 'ine_config_id');
        $snapshots = $snapshots[0][$children];
        foreach ($snapshots as $snapshot) {
            $index++;
            $row1[] = [
                'down' => 2,
                'value' => $snapshot['title'] . '得分',
                'index' => $index,
            ];
            $headers[$snapshot['node_index']] = [
                'value' => $snapshot['title'],
                'index' => $index,
            ];
        }
        foreach ($snapshots as &$snapshot) {
            $index2 = $index + 1;
            foreach ($snapshot[$children] as &$snapshot3) {
                $index3 = $index + 1;
                if ($snapshot3['count'] == 0) {
                    $snapshot3['count'] = 1;
                    $index++;
                } else {
                    foreach ($snapshot3[$children] as $snapshot4) {
                        $index++;
                        $row3[] = [
                            'value' => $snapshot4['title'],
                            'index' => $index,
                        ];
                        $headers[$snapshot4['node_index']] = [
                            'value' => $snapshot4['title'],
                            'index' => $index,
                        ];
                    }
                }
                $row2[] = [
                    'across' => max(0, $index - $index3),
                    'value' => $snapshot3['title'],
                    'index' => $index3,
                ];
            }
            $row1[] = [
                'across' => max(0, $index - $index2),
                'value' => $snapshot['title'],
                'index' => $index2,
            ];
        }
        return [$row1, $row2, $row3, $headers];
    }

    /**
     * 获取子节点个数
     * @param array $data
     * @param int $pid
     * @param string $id
     * @return int
     */
    public static function getCount(&$data = [], $pid = 0, $id = 'id')
    {
        $count = 0;
        foreach ($data as $k => &$v) {
            if ($v['p_id'] == $pid) {
                //找到所有儿子节点
                $count++;
                $v['count'] = self::getCount($data, $v[$id], $id);
            }
        }
        return $count;
    }

    /**
     * @param $survey
     * @param $headers
     * @return array
     */
    public static function getTargets($survey, $headers)
    {
        $tmp = $survey;
        if ($survey['status'] == 1 && isset($survey['result'])) {
            $ruleOutput = RuleOutputInfo::getResultMapByStandardId($survey['standard_id']);
            $nodes = json_decode($survey['result'], true);
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
        } else {
            $tmp['ine_total_points'] = '未生成';
        }

        $row = [];
        foreach ($headers as $key => $val) {
            $value = isset($tmp[$key]) ? $tmp[$key] : '';
            $row[] = [
                'value' => $value,
                'index' => $val['index'],
            ];
        }
        return $row;
    }
}