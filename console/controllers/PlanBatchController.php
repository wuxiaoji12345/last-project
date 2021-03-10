<?php


namespace console\controllers;


use api\models\Plan;
use api\models\PlanBatchTmp;
use api\models\ProtocolTemplate;
use api\models\Standard;
use api\models\Store;
use common\components\REMQ;
use common\libs\error\ConsoleErrorHandler;
use common\libs\file_log\LOG;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yii;
use Exception;

class PlanBatchController extends ServiceController
{
    //批量一次导入excel到临时表的条数
    const BATCH_EXCEL_IMPORT_NUM = 100;

    private $note = '';
    /**
     * 协议门店关系文件上传处理
     */
    public function actionExcelImport()
    {
        do {
            try {
                //获取上传协议门店关系文件信息
                $excel_data = Yii::$app->remq->dequeue(Yii::$app->remq::getQueueName('queue_plan_batch_tmp', '', '', false));
                if ($excel_data == NULL) {
                    continue;
                }
                //获取用户相关信息
                $company_code = $excel_data['user']['company_code'];
                $bu_code      = $excel_data['user']['bu_code'];

                //创建reader对象，有Xls和Xlsx格式两种
                $objReader = IOFactory::createReader($excel_data['ext']);
//              //读取文件
                $objPHPExcel = $objReader->load($excel_data['path']);
                //取得总行数
                $worksheetData = $objReader->listWorksheetInfo($excel_data['path']);
                $highestRow = $worksheetData[0]['totalRows'];

                //导入进度redis key
                $progress_key_name = Yii::$app->remq::getQueueName('queue_plan_batch_tmp', '', $excel_data['file_id']);
                if ($highestRow <= 1) {
                    unlink($excel_data['path']);
                    REMQ::setString($progress_key_name, ['status' => false, 'msg' => '文件中没有数据']);
                    REMQ::setExpire($progress_key_name, 300);
                    continue;
                }
                //循环读取excel表格，整合成数组。如果是不指定key的二维，就用$data[i][j]表示。
                $data = [];
                $codes = [];
                for ($j = 2; $j <= $highestRow; $j++) {
                    //9位需要补0
                    $store_id = intval($objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue());
                    $contract_code = intval($objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue());
                    $codes[$contract_code] = 1;
                    $data[$j - 2] = [
                        'file_id' => $excel_data['file_id'],
                        'contract_code' => $contract_code,  //协议编号
                        'store_id' => strlen($store_id) == 9 ? '0' . $store_id : $store_id,    //售点编号
                        'check_status' => PlanBatchTmp::CHECK_STATUS_PASS,  //校验状态
                        'note' => '',
                    ];

                    //循环批量插入100条数据
                    if (($j - 1) % self::BATCH_EXCEL_IMPORT_NUM == 0) {
                        $res = $this->_batchInsertTmp($data, $company_code, $bu_code);
                        if ($res) {
                            //更新处理进度
                            $progress = round(ceil(($j - 1) / ($highestRow - 1) * 100), 2);
                            REMQ::setString($progress_key_name, ['status' => true, 'msg' => '', 'progress' => $progress]);
                            REMQ::setExpire($progress_key_name, 90);
                        }
                        else {
                            LOG::log(' 文件 ' . $excel_data['path'] . ' 第' . ($j - self::BATCH_EXCEL_IMPORT_NUM) . ' 行 - ' . $j . ' 行数据批量插入失败');
                        }
                        $data = [];
                    }
                }
                //批量插入最后几十条数据
                if ($data) {
                    $res = $this->_batchInsertTmp($data, $company_code, $bu_code);
                    if ($res) {
                        //处理完成
                        REMQ::setString($progress_key_name, ['status' => true, 'msg' => $this->note, 'progress' => 100]);
                        REMQ::setExpire($progress_key_name, 90);
                    }
                }
                unlink($excel_data['path']);
            } catch (Exception $e) {
                (new ConsoleErrorHandler())->renderException($e);
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
    public function actionExcelImportFailDownload()
    {
        $headers = [
            'contract_code' => '协议编码',
            'store_id' => '门店编码',
            'note' => '备注',
        ];
        $title = '协议门店关系导入失败数据';
        $this->generateDownloadFile('plan_batch_excel_import_fail_download_list', 'plan_batch_excel_import_fail_download_process_prefix', $headers, $title);
    }

    /**
     * 批量插入临时表
     *
     * @param $data
     * @param $company_code
     * @param $bu_code
     * @return int
     * @throws \yii\db\Exception
     */
    private function _batchInsertTmp($data, $company_code, $bu_code)
    {
        //数据逻辑校验
        //批量获取协议模板数据
        $contract_codes     = array_unique(array_column($data, 'contract_code'));
        $protocol_templates = ProtocolTemplate::find()->where(['company_code' => $company_code, 'contract_code' => $contract_codes])->indexBy('contract_code')->asArray()->all();
        //批量获取成功图像标准数据
        $protocol_ids = array_column($protocol_templates, 'id');
        $standards    = Standard::findAllArray(['company_code' => $company_code, 'protocol_id' => $protocol_ids, 'status' => Standard::DEL_STATUS_NORMAL, 'standard_status' => Standard::STATUS_AVAILABLE], '*', 'protocol_id');
        // 判断所有协议是否一致
        $this->f($standards, $protocol_templates);
        // 每行售点id都检测是否在售点表中
        $store_ids = array_column($data, 'store_id');
        $stores    = Store::findAllArray(['store_id' => $store_ids, 'company_code' => $company_code, 'bu_code' => $bu_code], ['id', 'store_id'], 'store_id');
        foreach ($data as &$item) {
            //协议code在zft不存在
            if (!isset($protocol_templates[$item['contract_code']])) {
                $item['check_status'] = PlanBatchTmp::CHECK_STATUS_FAIL;
                $item['note']         = '协议code在zft不存在';
                continue;
            }
            //协议code未创建检查项目
            if (!isset($standards[$protocol_templates[$item['contract_code']]['id']])) {
                $item['check_status'] = PlanBatchTmp::CHECK_STATUS_FAIL;
                $item['note']         = '协议code未创建检查项目';
                continue;
            } else {
                if ($standards[$protocol_templates[$item['contract_code']]['id']]['standard_status'] != Standard::STATUS_AVAILABLE) {
                    $item['check_status'] = PlanBatchTmp::CHECK_STATUS_FAIL;
                    $item['note'] = '检查项目(' . $standards[$protocol_templates[$item['contract_code']]['id']]['title'] . ')尚未启用';
                    continue;
                }
            }
            //售点id不存在或和当前账号所属bu不一致
            if (!isset($stores[$item['store_id']])) {
                $item['check_status'] = PlanBatchTmp::CHECK_STATUS_FAIL;
                $item['note']         = '售点id不存在或和当前账号所属bu不一致';
                continue;
            }
            if (!empty($this->note)) {
                $item['check_status'] = PlanBatchTmp::CHECK_STATUS_FAIL;
                $item['note']         = $this->note;
                continue;
            }
        }
        $field       = ['file_id', 'contract_code', 'store_id', 'check_status', 'note'];
        $insert_data = [];
        foreach ($data as $i => $v) {
            $insert_data[$i] = [$v['file_id'], $v['contract_code'], $v['store_id'], $v['check_status'], $v['note']];
        }
        //批量插入
        return Yii::$app->db->createCommand()->batchInsert(PlanBatchTmp::tableName(), $field, $insert_data)->execute();
    }

    /**
     * 判断协议code是否一致
     * @param $standards
     * @param $protocol_templates
     */
    private function f($standards, $protocol_templates)
    {
        if (empty($this->note)) {
            // 是否都有ir或是否都没有
            $protocols = array_combine(array_column($protocol_templates, 'id'), array_keys($protocol_templates));
            $cur = current($standards);
            foreach ($standards as $standard) {
                $ir = !empty(json_decode($standard['question_manual_ir'], true));
                $not_ir = !empty(json_decode($standard['question_manual'], true));
                if ($ir || $not_ir) {//只要一个不为空
                    $have = true;
                } else {
                    $have = false;
                }
                $cur_ir = !empty(json_decode($cur['question_manual'], true));
                $cur_not_ir = !empty(json_decode($cur['question_manual'], true));
                if ($cur_ir || $cur_not_ir) {//只要一个不为空
                    $cur_have = true;
                } else {
                    $cur_have = false;
                }
                if ($cur_have != $have) {
                    $have = $have ? '有' : '无';
                    $cur_have = $cur_have ? '有' : '无';
                    $this->note = "协议{$protocols[$standard['protocol_id']]}{$have}问卷，协议{$protocols[$cur['protocol_id']]}{$cur_have}问卷，不可一起批量创建检查计划";
                }
                $cur = $standard;
            }
        }
    }
}