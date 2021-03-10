<?php


namespace api\service\plan;

use api\models\apiModels\RequestGetStandardInfo;
use api\models\CheckType;
use api\models\EngineResult;
use api\models\apiModels\apiIneChannelModel;
use api\models\IneChannel;
use api\models\Plan;
use api\models\PlanBatch;
use api\models\PlanBatchTmp;
use api\models\PlanStoreRelation;
use api\models\PlanStoreTmp;
use api\models\ProtocolTemplate;
use api\models\share\MarketSegment;
use api\models\Question;
use api\models\QuestionBusinessType;
use api\models\QuestionOption;
use api\models\share\Scene;
use api\models\share\SceneType;
use api\models\Standard;
use api\models\Store;
use common\libs\file_log\LOG;
use api\models\Survey;
use api\models\Tools;
use Helper;
use yii\db\Exception;
use Yii;
use yii\db\Query;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

class PlanService
{
    //批量一次保存检查计划的条数
    const BATCH_SAVE_NUM = 100;

    /**
     * 检查项目对应协议变更了时间
     * 更新检查计划执行时间
     * @param $standard_id
     * @param $excute_from_date 20200601
     * @param $excute_to_date 20200701
     * @return bool
     */
    public static function syncPlanTime($standard_id, $excute_from_date, $excute_to_date, $excute_cycle_list)
    {
        $short_cycle = [];
        foreach ($excute_cycle_list as $v) {
            $tmp['start_time'] = Helper::dateTimeFormat($v['cycleFromDate'], 'Y-m-d');
            $tmp['end_time'] = Helper::dateTimeFormat($v['cycleToDate'], 'Y-m-d');
            $tmp['cycle_id'] = $v['cycleID'];
            $short_cycle[] = $tmp;
        }
        Plan::updateAll([
            'start_time' => Helper::dateTimeFormat($excute_from_date, 'Y-m-d 00:00:00'),
            'end_time' => Helper::dateTimeFormat($excute_to_date, 'Y-m-d 23:59:59'),
            'short_cycle' => json_encode($short_cycle)],
            ['standard_id' => $standard_id, Plan::DEL_FIELD => Plan::DEL_STATUS_NORMAL]);

        return true;
    }

    /**
     * 分页获取售点列表
     * @param $tool_id
     * @param $contract_id
     * @param $page
     * @param $page_size
     * @return array
     */
    public static function getStoreListByContractId($tool_id, $contract_id, $page, $page_size)
    {
        $plan = self::getPlanByContractStandardId($tool_id, $contract_id);
        $result = ['data' => [], 'total_page' => 0];
        if ($plan != null) {
            $result = self::getStoreId($plan[0]['id'], $page, $page_size);
        }
        return $result;
    }

    public static function getStoreListByStandardId($tool_id, $standard_id, $page, $page_size)
    {
        $plan = self::getPlanByContractStandardId($tool_id, '', $standard_id);
        $result = ['data' => [], 'total_page' => 0];
        if ($plan != null) {
            $result = self::getStoreId($plan[0]['id'], $page, $page_size);
        }
        return $result;
    }

    public static function getStoreId($plan_id, $page, $page_size)
    {
        $result = ['data' => [], 'total_page' => 0];
        if ($plan_id != null) {
            // 只会有1个检查计划
            $res = self::getStoreByPlanId($plan_id, $page, $page_size);
            $result['data'] = array_column($res['data'], 'store_id');
            $result['total_page'] = ceil($res['total'] / $page_size);
        }
        return $result;
    }

    /**
     * @param $tool_id
     * @param $contract_id
     * @param $standard_id
     * @return mixed|null
     */
    public static function getPlanByContractStandardId($tool_id, $contract_id, $standard_id = '')
    {
        if ($standard_id == '') {
            $protocol = ProtocolTemplate::findOneArray(['contract_id' => $contract_id]);
            if ($protocol == null) {
                return null;
            }
            $where = ['protocol_id' => $protocol['id']];
        } else {
            $where = ['standard_id' => $standard_id];
        }
        $query = Standard::find()->where($where)
            ->innerJoinWith('protocolPlan')->andWhere(['tool_id' => $tool_id, 'plan_status' => Plan::PLAN_STATUS_ENABLE])
            ->asArray();
        $standard = $query->one();
        if ($standard == null) {
            return null;
        }
        return $standard['protocolPlan'];
    }

    /**
     * @param $plan_id
     * @param $page
     * @param $page_size
     * @return array
     */
    public static function getStoreByPlanId($plan_id, $page, $page_size)
    {
        $offset = $page_size * ($page - 1);
        $query = PlanStoreRelation::find()->select(['id', 'store_id'])->where(['plan_id' => $plan_id])->offset($offset)->limit($page_size);
        $res = $query->asArray()->all();
        $total = $query->count();
        return ['data' => $res, 'total' => $total];
    }

