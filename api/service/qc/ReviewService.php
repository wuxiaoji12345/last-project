<?php declare(strict_types=1);

namespace api\service\qc;

use api\models\CheckType;
use api\models\EngineResult;
use api\models\Plan;
use api\models\PlanQuery;
use api\models\QuestionAnswer;
use api\models\RuleOutputInfo;
use api\models\share\ChannelSub;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\Store;
use api\models\share\StoreBelong;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Survey;
use api\models\SurveyPlan;
use api\models\SurveyStandard;
use api\models\Tools;
use api\models\User;
use api\service\plan\PlanService;
use api\service\zft\SendService;
use Codeception\Example;
use common\libs\ding\Ding;
use common\libs\file_log\LOG;
use Exception;
use Yii;
use yii\data\Pagination;
use yii\db\Expression;

class ReviewService
{
    /**
     * 人工复核任务列表
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午5:54
     *
     * @param $searchForm
     *
     * @return array
     */
    public static function getManualReviewList($searchForm)
    {
        $res = Plan::getManualReviewList($searchForm);

        if (!empty($res['list'])) {
            $plan_id_arr = array_column($res['list'], 'id');
            $tool_id_arr = array_column($res['list'], 'tool_id');
            $standard_id_arr = array_column($res['list'], 'standard_id');

            $tool_name_arr = Tools::getName($tool_id_arr);
            $plan_total_arr = EngineResult::getPlanCountByIds($plan_id_arr);
            $plan_finish_total_arr = EngineResult::getPlanFinishByIds($plan_id_arr);
//            $standard_title_arr = Standard::getTitleByIds(array_unique($standard_id_arr));
            $survey_list_all = array_column($res['list'], 'survey_code');
            $standard_title_arr = Standard::getTitleByIds($standard_id_arr);

            foreach ($res['list'] as $k => &$v) {
//                $v['standard_title'] = $standard_title_arr[$v['standard_id']]['title'] ?? '';
                $v['standard_title'] = $standard_title_arr[$v['standard_id']]['title'] ?? '';
                $v['tool_name'] = $tool_name_arr[$v['tool_id']]['name'] ?? '';
                $v['plan_total'] = $plan_total_arr[$v['id']]['total'] ?? '0';
                $v['plan_finish_total'] = $plan_finish_total_arr[$v['id']]['total'] ?? '0';
                $v['remain_total'] = (string)($v['plan_total'] - $v['plan_finish_total']);

                $v['created_time'] = date('Y-m-d H:i:s', (int)$v['created_at']);
                $v['check_time'] = date('Y-m-d', strtotime($v['start_time'])) . '~' . date('Y-m-d', strtotime($v['end_time']));

                unset($v['created_at'], $v['start_time'], $v['end_time']);
            }
            unset($v);
        }

        return $res;
    }

    /**
     * 人工复核详情页中查看成功图像基本信息
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午2:55
     *
     * @param $searchForm
     *
     * @return SubActivity|array|\yii\db\ActiveRecord|null
     */
    public static function getSuccessImage($searchForm)
    {
        $image_info = SubActivity::getImageInfoById($searchForm['sub_activity_id']);

        if ($image_info) {
            if (!empty($image_info['scenes_code'])) {
                $let_scenes_code_arr = json_decode($image_info['scenes_code'], true);
                $let_scenes_code_arr = Scene::getSceneCodeName($let_scenes_code_arr);
                if ($let_scenes_code_arr) {
                    $image_info['scenes_code'] = implode('、', array_column($let_scenes_code_arr, 'scene_code_name'));
                } else {
                    $image_info['scenes_code'] = '';
                }
            } else {
                $image_info['scenes_code'] = '';
            }

            if (!empty($image_info['image'])) {
                $image_info['image'] = json_decode($image_info['image'], true);
            }
            return $image_info;
        }
        return [];
    }

