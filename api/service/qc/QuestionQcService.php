<?php


namespace api\service\qc;

use api\models\Image;
use api\models\ImageUrl;
use api\models\Plan;
use api\models\Question;
use api\models\QuestionAnswerQc;
use api\models\QuestionOption;
use api\models\QuestionQcIgnoreTmp;
use api\models\share\OrganizationRelation;
use api\models\share\Store;
use api\models\Standard;
use api\models\Survey;
use api\models\SurveyPlan;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * 问卷QC
 * Class QuestionQcService
 * @package api\service\qc
 */
class QuestionQcService
{
    const QUESTION_MODEL = [
        '1' => '前端必填、后端选填',
        '2' => '前端必填、后端不需填',
        '3' => '前端选填、后端必填',
        '4' => '前端不需填、后端必填',
        '5' => '无',
    ];

    /**
     * 详情页模式
     */
    const DETAIL_QC_MODE = 1;   //复核
    const DETAIL_VIEW_MODE = 2; //查看

    /**
     * 问卷QC任务详情
     *
     * @param int $qc_task_id Qc任务ID
     * @param array $condition Qc任务列表查询条件
     * @return array
     */
    public static function getDetail($qc_task_id, $condition = [])
    {
        $qc_task = [];
        //1、获取QC任务信息
        $survey_plan = SurveyPlan::findOneArray(['id' => $qc_task_id]);
        if ($survey_plan) {
            //2、获取走访详情
            $qc_task['survey'] = self::getSurvey($survey_plan['survey_code']);
            //3、获取所有图片(识别图片和问卷图片)，及对应的生动化信息和场景信息、问卷信息
            $qc_task['images'] = self::getImageList($survey_plan['survey_code']);
            //4、获取QC任务所有问卷信息，按场景ID分组
            $qc_task['questions'] = self::getQuestionList($survey_plan['survey_code'], $survey_plan['plan_id']);
            //5、获取剩余QC任务数、获取下一条QC任务ID、上一条QC任务ID、修改原因
            $qc_task['qc_task'] = self::getNearIDAndCount($survey_plan['plan_id'], $qc_task_id, $qc_task['survey']['survey_time'], $condition);
            $qc_task['qc_task']['reason'] = (string)$survey_plan['description'];
            $qc_task['qc_task']['question_qc_status'] = $survey_plan['question_qc_status'];
        }
        return $qc_task;
    }

    /**
     * 获取QC任务走访信息
     *
     * @param string $survey_code 走访号
     * @return array|\yii\db\ActiveRecord|null
     */
    public static function getSurvey($survey_code)
    {
        $survey_return = [];
        $survey = Survey::findOneArray(['survey_code' => $survey_code]);
        if ($survey) {
            $survey_return = [
                'survey_time' => $survey['survey_time'],
                'survey_code' => $survey['survey_code'],
                'route_code' => $survey['route_code'],
                'store_id' => $survey['store_id'],
                'store_name' => $survey['store_name'],
            ];
            //查询清单店售点地址
            if ($survey['is_inventory'] == 1) {
                $store = Store::findOneArray(['store_id' => $survey['store_id']]);
                $survey_return['store_name'] = ArrayHelper::getValue($store, 'name', '');
            }
        }
        return $survey_return;
    }