    /**
     * @param $batch_id
     * @return array
     * @throws Exception
     */
    public static function planBatchEnable($batch_id)
    {
        $plan = Plan::findAll(['plan_batch_id' => $batch_id]);
        $flag = true;
        $msg = '';

        $tran = Yii::$app->db->beginTransaction();
        foreach ($plan as $item) {
            /* @var $item Plan */
            $item->setScenario('enable');
            $item->plan_status = Plan::PLAN_STATUS_ENABLE;
            if (!$item->save()) {
                $flag = false;
                $msg = '检查计划：' . $item->id . ' ' . $item->standard_one->title . '(' . $item->standard_one->id . ') ' . $item->getErrStr();
                break;
            }
        }
        PlanBatch::updateAll(['batch_status' => PlanBatch::BATCH_STATUS_ENABLE], ['id' => $batch_id]);
        if ($flag) {
            $tran->commit();
        } else {
            $tran->rollBack();
        }

        return ['msg' => $msg, 'status' => $flag];
    }


    /**
     * @param $batch_id
     * @return array
     * @throws Exception
     */
    public static function planBatchDisable($batch_id)
    {
        $plan = Plan::findAll(['plan_batch_id' => $batch_id]);
        $flag = true;
        $msg = '';

        $tran = Yii::$app->db->beginTransaction();
        foreach ($plan as $item) {
            /* @var $item Plan */
            $item->plan_status = Plan::PLAN_STATUS_DISABLE;
            if (!$item->save(false)) {
                $flag = false;
                $msg = '检查计划：' . $item->id . ' ' . $item->standard_one->title . '(' . $item->standard_one->id . ') ' . $item->getErrStr();
                break;
            }
        }
        PlanBatch::updateAll(['batch_status' => PlanBatch::BATCH_STATUS_DISABLE], ['id' => $batch_id]);
        if ($flag) {
            $tran->commit();
        } else {
            $tran->rollBack();
        }

        return ['msg' => $msg, 'status' => $flag];
    }

    /**
     * 批量生成检查计划
     *
     * @param $model &PlanBatch
     * @param $form
     */
    public static function planBatchSave(&$model, $form)
    {
        $tran = Yii::$app->db->beginTransaction();
        if ($model->isNewRecord) {
            if (!$model->save()) {
                throw new Exception('批量检查计划记录创建失败');
            }
        }
        $company_code = $model->company_code;
        $bu_code = $model->bu_code;
        //获取file_id对应的所有临时表数据
        $plan_batch_tmp_list = PlanBatchTmp::findAllArray(['file_id' => $model->file_name, 'check_status' => PlanBatchTmp::CHECK_STATUS_PASS], '*');
        //获取之前老的检查计划
        $old_plans = Plan::findAllArray(['plan_batch_id' => $model->id], ['id']);
        $old_plans_ids = array_column($old_plans, 'id');
        try {
            //删除之前老的检查计划和关联关系
            if ($old_plans_ids) {
                Plan::updateAll([Plan::DEL_FIELD => Plan::DEL_STATUS_DELETE], ['id' => $old_plans_ids]);
            }
            if ($old_plans_ids) {
                PlanStoreRelation::deleteAll(['plan_id' => $old_plans_ids]);
            }
            //分批创建检查计划
            $plan_batch_tmp_page = [];
            for ($i = 1; $i <= count($plan_batch_tmp_list); $i++) {
                $plan_batch_tmp_page[] = $plan_batch_tmp_list[$i - 1];
                if ($i % self::BATCH_SAVE_NUM == 0) {
                    self::_batchSave($plan_batch_tmp_page, $company_code, $bu_code, $model, Yii::$app->params['user_info']['id'], $form);
                    $plan_batch_tmp_page = [];
                }
            }
            //创建最后一批检查计划
            if ($plan_batch_tmp_page) {
                self::_batchSave($plan_batch_tmp_page, $company_code, $bu_code, $model, Yii::$app->params['user_info']['id'], $form);
            }
            $tran->commit();
        } catch (Exception $e) {
            LOG::log('批量检查计划创建失败' . $e->getMessage());
            $tran->rollBack();
            throw $e;
        } finally {
            Yii::$app->db->close();
            Yii::getLogger()->flush(true);
        }
    }

