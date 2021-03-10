<?php
/**
 * Created by PhpStorm.
 * User: wudaji
 * Date: 2020/1/14
 * Time: 17:39
 */

namespace api\controllers;

use api\models\EngineResult;
use api\models\Image;
use api\models\ImageReport;
use api\models\ImageUrl;
use api\models\PageView;
use api\models\Plan;
use api\models\Question;
use api\models\QuestionAnswer;
use api\models\QuestionOption;
use api\models\Replan;
use api\models\share\ChannelMain;
use api\models\share\ChannelSub;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\StoreBelong;
use api\models\Standard;
use api\models\Store;
use api\models\SubActivity;
use api\models\Survey;
use api\models\Tools;
use api\models\User;
use api\service\report\ReportService;
use common\components\COS;
use common\libs\report\SceneReport;
use common\libs\sku\IRSku;
use Yii;
use yii\helpers\ArrayHelper;

class ReportController extends BaseApi
{
    const ACCESS_ANY = [
        'scene-list',
        'channel-name-list',
        'check-type-list',
        'channel-main-list',
        'route-drop-list',
    ];

    const IMAGE_REPORT_HAS_SURVEY = 1;
    const IMAGE_REPORT_NOT_HAS_SURVEY = 2;

    //统计点击率的环境名先写死太古
    const PAGE_VIEW_ENV = 'swire';