    /**
     * 获取QC任务所有图片(识别图片和问卷图片)，及对应的生动化信息和场景信息、问卷信息
     *
     * @param string $survey_code 走访号
     * @return array
     */
    public static function getImageList($survey_code)
    {
        $image_list = [];
        //3-1、
        $images = Image::find()->with([
            'subActivity' => function ($query) {
                $query->select('id, activation_id, activation_name');
            },
            'imageUrl' => function ($query) {
                $query->select('id, image_id, image_url, image_key, img_type, question_id')->where(['status' => ImageUrl::DEL_STATUS_NORMAL]);
            },
            'imageUrl.question' => function ($query) {
                $query->select('id, title, question_type')->where(['status' => Question::DEL_STATUS_NORMAL]);
            }])
            ->select('id, scene_id, scene_id_name, sub_activity_id, created_at')
            ->where(['survey_code' => $survey_code])->asArray()->all();
        //3-2、拼接图片信息
        foreach ($images as $image) {
            foreach ($image['imageUrl'] as $image_url) {
                array_push($image_list, [
                    'created_at' => date('Y-m-d H:i:s', $image['created_at']),
                    'sub_activity_id' => ArrayHelper::getValue($image, 'subActivity.activation_id', 0),
                    'sub_activity_name' => ArrayHelper::getValue($image, 'subActivity.activation_name', ''),
                    'scene_id' => $image['scene_id'],
                    'scene_name' => $image['scene_id_name'],
                    'image_url' => ArrayHelper::getValue($image_url, 'image_url', ''),
                    'image_key' => ArrayHelper::getValue($image_url, 'image_key', ''),
                    'question' => ArrayHelper::getValue($image_url, 'question.title', ''),
                    'img_type' => ArrayHelper::getValue($image_url, 'img_type', 0),
                ]);
            }
        }
        return $image_list;
    }

    /**
     * 获取QC任务所有问卷信息，按场景ID分组
     *
     * @param string $survey_code 走访号
     * @param int $plan_id 检查计划ID
     * @return array
     */
    public static function getQuestionList($survey_code, $plan_id)
    {
        $question_list = [];
        $question_answer_qcs = QuestionAnswerQc::find()->with([
            'question' => function ($query) {
                $query->select('id, title, question_type');
            },
            'question.options' => function ($query) {
                $query->select('id, question_id, option_index, name, value');
            }])
            ->select('id, image_id, question_id, answer')
            ->where([
                'survey_code' => $survey_code,
                'plan_id' => $plan_id
            ])->asArray()->all();
        //查询检查计划详情，用于判断问卷是否必填
        $plan = Plan::find()->with('standard')->where(['id' => $plan_id])->asArray()->one();
        $standard_questions = [];
        if (!empty($plan) && !empty($plan['standard'])) {
            $question_and_scenes = Standard::getQuestionAndScenes([$plan['standard']]);
            $standard_questions = ArrayHelper::getValue($question_and_scenes, 'questions', []);
        }
        //获取图片信息，场景ID、名称
        $image_ids = ArrayHelper::getColumn($question_answer_qcs, 'image_id', []);
        $images = Image::findAllArray(['id' => $image_ids], ['id', 'scene_id', 'scene_id_name']);
        //问卷按场景ID分组
        foreach ($images as $key => $image) {
            $question_list[$key] = [
                'scene_id' => $image['scene_id'],
                'scene_id_name' => $image['scene_id_name'],
                'question_list' => [],
            ];
            foreach ($question_answer_qcs as $question_answer_qc) {
                if ($question_answer_qc['image_id'] == $image['id']) {
                    //问卷是否必填，默认非必填
                    $is_required = 0;
                    //检查计划问卷填写模式为前端必填、后端不需填时问卷默认为非必填，反之根据检查项目配置判断问卷是否必填
                    if ($plan['question_model'] != 2) {
                        foreach ($standard_questions as $question) {
                            if ($question_answer_qc['question_id'] == $question['id']) {
                                $is_required = ArrayHelper::getValue($question, 'is_required', 0);
                            }
                        }
                    }
                    //问卷信息
                    array_push($question_list[$key]['question_list'], [
                        'id' => $question_answer_qc['question_id'],
                        'title' => ArrayHelper::getValue($question_answer_qc, 'question.title', ''),
                        'type' => ArrayHelper::getValue($question_answer_qc, 'question.question_type', 0),
                        'options' => ArrayHelper::getValue($question_answer_qc, 'question.options', []),
                        'answer' => $question_answer_qc['answer'],
                        'is_required' => $is_required,
                    ]);
                }
            }
        }
        return $question_list;
    }

