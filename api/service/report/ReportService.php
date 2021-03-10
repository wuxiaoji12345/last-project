<?php

namespace api\service\report;

use api\models\apiModels\apiIneReportListModel;
use api\models\EngineResult;
use api\models\IneChannel;
use api\models\IneConfig;
use api\models\IneConfigSnapshot;
use api\models\Survey;
use api\models\Tools;
use common\libs\report\SceneReport;
use common\libs\sku\IRSku;

class ReportService
{
    /**
     * 获取报告列表
     * @param apiIneReportListModel $model
     * @return array
     */
    public static function GetSurveyListReport(apiIneReportListModel $model)
    {
        $query = EngineResult::find()->asArray()->alias('e')->joinWith('survey s')
            ->select(['s.survey_code', 's.store_id', 's.store_name', 'survey_time',
                'ine_channel_id', 'ine_score' => 'ine_total_points',
                'address' => 's.store_address',]);
        $query->where(['is_ine' => 1, 'tool_id' => [Tools::TOOL_ID_SEA_LEADER, Tools::TOOL_ID_SEA], 'survey_status' => Survey::SURVEY_END]);
        $query->andFilterWhere(['s.survey_code' => $model->survey_list]);
        $query->andFilterWhere(['s.store_id' => $model->store_id]);
        if ($model->channel_id != '') {
            $ineChannel = IneChannel::findAllArray(['channel_id' => $model->channel_id]);
            $ineChannelId = array_column($ineChannel, 'id');
            $query->andFilterWhere(['ine_channel_id' => $ineChannelId]);
        }
        if ($model->start_date != '') {
            $query->andFilterWhere(['>=', 'survey_time', $model->start_date . ' 00:00:00']);
        }
        if ($model->end_date != '') {
            $query->andFilterWhere(['<=', 'survey_time', $model->end_date . ' 23:59:59']);
        }
        $data = $query->all();
        $ineChannelAll = IneChannel::findAllArray([], ['*'], 'id');
        foreach ($data as &$datum) {
            // 渠道字段
            $datum['channel_id'] = $ineChannelAll[$datum['ine_channel_id']]['channel_id'] ?? 0;
            $datum['channel_code'] = $ineChannelAll[$datum['ine_channel_id']]['channel_code'] ?? '';
            $datum['channel_id_label'] = $ineChannelAll[$datum['ine_channel_id']]['channel_name'] ?? '';
            unset($datum['ine_channel_id']);
            unset($datum['survey']);
        }
        return $data;
    }

    /**
     * @param $channel_id
     * @param $year
     * @param $survey_code
     * @return array
     */
    public static function IneScoreConfig($channel_id, $year, $survey_code = '')
    {
        $result = [];
        if ($survey_code != '') {
            // 按走访当时的ine配置返回
            $engineResult = EngineResult::findOneArray(['survey_code' => $survey_code]);
            if ($engineResult == null) {
                return $result;
            }
            $result = IneConfigSnapshot::findAllArray(['ine_config_timestamp_id' => $engineResult['ine_config_timestamp_id'], 'level' => 2, 'display' => true, 'tree_display' => true],
                ['ine_id' => 'id', 'ine_title' => 'title', 'max_score']);
        } else {
            $ineChannel = IneChannel::findOneArray(['channel_id' => $channel_id, 'year' => $year]);
            if ($ineChannel == null) {
                return $result;
            }
            $result = IneConfig::findAllArray(['ine_channel_id' => $ineChannel['id'], 'level' => 2, 'display' => true, 'tree_display' => true],
                ['ine_id' => 'id', 'ine_title' => 'title', 'max_score']);
        }

        return $result;
    }

    /**
     * 解析sku详情
     * @param $result
     * @return array
     */
    public static function GetSkuInfo($result)
    {
        $m = new SceneReport(json_decode($result, true), ['filter_function' => function ($item) {
            try {
                $skuInfo = IRSku::re2swire($item['id']);
                if (!$skuInfo) {
                    return null;
                }
                $skuInfo['id'] = $skuInfo['bar_code'];
                $skuInfo['url'] = $skuInfo['image_url'];
                $skuInfo['objects'] = $item['objects'];
                $skuInfo['price_tag'] = $item['price_tag'];
                return $skuInfo;
            } catch (\Exception $e) {
                return null;
            }
        }]);

        return $m->getReport();
    }
}