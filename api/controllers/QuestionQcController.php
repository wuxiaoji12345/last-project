<?php

namespace api\controllers;

use api\models\Plan;
use api\models\Question;
use api\models\QuestionAnswerQc;
use api\models\QuestionQcIgnoreTmp;
use api\models\Standard;
use api\models\SurveyPlan;
use api\models\User;
use api\service\qc\QuestionQcService;
use api\service\qc\ReviewService;
use common\libs\file_log\LOG;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * QuestionQc控制器
 * Class QuestionQcController
 * @package api\controllers
 */
class QuestionQcController extends BaseApi
{
    const ACCESS_ANY = [
        'get-success-image',
        'manual-check-result-list-download-progress',
        'survey-ignore-upload-progress',
        'download-fail',
        'fail-ignore-download-progress',
        'survey-list',
        'plan-list',
    ];

    /**
     * 问卷qc计划任务列表
     * @return array
     */
    public function actionPlanList()
    {
        $search = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($search, ['page', 'page_size'])) {
            return $this->error();
        }

        $search['page'] = $search['page'] ?? 1;
        $search['page_size'] = $search['page_size'] ?? 10;

        $search['need_question_qc'] = SurveyPlan::NEED_QC_YES;

        return QuestionQcService::getQuestionQcPlanList($search);
    }

    /**
     * 问卷qc计划走访任务列表
     * @return array
     */
    public function actionSurveyList()
    {
        $search = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($search, ['page', 'page_size'])) {
            return $this->error();
        }

        $search['page'] = $search['page'] ?? 1;
        $search['page_size'] = $search['page_size'] ?? 10;

        $search['need_question_qc'] = SurveyPlan::NEED_QC_YES;

        return QuestionQcService::getQuestionQcSurveyList($search);
    }

    /**
     * 问卷QC任务详情
     */
    public function actionDetail()
    {
        $params = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($params, ['qc_task_id'])) {
            return $this->error();
        }
        //详情页模式，默认为查看模式
        $params['mode'] = ArrayHelper::getValue($params, 'mode', QuestionQcService::DETAIL_VIEW_MODE);
        $qc_task = QuestionQcService::getDetail($params['qc_task_id'], $params);
        return $this->success($qc_task);
    }

    /**
     * 保存QC结果
     */
    public function actionSaveQcResult()
    {
        $params = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($params, ['qc_task_id', 'questions'])) {
            return $this->error();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $surveyPlan = SurveyPlan::find()->where(['id' => $params['qc_task_id']])->asArray()->one();
            if ($surveyPlan['question_qc_status'] == 1) { // 判断是否被qc
                return $this->error("问卷已经被{$surveyPlan['user_id']}qc");
            }
            if (isset($params['description'])) {
                SurveyPlan::updateAll(['description' => $params['description']], ['id' => $params['qc_task_id']]);
            }
            //问卷是否必填校验
            $questions = $params['questions'];
            $plan = Plan::find()->with('standard')->where(['id' => $surveyPlan['plan_id']])->asArray()->one();
            $standard_questions = [];
            if (!empty($plan) && !empty($plan['standard'])) {
                $question_and_scenes = Standard::getQuestionAndScenes([$plan['standard']]);
                $standard_questions = ArrayHelper::getValue($question_and_scenes, 'questions', []);
                $standard_questions = array_combine(array_column($standard_questions, 'id'), array_values($standard_questions));
            }
            foreach ($questions as $question) { //检查必填项是否填写
                if ($question['answer'] === '') {//如果没有填写
                    if (isset($standard_questions[$question['question_id']]) &&
                        $standard_questions[$question['question_id']]['is_required']) {//有这个问卷且是必填
                        return $this->error($standard_questions[$question['question_id']]['title'] . '是必填项！');
                    }
                }
            }

            // 更改问卷结果数据
            $where = [];
            $where['survey_code'] = $surveyPlan['survey_code'];
            foreach ($questions as $question) {
                $where['question_id'] = $question['question_id'];
                $where['scene_id'] = $question['scene_id'];
                QuestionAnswerQc::updateAll(['answer' => $question['answer']], $where);
            }

            //更改QC任务状态为复核成功、记录QC人员信息
            $userId = Yii::$app->params['user_info']['id'];
            SurveyPlan::updateAll(['question_qc_status' => 1, 'user_id' => $userId], ['id' => $params['qc_task_id']]);
            $transaction->commit();

            //入判断是否能送引擎计算队列
            $projectId = Yii::$app->params['project_id'];
            $send['survey_id'] = $surveyPlan['survey_code'];
            $send['qc_done'] = true;
            $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['calculation_task'] . $projectId, $send);
            if (!$queue) {
                Yii::error('走访号:' . $surveyPlan['survey_code'] . '完成接口入队失败');
                return $this->error('入队列失败');
            }
            //要将redis里面的plan任务已完成计数加1，剩余任务数减1。
            QuestionQcService::qcFinishToRedis($surveyPlan['plan_id'], $surveyPlan['tool_id']);
        } catch (\Exception $e) {
            $transaction->rollBack();
            LOG::log($e->getMessage());
        }
        return $this->success();
    }

    /**
     * 问卷批量复核上传
     * @return array
     * @throws \yii\base\Exception
     */
    public function actionSurveyIgnoreUpload()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->validateParam($bodyForm, ['plan_id'])) {
            return $this->error();
        }
        //获取上传文件
        $file = UploadedFile::getInstanceByName("file");
        if ($file) {
            //文件格式大小校验
            if (!in_array(strtolower($file->extension), ['xls', 'xlsx'])) {
                $this->responseMsg = '文件格式错误';
                return $this->error();
            }
            if ($file->size > 1024 * 1024 * 8) {
                $this->responseMsg = '文件大小不能超过8M';
                return $this->error();
            }
            //创建文件保存目录
            $dir = Yii::getAlias('@runtime') . '/question-qc/' . date('Ymd');
            if (!file_exists($dir)) {
                FileHelper::createDirectory($dir);
            }
            //随机生成文件名，保存文件到服务器
            $file_id = Yii::$app->getSecurity()->generateRandomString();
            $filepath = realpath($dir) . '/' . $file_id . '.' . $file->extension;
            if ($file->saveAs($filepath)) {
                //获取用户信息
                $user = User::getSwireUser($bodyForm['token']);
                $user_arr = $user->getAttributes();
                $user_arr['swire_bu_code'] = $user->swire_bu_code;
                //上传文件入队列
                $queue_data = ['plan_id' => $bodyForm['plan_id'], 'file_id' => $file_id, 'path' => $filepath, 'ext' => ucfirst(strtolower($file->extension)), 'user' => $user_arr];
                $queue_name = Yii::$app->remq::getQueueName('redis_queue', 'question_qc_upload', '', true);
                Yii::$app->remq->enqueue($queue_name, $queue_data);
                return $this->success(['file_id' => $file_id]);
            } else {
                $this->responseMsg = '保存失败 错误码:' . $file->error;
                return $this->error();
            }

        } else {
            $this->responseMsg = '文件不能为空';
            return $this->error();
        }
    }

    public function actionSurveyIgnoreUploadProgress()
    {
        $params = Yii::$app->request->post();
        if (!$this->validateParam($params, ['file_id'])) {
            return $this->error();
        }
        $queue_name = Yii::$app->remq::getQueueName('redis_queue', 'question_qc_upload', $params['file_id']);
        $result = Yii::$app->redis->get($queue_name);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if ($result['status']) {
                $count = QuestionQcIgnoreTmp::find()->where(['file_id' => $params['file_id']])->count();
                $result['count'] = (int)$count;
                $rs = $result;
            } else {
                return $this->error($result['msg']);
            }
        } else {
            $rs = [
                'progress' => 0
            ];
        }
        if ($rs['progress'] == 100) {
            $rs['success_num'] = QuestionQcIgnoreTmp::find()->where(['file_id' => $params['file_id'], 'check_status' => QuestionQcIgnoreTmp::CHECK_STATUS_PASS])->count();
            $rs['fail_num'] = QuestionQcIgnoreTmp::find()->where(['file_id' => $params['file_id'], 'check_status' => QuestionQcIgnoreTmp::CHECK_STATUS_FAIL])->count();
        }
        return $this->success($rs);
    }

    /**
     * 导入失败数据下载
     * @return array
     */
    public function actionDownloadFail()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->validateParam($bodyForm, ['file_id'])) {
            return $this->error();
        }

        $one = QuestionQcIgnoreTmp::find()->where([
            'file_id' => $bodyForm['file_id'],
            'check_status' => QuestionQcIgnoreTmp::CHECK_STATUS_FAIL
        ])->one();
        if ($one == null) {
            return $this->error('无导入失败记录');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['question_qc_fail_download'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, QuestionQcIgnoreTmp::class, 'getExcelImportFailList', 'getExcelImportFailQuery');
    }

    /**
     * 问卷批量复核下载失败数据
     * @return array|int[]|mixed
     */
    public function actionFailIgnoreDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        return self::DownloadProcess($bodyForm, 'question_qc_fail_download_progress');
    }

    /**
     * 批量复核有效数据
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionBatchQc()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['file_id'])) {
            return $this->error();
        }
        $qc_value = ArrayHelper::getValue($bodyForm, 'qc_value', '');
        QuestionQcService::BatchQc($bodyForm['file_id'], $qc_value);
        return $this->success();
    }
}