    /**
     * 批量复核
     * @param $file_id
     * @param $qc_value
     * @throws \yii\db\Exception
     */
    public static function BatchQc($file_id, $qc_value)
    {
        //查询问卷qc批量复核上传临时文件表
        $question_qc_ignore_tmp = QuestionQcIgnoreTmp::findOneArray(['file_id' => $file_id, 'check_status' => QuestionQcIgnoreTmp::CHECK_STATUS_PASS]);
        if ($question_qc_ignore_tmp) {
            $plan = Plan::findOneArray(['id' => $question_qc_ignore_tmp['plan_id']]);
            if ($plan['question_model'] == Plan::FRONT_SAFE_BACK_REQUIRED || $plan['question_model'] == Plan::FRONT_NOT_BACK_REQUIRED) {
                $update_filed_sql = "qc.answer = '$qc_value', sp.question_qc_status = " . SurveyPlan::QUESTION_QC_STATUS_BATCH_DONE;
            } else {
                $update_filed_sql = "sp.question_qc_status = " . SurveyPlan::QUESTION_QC_STATUS_BATCH_DONE;
            }
        }
        //查询文件对应的所有检查计划及检查计划对应的走访数
        $sql = "SELECT count(sp.id) as total, sp.plan_id, sp.tool_id 
                FROM sys_survey_plan sp 
                LEFT JOIN sys_survey s ON sp.survey_code = s.survey_code
                LEFT JOIN sys_question_qc_ignore_tmp t on t.plan_id = sp.plan_id and t.store_id = s.store_id 
                WHERE t.file_id = '$file_id' and s.survey_status = " . Survey::SURVEY_END .
                    " and t.id is not null and sp.need_question_qc = " . SurveyPlan::NEED_QC_YES .
                    " and sp.question_qc_status = " . SurveyPlan::QUESTION_QC_STATUS_DEFAULT .
                    " and t.check_status = " . QuestionQcIgnoreTmp::CHECK_STATUS_PASS .
                " GROUP BY sp.plan_id, sp.tool_id";
        $plan_list = Yii::$app->db->createCommand($sql)->queryAll();
        //批量更新问卷QC结果
        $sql = "UPDATE sys_survey_plan sp
                LEFT JOIN sys_survey s ON sp.survey_code = s.survey_code
                LEFT JOIN sys_question_qc_ignore_tmp t on t.plan_id = sp.plan_id and t.store_id = s.store_id
                LEFT JOIN sys_question_answer_qc qc on qc.survey_code = s.survey_code and qc.plan_id = sp.plan_id
                SET ". $update_filed_sql . "
                WHERE t.file_id = '$file_id' and s.survey_status = " . Survey::SURVEY_END .
                    " and t.id is not null and sp.need_question_qc = " . SurveyPlan::NEED_QC_YES .
                    " and sp.question_qc_status = " . SurveyPlan::QUESTION_QC_STATUS_DEFAULT .
                    " and t.check_status = " . QuestionQcIgnoreTmp::CHECK_STATUS_PASS;
        $rows = \Yii::$app->db->createCommand($sql)->execute();
        //批量更新计划任务数
        if ($rows) {
            foreach ($plan_list as $plan) {
                self::qcFinishToRedis($plan['plan_id'], $plan['tool_id'], $plan['total']);
            }
        }
    }

