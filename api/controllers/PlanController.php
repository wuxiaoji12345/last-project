<?php


namespace api\controllers;

use api\models\CheckStoreList;
use api\models\Plan;
use api\models\PlanStoreRelation;
use api\models\PlanStoreTmp;
use api\models\ProtocolStore;
use api\models\ProtocolTemplate;
use api\models\share\ChannelGroup;
use api\models\share\ChannelSub;
use api\models\share\CheckStoreQuestion;
use api\models\share\CheckStoreScene;
use api\models\share\MarketSegment;
use api\models\share\OrganizationRelation;
use api\models\share\ProtocolClientLevel;
use api\models\Standard;
use api\models\Store;
use api\models\Tools;
use api\models\User;
use Codeception\Util\HttpCode;
use common\libs\file_log\LOG;
use Helper;
use PhpOffice\PhpSpreadsheet\Exception;
use Yii;
use yii\db\Expression;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;

class PlanController extends BaseApi
{
    const ACCESS_ANY = [
        'tool-list',
        'tool-list-all',
        'standard-list',
        'standard-scene-list',
        'standard-scene-type-list',
        'manual-ready',
        'excel-store-list-progress',
        'excel-import-fail-download-progress',
        'channel-group-list',
        'protocol-client-level-list',
        'route-code-list',
        'market-segment-list',
        'region-list',
        'location-list',
    ];