    /**
     * 批量创建检查计划时，检查上传的售点和协议是否有异常
     * @param $model PlanBatch
     * @param $file
     * @return array
     */
    public static function checkUploadFile($model, $file)
    {
        $primary_id = $model->id;
        preg_match('/.(xlsx?)$/', $file['name'], $matchs);
        if (empty($matchs)) {
            return ['status' => false, 'msg' => '文件格式错误'];
        }
        $ext = $matchs[1];
        $filesize = round($file['size'] / 1024 / 1024, 2);
        if ($filesize > 8) {
            return ['status' => false, 'msg' => '文件大小不能超过8M'];
        }
        $path = Yii::getAlias('@runtime');  //文件路径和文件名
        $file_name = '/' . $primary_id . '_' . time() . '.' . $ext;

        if (!is_writeable($path)) {
            return ['status' => false, 'msg' => '文件上传失败，目录没有权限'];
        }
        $path .= $file_name;
        if (!file_exists($file['tmp_name'])) {
            return ['status' => false, 'msg' => '上传的临时文件已经丢失'];
        }
        move_uploaded_file($file['tmp_name'], $path);
        if (strtoupper($ext) == 'XLS') {
            $ext = 'Xls';
        }
        if (strtoupper($ext) == 'XLSX') {
            $ext = 'Xlsx';
        }
        return ['status' => true, 'msg' => '', 'path' => $path, 'ext' => $ext];
    }

    /**
     * @param apiIneChannelModel $model
     * @return array
     */
    public static function getChannelInfo(apiIneChannelModel $model)
    {
        $ineChannel = IneChannel::findOneArray(['year' => $model->year, 'channel_id' => $model->channel_id]);
        if ($ineChannel == null) {
            return [];
        }
        $standard = Standard::findOneArray(['id' => $ineChannel['standard_id']]);
        if ($standard == null) {
            return [];
        }
        $result = Standard::getQuestionAndScenes([$standard]);
        $questionProperty = $result['question_property'];
        unset($result['question_property']);

        $scenes_type_id = $result['scenes_type_id'];
        $scenesCodeAll = Scene::find()->select(['id', 'scene_type', 'scene_code'])->indexBy('id')->orderBy(['sort' => SORT_ASC])->asArray()->all();
        $scenesTypeAll = ArrayHelper::index($scenesCodeAll, 'scene_code', 'scene_type');

        $scenes_tmp = [];
        foreach ($scenes_type_id as $item) {
            $scene_type_id = $item['scenes_type_id'];
            // 这里$item有999的情况
            if ($scene_type_id == SceneType::SCENE_TYPE_ALL || $scene_type_id == [SceneType::SCENE_TYPE_ALL]) {
                $scenes_tmp = $scenesCodeAll;
                break;
            }
            $scenes_tmp = array_merge($scenes_tmp, $scenesTypeAll[$scene_type_id]);
        }
        $scenes_code = array_column($scenes_tmp, 'scene_code');

        $scenes_code = array_merge($scenes_code, $result['scenes_code']);
        $result['scenes_code'] = array_unique($scenes_code);

        $standardScenesAll = Scene::findAllArray(['scene_code' => $result['scenes_code']],
            ['id', 'scene_type', 'scene_code', 'scene_maxcount', 'scene_need_recognition', 'sort', 'scene_name' => 'scene_code_name'], '', false, ['sort' => SORT_ASC]);
        $scenesTypeIds = array_column($standardScenesAll, 'scene_type');
        $sceneTypeAll = SceneType::findAllArray(['id' => $scenesTypeIds], ['id', 'name'], 'id');
        if (!empty($standardScenesAll)) {
            foreach ($standardScenesAll as &$tmpScene) {
                $tmpScene['scene_type_label'] = $sceneTypeAll[$tmpScene['scene_type']]['name'] ?? '';
            }
        }

        $questions = [];
        $questionsIds = array_merge($result['question_manual_ir'], $result['question_manual']);
        if (!empty($questionsIds)) {
//            $questionAll = Question::findAllArray(['id' => $questionsIds]);
            $questionAll = Question::find()->where(['id' => $questionsIds])->select(['*'])->asArray()->all();
            $questionOptionAll = QuestionOption::findAllArray(['question_id' => $questionsIds], ['id', 'question_id', 'value']);
            $scenesIds = array_column($questionAll, 'scene_type_id');
            $scenesAll = Scene::findAllArray(['id' => $scenesIds], ['id', 'scene_code'], 'id');
            $businessTypeAll = QuestionBusinessType::findAllArray([], ['*'], 'id');
            $questionOptionGroup = ArrayHelper::index($questionOptionAll, 'id', 'question_id');

            foreach ($questionAll as $item) {
                $itemOption = [];
                if (!empty($questionOptionGroup[$item['id']])) {
                    foreach ($questionOptionGroup[$item['id']] as $option) {
                        $itemOption[] = ['id' => $option['id'], 'value' => $option['value']];
                    }
                }

                $questions[] = [
                    'question_id' => $item['id'],
                    'question_title' => $item['title'],
                    'question_type' => $item['question_type'],
                    'scene_code' => $scenesAll[$item['scene_type_id']]['scene_code'] ?? '',
                    'type' => $item['type'],
                    'is_ir' => $item['is_ir'],
                    'must_take_photo' => $questionProperty[$item['id']]['must_take_photo'] ?? '0', // 是否必拍
                    'is_required' => $questionProperty[$item['id']]['is_required'] ?? '0',      // 是否必填
                    'business_type_id' => $item['business_type_id'],
                    'business_type_sort' => $businessTypeAll[$item['business_type_id']]['sort'] ?? '0',
                    'business_type_label' => $businessTypeAll[$item['business_type_id']]['title'] ?? '',
                    'question_options' => $itemOption,
                ];

            }
        }

        return [
            "ine_channel_id" => $ineChannel['id'],
            "standard_id" => $standard['id'],
            "standard_title" => $standard['title'],
            "standard_desc" => $standard['description'],
            "scenes" => $standardScenesAll,
            "questions" => $questions
        ];
    }

//    public static function