    public static function qcDetail($params)
    {
        //返回数据
        $data = [];
        $engine_result = EngineResult::findOne(['id' => $params['engine_result_id']]);
        if (!$engine_result) return [false, "引擎结果不存在"];
        if (!$engine_result->plan) return [false, "检查计划不存在"];
        if (!$engine_result->survey) return [false, "走访记录不存在"];
        $share_store = Store::find()->where(['store_id' => $engine_result->survey->store_id])->one();
        if (!$share_store) return [false, "售点信息不存在"];
        //获取识别结果图片
//        $where = ['s.survey_code' => $engine_result->survey_code];
        $where = ['e.id' => $params['engine_result_id']];
        $images = Survey::getReportImage($where, '', '')['list'] ?? [];
        #############################开始拼接前端参数#############################
        //检查时间
//        $data['check_time'] = strtotime($engine_result->result_time);
        // 用走访时间
        $data['check_time'] = $images[0]['survey_time'];
        //走访号
        $data['survey_code'] = $engine_result->survey_code;
        //是否为整改 0-非整改 1-整改
        $data['is_rectify'] = $engine_result->is_rectify;
        //线路
        $data['route_code'] = $engine_result->survey->route_code;
        //售点编号
        $data['store_id'] = $share_store->store_id;
        //售点名称
        $data['store_name'] = $share_store->name;
        //修改原因
        $data['review_reason'] = $engine_result->review_reason;
        //图片
        $data['images'] = [];
        foreach ($images as $imageArr) {
            foreach ($imageArr['image'] as $image) {
                if (isset($image['imageUrl'])) {
                    foreach ($image['imageUrl'] as $singleImage) {
                        $data['images'][] = [
                            'image_url' => $singleImage['image_url'],
                            'sub_activity_id' => $image['sub_activity_id'],
                            'sub_activity_name' => $image['subActivity']['activation_name'] ?? null,
                            'scene_name' => $image['scene_id_name'],
                            'time' => $image['created_at'],
                            'question' => $singleImage['question'] ?? '',
                            'img_type' => $singleImage['img_type'],
                        ];
                    }
                }
            }
        }
        //引擎原始输出结果
        $data['result'] = $engine_result->getOutputInfoValue($engine_result->result);
        //QC输出结果
        $data['qc_result'] = $engine_result->getOutputInfoValue($engine_result->qc_result);
        return [true, $data];
    }

    //保存QC结果
    public static function saveQcResult($params)
    {
        $engine_result = EngineResult::findOne(['id' => $params['engine_result_id']]);
        if (!$engine_result) return [false, "引擎结果不存在"];
        if (!$engine_result->plan) return [false, "检查计划不存在"];
        if ($engine_result->qc_status != EngineResult::ENGINE_RESULT_QC_DEFAULT) return [true, $engine_result];
        $engine_result->qc_result = json_encode($params['qc_result']);
        $engine_result->review_reason = $params['review_reason'];
        $engine_result->qc_status = EngineResult::ENGINE_RESULT_QC_DOWN;
        //qc完之后把need_qc状态改为需要qc
        $engine_result->is_need_qc = EngineResult::IS_NEED_QC_YES;
        if (!$engine_result->save()) {
            return [false, $engine_result->errors];
        }
        //推送支付通
        //此处不能用全等于，因为从数据库里取出来的都是字符串
        if ($engine_result->plan->is_push_zft == 1) {
            $data = [
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
                'output_list' => $params['qc_result']
            ];
            if (!$res = SendService::sendResultToZft($data)) {
                LOG::log("走访号:" . $engine_result->survey_code . " 支付通推送失败");
//                Ding::getInstance()->sendTxt("支付通推送失败");
            }
        }
        //推送CP
        if ($engine_result->plan->tool_id == 2) {
            //拼接CP参数
            $data = [
                'is_qc' => true,
                'standard_id' => $engine_result->standard_id,
                'store_id' => $engine_result->survey->store_id,
                'tool_id' => $engine_result->plan->tool_id,
                'survey_code' => $engine_result->survey_code,
                'output_list' => $params['qc_result']
            ];
            if (!$res = SendService::newSendResultToCP($data)) {
                Yii::error("CP推送失败");
            }
        }
        return [true, $engine_result];
    }

