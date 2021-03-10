<?php


namespace console\controllers;


use api\models\Plan;
use api\models\PlanBatchTmp;
use api\models\PlanStoreRelation;
use api\models\ProtocolTemplate;
use api\models\Question;
use api\models\Standard;
use api\models\Store;
use api\models\QuestionQcIgnoreTmp;
use api\models\SurveyPlan;
use common\components\REMQ;
use common\libs\file_log\LOG;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yii;
use Exception;

class QuestionQcController extends ServiceController
{
    //批量一次导入excel到临时表的条数
    const BATCH_EXCEL_IMPORT_NUM = 100;

    /**
     * 问卷批量复核上传
     */
    public function actionSurveyIgnoreUpload()
    {
        do {
            try {
                //获取文件信息
                $excel_data = Yii::$app->remq->dequeue(Yii::$app->remq::getQueueName('redis_queue', 'question_qc_upload', '', true));
                if ($excel_data == NULL) {
                    continue;
                }
                //获取用户相关信息
                $company_code = $excel_data['user']['company_code'];
                $bu_code = $excel_data['user']['bu_code'];
                $plan_id = $excel_data['plan_id'];

                //创建reader对象，有Xls和Xlsx格式两种
                $objReader = IOFactory::createReader($excel_data['ext']);
//              //读取文件
                $objPHPExcel = $objReader->load($excel_data['path']);
                //取得总行数
                $worksheetData = $objReader->listWorksheetInfo($excel_data['path']);
                $highestRow = $worksheetData[0]['totalRows'];

                //导入进度redis key
                $progress_key_name = Yii::$app->remq::getQueueName('redis_queue', 'question_qc_upload', $excel_data['file_id']);
                if ($highestRow <= 1) {
                    unlink($excel_data['path']);
                    REMQ::setString($progress_key_name, ['status' => false, 'msg' => '文件中没有数据']);
                    REMQ::setExpire($progress_key_name, 300);
                    continue;
                }
                //循环读取excel表格，整合成数组。如果是不指定key的二维，就用$data[i][j]表示。
                $data = [];
                // 物理删除旧数据
                QuestionQcIgnoreTmp::deleteAll(['plan_id' => $plan_id]);
                for ($j = 2; $j <= $highestRow; $j++) {
                    //9位需要补0
                    $store_id = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                    $data[$j - 2] = [
                        'file_id' => $excel_data['file_id'],
                        'store_id' => strlen($store_id) == 9 ? '0' . $store_id : $store_id,    //售点编号
                        'check_status' => QuestionQcIgnoreTmp::CHECK_STATUS_PASS,  //校验状态
                        'note' => '',
                    ];

                    //循环批量插入100条数据
                    if (($j - 1) % self::BATCH_EXCEL_IMPORT_NUM == 0) {
                        $res = $this->_batchInsertTmp($data, $plan_id, $company_code, $bu_code);
                        if ($res) {
                            //更新处理进度
                            $progress = round(ceil(($j - 1) / ($highestRow - 1) * 100), 2);
                            REMQ::setString($progress_key_name, ['status' => true, 'msg' => '', 'progress' => $progress]);
                            REMQ::setExpire($progress_key_name, 90);
                        } else {
                            LOG::log(' 文件 ' . $excel_data['path'] . ' 第' . ($j - self::BATCH_EXCEL_IMPORT_NUM) . ' 行 - ' . $j . ' 行数据批量插入失败');
                        }
                        $data = [];
                    }
                }
                //批量插入最后几十条数据
                if ($data) {
                    $res = $this->_batchInsertTmp($data, $plan_id, $company_code, $bu_code);
                    if ($res) {
                        //处理完成
                        REMQ::setString($progress_key_name, ['status' => true, 'msg' => '', 'progress' => 100]);
                        REMQ::setExpire($progress_key_name, 300);
                    }
                }
                unlink($excel_data['path']);
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
     * 协议门店关系导入失败数据下载
     */
    public function actionFailDownload()
    {
        $headers = [
            'store_id' => '门店编码',
            'note' => '备注',
        ];
        $title = '问卷qc导入失败数据';
        $this->generateDownloadFile('question_qc_fail_download', 'question_qc_fail_download_progress', $headers, $title);
    }

    /**
     * 批量插入临时表
     *
     * @param $data
     * @param $plan_id
     * @param $company_code
     * @param $bu_code
     * @return int
     * @throws \yii\db\Exception
     */
    private function _batchInsertTmp($data, $plan_id, $company_code, $bu_code)
    {
        //每行售点id都检测是否在售点表中
        $store_ids = array_column($data, 'store_id');
        $stores = Store::findAllArray(['store_id' => $store_ids, 'company_code' => $company_code, 'bu_code' => $bu_code], ['id', 'store_id'], 'store_id');
        foreach ($data as &$item) {
            //售点id不存在或和当前账号所属bu不一致
            if (!isset($stores[$item['store_id']])) {
                $item['check_status'] = QuestionQcIgnoreTmp::CHECK_STATUS_FAIL;
                $item['note'] = '售点id不存在或和当前账号所属bu不一致';
                continue;
            } else {
                $item['check_status'] = QuestionQcIgnoreTmp::CHECK_STATUS_PASS;
                $item['note'] = '';
            }
        }
        unset($item);
        //每行售点id都检测是否关联检查计划
        $plan_store_relations = PlanStoreRelation::findAllArray(['store_id' => $store_ids, 'plan_id' => $plan_id], ['id', 'store_id'], 'store_id');
        foreach ($data as &$item) {
            if ($item['check_status'] == QuestionQcIgnoreTmp::CHECK_STATUS_PASS) {
                //售点id不在走访数据中
                if (!isset($plan_store_relations[$item['store_id']])) {
                    $item['check_status'] = QuestionQcIgnoreTmp::CHECK_STATUS_FAIL;
                    $item['note'] = '售点id不在走访数据中';
                    continue;
                } else {
                    $item['check_status'] = QuestionQcIgnoreTmp::CHECK_STATUS_PASS;
                    $item['note'] = '';
                }
            }
        }
        unset($item);
        //获取检查计划
        $plan = Plan::findOneArray(['id' => $plan_id]);
        if ($plan['question_model'] == Plan::FRONT_SAFE_BACK_REQUIRED || $plan['question_model'] == Plan::FRONT_NOT_BACK_REQUIRED) {
            //校验售点下走访对应的问卷是否全为判断题
            foreach ($data as &$item) {
                if ($item['check_status'] == QuestionQcIgnoreTmp::CHECK_STATUS_PASS) {
                    //根据检查计划ID和售点ID查到对应所有走访
                    $survey_plan_list = SurveyPlan::find()
                        ->with(['questionAnswerQc', 'questionAnswerQc.question'])
                        ->where([
                            'plan_id' => $plan_id,
                            'store_id' => $item['store_id'],
                            'need_question_qc' => SurveyPlan::NEED_QC_YES,
                            'question_qc_status' => SurveyPlan::QUESTION_QC_STATUS_DEFAULT,
                        ])->asArray()->all();
                    //查看走访对应的所有问卷，判断问卷是否都为判断题，如果是则能批量走访，反之则不能
                    foreach ($survey_plan_list as $survey_plan) {
                        foreach ($survey_plan['questionAnswerQc'] as $question_qc_list) {
                            if ($question_qc_list['question'] && $question_qc_list['question']['question_type'] != Question::QUESTION_TYPE_BOOL) {
                                $item['check_status'] = QuestionQcIgnoreTmp::CHECK_STATUS_FAIL;
                                $item['note'] = '该售点下的走访数据中问卷不都为判断题';
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        $time = time();
        $field = ['file_id', 'plan_id', 'store_id', 'check_status', 'note', 'created_at', 'updated_at'];
        $insert_data = [];
        foreach ($data as $i => $v) {
            $insert_data[$i] = [$v['file_id'], $plan_id, $v['store_id'], $v['check_status'], $v['note'], $time, $time];
        }
        //批量插入
        return Yii::$app->db->createCommand()->batchInsert(QuestionQcIgnoreTmp::tableName(), $field, $insert_data)->execute();
    }
}