    /**
     * 检查计划列表查询
     * @return array
     */
    public function actionPlanList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['page', 'page_size'])) {
            return $this->error();
        }

        $select = [
            new Expression(Plan::tableName() . '.company_code'),
            new Expression(Plan::tableName() . '.bu_code'),
            new Expression(Plan::tableName() . '.created_at'),
            new Expression(Plan::tableName() . '.id'),
            'start_time',
            'end_time',
            'tool_id',
            'rate_type',
            'rate_value',
            'plan_status',
            'standard_id'
        ];
        $where = $searchForm;
        $pager = [
            'page' => $searchForm['page'],
            'page_size' => $searchForm['page_size']
        ];
        // 时间处理
        $where['created_start'] = strtotime($where['create_start_time']);
        $where['created_end'] = strtotime($where['create_end_time'] . ' 23:59:59');

        $data = Plan::getList($select, $where, $pager, true, ['created_at' => SORT_DESC]);
        $user = Yii::$app->params['user_info'];
        $bu = OrganizationRelation::companyBu();
        $date = date('Y-m-d');
        foreach ($data['list'] as &$datum) {
            $key = $datum['company_code'] . '_' . $datum['bu_code'];
            $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            $datum['same_bu'] = $user['company_code'] == $datum['company_code'] && $user['bu_code'] == $datum['bu_code'];
            $datum['start_time'] = substr($datum['start_time'], 0, 10);
            $datum['end_time'] = substr($datum['end_time'], 0, 10);
            $datum['create_time'] = date('Y-m-d H:i', $datum['created_at']);
            $datum['standard_name'] = $datum['standard']['title'];
            $datum['tool_name'] = $datum['tool']['name'];
            $datum['rate_type'] = isset(Plan::RATE_TYPE_ARR[$datum['rate_type']]) ? Plan::RATE_TYPE_ARR[$datum['rate_type']] : '';
            $datum['ended'] = $date > $datum['end_time'];
            unset($datum['standard']);
            unset($datum['tool']);
        }
        return $data;
    }

    /**
     * 检查项目列表-启用状态且未被用户删除的标准
     * @return array
     */
    public function actionStandardList()
    {
        $select = [
            new Expression(Standard::tableName() . '.id'),
            new Expression(Standard::tableName() . '.title'),
            'check_type_id',
            'type',
            'is_need_qc',
            'scenes'
        ];

        $searchForm = Yii::$app->request->post();
        if (!isset($searchForm['standard_status']) || $searchForm['standard_status'] == '') {
            $searchForm['standard_status'] = [Standard::STATUS_AVAILABLE, Standard::STATUS_DISABLED];
        }
        //删除状态判断
        $searchForm['is_deleted'] = Standard::NOT_DELETED;
        $standard_list = Standard::getWithCheckType($searchForm, $select);
        $standardIds = array_column($standard_list, 'id');
        $questions = Standard::getStandardQuestion($standardIds);
        //是否有无生动化配置判断
        foreach ($standard_list as &$standard) {
            $output_list = ArrayHelper::getColumn(json_decode($standard['scenes'], true), 'outputList', []);
            if (isset($output_list[0]) && !empty($output_list[0])) {
                $standard['has_sub_activity'] = 1;
            } else {
                $standard['has_sub_activity'] = 0;
            }
            if (!empty($questions[$standard['id']])) {
                $standard['has_question'] = 1;
            } else {
                $standard['has_question'] = 0;
            }
            unset($standard['scenes']);
        }
        return ['list' => $standard_list];
    }

    /**
     * 执行工具列表
     * @return array
     */
    public function actionToolList()
    {
        $select = [
            'id',
            'name'
        ];
        $searchForm = Yii::$app->request->post();
        if (isset($searchForm['tool_status']) && $searchForm['tool_status'] != '') {
            return ['list' => Tools::findAllArray(['and', ['tool_status' => $searchForm['tool_status']], ['!=', 'id', 10]], $select)];
        } else {
            return ['list' => Tools::findAllArray(['!=', 'id', 10], $select)];
        }
    }

    /**
     * 执行工具列表
     * @return array
     */
    public function actionToolListAll()
    {
        $select = [
            'id',
            'name'
        ];
        $searchForm = Yii::$app->request->post();
        if (isset($searchForm['tool_status']) && $searchForm['tool_status'] != '') {
            return ['list' => Tools::findAllArray(['tool_status' => $searchForm['tool_status']], $select)];
        } else {
            return ['list' => Tools::findAllArray([], $select)];
        }
    }

    /**
     * 查询标准的问卷场景列表（合并）
     * @return array
     */
    public function actionStandardSceneList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['standard_id'])) {
            return $this->error();
        }
        $standard = Standard::findOne(['id' => $searchForm['standard_id']]);
        if ($standard == null) {
            return $this->error('规则标准不存在');
        }
        $scene_result = $standard->getSceneAndQuestion();
        return ['list' => $scene_result];

    }

    /**
     * 查询检查项目的场景列表
     * @return array
     */
    public function actionStandardSceneTypeList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['standard_id'])) {
            return $this->error();
        }
        $standard = Standard::findOne(['id' => $searchForm['standard_id']]);
        if ($standard == null) {
            return $this->error('检查项目不存在');
        }
        $scene_result = $standard->getSceneTypeList();
        return ['list' => $scene_result];
    }

    /**
     * 新增
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionCreate()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['start_time', 'end_time', 'standard_id', 'tool_id', 'reward_config', 'question_model'])) {
            return $this->error();
        }
        $model = new Plan();
        //问卷填写模式
        $model->question_model = $bodyForm['question_model'] ?? 5;
        //问卷填写模式
        $bodyForm['question_model'] = $bodyForm['question_model'] ?? 5;
        $bodyForm['question_model'] = intval($bodyForm['question_model']);
        $model->question_model = $bodyForm['question_model'];
        //设置是否需要qc
        if (in_array($bodyForm['question_model'], [1, 3, 4])) {
            $model->need_question_qc = 1;
        } else {
            $model->need_question_qc = 0;
        }
        $result = Plan::savePlan($model, $bodyForm);
        if ($result['success'] == true) {
            return $this->success(['id' => $model->id]);
        } else {
            $err = $model->getErrStr();
            return $this->error($err, -1);
        }
    }

    /**
     * 检查计划更新
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionEdit()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['id', 'start_time', 'end_time', 'question_model'])) {
            return $this->error();
        }
        $model = Plan::findOne(['id' => $bodyForm['id']]);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $model->company_code || $user['bu_code'] != $model->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }
        if ($model->plan_status == Plan::PLAN_STATUS_ENABLE) {
            $model->setScenario('update-time');
        } else {
            $model->setScenario('update');
        }

        $result = Plan::savePlan($model, $bodyForm);
        if ($result['success'] == true) {
            return $this->success(['id' => $model->id]);
        } else {
            $err = $model->getErrStr();
            return $this->error($err, -1);
        }
    }

    /**
     * 上传售点列表
     * 售点文件放入队列，脚本处理数据
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function actionStoreUpload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
//        if ($model->plan_status == Plan::PLAN_STATUS_ENABLE) {
//            return $this->error('启用状态的检查计划不能修改');
//        }

        $oldAttributeValue = $model->set_store_type;

        $model->setScenario('update-store');
        $model->load($bodyForm, '');

        $file = $_FILES['store_file'];
        $validate = Plan::checkStoreUploadFile($model->id, $file);
        if (!$validate['status']) {
            return $this->error($validate['msg'], -1);
        }

        // todo 上传的时候，先不更新db中的售点url及状态
        if ($model->save()) {
            $user = User::getSwireUser($bodyForm['token']);
            $user_arr = $user->getAttributes();
            $user_arr['swire_bu_code'] = $user->swire_bu_code;

            $queue = $bodyForm;
            $queue = array_merge($queue, ['set_store_type' => $oldAttributeValue, 'id' => $model->id, 'path' => $validate['path'], 'ext' => $validate['ext'], 'user' => $user_arr]);

            // 先删除旧的进度
//            $QueueName1 = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_tmp'] . '_' . $bodyForm['id'];
            $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', $bodyForm['id']);
            Yii::$app->redis->del($QueueName);

//            $QueueName2 = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_tmp'];
            $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', '', true);

            Yii::$app->remq->enqueue($QueueName, $queue);

            // 删除操作在导入的脚本处理
            // 如果之前是ZFT同步，现在是手动上传，需要把之前的售点数据清空
//            if($oldAttribute == Plan::SET_STORE_ZFT){
//                Plan::removeStore($model->id);
//            }
            return $this->success(['id' => $model->id]);
        } else {
            $err = $model->getErrStr();
            return $this->error($err, -1);
        }
    }

    /**
     * 检查计划完成设置
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionFinish()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['id', 'set_store_type'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $model->set_store_type = $bodyForm['set_store_type'];
        if ($model->save()) {
            $standard_new = Standard::findOneArray(['id' => $model->standard_id]);
            $model->syncStoreFromProtocol($standard_new);
            return $this->success();
        } else {
            return $this->error($model->getErrStr());
        }

    }

    /**
     * 上传售点excel进度查询
     * @return array
     */
    public function actionUploadProgressSearch()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }

        $import_type = $bodyForm['import_type'];

