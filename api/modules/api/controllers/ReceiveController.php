<?php
/**
 * Created by PhpStorm.
 * User: wudaji
 * Date: 2020/1/14
 * Time: 17:39
 */

namespace api\modules\api\controllers;

use api\models\apiModels\mediaResultModel;
use api\models\Image;
use api\models\ImageReport;
use api\models\ImageSimilarity;
use api\models\ImageUrl;
use api\models\Plan;
use api\models\share\Scene;
use api\models\share\SceneType;
use api\models\share\Store;
use api\models\Standard;
use api\models\RuleOutputInfo;
use api\models\StatisticalItem;
use api\models\SubActivity;
use api\models\Survey;
use api\models\SurveyScene;
use api\models\User;
use api\service\rule\RuleService;
use Yii;

class ReceiveController extends BaseApi
{
    const TOOL = [
        'AP001' => 3,
        'AP002' => 4,
        'AP003' => 5,
        'AP004' => 6,
        'AP005' => 7,
        'AP006' => 9
    ];
    const TOOL_WRONG = 'tool有误';
    const SIMILARITY_CAUSE = [
        "0-0-0" => 1, //不同线路，不同售点，不同时间
        "0-0-1" => 2, //不同线路，不同售点，相同时间
        "1-0-0" => 3, //相同线路，不同售点，不同时间
        "1-0-1" => 4, //相同线路，不同售点，相同时间
        "1-1-0" => 5, //相同线路，相同售点，不同时间
        "1-1-1" => 6  //相同线路，相同售点，相同时间
    ];