    /**
     * 获取任务列表搜索条件下剩余任务数及相邻任务ID
     *
     * @param int $plan_id 检查计划ID
     * @param int $qc_task_id 当前问卷QC任务ID
     * @param string $qc_task_survey_time 问卷QC任务对应走访时间
     * @param array $condition 问卷QC任务查询条件
     * @return array
     */
    public static function getNearIDAndCount($plan_id, $qc_task_id, $qc_task_survey_time, $condition)
    {
        $query = SurveyPlan::find()->alias('sp')
            ->leftJoin(Survey::tableName() . ' s', 'sp.survey_code = s.survey_code')
            ->andWhere(['sp.plan_id' => $plan_id]);
        //走访开始时间
        if (!empty($condition['check_start_time'])) {
            $query->andWhere(['>=', 's.survey_time', $condition['check_start_time']]);
        }
        //走访结束时间
        if (!empty($condition['check_end_time'])) {
            $query->andWhere(['<=', 's.survey_time', $condition['check_end_time']]);
        }
        //走访号
        if (!empty($condition['survey_code'])) {
            $query->andWhere(['s.survey_code' => $condition['survey_code']]);
        }
        //次渠道类型
        if (!empty($condition['channel_id_main'])) {
            $query->andWhere(['s.sub_channel_id' => $condition['channel_id_main']]);
        }
        //售点编号
        if (!empty($condition['store_id'])) {
            $query->andWhere(['s.store_id' => $condition['store_id']]);
        }
        //大区
        if (!empty($condition['region_code'])) {
            $query->andWhere(['s.region_code' => $condition['region_code']]);
        }
        //营业所
        if (!empty($condition['location_code'])) {
            $query->andWhere(['s.location_code' => $condition['location_code']]);
        }
        //主任
        if (!empty($condition['supervisor_name'])) {
            $query->andWhere(['s.supervisor_name' => $condition['supervisor_name']]);
        }
        //线路
        if (!empty($condition['route_code'])) {
            $query->andWhere(['s.route_code' => $condition['route_code']]);
        }
        //复核状态
        if (isset($condition['question_qc_status']) && $condition['question_qc_status'] != '') {
            $query->andWhere(['sp.question_qc_status' => $condition['question_qc_status']]);
        }
        //复核模式
        if ($condition['mode'] && $condition['mode'] == self::DETAIL_QC_MODE) {
            $query->andWhere(['sp.question_qc_status' => SurveyPlan::QUESTION_QC_STATUS_DEFAULT]);
        }
        $query->orderBy('s.survey_time desc, sp.id desc');
        $query_obj = serialize($query);
        //获取任务总数
        $count = unserialize($query_obj)->count();
        //获取上一条任务
        $prev_qc_task = unserialize($query_obj)->andWhere(['<=', 's.survey_time', $qc_task_survey_time])
            ->having(['<', 'sp.id', $qc_task_id])->select(['sp.id'])->asArray()->one();
        //获取下一条任务
        $next_qc_task = unserialize($query_obj)->andWhere(['>=', 's.survey_time', $qc_task_survey_time])
            ->having(['>', 'sp.id', $qc_task_id])->select(['sp.id'])->asArray()->one();
        return [
            'remain_task_num' => max(0, $count - 1),
            'prev_task_id' => ArrayHelper::getValue($prev_qc_task, 'id', 0),
            'next_task_id' => ArrayHelper::getValue($next_qc_task, 'id', 0),
        ];
    }

    /**
     * 获取完整的问卷qc计划任务列表详情
     * @param $searchForm
     * @return array|string|\yii\db\ActiveRecord|\yii\db\ActiveRecord[]|null
     */
    public static function getQuestionQcPlanList($searchForm)
    {
        $res = SurveyPlan::getQuestionQcPlanList($searchForm);

        if (!empty($res['list'])) {
            foreach ($res['list'] as $k => &$v) {
//                $v['standard_title'] = $standard_title_arr[$v['standard_id']]['title'] ?? '';
                $v['question_model_id'] = $v['question_model'];
                $v['question_model'] = self::QUESTION_MODEL[$v['question_model']];
                $v['check_time'] = substr($v['start_time'], 0, -9) . '~' . substr($v['end_time'], 0, -9);
                $v['create_time'] = \Helper::timestampToDate($v['create_time']);
                $prefix = '_QC_PLAN_';
                $re = self::planQcToRedis($prefix, $v['plan_id'], $v['tool_id']);
//                print_r($re);die;
                if ($re) {
                    $v['plan_total'] = $re['plan_total'];
                    $v['plan_finish_total'] = $re['plan_finish_total'];
                    $v['remain_total'] = $re['remain_total'];
                } else {
                    $v['plan_total'] = 0;
                    $v['plan_finish_total'] = 0;
                    $v['remain_total'] = 0;
                }
                unset($v['start_time'], $v['end_time']);
            }
        }
        return $res;
    }