//        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_tmp'] . '_' . $bodyForm['plan_id'];
        $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', $bodyForm['plan_id'] . '_' . $import_type);
        $result = Yii::$app->redis->get($QueueName);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if ($result['status']) {
                // 把已配置的数量返回
//                $count = PlanStoreRelation::find()->where(['plan_id' => $model->id])->count();
                $count = PlanStoreTmp::find()->where(['plan_id' => $bodyForm['plan_id'], 'import_type' => $import_type, 'check_status' => [PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS, PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL]])->count();
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
            //
            $rs['success_num'] = PlanStoreTmp::find()->where(['plan_id' => $bodyForm['plan_id'], 'import_type' => $import_type, 'check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS])->count();
            $rs['fail_num'] = PlanStoreTmp::find()->where(['plan_id' => $bodyForm['plan_id'], 'import_type' => $import_type, 'check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL])->count();
        }
        return $rs;
    }

    /**
     * 导入成功数据
     */
    public function actionImportUpload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_PASS], ['import_type' => $bodyForm['import_type'], 'plan_id' => $model->id, 'check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS]);
        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_FAIL], ['import_type' => $bodyForm['import_type'], 'plan_id' => $model->id, 'check_status' => PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_FAIL]);
        return [];

    }

    public function actionUploadProgressReset()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }

