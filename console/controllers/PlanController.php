<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

use api\models\Plan;
use api\models\PlanBatch;
use api\models\PlanStoreRelation;
use api\models\PlanStoreTmp;
use api\models\ProtocolStore;
use api\models\ProtocolTemplate;
use api\models\share\CheckStoreList;
use api\models\share\CheckStoreQuestion;
use api\models\share\CheckStoreScene;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\Standard;
use api\models\Store;
use api\service\plan\PlanService;
use api\service\zft\Protocol;
use common\components\REMQ;
use common\libs\excel\ChunkReadFilter;
use common\libs\file_log\LOG;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yii;
use yii\helpers\ArrayHelper;

class PlanController extends ServiceController
{
    // excel 分页数量
    public $excelChunkSize = 5000;
    public $maxTryTime = 2;     // 最大重试次数

    /**
     * 将上传的售点数据关联到检查计划
     * 先清除之前检查计划对应的售点数据
     */
    public function actionUploadStoreListBak()
    {
        do {
            try {
//                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_file'];
                $QueueNameList = Yii::$app->remq::getQueueName('queue_plan_store_file', '', '', true);
                $que = Yii::$app->remq->dequeue($QueueNameList);

//                $url = $tmp['url'];
//                $plan_id = $tmp['id'];
//                preg_match('/.(xlsx?)$/', $url, $matchs);
//                if (empty($matchs)) {
//                    return ['status' => false, 'msg' => '文件格式错误'];
//                }
//                $ext = $matchs[1];
//                $s = file_get_contents($url);
//                $path = Yii::getAlias('@runtime') . '/' . time() . '.' . $ext;  //文件路径和文件名
//
//                file_put_contents($path, $s);
//                if (strtoupper($ext) == 'XLS') {
//                    $ext = 'Xls';
//                }
//                if (strtoupper($ext) == 'XLSX') {
//                    $ext = 'Xlsx';
//                }

                if ($que == null) {
                    continue;
                }
                $queString = json_encode($que);
                $plan_id = $que['id'];
                $path = $que['path'];
                $ext = $que['ext'];
                $user = $que['user'];
                $old_set_store_type = $que['set_store_type'];
                $reader = IOFactory::createReader($ext);
//                $spreadsheet = $reader->load($path); // 载入excel文件
//                $worksheet = $spreadsheet->getActiveSheet();
//                $highestRow = $worksheet->getHighestRow(); // 总行数
                $chunkFilter = new ChunkReadFilter();
                $reader->setReadFilter($chunkFilter);
                $reader->setReadDataOnly(true);
                $chunkSize = $this->excelChunkSize;

                $worksheetData = $reader->listWorksheetInfo($path);

                $highestRow = $worksheetData[0]['totalRows'];

                $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_file', '', $plan_id);
                if ($highestRow == 1) {
                    unlink($path);
                    REMQ::setString($QueueName, ['status' => false, 'msg' => '文件中没有数据']);
                    REMQ::setExpire($QueueName, 300);
                    continue;
                }
                // 考虑一次查询500 或 1000 个
                $field = ['plan_id', 'store_id'];//测试数据键
//                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_file'] . '_' . $plan_id;

                $tmpStoreIds = [];
                $transaction = Yii::$app->db->beginTransaction();
                if ($old_set_store_type == Plan::SET_STORE_ZFT) {
                    Plan::removeStore($plan_id);
                }
                for ($startRow = 1; $startRow <= $highestRow; $startRow += $chunkSize) {
                    LOG::log(' 队列 ' . $queString . ' 第' . $startRow . ' 行，共 ' . $highestRow . ' 行');
                    $insertData = [];
                    // Tell the Read Filter, the limits on which rows we want to read this iteration
                    $chunkFilter->setRows($startRow, $chunkSize);
                    // Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
                    $spreadsheet = $reader->load($path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $data = $worksheet->toArray(null, true, false, false);
                    //            $data = $worksheet->toArray();
                    if ($startRow == 1 && empty($data)) {
                        unlink($path);
                        REMQ::setString($QueueName, ['status' => false, 'msg' => '文件中没有数据']);
                        REMQ::setExpire($QueueName, 300);
                        LOG::log('文件中没有数据' . ' 队列 ' . $queString);
                        continue 2;
                    }

                    // 第一页，要跳过第一行，后面的页数需要把第1行也考虑进去
                    $i = $startRow == 1 ? $startRow : $startRow - 1;

                    $total = count($data);
                    $store_ids = [];
                    for (; $i < $total; $i++) {
                        $tmp = (string)$data[$i][0];
                        if ($tmp == "") {
                            continue;
                        }
                        // 9位需要补0
                        $tmp = strlen($tmp) == 9 ? '0' . $tmp : $tmp;
                        // 售点id为字符串，不校验为数字
//                        if (!is_numeric($tmp) && $tmp != '') {
//                            unlink($path);
//                            return ['status' => false, 'msg' => '第' . ($i) . '行 格式错误'];
//                        }

                        if (!in_array($tmp, $tmpStoreIds)) {
                            $store_ids[] = $tmp;
                            $tmpStoreIds[] = $tmp;
                            $insertData[] = [$plan_id, $tmp];
                        }
                    }
                    $store_ids = array_unique($store_ids);
                    // 每行售点id都检测是否在售点表中
                    Yii::$app->params['user_info'] = $user;
                    $company_code = Yii::$app->params['user_info']['company_code'];
                    $bu_code = Yii::$app->params['user_info']['bu_code'];
                    $storeArr = Store::findAllArray(['store_id' => $store_ids, 'company_code' => $company_code, 'bu_code' => $bu_code], ['id', 'store_id']);
                    $storeId = array_column($storeArr, 'store_id');
                    if (count($store_ids) != count($storeArr)) {
                        $diff = array_diff($store_ids, $storeId);
                        unlink($path);
                        REMQ::setString($QueueName, ['status' => false, 'msg' => '售点id不存在或和当前账号所属bu不一致：' . $diff[array_keys($diff)[0]]]);
                        REMQ::setExpire($QueueName, 300);
                        LOG::log('售点id不存在：' . $diff[array_keys($diff)[0]] . ' 队列 ' . $queString);
                        continue 2;
                    }
                    $progress = round(ceil(($i - 1) / ($highestRow - 1) * 100), 2);

                    $res = Yii::$app->db->createCommand()->batchInsert(PlanStoreRelation::tableName(), $field, $insertData)->execute();//执行批量添加

                    if ($res) {
                        REMQ::setString($QueueName, ['status' => true, 'msg' => '', 'progress' => $progress]);
                        // 1分钟能处理1万数据
                        REMQ::setExpire($QueueName, 60);
                    }
                }

                // 删除重复导入的数据
                Plan::removeDuplicate($plan_id);
                $transaction->commit();
                // 删除临时文件
                unlink($path);

            } catch (Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }

    /**
     * 上传检查计划售点，先入临时库
     * 包含剔除售点
     */
    public function actionUploadStoreList()
    {
        do {
            try {
//                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_file'];
                $QueueNameList = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', '', true);
                $que = Yii::$app->remq->dequeue($QueueNameList);

                if ($que == null) {
                    continue;
                }
                $queString = json_encode($que);
                $plan_id = $que['id'];
                $path = $que['path'];
                $ext = $que['ext'];
                $user = $que['user'];
                $overwrite = $que['overwrite'];     // 覆盖上传
                $old_set_store_type = $que['set_store_type'];

                $import_type = PlanStoreTmp::IMPORT_TYPE_ADD;
                if (isset($que['import_type']) && $que['import_type'] != '') {
                    $import_type = $que['import_type'];
                }

                $reader = IOFactory::createReader($ext);
//                $spreadsheet = $reader->load($path); // 载入excel文件
//                $worksheet = $spreadsheet->getActiveSheet();
//                $highestRow = $worksheet->getHighestRow(); // 总行数
                $chunkFilter = new ChunkReadFilter();
                $reader->setReadFilter($chunkFilter);
                $reader->setReadDataOnly(true);
                $chunkSize = $this->excelChunkSize;

                $worksheetData = $reader->listWorksheetInfo($path);

                $highestRow = $worksheetData[0]['totalRows'];

                $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', $plan_id . '_' . $import_type);
                if ($highestRow == 1) {
                    unlink($path);
                    REMQ::setString($QueueName, ['status' => false, 'msg' => '文件中没有数据']);
                    REMQ::setExpire($QueueName, 300);
                    continue;
                }
                // 考虑一次查询500 或 1000 个
                $field = ['plan_id', 'store_id', 'import_type'];//测试数据键
//                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_file'] . '_' . $plan_id;

                $tmpStoreIds = [];
                $transaction = Yii::$app->db->beginTransaction();
                if ($old_set_store_type == Plan::SET_STORE_ZFT) {
                }

                if ($overwrite) {
                    PlanStoreTmp::removeStore($plan_id, $import_type);
                } else {
                    // 暂时不需要
//                    PlanStoreTmp::removeFilterStore($plan_id);
                    PlanStoreTmp::deleteAll(['plan_id' => $plan_id, 'import_type' => $import_type, 'check_status' => [PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS, PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL]]);
                }
                for ($startRow = 1; $startRow <= $highestRow; $startRow += $chunkSize) {
                    LOG::log(' 队列 ' . $queString . ' 第' . $startRow . ' 行，共 ' . $highestRow . ' 行');
                    $insertData = [];
                    // Tell the Read Filter, the limits on which rows we want to read this iteration
                    $chunkFilter->setRows($startRow, $chunkSize);
                    // Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
                    $spreadsheet = $reader->load($path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $data = $worksheet->toArray(null, true, false, false);
                    //            $data = $worksheet->toArray();
                    if ($startRow == 1 && empty($data)) {
                        unlink($path);
                        REMQ::setString($QueueName, ['status' => false, 'msg' => '文件中没有数据']);
                        REMQ::setExpire($QueueName, 300);
                        LOG::log('文件中没有数据' . ' 队列 ' . $queString);
                        continue 2;
                    }

                    // 第一页，要跳过第一行，后面的页数需要把第1行也考虑进去
                    $i = $startRow == 1 ? $startRow : $startRow - 1;

                    $total = count($data);
                    $store_ids = [];
                    for (; $i < $total; $i++) {
                        $tmp = trim((string)$data[$i][0]);
                        if ($tmp == "") {
                            continue;
                        }
                        // 9位需要补0
                        $tmp = strlen($tmp) == 9 ? '0' . $tmp : $tmp;
                        // 售点id为字符串，不校验为数字
//                        if (!is_numeric($tmp) && $tmp != '') {
//                            unlink($path);
//                            return ['status' => false, 'msg' => '第' . ($i) . '行 格式错误'];
//                        }

                        if (!in_array($tmp, $tmpStoreIds)) {
                            $store_ids[] = $tmp;
                            $tmpStoreIds[] = $tmp;
                            $insertData[] = [$plan_id, $tmp, $import_type];
                        }
                    }
                    $store_ids = array_unique($store_ids);
                    // 每行售点id都检测是否在售点表中
                    Yii::$app->params['user_info'] = $user;
                    $company_code = Yii::$app->params['user_info']['company_code'];
                    $bu_code = Yii::$app->params['user_info']['bu_code'];
                    $storeArr = Store::findAllArray(['store_id' => $store_ids, 'company_code' => $company_code, 'bu_code' => $bu_code], ['id', 'store_id']);
                    $storeId = array_column($storeArr, 'store_id');
                    $res = Yii::$app->db->createCommand()->batchInsert(PlanStoreTmp::tableName(), $field, $insertData)->execute();//执行批量添加
                    PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS],
                        ['plan_id' => $plan_id, 'store_id' => $storeId, 'import_type' => $import_type]);
                    if (count($store_ids) != count($storeArr)) {
                        $diff = array_diff($store_ids, $storeId);
                        $not_find = array_values($diff);
                        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL, 'note' => '售点id不存在或和当前账号所属bu不一致'],
                            ['plan_id' => $plan_id, 'store_id' => $not_find, 'import_type' => $import_type]);
                    }
                    // 校验售点是否有冲突
                    if ($import_type == PlanStoreTmp::IMPORT_TYPE_ADD) {
                        $conflictQuery = Plan::getConflictStoreQuery($plan_id, ['t.id']);
                        if ($conflictQuery != null){
                            $conflictUpdateSql = 'update ' . PlanStoreTmp::tableName() . ' set check_status = ' .
                                PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL . ', note = "协议类非随报随拍，不同执行工具，售点不能冲突" 
                            where plan_id = ' . $plan_id . ' and import_type = ' . PlanStoreTmp::IMPORT_TYPE_ADD . ' 
                            and id in (select id from (' . $conflictQuery->createCommand()->getRawSql() . ') a)';
                            $rows = Yii::$app->db->createCommand($conflictUpdateSql)->execute();
                            LOG::log($rows);
                        }
                    }
//                    if (count($store_ids) != count($storeArr)) {
//                        $diff = array_diff($store_ids, $storeId);
//                        unlink($path);
//                        REMQ::setString($QueueName, ['status' => false, 'msg' => '售点id不存在或和当前账号所属bu不一致：' . $diff[array_keys($diff)[0]]]);
//                        REMQ::setExpire($QueueName, 300);
//                        LOG::log('售点id不存在：' . $diff[array_keys($diff)[0]] . ' 队列 ' . $queString);
//                        continue 2;
//                    }
                    $progress = round(ceil(($i - 1) / ($highestRow - 1) * 100), 2);

                    if ($res) {
                        REMQ::setString($QueueName, ['status' => true, 'msg' => '', 'progress' => $progress]);
                        // 1分钟能处理1万数据
                        REMQ::setExpire($QueueName, 90);
                    }
                }

                // 删除重复导入的数据
                PlanStoreTmp::removeDuplicate($plan_id, $import_type);
                $transaction->commit();
                // 删除临时文件
                unlink($path);
            } catch (Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }

    /**
     * 检查计划生成售点检查数据
     * 考虑失败后重试，重试次数3次
     */
    public function actionActivePlan()
    {
        do {
            try {
                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_list_task'];
//                $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_list_task');
                $tmp = Yii::$app->remq->dequeue($QueueName);
//                $tmp = json_decode('{"token":"wZSu6VY4XFgD51PcGECOyahJQvtIx2fH","tool_id":1,"task_id":"f711996ad7fa44b4afa31d2464b76626","date":"20200315"}', true);

                $time = time();
                // 有可能是重试的
                if ($tmp == null || (isset($tmp['try_time']) && $tmp['try_time'] > $this->maxTryTime)) {
                    continue;
                }
                $task_id = $tmp['task_id'];
                $date = $tmp['date'];
                $tool_id = $tmp['tool_id'];
                // 先查出启用状态 且时间范围包含 $date 的 plan
                $plansAll = Plan::find()->where(['tool_id' => $tool_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE])
                    ->andWhere(['<=', 'start_time', $date])
                    ->andWhere(['>=', 'end_time', $date])
                    ->asArray()
                    ->select(['id', 'standard_id', 'rate_type', 'rate_value', 'must_take_photo'])
                    ->indexBy('id')
                    ->all();

                if (empty($plansAll)) {
                    $msg = '【检查计划】该执行工具还没有合适的检查计划' . json_encode($tmp);
                    $this->ding->sendTxt($msg);
                    Yii::error($msg);
                    Plan::sendPost($tmp);
                    continue;
                }
                LOG::log('检查计划开始生成售点检查数据');
                $plan_ids = array_column($plansAll, 'id');
//                $plan_ids = array_column($plans, 'id');

                $standard_ids = array_column($plansAll, 'standard_id');
                // , 'standard_status' => Standard::STATUS_AVAILABLE 检查项目的状态不影响检查计划生成数据
                //取出检查项目类型用于ine项目剔除招牌
                $standardsAll = Standard::findAllArray(['id' => $standard_ids], ['id', 'scenes', 'question_manual_ir', 'question_manual', 'scenes_ir_id', 'protocol_id', 'check_type_id'], 'id');

                $protocol_ids = array_column($standardsAll, 'protocol_id');
                $protocolAll = ProtocolTemplate::findAllArray(['id' => $protocol_ids], ['id', 'activation_list'], 'id');

                $scenesAll = Scene::find()->select(['id', 'scene_type', 'scene_code'])->indexBy('id')->asArray()->all();
                $scenesCodeAll = Scene::find()->select(['id', 'scene_type', 'scene_code'])->indexBy('id')->asArray()->all();
                $scenesTypeAll = ArrayHelper::index($scenesCodeAll, 'scene_code', 'scene_type');

                // 找出执行工具需要的售点数据  需要考虑数据量太大的情况
                // 删除批次号下的旧数据
                CheckStoreScene::deleteAll(['tool_id' => $tool_id, 'task_id' => $task_id, 'date' => $date]);
                CheckStoreQuestion::deleteAll(['tool_id' => $tool_id, 'task_id' => $task_id, 'date' => $date]);
                // 查出该批次的售点总数, group by store_id 可以去重
                $storeQuery = CheckStoreList::find()->where(['tool_id' => $tool_id, 'task_id' => $task_id])->select(['store_id', 'id'])
                    ->groupBy('store_id')->indexBy('store_id')->asArray();
                $storeTotal = $storeQuery->count();
                $pageSize = 1000;
                $pageTotal = ceil($storeTotal / $pageSize);
                $storeQuery->limit($pageSize);
                $page = 0;
                LOG::log(' task_id:' . $task_id . ' 总售点数：' . $storeTotal . ' 总页数：' . $pageTotal);
                while ($page < $pageTotal) {
                    LOG::log('第 ' . $page . ' 页');
                    $storeQuery->offset($page * $pageSize);
                    $data = $storeQuery->all();

                    $store_ids = array_column($data, 'store_id');

                    Plan::generateStoreCheckData($store_ids, $tool_id, $task_id, $date, $plan_ids, $plansAll, $standardsAll, $scenesAll, $scenesCodeAll, $scenesTypeAll, [], $protocolAll);
                    $page++;
                }

                // 接口通知对方数据已经生成完成
                Plan::sendPost($tmp);

                $costTime = time() - $time;
                LOG::log($storeTotal . ' 条售点数据生成结束 task_id:' . $task_id . ' 耗时：' . $costTime);

            } catch (Exception $e) {
                if (isset($tran)) {
                    $tran->rollBack();
                }
                // 异常报错，需要推进队列重试
                if (isset($tmp)) {
                    $tmp['try_time'] = isset($tmp['try_time']) ? $tmp['try_time'] + 1 : 1;
                    Yii::$app->remq->enqueue($QueueName, $tmp);
                }
                LOG::log($e->getMessage());
                $this->ding->sendTxt($e->getMessage());
                Yii::error($e->getMessage());
                Yii::getLogger()->flush(true);
                sleep(1);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }

    /**
     * 检查计划售点下载
     */
    public function actionStoreDownload()
    {
        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_store_download_list'];
//        $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'plan_store_download_list');
        $queuePrefix = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_store_download_process_prefix'];
//        $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'plan_store_download_process_prefix');

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
                $plan_id = $bodyForm['plan_id'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1; // 表头在第几行
                $headers = [
                    'store_id' => '售点id',
                    'name' => '售点名称',
                    'location_name' => '营业所',
                    'route_name' => '线路',
                ];

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $c++;
                }
                $row = $headerRow + 1;

                $query = PlanStoreRelation::find()->where(['plan_id' => $plan_id])->limit($pageSize)->asArray();
                $count = $query->count();
                while ($page <= $totalPageNum) {
                    $query->offset(($page - 1) * $pageSize);
                    $data = $query->all();

                    // 写入excel
                    $storeIds = array_column($data, 'store_id');
                    $storeAll = \api\models\share\Store::findAllArray(['store_id' => $storeIds], ['id', 'store_id', 'name', 'location_name', 'route_name'], 'store_id');
                    foreach ($data as $index => $datum) {
                        $store_tmp = isset($storeAll[$datum['store_id']]) ? $storeAll[$datum['store_id']] : ['id' => '', 'name' => '', 'location_name' => '', 'route_name' => ''];
                        $tc = 'A';
                        foreach ($headers as $header => $header_name) {
                            if ($header == 'name') {
                                $store_name = $store_tmp['name'];
                                $sheet->setCellValue($tc . $row, $store_name);
                            } else {
                                $sheet->setCellValue($tc . $row, $store_tmp[$header]);
                            }
                            $tc++;
                        }

                        $row++;
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
                // todo 名称有可能有冲突
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id, $plan_id . '_检查计划售点数据');
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
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

    /**
     * 协议售点下载
     * 只是下载长期协议中 plan_relation表中的售点，并不是报名的售点
     */
    public function actionProtocolStoreDownload()
    {
//        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_protocol_store_download_list'];
        $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'plan_protocol_store_download_list');
//        $queuePrefix = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_protocol_store_download_process_prefix'];
        $queuePrefix = Yii::$app->remq::getQueueName('redis_queue', 'plan_protocol_store_download_process_prefix');

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
                $plan_id = $bodyForm['plan_id'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1; // 表头在第几行
                $headers = [
                    'store_id' => '售点id',
                ];

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $c++;
                }
                $row = $headerRow + 1;

                $query = PlanStoreRelation::find()->asArray()->where(['plan_id' => $plan_id]);
                //                $query = ProtocolStore::find()->where(['contract_id' => $bodyForm['contract_id'], 'store_status' => ProtocolStore::PROTOCOL_STATUS_ENABLE])->limit($pageSize)->asArray();
                $count = $query->count();
                while ($page <= $totalPageNum) {
                    $query->offset(($page - 1) * $pageSize);
                    $data = $query->all();

                    // 写入excel
                    foreach ($data as $index => $datum) {
                        $tc = 'A';
                        $sheet->setCellValue($tc . $row, $datum['store_id']);

                        $row++;
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
                // todo 名称有可能有冲突
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id, $search_task_id . '_协议签约售点数据');
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
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
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }

    /**
     * 定时任务从ZFT获取报名的售点列表
     * @param $company_bu string 厂房BU 3006_0001
     * @param $date_s string 开始时间 2020-05-01
     * @param $date_e string 结束时间 2020-05-02
     */
    public function actionProtocolStore($company_bu = '', $date_s = '', $date_e = '')
    {
        try {
            if ($date_s == '') {
                $date_s = date('Ymd', strtotime('-1 days')) . '000000';
            } else {
                $date_s = date('Ymd', strtotime($date_s)) . '000000';
            }
            if ($date_e == '') {
                // 对方接口不支持 <= 2020-05-01 23:59:59
                $date_e = date('Ymd') . '000000';
            } else {
                $date_e = date('Ymd', strtotime('+1 days', strtotime($date_e))) . '000000';
            }
            $param = [
                'beginDate' => $date_s,
                'endDate' => $date_e
            ];
            $company_code_filter = [];
            if ($company_bu != '') {
                $company_bu_arr = explode('_', $company_bu);
                $company_code_filter = ['company_code' => $company_bu_arr[0], 'bu_code' => $company_bu_arr[1]];
            }
            $company_bus = OrganizationRelation::companyBu($company_code_filter);
            foreach ($company_bus as $key => $bu) {
                // 按bu拉取报名售点数据
                $bu = explode('_', $key);
                OrganizationRelation::syncStore($bu[0], $param);
            }

        } catch (\Exception $e) {
            $this->catchError($e);
        } finally {
            Yii::$app->db->close();
        }
    }

    public function actionPlanBatchUpload()
    {
        do {
            try {
                $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'plan_batch_upload', '', true);
                $que = Yii::$app->remq->dequeue($QueueName);

                if ($que == null) {
                    continue;
                }

                $QueuePrefixName = Yii::$app->remq::getQueueName('redis_queue', 'plan_batch_upload_process_prefix');

                $queString = json_encode($que);
                $plan_batch_id = $que['id'];
                $path = $que['path'];
                $ext = $que['ext'];
//                $user = $que['user'];
//                $old_set_store_type = $que['set_store_type'];
                $reader = IOFactory::createReader($ext);
//                $spreadsheet = $reader->load($path); // 载入excel文件
//                $worksheet = $spreadsheet->getActiveSheet();
//                $highestRow = $worksheet->getHighestRow(); // 总行数
                $chunkFilter = new ChunkReadFilter();
                $reader->setReadFilter($chunkFilter);
                $reader->setReadDataOnly(true);
                $chunkSize = $this->excelChunkSize;

                $worksheetData = $reader->listWorksheetInfo($path);

                $highestRow = $worksheetData[0]['totalRows'];

                $QueueNameProgress = $QueuePrefixName . '_' . $plan_batch_id;
                if ($highestRow == 1) {
                    $this->continueFunction('', $path, $QueueNameProgress, '文件中没有数据', $que);
                    continue;
                }
                $plan_batch = PlanBatch::findOne(['id' => $plan_batch_id]);
                if ($plan_batch == null) {
                    continue;
                }
                $que['company_code'] = $plan_batch->company_code;
                $que['bu_code'] = $plan_batch->bu_code;
                $company_code = $plan_batch->company_code;
                $bu_code = $plan_batch->bu_code;
                // 考虑一次查询500 或 1000 个
                $field = ['plan_id', 'store_id'];//测试数据键
//                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_file'] . '_' . $plan_id;

                $tmpStoreIds = [];
                $oldPlan = Plan::findAllArray(['plan_batch_id' => $plan_batch_id], ['id']);
                $oldPlanIds = array_column($oldPlan, 'id');
                $transaction = Yii::$app->db->beginTransaction();
                Plan::updateAll([Plan::DEL_FIELD => Plan::DEL_STATUS_DELETE], ['id' => $oldPlanIds]);
                PlanStoreRelation::deleteAll(['plan_id' => $oldPlanIds]);
                for ($startRow = 1; $startRow <= $highestRow; $startRow += $chunkSize) {
                    LOG::log(' 队列 ' . $queString . ' 第' . $startRow . ' 行，共 ' . $highestRow . ' 行');
                    $insertData = [];   // plan_id, store_id
                    // Tell the Read Filter, the limits on which rows we want to read this iteration
                    $chunkFilter->setRows($startRow, $chunkSize);
                    // Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
                    $spreadsheet = $reader->load($path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $data = $worksheet->toArray(null, false, false, false);
                    //            $data = $worksheet->toArray();
                    if ($startRow == 1 && empty($data)) {
                        $this->continueFunction($transaction, $path, $QueueNameProgress, '文件中没有数据', $que);
                        LOG::log('文件中没有数据' . ' 队列 ' . $queString);
                        continue 2;
                    }

                    // 第一页，要跳过第一行，后面的页数需要把第1行也考虑进去
//                    $i = $startRow == 1 ? $startRow : $startRow - 1;
//                    if ($startRow == 1) {
//                        array_shift($data);
//                    }
                    array_shift($data);

                    $total = count($data);
                    $templateResult = $this->prepareData($data, $company_code, $que);
                    if ($templateResult['status'] == false) {
                        $this->continueFunction($transaction, $path, $QueueNameProgress, $templateResult['msg'], $que);
                        continue 2;
                    }
                    $templateAll = $templateResult['template'];
                    $standardAll = $templateResult['standard'];
                    $planAll = $templateResult['plan'];
                    for ($i = 0; $i < $total; $i++) {
                        $tmpContractCode = $data[$i][0];
                        $tmpStoreId = $data[$i][1];
                        if ($tmpContractCode == '' || $tmpStoreId == '') {
                            continue;
                        }
                        // 9位需要补0
                        $tmpStoreId = strlen($tmpStoreId) == 9 ? '0' . $tmpStoreId : $tmpStoreId;

                        $template = $templateAll[$tmpContractCode];
                        $standard = $standardAll[$template['id']];

                        $planAll[$standard['id']]['store_id'][] = $tmpStoreId;
                    }
                    foreach ($planAll as &$plan) {
                        $insertData = [];
                        $plan['store_id'] = array_unique($plan['store_id']);
                        $store_ids = $plan['store_id'];
                        // 每行售点id都检测是否在售点表中
                        $storeArr = Store::findAllArray(['store_id' => $store_ids, 'company_code' => $company_code, 'bu_code' => $bu_code], ['id', 'store_id']);
                        $storeId = array_column($storeArr, 'store_id');
                        if (count($store_ids) != count($storeArr)) {
                            $diff = array_diff($store_ids, $storeId);
                            $this->continueFunction($transaction, $path, $QueueNameProgress, '售点id不存在或和当前账号所属bu不一致：' . $diff[array_keys($diff)[0]], $que);
                            LOG::log('售点id不存在：' . $diff[array_keys($diff)[0]] . ' 队列 ' . $queString);
                            continue 3;
                        }
                        foreach ($store_ids as $store_id) {
                            $insertData[] = [$plan['id'], $store_id];
                        }
                        $res = Yii::$app->db->createCommand()->batchInsert(PlanStoreRelation::tableName(), $field, $insertData)->execute();//执行批量添加
                        Plan::removeDuplicate($plan['id']);
                    }

                    $progress = round(ceil(($i - 1) / ($highestRow - 1) * 100), 2);
                    if ($res) {
                        REMQ::setString($QueueNameProgress, ['status' => true, 'msg' => '', 'progress' => $progress]);
                        // 1分钟能处理1万数据
                        REMQ::setExpire($QueueNameProgress, 60);
                    }
                }

                REMQ::setString($QueueNameProgress, ['status' => true, 'msg' => '', 'progress' => 100]);
                // 1分钟能处理1万数据
                REMQ::setExpire($QueueNameProgress, 60);

                // 删除重复导入的数据
//                Plan::removeDuplicate($plan_id);
                $transaction->commit();
                // 删除临时文件
                unlink($path);
            } catch (Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }

    /**
     * 数据准备
     * @param $data
     * @param $company_code
     * @param $post_data
     * @return array
     */
    private function prepareData(&$data, $company_code, $post_data)
    {
        foreach ($data as &$datum) {
            $datum[0] = (string)$datum[0];
            $datum[1] = (string)$datum[1];
            $datum[2] = (string)$datum[2];
        }
        $result = ['status' => true, 'msg' => '', 'template' => [], 'standard' => [], 'plan' => []];
        $res = $this->protocolSync($data, $company_code);

        $result['template'] = $res['template'];
        $contract_code = $res['contract_code'];
        $protocol_id = array_column($res['template'], 'id');
        $standard = Standard::findAllArray(['company_code' => $company_code, 'protocol_id' => $protocol_id], '*', 'protocol_id');
        $contractCodeNoStandard = [];
        $contractCodeNotExist = [];
        foreach ($contract_code as $item) {
            // 协议不存在
            if (!isset($res['template'][$item])) {
                $contractCodeNotExist[] = $item;
                continue;
            }
            if (!isset($standard[$res['template'][$item]['id']])) {
                $contractCodeNoStandard[] = $item;
            }
        }
        if (!empty($contractCodeNotExist)) {
            $result['status'] = false;
            $result['msg'] = '协议code在zft不存在：' . implode(',', $contractCodeNotExist);
            return $result;
        }
        if (!empty($contractCodeNoStandard)) {
            $result['status'] = false;
            $result['msg'] = '协议code未创建检查项目：' . implode(',', $contractCodeNoStandard);
            return $result;
        }
        $result['standard'] = $standard;

        // 创建plan
        $planAll = [];
        $planErr = [];
        $planData = ['start_time' => $post_data['start_time'], 'end_time' => $post_data['end_time'], 'tool_id' => $post_data['tool_id']];
        foreach ($standard as $item) {
            $plan = new Plan();
            $plan->standard_id = $item['id'];
            $plan->company_code = $company_code;
            $plan->plan_batch_id = $post_data['id'];
            $plan->bu_code = $post_data['bu_code'];
            $plan->user_id = $post_data['user_id'];
            $plan->rectification_model = $post_data['form']['rectification_model'];
            $plan->rectification_option = $post_data['form']['rectification_option'];
            $plan->re_photo_time = $post_data['form']['rectification_option'];
            $plan->is_push_zft = $post_data['form']['is_push_zft'];
            $plan->is_qc = $post_data['form']['is_qc'];

            $plan->load($planData, '');
            if (!$plan->save()) {
                $planErr[] = $item['title'] . ' ' . $plan->getErrStr();
            }
            $planAll[$plan->standard_id] = $plan->getAttributes();
            $planAll[$plan->standard_id]['store_id'] = [];
        }
        if (!empty($planErr)) {
            $result['status'] = false;
            $result['msg'] = '检查计划创建失败：' . implode(',', $planErr);
            return $result;
        }

        $result['plan'] = $planAll;
        return $result;
    }

    private function protocolSync($data, $company_code)
    {
        $contract_code = array_column($data, 0);
        $contract_code = array_unique($contract_code);
        // 去除空字符串
        $contract_code = array_filter($contract_code);
//        foreach ($contract_code as $item) {
//            $template = Protocol::getZftTemplate($company_code, $item);
//            if (!$template) {
//                $contract_err[] = $item;
//            }
//        }
        $protocol_template = ProtocolTemplate::find()->where(['company_code' => $company_code, 'contract_code' => $contract_code])->indexBy('contract_code')
            ->asArray()->all();
        return ['template' => $protocol_template, 'contract_code' => $contract_code];
    }

    /**
     * 校验失败后，回滚
     * @param $transaction
     * @param $path
     * @param $QueueNameProgress
     * @param $msg
     * @param $que
     */
    private function continueFunction($transaction, $path, $QueueNameProgress, $msg, $que)
    {
        unlink($path);
        REMQ::setString($QueueNameProgress, ['status' => false, 'msg' => $msg]);
        REMQ::setExpire($QueueNameProgress, 300);
        if ($transaction != '') {
            $transaction->rollBack();
        }
        if ($que['form']['id'] == '') {
            $this->deletePlanBatch($que['id']);
        }
    }

    private function deletePlanBatch($plan_batch_id)
    {
        PlanBatch::updateAll([PlanBatch::DEL_FIELD => PlanBatch::DEL_STATUS_DELETE], ['id' => $plan_batch_id]);
    }

    /**
     * 检查计划已导入售点下载
     */
    public function actionExcelStoreDownload()
    {
        $headers = [
            'store_id' => '售点id',
        ];
        $title = '检查计划已导入数据';
        $this->generateDownloadFile('plan_excel_store_download_list', 'plan_excel_store_download_process_prefix', $headers, $title);


    }

    /**
     * 检查计划已导入失败售点下载
     */
    public function actionExcelStoreFailDownload()
    {
        $headers = [
            'store_id' => '售点id',
            'note' => '备注',
        ];
        $title = '检查计划失败数据';
        $this->generateDownloadFile('plan_excel_store_fail_download_list', 'plan_excel_store_fail_download_process_prefix', $headers, $title);
    }

    /**
     * 售点更新，对检查计划的售点有影响
     */
    public function actionPlanStoreRelationUpdate()
    {
        do {
            try {
                // 这里是别的系统的队列，所以不能用getQueueName
                $queueName = Yii::$app->params['redis_console_queue']['sync_store_finished'];
                $que = Yii::$app->remq->dequeue($queueName);
                if ($que == null) {
                    continue;
                }
                $company_code = json_decode($que);
                PlanService::updateCompanyPlanRelation($company_code);

            } catch (Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }
}