    /**
     * 厂房根据线路号和日期获取活动的数据
     * 长短期标识 1长期，2短期，协议类活动才有用
     * 只有sfa的检查计划
     * @param RequestGetStandardInfo $model
     * @return array
     */
    public static function getStandardInfo(RequestGetStandardInfo $model)
    {
        $query = new Query();
        $query->from(Plan::tableName() . ' p');
        $query->select([
            'id' => new Expression('max(p.id)'),
            'zft_id' => 'contract_id',
            'type_flag' => new Expression('if( ' . CheckType::LONG_AGREEMENTS['check_type_id'] . ' = check_type_id, 1' .
                ', if(' . CheckType::SHORT_AGREEMENTS['check_type_id'] . '=check_type_id' . ',2,0))'),
            'standard_id',
            'standard_title' => 'st.title',
            'standard_desc' => 'st.description',
            'start_time' => new Expression('left(max(start_time), 10)'),
            'end_time' => new Expression('left(max(end_time), 10)'),
            'store_count' => new Expression('count(distinct r.store_id)')
        ]);
        $query->andWhere(['and',
            ['=', 'p.company_code', $model->company_code],
            ['=', 'p.bu_code', $model->bu_code],
            ['=', 's.company_code', $model->company_code],
            ['=', 's.bu_code', $model->bu_code],
            ['in', 'p.tool_id', [Tools::TOOL_ID_SFA, Tools::TOOL_ID_CP]],
            ['=', 'plan_status', Plan::PLAN_STATUS_ENABLE],
            ['<=', 'start_time', $model->date. ' 00:00:00'],
            ['>=', 'end_time', $model->date. ' 23:59:59']
        ]);

        $query->leftJoin(PlanStoreRelation::tableName() . ' r', 'r.plan_id = p.id')
            ->leftJoin(Store::tableName() . ' s', 's.store_id = r.store_id');
        $query->leftJoin(Standard::tableName() . ' st', 'st.id = p.standard_id')
            ->leftJoin(ProtocolTemplate::tableName() . ' pt', 'pt.id = st.protocol_id');
        $query->andWhere(['s.route_code' => $model->route_code]);
//        $query->andWhere(new Expression("FIND_IN_SET('$model->route_code',route_code_str) "));

        $query->groupBy(['p.standard_id']);
        // 已拍照售点统计

        $data = $query->all();
        foreach ($data as &$item) {
            $storeInfo = PlanService::getStoreByPlan($item['id'], ['route_code' => $model->route_code]);
            $item['finished_store_count'] = $storeInfo['total'];
            $item['finished_store_list'] = $storeInfo['list'];
        }
        return $data;
    }

    /**
     * 根据检查计划id获取已拍照售点数据
     * @param $plan_id
     * @param $where
     * @return array
     */
    public static function getStoreByPlan($plan_id, $where)
    {
        $query = EngineResult::find()->alias('e')
            ->select(['e.id', 's.store_id', 'e.survey_code', 'photo_time' => new Expression('max(survey_time)')])
            ->where(['e.plan_id' => $plan_id, 's.route_code'=> $where['route_code']])->joinWith('survey')
            ->joinWith('survey.store s')
            ->groupBy(['s.store_id'])->asArray();
        $data = $query->all();
        $storeIds = array_column($data, 'store_id');
        $where['store_id'] = $storeIds;
        $storeAll = \api\models\share\Store::findAllArray($where, ['store_id', 'name'], 'store_id');
        foreach ($data as &$datum) {
            $store = $storeAll[$datum['store_id']] ?? '';
            $datum['store_name'] = $store['name'] ?? '';
            unset($datum['survey']);
            unset($datum['survey_code']);
        }
        return ['total' => $query->count(), 'list' => $data];
    }