//        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_tmp'] . '_' . $bodyForm['plan_id'];
        $QueueName = Yii::$app->remq::getQueueName('queue_plan_store_tmp', '', $bodyForm['plan_id']);
        Yii::$app->redis->del($QueueName);
        return $this->success();
    }

    /**
     * 启用检查计划
     * @return array
     */
    public function actionEnable()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $model->company_code || $user['bu_code'] != $model->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }

        $model->plan_status = Plan::PLAN_STATUS_ENABLE;
        $model->setScenario('enable');
        if ($model->save()) {
            return $this->success(['id' => $model->id, 'message' => '启用成功']);
        }

        return $this->error($model->getErrStr());

    }

    /**
     * 检查计划详情
     * @return array
     */
    public function actionView()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $standard = Standard::findOneArray(['id' => $model->standard_id]);

        // 检查计划已配置售点数量
        $query = PlanStoreRelation::find()->where(['plan_id' => $model->id]);
        $count = $query->count();
        // 检查计划已导入售点数量
        $query = PlanStoreTmp::find()->where(['plan_id' => $model->id, 'import_type' => PlanStoreTmp::IMPORT_TYPE_ADD, 'check_status' => [
            PlanStoreTmp::CHECK_STATUS_PASS,
            PlanStoreTmp::CHECK_STATUS_FILTER_PASS,
            PlanStoreTmp::CHECK_STATUS_FILTER_FAIL,
        ]]);
        $import_count = $query->count();
        $query = PlanStoreTmp::find()->where(['plan_id' => $model->id, 'import_type' => PlanStoreTmp::IMPORT_TYPE_DELETE, 'check_status' => [
            PlanStoreTmp::CHECK_STATUS_PASS,
            PlanStoreTmp::CHECK_STATUS_FILTER_PASS,
            PlanStoreTmp::CHECK_STATUS_IMPORT_TMP_SUCCESS,
        ]]);
        $except_count = $query->count();
        $reward_config = $model->getRewardConfig();
        $company_bu = User::getCompanyBu();
        $result = [
            'company_code' => $company_bu['company_code'],
            'bu_code' => $company_bu['bu_code'],
            'bu_name' => $company_bu['bu_name'],
            'store_count' => $count,
            'import_count' => $import_count,
            'except_count' => $except_count,
            'plan_id' => $model->id,
            'check_type_id' => $standard['check_type_id'],
            'standard_id' => (string)$model->standard_id,
            'tool_id' => $model->tool_id,
            'start_time' => substr($model->start_time, 0, 10),
            'end_time' => substr($model->end_time, 0, 10),
            'rate_type' => $model->rate_type,
            'plan_status' => $model->plan_status,
            'must_take_photo' => explode(',', $model->must_take_photo),
//            'rate_value'=> $model->rate_value,
            'reward_time' => $model->reward_time,
            'reward_amount' => $model->reward_amount,
            'reward_mode' => $model->reward_mode,
            'reward_config' => $reward_config,
            're_photo_time' => $model->re_photo_time,
            'photo_type' => $standard['photo_type'],
            'set_store_type' => $model->set_store_type,
            'is_push_zft' => $model->is_push_zft,
            'is_qc' => $model->is_qc,
            'screen_option' => json_decode($model->screen_store_option),
            'delete_option' => json_decode($model->delete_store_option),
            'short_cycle' => json_decode($model->short_cycle, true),
            'rectification_model' => $model->rectification_model,
            'question_model' => $model->question_model
        ];

        return $this->success(['plan' => $result]);
    }


    /**
     * 检查计划已配置的售点下载
     * @return array
     */
    public function actionDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['plan_store_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, Plan::class, 'getStoreList');
    }

    /**
     * 检查计划 协议签约售点
     * @return array
     */
    public function actionDownloadProtocolStore()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;
        $plan = Plan::findOneArray(['id' => $bodyForm['plan_id']]);
        $standard = Standard::findOneArray(['id' => $plan['standard_id']]);
        $protocol = ProtocolTemplate::findOneArray(['id' => $standard['protocol_id']]);

        $bodyForm['contract_id'] = $protocol['contract_id'];

        $redis_queue_key = Yii::$app->params['redis_queue']['plan_protocol_store_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, ProtocolStore::class, 'getProtocolStoreList');
    }

    /**
     * 检查计划已配置售点数据下载进度查询
     * @return array|mixed
     */
    public function actionStoreListProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_store_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->remq::getString($cacheKey);

        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 检查计划已配置售点数据下载进度查询
     * @return array|mixed
     */
    public function actionProtocolStoreListProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_protocol_store_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->remq::getString($cacheKey);

        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 检查计划删除
     * @return array
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function actionDelete()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }

        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $model->company_code || $user['bu_code'] != $model->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }

        if ($model->plan_status != Plan::PLAN_STATUS_ENABLE) {
            if ($model->delete()) {
                return $this->success(['id' => $model->id, 'message' => '删除成功']);
            } else {
                return $this->error('删除失败');
            }

        } else {
            return $this->error('检查计划当前是启用状态，无法删除');
        }
    }

    /**
     * 禁用
     * @return array
     */
    public function actionDisable()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }

        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $model->company_code || $user['bu_code'] != $model->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }

        $model->plan_status = Plan::PLAN_STATUS_DISABLE;
        if ($model->save(false)) {
            return $this->success(['id' => $model->id, 'message' => '禁用成功']);
        }

        return $this->error($model->getErrStr());
    }

    /**
     * 手动推送执行工具售点检查数据已生成就绪
     * @return array
     */
    public function actionManualReady()
    {

        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['task_id', 'date'])) {
            return $this->error();
        }

        $model = CheckStoreList::findOne(['task_id' => $bodyForm['task_id']]);
        if ($model == null) {
            return $this->error('未找到执行工具生成的售点数据');
        }
        // 不需要校验数据是否生成，有可能是空数据