    /**
     * 计划维度问卷qc详情入缓存
     * @param $prefix
     * @param $plan_id
     * @param $tool_id
     * @param bool $init
     * @return array|bool|mixed
     */
    public static function planQcToRedis($prefix, $plan_id, $tool_id, $init = false)
    {
        $queueName = self::makeQcQueueName($prefix, $plan_id . '_' . $tool_id);
        $re = Yii::$app->remq->hgetall($queueName);
        if ($re) {
            if ($init) {
                self::hincrby($queueName, 'plan_total', $init);
                self::hincrby($queueName, 'remain_total', $init);
                Yii::$app->remq->expire($queueName, 24 * 60 * 60);
                return true;
            }
            $count = count($re);
            $data = [];
            for ($i = 0; $i < $count; $i = $i + 2) {
                $data[$re[$i]] = $re[$i + 1];
            }
            return $data;
        } else {
            if ($init) {
                $hmset = Yii::$app->remq->hmset($queueName, 'plan_total', $init, 'plan_finish_total', 0, 'remain_total', $init);
                Yii::$app->remq->expire($queueName, 24 * 60 * 60);
                if (!$hmset) {
                    Yii::error('检查计划id：' . $plan_id . '的qc详情入缓存失败');
                }
                return true;
            }
            $plan_total_info = SurveyPlan::getPlanTotalInfo($plan_id, $tool_id);
            $hmset = Yii::$app->remq->hmset($queueName, 'plan_total', $plan_total_info['plan_total'], 'plan_finish_total', $plan_total_info['plan_finish_total'], 'remain_total', $plan_total_info['remain_total']);
            Yii::$app->remq->expire($queueName, 24 * 60 * 60);
            if (!$hmset) {
                Yii::error('检查计划id：' . $plan_id . '的qc详情入缓存失败');
            }
            return $plan_total_info;
        }
    }

    /**
     * 获取完整的单个计划的所有走访的列表的数据
     * @param $searchForm
     * @return array|string|\yii\db\ActiveRecord|\yii\db\ActiveRecord[]|null
     */
    public static function getQuestionQcSurveyList($searchForm)
    {
        $res = SurveyPlan::getQuestionQcSurveyList($searchForm);

        $store_ids = array_column($res['list'], 'store_id');
        $region_codes = array_column($res['list'], 'region_code');
        $store_names = Store::findAllArray(['store_id' => $store_ids], ['name', 'store_id'], 'store_id');
        $region_names = OrganizationRelation::findAllArray(['region_code' => $region_codes], ['region_name', 'region_code'], 'region_code');
        if (!empty($res['list'])) {
            foreach ($res['list'] as $k => &$v) {
                $v['question_qc_status'] = SurveyPlan::QUESTION_QC_STATUS_CN[$v['question_qc_status']];
                $v['store_name'] = $store_names[$v['store_id']]['name'];
                $v['region_name'] = $v['region_code'] ? $region_names[$v['region_code']]['region_name'] : '';
            }
        }
        return $res;
    }

    /**
     * 问卷qc完成后更新检查计划任务数
     * @param $plan_id
     * @param $tool_id
     * @param int $num
     */
    public static function qcFinishToRedis($plan_id, $tool_id, $num = 1)
    {
        $prefix = '_QC_PLAN_';
        $queueName = self::makeQcQueueName($prefix, $plan_id . '_' . $tool_id);
        if(!Yii::$app->remq->hget($prefix, 'plan_total')){
            self::hincrby($queueName, 'plan_total', $num);
        }
        self::hincrby($queueName, 'plan_finish_total', $num);
        self::hincrby($queueName, 'remain_total', -$num);
    }


    /**
     * 拼凑队列名
     * @param $prefix
     * @param $data
     * @return string
     */
    public static function makeQcQueueName($prefix, $data)
    {
        return Yii::$app->params['project_id'] . $prefix . $data;
    }

    /**
     * redis计数自增
     * @param $key
     * @param $field
     * @param int $increment
     * @return mixed
     */
    public static function hincrby($key, $field, $increment = 1)
    {
        return Yii::$app->remq->hincrby($key, $field, $increment);
    }
}