<?php


namespace console\service\downloads;

use api\models\Download;
use api\models\ImageSimilarity;
use api\models\share\Store;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Survey;
use api\models\Tools;
use api\models\User;
use api\service\ine\InterviewService;
use data\writer\SheetExport;
use data\writer\ExcelExport;
use data\writer\ZipExport;
use Yii;
use yii\log\Logger;


class DownloadReportImage extends Base
{
    public function reportImageDownload($bodyForm)
    {
        $page = 0;
        $pageSize = 1000;
        $bodyForm['page_num'] = $page;
        $bodyForm['page_size'] = $pageSize;
        $search_task_id = $bodyForm['search_task_id'];

        $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);
        $cache = Yii::$app->redis;
        $headers = [
            "survey_time" => "检查时间",
            "survey_code" => "走访号",
            "tool_name" => "执行工具",
            "sta_title" => "检查项目名称",
            "activation_id" => "生动化ID",
            "scene_id" => "场景ID",
            "is_rectify" => "是否为整改",
            "route_code" => "线路",
            "store_id" => "售点编号",
            "store_name" => "售点名称",
            "is_rebroadcast" => "是否为翻拍",
            "is_similarity" => "是否为相似图",
            "image_url" => "图片URL地址"
        ];
        $fileFull = self::generateFileName($bodyForm, $search_task_id, '图片查询列表下载.xls');
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
            $bodyForm['page_num'] = $page;
            $data = Survey::getReportImageNotSurveyDown($bodyForm['where'], $page, $pageSize);
            //售点信息
            $store_id_list = array_unique(array_column($data['list'], 'store_id'));
            $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_id_list], true, true, '', 'store_id');
            //生动化
            $standard_id_list = array_unique(array_column($data['list'], 'standard_id'));
            $activation_list = SubActivity::findJoin('', [], ['id activation_id', 'activation_name', 'count(*) as count', 'standard_id'], ['in', 'standard_id', $standard_id_list], true, true, '', 'standard_id', 'standard_id');
            // 写入excel
            foreach ($data['list'] as &$v) {
                $v['store_name'] = $store_list[$v['store_id']]['store_name'];
                if (isset($activation_list[$v['standard_id']]) && $activation_list[$v['standard_id']]['count'] == 1) {
                    $v['activation_id'] = $activation_list[$v['standard_id']]['activation_id'];
                } else {
                    $v['activation_id'] = '';
                }
            }
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

    private function getSimilarList($where)
    {
        $data = ImageSimilarity::getList($where);
        //售点信息
        $store_id_list = array_column($data, 'store_id');
        $similarity_store_id_list = array_column($data, 'similarity_store_id');
        $store_ids = array_unique(array_merge($store_id_list, $similarity_store_id_list));
        $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_ids], true, true, '', 'store_id');
        //检查项目
        $standard_id_list = array_column($data, 'standard_id');
        $similarity_standard_id_list = array_column($data, 'similarity_standard_id');
        $standard_ids = array_unique(array_merge($standard_id_list, $similarity_standard_id_list));
        $standard_list = Standard::findJoin('', [], ['id standard_id', 'title standard_name'], ['in', 'id', $standard_ids], true, true, '', 'standard_id');
        //生动化
        $activation_list = SubActivity::findJoin('', [], ['id activation_id', 'activation_name', 'count(*) as count', 'standard_id'], ['in', 'standard_id', $standard_ids], true, true, '', 'standard_id','standard_id');
        //工具列表
        $tools = Tools::find()->indexBy('id')->asArray()->all();
        $causes = [
            ["causename" => '不同线路，不同售点，不同时间', "value" => 1],
            ["causename" => '不同线路，不同售点，相同时间', "value" => 2],
            ["causename" => '相同线路，不同售点，不同时间', "value" => 3],
            ["causename" => '相同线路，不同售点，相同时间', "value" => 4],
            ["causename" => '相同线路，相同售点，不同时间', "value" => 5]
        ];
        foreach ($data as &$v) {
            $v['store_name'] = $store_list[$v['store_id']]['store_name'];
            $v['similarity_store_name'] = $store_list[$v['similarity_store_id']]['store_name'];
            $v['standard_name'] = $standard_list[$v['standard_id']]['standard_name'] ?? '';
            $v['similarity_standard_name'] = $standard_list[$v['similarity_standard_id']]['standard_name'] ?? '';
            $v['similarity_cause'] = $causes[$v['similarity_cause'] - 1]['causename'] ?? '';
            $v['tool_name'] = $tools[$v['tool_id']]['name'] ?? '';
            $v['similarity_tool_name'] = $tools[$v['similarity_tool_id']]['name'] ?? '';
            /**
             * 生动化设置
             */
            if (isset($activation_list[$v['standard_id']]) && $activation_list[$v['standard_id']]['count'] == 1) {
                $v['activation_id'] = $activation_list[$v['standard_id']]['activation_id'];
                $v['activation_name'] = $activation_list[$v['standard_id']]['activation_name'];
            } else {
                $v['activation_id'] = '';
                $v['activation_name'] = '';
            }
            if (isset($activation_list[$v['similarity_standard_id']]) && $activation_list[$v['similarity_standard_id']]['count'] == 1) {
                $v['similarity_activation_id'] = $activation_list[$v['similarity_standard_id']]['activation_id'];
                $v['similarity_activation_name'] = $activation_list[$v['similarity_standard_id']]['activation_name'];
            } else {
                $v['similarity_activation_id'] = '';
                $v['similarity_activation_name'] = '';
            }
        }
        return $data;
    }

    public function similarImageDownload($bodyForm)
    {
        /**
         * 查询任务
         */
        $download = Download::find()->where(['id' => $bodyForm['download_id']])->one();
        $download->download_status = 1;//文档生成中
        $download->task_id = $bodyForm['search_task_id'];
        list($time, $i) = explode('_', $download->task_id);
        $file_path = '相似图' . date('Ymd-His', $time) . '-' . $i;
        $relative_path = DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $file_path;
        $path = \Yii::getAlias('@api') . DIRECTORY_SEPARATOR . 'web' . $relative_path;
        $images_path = $path . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
        if (!is_dir($images_path)) {
            mkdir($images_path, 0777, true);
        }
        $file = $path . DIRECTORY_SEPARATOR . '相似图.xls';

        $headers = [
            'similarity_cause' => '相似条件',
            'tool_name' => '相似图执行工具名称',
            'store_id' => '相似图售点编号',
            'store_name' => '相似图售点名称',
            'route_code' => '相似图线路号',
            'standard_name' => '相似图检查项目名称',
            'survey_time' => '相似图检查时间',
            'survey_code' => '相似图走访号',
            'activation_id' => '相似图生动化ID',
            'activation_name' => '相似图生动化名称',
            'scene_id_name' => '相似图场景名称',
            'image_key' => '相似图照片key',
            'image_url' => '相似图照片url',
            'image' => '相似图照片',
            'similarity_tool_name' => '原图执行工具名称',
            'similarity_store_id' => '原图售点编号',
            'similarity_store_name' => '原图售点名称',
            'similarity_route_code' => '原图线路号',
            'similarity_standard_name' => '原图检查项目名称',
            'similarity_survey_time' => '原图检查时间',
            'similarity_survey_code' => '原图走访号',
            'similarity_activation_id' => '原图生动化ID',
            'similarity_activation_name' => '原图生动化名称',
            'similarity_scene_id_name' => '原图场景名称',
            'similarity_image_key' => '原图照片key',
            'similarity_image_url' => '原图照片URL',
            'similarity_image' => '原图照片',
        ];
        $export = new ExcelExport($file);
        $export->setHeader($headers);
        $user = $bodyForm['user'];
        Yii::$app->params['user_info'] = $user;
        Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
        $cache = Yii::$app->redis;
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_image_download_process_prefix'] . '_' . $search_task_id;

        $data = $this->getSimilarList($bodyForm['where']);
        $count = $bodyForm['count'];
        $page = 0;
        $page_size = 1000;
        $totalPageNum = ceil($count / $page_size);
        while ($page < $totalPageNum) {
            $list = array_slice($data, $page * $page_size, $page_size);

            foreach ($list as $index => &$val) {
                /**
                 * 原图地址
                 */
                $val['image_url'] = Yii::$app->params['cos_url'] . $val['image_key'];
                $val['similarity_image_url'] = Yii::$app->params['cos_url'] . $val['similarity_image_key'];
                /**
                 * 获取缩放图片到本地
                 */
                $image = $val['image_url'] . "?imageMogr2/thumbnail/100x";
                $similarity_image = $val['similarity_image_url'] . "?imageMogr2/thumbnail/100x";
                $image_file = $images_path . $val['image_key'];
                $similarity_image_file = $images_path . $val['similarity_image_key'];
                try {
                    if (!file_exists($image_file)) {
                        file_put_contents($image_file, file_get_contents($image));
                    }
                    if (!file_exists($similarity_image_file)) {
                        file_put_contents($similarity_image_file, file_get_contents($similarity_image));
                    }
                } catch (\Exception $e) {
                    Yii::getLogger()->log($e->getMessage(), Logger::LEVEL_WARNING);
                    $val['image'] = '图片获取失败';
                    $val['similarity_image'] = '图片获取失败';
                    continue;
                }

                /**
                 * 获取图片高度
                 */
                $image_height = getimagesize($image_file)[1];
                $similarity_image_height = getimagesize($similarity_image_file)[1];
                /**
                 * 设置图片内容
                 */
                $val['image'] = ['value' => "<img src=\"./images/{$val['image_key']}\"/>", 'style' => " width='100' height='$image_height'"];
                $val['similarity_image'] = ['value' => "<img src=\"./images/{$val['similarity_image_key']}\"/>", 'style' => " width='100' height='$similarity_image_height'"];
            }
            // 写入xml
            $export->setContent($list);
            $cache->expire($cacheKey, 300);
            $progress = round(ceil($page / $totalPageNum * 100), 2);

            $cache->set($cacheKey, json_encode([
                'count' => $count,
                'progress' => $progress,
                'data' => null
            ]));
            $page++;
        }
        $export->setFooter();
        $zipFile = $path . '.zip';
        ZipExport::zip($path, $zipFile);

        $download->file_path = dirname($path);
        $download->file_name = substr($zipFile, strrpos($zipFile, DIRECTORY_SEPARATOR) + 1);
        $download->file_size = (string)filesize($zipFile);
        $download->download_url = $relative_path . '.zip';
        $download->download_status = 2;//文档已完成
        $download->save();
    }

    /**
     *下载走访记录列表
     * @param $bodyForm
     * @throws \yii\base\Exception
     */
    public function interviewDownload($bodyForm)
    {
        $page = 1;
        $pageSize = 1000;
        $search_task_id = $bodyForm['search_task_id'];

        $totalPageNum = ceil($bodyForm['count'] / $pageSize);
        $cache = Yii::$app->redis;

        $fileFull = self::generateFileName($bodyForm, $search_task_id, '走访记录列表.xls');
        $filename = $fileFull['file_name'];
        $fileFullName = $fileFull['full_name'];
        $relativePath = $fileFull['relative_path'];
        $export = new SheetExport($fileFullName);
        $export->setHeader();

        $user = $bodyForm['user'];
        Yii::$app->params['user_info'] = $user;
        Yii::$app->params['user_is_3004'] = $user['company_code'] == User::COMPANY_CODE_ALL;
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_image_download_process_prefix'] . '_' . $search_task_id;
        $map = [];
        $data = ['count' => 0, 'map' => $map, 'list' => []];
        $ineChannelId = 0;
        $rows = [];
        $select = ['s.id', 's.survey_code', 's.examiner', 's.survey_time', 's.store_id', 's.store_name',
                   's.store_address', 's.sub_channel_id', 'c.year', 'c.channel_name', 'r.ine_total_points', 'r.result', 'r.standard_id'];
        while ($page <= $totalPageNum) {
            $data = Survey::getInterview($bodyForm['where'], $page, $pageSize, $select, 's.ine_channel_id');
            $list = $data['list'];
            foreach ($list as $survey) {
                if ($survey['ine_channel_id'] != $ineChannelId) {
                    if ($ineChannelId != 0) {
                        $export->setRow($rows);
                        $export->setSheetFooter();
                        $sheetName = $survey['channel_name'] . $survey['year'];
                        $export->setSheetHead($sheetName);
                    } else {
                        $sheetName = $survey['channel_name'] . $survey['year'];
                        $export->setSheetHead($sheetName);
                    }
                    $ineChannelId = $survey['ine_channel_id'];
                    $header = InterviewService::getHeaders($ineChannelId);
                    $rows = [$header[0], $header[1], $header[2]];
                    $indexes = $header[3];
                }
                $rows[] = InterviewService::getTargets($survey, $indexes);
            }
            // 写入excel
            $export->setRow($rows);
            $rows = [];
            $cache->expire($cacheKey, 300);
            $progress = round(ceil($page / $totalPageNum * 100), 2);
            $cache->set($cacheKey, json_encode([
                'count' => $data['count'],
                'progress' => $progress,
                'data' => null
            ]));
            $page++;
        }
        $export->setSheetFooter();
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