//
//        $question = CheckStoreQuestion::findOne(['task_id' => $bodyForm['task_id'], 'date' => $bodyForm['date']]);
//        $scene = CheckStoreScene::findOne(['task_id' => $bodyForm['task_id'], 'date' => $bodyForm['date']]);
//        if ($question == null && $scene == null) {
//            return $this->error('问卷和拍照场景都未找到该批次对应的数据');
//        }

        $url = Yii::$app->params['tools']['update_question'];

        $tmp = $bodyForm;
        $tmp['tool_id'] = $model->tool_id;
        $tmp['date'] = date('Ymd', strtotime($tmp['date']));

        Plan::sendPost($tmp);

        return $this->error($model->getErrStr());
    }

    /**
     * 检查计划售点分发
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionDeployStore()
    {

        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !isset($bodyForm['plan_id']) || !isset($bodyForm['screen_option'])) {
            return $this->error();
        }
        if (isset($bodyForm['is_count']) && $bodyForm['is_count']) {
            $data = Store::findScreenStore($bodyForm, true);
            return $this->success($data);
        } else {
            $data = Store::findScreenStore($bodyForm);
            if ($data[0]) {
                return $this->success($data[1]);
            } else {
                return $this->error($data[1]);
            }
        }
    }

    /**
     * 查看数量
     */
    public function actionPlanStoreCount()
    {

        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['plan_id' => $bodyForm['plan_id']]);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }

        return $this->success([]);
    }


    /**
     * 已导入售点下载
     * @return array
     */
    public function actionExcelImportDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['plan_excel_store_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, Plan::class, 'getExcelImportList', 'getExcelImportQuery');
    }

    /**
     * 已导入售点下载 进度查询
     * @return array|mixed
     */
    public function actionExcelStoreListProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_excel_store_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->remq::getString($cacheKey);

        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 已导入售点失败数据下载
     * @return array
     */
    public function actionExcelImportFailDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['plan_id'])) {
            return $this->error();
        }

        $model = Plan::findOne(['id' => $bodyForm['plan_id']], true);
        if ($model == null) {
            return $this->error('检查计划不存在');
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['plan_excel_store_fail_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, Plan::class, 'getExcelImportFailList', 'getExcelImportFailQuery');
    }

    /**
     * 已导入售点失败数据下载 进度查询
     * @return array|mixed
     */
    public function actionExcelImportFailDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        return self::DownloadProcess($bodyForm, 'plan_excel_store_fail_download_process_prefix');
    }

    /**
     * 渠道组列表查询
     * @return array
     */
    public function actionChannelGroupList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = ChannelGroup::findAllArray([], ['channel_code', 'channel_name']);
        return $this->success($data);
    }

    /**
     * 协议客户级别列表查询
     * @return array
     */
    public function actionProtocolClientLevelList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = ProtocolClientLevel::findAllArray([], ['client_level', 'smart_describe name']);
        return $this->success($data);
    }

    /**
     * 线路列表查询
     * @return array
     */
    public function actionRouteCodeList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = OrganizationRelation::findAllArray([], ['route_code'], '', false, 'route_code');
        return $this->success($data);
    }

    /**
     * 市场区隔列表
     * @return array
     */
    public function actionMarketSegmentList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = MarketSegment::findAllArray([], ['market_segment', 'market_segment_desc'], '', false, 'market_segment');
        return $this->success($data);
    }

    /**
     * 大区列表
     * @return array
     */
    public function actionRegionList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = OrganizationRelation::findAllArray([], ['region_code', 'region_name'], '', false, 'region_code');
        return $this->success($data);
    }

    /**
     * 营业所列表
     * @return array
     */
    public function actionLocationList()
    {
        $bodyForm = Yii::$app->request->post();
        if (!$this->isPost()) {
            return $this->error();
        }
        $data = OrganizationRelation::find()->select(['location_code', 'location_name'])->where(['and',['<>','location_code',''],['status'=>OrganizationRelation::DEL_STATUS_NORMAL]])
            ->groupBy('location_code')->asArray()->all();
        return $this->success($data);
    }

}