    /**
     * 更新厂房下所有检查计划的售点关系表
     * 只对设置了筛选条件的售点进行处理
     * @param $company_code
     * @throws Exception
     */
    public static function updateCompanyPlanRelation($company_code)
    {
        $today = date('Y-m-d 00:00:00');
        $plans = Plan::find()->where(['company_code' => $company_code, 'plan_status' => Plan::PLAN_STATUS_ENABLE])
            ->andWhere(['>=', 'end_time', $today])->asArray()->all();
        if (!empty($plans)) {
            foreach ($plans as $plan) {
                self::updatePlanStoreRelation($plan);
            }
        }
    }

    /**
     * 更新检查计划下的售点关系表
     * 设置了筛选条件的检查计划才做处理
     * @param $plan
     * @throws Exception
     */
    public static function updatePlanStoreRelation($plan)
    {
        if (self::planHasFilter($plan)) {
            // 将售点状态进行变更
            $query = PlanService::getPlanStoreListByParams($plan);
            $tmpQuery = new Query();
            $tmpQuery->select(['store_id'])->from(new Expression(' (' . $query->createCommand()->getRawSql() . ') a'));
            $tmpQuery->select([new Expression($plan['id']), 'store_id']);
            $tran = Yii::$app->db->beginTransaction();
            PlanStoreRelation::deleteAll(['plan_id' => $plan['id']]);
            Yii::$app->db->createCommand("insert into " . PlanStoreRelation::tableName() . " (plan_id, store_id) " . $tmpQuery->createCommand()->getRawSql())
                ->execute();
            Plan::removeDuplicate($plan['id']);

            $tran->commit();
        }
    }

    /**
     * 获取匹配的售点
     * @param $plan
     * @param $match_status_arr
     * @return \api\models\bQuery|\yii\db\ActiveQuery|Query
     */
    public static function getPlanStoreListByParams($plan, $match_status_arr = [PlanStoreTmp::CHECK_STATUS_FILTER_PASS])
    {
        if (is_string($plan['screen_store_option']))
            $screen_option = json_decode($plan['screen_store_option'], true);
        else {
            $screen_option = $plan['screen_store_option'];
        }
        if (is_string($plan['delete_store_option']))
            $delete_option = json_decode($plan['delete_store_option'], true);
        else
            $delete_option = $plan['delete_store_option'];
        $andWhere = ['and'];
        $orWhere = ['or'];
        $deleteAndWhere = ['and'];
        $deleteOrWhere = ['or'];
        $fields = [
            'time' => 'time',
            'region_code' => 'region_code',
            'location_code' => 'location_code',
            'market_channel' => 'market_channel',
            'sub_channel_code' => 'sub_channel_code',
            'route_code' => 'route_code',
            'is_ka' => 'ka_indicator',
            'client_level' => 'market_segment_code',
            'has_icebox' => 'has_equipment',
            'market_segment' => 'market_segment',
        ];
        foreach ($fields as $key => $field) {
            self::fillWhere($screen_option, $andWhere, $orWhere, $key, $field);
        }

        $query = self::generateWhere($screen_option, $andWhere, $orWhere, $plan, PlanStoreTmp::IMPORT_TYPE_ADD, $match_status_arr);

        // 如果有剔除售点
        foreach ($fields as $key => $field) {
            self::fillWhere($delete_option, $deleteAndWhere, $deleteOrWhere, $key, $field);
        }
        if (!empty($delete_option)) {
            $query2 = self::generateWhere($delete_option, $deleteAndWhere, $deleteOrWhere, $plan, PlanStoreTmp::IMPORT_TYPE_DELETE, $match_status_arr)
                ->select(['main.store_id']);
            $query = (new Query())->from(['main' => $query])
                ->andWhere('store_id not in (select store_id from  (' . $query2->createCommand()->getRawSql() . ') tmp ) ');

//            $query = (new Query())->from(['main' => $query])
//                ->andWhere(['not in', 'store_id', $query2->select(['main.store_id'])]);
        }

        $query->select(['main.store_id']);

        // 如果没有筛选条件，并且导入表没有数据，直接返回空
        $plan['screen_store_option'] = json_encode($screen_option);
        $plan['delete_store_option'] = json_encode($delete_option);
        $tmpQuery = PlanStoreTmp::find()->where(['plan_id'=> $plan['id']]);
        if (!PlanService::planHasFilter($plan) && $tmpQuery->count() == 0) {
            $query->andWhere('1=0');
        }
        return $query;
    }

    /**
     * 对字段拼接where条件
     * @param $filter_option
     * @param $andWhere
     * @param $orWhere
     * @param $key
     * @param $field
     */
    private static function fillWhere($filter_option, &$andWhere, &$orWhere, $key, $field)
    {
        if (isset($filter_option[$key])) {
            if ($filter_option[$key]['logic'] == 'and') {
                // 时间字段单独处理
                self::_setWhere($andWhere, $filter_option, $key, $field);
            } else {
                self::_setWhere($orWhere, $filter_option, $key, $field);
            }
        }
    }