    /**
     * 获取人工复核结果列表
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午7:14
     *
     * @param $searchForm
     *
     * @return \api\models\Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getManualCheckResultList($searchForm)
    {
//        if (isset($searchForm['channel_id']) and !empty($searchForm['channel_id'])) {
//            $searchForm['sub_channel_arr'] = ChannelSub::getSubChannelCodeByMainID($searchForm['channel_id']);
//        }

        $searchForm['sub_channel_id'] = $searchForm['channel_id'] ?? '';
        $res = EngineResult::getManualCheckResultList($searchForm);

        $res['map'] = [];
        if (!empty($res['list'])) {
            $tool_id_arr = array_column($res['list'], 'tool_id');
            $standard_id_arr = array_unique(array_column($res['list'], 'standard_id'));
            $region_code_arr = array_column($res['list'], 'region_code');

            $tool_name_arr = Tools::getName($tool_id_arr);
            $region_code_name_arr = StoreBelong::getNameByIds(array_unique($region_code_arr));

            $survey_list_all = array_column($res['list'], 'survey_code');
//            $standard_title_arr = Standard::getTitleByIds($standard_id_arr);
            $standard_title_arr = SurveyStandard::getTitleByIds($survey_list_all);

            $check_name_arr = SurveyStandard::getCheckTypeNameByIds($survey_list_all);

            $ruleOutput = RuleOutputInfo::getQcOutputInfo($standard_id_arr);
            foreach ($res['list'] as $k => &$v) {
                $tmpArr = isset($standard_title_arr[$v['survey_code']]['need_qc_data']) ? json_decode($standard_title_arr[$v['survey_code']]['need_qc_data'], true) : [];
                $result = self::arrayCovert($tmpArr);

                $v['need_qc_data'] = $result;
            }
            if (!empty($ruleOutput)) {
                $res['map'] = EngineResult::getMap($res['list'], $ruleOutput, 'qc_result', true);
            }
            $bu = OrganizationRelation::companyBu();
            foreach ($res['list'] as $k => &$v) {
                $v['standard_title'] = $standard_title_arr[$v['survey_code']]['title'] ?? '';
                $v['tool_name'] = $tool_name_arr[$v['tool_id']]['name'] ?? '';

                $v['region_code_name'] = $region_code_name_arr[$v['region_code']]['name'] ?? '';
                $v['check_type_title'] = $check_name_arr[$v['standard_id']]['title'] ?? '';

                $key = $v['company_code'] . '_' . $v['bu_code'];
                $v['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
                switch ($v['qc_status']) {
                    case 1:
                        $v['qc_status'] = '复核成功';
                        break;
                    case 2:
                        $v['qc_status'] = '放弃复核';
                        break;
                    default:
                        $v['qc_status'] = '';
                }

                if (!empty($v['qc_result'])) {
                    $v['qc_result'] = json_decode($v['qc_result'], true);
                    $v['qc_result'] = array_column($v['qc_result'], 'output', 'node_index');
                } else {
                    $v['qc_result'] = [];
                }

                if (empty($v['review_reason'])) $v['review_reason'] = '';
            }
            unset($v);
        }

        return $res;
    }

    /**
     * @param $searchForm
     * @return array
     */
    public static function getSurvey($searchForm)
    {
        $query = PlanQuery::getQcSurveyQuery($searchForm);
        $pager = ['page' => $searchForm['page'], 'page_size' => $searchForm['page_size']];
        //按走访时间倒叙
        $query->orderBy(['survey_time' => SORT_DESC]);
        $query->page($pager);
        $data = $query->all();
        $count = $query->count();
        $rule_query = RuleOutputInfo::find()
            ->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id', 'status', 'formats'])
            ->orderBy(['status' => SORT_DESC, 'sort_id' => SORT_ASC])
            ->asArray();
        //        $ruleOutput = array_column($ruleOutput, 'node_index');

        //输出项删除时规则还未启用不展示在规则引擎结果里
        if (isset($searchForm['standard_id']) && $searchForm['standard_id'] != '') {
            $rule_query->where(['standard_status' => RuleOutputInfo::STANDARD_START_YES, 'standard_id' => $searchForm['standard_id']]);
        }
        $ruleOutput = $rule_query->all();
        $standardIds = array_column($data, 'standard_id');
        $standardAll = Standard::findAllArray(['id' => $standardIds], ['id', 'title', 'check_type_id', 'need_qc_data'], 'id');
        $checkTypeIds = array_column($standardAll, 'check_type_id');
        $checkTypeAll = CheckType::findAllArray(['id' => $checkTypeIds], ['id', 'title'], 'id');
        $toolIds = array_column($data, 'tool_id');
        $tools = Tools::findAllArray(['id' => $toolIds], ['id', 'name'], 'id');
        //大区名称
        $region_code_arr = array_column($data, 'region_code');
        $region_code_name_arr = StoreBelong::getNameByIds(array_unique($region_code_arr));
        foreach ($data as &$datum) {
            if (isset($standardAll[$datum['standard_id']])) {
                $datum['standard_title'] = $standardAll[$datum['standard_id']]['title'];
                $tmpArr = json_decode($standardAll[$datum['standard_id']]['need_qc_data'], true);
                $datum['need_qc_data'] = self::arrayCovert($tmpArr);;
            } else {
                $datum['standard_title'] = '';
                $datum['need_qc_data'] = [];
            }
            $datum['tool_name'] = isset($tools[$datum['tool_id']]) ? $tools[$datum['tool_id']]['name'] : '';
            if (isset($standardAll[$datum['standard_id']])) {
                $datum['check_type_label'] = $checkTypeAll[$standardAll[$datum['standard_id']]['check_type_id']]['title'];
            }
            $datum['is_rectify_label'] = EngineResult::IS_RECTIFY_EXPLAIN[$datum['is_rectify']];
            $datum['region_name'] = $region_code_name_arr[$datum['region_code']]['name'] ?? '';
        }

        $map = EngineResult::getMap($data, $ruleOutput, 'result', true);
        return ['list' => $data, 'count' => $count, 'map' => $map];
    }

