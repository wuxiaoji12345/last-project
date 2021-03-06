<?php

namespace api\controllers;

use api\models\Plan;
use api\models\PlanBatchTmp;
use api\models\ProtocolTemplate;
use api\models\share\OrganizationRelation;
use api\models\Standard;
use api\models\User;
use api\service\plan\PlanService;
use common\components\REMQ;
use Yii;
use api\models\PlanBatch;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class PlanBatchController extends BaseApi
{
    const ACCESS_ANY = ['standard-list', 'excel-template-download'];

    public function actionList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['page', 'page_size', 'company_bu'])) {
            return $this->error();
        }

        $where = $searchForm;
        $where['created_start'] = strtotime($where['create_start_time']);
        $where['created_end'] = strtotime($where['create_end_time']);

        $query = PlanBatch::getListQuery($where);

        $count = $query->count();

        $pager = [
            'page' => $searchForm['page'],
            'page_size' => $searchForm['page_size']
        ];
        $page = $pager['page'];
        $pageSize = $pager['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);
        $data = $query->orderBy(['id' => SORT_DESC])->all();
        $bu = OrganizationRelation::companyBu();
        $ids = array_unique(array_column($data, 'id'));
        $questionModels = Plan::find()->where(['plan_batch_id' => $ids])->groupBy('plan_batch_id')
            ->select(['plan_batch_id', 'question_model'])->indexBy('plan_batch_id')->asArray()->all();
        foreach ($data as &$datum) {
            $key = $datum['company_code'] . '_' . $datum['bu_code'];
            $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            $datum['created_time'] = date('Y-m-d H:i:s', $datum['created_at']);
            $datum['status_label'] = PlanBatch::BATCH_STATUS_ARR_LABEL[$datum['batch_status']];
            $datum['question_model'] = $questionModels[$datum['id']]['question_model'] ?? '';
            unset($datum['tool']);
        }
        return ['list' => $data, 'count' => (int)$count];
    }

    public function actionView()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id', 'page', 'page_size'])) {
            return $this->error();
        }

        $batchPlan = PlanBatch::findOne(['id' => $searchForm['id']], true);
        if ($batchPlan == null) {
            return $this->error('???????????????');
        }

        $query = $batchPlan->getPlanQuery($searchForm);

        $total = $query->count();
        $pager = ['page' => $searchForm['page'], 'page_size' => $searchForm['page_size']];
        $query->page($pager);

        $data = $query->asArray()->all();

        $standard_ids = array_column($data, 'standard_id');
        $standards = Standard::findAllArray(['id' => $standard_ids], ['id', 'protocol_id', 'title'], 'id');
        $protocol_ids = array_column($standards, 'protocol_id');
        $protocols = ProtocolTemplate::findAllArray(['id' => $protocol_ids], ['id', 'contract_code'], 'id');
        foreach ($data as &$datum) {
            $datum['standard_title'] = $standards[$datum['standard_id']]['title'];
            $datum['contract_code'] = $protocols[$standards[$datum['standard_id']]['protocol_id']]['contract_code'];
            $datum['plan_status_label'] = Plan::PLAN_STATUS_LABEL[$datum['plan_status']];
        }
        return ['count' => $total, 'list' => $data];
    }

    /**
     * ??????
     * ??????????????????????????????????????????????????????
     * ???????????????????????????????????????BU????????????ZFTID???
     * ???????????????????????????????????????????????????????????????plan???
     * @return array
     * @throws Exception
     */
    public function actionEnable()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id'])) {
            return $this->error();
        }
        $batchPlan = PlanBatch::findOne(['id' => $searchForm['id']], true);
        if ($batchPlan == null) {
            return $this->error('???????????????');
        }

        $res = PlanService::planBatchEnable($searchForm['id']);
        if ($res['status']) {
            return $this->success();
        } else {
            return $this->error($res['msg']);
        }
    }

    public function actionDisable()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id'])) {
            return $this->error();
        }
        $batchPlan = PlanBatch::findOne(['id' => $searchForm['id']], true);
        if ($batchPlan == null) {
            return $this->error('???????????????');
        }

        $res = PlanService::planBatchDisable($searchForm['id']);
        if ($res['status']) {
            return $this->success();
        } else {
            return $this->error($res['msg']);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function actionSave()
    {
        $form = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($form, ['tool_id', 'question_model'])) {
            return $this->error();
        }

        try {
            if ($form['id'] != '') {
                $model = $this->updatePlanBatch($form);
            } else {
                $model = $this->createPlanBatch($form);
            }
            return $this->success(['id' => $model->id]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * @param $form
     * @return PlanBatch
     */
    private function createPlanBatch($form)
    {
        $model = new PlanBatch();
        if (!$this->check($form, ['file_id'])) {
            $model->addError('file_name', '???????????????');
            return $model;
        }

        $model->tool_id = $form['tool_id'];
        $model->load($form, '');
        $model->file_name = $form['file_id'];

        $model->setScenario('create');
        PlanService::planBatchSave($model, $form);
        return $model;
    }

    /**
     * @param $form
     * @return PlanBatch|array|ActiveRecord
     * @throws Exception
     */
    private function updatePlanBatch($form)
    {
        $model = PlanBatch::findOne(['id' => $form['id']], true);
        if ($model == null) {
            return $this->error('???????????????');
        }
        $model->setScenario('update');
        // ??????????????????????????????
        if (isset($_FILES['upload_file'])) {
            PlanService::planBatchSave($model, $form);
            $QueuePrefixName = Yii::$app->remq::getQueueName('redis_queue', 'plan_batch_upload_process_prefix');
            $QueueNameProgress = $QueuePrefixName . '_' . $model->id;
            REMQ::setString($QueueNameProgress, ['status' => true, 'msg' => '', 'progress' => 0]);
            REMQ::setExpire($QueueNameProgress, 60);
        } else {
            $have = Plan::find()->where(['plan_status' => [Plan::PLAN_STATUS_ENABLE, Plan::PLAN_STATUS_DISABLE], 'plan_batch_id' => $form['id']])->asArray()->one();
            if (empty($have) && isset($form['question_model'])) {
                Plan::updateAll(['question_model' => $form['question_model']], ['plan_batch_id' => $form['id']]);
            }
            $model->load($form, '');
            $model->save();
        }

        return $model;
    }

    /**
     * ??????????????????
     * @return array
     */
    public function actionImportProgress()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id'])) {
            return $this->error();
        }

        $QueuePrefixName = Yii::$app->remq::getQueueName('redis_queue', 'plan_batch_save_process_prefix');
        $QueueName = $QueuePrefixName . '_' . $searchForm['id'];
        $result = Yii::$app->redis->get($QueueName);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if ($result['status']) {
                // ???????????????????????????
                $rs = $result;
            } else {
                return $this->error($result['msg']);
            }
        } else {
            $rs = [
                'progress' => 0
            ];
        }
        return $rs;
    }

    /**
     * ????????????
     * @return array
     */
    public function actionDeleteBatch()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id'])) {
            return $this->error();
        }
        $batchPlan = PlanBatch::findOne(['id' => $searchForm['id']], true);
        if ($batchPlan == null) {
            return $this->error('???????????????');
        }

        if ($batchPlan->batch_status == PlanBatch::BATCH_STATUS_ENABLE) {
            return $this->error('???????????????????????????');
        }

        PlanBatch::updateAll([PlanBatch::DEL_FIELD => PlanBatch::DEL_STATUS_DELETE], ['id' => $batchPlan->id]);
        Plan::updateAll([Plan::DEL_FIELD => Plan::DEL_STATUS_DELETE], ['plan_batch_id' => $batchPlan->id]);
        return $this->success();
    }

    public function actionStandardList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['id'])) {
            return $this->error();
        }
        $batchPlan = PlanBatch::findOne(['id' => $searchForm['id']], true);
        if ($batchPlan == null) {
            return $this->error('???????????????');
        }

        $plan = Plan::findAllArray(['plan_batch_id' => $batchPlan['id']], ['id', 'standard_id']);
        $standard_ids = array_column($plan, 'standard_id');
        $standards = Standard::findAllArray(['id' => $standard_ids], ['id', 'title']);
        return $this->success($standards);
    }

    /**
     * ??????????????????????????????
     */
    public function actionExcelImport()
    {
        $bodyForm = Yii::$app->request->post();
        //??????????????????
        $file = UploadedFile::getInstanceByName("file");
        if ($file) {
            //????????????????????????
            if (!in_array(strtolower($file->extension), ['xls', 'xlsx'])) {
                $this->responseMsg = '??????????????????';
                return $this->error();
            }
            if ($file->size > 1024 * 1024 * 8) {
                $this->responseMsg = '????????????????????????8M';
                return $this->error();
            }
            //????????????????????????
            $dir = Yii::getAlias('@runtime') . '/plan-batch/' . date('Ymd');
            if (!file_exists($dir)) {
                FileHelper::createDirectory($dir);
            }
            //????????????????????????????????????????????????
            $file_id = Yii::$app->getSecurity()->generateRandomString();
            $filepath = realpath($dir) . '/' . $file_id . '.' . $file->extension;
            if ($file->saveAs($filepath)) {
                //??????????????????
                $user = User::getSwireUser($bodyForm['token']);
                $user_arr = $user->getAttributes();
                $user_arr['swire_bu_code'] = $user->swire_bu_code;
                //?????????????????????
                $queue_data = ['file_id' => $file_id, 'path' => $filepath, 'ext' => ucfirst(strtolower($file->extension)), 'user' => $user_arr];
                $queue_name = Yii::$app->remq::getQueueName('queue_plan_batch_tmp', '', '', false);
                Yii::$app->remq->enqueue($queue_name, $queue_data);
                return $this->success(['file_id' => $file_id]);
            }

        } else {
            $this->responseMsg = '??????????????????';
            return $this->error();
        }
    }

    /**
     * ??????????????????????????????????????????
     */
    public function actionExcelImportProgress()
    {
        $params = Yii::$app->request->post();
        if (!$this->validateParam($params, ['file_id'])) {
            return $this->error();
        }
        $queue_name = Yii::$app->remq::getQueueName('queue_plan_batch_tmp', '', $params['file_id']);
        $result = Yii::$app->redis->get($queue_name);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if ($result['status']) {
                $count = PlanBatchTmp::find()->where(['file_id' => $params['file_id']])->count();
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
            $rs['success_num'] = PlanBatchTmp::find()->where(['file_id' => $params['file_id'], 'check_status' => PlanBatchTmp::CHECK_STATUS_PASS])->count();
            $rs['fail_num'] = PlanBatchTmp::find()->where(['file_id' => $params['file_id'], 'check_status' => PlanBatchTmp::CHECK_STATUS_FAIL])->count();
        }
        return $this->success($rs);
    }

    /**
     * ??????????????????????????????????????????
     */
    public function actionExcelImportFailDownload()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->validateParam($bodyForm, ['file_id'])) {
            return $this->error();
        }

        $one = PlanBatchTmp::find()->where([
            'file_id' => $bodyForm['file_id'],
            'check_status' => PlanBatchTmp::CHECK_STATUS_FAIL
        ])->one();
        if ($one == null) {
            return $this->error('?????????????????????');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['plan_batch_excel_import_fail_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, PlanBatchTmp::class, 'getExcelImportFailList', 'getExcelImportFailQuery');
    }

    /**
     * ??????????????????????????????????????????????????????
     */
    public function actionExcelImportFailDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        return self::DownloadProcess($bodyForm, 'plan_batch_excel_import_fail_download_process_prefix');
    }

    /**
     * ????????????????????????????????????
     */
    public function actionExcelTemplateDownload()
    {
        $path = '/resource/protocol_store_relation.xls';
        return $this->success(['path' => $path]);
    }
}