    /**
     * 设置条件
     * @param $filterWhere
     * @param $filter_option
     * @param $key
     * @param $field
     */
    private static function _setWhere(&$filterWhere, $filter_option, $key, $field)
    {
        $boolKey = ['is_ka' => [true => 'Y', false => 'N'], 'has_icebox' => [true => '1', false => '0']];
        $boolKeys = array_keys($boolKey);
        if ($key == 'time') {
            if (!empty($filter_option[$key]['create_time_start']) && !empty($filter_option[$key]['create_time_end'])) {
                $filterWhere[] = ['between', 'sap_create_date', $filter_option[$key]['create_time_start'] . ' 00:00:00', $filter_option[$key]['create_time_end'] . ' 23:59:59'];
            } else if (!empty($filter_option[$key]['create_time_end'])) {
                $filterWhere[] = ['<=', 'sap_create_date', $filter_option[$key]['create_time_end'] . ' 23:59:59'];
            } else {
                $filterWhere[] = ['>=', 'sap_create_date', $filter_option[$key]['create_time_start'] . ' 00:00:00'];
            }
        } else if (in_array($key, $boolKeys)) {
            $filterWhere[] = [$field => $boolKey[$key][$filter_option[$key][$key]]];
        } else if ($key == 'market_segment') {
            // 使用join
            $segment = MarketSegment::find()->select(['id', 'town_code'])
                ->where(['in', 'market_segment', $filter_option['market_segment']['market_segment']])
                ->asArray()->all();
            $town_code = array_column($segment, 'town_code');
            $filterWhere[] = ['in', 'town_code', $town_code];
        } else if ($key == 'client_level') {
            if (is_array($filter_option[$key][$key])) {
                foreach ($filter_option[$key][$key] as &$item) {
                    $item = str_pad($item, 2, '0', STR_PAD_LEFT);
                }
            }
            $filterWhere[] = [$field => $filter_option[$key][$key]];
        } else {
            $filterWhere[] = [$field => $filter_option[$key][$key]];
        }
    }

