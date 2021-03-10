<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

use api\models\ActivationSendZftInfo;
use api\models\EngineResult;
use api\models\Image;
use api\models\ImageReport;
use api\models\ImageUrl;
use api\models\IneConfigSnapshot;
use api\models\Plan;
use api\models\PlanStoreRelation;
use api\models\ProtocolStore;
use api\models\ProtocolTemplate;
use api\models\QuestionAnswer;
use api\models\QuestionAnswerQc;
use api\models\Replan;
use api\models\ReplanSurvey;
use api\models\Standard;
use api\models\StatisticalItem;
use api\models\Store;
use api\models\SubActivity;
use api\models\Survey;
use api\models\ResultNode;
use api\models\RuleOutputInfo;
use api\models\SurveyIneChannel;
use api\models\SurveyPlan;
use api\models\SurveyQuestion;
use api\models\SurveyStandard;
use api\models\SurveySubActivity;
use api\models\Tools;
use api\service\plan\PlanService;
use api\service\qc\QuestionQcService;
use api\service\qc\ReviewService;
use api\service\zft\Protocol;
use api\service\zft\SendService;
use common\components\COS;
use common\libs\ding\Ding;
use common\libs\file_log\LOG;
use linslin\yii2\curl;
use Yii;
use Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

class ImageServiceController extends ServiceController
{
    //大中台相关
    private $calculationOutput = 'CALCULATION_RESULT_OUTPUT_';
    private $cp_id = 2;
    private $cosOutput = 'REMQ_COS_OUTPUT_';
    private $calculationResultOutput = 'REMQ_CALCULATION_RESULT_OUTPUT_';

    /*
    * 生动化结果
    */
    const ACTIVATION_RESULT_DEFAULT = 0; //待检查
    const ACTIVATION_RESULT_ING = 1; //检查中
    const ACTIVATION_RESULT_SUCCESS = 2; //检查合格
    const ACTIVATION_RESULT_FAIL = 3; //检查不合格


    const OUTPUT_SUCCESS = 1; //输出状态成功
    const OUTPUT_FAIL = 0; //输出状态失败

    const REGULAR_ZFT_TIME = '-30 day'; //输出状态成功

