<?php

namespace console\controllers;

use api\models\EngineResult;
use api\models\Plan;
use api\models\ResultNode;
use api\models\RuleOutputInfo;
use api\models\share\Store;
use api\models\share\StoreBelong;
use api\models\Standard;
use api\models\Survey;
use api\models\Tools;
use common\libs\sftp\SFTP;
use Yii;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use ZipArchive;

class RectifyController extends BaseController
{
    const batchAmount = 1000;       // 批量处理引擎结果的数量
    const header = [
        'survey_id',
        'company_code',
        'outlet_no',
        'suc_pic_sk',
        'pos_name',
        'last_check_date',
        'real_score',
        'pos_score',
        'fraction_defective',
        'begin_date',
        'end_date',
        'survey_system',
        'no_check_code',
        'no_check_desc',
        'create_date'
    ];
    const item = [
        'survey_id',
        'suc_pic_sk',
        'kbi_sk',
        'item_name',
        'defective_repeat',
        'create_date'
    ];

    /**
     * 生成整改数据
     * 先查出组织架构表中的厂房列表，每个厂房单独查数据，如果为空，也要生成对应的文件
     * 数据上传完之后，上传一个日期维度的文件名格式为 red-done-2020-02-28.txt 的文件
     * runtime目录生成，然后推送sftp，再删除runtime中的文件
     * @param string $date
     */
    public function actionIndex($date = '')
    {
        try {
            $sftp = SFTP::getInstance();
            $exist = $sftp->ssh2_dir_exits('ftp');
            if (!$exist) {
                $success = $sftp->ssh2_sftp_mchkdir('/ftp');
                if (!$success) {
                    $this->ding->sendTxt('【整改对接】sftp目录创建失败： ftp');
                }
            }

            $yesterday = $date == '' ? date('Y-m-d', strtotime('-1 days')) : $date;
            $exist = $sftp->ssh2_dir_exits('ftp/' . $yesterday);
            if (!$exist) {
                $success = $sftp->ssh2_sftp_mchkdir('/ftp/' . $yesterday);
                if (!$success) {
                    $this->ding->sendTxt('【整改对接】sftp目录创建失败： ftp/' . $yesterday);
                }
            }
            $runtimePath = Yii::getAlias('@runtime');
            // 查找出厂房列表
            $companyAll = StoreBelong::findAllArray(['type' => StoreBelong::TYPE_COMPANY]);

            foreach ($companyAll as $company) {
                $headerFile = 'check_fail_result_header_' . $company['code'] . '_' . $yesterday;
                $headerPath = $runtimePath . DIRECTORY_SEPARATOR . $headerFile . '.txt';
                $headerZipPath = $runtimePath . DIRECTORY_SEPARATOR . $headerFile . '.zip';
                $itemFile = 'check_fail_result_item_' . $company['code'] . '_' . $yesterday;
                $itemPath = $runtimePath . DIRECTORY_SEPARATOR . $itemFile . '.txt';
                $itemZipPath = $runtimePath . DIRECTORY_SEPARATOR . $itemFile . '.zip';
                // 先生成文件
                touch($headerPath);
                touch($itemPath);

                $handleHeader = fopen($headerPath, "a+");
                $handleItem = fopen($itemPath, "a+");

                // 写header表头
                $write = self::header;
                $string = implode($write, '^');
                fwrite($handleHeader, $string . "\n");

                // 写item表头
                $write = self::item;
                $string = implode($write, '^');
                fwrite($handleItem, $string . "\n");

                // 查询出 sys_engine_result 表，前一天出引擎结果的数据

                // 这里分页处理，有可能一天的数据量太多，每批
                $page = 1;
                $query = EngineResult::find()->where(['and',
                    ['in', new Expression(Survey::tableName() . '.tool_id'), [Tools::TOOL_ID_SEA, Tools::TOOL_ID_MEDI_SEA]],
                    ['=', new Expression(EngineResult::tableName() . '.status'), EngineResult::DEL_STATUS_NORMAL],
                    ['=', new Expression(Survey::tableName() . '.status'), Survey::DEL_STATUS_NORMAL],
                    ['result_status' => EngineResult::RESULT_STATUS_DONE],
                    ['between', 'result_time', $yesterday . ' 00:00:00', $yesterday . ' 23:59:59'],
                    ['=', new Expression(Survey::tableName() . '.company_code'), $company['code']],
                    ['and', ['or',
                        ['=', new Expression(EngineResult::tableName() . '.is_need_qc'), EngineResult::IS_NEED_QC_NO],
                        ['and',
                            ['=', new Expression(EngineResult::tableName() . '.is_need_qc'), EngineResult::IS_NEED_QC_YES],
                            ['<>', new Expression(EngineResult::tableName() . '.qc_result'), EngineResult::ENGINE_RESULT_QC_DEFAULT]
                        ]
                    ]]
                ])
                    ->select(
                        [
                            new Expression(EngineResult::tableName() . '.id'),
                            new Expression(EngineResult::tableName() . '.survey_code'),
                            new Expression(EngineResult::tableName() . '.plan_id'),
                            'tool_id', 'survey_time', 'survey_id', 'standard_id', 'result', 'qc_result']
                    )->orderBy(['result_time' => SORT_ASC])->asArray();
                $query->joinWith('survey');
                $query->joinWith('survey.store');

                $total = $query->count();
                $maxPage = ceil($total / self::batchAmount);
                $query->limit(self::batchAmount);

                while ($page <= $maxPage) {
                    $data = $query->offset(($page - 1) * self::batchAmount)->all();
                    $standard_ids = array_column($data, 'standard_id');
                    $standard_ids = array_unique($standard_ids);
                    $rule_output_main = RuleOutputInfo::findAllArray(['standard_id' => $standard_ids, 'is_main' => RuleOutputInfo::IS_MAIN_YES],
                        ['id', 'standard_id', 'node_index', 'node_name', 'output_type', 'is_main', 'is_score', 'sort_id']);
                    $rule_output_score = RuleOutputInfo::findAllArray(['standard_id' => $standard_ids, 'is_score' => RuleOutputInfo::IS_SCORE_YES],
                        ['id', 'standard_id', 'node_index', 'node_name', 'output_type', 'is_main', 'is_score', 'sort_id'], 'standard_id');
                    // 查询走访表数据
                    $survey_codes = array_column($data, 'survey_code');
                    $surveyPage = Survey::findAllArray(['survey_code' => $survey_codes], ['id', 'survey_code', 'store_id', 'survey_date', 'survey_time',], 'survey_code');
                    // 查询售点数据
                    $store_ids = array_column($surveyPage, 'store_id');
                    $store_ids = array_unique($store_ids);
                    $stores = Store::findAllArray(['store_id' => $store_ids], ['id', 'store_id', 'company_code'], 'store_id');
                    // 查询检查项目
                    $standards = Standard::findAllArray(['id' => $standard_ids], ['id', 'title', 'pos_score'], 'id');
                    // 查询检查计划
                    // 失败次数
                    $check_main_ids = array_column($rule_output_main, 'id');
                    $failPage = ResultNode::findAllArray(['rule_output_node_id' => $check_main_ids], ['*'], 'rule_output_node_id');

                    //  写入header
                    foreach ($data as $datum) {
                        $headerWrote = false;
                        $result = json_decode($datum['result'], true);
                        if ($datum['qc_result'] != null) {
                            $qc_result = json_decode($datum['qc_result'], true);
                            $result = \Helper::objectArrayMerge($qc_result, $result, 'node_index', 'output');
                        }
                        $result = ArrayHelper::index($result, 'node_index');
                        //主检查项且失败了，就要整改
                        //是否失败，看output字段为0的，就是失败
                        // 找出主检查项
                        $standard_items = ArrayHelper::index($rule_output_main, 'node_index', 'standard_id');
                        if (!isset($standard_items[$datum['standard_id']])) {
                            continue;
                        }
                        $checkOutputInfo = $standard_items[$datum['standard_id']];
                        $plan = Plan::findOneArray(['standard_id' => $datum['standard_id'], 'tool_id' => $datum['tool_id']], ['id', 'standard_id', 'start_time', 'end_time']);

                        if ($plan == null) {
                            continue;
                        }
                        foreach ($checkOutputInfo as $nodeMain) {
                            // 取 result 中的 output
                            // 为0就是失败
                            if (!isset($result[$nodeMain['node_index']])) {
                                continue;
                            }
                            if ($result[$nodeMain['node_index']]['output'] == 0) {
                                // 计算 real_score 真实得分
                                // 找出得分项，有可能检查项目未配置得分项传空
                                if (isset($rule_output_score[$nodeMain['standard_id']]) && isset($result[$rule_output_score[$nodeMain['standard_id']]['node_index']])) {
                                    $nodeScore = $result[$rule_output_score[$nodeMain['standard_id']]['node_index']];
                                    $real_score = $nodeScore['output'];
                                } else {
                                    $real_score = '';
                                }

                                $store = $stores[$surveyPage[$datum['survey_code']]['store_id']];
                                if (!$headerWrote) {
                                    // 和上面 header表头顺序一致
                                    $oneHeader = [
                                        $datum['survey_code'],
                                        $store['company_code'],     // store 表
                                        $store['store_id'],// store 表
                                        $datum['standard_id'],
                                        $standards[$datum['standard_id']]['title'],     // standard 表
                                        $surveyPage[$datum['survey_code']]['survey_time'], // survey 表
                                        $real_score,       // 计算
                                        $standards[$datum['standard_id']]['pos_score'],        // standard 表
                                        '',                 // 空 fraction_defective
                                        substr($plan['start_time'], 0, 10),       // plan 表
                                        substr($plan['end_time'], 0, 10),         // plan 表
                                        '2',                // 固定为2
                                        '',        // todo 先空着 no_check_code
                                        '',        // todo 先空着 no_check_desc
                                        $surveyPage[$datum['survey_code']]['survey_time']                  // 空 先放走访时间
                                    ];

                                    // header
                                    $oneHeader = implode($oneHeader, '^');

                                    fwrite($handleHeader, $oneHeader . "\n");
                                    $headerWrote = true;
                                }

                                // 查询失败次数
                                // 写入item
                                $oneItem = [
                                    $datum['survey_code'],
                                    $datum['standard_id'],
                                    $nodeMain['id'],
                                    $nodeMain['node_name'],
                                    isset($failPage[$nodeMain['id']]) ? $failPage[$nodeMain['id']]['fail_count'] : 1,
                                    $surveyPage[$datum['survey_code']]['survey_time']  // 空 先放走访时间
                                ];
                                $oneItem = implode($oneItem, '^');

                                fwrite($handleItem, $oneItem . "\n");
                            }
                        }
                    }
                    $page++;
                }

                fclose($handleHeader);
                fclose($handleItem);

                // zip压缩
                $this->zipFile($headerZipPath, $headerPath, $headerFile);
                $headerUpload = $sftp->upftp($headerZipPath, 'ftp/' . $yesterday . '/' . $headerFile . '.zip');

                // 删除上传的文件
                if ($headerUpload) {
                    unlink($headerPath);
                    unlink($headerZipPath);
                } else {
                    $this->ding->sendTxt('【整改对接】文件上传失败：' . $headerPath);
                }

                $this->zipFile($itemZipPath, $itemPath, $itemFile);
                // 考虑到文件大小比较大，如果header 上传成功，item上传失败，header 文件不删除
                $itemUpload = $sftp->upftp($itemZipPath, 'ftp/' . $yesterday . '/' . $itemFile . '.zip');
                if ($itemUpload) {
                    unlink($itemPath);
                    unlink($itemZipPath);
                } else {
                    $this->ding->sendTxt('【整改对接】文件上传失败：' . $itemUpload);
                }
            }

            $doneFile = 'red-done-' . $yesterday . '.txt';
            $donePath = $runtimePath . DIRECTORY_SEPARATOR . $doneFile;
            touch($donePath);
            $sftp->upftp($donePath, 'ftp/' . $doneFile);
            unlink($donePath);
        } catch (\Exception $e) {
            $this->catchError($e);
            if (isset($headerPath) && file_exists($headerPath)) {
                unlink($headerPath);
            }
            if (isset($itemPath) && file_exists($itemPath)) {
                unlink($itemPath);
            }
        } finally {
            Yii::$app->db->close();
            Yii::$app->db2->close();
        }
    }

    /**
     * 压缩文件
     * @param $zipPath string zip路径
     * @param $filePath string 需要压缩的文件路径
     * @param $fileName string 文件压缩到 zip 文件的路径
     */
    private function zipFile($zipPath, $filePath, $fileName)
    {
        $zipTmpFile = new ZipArchive();
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        $zipTmpFile->open($zipPath, ZipArchive::CREATE);
        $zipTmpFile->addFile($filePath, $fileName . '.txt');
        $zipTmpFile->close();
    }
}