    /**
     * 接收图像识别结果
     * @return array
     */
    public function actionRecognitionResult()
    {
        $url = "http://snapshot-1255412942.picsh.myqcloud.com/";
        $params = Yii::$app->request->rawBody;
        $params = json_decode($params, true);
        if ($this->check($params, ['unid', 'status', 'result'])) {
            if ($params['status'] === 0) {
                $rebroadcastResults = $params['result']['rebroadcast_results'] ?? false;
                $imageUrlId = $params['result']['context']['image_url_id'] ?? false;
                $imageFiles = $params['result']['image_files'] ?? [];
                $where = ['photo_id' => $params['unid']];
                $params['scene_type'] = $params['result']['type'];
                $params['url'] = $url . $params['result']['image_file'];
                $params['result'] = json_encode($params['result']);
                $result = ImageReport::saveRecognitionResult($where, $params);
                //======处理翻拍图信息
                if ($rebroadcastResults) {
                    foreach ($rebroadcastResults as $item) {
                        foreach ($item as $key => $v) {
                            if ($v == 1) {
                                $where = [
                                    'image_id' => $params['unid'],
                                    'image_key' => $key
                                ];
                                if($imageUrlId && $imageFiles){
                                    $k = array_search($key, $imageFiles);
                                    if(isset($imageUrlId[$k])){
                                        $where = [
                                            'id' => (int)$imageUrlId[$k]
                                        ];
                                    }
                                }
                                ImageUrl::saveRebroadcast($where,
                                    [
                                        'rebroadcast_status' => 1,
                                        'is_rebroadcast' => 1
                                    ]);
                            }
                        }
                    }
                }
                //======
                if ($result[0]) {
                    return $this->success($result[1]);
                } else {
                    return $this->error($result[1]);
                }
            } else {
                yii::error('识别失败，或因图片异常');
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 接收相似图结果
     * @return array
     */
    public function actionSimilarityImage()
    {
        $params = Yii::$app->request->rawBody;
        $params = json_decode($params, true);
        if ($this->check($params, ['unid', 'survey_code', 'url', 'similarity_images'])) {
            if(isset($params['image_url_id'])){
                $where = [
                    'id' => (int)$params['image_url_id']
                ];
                $imageKey = ImageUrl::getKeyById($params['image_url_id']);
            }else{
                $where = [
                    'image_id' => $params['unid'],
                    'image_key' => $params['url']
                ];
            }
            $result = ImageUrl::saveSimilarity($where, [
                'similarity_status' => 1,
                'is_similarity' => 0,
                'similarity_result' => $params['similarity_images']
            ]);
            if ($result[0]) {
                $is_similar_status = false;
                $surveyModel = Survey::getIdByCode($params['survey_code']);
                foreach ($params['similarity_images'] as $item) {
                    $similaritySurveyModel = Survey::getIdByCode($item['survey_code']);
                    $isRoute = $surveyModel[1]['route_code'] == $similaritySurveyModel[1]['route_code'] ? 1 : 0;
                    $isStore = $surveyModel[1]['store_id'] == $similaritySurveyModel[1]['store_id'] ? 1 : 0;
                    if (empty($surveyModel[1]['survey_time']) || empty($similaritySurveyModel[1]['survey_time'])) {
                        $isDay = date("Y-m-d", $surveyModel[1]['created_at']) == date("Y-m-d", $similaritySurveyModel[1]['created_at']) ? 1 : 0;
                    } else {
                        $isDay = substr($surveyModel[1]['survey_time'], 0, 10) == substr($similaritySurveyModel[1]['survey_time'], 0, 10) ? 1 : 0;
                    }
                    $similarityCause = self::SIMILARITY_CAUSE[$isRoute . '-' . $isStore . '-' . $isDay];
                    if($similarityCause != 6 && $similarityCause != 1 && $similarityCause != 2){ //暂时不显示不同线路
                        $is_similar_status = true;
                        $imageKey = $imageKey ?? $params['url'];
                        $similarityImageKey = isset($item['image_url_id']) ? ImageUrl::getKeyById($item['image_url_id']) : $item['url'];
                        $data = [
                            'image_id' => $params['unid'],
                            'image_key' => $imageKey,
                            'survey_code' => $params['survey_code'],
                            'similarity_image_id' => $item['unid'],
                            'similarity_image_key' => $similarityImageKey,
                            'similarity_survey_code' => $item['survey_code'],
                            'similarity_number' => $item['confidence'],
                            'similarity_cause' => $similarityCause,
                            'similarity_status' => 1,
                        ];
                        ImageSimilarity::saveSimilarity($data);
                    }
                }
                if($is_similar_status){
                    if(isset($params['image_url_id'])){
                        $where = [
                            'id' => (int)$params['image_url_id']
                        ];
                    }else{
                        $where = [
                            'image_id' => $params['unid'],
                            'image_key' => $params['url']
                        ];
                    }
                    ImageUrl::saveSimilarity($where, [
                        'similarity_status' => 1,
                        'is_similarity' => 1,
                        'similarity_result' => $params['similarity_images']
                    ]);
                }
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 接收计算引擎输出项
     * @return array
     */
    public function actionEngineOutput()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['rule_output_field'])) {
            if (!isset($params['standard_id']) && !isset($params['statistical_id'])) {
                return $this->error('缺少检查id，请检查');
            }
            try {
                //检查项目已启用情况下无法进行其他动作
                if (isset($params['standard_id'])) {
                    $standard_status = Standard::isStandardStart($params['standard_id']);
                    if (!$standard_status[0]) {
                        return $this->error($standard_status[1]);
                    }
                }
                if (isset($params['is_finished']) && !$params['is_finished']) {
                    if (!isset($params['statistical_id'])) {
                        Standard::updateAll(['is_change' => Standard::NOT_CHANGE, 'setup_step' => Standard::SETUP_STEP_SET_RULE, 'set_rule' => Standard::SET_RULE_YES], ['id' => $params['standard_id']]);
                    } else {
                        StatisticalItem::updateAll(['setup_step' => StatisticalItem::SETUP_STEP_RULE], ['id' => $params['statistical_id']]);
                    }
                    return $this->success($params);
                }
                $transaction = Yii::$app->getDb()->beginTransaction();
                $where = isset($params['statistical_id']) ? ['statistical_id' => $params['statistical_id']] : ['standard_id' => $params['standard_id']];
                $rule_output_field = json_decode($params['rule_output_field'], true);

                RuleOutputInfo::updateAll(['tag' => RuleOutputInfo::OUTPUT_STATUS_DEFAULT], $where);

                $where['status'] = RuleOutputInfo::DEL_STATUS_NORMAL;
                $select = ['node_index', 'node_name', 'scene_type', 'output_type', 'scene_code', 'is_all_scene', 'formats', 'sub_activity_id'];
                $output_result = RuleOutputInfo::findAllArray($where, $select);
                if (!is_array($rule_output_field)) {
                    $transaction->rollBack();
                    return $this->error('参数有误');
                }
                //输出项是否有变化（只考虑有数据然后变化的情况）
                $change = false;
                $id = isset($params['standard_id']) ? ['standard_id', $params['standard_id']] : ['statistical_id', $params['statistical_id']];
                if (empty($output_result)) {
                    //现在子活动与输出项的关系由规则引擎传过来
                    //数据库该标准下无数据，说明可以直接更新
//                    foreach ($rule_output_field as $k => $v) {
//                        if (isset($params['standard_id'])) {
//                            //新增的条目与子活动表对比，获得子活动id
//                            foreach ($v['scene_code'] as $item) {
//                                $v['scene_type'][] = Scene::findOneArray(['scene_code' => $item], ['scene_type'])['scene_type'];
//                            }
//                            $v['scene_type'] = array_unique($v['scene_type']);
//                            $add_data = $v;
//                            ksort($add_data['scene_type']);
//                            ksort($add_data['scene_code']);
//                            $scene = [
//                                'scene_type' => $add_data['scene_type'],
//                                'scene_code' => $add_data['scene_code']
//                            ];
//                            $scenes_code = json_encode($scene);
//                            $where = ['standard_id' => $params['standard_id']];
//                            $sub_activity = SubActivity::findAllArray($where,
//                                ['id', 'scenes_type_id', 'scenes_code']);
//                            foreach ($sub_activity as $item) {
//                                $tmpArr = json_decode($item['scenes_type_id'], true);
//                                ksort($tmpArr);
//                                $check['scene_type'] = $tmpArr;
//                                $tmpArr = json_decode($item['scenes_code'], true);
//                                ksort($tmpArr);
//                                $check['scene_code'] = $tmpArr;
//                                $check_code = json_encode($check);
//                                //如果都是全店模式，或者json数据对比相同那么插入子活动id
//                                if ((!empty($check['scene_type']) && $add_data['is_all_scene'] == RuleOutputInfo::IS_ALL_SCENE_YES &&
//                                        $check['scene_type'][0] == SceneType::SCENE_TYPE_ALL) || $scenes_code == $check_code) {
//                                    $rule_output_field[$k]['sub_activity_id'] = $item['id'];
//                                }
//                            }
//                            $id = ['standard_id', $params['standard_id']];
//                        } else {
//                            $id = ['statistical_id', $params['statistical_id']];
//                            $rule_output_field[$k]['sub_activity_id'] = 0;
//                        }
//                        $rule_output_list[] = $rule_output_field[$k];
////                        if (!$result[0]) {
////                            $transaction->rollBack();
////                            return $this->error($result[1]);
////                        }
//                    }
                    if ($rule_output_field) {
                        $result = RuleOutputInfo::updateOutput($id, $rule_output_field);
                    }
                } else {
                    //规则引擎新返回的数据以json格式与老数据对比，产生变化
                    $old_output = [];
                    foreach ($output_result as $v) {
                        $v['node_index'] = (int)$v['node_index'];
                        $v['node_name'] = $v['node_name'];
                        $v['output_type'] = isset($v['output_type']) ? (int)$v['output_type'] : RuleOutputInfo::OUTPUT_TYPE_DEFAULT;
                        $v['is_all_scene'] = (int)$v['is_all_scene'];
                        $v['scene_type'] = json_decode($v['scene_type'], true);
                        $v['scene_code'] = json_decode($v['scene_code'], true);
                        $v['formats'] = json_decode($v['formats'], true);
                        $v['sub_activity_id'] = json_decode($v['sub_activity_id'], true);
                        ksort($v);
                        $old_output[] = json_encode($v);
                    }
                    $new_output = [];
                    foreach ($rule_output_field as $v) {
                        $v['is_all_scene'] = (int)$v['is_all_scene'];
                        ksort($v);
                        foreach ($v['sub_activity_id'] as $item){
                            $tmp = $v;
                            $tmp['sub_activity_id'] = $item;
                            $new_output[] = json_encode($tmp);
                        }
                    }
                    //老的比新的多的条目就是最近删除
                    $new_delete = array_diff($old_output, $new_output);
                    foreach ($new_delete as $v) {
                        $delete_data = json_decode($v, true);
                        $where = isset($params['standard_id']) ? ['standard_id' => $params['standard_id'], 'node_index' => $delete_data['node_index']] :
                            ['statistical_id' => $params['statistical_id'], 'node_index' => $delete_data['node_index']];
                        RuleOutputInfo::deleteOutput($where, $id);
                        $change = true;
                    }
                    //新的比老的多的条目就是最近新增
                    $new_add = array_diff($new_output, $old_output);
                    $change = $new_add ? true : $change;
                    if ($new_add) {
                        $result = RuleOutputInfo::updateOutput($id, $new_add);
                    }
                }

                if (!isset($params['statistical_id'])) {
                    Standard::updateAll(['is_change' => Standard::NOT_CHANGE, 'setup_step' => Standard::SETUP_STEP_SET_RULE, 'set_rule' => Standard::SET_RULE_YES],
                        ['id' => $params['standard_id']]);
                    $standard_model = Standard::findOne(['id' => $params['standard_id']]);
                    $scenes = json_decode($standard_model->scenes, true);
                    $all_type = SceneType::findAllArray([], ['*'], 'id');
                    $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
                    RuleService::joinSceneAndOutput($rule_output_field,$all_type,$all_scene);
                    foreach ($scenes as &$v) {
                        $v['outputList'] = [];
                        foreach ($rule_output_field as $item) {
                            if (is_array($item['sub_activity_id'])) {
                                if (in_array($v['sub_activity_id'], $item['sub_activity_id']) && $item['output_type'] == RuleOutputInfo::OUTPUT_TYPE_BOOL) {
                                    $value = $item;
                                    unset($value['sub_activity_id']);
                                    unset($value['formats']);
                                    $value['disabled'] = true;
                                    $v['outputList'][] = $value;
                                }
                            } else {
                                if (($v['sub_activity_id'] == $item['sub_activity_id']) && $item['output_type'] == RuleOutputInfo::OUTPUT_TYPE_BOOL) {
                                    $value = $item;
                                    unset($value['sub_activity_id']);
                                    unset($value['formats']);
                                    $value['disabled'] = true;
                                    $v['outputList'][] = $value;
                                }
                            }
                        }
                    }
                    $standard_model->scenes = json_encode($scenes);
                    if (!$standard_model->save()) {
                        return $this->error($standard_model->getErrors());
                    }
                } else {
                    StatisticalItem::updateAll(['setup_step' => StatisticalItem::SETUP_STEP_RULE], ['id' => $params['statistical_id']]);
                }
                //以前的需求与现在的需求有冲突
//                if ($change && isset($params['standard_id'])) {
//                    $clear = Standard::clearOutput(['id' => $params['standard_id']]);
//                    if (!$clear) {
//                        $transaction->rollBack();
//                        return $this->error('输出项入库失败！');
//                    }
//                }
                $transaction->commit();
            } catch (\Exception $e) {
                if (isset($transaction)) {
                    $transaction->rollBack();
                }
                Yii::error($e);
                return $this->error($e->getMessage());
            }
            return $this->success($params);
        } else {
            return $this->error('缺少rule_output_field字段，请检查');
        }
    }

    /**
     * 直接由media过来的数据
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionMediaResult()
    {
        $form = new mediaResultModel();
        //验证token
        $headers = Yii::$app->getRequest()->getHeaders();
        $timestamp = $headers->get('timestamp');
        $token = $headers->get('token');

        $api_key = Yii::$app->params['media_key'];
        $vali = md5($timestamp . md5($api_key) . $timestamp);
        if (empty($timestamp) || empty($token) || $token != $vali) {
            return $this->error('token验证失败');
        }
        $body = Yii::$app->request->bodyParams;
        $form->load($body, '');
        if ($form->validate()) {
            if (!isset(self::TOOL[$form->tool_id])) {
                return $this->error(self::TOOL_WRONG);
            }
            if (!strtotime($form->survey_time)) {
                return $this->error('日期字段survey_time格式非法');
            }

//            $survey_code = $form->survey_id;
//            $result = Survey::getIdByCode($survey_code);
            //场景code如果不存在就直接返回
            $check = Scene::findOneArray(['scene_code' => $form->scene_code]);
            if (empty($check)) {
                return $this->error('场景code不存在，请检查');
            }
//            if (!$result[0]) {
            $store_id = $form->store_id;
            $where = ['st.store_id' => $store_id];
            $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code'];
            $store_result = Store::getChannelSubInfo($where, $select);
            $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
            $survey['sub_channel_id'] = !empty($store_result) && $store_result['id'] != null ? $store_result['id'] : 0;
            $survey['survey_code'] = $form->survey_id;
            $survey['store_id'] = $form->store_id;
            $survey['tool_id'] = self::TOOL[$form->tool_id];
//                $survey['examiner_id'] = $form->examiner_id;
//                $survey['examiner'] = $form->examiner;
//                $survey['survey_date'] = $form->survey_date;
            //直接从王河来的media数据走访时间用走访号解析取得
            $survey['survey_time'] = date('Y-m-d H:i:s', strtotime(substr($form->survey_id, 0, 14)));
            $survey['photo_url'] = $form->photo_url;
            $survey['sub_activity_id'] = isset($body['sub_activity_id']) && !empty($body['sub_activity_id']) ? $body['sub_activity_id'] : 0;
            $survey['plan_id'] = isset($form->plan_id) && !empty($form->plan_id) ? $form->plan_id : 0;
            $survey['survey_status'] = $form->survey_status;
            $survey['company_code'] = $store_result['company_code'] ? $store_result['company_code'] : '';
            $survey['bu_code'] = $store_result['bu_code'] ? $store_result['bu_code'] : '';
            $survey['location_name'] = $store_result['location_name'] ? $store_result['location_name'] : '';
            $survey['supervisor_name'] = $store_result['supervisor_name'] ? $store_result['supervisor_name'] : '';
            $survey['route_code'] = $store_result['route_code'] ? $store_result['route_code'] : '';
            $transaction = Yii::$app->db->beginTransaction();
            //考虑重推执行覆盖策略
            $survey_result = Survey::saveSurveyCover($survey);
            if (!$survey_result[0]) {
                $transaction->rollBack();
                return $this->error($survey_result[1]);
            }
            $survey_id = $survey_result[1];


            $image['survey_code'] = $form->survey_id;
            $image['tool_id'] = self::TOOL[$form->tool_id];
            $image['img_type'] = !empty($images) ? Image::IMG_DISCRIMINATE : Image::IMG_QUESTION_COPY;
            $image['scene_code'] = $form->scene_code;
            $image['scene_id'] = $form->scene_id;
            $image['scene_id_name'] = $form->scene_id_name;
            $image_result = Image::saveImage($image);
            if (!$image_result[0]) {
                $transaction->rollBack();
                return $this->error($image_result[1]);
            }
            $re = ImageUrl::findOne(['image_id' => $image_result[1]]);
            if ($re) {
                ImageUrl::updateAll(['status' => ImageUrl::DEL_STATUS_DELETE], ['image_id' => $image_result[1]]);
            }


            $scene_exist = SurveyScene::findOne(['scene_id' => $form->scene_id, 'survey_id' => $survey_id]);
            if (empty($scene_exist)) {
                $survey_scene['survey_id'] = $form->survey_id;
                $survey_scene['tool_id'] = self::TOOL[$form->tool_id];
                $survey_scene['scene_code'] = $form->scene_code;
                $survey_scene['scene_id'] = $form->scene_id;
                $survey_scene['scene_id_name'] = $form->scene_id_name;
//            $survey_scene['asset_name'] = $form->asset_name;
//            $survey_scene['asset_code'] = $form->asset_code;
//            $survey_scene['asset_type'] = $form->asset_type;
                $survey_scene_result = SurveyScene::saveSurveyScene($survey_scene);
                if (!$survey_scene_result[0]) {
                    $transaction->rollBack();
                    return $this->error($survey_scene_result[1]);
                }
            }


            if (!empty($form->result)) {
                $image_report['survey_id'] = $form->survey_id;
                $image_report['photo_id'] = $image_result[1];
                $image_report['origin_type'] = ImageReport::REPORT_TYPE_IMAGE;
                $image_report['url'] = $form->result_img;
                $image_report['result'] = is_array($form->result) ? json_encode($form->result) : $form->result;
                $image_report['scene_type'] = $form->scene_type;
                $image_report['report_status'] = ImageReport::REPORT_STATUS_END;
                $image_report_result = ImageReport::createImageReport($image_report);
                if (!$image_report_result[0]) {
                    $transaction->rollBack();
                    return $this->error($image_report_result[1]);
                }
            }


            $images = $form->photo_url;
            $url = [];
            $image_url_arr = [];
            if (!empty($images)) {
                foreach ($images as $k=>$v) {
                    if (isset($v['url'])) {
                        $image_url = [];
                        $image_url[] = $image_result[1];
                        $image_url[] = $v['url'];
                        $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                        $url[] = $v['url'];
                        $image_url_arr[] = $image_url;
                    }
                }
            }
            if (!empty($image_url_arr)) {
                $url_result = ImageUrl::saveImageUrl($image_url_arr);
                if (!$url_result[0]) {
                    $transaction->rollBack();
                    return $this->error($url_result[1]);
                }
            }
            $transaction->commit();

            if ($form->survey_status == Survey::SURVEY_END) {
                //入判断是否能送引擎计算队列
                $data['survey_id'] = $body['survey_id'];
                $projectId = Yii::$app->params['project_id'];
                $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['calculation_task'] . $projectId, $data);
                if ($queue) {
                    return $this->success('接收并发送规则引擎成功');
                } else {
                    $transaction->rollBack();
                    return $this->error('走访号：' . $body['survey_id'] . '发送规则引擎失败');
                }
            } else {
                return $this->success('接收成功');
            }
        } else {
            $err = $form->getErrors();
            return $this->error($err);
        }
    }

    /**
     * 规则引擎查询token是否有效
     * @return array
     */
    public function actionValidateToken()
    {
        $check_id = 'SEAP-SURVEY-0006';
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['token'])) {
            return $this->error();
        }
        $user_cache = User::getSwireUser($params['token']);
        if ($user_cache != null) {
            if (!in_array($check_id, User::getFunctionList($params['token']))) {
                $data['auth'] = false;
                return $this->error('', -1, $data);
            }
            $data['auth'] = true;
            return $this->success($data);
        } else {
            $data['auth'] = false;
            return $this->error('', -1, $data);
        }
    }

    /**
     * 查询规则是否使用过
     * @return array
     */
    public function actionStandardStatus()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['standard_id'])) {
            return $this->error();
        }
        $result = Plan::getStandardStatus($params);
        $is_used = $result ? true : false;
        return $this->success(['standard_id' => $params['standard_id'], 'is_used' => $is_used]);
    }
}