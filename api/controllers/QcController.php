<?php

namespace api\controllers;

use api\models\Plan;
use api\models\User;
use api\service\qc\ReviewService;
use Yii;

/**
 * Qc控制器
 * Class QcController
 * @package api\controllers
 */
class QcController extends BaseApi
{
    const ACCESS_ANY = [
        'get-success-image',
        'detail',
        'save-qc-result',
        'manual-check-result-list-download-progress',
    ];

    /**
     * 人工复核任务列表
     *
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午4:30
     * @return array
     */
    public function actionManualReviewList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['page', 'page_size'])) {
            return $this->error();
        }

        $searchForm['create_start_time'] = $searchForm['create_start_time'] ?? '';
        $searchForm['create_end_time'] = $searchForm['create_end_time'] ?? '';
        $searchForm['start_time'] = $searchForm['start_time'] ?? '';
        $searchForm['end_time'] = $searchForm['end_time'] ?? '';

        $searchForm['page'] = $searchForm['page'] ?? 1;
        $searchForm['page_size'] = $searchForm['page_size'] ?? 10;

        return ReviewService::getManualReviewList($searchForm);
    }

    /**
     * 人工复核详情页中查看成功图像
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午2:15
     * @return array|void
     */
    public function actionGetSuccessImage()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['sub_activity_id'])) {
            return $this->error();
        }

        return ReviewService::getSuccessImage($searchForm);
    }

    //QC详情接口
    public function actionDetail()
    {
        $params = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($params, ['engine_result_id'])) {
            return $this->error();
        }
        $rs = ReviewService::qcDetail($params);
        if (!$rs[0]) {
            return $this->error($rs[1]);
        }
        return $this->success([$rs[1]]);
    }

    //保存QC结果
    public function actionSaveQcResult()
    {
        $params = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($params, ['engine_result_id', 'qc_result'])) {
            return $this->error();
        }
        $rs = ReviewService::saveQcResult($params);
        if (!$rs[0]) {
            return $this->error($rs[1]);
        }
        return $this->success();
    }

    /**
     * 获取人工复核结果列表
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午7:15
     * @return \api\models\Replan[]|array|\yii\db\ActiveRecord[]
     */
    public function actionManualCheckResultList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['tool_id', 'standard_id', 'page', 'page_size'])) {
            return $this->error();
        }

        $searchForm['start_time'] = $searchForm['start_time'] ?? '';
        $searchForm['end_time'] = $searchForm['end_time'] ?? '';

        $searchForm['page'] = $searchForm['page'] ?? 1;
        $searchForm['page_size'] = $searchForm['page_size'] ?? 10;

        return ReviewService::getManualCheckResultList($searchForm);
    }

    /**
     * Qc 检查计划走访列表
     * 央服的人无权限查看
     * @return array
     */
    public function actionSurveyList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['plan_id'])) {
            return $this->error();
        }
        if (Yii::$app->params['user_is_3004']) {
            return $this->error('央服没有权限查看');
        }
        $plan = Plan::allOne(['id' => $searchForm['plan_id']]);
        if ($plan == null) {
            return $this->error('检查计划不存在');
        }
        $searchForm['standard_id'] = $plan['standard_id'];
        return ReviewService::getSurvey($searchForm);
    }

    /**
     * 特定搜索条件下的下一个走访号
     * @return array
     */
    public function actionSurveyNext()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['plan_id'])) {
            return $this->error();
        }
        $plan = Plan::findOneArray(['id' => $searchForm['plan_id']]);
        if ($plan == null) {
            return $this->error('检查计划不存在');
        }
        $searchForm['standard_id'] = $plan['standard_id'];
        return ReviewService::getNextSurveyCode($searchForm);
    }

    /**
     * 特定搜索条件下的放弃复核
     * @return array
     */
    public function actionSurveyIgnore()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['plan_id'])) {
            return $this->error();
        }
        $plan = Plan::findOneArray(['id' => $searchForm['plan_id']]);
        if ($plan == null) {
            return $this->error('检查计划不存在');
        }
        return ReviewService::ignoreSurvey($searchForm);
    }

    /**
     * 人工复核结果列表下载
     */
    public function actionManualCheckResultListDownload()
    {
        $searchForm = Yii::$app->request->post();

        if (!$this->isPost()) {
            return $this->error();
        }

        $user = User::getSwireUser($searchForm['token']);
        $user_arr = $user->getAttributes();
        $searchForm['user'] = $user_arr;
        $searchForm['start_time'] = $searchForm['start_time'] ?? '';
        $searchForm['end_time'] = $searchForm['end_time'] ?? '';

        $redis_queue_key = Yii::$app->params['redis_queue']['manual_check_result_list_download'];
        return $this->downloadPushQueue($redis_queue_key, $searchForm, ReviewService::class, 'getManualCheckResultList');
    }

    /**
     * 人工复核结果列表下载进度查询
     */
    public function actionManualCheckResultListDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['manual_check_result_list_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->remq::getString($cacheKey);

        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }
}