    /**
     * 检查结果列表
     * @return array
     */
    public function actionList()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['page', 'page_size'])) {
            return $this->error();
        }
        $where[] = 'and';
        if (!empty($params['survey_time_start'])) {
            $where[] = ['>=', 'survey_time', $params['survey_time_start'] . ' 00:00:00'];
        }
        if (!empty($params['survey_time_end'])) {
            $where[] = ['<=', 'survey_time', $params['survey_time_end'] . ' 23:59:59'];
        }
        if (!empty($params['tool_id'])) {
            $where[] = ['=', 't.id', $params['tool_id']];
        }
        if (!empty($params['survey_code'])) {
            $where[] = ['=', 'survey_code', $params['survey_code']];
        }
        if (!empty($params['sub_channel_id'])) {
            $where[] = ['=', 'sub_channel_id', $params['sub_channel_id']];
        }
        if (!empty($params['store_id'])) {
            $where[] = ['=', 'store_id', $params['store_id']];
        }
        if (!empty($params['location_code']) && is_array($params['location_code'])) {
            $where[] = ['in', 'location_code', $params['location_code']];
        }
        if (!empty($params['route_code']) && is_array($params['route_code'])) {
            $where[] = ['in', 'route_code', $params['route_code']];
        }
        if (!empty($params['supervisor_name'])) {
            $where[] = ['like', 'supervisor_name', $params['supervisor_name']];
        }
        //0在php中也是判断为空
        if ($params['is_inventory'] !== '') {
            $where[] = ['=', 'is_inventory', $params['is_inventory']];
        }
        if (!empty($params['company_bu'])) {
            foreach ($params['company_bu'] as $v) {
                $company_bu = explode('_', $v);
                $company_code[] = $company_bu[0];
                if (isset($company_bu[1])) {
                    $bu_code[] = $company_bu[1];
                }
            }
            $where[] = ['in', 'company_code', $company_code];
            if (!empty($bu_code)) {
                $where[] = ['in', 'bu_code', $bu_code];
            }
        }
        //如果前端未输入查询参数就不返回数据
        if (count($where) <= 1) {
            $data = ['list' => [], 'count' => 0];
            return $this->success($data);
        }
        $where[] = ['=', 'survey_status', Survey::SURVEY_END];

        $data = Survey::getReportList($where, $params['page_size'], $params['page']);

        $bu_list = OrganizationRelation::companyBu();
        foreach ($data['list'] as &$v) {
            $bu_name = '';
            if (!empty($v['company_code'])) {
                $bu_name = isset($bu_list[$v['company_code'] . '_' . $v['bu_code']]) ? $bu_list[$v['company_code'] . '_' . $v['bu_code']] : '';
            }
            $v['bu_name'] = $bu_name ? $bu_name : User::COMPANY_CODE_ALL_LABEL;
        }
        if (isset($data)) {
            return $this->success($data);
        } else {
            return $this->error("查询异常，请检查");
        }
    }

    /**
     * 单个售点单次走访识别详情
     * @return array
     */
    public function actionStoreReport()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['survey_code'])) {
            $where[] = 'and';
            $where[] = ['=', 's.survey_code', $params['survey_code']];
            $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
            $where[] = ['=', 'i.status', Image::DEL_STATUS_NORMAL];
            $list = Survey::getSurveyInfo($where);
            if ($list) {
                $data = [];
                $arr = [];
                $store_ids = array_column($list, 'store_id');
                $storeAll = \api\models\share\Store::findAllArray(['store_id' => $store_ids], ['store_id', 'name'], 'store_id');

                foreach ($list as $v) {
                    if (empty($data)) {
                        $data['store_id'] = $v['store_id'];
                        $data['store_name'] = $v['store_name'] != '' ? $v['store_name'] : ($storeAll[$v['store_id']]['name'] ?? '');
                        $data['survey_time'] = $v['survey_time'];
                    }
                    $info['scene_type'] = $v['scene_id_name'];
                    $info['photo_id'] = $v['photo_id'];
                    if ($v['scene_id_name'] == '售点概况') {
                        array_unshift($arr, $info);
                    } else {
                        $arr[] = $info;
                    }
                }
                $data['info'] = $arr;
                return $this->success($data);
            } else {
                $where = [];
                $where[] = 'and';
                $where[] = ['=', 's.survey_code', $params['survey_code']];
                $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
                $list = Survey::getSurveyInfo($where);
                if ($list) {
                    $data['store_id'] = $list[0]['store_id'];
                    $data['store_name'] = $list[0]['name'];
                    $data['survey_time'] = $list[0]['survey_time'];
                    $data['info'] = [];
                } else {
                    $data['store_id'] = '';
                    $data['store_name'] = '';
                    $data['survey_time'] = '';
                    $data['info'] = [];
                }
            }
            return $this->success($data);
        } else {
            return $this->error("缺少入参，请检查");
        }
    }

    /**
     * 单个场景维度的走访识别详情
     * @return array
     */
    public function actionPhotoReport()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['photo_id'])) {
            $where = ['i.id' => $params['photo_id']];
            $result = Image::findImageReport($where);
            $info = [];
            $img_id = 0;
            foreach ($result as $v) {
                if (empty($info)) {
                    $info['remark'] = [
                        '设备名称：' . $v['asset_name'],
                        '资产编码：' . $v['asset_code'],
                        '设备型号：' . $v['asset_type']
                    ];
                    if (!empty($v['result'])) {
                        //直接套用施展的解析识别结果的方法
                        $info['result'] = ReportService::GetSkuInfo($v['result']);
                    }
                    $info['result'] = isset($info['result']) ? $info['result'] : [];
                    $info['url'] = !empty($v['url']) ? $v['url'] : '';
                    $img_id = $v['img_id'];
                }
                if (!empty($v['title'])) {
                    if ($v['question_type'] == Question::QUESTION_TYPE_BOOL) {
                        $v['answer'] = $v['answer'] ? '是' : '否';
                    } elseif ($v['question_type'] == Question::QUESTION_TYPE_SELECT) {
                        //选择题要将选项号转成值
                        $v['answer'] = QuestionOption::findOneArray(['id' => $v['answer']], ['name'])['name'];
                    }
                    //此处新增加问卷留底的照片的返回
                    $info['question_info'][] = [
                        'question' => $v['title'] . ':' . $v['answer'],
                        'question_url' => json_decode($v['question_image'], true) ?? [],
                    ];
                    $info['type'] = $v['type']; // 1代表售点
                }
            }
            //此处也不返回问卷图片
            $old_iamges = ImageUrl::findAllArray(['image_id' => $img_id, 'status' => ImageUrl::DEL_STATUS_NORMAL, 'img_type' => ImageUrl::NOT_QUESTIONNAIRE_IMAGE], ['image_url']);
            $info['old_images'] = array_column($old_iamges, 'image_url');
            return $this->success($info);
        } else {
            return $this->error("缺少入参，请检查");
        }
    }

    /**
     * 查询走访号列表
     * @return array
     */
    public function actionSurveyList()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['page_size']) && isset($params['page'])) {
            $where = ['=', 'status', Survey::DEL_STATUS_NORMAL];
            $result = Survey::getSurveyList($where, $params['page_size'], $params['page']);
            if (isset($result)) {
                $data['list'] = $result;
                $data['count'] = count($data['list']);
                return $this->success($data);
            } else {
                return $this->error("查询异常，请检查");
            }
        } else {
            return $this->error("缺少入参，请检查");
        }
    }

    /**
     * 查询次渠道名称列表
     * @return array
     */
    public function actionChannelNameList()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['page_size']) && isset($params['page'])) {
            $select = [
                'name sub_channel_name',
                'id sub_channel_id'
            ];
            $result = ChannelSub::getChannelNameList($params['page_size'], $params['page'], $select);
            if (isset($result)) {
                $data['list'] = $result;
                $data['count'] = count($data['list']);
                return $this->success($data);
            } else {
                return $this->error("查询异常，请检查");
            }
        } else {
            return $this->error("缺少入参，请检查");
        }
    }

    /**
     * 检查结果问卷列表
     * @return array
     */
    public function actionQuestionList()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm,
                // 'start_time','end_time','tool_id','survey_code','standard_id','rate_type','type','scene_id','question_title','store_id',
                ['page', 'page_size'])) {
            return $this->error();
        }

        $data = QuestionAnswer::getAnswerData($bodyForm);

        $sceneAll = Scene::getAll(['id', 'scene_code', 'scene_code_name'], 'scene_code');

        $bu = OrganizationRelation::companyBu();
        // 将数据查出来之后，把检查计划中的所有 question_id 查出来，再匹配
        foreach ($data['list'] as &$datum) {
            $key = $datum['company_code'] . '_' . $datum['bu_code'];
            $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            $datum['scene_type_label'] = isset($sceneAll[$datum['scene_code']]) ? $sceneAll[$datum['scene_code']]['scene_code_name'] : '';
            $datum['rate_type'] = isset(Plan::RATE_TYPE_ARR[$datum['rate_type']]) ? Plan::RATE_TYPE_ARR[$datum['rate_type']] : '';
            //如果没有查到plan，那么standard_title直接用image关联的检查项目名称
            if (!$datum['standard_title']) {
                $datum['standard_title'] = $datum['image']['standard']['title'];
            }
            unset($datum['question']);
            unset($datum['survey']);
            unset($datum['tool']);
            unset($datum['image']);
        }
        return $data;

    }

    /**
     * 规则引擎结果列表
     * @return array
     */
    public function actionEngineResultList()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['page', 'page_size', 'tool_id'])) {
            return $this->error();
        }
        return EngineResult::getEngineResultData($bodyForm);
    }

    /**
     * 主任下拉列表
     * @return array
     */
    public function actionSupervisorDropList()
    {
        $where = [];
        $data = OrganizationRelation::findAllArray($where,
            ['id' => 'id', 'name' => 'supervisor_name', 'value' => 'supervisor_name', 'code' => 'supervisor_name'],
            '', true, 'supervisor_name');
        return $this->success(['list' => $data]);
    }

    /**
     * 线路下拉列表
     * @return array
     */
    public function actionRouteDropList()
    {
        $where = [];
        $data = OrganizationRelation::findAllArray($where,
            ['id' => 'id', 'name' => 'route_code', 'value' => 'route_code', 'code' => 'route_code'],
            '', true, 'route_code');
        return $this->success(['list' => $data]);
    }

    public function actionChannelMainList()
    {
        $data = ChannelMain::getAll();
        return $this->success(['list' => $data]);
    }

    /**
     * 检查结果请求下载
     */
    public function actionReportSceneDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['tool_id'])) {
            return $this->error();
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        //生成文件名，执行工具名称+检查项目名称+日期，比如CP中秋活动节20200601
        $standard = Standard::getStandardDetail(['id' => $bodyForm['standard_id']]);
        $tool = Tools::find()->where(['id' => $bodyForm['tool_id']])->asArray()->one();
        $bodyForm['file_name'] = ArrayHelper::getValue($tool, 'name', '') . ArrayHelper::getValue($standard, 'title', '') . date('Ymd');

        $redis_queue_key = Yii::$app->params['redis_queue']['report_scene_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, EngineResult::class, 'getEngineResultData');
    }

    /**
     * 引擎结果下载进度查询
     * @return array|mixed
     */
    public function actionDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_scene_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->redis->get($cacheKey);
        $result = json_decode($result, true);
        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 检查结果问卷请求下载
     */
    public function actionReportQuestionDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost()) {
            return $this->error();
        }

        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $user_arr['swire_bu_code'] = $user->swire_bu_code;
        $bodyForm['user'] = $user_arr;

        //下载文件名
        $bodyForm['file_name'] = '问卷结果' . date('Ymd');

        $redis_queue_key = Yii::$app->params['redis_queue']['report_question_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, QuestionAnswer::class, 'getAnswerData');
    }

    /**
     * 引擎结果问卷下载进度查询
     * @return array|mixed
     */
    public function actionQuestionDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_question_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->redis->get($cacheKey);
        $result = json_decode($result, true);
        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 统计项目检查结果请求下载
     * @return array
     */
    public function actionStatisticalReportSceneDownload()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['statistical_id', 'tool_id'])) {
            return $this->error();
        }
        $user = User::getSwireUser($bodyForm['token']);
        $user_arr = $user->getAttributes();
        $bodyForm['user'] = $user_arr;

        $redis_queue_key = Yii::$app->params['redis_queue']['report_statistical_download_list'];
        return $this->downloadPushQueue($redis_queue_key, $bodyForm, EngineResult::class, 'getStatisticalEngineResultData');
    }

    /**
     * 统计项目引擎结果下载进度查询
     * @return array|mixed
     */
    public function actionStatisticalDownloadProgress()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['report_statistical_download_process_prefix'] . '_' . $search_task_id;
        $result = Yii::$app->redis->get($cacheKey);
        $result = json_decode($result, true);
        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }

    /**
     * 统计项目规则引擎计算结果
     * @return array
     */
    public function actionStatisticalEngineResultList()
    {
        $params = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($params, ['page', 'page_size', 'statistical_id'])) {
            return $this->error();
        }
        $result = EngineResult::getStatisticalEngineResultData($params);
        foreach ($result['list'] as &$v) {
            $v['check_scope_name'] = Replan::CHECK_LABEL_ARR[$v['check_scope']];
            unset($v['check_scope']);
        }
        return $result;
    }

    /**
     * 检查结果图片查看
     * @return array
     */
    public function actionReportImage()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->isPost() || !$this->validateParam($params, ['page', 'page_size', 'model', 'search'])) {
            return $this->error();
        }
        $params['start_time'] = $params['start_time'] ? $params['start_time'] . ' 00:00:00' : '';
        $params['end_time'] = $params['end_time'] ? $params['end_time'] . ' 23:59:59' : '';
        //此处图片查看分成两种模式返回
        if ($params['model'] == self::IMAGE_REPORT_HAS_SURVEY) {
            $where_data = [
                [['start_time' => 's.survey_time'], '>='],
                [['end_time' => 's.survey_time'], '<='],
                [
                    [
                        'route_code' => 's.route_code',
                        'location_code' => 's.location_code',
                    ], 'in'
                ],
                [
                    [
                        'tool_id' => 's.tool_id',
                        'survey_code' => 's.survey_code',
                        'sub_channel_id' => 's.sub_channel_id',
                        'standard_id' => 'e.standard_id',
                        'check_type_id' => 'sta.check_type_id',
                        'is_rectify' => 'e.is_rectify',
                        'store_id' => 's.store_id',
                        'is_inventory' => 's.is_inventory'
                    ], '='
                ],
                [['supervisor_name' => 's.supervisor_name'], 'like'],
            ];
            $where = $this->makeWhere($where_data, $params);
//        $where[] = ['i.status' => Image::DEL_STATUS_NORMAL];
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
            //如果前端未输入必填参数就不返回数据
            if (empty($params['tool_id']) && empty($params['standard_id'])) {
                $data = ['list' => [], 'count' => 0];
                return $this->success($data);
            }
            $data = Survey::getReportImage($where, $params['page'] - 1, $params['page_size']);
            $store_id_list = array_column($data['list'], 'store_id');
            $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_id_list], true, true, '', 'store_id');
            $store_list_arr = array_column($store_list, 'store_id');
            $store_name = \api\models\share\Store::findAllArray(['store_id' => $store_list_arr], ['name', 'store_id'], 'store_id');

            foreach ($data['list'] as &$v) {
                $v['is_rectify'] = EngineResult::IS_RECTIFY_EXPLAIN[$v['is_rectify']];
                $image = [];
                foreach ($v['image'] as $item1) {
                    //必须image的状态为正常才返回其数据
                    if ($item1['status'] == Image::DEL_STATUS_NORMAL) {
                        if (isset($item1['imageUrl'])) {
                            foreach ($item1['imageUrl'] as $img) {
                                $tmp['created_at'] = date('Y-m-d H:i:s', $item1['created_at']);
                                $tmp['activation_id'] = $item1['subActivity']['activation_id'];
                                $tmp['activation_name'] = $item1['subActivity']['activation_name'];
                                $tmp['scene_id_name'] = $item1['scene_id_name'];
                                $tmp['img_type'] = $img['img_type'];
                                $tmp['question'] = $img['question'] ?? '';
                                $tmp['standard_name'] = $v['standard_name'] ?? '';
                                //$tmp['image_url'] = Yii::$app->params['cos_url'].$img['image_key'];
                                $tmp['image_url'] = $img['image_url'];
                                $tmp['rebroadcast_status'] = $img['rebroadcast_status'];
                                $tmp['is_rebroadcast'] = $img['is_rebroadcast'];
                                $tmp['similarity_status'] = $img['similarity_status'];
                                $tmp['is_similarity'] = $img['is_similarity'];
                                $image[] = $tmp;
                            }
                        } else {
                            for ($i = 0; $i < $item1['number']; $i++) {
                                $tmp['created_at'] = date('Y-m-d H:i:s', $item1['created_at']);
                                $tmp['activation_id'] = $item1['subActivity']['activation_id'];
                                $tmp['activation_name'] = $item1['subActivity']['activation_name'];
                                $tmp['scene_id_name'] = $item1['scene_id_name'];
                                $tmp['image_url'] = Yii::$app->params['cos_url'] . $item1['img_prex_key'] . '_' . $i . '.jpg';
                                $tmp['rebroadcast_status'] = '';
                                $tmp['is_rebroadcast'] = '';
                                $tmp['similarity_status'] = '';
                                $tmp['is_similarity'] = '';
                                $image[] = $tmp;
                            }
                        }
                    }
                }

                $v['image'] = $image;
                $v['store_name'] = $v['store_name'] != '' ? $v['store_name'] : $store_name[$v['store_id']]['name'] ?? '';
            }
        } else {
            $where_data = [
                [['start_time' => 's.survey_time'], '>='],
                [['end_time' => 's.survey_time'], '<='],
                [['route_code' => 's.route_code'], 'in'],
                [
                    [
                        'tool_id' => 's.tool_id',
                        'sub_channel_id' => 's.sub_channel_id',
                        'standard_id' => 'e.standard_id',
                        'check_type_id' => 'sta.check_type_id',
                        'is_rectify' => 'e.is_rectify',
                        'is_rebroadcast' => 'iu.is_rebroadcast',
                        'is_similarity' => 'iu.is_similarity',
                        'store_id' => 's.store_id',
                        'location_code' => 's.location_name',
                        'is_inventory' => 's.is_inventory'
                    ], '='
                ],
                [['supervisor_name' => 's.supervisor_name'], 'like'],
            ];
            $where = $this->makeWhere($where_data, $params);
//        $where[] = ['i.status' => Image::DEL_STATUS_NORMAL];
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
            //如果前端未输入必填参数就不返回数据
            if (empty($params['tool_id']) && empty($params['standard_id'])) {
                $data = ['list' => [], 'count' => 0];
                return $this->success($data);
            }
            $where[] = ['iu.status' => ImageUrl::DEL_STATUS_NORMAL];
            $data = Survey::getReportImageNotSurvey($where, $params['page'] - 1, $params['page_size']);
            $store_id_list = array_column($data['list'], 'store_id');
            $store_list = Store::findJoin('', [], ['store_id', 'name store_name'], ['in', 'store_id', $store_id_list], true, true, '', 'store_id');
            $store_list_arr = array_column($store_list, 'store_id');
            $store_name = \api\models\share\Store::findAllArray(['store_id' => $store_list_arr], ['name', 'store_id'], 'store_id');

            $image_id = 0;
            $number = 0;
            foreach ($data['list'] as &$v) {
                if ($image_id != $v['image_id']) {
                    $image_id = $v['image_id'];
                    $number = 0;
                }
                $v['is_rectify'] = EngineResult::IS_RECTIFY_EXPLAIN[$v['is_rectify']];
                $v['created_at'] = date('Y-m-d H:i:s', $v['created_at']);
                //$v['image_url'] = Yii::$app->params['cos_url'].$v['image_key'];
                $number++;
                $v['store_name'] = $v['store_name'] != '' ? $v['store_name'] : $store_name[$v['store_id']]['name'] ?? '';
                $v['question'] = $v['question'] ?? '';
            }
        }
        //存储下接口访问次数
        PageView::saveClick(self::PAGE_VIEW_ENV, $params['search']);

        if (isset($data)) {
            return $this->success($data);
        } else {
            return $this->error("查询异常，请检查");
        }
    }
}