    /**
     * 将图像上传cos后，送图像识别
     */
    public function actionDequeueCos()
    {
        do {
            try {
                $projectId = Yii::$app->params['project_id'];
                $result = Yii::$app->remq->dequeue(Yii::$app->params['queue']['cos'] . $projectId);
                if ($result) {
                    $is_key = isset($result['is_key']) ? $result['is_key'] : 0;
                    $count = count($result['image']);
                    $transaction = Yii::$app->getDb()->beginTransaction();
                    //问卷结果入库
                    $re = $this->saveQuestionImage($result);
                    if (!$re['status']) {
                        $transaction->rollBack();
                        Yii::error($re['msg']);
                        continue;
                    }
                    if ($count > 0) {
                        //根据image_id查询所有image_url记录
                        $image_url_list = ImageUrl::find()->where(['image_id' => $result['image_id']])->all();
                        //循环上传所有图片到cos太古桶根目录，并更新image_url表记录
                        foreach ($image_url_list as $image_url) {
                            //判断图片地址是否符合cos太古桶根目录形式，如果符合则无需重复上传，不符合则上传
                            $parse_image_url = parse_url($image_url['image_url']);
                            if ($parse_image_url['host'] == 'snapshot-swire-1255412942.cos.ap-shanghai.myqcloud.com' && count(explode('/', trim($parse_image_url['path'], '/'))) == 1) {
                                $tmp_image_key = trim($parse_image_url['path'], '/');
                            } else {
                                $upload_result = Cos::saveImage($image_url['image_url'], 'MES_IMAGE', 3);
                                if (!$upload_result['status']) {
                                    throw new \Exception('图片上传cos失败, image_id:' . $result['image_id'] . ',image_url_id:' . $image_url['id'] . ',错误信息:' . $upload_result['msg']);
                                }
                                $tmp_image_key = $upload_result['key'];
                            }
                            //更新image_url记录
                            $image_url->image_key = $tmp_image_key;
                            if (!$image_url->save()) {
                                throw new \Exception('image_url记录更新失败,image_url_id:' . $image_url['id'] . ',错误信息:' . $image_url->getErrStr());
                            }
                        }
                        //更新image表count字段
                        $image_update_result = Image::saveCosKey($result['image_id'], '', $count);
                        if (!$image_update_result[0]) {
                            throw new \Exception('image记录更新失败,image_id' . $result['image_id'] . ',错误信息:' . $image_update_result[1]);
                        }
                        //更新图片识别报告状态并送识别服务
                        if ($result['img_type'] != 1) {
                            $image_report_update_result = ImageReport::changeReportStatus(['id' => $result['image_report_id']], ImageReport::REPORT_STATUS_DOING);
                            if (!$image_report_update_result[0]) {
                                throw new \Exception('image_report记录更新失败,image_report_id' . $result['image_report_id'] . ',错误信息:' . $image_report_update_result[1]);
                            }
                            //送识别服务
                            $this->sendDistinguish($result['image'], $result['image_id'], $result['survey_code'], $result['store_id'], $result['scene_id']);
                        }
                    }
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if (isset($transaction)) {
                    $transaction->rollBack();
                }
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
            }
            pcntl_signal_dispatch();
        } while ($this->runnable);
    }

    /**
     * 走访完成创建任务，判断条件送引擎计算
     */
    public function actionDequeueCalculationTask()
    {
        do {
            try {
                $projectId = Yii::$app->params['project_id'];
                $result = Yii::$app->remq->dequeue(Yii::$app->params['queue']['calculation_task'] . $projectId);
                if ($result) {
                    if (!isset($result['qc_done'])) {
                        //将这些需要处理的问题从完成接口移至此处
                        $need_qc = $this->saveFinishData($result);
                        //需要qc的话就跳过本次送规则引擎
                        if ($need_qc) continue;
                    }
                    $time = 1;
                    while (1) {
                        $where = [];
                        $where[] = 'and';
                        $where[] = ['=', 'survey_id', (string)$result['survey_id']];
                        $where[] = ['<', 'report_status', 2];
                        $report = ImageReport::checkReportStatus($where);
                        if (empty($report)) {
                            $queue = $this->newSendReportToCalculation($result);
                            break;
                        } else {
                            sleep(10);
                            $time++;
                            if ($time > 20) {
                                Yii::error('图片识别超时，图片id为' . $report['photo_id']);
                                //更新走访表发送规则引擎状态字段
                                $status = Survey::SEND_ENGINE_TIME_OUT;
                                Survey::updateAll(['send_engine' => $status], ['survey_code' => $result['survey_id']]);
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
            }
            pcntl_signal_dispatch();
        } while ($this->runnable);
    }

    /**
     * 接收引擎计算结果
     */
    public function actionDequeueCalculationResult()
    {
        do {
            try {
                $projectId = Yii::$app->params['project_id'];
                $name = $this->calculationOutput . $projectId;
                $result = Yii::$app->remq->dequeueEngineCalculation($name);
                LOG::log('接收到引擎计算结果' . json_encode($result));
                $transaction = Yii::$app->getDb()->beginTransaction();
                if ($result) {
                    if (isset($result['context']['standard_id'])) {
                        //如果是执行工具sea或sea高管的通知执行工具
                        if (in_array($result['context']['tool_id'], Tools::ALL_INE_TOOL_ID)) {
                            $data['surveyCode'] = $result['context']['survey_code'];
                            $url = Yii::$app->params['sea_url'] . '/report/complete';
                            \Helper::curlQueryLog($url, $data, true);
                        }
                        //更新走访表发送规则引擎状态字段
                        $status = Survey::SEND_ENGINE_HAS_RESULT;
                        Survey::updateAll(['send_engine' => $status], ['survey_code' => $result['context']['survey_code']]);
                        //更新走访表发送规则引擎状态字段
                        $status = Survey::SEND_ENGINE_HAS_RESULT;
                        Survey::updateAll(['send_engine' => $status], ['survey_code' => $result['context']['survey_code']]);
                        //更新检查项失败统计
                        $this->updateResultNode($result);
                        //生成走访检查项目和子活动快照
                        $this->createSurveySnapshot($result['context']['survey_code'], $result['context']['standard_id']);
                        //判断生动化是否都合格
                        $this->updatePassStatus($result['output_list'], $result['context']['survey_code'], $result['context']['standard_id']);
                        //判断是否为整改拍照
                        $this->updateIsRectify($result['context']['plan_id'], $result['context']['store_id'], $result['context']['survey_code'], $result['context']['standard_id']);
                        //发送协议类检查规则引擎结果给ZFT
                        $where = ['s.id' => $result['context']['standard_id']];
                        //标注ine走访
                        $type = Standard::getStandardCheckType($where);
                        if ($type['check_type_id'] == Survey::CHECK_TYPE_INE_ID) {
                            Survey::updateAll(['is_ine' => Survey::IS_INE_YES], ['survey_code' => $result['context']['survey_code']]);
                        }
                        //由plan里的是否要qc和是否推zft判断是否在此推送zft
                        $plan_info = Plan::findOneArray(['id' => $result['context']['plan_id']], ['is_push_zft', 'is_qc']);
                        if ($plan_info['is_push_zft'] == Plan::IS_PUSH_ZFT_YES && $plan_info['is_qc'] == Plan::IS_QC_NO) {
                            $result['context']['protocol_id'] = $type['protocol_id'];
                            $result['context']['company_code'] = $type['company_code'];
                            $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['send_zft'] . $projectId, $result);
//                        $this->sendResultToZFT($result);
                        }
                        //发送生动化结果给cp
                        if ($result['context']['tool_id'] == $this->cp_id) {
                            $result['result_time'] = time();
//                            SendService::sendResultToCP($result);
                            //新的推送cp这次先不上
                            SendService::newSendResultToCP($result);
                        }
                        //正常流程走规则引擎
                        //保存规则引擎结果放到最后，以免与一键放弃等其他会查询规则引擎状态的地方冲突，产生死锁问题
                        $where = ['survey_code' => $result['context']['survey_code'], 'standard_id' => $result['context']['standard_id']];
                        $re = EngineResult::saveEngineResult($where, $result);
                        if (!$re[0]) {
                            $this->ding->sendTxt($re[1]);
                            $transaction->rollBack();
                            continue;
                        }
                    } else if (isset($result['context']['statistical_id'])) {
                        //重跑任务走规则引擎
                        $where = ['survey_code' => $result['context']['survey_code'], 'replan_id' => $result['context']['replan_id']];
                        //暂不考虑ine的重跑
                        EngineResult::saveEngineResult($where, $result);
                        //更新走访表发送规则引擎状态字段
                        $status = Survey::SEND_ENGINE_HAS_RESULT;
                        Survey::updateAll(['send_engine' => $status], ['survey_code' => $result['context']['survey_code']]);
                        //更新已完成数量且到总数量就修改replan状态为完成
                        $id = $result['context']['replan_id'];
                        Replan::saveFinishedNumber($id);
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                if (isset($transaction)) {
                    $transaction->rollBack();
                }
                $this->catchError($e);
            } finally {
                $this->finally();
            }
            pcntl_signal_dispatch();
        } while ($this->runnable);
    }

    /**
     * 重跑数据入规则引擎计算
     */
    public function actionDequeueReplanTask()
    {
        do {
            try {
                $result = Yii::$app->remq->dequeue(Yii::$app->remq::getQueueName('queue', 'calculation_task', 1));
                LOG::log('接收到重跑任务：' . json_encode($result));
                if ($result) {
                    $alias = 'r';
                    $join = [
                        ['type' => 'LEFT JOIN',
                            'table' => StatisticalItem::tableName() . ' s',
                            'on' => 's.id = r.statistical_id']
                    ];
                    $select = ['r.tool_id', 'r.company_code', 'r.check_scope', 'r.statistical_id', 's.engine_code'];
                    $where = ['r.id' => $result['replan_id']];
                    $replan_result = Replan::findJoin($alias, $join, $select, $where, true, false);

                    $alias = 'i';
                    $join = [
                        ['type' => 'LEFT JOIN',
                            'table' => ImageReport::tableName() . ' ir',
                            'on' => 'i.id = ir.photo_id'],
                        ['type' => 'LEFT JOIN',
                            'table' => Survey::tableName() . ' s',
                            'on' => 'i.survey_code = s.survey_code']
                    ];
                    $select = ['i.scene_code', 'ir.result', 'i.id img_id',
                        's.store_id', 's.survey_time', 's.is_ir', 's.examiner'];
                    $where = ['i.survey_code' => (string)$result['survey_code']];
                    $image_result = Image::findJoin($alias, $join, $select, $where);
                    $results = [];
                    $img_list = [];
                    $store_id = '';
                    foreach ($image_result as $v) {
                        $res['scene_code'] = $v['scene_code'];
                        $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                        $results[] = $res;
                        $img_list[] = $v['img_id'];
                        $store_id = $v['store_id'];
                        $is_ir = $v['is_ir'];
                    }
                    $where = ['in', 'photo_id', $img_list];
                    $questionnaire = QuestionAnswer::getAnswer($where);
                    $key = ['result_status', 'survey_code', 'statistical_id', 'merge_answer', 'replan_id', 'created_at', 'updated_at'];
                    $data_list['data'] = [];
                    $data_list['send_data'] = [];
                    $send_data['rule_code'] = $replan_result['engine_code'];
                    $data['survey_code'] = $result['survey_code'];
                    $data['replan_id'] = $result['replan_id'];
                    $data['statistical_id'] = $replan_result['statistical_id'];
                    $data['store_id'] = $store_id;
                    $data['tool_id'] = $replan_result['tool_id'];
//                    $data['sub_activity_id'] = $survey_info['sub_activity_id'];
                    $send_data['context'] = $data;
                    $send_data['results'] = $results;
                    $send_data['questionnaires'] = $this->mergeQuestion($questionnaire);
                    $send_data['enqueue_at'] = time();
                    $projectId = Yii::$app->params['project_id'];
                    $send_data['output_queue'] = $this->calculationOutput . $projectId;
                    $send_data['is_ir'] = $is_ir;
                    $data_list['data'][] = [
                        EngineResult::RESULT_STATUS_ING,
                        $result['survey_code'],
                        $replan_result['statistical_id'],
                        json_encode($send_data['questionnaires']),
                        $result['replan_id'],
                        time(),
                        time()
                    ];
                    $data_list['send_data'][] = $send_data;

                    //批量插入数据库
                    if (!empty($data_list['data'])) {
                        EngineResult::saveAllEngineResult($key, $data_list['data']);
                    }
                    //批量发送信息到队列
                    foreach ($data_list['send_data'] as $v) {
                        $re = Yii::$app->remq->enqueue(Yii::$app->params['engine_calculation']['engine_1'], $v);
//                        $re = Yii::$app->remq->createEngineCalculation($v);
                    }
                }
            } catch (\Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                Yii::getLogger()->flush(true);
            }
            pcntl_signal_dispatch();
        } while ($this->runnable);
    }

    /**
     * 拼接数据发送给图像识别，使用url模式
     * @param $image_urls
     * @param $image_id
     * @param $survey_code
     * @param $store_id
     * @param $scene_id
     * @return mixed
     */
    public function sendDistinguish($image_urls, $image_id, $survey_code, $store_id, $scene_id)
    {
        $url = Yii::$app->params['url']['media_host'] . 'image/upload';
        $api_key = Yii::$app->params['media_key'];
        $timestamp = time();
        $token = md5($timestamp . md5($api_key) . $timestamp);
        $head[] = 'timestamp: ' . $timestamp;
        $head[] = 'token: ' . $token;
        $data['img'] = $image_urls;
        $data['img_type'] = 'url';
        $re = ImageUrl::getIdByImageId($image_id);
        if ($re[0]) {
            foreach ($re[1] as $v) {
                $data['image_url_id'][] = $v['id'];
            }
        }
        $data['unid'] = $image_id;
        $data['survey_code'] = $survey_code;
        $data['store_id'] = $store_id;
        $data['scene_id'] = $scene_id;
        $data['channel'] = "太古";
        $data['call_back'] = Yii::$app->params['distinguish_callback'];

        //======控制识别服务开关
        $data['check_options'] = [
//            "do_perspective_correct", // 图片纠正
            "with_bottlepose",     //躺倒检测
            "with_rebroadcast",    //翻拍检测
            "with_material",       //物料检测
            //"with_coolercompleted",//冰柜完整性检测
            //"with_price",          //价格价签检测
            //"with_display",        //地堆去背景检测
            //"with_productiondate", //日期数字检测
            //"with_blurry",         //模糊检测
            //"with_exposure",       //曝光检测
            //"with_angle"           //倾斜检测
        ];
        //======

        //======相似图检查
        if (Yii::$app->params['open_similarity']) {
            $imgTags = [];
            if ($storeModel = Store::getLocationCode(['store_id' => $store_id])) $imgTags[] = $storeModel['location_code'] . '_' . $storeModel['route_code']; //营业所编号
            $surveyModel = Survey::getCode(['survey_code' => $survey_code]);
            if ($surveyModel) $imgTags[] = (string)$surveyModel['tool_id']; //执行工具
            $imageTime = strtotime($surveyModel['survey_time']) ? strtotime($surveyModel['survey_time']) : $surveyModel['created_at'];
            $checkEtime = $imageTime ? $imageTime : 0;
            $checkStime = ($checkEtime - (90 * 24 * 3600)) > 0 ? ($checkEtime - (90 * 24 * 3600)) : 0;
            $imageModel = Image::getStandardId(['id' => $image_id]);
            if ($imageModel['standard_id'] != 0) {
                $imgTags[] = (string)$imageModel['standard_id']; //检查
            } else {
                $planList = PlanStoreRelation::getPlanId(['store_id' => $store_id]);
                foreach ($planList as $planId) {
                    $model = Plan::getOneById($planId);
                    if ($model && $imageTime > strtotime($model['start_time']) && $imageTime < strtotime($model['end_time'])) {
                        $imgTags[] = (string)$model['standard_id']; //检查
                    }
                }
            }

            $checkTags = $imgTags;
            $data['similarity_options'] = [ //相似图检测入参
                "image_time" => $imageTime,
                "img_tag" => $imgTags, //图片tags 包含 执行工具、检查工具、营业所编号
                "check_tag" => $checkTags,  //检查tags
                "check_stime" => $checkStime, //检查核对开始时间
                "check_etime" => $checkEtime, //检查核对结束时间
                "callback" => Yii::$app->params['similarity_callback'] //检查结果回推地址
            ];
        }
        //======

        $data = json_encode($data);
        //以防万一送识别失败，做一个失败重送机制
        $time = 0;
        do {
            $re = $this->curlQuery($url, $data, $head);
            if ($re) {
                break;
            } else {
                sleep(1);
                $time++;
            }
        } while ($time < 10);
        return $re;
    }

    public function curlQuery($url, $data, $head = false)
    {
        LOG::log($url);
        LOG::log($data);
        $headers = array(
            "Content-Type: application/json"
        );
        $ch = curl_init();
        $timeout = 300;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, " CURL Example beta");
        if ($head) {
            $headers = array_merge($headers, $head);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response == null) {
            $ding = Ding::getInstance();
            $ding->sendTxt("推送返回为null \nurl:" . $url . "\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        LOG::log($response);
        return $response;
    }

    /**
     * 拼凑条件发送给规则引擎计算
     * @param $param
     * @return bool|mixed|string
     */
    public function sendReportToCalculation($param)
    {
        try {
            $where = ['i.survey_code' => (string)$param['survey_id'], 'i.status' => Image::DEL_STATUS_NORMAL];
            $result = Image::getReportToCalculation($where);
            $queue = false;
            $hit_rule = false;
            if (!empty($result)) {
                $has_standard = false;
                foreach ($result as $v) {
                    if ($v['standard_id'] != 0) {
                        $has_standard = true;
                        $res['scene_code'] = $v['scene_code'];
                        $res['sub_activity_id'] = $v['sub_activity_id'];
                        $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                        $send_list[$v['standard_id']]['results'][] = $res;
                        $send_list[$v['standard_id']]['img_list'][] = $v['img_id'];
//                    $send_list[$v['standard_id']]['tool_id'] = $v['tool_id'];
                        $standard_list[] = $v['standard_id'];
                        $img_list[] = $v['img_id'];
                    } else {
                        $res['scene_code'] = $v['scene_code'];
                        $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                        $results[] = $res;
                        $img_list[] = $v['img_id'];
                    }
                }
                $where = ['in', 'photo_id', $img_list];
                $questionnaire = QuestionAnswer::getAnswer($where);
                $key = ['result_status', 'survey_code', 'standard_id', 'merge_answer', 'is_rectify', 'created_at', 'updated_at', 'plan_id'];
                $data_list['data'] = [];
                $data_list['send_data'] = [];
                $survey_info = Survey::getSurveyStore($param['survey_id']);
                if (!$has_standard) {
                    //通过走访号反查标准id的rule_code
                    if ((int)$survey_info['plan_id'] === 0) {
                        $and_where[] = 'and';
                        $and_where[] = ['=', 'ps.store_id', $survey_info['store_id']];
                        $and_where[] = ['<=', 'p.start_time', $survey_info['survey_time']];
                        $and_where[] = ['>=', 'p.end_time', $survey_info['survey_time']];
                        $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                        $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                        $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
                        $select = ['s.engine_rule_code', 'p.standard_id', 'p.id plan_id', 'p.check_type_id'];
                        $rule_code = Plan::getRuleCode($and_where, $select);
                    } else {
                        $and_where[] = 'and';
                        $and_where[] = ['=', 'p.id', $survey_info['plan_id']];
                        $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                        $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                        $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
                        $select = ['s.engine_rule_code', 'p.standard_id', 'p.id plan_id', 'p.check_type_id'];
                        $rule_code = Plan::getRuleCode($and_where, $select);
                        $survey_info['is_ir'] = 1;
                    }
                    $hit_rule = empty($rule_code) ? false : true;
                    foreach ($rule_code as $v) {
                        $send_data['rule_code'] = $v['engine_rule_code'];
                        $data['survey_code'] = $param['survey_id'];
                        $data['plan_id'] = $v['plan_id'];
                        $data['standard_id'] = $v['standard_id'];
                        $data['store_id'] = $survey_info['store_id'];
                        $data['tool_id'] = $survey_info['tool_id'];
                        $data['sub_activity_id'] = $survey_info['sub_activity_id'];
                        $data['check_type_id'] = $survey_info['check_type_id'];
                        $send_data['context'] = $data;
                        $send_data['results'] = $results;
                        $send_data['questionnaires'] = $this->mergeQuestion($questionnaire);
                        $send_data['enqueue_at'] = time();
                        $projectId = Yii::$app->params['project_id'];
                        $send_data['output_queue'] = $this->calculationOutput . $projectId;
                        $send_data['is_ir'] = $survey_info['is_ir'];
                        $send_data['store_id'] = $survey_info['store_id'];
                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                        $send = true;
                        //找到检查项目对应的是否整改判断
                        if (isset($param['standard_list'])) {
                            $send = false;
                            foreach ($param['standard_list'] as $item) {
                                if ($item['standard_id'] == $v['standard_id']) {
                                    $is_rectify = (int)(!$item['is_first']);
                                    $send = true;
                                }
                            }
                        }
                        if ($send) {
                            $data_list['data'][] = [
                                EngineResult::RESULT_STATUS_ING,
                                $param['survey_id'],
                                $v['standard_id'],
                                json_encode($send_data['questionnaires']),
                                $is_rectify,
                                time(),
                                time(),
                                $v['plan_id']
                            ];
                            $data_list['send_data'][] = $send_data;
                        }
                    }
                } else {
                    $hit_rule = true;
                    $where = ['and'];
                    $where[] = ['in', 's.id', $standard_list];
                    $where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                    $where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
                    $rule_code = Standard::findRuleCode($where);
                    foreach ($send_list as $k => $v) {
                        $data = $survey_info;
                        $data['survey_code'] = $param['survey_id'];
                        $data['standard_id'] = $k;
                        $data['is_zft'] = true;
                        $data['company_code'] = $rule_code[$k]['company_code'];
                        $data['plan_id'] = $rule_code[$k]['plan_id'];
                        $data['protocol_id'] = $rule_code[$k]['protocol_id'];
                        $data['check_type_id'] = $rule_code[$k]['check_type_id'];
                        $send_data['standard_id'] = $k;
//                        $send_data['sub_activity_id'] = $survey_info['sub_activity_id'];
                        $send_data['rule_code'] = $rule_code[$k]['engine_rule_code'];
                        $send_data['context'] = $data;
                        $send_data['results'] = $v['results'];
                        $question_info = [];
                        foreach ($v['img_list'] as $item) {
                            if (isset($questionnaire[$item])) {
                                $question_info[] = $questionnaire[$item];
                            }
                        }
                        $send_data['questionnaires'] = $this->mergeQuestion($question_info);
                        $send_data['enqueue_at'] = time();
                        $projectId = Yii::$app->params['project_id'];
                        $send_data['output_queue'] = $this->calculationOutput . $projectId;
                        $send_data['is_ir'] = $survey_info['is_ir'];
                        $send_data['store_id'] = $survey_info['store_id'];
                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                        $send = true;
                        //找到检查项目对应的是否整改判断
                        if (isset($param['standard_list'])) {
                            $send = false;
                            foreach ($param['standard_list'] as $item) {
                                if ($item['standard_id'] == $k) {
                                    $is_rectify = (int)(!$item['is_first']);
                                    $send = true;
                                }
                            }
                        }
                        if ($send) {
                            $data_list['data'][] = [
                                EngineResult::RESULT_STATUS_ING,
                                $param['survey_id'],
                                $k,
                                json_encode($send_data['questionnaires']),
                                $is_rectify,
                                time(),
                                time(),
                                $rule_code[$k]['plan_id']
                            ];
                            $data_list['send_data'][] = $send_data;
                        }
                    }
                }
                //批量插入数据库
                if (!empty($data_list['data'])) {
                    EngineResult::saveAllEngineResult($key, $data_list['data']);
                }
                //批量发送信息到队列
                foreach ($data_list['send_data'] as $v) {
                    //新的送规则引擎队列
                    $re = Yii::$app->remq->enqueue(Yii::$app->params['engine_calculation']['engine_0'], $v);
//                    $re = Yii::$app->remq->createEngineCalculation($v);
                    $queue = $re ? true : $queue;
                }
            }
            //更新走访表发送规则引擎状态字段
            if ($hit_rule) {
                $status = $queue ? Survey::SEND_ENGINE_YES : Survey::SEND_ENGINE_TIME_OUT;
            } else {
                $status = Survey::NOT_HIT_RULE;
                $queue = true;
            }
            Survey::updateAll(['send_engine' => $status], ['survey_code' => (string)$param['survey_id']]);
            return $queue;
        } catch (\Exception $e) {
            $this->catchError($e);
        }
    }


    /**
     * 测试方法
     * @return bool|mixed|string
     */
    public function actionTest()
    {

    }

    /**
     * 新的拼凑条件发送给规则引擎计算方法
     * @param $param
     * @return bool|mixed|string
     */
    public function newSendReportToCalculation($param)
    {
        try {
            $where = ['i.survey_code' => (string)$param['survey_id'], 'i.status' => Image::DEL_STATUS_NORMAL];
            $result = Image::getAllToCalculation($where);
            $queue = false;
            $hit_rule = false;
            if (!empty($result)) {
                $has_standard = false;
                $store_question = [];
                foreach ($result as $v) {
                    if ($v['result']) {
                        //这个地方sfa会有检查项目id上传，sea没有
                        if ($v['standard_id'] != 0) {
                            $has_standard = true;
                            $res['scene_code'] = $v['scene_code'];
                            $res['sub_activity_id'] = $v['sub_activity_id'];
                            $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                            $send_list[$v['standard_id']]['results'][$v['scene_id']]['scene_code'] = $res['scene_code'];
                            $send_list[$v['standard_id']]['results'][$v['scene_id']]['sub_activity_id'] = $res['sub_activity_id'];
                            $send_list[$v['standard_id']]['results'][$v['scene_id']]['result'] = $res['result'];
                            if ($v['question_id']) {
                                $send_list[$v['standard_id']]['results'][$v['scene_id']]['questionnaires'][$v['question_id']] = $v['answer'];
                            } else {
                                $send_list[$v['standard_id']]['results'][$v['scene_id']]['questionnaires'] = isset($send_list[$v['standard_id']]['results'][$v['scene_id']]['questionnaires'])
                                    ? $send_list[$v['standard_id']]['results'][$v['scene_id']]['questionnaires'] : [];
                            }
                            $send_list[$v['standard_id']]['img_list'][] = $v['img_id'];
//                    $send_list[$v['standard_id']]['tool_id'] = $v['tool_id'];
                            $standard_list[] = $v['standard_id'];
                            $img_list[] = $v['img_id'];
                        } else {
                            $res['scene_code'] = $v['scene_code'];
                            $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                            $results[$v['scene_id']]['scene_code'] = $res['scene_code'];
                            $results[$v['scene_id']]['result'] = $res['result'];
                            if ($v['question_id']) {
                                $results[$v['scene_id']]['questionnaires'][$v['question_id']] = $v['answer'];
                            } else {
                                $results[$v['scene_id']]['questionnaires'][$v['question_id']] = isset($results[$v['scene_id']]['questionnaires'][$v['question_id']])
                                    ? $results[$v['scene_id']]['questionnaires'][$v['question_id']] : [];
                            }
                            $img_list[] = $v['img_id'];
                        }
                    } else {
                        if ($v['question_id']) {
                            $store_question[$v['question_id']] = $v['answer'];
                        }
                        if ($v['standard_id'] != 0) {
                            $standard_list[] = $v['standard_id'];
                        }
                    }
                }
                $key = ['result_status', 'survey_code', 'standard_id', 'merge_answer', 'is_rectify', 'created_at', 'updated_at', 'plan_id', 'ine_config_timestamp_id'];
                $data_list['data'] = [];
                $data_list['send_data'] = [];
                $survey_info = Survey::getSurveyStore($param['survey_id']);
                //增加sea高管巡店的特殊情况
                if ($survey_info['tool_id'] == Tools::TOOL_ID_SEA_LEADER) {
                    $hit_rule = true;
                    $where = ['and'];
                    $where[] = ['in', 'id', $standard_list];
                    $select = ['engine_rule_code', 'company_code', 'id', 'protocol_id', 'check_type_id'];
                    //因为sea高管走访肯定只有一个检查项目，所以此处取一条就行
                    $rule_code = Standard::findOneArray($where, $select);
                    if (isset($send_list)) {
                        foreach ($send_list as $k => $v) {
                            $data = $survey_info;
                            $data['survey_code'] = $param['survey_id'];
                            $data['standard_id'] = $k;
                            $data['is_zft'] = true;
                            $data['company_code'] = $rule_code['company_code'];
                            $data['protocol_id'] = $rule_code['protocol_id'];
                            //sea高管巡店的默认模式给无，plan_id给0，整改给不是整改
                            $is_rectify = EngineResult::IS_RECTIFY_NO;
                            $data['rectification_model'] = Plan::RECTIFICATION_MODEL_NONE;
                            $data['plan_id'] = 0;
                            $send_data['results'] = array_values($v['results']);
                            $send_data['enqueue_at'] = time();
                            $projectId = Yii::$app->params['project_id'];
                            $send_data['output_queue'] = $this->calculationOutput . $projectId;
                            $send_data['is_ir'] = $survey_info['is_ir'];
                            $send_data['store_id'] = $survey_info['store_id'];
                            $send_data['standard_id'] = $k;
//                        $send_data['sub_activity_id'] = $survey_info['sub_activity_id'];
                            $send_data['rule_code'] = $rule_code['engine_rule_code'];
                            //售点问卷也要传给规则引擎
                            $send_data['questionnaires'] = $store_question;
                            $send_data['context'] = $data;
                            $data_list['data'][] = [
                                EngineResult::RESULT_STATUS_ING,
                                $param['survey_id'],
                                $k,
                                '',
                                $is_rectify,
                                time(),
                                time(),
                                0,
                                0
                            ];
                            $data_list['send_data'][] = $send_data;
                        }
                    } else {
                        $data = $survey_info;
                        $data['survey_code'] = $param['survey_id'];
                        $data['standard_id'] = $standard_list[0];
                        $data['is_zft'] = false;
                        $data['company_code'] = $rule_code['company_code'];
                        $data['protocol_id'] = $rule_code['protocol_id'];
                        //sea高管巡店的默认模式给无，plan_id给0，整改给不是整改
                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                        $data['rectification_model'] = Plan::RECTIFICATION_MODEL_NONE;
                        $data['plan_id'] = 0;
                        $send_data = [
                            'results' => [],
                            'enqueue_at' => time(),
                            'output_queue' => $this->calculationOutput . Yii::$app->params['project_id'],
                            'is_ir' => $survey_info['is_ir'],
                            'store_id' => $survey_info['store_id'],
                            'standard_id' => $standard_list[0],
                            'rule_code' => $rule_code['engine_rule_code'],
                            'context' => $data,
                            'questionnaires' => $store_question
                        ];
                        $data_list['data'][] = [
                            EngineResult::RESULT_STATUS_ING,
                            $param['survey_id'],
                            $standard_list[0],
                            '',
                            $is_rectify,
                            time(),
                            time(),
                            0,
                            0
                        ];
                        $data_list['send_data'][] = $send_data;
                    }
                } else if (!$has_standard) {
                    //通过走访号反查标准id的rule_code
                    if ((int)$survey_info['plan_id'] === 0) {
                        $and_where[] = 'and';
                        $and_where[] = ['=', 'ps.store_id', $survey_info['store_id']];
                        $and_where[] = ['<=', 'p.start_time', $survey_info['survey_time']];
                        $and_where[] = ['>=', 'p.end_time', $survey_info['survey_time']];
                        $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                        $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                        $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
                        $select = ['s.engine_rule_code', 'p.standard_id', 'p.id plan_id', 'p.rectification_model', 's.check_type_id'];
                        $rule_code = Plan::getRuleCode($and_where, $select);
                    } else {
                        $and_where[] = 'and';
                        $and_where[] = ['=', 'p.id', $survey_info['plan_id']];
                        $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                        $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                        $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
                        $select = ['s.engine_rule_code', 'p.standard_id', 'p.id plan_id', 'p.rectification_model', 's.check_type_id'];
                        $rule_code = Plan::getRuleCode($and_where, $select);
                        $survey_info['is_ir'] = 1;
                    }
                    $hit_rule = empty($rule_code) ? false : true;
                    $data_array = [];
                    foreach ($rule_code as $v) {
                        $send_data['rule_code'] = $v['engine_rule_code'];
                        $send_data['questionnaires'] = $store_question;
                        $data = $survey_info;
                        $data['survey_code'] = $param['survey_id'];
                        $data['plan_id'] = $v['plan_id'];
                        $data['standard_id'] = $v['standard_id'];
                        $data['rectification_model'] = $v['rectification_model'];
                        $data['check_type_id'] = $v['check_type_id'];
                        $data_array[] = $data;
                        $send_data['context'] = $data;
                        $send_data['results'] = isset($results) ? array_values($results) : [];
//                        $send_data['questionnaires'] = $this->mergeQuestion($questionnaire);
                        $send_data['enqueue_at'] = time();
                        $projectId = Yii::$app->params['project_id'];
                        $send_data['output_queue'] = $this->calculationOutput . $projectId;
                        $send_data['is_ir'] = $survey_info['is_ir'];
                        $send_data['store_id'] = $survey_info['store_id'];
                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                        $send = true;
                        //找到检查项目对应的是否整改判断
                        if (isset($param['standard_list'])) {
                            $send = false;
                            foreach ($param['standard_list'] as $item) {
                                if ($item['standard_id'] == $v['standard_id']) {
                                    if ($v['rectification_model'] != Plan::RECTIFICATION_MODEL_NONE) {
                                        $is_rectify = (int)(!$item['is_first']);
                                    } else {
                                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                                    }
                                    $send = true;
                                }
                            }
                        }
                        if ($send) {
                            $data_list['data'][] = [
                                EngineResult::RESULT_STATUS_ING,
                                $param['survey_id'],
                                $v['standard_id'],
                                '',
                                $is_rectify,
                                time(),
                                time(),
                                $v['plan_id'],
                                0
                            ];
                            $data_list['send_data'][] = $send_data;
                        }

//                        EngineResult::createEngineResult($data);
//                        $queue = Yii::$app->remq->createEngineCalculation($send_data);
                    }
                    //sea检查员走访也要存ine走访
                    $ine_standard = [];
                    foreach ($data_array as $v) {
                        if ($v['check_type_id'] == Standard::CHECK_IS_INE) {
                            $ine_standard[] = $v['standard_id'];
                        }
                    }
                    $ine_channel_id = IneConfigSnapshot::findJoin('', [], ['ine_config_timestamp_id'], ['standard_id' => $ine_standard], true, true, 'id DESC', 'standard_id', 'standard_id');
                    if ($ine_channel_id && !empty($data_list['data'])) {
                        foreach ($data_list['data'] as &$v) {
                            if (!empty($ine_channel_id[$v[2]])) {
                                $v[8] = $ine_channel_id[$v[2]]['ine_config_timestamp_id'];
                            }
                        }
                    }
                } else {
                    //sfa流程
                    $hit_rule = true;
                    $where = ['and'];
                    $where[] = ['in', 's.id', $standard_list];
                    $where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                    $where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                    $where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                    $rule_code = Standard::findRuleCode($where);
                    if (isset($send_list)) {
                        foreach ($send_list as $k => $v) {
                            $data = $survey_info;
                            $data['survey_code'] = $param['survey_id'];
                            $data['standard_id'] = $k;
                            $data['is_zft'] = true;
                            $data['plan_id'] = $rule_code[$k]['plan_id'];
                            $send_data['results'] = array_values($v['results']);
                            $send_data['enqueue_at'] = time();
                            $projectId = Yii::$app->params['project_id'];
                            $send_data['output_queue'] = $this->calculationOutput . $projectId;
                            $send_data['is_ir'] = $survey_info['is_ir'];
                            $send_data['store_id'] = $survey_info['store_id'];
                            $is_rectify = EngineResult::IS_RECTIFY_NO;
                            $send = true;
                            //找到检查项目对应的是否整改判断
                            if (isset($param['standard_list'])) {
                                $send = false;
                                foreach ($param['standard_list'] as $item) {
                                    if ($item['standard_id'] == $k) {
                                        if ($rule_code[$k]['rectification_model'] != Plan::RECTIFICATION_MODEL_NONE) {
                                            $is_rectify = (int)(!$item['is_first']);
                                        } else {
                                            $is_rectify = EngineResult::IS_RECTIFY_NO;
                                        }
                                        $send = true;
                                    }
                                }
                            }
                            if ($send) {
                                $data['company_code'] = $rule_code[$k]['company_code'];
                                $data['protocol_id'] = $rule_code[$k]['protocol_id'];
                                $data['rectification_model'] = $rule_code[$k]['rectification_model'];
                                $data['plan_id'] = $rule_code[$k]['plan_id'];
                                $send_data['standard_id'] = $k;
//                        $send_data['sub_activity_id'] = $survey_info['sub_activity_id'];
                                $send_data['rule_code'] = $rule_code[$k]['engine_rule_code'];
                                $send_data['questionnaires'] = $store_question;
                                $send_data['context'] = $data;

                                $data_list['data'][] = [
                                    EngineResult::RESULT_STATUS_ING,
                                    $param['survey_id'],
                                    $k,
                                    '',
                                    $is_rectify,
                                    time(),
                                    time(),
                                    $rule_code[$k]['plan_id'],
                                    0
                                ];
                                $data_list['send_data'][] = $send_data;
                            }
                        }
                    } else {
                        $data = $survey_info;
                        $data['survey_code'] = $param['survey_id'];
                        $data['standard_id'] = $standard_list[0];
                        $data['is_zft'] = false;
                        $data['company_code'] = $rule_code['company_code'];
                        $data['protocol_id'] = $rule_code['protocol_id'];
                        //只传了售点图片的话，无法判断是否整改，默认给0
                        $is_rectify = EngineResult::IS_RECTIFY_NO;
                        $data['rectification_model'] = Plan::RECTIFICATION_MODEL_NONE;
                        $data['company_code'] = '';
                        $data['protocol_id'] = '';
                        $data['rectification_model'] = '';
                        $data['plan_id'] = 0;
                        $send_data = [
                            'results' => [],
                            'enqueue_at' => time(),
                            'output_queue' => $this->calculationOutput . Yii::$app->params['project_id'],
                            'is_ir' => $survey_info['is_ir'],
                            'store_id' => $survey_info['store_id'],
                            'standard_id' => $standard_list[0],
                            'rule_code' => $rule_code['engine_rule_code'],
                            'context' => $data,
                            'questionnaires' => $store_question
                        ];
                        $data_list['data'][] = [
                            EngineResult::RESULT_STATUS_ING,
                            $param['survey_id'],
                            $standard_list[0],
                            '',
                            $is_rectify,
                            time(),
                            time(),
                            0,
                            0
                        ];
                        $data_list['send_data'][] = $send_data;
                    }
                }
                $queue = true;
                //批量插入数据库
                if (!empty($data_list['data'])) {
                    EngineResult::saveAllEngineResult($key, $data_list['data']);
                }
                //批量发送信息到队列
                foreach ($data_list['send_data'] as $v) {
//                    $re = Yii::$app->remq->createEngineCalculation($v);
                    $re = Yii::$app->remq->enqueue(Yii::$app->params['engine_calculation']['engine_0'], $v);
                    if (!$re) {
                        $this->ding->sendTxt('数据：' . json_encode($v) . '入规则引擎队列失败');
                        LOG::log('数据：' . json_encode($v) . '入规则引擎队列失败');
                    }
                    $queue = $re ? $queue : false;
                }
            }
            //更新走访表发送规则引擎状态字段
            if ($hit_rule) {
                $status = $queue ? Survey::SEND_ENGINE_YES : Survey::SEND_ENGINE_TIME_OUT;
            } else {
                $status = Survey::NOT_HIT_RULE;
                $queue = true;
            }
            Survey::updateAll(['send_engine' => $status], ['survey_code' => (string)$param['survey_id']]);
            return $queue;
        } catch (\Exception $e) {
            $this->catchError($e);
        }
    }

    /**
     * 拼凑合并问卷
     * @param $questions
     * @return array
     */
    public function mergeQuestion($questions)
    {
        $questionnaire = [];
        foreach ($questions as $v) {
            if (isset($v['question_id']) && !empty($v['question_id'])) {
                if (isset($questionnaire[$v['question_id']])) {
                    if (is_numeric($v['answer'])) {
                        if ($v['merge_type'] == 1) {
                            $questionnaire[$v['question_id']] = $questionnaire[$v['question_id']] + $v['answer'];
                        } else if ($v['merge_type'] == 2) {
                            $questionnaire[$v['question_id']] = ($questionnaire[$v['question_id']] == 1 && $v['answer'] == 1 ? 1 : 0);
                        } else if ($v['merge_type'] == 3) {
                            $questionnaire[$v['question_id']] = ($questionnaire[$v['question_id']] == 1 || $v['answer'] == 1 ? 1 : 0);
                        }
                    }
                } else {
                    $questionnaire[$v['question_id']] = $v['answer'];
                }
            }
        }
        return $questionnaire;
    }

    /**
     * 更新/新增主检查项失败次数
     * @param $data
     */
    public function updateResultNode($data)
    {
        $rule_output_node_id = RuleOutputInfo::findAllArray(['standard_id' => $data['context']['standard_id'],
            'is_main' => RuleOutputInfo::IS_MAIN_YES], ['id', 'node_index']);
        foreach ($rule_output_node_id as $item) {
            foreach ($data['output_list'] as $v) {
                if ($item['node_index'] == $v['node_index']) {
                    ResultNode::saveFailCount($data['context']['tool_id'], $data['context']['store_id'], $item['id'], $v['output']);
                }
            }
        }
    }

    /**
     * 拼凑条件字段将结果送给zft的脚本
     * @return mixed|string
     */
    public function actionSendResultToZft()
    {
        do {
            try {
                $projectId = Yii::$app->params['project_id'];
                $data = Yii::$app->remq->dequeue(Yii::$app->params['queue']['send_zft'] . $projectId);
                if (isset($data['is_ignore'])) {
                    foreach ($data['data'] as $v) {
                        $engine_result = EngineResult::findOne(['id' => $v['id']]);
                        if ($engine_result->plan->is_push_zft == 1) {
                            $info = [
                                'standard_id' => $engine_result->standard_id,
                                'plan_id' => $engine_result->plan_id,
                                'store_id' => $engine_result->survey->store_id,
                                'tool_id' => $engine_result->plan->tool_id,
                                'survey_code' => $engine_result->survey_code,
                                'survey_time' => $engine_result->survey->survey_time,
                                'protocol_id' => $engine_result->standard->protocol_id,
                                'rectification_model' => $engine_result->plan->rectification_model,
                                'company_code' => $engine_result->plan->company_code,
                                'examiner_id' => $engine_result->survey->examiner_id,
                                'output_list' => json_decode($engine_result->result, true)
                            ];
                            if (!$res = SendService::sendResultToZft($info)) {
                                LOG::log("走访号:" . $engine_result->survey_code . " 支付通推送失败");
//                                $this->ding->sendTxt("支付通推送失败");
                            }
                        }
                    }
                    sleep(1);
                } else {
                    SendService::sendZFT($data);
                }
            } catch (\Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
            }
            pcntl_signal_dispatch();
        } while ($this->runnable);
    }

    /**
     * 定时拼凑条件字段将结果送给zft的脚本
     * @param string $choose_time
     */
    public function actionRegularSendToZft($choose_time = '')
    {
        try {
            $choose_time = $choose_time ? strtotime($choose_time) : time();
            $url1 = Yii::$app->params['zft_url'] . '/api/submitExecuteResult';
            //七天内符合发送标准而没有发送的数据，在此处发送ZFT
            $alias = 'e';
            $join[] = [
                'type' => 'JOIN',
                'table' => Survey::tableName() . ' s',
                'on' => 's.survey_code = e.survey_code'
            ];
            $join[] = [
                'type' => 'JOIN',
                'table' => Standard::tableName() . ' st',
                'on' => 'st.id = e.standard_id'
            ];
            $join[] = [
                'type' => 'JOIN',
                'table' => ProtocolTemplate::tableName() . ' p',
                'on' => 'p.id = st.protocol_id'
            ];
            $join[] = [
                'type' => 'JOIN',
                'table' => ProtocolStore::tableName() . ' ps',
                'on' => 'ps.contract_id = p.contract_id AND ps.outlet_id = s.store_id'
            ];
            $join[] = [
                'type' => 'JOIN',
                'table' => Plan::tableName() . ' pl',
                'on' => 'pl.standard_id = e.standard_id and s.tool_id = pl.tool_id'
            ];
            $join[] = [
                'type' => 'LEFT JOIN',
                'table' => Tools::tableName() . ' t',
                'on' => 's.tool_id = t.id'
            ];
            $select = ['ps.outlet_contract_id', 'p.excute_count', 'p.excute_cycle_list', 's.store_id', 's.company_code',
                's.survey_time', 'e.standard_id', 'e.result', 'e.qc_result', 'e.qc_status',
                's.examiner_id', 's.survey_code', 'st.scenes', 'pl.re_photo_time', 't.name', 'pl.start_time', 'pl.end_time',
                'pl.rectification_model', 'pl.is_qc', 'pl.is_push_zft', 'pl.short_cycle'];
            $time_frame = date('Y-m-d H:i:s', strtotime(self::REGULAR_ZFT_TIME, $choose_time));
            $end_time = date('Y-m-d H:i:s', $choose_time);
            $where[] = 'and';
            $where[] = ['between', 's.survey_time', $time_frame, $end_time];
            $where[] = ['e.result_status' => EngineResult::RESULT_STATUS_DONE];
            $where[] = ['p.protocol_status' => ProtocolTemplate::PROTOCOL_STATUS_ENABLE];
            $where[] = ['ps.store_status' => ProtocolStore::PROTOCOL_STATUS_ENABLE];
            $where[] = ['<>', 'send_zft_status', EngineResult::SEND_ZFT_STATUS_SUCCESS];
            $result = EngineResult::findJoin($alias, $join, $select, $where);
            foreach ($result as $item) {
                //如果是需要qc，而qc又没出结果的，跳过
                if ($item['is_qc'] == Plan::IS_QC_YES && $item['qc_status'] == EngineResult::ENGINE_RESULT_QC_DEFAULT) {
                    continue;
                }
                $cycle_id = 0;
                if ($item['rectification_model'] == Plan::RECTIFICATION_MODEL_WITH_CYCLE) {
                    $excute_cycle_list = [];
                    if (!empty($item['excute_cycle_list'])) {
                        foreach (json_decode($item['excute_cycle_list'], true) as $v) {
                            $tmp['start_time'] = \Helper::dateTimeFormat($v['cycleFromDate'], 'Y-m-d');
                            $tmp['end_time'] = \Helper::dateTimeFormat($v['cycleToDate'], 'Y-m-d');
                            $tmp['cycle_id'] = $v['cycleID'];
                            $excute_cycle_list[] = $tmp;
                        }
                    }
                    $cycle_list = json_decode($item['short_cycle'], true) ?: $excute_cycle_list;
                    if ($cycle_list) {
                        $where = [];
                        foreach ($cycle_list as $v) {
                            $start = date('Y-m-d', strtotime($v['start_time'])) . ' 00:00:00';
                            $end = date('Y-m-d', strtotime($v['end_time'])) . ' 23:59:59';
                            $time = $item['survey_time'];
                            if ($time > $start && $time < $end) {
                                $cycle_id = $v['cycle_id'];
                                $where[] = 'and';
                                $where[] = ['>', 's.survey_time', $start];
                                $where[] = ['<', 's.survey_time', $end];
                            }
                        }
                        //不在时间段内的走访没有返回
                        if ($cycle_id == 0) {
                            continue;
                        }

                        $alias = 'e';
                        $join = [
                            ['type' => 'JOIN',
                                'table' => Survey::tableName() . ' s',
                                'on' => 's.survey_code = e.survey_code']
                        ];
                        $select = ['e.id engine_id', 'e.result', 'e.qc_result', 'e.is_need_qc', 'e.qc_status', 'e.standard_id', 's.survey_time', 's.survey_code', 's.examiner_id'];
                        $where[] = ['s.store_id' => $item['store_id']];
                        $where[] = ['e.standard_id' => $item['standard_id']];
                        $where[] = ['e.result_status' => EngineResult::RESULT_STATUS_DONE];
                        $where[] = ['<>', 'send_zft_status', EngineResult::SEND_ZFT_STATUS_SUCCESS];
                        //获取该检查的总共检查次数
                        $engine_result = EngineResult::findJoin($alias, $join, $select, $where);
                    } else {
                        $where = ['and'];
                        $time = $item['survey_time'];
                        //不在时间段内的走访没有返回
                        if ($time < $item['start_time'] && $time > $item['end_time']) {
                            continue;
                        }
                        $alias = 'e';
                        $join = [
                            ['type' => 'JOIN',
                                'table' => Survey::tableName() . ' s',
                                'on' => 's.survey_code = e.survey_code']
                        ];
                        $select = ['e.id engine_id', 'e.result', 'e.qc_result', 'e.is_need_qc', 'e.qc_status', 'e.standard_id', 's.survey_time', 's.survey_code', 's.examiner_id'];
                        $where[] = ['s.store_id' => $item['store_id']];
                        $where[] = ['e.standard_id' => $item['standard_id']];
                        $where[] = ['e.result_status' => EngineResult::RESULT_STATUS_DONE];
                        $where[] = ['>', 's.survey_time', $item['start_time']];
                        $where[] = ['<', 's.survey_time', $item['end_time']];
                        $where[] = ['<>', 'send_zft_status', EngineResult::SEND_ZFT_STATUS_SUCCESS];
                        //获取该检查的总共检查次数
                        $engine_result = EngineResult::findJoin($alias, $join, $select, $where);
                    }
                } else {
                    continue;
                }
                if ($engine_result) {
                    $count = count($engine_result);
                    $scenes = json_decode($item['scenes'], true);
                    $activation_list = [];
                    //输出项全成功，生动化项才算是合格
                    $all_success = '';
                    //如果整改次数达到了或者plan结束了，那么就取最后一次整改的数据发送
                    if ($count >= ($item['re_photo_time'] + $item['excute_count']) || $item['end_time'] < $end_time) {
                        $tmp = $engine_result;
                        $last = array_pop($tmp);
                        $survey_time = $last['survey_time'];
                        $examiner = $item['name'] . '_' . $last['examiner_id'];
                        $survey_code = $last['survey_code'];
                        //如果是需要qc，而qc又没出结果的，跳过
                        if ($last['is_need_qc'] == Plan::IS_QC_YES && $last['qc_status'] == EngineResult::ENGINE_RESULT_QC_DEFAULT) {
                            continue;
                        }
                        $output_list = ($last['is_need_qc'] == Plan::IS_QC_YES && $last['qc_status'] == EngineResult::ENGINE_RESULT_QC_DOWN) ?
                            json_decode($last['qc_result'], true) : json_decode($last['result'], true);
                        //此处为新版加入的生动化发送zft详情表，有它的记录的话就不要用从其他表取
                        $Activation_info = ActivationSendZftInfo::findAllArray(['standard_id' => $last['standard_id'], 'survey_code' => $last['survey_code']]);
                        if ($Activation_info) {
                            foreach ($Activation_info as $v) {
                                $activation_list[] = [
                                    'activationID' => $v['activation_id'],
                                    'isStandard' => $v['is_standard'],
                                    'checkCount' => 0,
                                    'executeResult' => $v['activation_status']
                                ];
                            }
                        } else {
                            foreach ($scenes as $v) {
                                $execute_result = '';
                                $activation_data['activationID'] = $v['activationID'];
                                $activation_data['isStandard'] = $v['isStandard'];
                                $activation_data['checkCount'] = 0;
                                foreach ($v['outputList'] as $item1) {
                                    foreach ($output_list as $item2) {
                                        if ($item1['node_index'] == $item2['node_index']) {
                                            $execute_result = ($execute_result === '') ? 1 : $execute_result;
                                            $execute_result = $item2['output'] ? $execute_result : 0;
//                                        $all_success = $item2['output'] ? $all_success : false;
                                        }
                                    }
                                }
                                $activation_data['executeResult'] = $execute_result;
                                $activation_list[] = $activation_data;
                            }
                        }
                    } else {
                        $output_list = ($item['is_qc'] == Plan::IS_QC_YES && $item['qc_status'] == EngineResult::ENGINE_RESULT_QC_DOWN) ?
                            json_decode($item['qc_result'], true) : json_decode($item['result'], true);;
                        $survey_time = $item['survey_time'];
                        $examiner = $item['name'] . '_' . $item['examiner_id'];
                        $survey_code = $item['survey_code'];
                        //此处为新版加入的生动化发送zft详情表，有它的记录的话就不要用从其他表取
                        $Activation_info = ActivationSendZftInfo::findAllArray(['standard_id' => $item['standard_id'], 'survey_code' => $item['survey_code']]);
                        if ($Activation_info) {
                            //如果既没有达到整改的最后一次也不是全部成功，就不发送zft
                            if ($Activation_info[0]['all_activation_status'] != ActivationSendZftInfo::ALL_ACTIVATION_STATUS_SUCCESS) {
                                continue;
                            }
                            foreach ($Activation_info as $v) {
                                $activation_list[] = [
                                    'activationID' => $v['activation_id'],
                                    'isStandard' => $v['is_standard'],
                                    'checkCount' => 0,
                                    'executeResult' => $v['activation_status']
                                ];
                            }
                        } else {
                            foreach ($scenes as $v) {
                                $execute_result = '';
                                $activation_data['activationID'] = $v['activationID'];
                                $activation_data['isStandard'] = $v['isStandard'];
                                $activation_data['checkCount'] = 0;
                                foreach ($v['outputList'] as $item1) {
                                    foreach ($output_list as $item2) {
                                        if ($item1['node_index'] == $item2['node_index']) {
                                            $execute_result = ($execute_result === '') ? 1 : $execute_result;
                                            $execute_result = $item2['output'] ? $execute_result : 0;
                                        }
                                    }
                                }
                                $all_success = ($all_success === '') ? true : $all_success;
                                $activation_data['executeResult'] = ($execute_result === '') ? 0 : $execute_result;
                                $all_success = ($activation_data['executeResult'] === 0) ? false : $all_success;
                                $activation_list[] = $activation_data;
                            }
                            //如果既没有达到整改的最后一次也不是全部成功，就不发送zft
                            if (!$all_success) {
                                continue;
                            }
                        }
                    }
                    $execute_list = [];
                    $company_code = $item['company_code'];
                    $execute_list[] = [
                        'outletContractID' => (int)$item['outlet_contract_id'],
                        'outletNo' => $item['store_id'],
                        'executeCycleID' => $cycle_id,
                        //检查次数暂定默认为1
                        'executeCount' => 1,
                        'executeDate' => date('YmdHis', strtotime($survey_time)),
                        'executeBy' => $examiner,
                        'surveyId' => $survey_code,
                        'activationList' => $activation_list,
                    ];
                    $send_data = [
                        'companyCode' => $company_code,
                        'executeList' => $execute_list
                    ];
                    $header = Protocol::getZftToken(time());
                    $re = \Helper::curlQueryLog($url1, $send_data, true, 300, $header);
                    $engine_id = array_column($engine_result, 'engine_id');
                    if ($re) {
                        if ($re['resultCode'] == 200) {
                            EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_SUCCESS],
                                ['in', 'id', $engine_id]);
                            ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_SUCCESS, 'send_zft_time' => time()],
                                ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                        } else {
                            EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => $re['resultMessage']],
                                ['in', 'id', $engine_id]);
                            ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                                ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                        }
                    } else {
                        EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => 'ZFT无返回'],
                            ['in', 'id', $engine_id]);
                        ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                            ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                    }
                }
            }


            //短期协议--有ZFT签约数据，且活动周期已经结束，且有检查结果（不管是否合格以及剩余整改次数），且未在检查日期所属周期内推送过的
            $alias = 'p';
            $join = [];
            $join[] = [
                'type' => 'JOIN',
                'table' => ProtocolStore::tableName() . ' ps',
                'on' => 'p.contract_id = ps.contract_id'
            ];
            $join[] = [
                'type' => 'JOIN',
                'table' => Standard::tableName() . ' s',
                'on' => 'p.id = s.protocol_id'
            ];
            $select = ['ps.outlet_contract_id', 'ps.outlet_id store_id', 's.id standard_id', 's.scenes', 'p.excute_count', 'p.excute_cycle_list'];
            $time_frame = date('Ymd', strtotime(self::REGULAR_ZFT_TIME, $choose_time));
            $now = date('Ymd');
            $where = [];
            $where[] = 'and';
            $where[] = ['>=', 'p.excute_to_date', $time_frame];
            $where[] = ['p.protocol_status' => ProtocolTemplate::PROTOCOL_STATUS_ENABLE];
            $where[] = ['ps.store_status' => ProtocolStore::PROTOCOL_STATUS_ENABLE];
            $protocol_template = ProtocolTemplate::findJoin($alias, $join, $select, $where);
            foreach ($protocol_template as $item) {
                $cycle_id = 0;
                $where = [];
                $cycle_list = json_decode($item['excute_cycle_list'], true);
                foreach ($cycle_list as $v) {
                    if ($time_frame < $v['cycleToDate'] && $now > $v['cycleToDate']) {
                        $cycle_id = $v['cycleID'];
                        $start = date('Y-m-d', strtotime($v['cycleFromDate']));
                        $end = date('Y-m-d', strtotime($v['cycleToDate']));
                        $where = ['and', ['>', 'survey_time', $start . ' 00:00:00'], ['<', 'survey_time', $end . ' 23:59:59']];
                    }
                }
                if (!$cycle_id) {
                    continue;
                }
                $alias = 'e';
                $join = [];
                $join[] = [
                    'type' => 'JOIN',
                    'table' => Survey::tableName() . ' s',
                    'on' => 's.survey_code = e.survey_code'
                ];
                $join[] = [
                    'type' => 'LEFT JOIN',
                    'table' => Tools::tableName() . ' t',
                    'on' => 't.id = s.tool_id'
                ];
                $join[] = [
                    'type' => 'JOIN',
                    'table' => Plan::tableName() . ' pl',
                    'on' => 'pl.standard_id = e.standard_id and s.tool_id = pl.tool_id'
                ];
                $select = ['e.id engine_id', 'e.result', 'e.qc_result', 'e.qc_status', 'pl.is_qc', 'pl.is_push_zft',
                    's.survey_time', 's.examiner_id', 's.survey_code', 's.company_code', 't.name'];
                $where[] = ['s.store_id' => $item['store_id']];
                $where[] = ['e.standard_id' => $item['standard_id']];
                $where[] = ['<>', 'e.send_zft_status', EngineResult::SEND_ZFT_STATUS_SUCCESS];
                //此处为冗余，默认规则引擎应该是一定会有结果，但是不考虑脏数据的话会报错
                $where[] = ['e.result_status' => EngineResult::RESULT_STATUS_DONE];
                // 该时段已发送过的不返回
                $engine_result = EngineResult::findJoin($alias, $join, $select, $where);
                if ($engine_result) {
                    //多次同检查项目走访取最新一次
                    $tmp = $engine_result;
                    $last = array_pop($tmp);
                    //如果是需要qc，而qc又没出结果的，跳过
                    if ($last['is_qc'] == Plan::IS_QC_YES && $last['qc_status'] == EngineResult::ENGINE_RESULT_QC_DEFAULT) {
                        continue;
                    }
                    $scenes = json_decode($item['scenes'], true);
                    $survey_time = $last['survey_time'];
                    $examiner = $last['name'] . '_' . $last['examiner_id'];
                    $survey_code = $last['survey_code'];
                    $output_list = ($last['is_qc'] == Plan::IS_QC_YES && $last['qc_status'] == EngineResult::ENGINE_RESULT_QC_DOWN) ?
                        json_decode($last['qc_result'], true) : json_decode($last['result'], true);
                    $activation_list = [];
                    //此处为新版加入的生动化发送zft详情表，有它的记录的话就不要用从其他表取
                    $Activation_info = ActivationSendZftInfo::findAllArray(['standard_id' => $item['standard_id'], 'survey_code' => $last['survey_code']]);
                    if ($Activation_info) {
                        foreach ($Activation_info as $v) {
                            $activation_list[] = [
                                'activationID' => $v['activation_id'],
                                'isStandard' => $v['is_standard'],
                                'checkCount' => 0,
                                'executeResult' => $v['activation_status']
                            ];
                        }
                    } else {
                        foreach ($scenes as $v) {
                            $execute_result = '';
                            $activation_data['activationID'] = $v['activationID'];
                            $activation_data['isStandard'] = $v['isStandard'];
                            $activation_data['checkCount'] = 0;
                            foreach ($v['outputList'] as $item1) {
                                foreach ($output_list as $item2) {
                                    if ($item1['node_index'] == $item2['node_index']) {
                                        $execute_result = ($execute_result === '') ? 1 : $execute_result;
                                        $execute_result = $item2['output'] ? $execute_result : 0;
                                    }
                                }
                            }
                            //如果一条输出项都没对上，那么直接结果置为0
                            $activation_data['executeResult'] = ($execute_result === '') ? 0 : $execute_result;
                            $activation_list[] = $activation_data;
                        }
                    }

                    $execute_list = [];
                    $company_code = $last['company_code'];
                    $execute_list[] = [
                        'outletContractID' => (int)$item['outlet_contract_id'],
                        'outletNo' => $item['store_id'],
                        'executeCycleID' => $cycle_id,
                        'executeCount' => (int)$item['excute_count'],
                        'executeDate' => date('YmdHis', strtotime($survey_time)),
                        'executeBy' => $examiner,
                        'surveyId' => $survey_code,
                        'activationList' => $activation_list,
                    ];
                    $send_data = [
                        'companyCode' => $company_code,
                        'executeList' => $execute_list
                    ];
                    $header = Protocol::getZftToken(time());
                    $re = \Helper::curlQueryLog($url1, $send_data, true, 300, $header);
                    $engine_id = array_column($engine_result, 'engine_id');
                    if ($re) {
                        if ($re['resultCode'] == 200) {
                            EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_SUCCESS],
                                ['in', 'id', $engine_id]);
                            ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_SUCCESS, 'send_zft_time' => time()],
                                ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                        } else {
                            EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => $re['resultMessage']],
                                ['in', 'id', $engine_id]);
                            ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                                ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                        }
                    } else {
                        EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => 'ZFT无返回'],
                            ['in', 'id', $engine_id]);
                        ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                            ['standard_id' => $item['standard_id'], 'survey_code' => $survey_code]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->catchError($e);
        }
    }

    /**
     * 手动重发规则引擎
     * @param $survey_code
     */
    public function actionReSendToCalculation($survey_code)
    {
        // 首先删除需要重推的原有规则引擎结果
        EngineResult::deleteAll(['survey_code' => $survey_code, 'replan_id' => 0]);
        $data['survey_id'] = $survey_code;
        if ($this->newSendReportToCalculation($data)) {
            print_r('走访号：' . $survey_code . '重发成功');
        } else {
            print_r('走访号：' . $survey_code . '发送失败');
        }
    }

    /**
     * 手动重推重跑任务的脚本
     * @param $replan_id
     * @param string $survey_code
     */
    public function actionReSendReplanToCalculation($replan_id, $survey_code = '')
    {
        if (!$survey_code) {
            $alias = 'r';
            $join = [
                ['type' => 'LEFT JOIN',
                    'table' => EngineResult::tableName() . ' e',
                    'on' => 'e.survey_code = r.survey_code and e.replan_id = r.replan_id'
                ]
            ];
            $select = ['r.replan_id', 'r.survey_code'];
            $where[] = 'and';
            $where[] = ['IS', 'e.result', new Expression('NULL')];
            $where[] = ['r.replan_id' => $replan_id];
            $replans = ReplanSurvey::findJoin($alias, $join, $select, $where);
            if ($replans) {
                // 首先删除需要重推的原有规则引擎结果
                $survey_codes = array_column($replans, 'survey_code');
                EngineResult::deleteAll(['survey_code' => $survey_codes, 'replan_id' => $replan_id]);
                foreach ($replans as $v) {
                    $result = Yii::$app->remq->enqueue(Yii::$app->remq::getQueueName('queue', 'calculation_task', 1), $v);
                    if ($result) {
                        print_r('走访号：' . $survey_code . '重发成功');
                    } else {
                        print_r('走访号：' . $survey_code . '发送失败');
                    }
                }
            }
        } else {
            // 首先删除需要重推的原有规则引擎结果
            EngineResult::deleteAll(['survey_code' => $survey_code, 'replan_id' => $replan_id]);
            $result = Yii::$app->remq->enqueue(Yii::$app->remq::getQueueName('queue', 'calculation_task', 1), compact('replan_id', 'survey_code'));
            if ($result) {
                print_r('走访号：' . $survey_code . '重发成功');
            } else {
                print_r('走访号：' . $survey_code . '发送失败');
            }
        }
    }

    /**
     * 超时后补偿发送规则引擎的定时脚本
     * @param int $check_time
     */
    public function actionCompensateSendEngine($check_time = 2)
    {
        try {
            //查询条件为限定时间内发送超时状态的走访
            $start_time = date('Y-m-d H:i:s', strtotime('-' . $check_time . ' days'));
            $alias = '';
            $join = [];
            $select = ['survey_code'];
            $where[] = 'and';
            $where[] = ['>', 'survey_time', $start_time];
            $where[] = ['send_engine' => Survey::SEND_ENGINE_TIME_OUT];
            $survey_result = Survey::findJoin($alias, $join, $select, $where);
            $survey_list = array_column($survey_result, 'survey_code');

            //查询是否有没有完成识别的图片
            $alias = 'ir';
            //排除被删除的场景
            $join = [
                ['type' => 'JOIN',
                    'table' => Image::tableName() . ' i',
                    'on' => 'i.survey_code = ir.survey_id']
            ];
            $select = ['ir.survey_id'];
            $where = [];
            $where[] = 'and';
            $where[] = ['in', 'ir.survey_id', $survey_list];
            $where[] = ['<>', 'ir.report_status', ImageReport::REPORT_STATUS_END];
            $where[] = ['=', 'i.status', Image::DEL_STATUS_NORMAL];
            $image_result = ImageReport::findJoin($alias, $join, $select, $where);
            $not_down_list = array_column($image_result, 'survey_id');

            foreach ($survey_list as $v) {
                if (!in_array($v, $not_down_list)) {
                    $re = $this->newSendReportToCalculation(['survey_id' => $v]);
                    if ($re) {
                        LOG::log('走访号：' . $v . '补偿重发成功');
                    } else {
                        Yii::error('走访号：' . $v . '补偿重发发送失败');
                        $this->ding->sendTxt('走访号：' . $v . '补偿重发发送失败');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->catchError($e);
        }
    }

    /**
     * 重推图像识别
     * @param int $time
     */
    public function actionReSendDistinguish($time = 0)
    {
        $time = $time ? $time : strtotime('-7 days');
        $alias = 'i';
        $join = [
            ['type' => 'LEFT JOIN',
                'table' => ImageReport::tableName() . ' ir',
                'on' => 'i.id = ir.photo_id'],
            ['type' => 'LEFT JOIN',
                'table' => Survey::tableName() . ' s',
                'on' => 's.survey_code = i.survey_code']
        ];
        $select = ['i.img_prex_key', 'i.number', 'i.id image_id', 'ir.id report_id', 'i.survey_code', 's.store_id', 'i.scene_id'];
        $where = [];
        $where[] = 'and';
        $where[] = ['>', 'i.created_at', $time];
        $where[] = ['=', 'ir.report_status', ImageReport::REPORT_STATUS_DOING];
        $image_result = Image::findJoin($alias, $join, $select, $where);

        foreach ($image_result as $v) {
            $images = ImageUrl::findAllArray(['image_id' => $v['image_id']], ['image_url']);
            $images = array_column($images, 'image_url');
            $re = $this->sendDistinguish($images, $v['image_id'], $v['survey_code'], $v['store_id'], $v['scene_id']);
            if ($re) {
                print_r('走访号：' . $v['survey_code'] . '的图片id：' . $v['image_id'] . "重送识别成功 \n");
            } else {
                Yii::error('走访号：' . $v['survey_code'] . '的图片id：' . $v['image_id'] . "重送识别失败");
            }
        }
    }

    /**
     * 生成走访检查项目和子活动快照
     *
     * @param $survey_code
     * @param $standard_id
     * @throws Exception
     */
    private function createSurveySnapshot($survey_code, $standard_id)
    {
        //获取检查项目
        $standard = Standard::find()->with('subActivity')->where(['id' => $standard_id])->asArray()->one();
        //新增走访检查项目快照
        $survey_standard_model = new SurveyStandard();
        unset($standard['update_time']);
        $survey_standard_model->load($standard, '');
        $survey_standard_model->survey_code = $survey_code;
        $survey_standard_model->standard_id = $standard_id;
        $survey_standard_model->scenes = '';  //手动置为空
        if (!$survey_standard_model->save()) {
            throw new Exception('走访检查项目快照创建失败，' . $survey_standard_model->getErrStr());
        }
        //新增走访子活动快照
        foreach ($standard['subActivity'] as $sub_activity) {
            $survey_sub_activity_model = new SurveySubActivity();
            unset($sub_activity['update_time']);
            $survey_sub_activity_model->load($sub_activity, '');
            $survey_sub_activity_model->survey_code = $survey_code;
            $survey_sub_activity_model->standard_id = $standard_id;
            $survey_sub_activity_model->sub_activity_id = $sub_activity['id'];
            if (!$survey_sub_activity_model->save()) {
                throw new Exception('走访子活动快照创建失败，' . $survey_sub_activity_model->getErrStr());
            }
        }
    }

    /**
     * 判断生动化是否都合格
     *
     * @param $output_list
     * @param $survey_code
     * @param $standard_id
     * @throws Exception
     */
    private function updatePassStatus($output_list, $survey_code, $standard_id)
    {
        //获取检查项目
        $standard = Standard::find()->where(['id' => $standard_id])->asArray()->one();
        //获取检查项目所有场景集合
        $scenes = json_decode(ArrayHelper::getValue($standard, 'scenes', '[]'), true);
        //获取所有引擎输出项node_index
        $node_index_all = [];
        foreach ($scenes as $sub_activity) {
            //要考虑没绑定生动化项的情况
            if (isset($sub_activity['outputList'])) {
                foreach ($sub_activity['outputList'] as $output) {
                    array_push($node_index_all, (int)$output['node_index']);
                }
            }
        }
        //没有绑定任何生动化项，直接返回
        if (!$node_index_all) {
            return;
        }
        //判断所有场景集合，布尔型引擎输出项是、否全为true
        $node_index_all = array_unique($node_index_all);
        $pass_status = EngineResult::ENGINE_PASS_STATUS_YES;
        foreach ($node_index_all as $node_index) {
            foreach ($output_list as $output) {
                if ($node_index == $output['node_index'] && is_bool($output['output']) && $output['output'] === false) {
                    $pass_status = EngineResult::ENGINE_PASS_STATUS_NO;
                    break 2;
                }
            }
        }
        //更新规则引擎计算结果数据
        $engine_result_model = EngineResult::findOne(['survey_code' => $survey_code, 'standard_id' => $standard_id]);
        if ($engine_result_model) {
            $engine_result_model->pass_status = $pass_status;
            if (!$engine_result_model->save()) {
                throw new Exception('规则引擎计算结果更新(生动化是否都合格)字段失败');
            }
        } else {
            throw new Exception('规则引擎计算结果不存在');
        }
    }

    /**
     * 判断是否为整改拍照并且修改是否需要qc
     *
     * @param $plan_id
     * @param $store_id
     * @param $survey_code
     * @param $standard_id
     * @throws Exception
     */
    private function updateIsRectify($plan_id, $store_id, $survey_code, $standard_id)
    {
        //代码集成到service层
        $model = PlanService::getSamePlanModel($plan_id, $store_id);
        if ($model) {
            $count = $model->count('s.id');
            //查询时间段内的走访数，大于1则为整改，否则为非整改
            $is_rectify = EngineResult::IS_RECTIFY_NO;
            if ($count > 1) {
                $is_rectify = EngineResult::ENGINE_PASS_STATUS_YES;
            }
            $engine_info = $model->select('e.id')->all();
            $engine_id_list = array_column($engine_info, 'id');
            //更新是否需要qc的字段，先将所有同周期的未qc的走访的qc状态改为不需要qc
            $where = [
                'and',
                ['<>', 'qc_status', EngineResult::ENGINE_RESULT_QC_DOWN],
                ['in', 'id', $engine_id_list]
            ];
            EngineResult::updateAll(['is_need_qc' => EngineResult::IS_NEED_QC_NO], $where);
            //更新规则引擎计算结果数据
            $engine_result_model = EngineResult::findOne(['survey_code' => $survey_code, 'standard_id' => $standard_id]);
            if ($engine_result_model) {
                $engine_result_model->is_rectify = $is_rectify;
                // 将本身的是否需要qc改为需要
                // 要根据检查项目和检查计划来判断是否需要更改
                $plan = Plan::findOneArray(['id' => $plan_id]);
                if ($plan != null) {
                    $engine_result_model->is_need_qc = $plan['is_qc'];
                }
                if (!$engine_result_model->save()) {
                    throw new Exception('规则引擎计算结果更新(是否为整改拍照)字段失败');
                }
            } else {
                throw new Exception('规则引擎计算结果不存在');
            }
        }
    }

    /**
     * 将问卷的照片与其他信息存入问卷答案表
     * @param $data
     * @return array
     * @throws \yii\db\Exception
     */
    public function saveQuestionImage($data)
    {
        $answer_arr = [];
        $image_urls = [];
        //没有传问卷过来就直接返回
        if (!isset($data['question'])) return ['status' => true];
        if (is_array($data['question'])) {
            foreach ($data['question'] as $v) {
                //将问卷图片存入cos换取key
                $re = COS::saveImageForMes($v['question_image']);
                if (!$re['status']) {
                    return $re;
                }
                $keys = [];
                for ($i = 0; $i < count($v['question_image']); $i++) {
                    $keys[] = $re['key'] . '_' . $i;
                }
                $answer = [];
                $answer[] = $data['image_id'];
                $answer[] = $data['survey_code'];
                $answer[] = $data['tool_id'];
                $answer[] = $data['store_id'];
                $answer[] = $v['question_id'];
                $answer[] = $v['answer'];
                $answer[] = time();
                $answer[] = time();
                $answer[] = json_encode($v['question_image']);
                $answer[] = json_encode($keys);
                $answer[] = $data['scene_code'];
                $answer[] = $data['scene_id'];
                $answer[] = $data['scene_id_name'];
                $answer_arr[] = $answer;
                foreach ($v['question_image'] as $k1 => $v1) {
                    $image_url = [
                        $data['image_id'],
                        $v1,
                        $re['key'] . '_' . $k1 . 'jpg',
                        ImageUrl::IMAGE_QUESTIONNAIRE,
                        $v['question_id']
                    ];
                    $image_urls[] = $image_url;
                }

            }
        }
        if ($answer_arr) {
            $key = ['photo_id', 'survey_id', 'tool_id', 'store_id', 'question_id', 'answer', 'created_at', 'updated_at', 'question_image', 'question_image_key', 'scene_code', 'scene_id', 'scene_id_name'];
            $re = QuestionAnswer::saveAnswer($key, $answer_arr);
            if (!$re[0]) {
                return ['status' => $re[0], 'msg' => $re[1]];
            }
        }
        if ($image_urls) {
            $key = ['image_id', 'image_url', 'image_key', 'img_type', 'question_id'];
            ImageUrl::saveImageUrl($image_urls, $key);
        }
        return ['status' => true];
    }

    /**
     * 完成接口需要处理的问题
     * @param $result
     * @return bool
     * @throws \yii\db\Exception
     */
    public function saveFinishData($result)
    {
        //存入走访问题表
        $survey_model = Survey::findOne(['survey_code' => $result['survey_id']]);
        if (!$survey_model) {
            return true;
        }
        $survey_info = $survey_model->attributes;
        if (isset($result['standard_list'])) {
            $standard_id = array_column($result['standard_list'], 'standard_id');
            $and_where[] = 'and';
            $and_where[] = ['s.id' => $standard_id];
            $and_where[] = ['<=', 'p.start_time', $survey_info['survey_time']];
            $and_where[] = ['>=', 'p.end_time', $survey_info['survey_time']];
            $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
            $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
            $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
        } else {
            if ((int)$survey_info['plan_id'] === 0) {
                $and_where[] = 'and';
                $and_where[] = ['=', 'ps.store_id', $survey_info['store_id']];
                $and_where[] = ['<=', 'p.start_time', $survey_info['survey_time']];
                $and_where[] = ['>=', 'p.end_time', $survey_info['survey_time']];
                $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                $and_where[] = ['=', 'p.plan_status', Plan::PLAN_STATUS_ENABLE];
                $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
            } else {
                $and_where[] = 'and';
                $and_where[] = ['=', 'p.id', $survey_info['plan_id']];
                $and_where[] = ['=', 'p.status', Plan::DEL_STATUS_NORMAL];
                $and_where[] = ['=', 'p.tool_id', $survey_info['tool_id']];
            }
        }
        $select = ['p.id plan_id', 's.question_manual_ir', 's.question_manual', 's.scenes', 's.id standard_id', 's.check_type_id', 'p.need_question_qc'];
        $rule_code = Plan::getRuleCode($and_where, $select);
        $value = [];
        $value1 = [];
        $survey_plan_data = [];
        $ine_standard = [];
        //用于判断是否需要qc
        $need_qc = false;
        //增加问卷qc计划任务的总数
        $add_number = [];
        $image_sub = [];
        $images = Image::findAllArray(['survey_code' => $result['survey_id']], ['id image_id', 'scene_code', 'scene_id', 'scene_id_name'], 'scene_code');
        $scene_codes = array_column($images, 'scene_code');
        $question_answers = QuestionAnswer::findAllArray(['survey_id' => $result['survey_id']], ['photo_id', 'question_id', 'answer', 'question_image', 'question_image_key']);
        $question_answers = ArrayHelper::index($question_answers, null, ['photo_id', 'question_id']);
        foreach ($rule_code as $item) {
            $need_qc = $item['need_question_qc'] == Plan::NEED_QC_YES ?? $need_qc;
            //取出ine检查项目，用于保存ine_channel_id
            if ($item['check_type_id'] == Standard::CHECK_IS_INE) {
                $ine_standard[] = $item['standard_id'];
            }
            $scenes = json_decode($item['scenes'], true);
            if (is_array($scenes)) {
                foreach ($scenes as $val) {
                    $image_info = [];
                    $question_manual_ir = $val['question_manual_ir'];
                    $question_manual = $val['question_manual'];
                    foreach ($scene_codes as $scene_code) {
                        if (in_array($scene_code, $val['scenes_code'])) $image_info = $images[$scene_code];
                        //在此处将image表的所属sub_activity_id收集，后面一并更新至image表
                        $image_sub[] = [
                            'id' => $images[$scene_code]['image_id'],
                            'sub_activity_id' => $val['sub_activity_id'] ?? 0
                        ];
                    }
                    if (is_array($question_manual_ir) || is_array($question_manual)) {
                        if (is_array($question_manual_ir) && is_array($question_manual)) {
                            $question_arr = array_merge((array)$question_manual_ir, $question_manual);
                        } else if (is_array($question_manual)) {
                            $question_arr = $question_manual;
                        } else {
                            $question_arr = $question_manual_ir;
                        }
                        foreach ($question_arr as $v) {
                            $value[] = [$survey_info['id'], $item['plan_id'], $v['id']];
                            if ($image_info) {
                                $term = isset($question_answers[$image_info['image_id']]) && isset($question_answers[$image_info['image_id']][$v['id']]);
                                $answer = $term ? $question_answers[$image_info['image_id']][$v['id']][0]['answer'] : '';
                                $question_image = $term ? $question_answers[$image_info['image_id']][$v['id']][0]['question_image'] : '';
                                $question_image_key = $term ? $question_answers[$image_info['image_id']][$v['id']][0]['question_image_key'] : '';
                                $value1[] = [
                                    $image_info['image_id'],
                                    $survey_info['survey_code'],
                                    $item['plan_id'],
                                    $image_info['scene_code'],
                                    $image_info['scene_id'],
                                    $image_info['scene_id_name'],
                                    $v['id'],
                                    $answer,
                                    $question_image,
                                    $question_image_key,
                                    1,
                                    time(),
                                    time(),
                                ];
                            }
                        }
                    }
                }
            }

            //存数据到走访计划表
            $survey_plan_data[] = [
                $survey_info['survey_code'],
                $item['plan_id'],
                $survey_info['tool_id'],
                $survey_info['store_id'],
                $item['need_question_qc'],
                SurveyPlan::QUESTION_QC_STATUS_DEFAULT,
                '',
                //这里没有登录根本就拿不到缓存
                0,
                1,
                time(),
                time(),
            ];

            if ($item['need_question_qc'] == Plan::NEED_QC_YES) {
                $add_number[$item['plan_id']] = isset($add_number[$item['plan_id']]) ? $add_number[$item['plan_id']] + 1 : 1;
            }
        }
        $transaction = Yii::$app->getDb()->beginTransaction();
        if ($image_sub) {
            Image::insertOrUpdate(Image::tableName(), $image_sub, true);
        }
        if (!empty($result['ine_channel_id'])) {
            $tmp = IneConfigSnapshot::findJoin('', [], ['ine_channel_id', 'ine_config_timestamp_id'], ['ine_channel_id' => $result['ine_channel_id']], true, false, 'id DESC', '', 'standard_id');
            $tmp['survey_code'] = $result['survey_id'];
            $tmp['created_at'] = time();
            $tmp['updated_at'] = time();
            $ine_channel[] = $tmp;
        } else {
            //取出对应的ine_channel_id存入走访表
            $ine_channel = IneConfigSnapshot::findJoin('', [], ['ine_channel_id', 'ine_config_timestamp_id'], ['standard_id' => $ine_standard], true, true, 'id DESC', '', 'standard_id');
            if (!empty($ine_channel)) {
                $ine_channel_list = array_column($ine_channel, 'ine_channel_id');
                $ine_channel_id = implode(',', $ine_channel_list);
                $survey_model->ine_channel_id = $ine_channel_id;
                $survey_model->save();
                //将有ine检查项目的数据插入走访渠道中间表
                foreach ($ine_channel as &$v) {
                    $v['survey_code'] = $result['survey_id'];
                    $v['created_at'] = time();
                    $v['updated_at'] = time();
                }
            }
        }
        if ($ine_channel) {
            Yii::$app->db->createCommand()->batchInsert(SurveyIneChannel::tableName(), ['ine_channel_id', 'ine_config_timestamp_id', 'survey_code', 'created_at', 'updated_at'], $ine_channel)->execute();
        }
        if (!empty($value)) {
            $result = SurveyQuestion::saveSurveyQuestion($value);
            if (!$result[0]) {
                $transaction->rollBack();
                Yii::error($result[1]);
                return true;
            }
        }
        if (!empty($value1)) {
            $result = QuestionAnswerQc::saveQc($value1);
            if (!$result[0]) {
                $transaction->rollBack();
                Yii::error($result[1]);
                return true;
            }
        }
        if (!empty($survey_plan_data)) {
            $result = SurveyPlan::saveSurveyPlan($survey_plan_data);
            if (!$result[0]) {
                $transaction->rollBack();
                Yii::error($result[1]);
                return true;
            }
        }
        $transaction->commit();
        //将走访计划列表的计数存入redis的hash里，以便查询使用
        foreach ($add_number as $plan_id => $number) {
            $prefix = '_QC_PLAN_';
            QuestionQcService::planQcToRedis($prefix, $plan_id, $survey_info['tool_id'], $number);
        }
        return $need_qc;
    }
}