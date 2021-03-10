<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

use api\models\EngineResult;
use api\models\Plan;
use api\models\Question;
use api\models\QuestionAnswer;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\Store;
use api\models\User;
use common\libs\sku\IRSku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use yii\helpers\ArrayHelper;
use Yii;

class ReportController extends ServiceController
{
    public function actionSceneDownload()
    {
        do {
            try {
                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_scene_download_list'];
                $bodyForm = Yii::$app->remq->dequeue($QueueName);
                if ($bodyForm == null) {
                    continue;
                }
                $page = 1;
                $pageSize = 1000;
                $bodyForm['page'] = $page;
                $bodyForm['page_size'] = $pageSize;
                $search_task_id = $bodyForm['search_task_id'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1;
                /*
                $searchLabel = [
//                    '业务前端' => $appName,
                    '检查起始时间' => $bodyForm['time_begin'],
                    '检查截止时间' => $bodyForm['time_end'],
                ];
                foreach ($searchLabel as $k => $item) {
                    if ($item != '') {
                        $sheet->setCellValue('A' . $row, $k . ':');
                        $sheet->setCellValue('B' . $row, $item);
                        $row++;
                    }
                }*/
                $headers = [
//                    'id' => '序号',
                    'survey_time' => '走访时间',
                    'standard_name' => '检查项目名称',
                    'tool_name' => '执行工具',
                    'bu_name' => '所属bu',
                    'survey_code' => '走访号',
                    'is_rectify' => '是否整改',
                    'channel_type_label' => '渠道类型',
                    'store_id' => '售点编号',
                    'name' => '售点名称',
                    'location_name' => '营业所',
                    'supervisor_name' => '主任',
                    'route_code' => '线路'
                ];

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $sheet->getStyle($c)->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $c++;
                }
                $user = $bodyForm['user'];
                Yii::$app->params['user_info'] = $user;
                Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
                $row = $headerRow + 1;
                $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_scene_download_process_prefix'] . '_' . $search_task_id;
                $map = [];
                $data = ['count' => 0, 'map' => $map, 'list' => []];
                while ($page <= $totalPageNum) {
                    $bodyForm['page'] = $page;
                    $data = EngineResult::getEngineResultData($bodyForm);
                    $tmpMap = ArrayHelper::index($data['map'], 'node_index');

                    // 将没有写过的表头写入
                    foreach ($tmpMap as $node_index => $node) {
                        if (!isset($map[$node_index])) {
                            $sheet->setCellValue($c . $headerRow, $node['label']);
                            $sheet->getColumnDimension($c)->setWidth('20');
                            $map[$node_index] = $c++;
                        }
                    }
                    // 写入excel
                    // 售点名称要从共享库中取
                    $store_ids = array_column($data['list'], 'store_id');
                    $store_all = Store::findAllArray(['store_id' => $store_ids], ['id', 'store_id', 'name'], 'store_id');
                    foreach ($data['list'] as $index => $datum) {
                        $tc = 'A';
                        foreach ($headers as $k => $header) {
                            $datum[$k] = $k == 'is_rectify' ? ($datum['is_rectify'] == EngineResult::IS_RECTIFY_YES ? "true" : "false") : $datum[$k];
                            // 售点名称里面单独处理
                            if ($k == 'name') {
                                $sheet->getCell($tc . $row)->setValueExplicit($store_all[$datum['store_id']]['name'], 's');
                            } else {
                                $sheet->getCell($tc . $row)->setValueExplicit($datum[$k], 's');
                            }
                            $tc++;
                        }

                        // 检查输出项合并
                        foreach ($datum['check_list'] as $node_index => $check_item) {
                            if (isset($map[$node_index])) {
                                $sheet->setCellValue($map[$node_index] . $row, $check_item);
                            }
                        }
                        $row++;
                    }
                    $cache->expire($cacheKey, 300);
                    $progress = floor($page / $totalPageNum * 100);
                    $progress = $progress == 100 ? 99 : $progress;

                    $cache->set($cacheKey, json_encode([
                        'count' => $data['count'],
                        'progress' => $progress,
                        'data' => null
                    ]));
                    $page++;
                }
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id, ArrayHelper::getValue($bodyForm, 'file_name', ''));
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
                $cache->set($cacheKey, json_encode([
                    'count' => $data['count'],
                    'progress' => 100,
                    'data' => [
                        'file_path' => $relativePath . $filename
                    ]
                ]));
                $cache->expire($cacheKey, 300);
            } catch (\Exception $e) {
                Yii::error($e);
                $this->ding->sendTxt($e->getMessage());
                sleep(1);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }

    public function actionQuestionDownload()
    {
        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_question_download_list'];
        $queuePrefix = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_question_download_process_prefix'];

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

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1; // 表头在第几行
                /*
                 * 搜索条件可能放在excel里
                $searchLabel = [
//                    '业务前端' => $appName,
                    '检查起始时间' => $bodyForm['time_begin'],
                    '检查截止时间' => $bodyForm['time_end'],
                ];
                foreach ($searchLabel as $k => $item) {
                    if ($item != '') {
                        $sheet->setCellValue('A' . $row, $k . ':');
                        $sheet->setCellValue('B' . $row, $item);
                        $row++;
                    }
                }*/
                $headers = [
//                    'id' => '序号',
                    'survey_time' => '走访时间',
                    'survey_code' => '走访号',
                    'bu_name' => '所属bu',
                    'store_id' => '售点编号',
                    'standard_title' => '检查项目名称',
                    'name' => '执行工具',
                    'rate_type_label' => '检查频率',
                    'type_label' => '问卷纬度',
                    'scene_type_label' => '场景类型',
                    'scene_id_name' => '业务端场景名称',
                    'question_title' => '问卷名称',
                    'answer' => '问卷答案'
                ];

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $sheet->getStyle($c)->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $c++;
                }
                $user = $bodyForm['user'];
                Yii::$app->params['user_info'] = $user;
                Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
                $row = $headerRow + 1;
                $sceneAll = Scene::getAll(['id', 'scene_code', 'scene_code_name'], 'scene_code');

                $bu = OrganizationRelation::companyBu();
                // 将数据查出来之后，把检查计划中的所有 question_id 查出来，再匹配
                while ($page <= $totalPageNum) {
                    $bodyForm['page'] = $page;
                    $data = QuestionAnswer::getAnswerData($bodyForm);

                    // 写入excel
                    foreach ($data['list'] as $index => $datum) {
                        // 字段label处理
                        $key = $datum['company_code'] . '_' . $datum['bu_code'];
                        $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
                        $datum['scene_type_label'] = isset($sceneAll[$datum['scene_code']]) ? $sceneAll[$datum['scene_code']]['scene_code_name'] : '';
                        $datum['rate_type_label'] = isset(Plan::RATE_TYPE_ARR[$datum['rate_type']]) ? Plan::RATE_TYPE_ARR[$datum['rate_type']] : '';
                        $datum['type_label'] = Question::TYPE_ARR[$datum['type']];
                        $tc = 'A';
                        foreach ($headers as $k => $header) {
                            $sheet->getCell($tc . $row)->setValueExplicit($datum[$k], 's');
                            $tc++;
                        }

                        $row++;
                    }
                    $cache->expire($cacheKey, 300);
                    $progress = floor($page / $totalPageNum * 100);

                    $cache->set($cacheKey, json_encode([
                        'count' => $data['count'],
                        'progress' => $progress,
                        'data' => null
                    ]));
                    $page++;
                }
                // todo 名称有可能有冲突
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id);
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
                $cache->set($cacheKey, json_encode([
                    'count' => isset($data['count']) ? $data['count'] : 0,
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

    public function actionStatisticalDownload()
    {
        do {
            try {
                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_statistical_download_list'];
                $bodyForm = Yii::$app->remq->dequeue($QueueName);
                if ($bodyForm == null) {
                    continue;
                }
                $page = 1;
                $pageSize = 1000;
                $bodyForm['page'] = $page;
                $bodyForm['page_size'] = $pageSize;
                $search_task_id = $bodyForm['search_task_id'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1;
                /*
                $searchLabel = [
//                    '业务前端' => $appName,
                    '检查起始时间' => $bodyForm['time_begin'],
                    '检查截止时间' => $bodyForm['time_end'],
                ];
                foreach ($searchLabel as $k => $item) {
                    if ($item != '') {
                        $sheet->setCellValue('A' . $row, $k . ':');
                        $sheet->setCellValue('B' . $row, $item);
                        $row++;
                    }
                }*/
                $headers = [
//                    'id' => '序号',
                    'survey_time' => '检查时间',
                    'statistical_name' => '统计项目名称',
                    'tool_name' => '执行工具',
                    'survey_code' => '走访号',
                    'bu_name' => '所属bu',
                    'sub_channel_name' => '次渠道类型',
                    'store_id' => '售点编号',
                    'location_name' => '营业所',
                    'supervisor_name' => '主任',
                    'route_code' => '线路',
                    'check_scope_name' => '检查范围'
                ];

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $sheet->getStyle($c)->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $c++;
                }
                $user = $bodyForm['user'];
                Yii::$app->params['user_info'] = $user;
                Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
                $row = $headerRow + 1;
                $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_scene_download_process_prefix'] . '_' . $search_task_id;
                $map = [];
                $data = ['count' => 0, 'map' => $map, 'list' => []];
                while ($page <= $totalPageNum) {
                    $bodyForm['page'] = $page;
                    $data = EngineResult::getStatisticalEngineResultData($bodyForm);
                    $tmpMap = ArrayHelper::index($data['map'], 'node_index');

                    // 将没有写过的表头写入
                    foreach ($tmpMap as $node_index => $node) {
                        if (!isset($map[$node_index])) {
                            $sheet->setCellValue($c . $headerRow, $node['label']);
                            $sheet->getColumnDimension($c)->setWidth('20');
                            $map[$node_index] = $c++;
                        }
                    }
                    // 写入excel
                    foreach ($data['list'] as $index => $datum) {
                        $tc = 'A';
                        foreach ($headers as $k => $header) {
                            $sheet->getCell($tc . $row)->setValueExplicit($datum[$k], 's');
                            $tc++;
                        }

                        // 检查输出项合并
                        foreach ($datum['check_list'] as $node_index => $check_item) {
                            if (isset($map[$node_index])) {
                                $sheet->setCellValue($map[$node_index] . $row, $check_item);
                            }
                        }
                        $row++;
                    }
                    $cache->expire($cacheKey, 300);
                    $progress = floor($page / $totalPageNum * 100);

                    $cache->set($cacheKey, json_encode([
                        'count' => $data['count'],
                        'progress' => $progress,
                        'data' => null
                    ]));
                    $page++;
                }
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id);
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
                $cache->set($cacheKey, json_encode([
                    'count' => $data['count'],
                    'progress' => 100,
                    'data' => [
                        'file_path' => $relativePath . $filename
                    ]
                ]));
                $cache->expire($cacheKey, 300);
            } catch (\Exception $e) {
                Yii::error($e);
                $this->ding->sendTxt($e->getMessage());
                sleep(1);
            } finally {
                Yii::$app->db->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }

    public function actionSkuDetailDownload($company_code, $start_date, $end_date)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $c = 'A';
            $headerRow = 1;
            $headers = [
                'survey_time' => '拜访日期',
                'region_code1' => '运营中心',
                'region_code3' => '大区',
                'location_name' => '营业所',
                'sub_channel_code' => '渠道编码',
                'route_code' => '线路编号',
                'store_id' => '客户编号',
                'name' => '客户名称',
                'title' => '检查项目名称',
                'description' => '检查要求描述',
                'scene_name' => '场景',
                'group_name' => '厂商',
                'brand_name' => '品牌',
                'category_name' => '饮料类别',
                'sku_name' => '产品名称',
                'num' => '排面数',
            ];
            $map = [];
            foreach ($headers as $k => $name) {
                $sheet->setCellValue($c . $headerRow, $name);
                $sheet->getColumnDimension($c)->setWidth('20');
                $sheet->getStyle($c)->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
                $map[$k] = $c;
                $c++;
            }
            // 查询数据
            $where = " and  s.company_code=$company_code and su.survey_time>='$start_date' and su.survey_time<'$end_date'";
            $sql = "select st.company_code,st.region_code,DATE_FORMAT(su.survey_time, '%Y-%m-%d') survey_time,st.location_name,st.`sub_channel_code`,st.route_code,st.store_id,st.name,s.title,s.`description`,i.scene_code,ir.`result` from sys_standard s
right join sys_engine_result er on er.`standard_id`=s.id  left join `sys_survey` su on su.`survey_code`=er.`survey_code` left join sys_store st on st.`store_id`=su.`store_id`
left join sys_image i on i.`survey_code`=er.`survey_code` left join `sys_image_report` ir on ir.photo_id=i.id where ir.result is not null and su.`survey_code` is not null" . $where . " order by su.survey_time asc";
            $data = Yii::$app->db->createCommand($sql)->queryAll();
//            $path = Yii::getAlias('@api') . '/web/tmp/report.csv';
//            $file = fopen($path);
            $company_codes = array_unique(array_column($data, 'company_code'));
            // 获取region1
            $codes = "'" . implode("','", $company_codes) . "'";
            $sql1 = "select `code`,`name` from sys_store_belong where `type`=1 and `value` in ($codes)";
            $regions = Yii::$app->db2->createCommand($sql1)->queryAll();
            $region1 = array_combine(array_column($regions, 'code'), array_column($regions, 'name'));
            // 获取region3
            $region_codes = array_unique(array_column($data, 'region_code'));
            $codes = "'" . implode("','", $region_codes) . "'";
            $sql1 = "select `code`,`name` from sys_store_belong where `type`=3 and `value` in ($codes)";
            $regions = Yii::$app->db2->createCommand($sql1)->queryAll();
            $region3 = array_combine(array_column($regions, 'code'), array_column($regions, 'name'));
            // 获取 scene
            $scene_codes = array_unique(array_column($data, 'scene_code'));
            $codes = "'" . implode("','", $scene_codes) . "'";
            $sql1 = "select `scene_code`,`scene_code_name` from sys_scene where `scene_code` in ($codes)";
            $scenes = Yii::$app->db2->createCommand($sql1)->queryAll();
            $scene = array_combine(array_column($scenes, 'scene_code'), array_column($scenes, 'scene_code_name'));

            // 遍历数据
            $excel_data = [];
            foreach ($data as $key => &$val) {
                $val['region_code1'] = isset($region1[$val['company_code']]) ? $region1[$val['company_code']] : '';
                $val['region_code3'] = isset($region3[$val['region_code']]) ? $region3[$val['region_code']] : '';
                $val['scene_name'] = isset($scene[$val['scene_code']]) ? $scene[$val['scene_code']] : '';
                // 解析result
                $result = json_decode($val['result'], true);
                if (!isset($result['structure_detections']) || empty($result['structure_detections']))
                    continue;
                $skus = [];
                foreach ($result['structure_detections'] as $structure) {
                    foreach ($structure['layers'] as $layer) {
                        if (!isset($layer['objects']) || empty($layer['objects']))
                            continue;
                        foreach ($layer['objects'] as $object) {
                            if (!isset($result['class_detections'][$object[0]]['sku_name']))
                                continue;
                            $sku_id = explode('^', $result['class_detections'][$object[0]]['sku_name'])[0];
                            if (isset($skus[$sku_id])) {
                                $skus[$sku_id]++;
                            } else {
                                $skus[$sku_id] = 1;
                            }
                        }
                    }
                }
                $sku_ids = implode(',', array_keys($skus));
                if (empty($sku_ids)) {//避免空参数请求接口
                    continue;
                }
                // 查询sku_id对应太古的品牌分类公司信息
                $skuAttributes = IRSku::getSkuAttributes($sku_ids);
                foreach ($skus as $id => $num) {
                    if (isset($skuAttributes[$id])) { // 只导出有sku的
                        $tmp = $val;
                        $tmp['group_name'] = $skuAttributes[$id]['group_name'];
                        $tmp['brand_name'] = $skuAttributes[$id]['brand_name'];
                        $tmp['category_name'] = $skuAttributes[$id]['category_name'];
                        $tmp['sku_name'] = $skuAttributes[$id]['sku_name'];
                        $tmp['num'] = $num;
                        $excel_data[] = $tmp;
                    }
                }
            }
            $rowNum = 2;
            // 写入excel
            foreach ($excel_data as $index => $datum) {
                // 检查输出项合并
                foreach ($map as $node_index => $char) {
                    if (isset($datum[$node_index])) {
                        $sheet->setCellValue($char . $rowNum, $datum[$node_index]);
                    }
                }
                $rowNum++;
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $fileFull = $this->generateFileName(['start_time' => $start_date, 'end_time' => $end_date], $company_code);
            $fileFullName = $fileFull['full_name'];
            $writer->save($fileFullName);
        } catch (\Exception $e) {
            echo $e;
        } finally {
            Yii::$app->db->close();
            pcntl_signal_dispatch();
        }
    }

    public function actionSkuDetailDownloadCsv($company_code, $start_date, $end_date)
    {
        try {
            $file_name = $start_date . '_' . $end_date . '_' . '_' . $company_code . '.csv';
            $relativePath = '/tmp/' . date('Ymd') . '/';
            $path = Yii::getAlias('@api') . '/web' . $relativePath;
            $fileFullName = $path . $file_name;
            $fh = fopen($fileFullName, 'w+');

            $headers = [
                'survey_time' => '拜访日期',
                'region_code1' => '运营中心',
                'region_code3' => '大区',
                'location_name' => '营业所',
                'sub_channel_code' => '渠道编码',
                'route_code' => '线路编号',
                'store_id' => '客户编号',
                'name' => '客户名称',
                'title' => '检查项目名称',
                'description' => '检查要求描述',
                'scene_name' => '场景',
                'group_name' => '厂商',
                'brand_name' => '品牌',
                'category_name' => '饮料类别',
                'sku_name' => '产品名称',
                'num' => '排面数',
            ];
            fwrite($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
            $content = implode(',', array_values($headers)) . "\n";
            // 查询数据
            $where = " and  s.company_code=$company_code and su.survey_time>='$start_date' and su.survey_time<'$end_date'";
            $sql = "select st.company_code,st.region_code,DATE_FORMAT(su.survey_time, '%Y-%m-%d') survey_time,st.location_name,st.`sub_channel_code`,st.route_code,st.store_id,st.name,s.title,s.`description`,i.scene_code,ir.`result` from sys_standard s
right join sys_engine_result er on er.`standard_id`=s.id  left join `sys_survey` su on su.`survey_code`=er.`survey_code` left join sys_store st on st.`store_id`=su.`store_id`
left join sys_image i on i.`survey_code`=er.`survey_code` left join `sys_image_report` ir on ir.photo_id=i.id where ir.result is not null and su.`survey_code` is not null" . $where . " order by su.survey_time asc";
            $data = Yii::$app->db->createCommand($sql)->queryAll();
            $company_codes = array_unique(array_column($data, 'company_code'));
            // 获取region1
            $codes = "'" . implode("','", $company_codes) . "'";
            $sql1 = "select `code`,`name` from sys_store_belong where `type`=1 and `value` in ($codes)";
            $regions = Yii::$app->db2->createCommand($sql1)->queryAll();
            $region1 = array_combine(array_column($regions, 'code'), array_column($regions, 'name'));
            // 获取region3
            $region_codes = array_unique(array_column($data, 'region_code'));
            $codes = "'" . implode("','", $region_codes) . "'";
            $sql1 = "select `code`,`name` from sys_store_belong where `type`=3 and `value` in ($codes)";
            $regions = Yii::$app->db2->createCommand($sql1)->queryAll();
            $region3 = array_combine(array_column($regions, 'code'), array_column($regions, 'name'));
            // 获取 scene
            $scene_codes = array_unique(array_column($data, 'scene_code'));
            $codes = "'" . implode("','", $scene_codes) . "'";
            $sql1 = "select `scene_code`,`scene_code_name` from sys_scene where `scene_code` in ($codes)";
            $scenes = Yii::$app->db2->createCommand($sql1)->queryAll();
            $scene = array_combine(array_column($scenes, 'scene_code'), array_column($scenes, 'scene_code_name'));

            // 遍历数据
            foreach ($data as $key => &$val) {
                $val['region_code1'] = isset($region1[$val['company_code']]) ? $region1[$val['company_code']] : '';
                $val['region_code3'] = isset($region3[$val['region_code']]) ? $region3[$val['region_code']] : '';
                $val['scene_name'] = isset($scene[$val['scene_code']]) ? $scene[$val['scene_code']] : '';
                // 解析result
                $result = json_decode($val['result'], true);
                if (!isset($result['structure_detections']) || empty($result['structure_detections']))
                    continue;
                $skus = [];
                foreach ($result['structure_detections'] as $structure) {
                    foreach ($structure['layers'] as $layer) {
                        if (!isset($layer['objects']) || empty($layer['objects']))
                            continue;
                        foreach ($layer['objects'] as $object) {
                            if (!isset($result['class_detections'][$object[0]]['sku_name']))
                                continue;
                            $sku_id = explode('^', $result['class_detections'][$object[0]]['sku_name'])[0];
                            if (isset($skus[$sku_id])) {
                                $skus[$sku_id]++;
                            } else {
                                $skus[$sku_id] = 1;
                            }
                        }
                    }
                }
                $sku_ids = implode(',', array_keys($skus));
                if (empty($sku_ids)) {//避免空参数请求接口
                    continue;
                }
                // 查询sku_id对应太古的品牌分类公司信息
                $skuAttributes = IRSku::getSkuAttributes($sku_ids);
                foreach ($skus as $id => $num) {
                    if (isset($skuAttributes[$id])) { // 只导出有sku的
                        $tmp = $val;
                        $tmp['group_name'] = $skuAttributes[$id]['group_name'];
                        $tmp['brand_name'] = $skuAttributes[$id]['brand_name'];
                        $tmp['category_name'] = $skuAttributes[$id]['category_name'];
                        $tmp['sku_name'] = $skuAttributes[$id]['sku_name'];
                        $tmp['num'] = $num;
                        $tmp1 = [];
                        foreach ($headers as $tkey => $tval) {
                            $tmp1[] = $tmp[$tkey];
                        }
//                        fputcsv($fh, $tmp1);
                        $content .= str_replace(["\n", "\r\n"], '', implode(',', $tmp1)) . "\n";
                    }
                }
            }
            fwrite($fh, $content);
            fclose($fh);
        } catch (\Exception $e) {
            echo $e;
        } finally {
            Yii::$app->db->close();
            pcntl_signal_dispatch();
        }
    }
}