    /**
     * Qc获取下一个走访号
     * @param $searchForm
     * @return array
     */
    public static function getNextSurveyCode($searchForm)
    {
        $query = PlanQuery::getQcSurveyQuery($searchForm);
        // 如果总数比当前条件少，取最后一条
        $count = $query->count();
        $page = $searchForm['page'];
        $page_size = $searchForm['page_size'];
        $page_offset = $searchForm['offset'];
        $offset = (($page - 1) * $page_size) + $page_offset - 1;
        if ($count <= $offset) {
            $query->offset($count);
        } else {
            $query->offset($offset);
        }

        $one = $query->one();
        return ['id' => $one['id'], 'remain_count' => $query->count(), 'plan_id' => $searchForm['plan_id'], 'survey_code' => $one['survey_code']];
    }

    /**
     * 放弃一批未q的走访
     * @param $searchForm
     * @return array
     */
    public static function ignoreSurvey($searchForm)
    {
        $query = PlanQuery::getQcSurveyQuery($searchForm, 'e.id');
//        $whereStr = 'id in (select id from (' . $query->createCommand()->getRawSql() . ') a)';
        $ids = $query->asArray()->all();
        $ids = array_column($ids, 'id');
        $rows = EngineResult::updateAll(['qc_status' => EngineResult::ENGINE_RESULT_QC_IGNORE], ['id' => $ids]);
        //放弃复核也要发送zft，将所有数据分批送给发送zft脚本
        $query1 = PlanQuery::getQcSurveyQuery($searchForm, 'e.id', false);
//        $whereStr1 = 'id in (select id from (' . $query1->createCommand()->getRawSql() . ') a)';
        $model = $query1;
        $count = $model->count();
        for ($i = 0; $i < $count; $i += 200) {
            $pagination = new Pagination(['pageSize' => 200, 'page' => $i]);
            $model->offset($pagination->offset)->limit($pagination->limit);
            $data = $model->asArray()->all();
            $is_ignore = true;
            Yii::$app->remq->enqueue(Yii::$app->params['queue']['send_zft'] . Yii::$app->params['project_id'],
                compact('data', 'is_ignore'));
        }

        return ['rows' => $rows];
    }

    private static function arrayCovert($tmpArr)
    {
        if (empty($tmpArr)) {
            return [];
        }
        $result = [];
        array_walk_recursive($tmpArr, function ($value) use (&$result) {
            array_push($result, $value);
        });
        return $result;

    }

}
