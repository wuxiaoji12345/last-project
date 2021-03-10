<?php


namespace api\controllers;


use api\models\Download;
use api\models\EngineResult;
use api\models\Image;
use api\models\ImageSimilarity;
use api\models\PageView;
use api\models\Store;
use api\models\Survey;
use api\models\SurveyPlan;
use api\models\User;
use api\service\qc\QuestionQcService;
use Yii;

class DownloadController extends BaseApi
{
    const ACCESS_ANY = [
        'survey-list-download-progress',
        'survey-list-download'
    ];
    /*
     * 获取相似图导出列表
     * */
    public function actionSimilarList()
    {
        $params = Yii::$app->request->bodyParams;
        $user = User::getSwireUser($params['token']);
        $user_arr = $user->getAttributes();
        $where = ['uid'=>$user_arr['id']];
        $data = Download::findAllArray($where,['id','task_id','file_name','file_size','download_url','download_num','download_status','created_at'],'',false,'id desc');
        if (isset($data)) {
            return $this->success($data);
        } else {
            return $this->error("没有数据了");
        }
    }

    /*
     * 删除相似图导出任务
     * */
    public function actionDel()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['id'])) {
            return $this->error();
        }
        $model = Download::findOne(['id'=>$params['id']]);
        if($model){
            $model->status = 0;
            if($model->save()){
                return $this->success();
            }
        }
        return $this->error("删除异常，请刷新");
    }

    /**
     * 图片列表请求下载
     */
    public function actionReportImageDownload()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['token', 'tool_id', 'standard_id'])) {
            return $this->error();
        }
        $params['page'] = 1;
        $params['page_size'] = 1;
        $params['start_time'] = isset($params['start_time']) && !empty($params['start_time']) ? $params['start_time'] . ' 00:00:00' : '';
        $params['end_time'] = isset($params['end_time']) && !empty($params['end_time']) ? $params['end_time'] . ' 23:59:59' : '';
        $where_data = [
            [['start_time' => 's.survey_time'], '>='],
            [['end_time' => 's.survey_time'], '<='],
            [['route_code' => 's.route_code', 'region_code' => 'st.region_code', 'standard_id' => 'e.standard_id'], 'in'],
            [
                [
                    'tool_id' => 's.tool_id',
                    'survey_code' => 's.survey_code',
                    'sub_channel_id' => 's.sub_channel_id',
                    'standard_id' => 'e.standard_id',
                    'check_type_id' => 'sta.check_type_id',
                    'is_rectify' => 'e.is_rectify',
                    'is_rebroadcast' => 'iu.is_rebroadcast',
                    'is_similarity' => 'iu.is_similarity',
                    'store_id' => 's.store_id',
                    'image_key' => 'iu.image_key'
                ], '='
            ],
            [['location_code' => 's.location_name', 'supervisor_name' => 's.supervisor_name'], 'like'],
        ];
        $where = $this->makeWhere($where_data, $params);
        if (!empty($params['company_bu'])) {
            foreach ($params['company_bu'] as $v) {
                $company_bu = explode('_', $v);
                $company_code[] = $company_bu[0];
                if (isset($company_bu[1])) {
                    $bu_code[] = $company_bu[1];
                }
            }
            $where[] = ['in', 's.company_code', $company_code];
            if (!empty($bu_code)) {
                $where[] = ['in', 's.bu_code', $bu_code];
            }
        }
        $data = Survey::getReportImageNotSurveyDown($where, $params['page'] - 1, $params['page_size']);

        $user = User::getSwireUser($params['token']);
        $user_arr = $user->getAttributes();
        $params['user'] = $user_arr;
        $params['where'] = $where;
        $params['count'] = $data['count'];

        $params['callback'] = ["console\service\downloads\DownloadReportImage", "reportImageDownload"];
        return $this->downloadPushQueueSimple($params);
    }

    /**
     * 图片列表下载进度查询
     * @return array|mixed
     */
    public function actionReportImageDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_image_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->redis->get($cacheKey);
        $result = json_decode($result, true);
        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 相似图请求下载
     */
    public function actionSimilarImageDownload()
    {
        $params = Yii::$app->request->bodyParams;
        $params['start_time'] = $params['start_time'] ? $params['start_time'] . ' 00:00:00' : '';
        $params['end_time'] = $params['end_time'] ? $params['end_time'] . ' 23:59:59' : '';
        $where_data = [
            [['start_time' => 's.survey_time'], '>='],
            [['end_time' => 's.survey_time'], '<='],
            [['route_code' => 's.route_code'], 'in'],
            [
                [
                    'tool_id' => 's.tool_id',
                    'survey_code' => 's.survey_code',
                    'sub_channel_id' => 's.sub_channel_id',
                    'standard_id' => 'img.standard_id',
                    'similarity_cause' => 'sim.similarity_cause',
                    'store_id' => 's.store_id'
                ], '='
            ],
            [
                [
                    'store_id' => 's.store_id',
                    'location_code' => 's.location_name',
                    'supervisor_name' => 's.supervisor_name',
                    'region_code' => 's.region_code',
                ], 'like'
            ],
        ];
        $where = $this->makeWhere($where_data, $params);
        $where[] = ['>', 'sim.similarity_cause', 2];
        $data = ImageSimilarity::getList($where, 1, 1);

        $user = User::getSwireUser($params['token']);
        $user_arr = $user->getAttributes();
        $params['user'] = $user_arr;
        $params['count'] = $data['count'];
        $params['where'] = $where;
        $params['callback'] = ["console\service\downloads\DownloadReportImage", "similarImageDownload"];
        $download = new Download();
        $download->download_status = 0;//文档待生成
        $download->uid = $user_arr['id'];
        $download->save();
        $params['download_id'] = $download->id;
        $data = $this->downloadPushQueueSimple($params);
        return $data;
    }

    /**
     * 下载走访记录列表
     */
    public function actionInterview()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost()) {
            return $this->error('非post请求！');
        }
        $params['start_time'] = isset($params['survey_time_start']) && !empty($params['survey_time_start']) ? $params['survey_time_start'] . ' 00:00:00' : '';
        $params['end_time'] = isset($params['survey_time_end']) && !empty($params['survey_time_end']) ? $params['survey_time_end'] . ' 23:59:59' : '';
        $where_data = [
            [['survey_time_start' => 's.survey_time'], '>='],
            [['survey_time_end' => 's.survey_time'], '<='],
            [['tool_id' => 's.tool_id', 'survey_code' => 's.survey_code', 'is_inventory' => 's.is_inventory', 'channel_id' => 'c.channel_id'], '='],
            [['store_id' => 's.store_id'], 'like'],
        ];
        $where = $this->makeWhere($where_data, $params);
        $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
        $where[] = ['>', 'i.ine_channel_id', 0];//过滤掉没有ine渠道的数据
        Survey::setBu($where);
        Survey::setUserInfo($where);
        $data = Survey::getInterview($where, 1, 1);

        $user = User::getSwireUser($params['token']);
        $user_arr = $user->getAttributes();
        $params['user'] = $user_arr;
        $params['where'] = $where;
        $params['count'] = $data['count'];

        $params['callback'] = ["console\service\downloads\DownloadReportImage", "interviewDownload"];
        return $this->downloadPushQueueSimple($params);
    }


    /**
     * 问卷qc计划走访任务列表下载
     * @return array
     */
    public function actionSurveyListDownload()
    {
        $search = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($search, ['plan_id'])) {
            return $this->error();
        }
        $search['page'] = 1;
        $search['page_size'] = 1;
        $search['need_question_qc'] = SurveyPlan::NEED_QC_YES;

        $re = QuestionQcService::getQuestionQcSurveyList($search);

        $user = User::getSwireUser($search['token']);
        $user_arr = $user->getAttributes();
        $params['user'] = $user_arr;
        $params['searchForm'] = $search;
        $params['count'] = $re['count'];

        $params['callback'] = ["console\service\downloads\DownloadQuestionQcData", "surveyListDownload"];
        return $this->downloadPushQueueSimple($params);
    }

    /**
     * 查询问卷qc计划走访任务列表下载进度
     * @return array|mixed
     */
    public function actionSurveyListDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_image_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->redis->get($cacheKey);
        $result = json_decode($result, true);
        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }
}