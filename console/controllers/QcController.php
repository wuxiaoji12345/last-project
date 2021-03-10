<?php


namespace console\controllers;

use api\models\PlanStoreRelation;
use api\models\RuleOutputInfo;
use api\models\User;
use api\service\qc\ReviewService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yii;

class QcController extends ServiceController
{
    // excel 分页数量
    public $excelChunkSize = 5000;
    public $maxTryTime = 2;     // 最大重试次数

    /**
     * 人工复核结果列表下载
     */
    public function actionManualCheckResultListDownload()
    {
//        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['manual_check_result_list_download'];
        $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'manual_check_result_list_download');
//        $queuePrefix = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['manual_check_result_list_download_process_prefix'];
        $queuePrefix = Yii::$app->remq::getQueueName('redis_queue', 'manual_check_result_list_download_process_prefix');

        $pageSize = 1000;
        do {
            try {
                $page = 1;
                $bodyForm = Yii::$app->remq->dequeue($QueueName);
                if ($bodyForm == null) {
                    continue;
                }
                $bodyForm['page'] = $page;
                $bodyForm['page_size'] = $pageSize;
                $search_task_id = $bodyForm['search_task_id'];
                $cacheKey = $queuePrefix . '_' . $search_task_id;
                $count = $bodyForm['count'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $excel_top = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv="Content-type" content="text/html;charset=UTF-8" /><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>excelName</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table><tbody>';

                $excel_body = ''; // 下面赋值

                $excel_bottom = '</tbody></table></body></html>';

                $fileFull = $this->generateFileName($bodyForm, $search_task_id, $search_task_id . '_人工复核结果列表');
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];

                // 因为导出的 xlsx 有的电脑打不开，就改成 xls
                $filename = str_replace('.xlsx', '.xls', $filename);
                $fileFullName = str_replace('.xlsx', '.xls', $fileFullName);

                // 打开文件 // todo 名称有可能有冲突
                $fopen = fopen($fileFullName, "wb");
                fwrite($fopen, $excel_top);

                $cache = Yii::$app->redis;

                $headers1 = [
                    'survey_time' => '检查时间',
                    'standard_title' => '检查项目名称',
                    'tool_name' => '执行工具',
                    'survey_code' => '走访号',
                    'qc_status' => '复核状态',
                    'sub_channel_name' => '次渠道类型',
                    'store_id' => '售点编号',
                    'region_code_name' => '大区',
                    'location_name' => '营业所',
                    'supervisor_name' => '主任',
                    'route_name' => '线路',
                    'check_type_title' => '检查类型',
                ];

                $map = RuleOutputInfo::getResultMapByStandard($bodyForm['standard_id']);

                $headers2 = [];
                foreach ($map as $item) {
                    $headers2['node_index' . $item['node_index']] = $item['label'];
                }

                $headers = array_merge($headers1, $headers2, ['review_reason' => '修改原因']);

                // 写入excel
                $excel_body = '<thead><tr>'; // 一行插入一次，所以不要连接之前的
                foreach ($headers as $index => $header) {
                    $excel_body .= "<td>{$header}</td>";
                }
                $excel_body .= '</tr></thead>';
                fwrite($fopen, $excel_body);

                $user = $bodyForm['user'];
                Yii::$app->params['user_info'] = $user;
                Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;

                while ($page <= $totalPageNum) {
                    $bodyForm['page'] = $page;
                    $bodyForm['page_size'] = $pageSize;
                    $result = ReviewService::getManualCheckResultList($bodyForm);
                    $data = $result['list'];

                    // 写入excel
                    foreach ($data as $index => $datum) {
                        // 先解析 qc_result 字段
                        foreach ($datum['qc_result'] as $node_index => $node_value) {
                            $datum['node_index' . $node_index] = $node_value;
                        }

                        $excel_body = '<tr>'; // 一行插入一次，所以不要连接之前的
                        foreach ($headers as $field => $header) {
                            $excel_body .= isset($datum[$field]) ? "<td x:str >" . $this->formatTd($datum[$field]) . "</td>" : "<td></td>";
                        }
                        $excel_body .= '</tr>';

                        fwrite($fopen, $excel_body);
                    }
                    $cache->expire($cacheKey, 300);
                    $progress = floor($page / $totalPageNum * 100);

                    // 下面保存文件还需要消耗些时间，这里只返回99%
                    if ($progress > 99) {
                        $progress = 99;
                    }

                    $cache->set($cacheKey, json_encode([
                        'count' => $count,
                        'progress' => $progress,
                        'data' => null
                    ]));
                    $cache->expire($cacheKey, 300);
                    $page++;
                }

                fwrite($fopen, $excel_bottom);
                fclose($fopen);
                $cache->set($cacheKey, json_encode([
                    'count' => $count,
                    'progress' => 100,
                    'data' => [
                        'file_path' => $relativePath . $filename
                    ]
                ]));
                $cache->expire($cacheKey, 300);
            } catch (\Exception $e) {
                $this->ding->sendTxt($e->getMessage());
                Yii::error($e);
                sleep(1);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }

    private function formatTd($value)
    {
        if (is_bool($value)) {
            return $value ? "合格" : "不合格";
        }
        return $value;

    }
}