    /**
     * @param $filter_option
     * @param $andWhere
     * @param $orWhere
     * @param $plan
     * @param $import_type
     * @param $match_status_arr
     * @return \api\models\bQuery|\yii\db\ActiveQuery
     */
    private static function generateWhere($filter_option, $andWhere, $orWhere, $plan, $import_type, $match_status_arr = [PlanStoreTmp::CHECK_STATUS_FILTER_PASS])
    {
        $file_flag = $import_type == PlanStoreTmp::IMPORT_TYPE_ADD ? 'up_file' : 'delete_file';
        if (isset($filter_option[$file_flag])) {
            if ($filter_option[$file_flag]['logic'] == 'and') {
                if ($andWhere != ['and']) {
                    $andWhere = ['and', ['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']], $andWhere];
                }

                $queryUnion1 = PlanStoreTmp::find()->alias('main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr])
                    ->andWhere($andWhere)
                    ->innerJoin(Store::tableName() . ' s', 's.store_id = main.store_id');

                if ($orWhere == ['or']) {
                    return $queryUnion1;
                }

                $orWhere = ['and', ['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']], $orWhere];
                $queryUnion2 = Store::find()
                    ->alias('main')
                    ->select(['main.store_id'])
                    ->andWhere($orWhere);

                $unionStr = $queryUnion2->createCommand()->getRawSql();
                $query = $queryUnion1->union($unionStr);
            } else {
                $queryUnion1 = PlanStoreTmp::find()->alias('main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr]);

                // 有可能只有1个条件
                if ($andWhere == ['and'] && $orWhere == ['or']) {
                    return $queryUnion1;
                }
                $queryUnion2 = Store::find()
                    ->alias('main')
                    ->select(['main.store_id'])
                    ->andWhere($andWhere)
                    ->orWhere($orWhere)
                    ->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);

                $unionStr = $queryUnion2->createCommand()->getRawSql();
                $query = $queryUnion1->union($unionStr);
            }

            /*
            if ($filter_option[$file_flag]['logic'] == 'and') {
                // 使用join
                $query = PlanStoreTmp::find()
                    ->from(PlanStoreTmp::tableName() . ' main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr])
                    ->andWhere($andWhere)
                    ->orWhere($orWhere)
                    ->innerJoin(Store::tableName() . ' s', 'main.store_id = s.store_id and main.plan_id = '. $plan['id']);
                $query->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);
            } else {
                // 使用union
                $queryUnion1 = PlanStoreTmp::find()->alias('main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr]);
                // 筛选条件为空的情况，不查售点表
                if ($andWhere == ['and'] && $orWhere == ['or']) {
                    $query = $queryUnion1;
                } else {
                    $queryUnion2 = Store::find()
                        ->alias('main')
                        ->select(['main.store_id'])
                        ->andWhere($andWhere)
                        ->orWhere($orWhere);
                    $queryUnion2->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);

                    $unionStr = $queryUnion2->createCommand()->getRawSql();
                    $query = $queryUnion1->union($unionStr);
                }
            }*/
        } else {
            $query = Store::find()->alias('main');
            $query
                ->select(['main.store_id'])
                ->andWhere($andWhere)
                ->orWhere($orWhere);
            $query->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);
        }
        return $query;
    }


    private static function generateWhereBak($filter_option, $andWhere, $orWhere, $plan, $import_type, $match_status_arr = [PlanStoreTmp::CHECK_STATUS_FILTER_PASS])
    {
        $file_flag = $import_type == PlanStoreTmp::IMPORT_TYPE_ADD ? 'up_file' : 'delete_file';
        if (isset($filter_option[$file_flag])) {
            if ($filter_option[$file_flag]['logic'] == 'and') {
                // 使用join
                $query = PlanStoreTmp::find()
                    ->from(PlanStoreTmp::tableName() . ' main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr])
                    ->andWhere($andWhere)
                    ->orWhere($orWhere)
                    ->innerJoin(Store::tableName() . ' s', 'main.store_id = s.store_id and main.plan_id = ' . $plan['id']);
                $query->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);
            } else {
                // 使用union
                $queryUnion1 = PlanStoreTmp::find()->alias('main')
                    ->select(['main.store_id'])
                    ->where(['plan_id' => $plan['id'], 'import_type' => $import_type, 'check_status' => $match_status_arr]);
                // 筛选条件为空的情况，不查售点表
                if ($andWhere == ['and'] && $orWhere == ['or']) {
                    $query = $queryUnion1;
                } else {
                    $queryUnion2 = Store::find()
                        ->alias('main')
                        ->select(['main.store_id'])
                        ->andWhere($andWhere)
                        ->orWhere($orWhere);
                    $queryUnion2->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);

                    $unionStr = $queryUnion2->createCommand()->getRawSql();
                    $query = $queryUnion1->union($unionStr);
                }
            }
        } else {
            $query = Store::find()->alias('main');
            $query
                ->select(['main.store_id'])
                ->andWhere($andWhere)
                ->orWhere($orWhere);
            $query->andWhere(['company_code' => $plan['company_code'], 'bu_code' => $plan['bu_code']]);
        }
        return $query;
    }

    /**
     * 检查计划返回是否设置了筛选条件
     * @param $plan
     * @return bool
     */
    public static function planHasFilter($plan)
    {
        return ($plan['screen_store_option'] != '[]' && $plan['screen_store_option'] != '{}'
                && $plan['screen_store_option'] != '{"up_file":{"logic":"or"}}' && $plan['screen_store_option'] != '{"up_file":{"logic":"and"}}')
            || ($plan['delete_store_option'] != '[]' && $plan['delete_store_option'] != '{}'
                && $plan['delete_store_option'] != '{"up_file":{"logic":"or"}}' && $plan['delete_store_option'] != '{"up_file":{"logic":"and"}}');
    }

    // todo 未完成
    public static function getPlanStoreFilterQuery($plan)
    {
        $query = PlanStoreTmp::find()->leftJoin(Store::tableName(), PlanStoreTmp::tableName() . '.store_id = ' . Store::tableName() . '.store_id');
        $screen_option = $plan['screen_option'];
        $delete_option = $plan['delete_option'];
        if (isset($screen_option['region_code'])) {
            $query->where([$screen_option['region_code']['logic'], 'region_code', $screen_option['region_code']['region_code']]);
        }
        // todo


    }

    /**
     * 批量创建检查计划
     * @param $plan_batch_tmp_page
     * @param $company_code
     * @param $bu_code
     * @param $plan_batch
     * @param $user_id
     * @param $form
     * @throws Exception
     */
    private static function _batchSave($plan_batch_tmp_page, $company_code, $bu_code, $plan_batch, $user_id, $form)
    {
        //批量获取协议模板数据
        $contract_codes = array_column($plan_batch_tmp_page, 'contract_code');
        $protocol_templates = ProtocolTemplate::find()->where(['company_code' => $company_code, 'contract_code' => $contract_codes])->indexBy('contract_code')->asArray()->all();
        //批量获取成功图像标准数据
        $protocol_ids = array_column($protocol_templates, 'id');
        $standards = Standard::findAllArray(['company_code' => $company_code, 'protocol_id' => $protocol_ids], '*', 'protocol_id');
        //批量创建检查计划
        $plan_all = [];
        $plan_data = [
            'start_time' => $plan_batch['start_time'],
            'end_time' => $plan_batch['end_time'],
            'tool_id' => $plan_batch['tool_id']
        ];
        foreach ($standards as $item) {
            $plan = new Plan();
            $plan->standard_id = $item['id'];
            $plan->company_code = $company_code;
            $plan->plan_batch_id = $plan_batch['id'];
            $plan->bu_code = $bu_code;
            $plan->user_id = $user_id;
            $plan->rectification_model = $plan_batch['rectification_model'];
            $plan->rectification_option = (string)$plan_batch['rectification_option'];

            // 下面这两个只有上了1.6.4才能用
            if (isset($plan_batch['is_push_zft'])) {
                $plan->is_push_zft = $plan_batch['is_push_zft'];
            }
            if (isset($plan_batch['is_qc'])) {
                $plan->is_qc = $plan_batch['is_qc'];
            }
            //问卷填写模式
            $form['question_model'] = $form['question_model'] ?? 5;
            $plan->question_model = $form['question_model'];
            //设置是否需要qc
            if (in_array($form['question_model'], [1, 3, 4])) {
                $plan->need_question_qc = 1;
            } else {
                $plan->need_question_qc = 0;
            }
            $plan->load($plan_data, '');
            if (!$plan->save()) {
                throw new Exception($item['title'] . ' ' . $plan->getErrStr());
            }
            $plan_all[$plan->standard_id] = $plan->getAttributes();
            $plan_all[$plan->standard_id]['store_id'] = [];
        }
        //获取同一检查计划对应的门店编号
        foreach ($plan_batch_tmp_page as $plan_batch_tmp) {
            $template = $protocol_templates[$plan_batch_tmp['contract_code']];
            $standard = $standards[$template['id']];
            $plan_all[$standard['id']]['store_id'][] = $plan_batch_tmp['store_id'];
        }

        //批量创建检查计划售点关联关系
        foreach ($plan_all as &$plan) {
            $field = ['plan_id', 'store_id'];//测试数据键
            $insertData = [];
            $store_ids = array_unique($plan['store_id']);
            foreach ($store_ids as $store_id) {
                $insertData[] = [$plan['id'], $store_id];
            }
            //执行批量添加
            Yii::$app->db->createCommand()->batchInsert(PlanStoreRelation::tableName(), $field, $insertData)->execute();
            Plan::removeDuplicate($plan['id']);
        }
    }

    /**
     * 获取同周期同售点同计划走访的模型
     * @param $plan_id
     * @param $store_id
     * @param string $cycle_list
     * @param array $join
     * @param array $where
     * @return \api\models\bQuery|string
     */
    public static function getSamePlanModel($plan_id, $store_id, $cycle_list = '', $join = [], $where = [])
    {
        $start_time = $end_time = '';
        $plan = Plan::find()->with('standard')->where(['id' => $plan_id])->asArray()->one();
        if (!$cycle_list) {
            //根据检查计划整改模式获取检查计划起始时间
            $cycle_list = $plan['short_cycle'];
        }
        if (json_decode($cycle_list, true)) {
            //获取协议模板详情
//            $protocol_template = ProtocolTemplate::findOneArray(['id' => $plan['standard']['protocol_id']]);
            $excute_cycle_list = json_decode($cycle_list, true);
            foreach ($excute_cycle_list as $excute_cycle) {
                if ($excute_cycle['start_time'] <= date('Y-m-d') && date('Y-m-d') <= $excute_cycle['end_time']) {
                    $start_time = preg_replace('{^(\d{4})(\d{2})(\d{2})(.*?)$}u', '$1-$2-$3 00:00:00', $excute_cycle['start_time']);
                    $end_time = preg_replace('{^(\d{4})(\d{2})(\d{2})(.*?)$}u', '$1-$2-$3 23:59:59', $excute_cycle['end_time']);
                }
            }
        } else {
            $start_time = $plan['start_time'];
            $end_time = $plan['end_time'];
        }
        $model = '';
        if ($start_time && $end_time) {
            $where = $where ? $where : ['and', ['s.store_id' => $store_id], ['e.plan_id' => $plan_id]];
            $where = $start_time ? array_merge($where, [['>=', 's.survey_time', $start_time]]) : $where;
            $where = $end_time ? array_merge($where, [['<=', 's.survey_time', $end_time]]) : $where;
            $model = Survey::find()->alias('s');
            if (!$join) {
                $model->leftJoin('sys_engine_result e', 's.survey_code = e.survey_code');
            } else {
                foreach ($join as $v) {
                    $model->join($v['type'], $v['table'], $v['on']);
                }
            }
//                ->andwhere(['s.store_id' => $store_id, 'r.plan_id' => $plan_id])
//                ->andFilterWhere(['>=', 's.survey_time', $start_time])
//                ->andFilterWhere(['<=', 's.survey_time', $end_time])
            $model->andWhere($where);
        }
        return $model;
    }
}