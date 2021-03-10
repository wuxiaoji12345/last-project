<?php


namespace console\service\downloads;

use api\models\User;
use api\service\qc\QuestionQcService;
use data\writer\ExcelExport;
use Yii;

class DownloadQuestionQcData extends Base
{
    /**
     * 问卷qc计划走访任务列表下载脚本
     * @param $bodyForm
     * @throws \yii\base\Exception
     */
    public function surveyListDownload($bodyForm)
    {
        $page = 0;
        $pageSize = 1000;
        $bodyForm['page'] = $page;
        $bodyForm['page_size'] = $pageSize;
        $search_task_id = $bodyForm['search_task_id'];

        $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);
        $cache = Yii::$app->redis;
        $headers = [
            "survey_time" => "检查时间",
            "survey_code" => "走访号",
            "tool_name" => "执行工具",
            "standard_name" => "检查项目名称",
            "channel_name" => "次渠道类型",
            "store_name" => "售点名称",
            "region_name" => "大区",
            "location_name" => "营业所",
//            "supervisor_name" => "主任",
            "route_name" => "线路",
            "question_qc_status" => "复核状态",
            "question_qc_describe" => "修改原因"
        ];
        $fileFull = self::generateFileName($bodyForm, $search_task_id, '后台问卷任务列表下载.xls');
        $filename = $fileFull['file_name'];
        $fileFullName = $fileFull['full_name'];
        $relativePath = $fileFull['relative_path'];
        $export = new ExcelExport($fileFullName);
        $export->setHeader($headers);

        $user = $bodyForm['user'];
        Yii::$app->params['user_info'] = $user;
        Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_image_download_process_prefix'] . '_' . $search_task_id;
        $map = [];
        $data = ['count' => 0, 'map' => $map, 'list' => []];
        while ($page < $totalPageNum) {
            $bodyForm['searchForm']['page'] = $page + 1;
            $bodyForm['searchForm']['page_size'] = $pageSize;
            $data = QuestionQcService::getQuestionQcSurveyList($bodyForm['searchForm']);
            // 写入excel
            $export->setContent($data['list']);
            $cache->expire($cacheKey, 300);
            $progress = round(ceil($page / $totalPageNum * 100), 2);
            $cache->set($cacheKey, json_encode([
                'count' => $data['count'],
                'progress' => $progress,
                'data' => null
            ]));
            $page++;
        }
        $export->setFooter();
        $cache->set($cacheKey, json_encode([
            'count' => $data['count'],
            'progress' => 100,
            'data' => [
                'file_path' => $relativePath . $filename
            ]
        ]));
        $cache->expire($cacheKey, 300);
    }
}