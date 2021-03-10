<?php


namespace api\modules\api\controllers;


use api\models\apiModels\apiBaseModel;
use api\models\apiModels\ProtocolStore;
use api\models\apiModels\RequestGetStandardInfo;
use api\models\apiModels\apiIneChannelModel;
use api\models\apiModels\apiIneReportListModel;
use api\models\apiModels\RequestProtocolStore;
use api\models\apiModels\standardCheckDataModel;
use api\models\apiModels\storeCheckDataModel;
use api\models\apiModels\transferImageCpModel;
use api\models\apiModels\transferImageSceneZftModel;
use api\models\apiModels\transferImageStoreModel;
use api\models\apiModels\transferImageSceneModel;
use api\models\EngineResult;
use api\models\Image;
use api\models\ImageReport;
use api\models\IneChannel;
use api\models\IneConfigSnapshot;
use api\models\LogApi;
use api\models\Plan;
use api\models\ProtocolTemplate;
use api\models\Question;
use api\models\QuestionAnswer;
use api\models\RuleOutputInfo;
use api\models\share\ChannelSub;
use api\models\share\Scene;
use api\models\share\Store;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Survey;
use api\models\ImageUrl;
use api\models\SurveyIneChannel;
use api\models\SurveyQuestion;
use api\models\SurveyScene;
use api\models\SurveyStandard;
use api\models\Tools;
use api\service\ine\IneConfigService;
use api\service\plan\PlanService;
use api\service\report\ReportService;
use api\service\tools\CP;
use api\service\tools\SEA;
use api\service\tools\SFA;
use common\libs\engine\Format;
use common\libs\file_log\LOG;
use common\libs\report\SceneReport;
use common\libs\sku\IRSku;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\rest\Controller;
use Yii;
use yii\web\ForbiddenHttpException;

class ApiController extends Controller
{
    public $tool_id = null;
    public $log;
    public $responseMsg = '';

    /*
     * 检查状态
     */
    const RESULT_STATUS_DEFAULT = 0;//待检查
    const RESULT_STATUS_ING = 1;//检查中
    const RESULT_STATUS_DONE = 2;//检查完成

    /*
     * 生动化结果
     */
    const ACTIVATION_RESULT_DEFAULT = 0; //待检查
    const ACTIVATION_RESULT_ING = 1; //检查中
    const ACTIVATION_RESULT_SUCCESS = 2; //检查合格
    const ACTIVATION_RESULT_FAIL = 3; //检查不合格


    const CP_TOOL_ID = 2; //CP的tool_id
    const SFA_TOOL_ID = 8; //SFA的tool_id


    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // 暂时不需要验证权限
//        $behaviors['authenticator'] = [
//            'class' => HttpBasicAuth::class,
//        ];
        return $behaviors;
    }

    /**
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        // 对外接口，可以考虑不入db库，日志只入文件日志，性能不足时可以考虑
        self::logApi();
        LOG::log(Yii::$app->request->url);
        LOG::log(json_encode(Yii::$app->request->post(), JSON_UNESCAPED_UNICODE));
        $queue = Yii::$app->getRequest()->getBodyParams();
        $header = Yii::$app->getRequest()->getHeaders();
        $timestamp = $header->get('timestamp');
        $md5token = $header->get('token');
        $this->tool_id = isset($queue['tool_id']) ? $queue['tool_id'] : '';
        $baseModel = new apiBaseModel();
        $data = [
            'tool_id' => $this->tool_id,
            'token' => $md5token,
            'timestamp' => $timestamp,
        ];
        $baseModel->load($data, '');

//        if (!$this->checkToken($timestamp, $md5token)) {
        if (!$baseModel->validate()) {
            throw new ForbiddenHttpException($baseModel->getErrStr());
        }
    }

    private function logApi()
    {
//        $m = new LogApi();
//        $m->request_uri = Yii::$app->request->getPathInfo();
//        $m->ip = Yii::$app->request->getUserIP();
//        $m->ua = Yii::$app->request->getUserAgent();
//        $input = file_get_contents("php://input");
//        $m->data = json_encode(array(
//            'post' => $_POST,
//            'get' => $_GET,
//            'input' => $input
//        ));
//        $m->save();
//        $this->log = $m;
//        Yii::$app->params['log_id'] = $m->id;
        Yii::$app->params['log_id'] = base64_encode(microtime());
    }

    public function success($data = null, $code = 200, $msg = 'success')
    {
        return ['data' => $data, 'code' => $code, 'msg' => $msg, 'status' => true, 'output' => true];
    }

    public function error($msg = 'fail', $code = -1)
    {
        $msg = $msg ? $msg : $this->responseMsg;
        return ['data' => null, 'code' => $code, 'msg' => $msg, 'status' => true, 'output' => true];
    }

    /**
     * 删除token
     * @param $token
     */
    public function delToken($token)
    {
        Yii::$app->redis->srem(Yii::$app->params['queue_tool_token'], $token);
    }

    public function afterAction($action, $result)
    {
//        $this->log->output = json_encode($result);
//        $this->log->save();
        LOG::log($result);
        return parent::afterAction($action, $result);
    }

    /**
     * 完成走访
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionSurveyFinish()
    {
        $body = Yii::$app->getRequest()->getBodyParams();
        if (!isset($body['survey_id']) || !isset($body['tool_id']) || !isset($body['is_ir'])) {
            return $this->error('缺少参数，请检查');
        }
        if ($body['tool_id'] == Tools::TOOL_ID_SEA_LEADER && (!isset($body['ine_channel_id']) || !isset($body['standard_id']))) {
            return $this->error('高管巡店缺少必要参数，请检查');
        }
        $check = $this->check($body, ['tool_id', 'is_ir']);
        if (!$check[0]) {
            return $this->error($check[1]);
        }


        $transaction = Yii::$app->db->beginTransaction();
        if (isset($body['del_scene']) && !empty($body['del_scene'])) {
            Image::updateAll(['status' => Image::DEL_STATUS_DELETE], ['and', ['survey_code' => $body['survey_id']], ['in', 'scene_id', $body['del_scene']]]);
        }
        if (isset($body['standard_list']) && !empty($body['standard_list']) && $body['tool_id'] != 1) {
            Image::updateAll(['status' => Image::DEL_STATUS_DELETE], ['and', ['survey_code' => $body['survey_id']], ['not in', 'standard_id', $body['standard_list']]]);
        }
        //如果有传检查项目id，则将该走访下的所有场景上传的检查项目值更新
        if (isset($body['standard_id']) && $body['standard_id']) {
            Image::updateAll(['standard_id' => $body['standard_id']], ['survey_code' => $body['survey_id']]);
        }
        $where = ['survey_code' => $body['survey_id']];
        if (!empty($body['ine_channel_id'])) {
            $body['ine_channel_id'] = (string)$body['ine_channel_id'];
        }
        $result = Survey::doSurveyFinish($where, $body);
        if (!$result[0]) {
            $transaction->rollBack();
            return $this->error($result[1]);
        }
        //如果是已完成的直接返回成功
        if ($result[2] == 'done') {
            $transaction->commit();
            return $this->success();
        }

        $transaction->commit();
        //入判断是否能送引擎计算队列
        $data['survey_id'] = $body['survey_id'];
        if (isset($body['standard_list'])) {
            $data['standard_list'] = $body['standard_list'];
        }
        $data['ine_channel_id'] = $body['ine_channel_id'] ?? 0;
        $projectId = Yii::$app->params['project_id'];
        $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['calculation_task'] . $projectId, $data);
        if ($queue) {
            return $this->success($queue);
        } else {
            Yii::error('走访号:' . $body['survey_id'] . '完成接口入队失败');
            return $this->error('入队列失败');
        }
    }

    /**
     * 接收售点级别的上传图片及问卷
     * @return array
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionTransferImageStore()
    {
        $form = new transferImageStoreModel();
        $body = Yii::$app->getRequest()->getBodyParams();
        $form->load($body, '');
        $transaction = Yii::$app->db->beginTransaction();
        if ($form->validate()) {
            $check = !isset($body['is_inventory']) || $body['is_inventory'] ? $this->check($body, ['tool_id', 'store_id', 'survey_date', 'survey_time', 'examiner_id']) :
                $this->check($body, ['tool_id', 'survey_date', 'survey_time', 'examiner_id']);
            if (!$check[0]) {
                return $this->error($check[1]);
            }
            $survey_code = $form->survey_id;
            $result = Survey::getIdByCode($survey_code);
            if (!$result[0]) {
                if (!empty($body['is_inventory'])) {
                    $store_id = $form->store_id;
                    $where = ['st.store_id' => $store_id];
                    $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code', 'st.name store_name', 'st.region_code'];
                    $store_result = Store::getChannelSubInfo($where, $select);
                    $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
                    $survey['sub_channel_id'] = !empty($store_result) && $store_result['id'] != null ? $store_result['id'] : 0;
                    $survey['company_code'] = $store_result['company_code'] ? $store_result['company_code'] : '';
                    $survey['bu_code'] = $store_result['bu_code'] ? $store_result['bu_code'] : '';
                    $survey['location_name'] = $store_result['location_name'] ? $store_result['location_name'] : '';
                    $survey['supervisor_name'] = $store_result['supervisor_name'] ? $store_result['supervisor_name'] : '';
                    $survey['route_code'] = $store_result['route_code'] ? $store_result['route_code'] : '';
                    $survey['region_code'] = $store_result['region_code'] ? $store_result['region_code'] : '';
                    //把清单店的售点名称也在此写入走访表
                    $survey['store_name'] = $store_result['store_name'] ? $store_result['store_name'] : '';
                }
                $survey['survey_code'] = $form->survey_id;
                $survey['store_id'] = $form->store_id;
                $survey['tool_id'] = $form->tool_id;
                $survey['examiner_id'] = $form->examiner_id;
                $survey['examiner'] = $form->examiner;
                $survey['survey_date'] = $form->survey_date;
                $survey['survey_time'] = $form->survey_time;
                $survey['plan_id'] = isset($form->plan_id) ? $form->plan_id : 0;
                $survey_result = Survey::saveSurvey($survey);
                if (!$survey_result[0]) {
                    $transaction->rollBack();
                    return $this->error($survey_result[1]);
                }
            } else {
                if ($result[1]['survey_status'] == 1) {
                    $transaction->rollBack();
                    return $this->error('走访:' . $result[1]['survey_code'] . '已结束，无法再次提交');
                }
            }
            // 不做重复上传的限制了
//            $check_repeat = Image::findOneArray(['survey_code' => $form->survey_id, 'scene_id_name' => '售点概况']);
//            if ($check_repeat) {
//                $transaction->rollBack();
//                return $this->error('已上传过售点级别的图片，请勿重复上传');
//            }

            $key = '';
            $number = 0;
            foreach ($form->images as $v) {
                if (isset($v['key'])) {
                    $key = $key ? $key . ',' . $v['key'] : $v['key'];
                    $number++;
                }
            }
            $image['img_prex_key'] = $key;
            $image['number'] = $number;
            $image['is_key'] = $number ? Image::IS_KEY_YES : Image::IS_KEY_NO;
            $image['survey_code'] = $form->survey_id;
            $image['tool_id'] = $form->tool_id;
            //售点上传图片类型默认为留底
            $image['img_type'] = Image::IMG_QUESTION_COPY;
            $image['scene_id_name'] = '售点概况';
            $image['scene_id'] = '';
            $image['scene_code'] = '';
            $image['get_photo_time'] = isset($body['get_photo_time']) ? $body['get_photo_time'] : time();
            $image_result = Image::saveImage($image);
            if (!$image_result[0]) {
                $transaction->rollBack();
                return $this->error($image_result[1]);
            }
            //第二次上传的时候覆盖前一次的图片，覆盖第一次的问卷结果
            ImageUrl::updateAll(['status' => ImageUrl::DEL_STATUS_DELETE], ['image_id' => $image_result[1]]);
            QuestionAnswer::deleteAll(['photo_id' => $image_result[1]]);


            $questionnaires = $form->questionnaires;
            $answer_arr = [];
            if (!empty($questionnaires)) {
                $question_id_arr = array_column($questionnaires, 'question_id');
//                $check1 = Question::findAllArray(['and', ['in', 'id', $question_id_arr], ['type' => Question::TYPE_SCENE]]);
                $check1 = Question::find()->where(['and', ['in', 'id', $question_id_arr], ['type' => Question::TYPE_STORE]])->select(['*'])->asArray()->all();
                if ($check1) {
                    $transaction->rollBack();
                    return $this->error('问卷:' . $check1[0]['title'] . ' 是场景问卷');
                }
//                $check2 = QuestionAnswer::findAllArray(['and', ['in', 'question_id', $question_id_arr], ['survey_id' => $form->survey_id]]);
//                if ($check2) {
//                    $transaction->rollBack();
//                    return $this->error('问卷id:' . $check2[0]['question_id'] . ' 已上传过');
//                }
                foreach ($questionnaires as $v) {
                    if ($v['answer'] == 'true') {
                        $v['answer'] = 1;
                    } else if ($v['answer'] == 'false') {
                        $v['answer'] = 0;
                    }
                    if (!is_numeric($v['answer'])) {
                        $transaction->rollBack();
                        return $this->error('问卷答案必须为数值型');
                    }
                }
            }

            $images = $form->images;
            $url = [];
            $image_url_arr = [];
            if (!empty($images)) {
                foreach ($images as $k => $v) {
                    $image_url = [];
                    $image_url[] = $image_result[1];
                    $image_url[] = $v['url'];
                    $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                    //售点图片现在要有标识
                    $image_url[] = ImageUrl::IMAGE_STORE;
                    $image_url_arr[] = $image_url;
                    $url[] = $v['url'];
                }
            }
            if (!empty($image_url_arr)) {
//                ImageUrl::deleteAll(['image_id' => $image_result[1]]);
                $key = ['image_id','image_url','image_key','img_type'];
                $url_result = ImageUrl::saveImageUrl($image_url_arr, $key);
                if (!$url_result[0]) {
                    $transaction->rollBack();
                    return $this->error($url_result[1]);
                }
            }
            $transaction->commit();


            $queue = 1;
            //现在问卷放到后面存的话，每次都要进队列
            $data['question'] = $questionnaires;
            $data['image'] = $url;
            $data['image_id'] = $image_result[1];
            $data['img_type'] = Image::IMG_QUESTION_COPY;
            $data['is_key'] = $image['is_key'];
            $data['survey_code'] = $form->survey_id;
            $data['store_id'] = $form->store_id;
            $data['tool_id'] = $form->tool_id;
            $data['scene_id_name'] = '售点概况';
            $data['scene_id'] = '';
            $data['scene_code'] = '';
            $projectId = Yii::$app->params['project_id'];
            $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['cos'] . $projectId, $data);


            if ($queue) {
                return $this->success($queue);
            } else {
                Yii::error('售点级别的上传图片接口入队失败，具体信息：' . json_encode($data));
                return $this->error('入队列失败');
            }
        } else {
            $err = $form->getErrors();
            return $this->error($err);
        }
    }

    /**
     * 接收场景级别的上传图片及问卷
     * @return array
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionTransferImageScene()
    {
        $form = new transferImageSceneModel();
        $body = Yii::$app->getRequest()->getBodyParams();
        $form->load($body, '');
        if ($form->validate()) {
            $check = !isset($body['is_inventory']) || $body['is_inventory'] ? $this->check($body, ['tool_id', 'store_id', 'scene_id_name', 'img_type', 'examiner_id', 'survey_date', 'survey_time', 'scene_id']) :
                $this->check($body, ['tool_id', 'scene_id_name', 'img_type', 'examiner_id', 'survey_date', 'survey_time', 'scene_id']);
            if (!$check[0]) {
                return $this->error($check[1]);
            }
            $survey_code = $form->survey_id;
            $result = Survey::getIdByCode($survey_code);
            //场景code如果不存在就直接返回
            $check = Scene::findOneArray(['scene_code' => $form->scene_code]);
            if (empty($check)) {
                return $this->error('场景code不存在，请检查');
            }
//            $check1 = Image::findOneArray(['scene_id' => $form->scene_id, 'survey_code' => $form->survey_id]);
//            if ($check1) {
//                return $this->success('');
//            }
            $transaction = Yii::$app->db->beginTransaction();
            if (!$result[0]) {
                if (!empty($body['is_inventory'])) {
                    $store_id = $form->store_id;
                    $where = ['st.store_id' => $store_id];
                    $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code', 'st.name store_name', 'st.region_code'];
                    $store_result = Store::getChannelSubInfo($where, $select);
                    $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
                    $survey['sub_channel_id'] = !empty($store_result) && $store_result['id'] != null ? $store_result['id'] : 0;
                    $survey['company_code'] = $store_result['company_code'] ? $store_result['company_code'] : '';
                    $survey['bu_code'] = $store_result['bu_code'] ? $store_result['bu_code'] : '';
                    $survey['location_name'] = $store_result['location_name'] ? $store_result['location_name'] : '';
                    $survey['supervisor_name'] = $store_result['supervisor_name'] ? $store_result['supervisor_name'] : '';
                    $survey['route_code'] = $store_result['route_code'] ? $store_result['route_code'] : '';
                    $survey['region_code'] = $store_result['region_code'] ? $store_result['region_code'] : '';
                    //把清单店的售点名称也在此写入走访表
                    $survey['store_name'] = $store_result['store_name'] ? $store_result['store_name'] : '';
                }
                $survey['survey_code'] = $form->survey_id;
                $survey['store_id'] = $form->store_id;
                $survey['tool_id'] = $form->tool_id;
                $survey['examiner_id'] = $form->examiner_id;
                $survey['examiner'] = $form->examiner;
                $survey['survey_date'] = $form->survey_date;
                $survey['survey_time'] = $form->survey_time;
                $survey['sub_activity_id'] = isset($body['sub_activity_id']) ? $body['sub_activity_id'] : 0;
                $survey['plan_id'] = isset($form->plan_id) ? $form->plan_id : 0;
                $survey_result = Survey::saveSurvey($survey);
                if (!$survey_result[0]) {
                    $transaction->rollBack();
                    return $this->error($survey_result[1]);
                }
            } else {
                if ($result[1]['survey_status'] == 1) {
                    $transaction->rollBack();
                    return $this->error('走访:' . $result[1]['survey_code'] . '已结束，无法再次提交');
                }
            }

            $key = '';
            $number = 0;
            foreach ($form->images as $v) {
                if (isset($v['key'])) {
                    $key = $key ? $key . ',' . $v['key'] : $v['key'];
                    $number++;
                }
            }
            $image['img_prex_key'] = $key;
            $image['number'] = $number;
            $image['is_key'] = $number ? Image::IS_KEY_YES : Image::IS_KEY_NO;
            $image['survey_code'] = $form->survey_id;
            $image['tool_id'] = $form->tool_id;
            $image['img_type'] = $form->img_type;
            $image['scene_code'] = $form->scene_code;
            $image['scene_id'] = $form->scene_id;
            $image['scene_id_name'] = $form->scene_id_name;
            $image['standard_id'] = isset($body['standard_id']) ? $body['standard_id'] : 0;
            $image['get_photo_time'] = isset($body['get_photo_time']) ? $body['get_photo_time'] : time();
            $image_result = Image::saveImage($image);
            if (!$image_result[0]) {
                $transaction->rollBack();
                return $this->error($image_result[1]);
            }
            $re = ImageUrl::findOne(['image_id' => $image_result[1]]);
            if ($re) {
                ImageUrl::updateAll(['status' => ImageUrl::DEL_STATUS_DELETE], ['image_id' => $image_result[1]]);
            }


            $survey_scene['survey_id'] = $form->survey_id;
            $survey_scene['tool_id'] = $form->tool_id;
            $survey_scene['scene_code'] = $form->scene_code;
            $survey_scene['scene_id'] = $form->scene_id;
            $survey_scene['scene_id_name'] = $form->scene_id_name;
            $survey_scene['asset_name'] = $form->asset_name;
            $survey_scene['asset_code'] = $form->asset_code;
            $survey_scene['asset_type'] = $form->asset_type;
            $survey_scene_result = SurveyScene::saveSurveyScene($survey_scene);
            if (!$survey_scene_result[0]) {
                $transaction->rollBack();
                return $this->error($survey_scene_result[1]);
            }


            $questionnaires = $form->questionnaires;
            $answer_arr = [];
            if (!empty($questionnaires)) {
                $question_id_arr = array_column($questionnaires, 'question_id');
//                $check1 = Question::findAllArray(['and', ['in', 'id', $question_id_arr], ['type' => Question::TYPE_STORE]]);
                $check1 = Question::find()->where(['and', ['in', 'id', $question_id_arr], ['type' => Question::TYPE_STORE]])->select(['*'])->asArray()->all();
                if ($check1) {
                    $transaction->rollBack();
                    return $this->error('问卷:' . $check1[0]['title'] . ' 是售点问卷');
                }
                foreach ($questionnaires as $v) {
                    if ($v['answer'] == 'true') {
                        $v['answer'] = 1;
                    } else if ($v['answer'] == 'false') {
                        $v['answer'] = 0;
                    }
                    if (!is_numeric($v['answer'])) {
                        $transaction->rollBack();
                        return $this->error('问卷答案必须为数值型');
                    }
                }
            }


            if ($form->img_type != 1 && !empty($form->images) && !empty($form->images[0])) {
                $image_report['survey_id'] = $form->survey_id;
                $image_report['photo_id'] = $image_result[1];
                $image_report['origin_type'] = 1;
                $image_report['report_status'] = 0;
                $image_report_result = ImageReport::createImageReport($image_report);
                if (!$image_report_result[0]) {
                    $transaction->rollBack();
                    return $this->error($image_report_result[1]);
                }
            }


            $images = $form->images;
            $url = [];
            $image_url_arr = [];
            if (!empty($images)) {
                foreach ($images as $k => $v) {
                    $image_url = [];
                    $image_url[] = $image_result[1];
                    $image_url[] = $v['url'];
                    $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                    $image_url_arr[] = $image_url;
                    $url[] = $v['url'];
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


            $queue = 1;
            //现在问卷放到后面存的话，每次都要进队列
            $data['question'] = $questionnaires;
            $data['image'] = $url;
            $data['image_id'] = $image_result[1];
            $data['img_type'] = $form->img_type;
            $data['survey_code'] = $form->survey_id;
            $data['store_id'] = $form->store_id;
            $data['scene_code'] = $form->scene_code;
            $data['scene_id'] = $form->scene_id;
            $data['scene_id_name'] = $form->scene_id_name;
            $data['image_report_id'] = isset($image_report_result[1]) ? $image_report_result[1] : 0;
            $data['is_key'] = $image['is_key'];
            $data['tool_id'] = $form->tool_id;
            $projectId = Yii::$app->params['project_id'];
            $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['cos'] . $projectId, $data);

//            $time = microtime(true) - $start;
            if ($queue) {
                return $this->success($queue);
            } else {
                Yii::error('场景级别的上传图片接口入队失败，具体信息：' . json_encode($data));
                return $this->error('入队列失败');
            }
        } else {
            $err = $form->getErrors();
            return $this->error($err);
        }
    }

    /**
     * 触发生成售点维度检查执行计划
     * @return array
     * @throws InvalidConfigException
     */
    public function actionPlanStoreReady()
    {
        $queue = Yii::$app->getRequest()->getBodyParams();
        // 校验日期必须大于今天
        $date = date('Y-m-d', strtotime($queue['date']));
//        if ($date <= date('Y-m-d')) {
//            return $this->error('日期必须大于当天');
//        }
        $queue['date'] = $date;
        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_plan_store_list_task'];
        Yii::$app->remq->enqueue($QueueName, $queue);
        return $this->success(null);
    }

    /**
     * cp专用图片上传接口
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionTransferImageCp()
    {
        $form = new transferImageCpModel();
        $body = Yii::$app->getRequest()->getBodyParams();
        $form->load($body, '');
        // 固定tool_id
        $form->tool_id = Tools::TOOL_ID_CP;
        if ($form->validate()) {
            $check = $this->check($body, ['tool_id', 'store_id', 'survey_date', 'survey_time']);
            if (!$check[0]) {
                return $this->error($check[1]);
            }
            $projectId = Yii::$app->params['project_id'];
            $guid = $this->getGuid();
            $store_id = $form->store_id;
            $where = ['st.store_id' => $store_id];
            $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code', 'st.region_code'];
            $store_result = Store::getChannelSubInfo($where, $select);
            $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
            $survey['sub_channel_id'] = !empty($store_result) && $store_result['name'] != null ? $store_result['id'] : 0;
            $survey['survey_code'] = $form->survey_id;
            $survey['store_id'] = $form->store_id;
            $survey['tool_id'] = $form->tool_id;
            $survey['examiner_id'] = 0;
            $survey['examiner'] = '';
            $survey['survey_date'] = $form->survey_date;
            $survey['survey_time'] = $form->survey_time;
            $survey['plan_id'] = isset($form->plan_id) ? $form->plan_id : 0;
            $survey['sub_activity_id'] = $body['sub_activity_id'];
            $survey['survey_status'] = Survey::SURVEY_END;
            $survey['region_code'] = $store_result['region_code'] ? $store_result['region_code'] : '';
            $survey['company_code'] = $store_result['company_code'] ? $store_result['company_code'] : '';
            $survey['bu_code'] = $store_result['bu_code'] ? $store_result['bu_code'] : '';
            $survey['location_name'] = $store_result['location_name'] ? $store_result['location_name'] : '';
            $survey['supervisor_name'] = $store_result['supervisor_name'] ? $store_result['supervisor_name'] : '';
            $survey['route_code'] = $store_result['route_code'] ? $store_result['route_code'] : '';
            $transaction = Yii::$app->db->beginTransaction();
            $survey_result = Survey::saveSurvey($survey);
            if (!$survey_result[0]) {
                $transaction->rollBack();
                return $this->error($survey_result[1]);
            }


            $survey_scene['survey_id'] = $form->survey_id;
            $survey_scene['tool_id'] = $form->tool_id;
            $survey_scene['scene_code'] = '';
            $survey_scene['scene_id'] = md5($guid);
            $survey_scene['scene_id_name'] = '';
            $survey_scene['asset_name'] = '';
            $survey_scene['asset_code'] = '';
            $survey_scene['asset_type'] = '';
            $survey_scene_result = SurveyScene::saveSurveyScene($survey_scene);
            if (!$survey_scene_result[0]) {
                $transaction->rollBack();
                return $this->error($survey_scene_result[1]);
            }
            $data_arr = [];
            foreach ($form->images as $item) {
                $check = $this->check($item, ['scene_code', 'images.scene_id_name', 'urls']);
                if (!$check[0]) {
                    return $this->error($check[1]);
                }
                $image['survey_code'] = $form->survey_id;
                $image['tool_id'] = $form->tool_id;
                $image['img_type'] = 0;
                $image['scene_code'] = $item['scene_code'];
                $image['scene_id'] = md5($guid);
                $image['scene_id_name'] = $item['scene_id_name'];
                $image['get_photo_time'] = isset($body['get_photo_time']) ? $body['get_photo_time'] : time();
                $image_result = Image::saveImage($image);
                if (!$image_result[0]) {
                    $transaction->rollBack();
                    return $this->error($image_result[1]);
                }
                $re = ImageUrl::findOne(['image_id' => $image_result[1]]);
                if ($re) {
                    ImageUrl::updateAll(['status' => ImageUrl::DEL_STATUS_DELETE], ['image_id' => $image_result[1]]);
                }


                $image_report['survey_id'] = $form->survey_id;
                $image_report['photo_id'] = $image_result[1];
                $image_report['origin_type'] = 1;
                $image_report['report_status'] = 0;
                $image_report_result = ImageReport::createImageReport($image_report);
                if (!$image_report_result[0]) {
                    $transaction->rollBack();
                    return $this->error($image_report_result[1]);
                }


                $url = [];
                $image_url_arr = [];
                foreach ($item['urls'] as $k => $v) {
                    $image_url = [];
                    $image_url[] = $image_result[1];
                    $image_url[] = $v;
                    $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                    $image_url_arr[] = $image_url;
                    $url[] = $v;
                }
                if (!empty($image_url_arr)) {
                    $url_result = ImageUrl::saveImageUrl($image_url_arr);
                    if (!$url_result[0]) {
                        $transaction->rollBack();
                        return $this->error($url_result[1]);
                    }
                }


                $data['image'] = $url;
                $data['image_id'] = $image_result[1];
                $data['img_type'] = 0;
                $data['survey_code'] = $form->survey_id;
                $data['store_id'] = $form->store_id;
                $data['scene_id'] = md5($guid);
                $data['image_report_id'] = isset($image_report_result[1]) ? $image_report_result[1] : 0;
                $data_arr[] = $data;
            }
            $transaction->commit();
            //批量入列送图片识别
            $queue = 1;
            if (!empty($data_arr)) {
                foreach ($data_arr as $v) {
                    $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['cos'] . $projectId, $v);
                    if (!$queue) {
                        Yii::error('cp上传图片接口入队失败，具体信息：' . json_encode($v));
                    }
                }
            }

            //入判断是否能送引擎计算队列
            $send['survey_id'] = $body['survey_id'];
            $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['calculation_task'] . $projectId, $send);

            if ($queue) {
                return $this->success($queue);
            } else {
                Yii::error('cp送引擎识别接口入队失败，具体信息：' . json_encode($send));
                return $this->error('入队列失败');
            }
        } else {
            $err = $form->getErrors();
            return $this->error($err);
        }
    }

    /**
     * SFA、CP专用场景图片上传接口
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionTransferImage007()
    {
        $s_time = microtime(true);
        $form = new transferImageSceneZftModel();
        $body = Yii::$app->getRequest()->getBodyParams();
        $form->load($body, '');
        if ($form->validate()) {
            $check = $this->check($body, ['tool_id', 'store_id', 'scene_id', 'standard_id']);
            if (!$check[0]) {
                return $this->error($check[1]);
            }
            $survey_code = $form->survey_id;
            $result = Survey::getIdByCode($survey_code);
            //场景code如果不存在就直接返回
            $check = Scene::findOneArray(['scene_code' => $form->scene_code]);
            if (empty($check)) {
                return $this->error('场景code不存在，请检查');
            }
            $check1 = Image::findOneArray(['scene_id' => $form->scene_id, 'survey_code' => $form->survey_id]);
            if ($check1) {
                return $this->success('');
            }
            $transaction = Yii::$app->db->beginTransaction();
            if (!$result[0]) {
                $store_id = $form->store_id;
                $where = ['st.store_id' => $store_id];
                $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code', 'st.region_code'];
                $store_result = Store::getChannelSubInfo($where, $select);
                $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
                $survey['sub_channel_id'] = !empty($store_result) && $store_result['id'] != null ? $store_result['id'] : 0;
                $survey['survey_code'] = $form->survey_id;
                $survey['store_id'] = $form->store_id;
                $survey['tool_id'] = $form->tool_id;
//                $survey['survey_time'] = $form->survey_time;
                $survey['sub_activity_id'] = 0;
                $survey['plan_id'] = isset($form->plan_id) ? $form->plan_id : 0;
                $survey['region_code'] = $store_result['region_code'];
                $survey['company_code'] = $store_result['company_code'];
                $survey['bu_code'] = $store_result['bu_code'];
                $survey['location_name'] = $store_result['location_name'];
                $survey['supervisor_name'] = $store_result['supervisor_name'];
                $survey['route_code'] = $store_result['route_code'];
                $survey_result = Survey::saveSurvey($survey);
                if (!$survey_result[0]) {
                    $transaction->rollBack();
                    return $this->error($survey_result[1]);
                }
            } else {
                if ($result[1]['survey_status'] == 1) {
                    $transaction->rollBack();
                    return $this->error('走访:' . $result[1]['survey_code'] . '已结束，无法再次提交');
                }
            }

            //增加兼容上传key的形式
            if (isset($body['keys'])) {
                $key = '';
                $number = 0;
                foreach ($body['keys'] as $v) {
                    $key = $key ? $key . ',' . $v : $v;
                    $number++;
                }
                $image['img_prex_key'] = $key;
                $image['number'] = $number;
                $image['is_key'] = $number ? Image::IS_KEY_YES : Image::IS_KEY_NO;
            }
            $image['survey_code'] = $form->survey_id;
            $image['tool_id'] = $form->tool_id;
            $image['img_type'] = Image::IMG_DISCRIMINATE;
            $image['scene_code'] = $form->scene_code;
            $image['scene_id'] = $form->scene_id;
            $image['scene_id_name'] = $form->scene_id_name;
            $image['sub_activity_id'] = isset($body['sub_activity_id']) && !empty($body['sub_activity_id']) ? $body['sub_activity_id'] : 0;
            $image['standard_id'] = isset($body['standard_id']) ? $body['standard_id'] : 0;
            $image['get_photo_time'] = isset($body['get_photo_time']) ? $body['get_photo_time'] : time();
            $image_result = Image::saveImage($image);
            if (!$image_result[0]) {
                $transaction->rollBack();
                return $this->error($image_result[1]);
            }


            $images = $form->images;
            $url = [];
            $image_url_arr = [];
            if (!empty($images)) {
                foreach ($images as $k => $v) {
                    $image_url = [];
                    $image_url[] = $image_result[1];
                    $image_url[] = $v;
                    $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                    $image_url_arr[] = $image_url;
                    $url[] = $v;
                }
            }
            if (!empty($image_url_arr)) {
                $url_result = ImageUrl::saveImageUrl($image_url_arr);
                if (!$url_result[0]) {
                    $transaction->rollBack();
                    return $this->error($url_result[1]);
                }
            }


            if (!empty($images) && !empty($images[0])) {
                $image_report['survey_id'] = $form->survey_id;
                $image_report['photo_id'] = $image_result[1];
                $image_report['origin_type'] = 1;
                $image_report['report_status'] = 0;
                $image_report_result = ImageReport::createImageReport($image_report);
                if (!$image_report_result[0]) {
                    $transaction->rollBack();
                    return $this->error($image_report_result[1]);
                }
            }
            $transaction->commit();

            $queue = 1;
            if (!empty($form->images) && !empty($form->images[0])) {
                $data['question'] = $form->questionnaires ?: [];
                $data['image'] = $url;
                $data['image_id'] = $image_result[1];
                $data['img_type'] = Image::IMG_DISCRIMINATE;
                $data['survey_code'] = $form->survey_id;
                $data['store_id'] = $form->store_id;
                $data['tool_id'] = $form->tool_id;
                $data['scene_code'] = $form->scene_code;
                $data['scene_id'] = $form->scene_id;
                $data['scene_id_name'] = $form->scene_id_name;
                $data['image_report_id'] = isset($image_report_result[1]) ? $image_report_result[1] : 0;
                $data['is_key'] = isset($image['is_key']) ? $image['is_key'] : Image::IS_KEY_NO;
                $projectId = Yii::$app->params['project_id'];
                $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['cos'] . $projectId, $data);
            }
            $time = microtime(true) - $s_time;
            if ($queue) {
//                return $this->success($queue);
                return $this->success($time);
            } else {
//                Yii::error('场景级别的上传图片接口入队失败，具体信息：' . json_encode($data));
                return $this->error('入队列失败');
            }
        } else {
            $err = $form->getErrors();
            return $this->error($err);
        }
    }

    /**
     *SFA、ZFT专用完成走访接口
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionSurveyFinish008()
    {
        $s_time = microtime(true);
        $body = Yii::$app->getRequest()->getBodyParams();
        if (!isset($body['survey_id']) || !isset($body['tool_id']) || !isset($body['standard_list']) || !isset($body['examiner'])
            || !isset($body['examiner_id']) || !isset($body['survey_time'])) {
            return $this->error('缺少参数，请检查');
        }
        $check = $this->check($body, ['tool_id', 'survey_time']);
        if (!$check[0]) {
            return $this->error($check[1]);
        }

        $transaction = Yii::$app->db->beginTransaction();
        $standard_list = array_column($body['standard_list'], 'standard_id');
        if (isset($body['del_scene']) && !empty($body['del_scene'])) {
            $where = ['and', ['survey_code' => $body['survey_id']], ['or', ['not in', 'standard_id', $standard_list], ['in', 'scene_id', $body['del_scene']]]];
        } else {
            $where = ['and', ['survey_code' => $body['survey_id']], ['not in', 'standard_id', $standard_list]];
        }
        Image::updateAll(['status' => Image::DEL_STATUS_DELETE], $where);

        $where = ['survey_code' => $body['survey_id']];
        $body['is_ir'] = Survey::IS_IR_YES;
        $result = Survey::doSurveyFinish($where, $body);
        if (!$result[0]) {
            $transaction->rollBack();
            return $this->error($result[1]);
        }
        //如果是已完成的直接返回成功
        if ($result[2] == 'done') {
            $transaction->commit();
            return $this->success();
        }

        if (isset($body['re_scenes']) && !empty($body['re_scenes'])) {
            $image_url_arr = [];
            foreach ($body['re_scenes'] as $v) {
                $where = ['survey_code' => $v['re_survey_id'], 'scene_id' => $v['re_scene_id']];
                $select = ['i.id img_id', 'scene_id', 'standard_id', 'tool_id', 'scene_code', 'scene_id_name', 'img_type', 'origin_type', 'result', 'scene_type', 'url', 'sub_activity_id', 'img_prex_key'];
                $re_img = Image::findImageAndReport($where, $select);
                if ($re_img) {
                    $re_img['survey_code'] = $body['survey_id'];
                    $img_result = Image::saveImage($re_img);
                    if (!$img_result[0]) {
                        $transaction->rollBack();
                        return $this->error($img_result[1]);
                    } else {
                        $images = ImageUrl::findAllArray(['image_id' => $re_img['img_id']]);
                        if (!empty($images)) {
                            foreach ($images as $k => $item) {
                                $image_url = [];
                                $image_url[] = $img_result[1];
                                $image_url[] = $item['image_url'];
                                $image_url[] = isset($re_img['img_prex_key']) ? $re_img['img_prex_key'] . '_' . $k . '.jpg' : '';
                                $image_url_arr[] = $image_url;
                            }
                        }
                        $re_img['photo_id'] = $img_result[1];
                        $re_img['report_status'] = ImageReport::REPORT_STATUS_END;
                        $re_img['survey_id'] = $body['survey_id'];
                        $report_result = ImageReport::createImageReport($re_img);
                        if (!$report_result[0]) {
                            $transaction->rollBack();
                            return $this->error($report_result[1]);
                        }
                    }
                } else {
                    continue;
                }
            }
            if (!empty($image_url_arr)) {
                $url_result = ImageUrl::saveImageUrl($image_url_arr);
                if (!$url_result[0]) {
                    $transaction->rollBack();
                    return $this->error($url_result[1]);
                }
            }
        }
        $transaction->commit();
        //入判断是否能送引擎计算队列
        $data['survey_id'] = $body['survey_id'];
        $data['standard_list'] = $body['standard_list'];
        $projectId = Yii::$app->params['project_id'];
        $queue = Yii::$app->remq->enqueue(Yii::$app->params['queue']['calculation_task'] . $projectId, $data);
        $time = microtime(true) - $s_time;
        if ($queue) {
            return $this->success($time);
        } else {
            Yii::error('走访号:' . $body['survey_id'] . '完成接口入队失败');
            return $this->error('入队列失败');
        }
    }

    /**
     * 对外查询引擎计算结果接口
     * @return array
     * @throws InvalidConfigException
     */
    public function actionGetEngineReport009()
    {
        $body = Yii::$app->getRequest()->getBodyParams();
        if (!isset($body['survey_list']) || empty($body['survey_list'])) {
            return $this->error('缺少参数，请检查');
        }


        $result = [];
        foreach ($body['survey_list'] as $item) {
            $check = $this->check($item, ['survey_list.tool_id']);
            if (!$check[0]) {
                return $this->error($check[1]);
            }
            $alias = '';
            $join = [];
            $select = ['id', 'node_index', 'node_name', 'sub_activity_id', 'is_vividness'];
            $where = ['standard_id' => $item['standard_id']];
            $output_info = RuleOutputInfo::findJoin($alias, $join, $select, $where, true, true, '', 'node_index');

            $alias = 's';
            $join = [
                ['type' => 'LEFT JOIN',
                    'table' => ProtocolTemplate::tableName() . ' p',
                    'on' => 'p.id = s.protocol_id'],
                ['type' => 'LEFT JOIN',
                    'table' => Plan::tableName() . ' pl',
                    'on' => 'pl.standard_id = s.id']
            ];
            $select = ['p.excute_cycle_list', 'p.contract_id', 's.scenes', 's.protocol_id', 'p.excute_count', 'pl.re_photo_time', 'pl.id plan_id'];
            $where = ['s.id' => $item['standard_id'], 'pl.status' => Plan::DEL_STATUS_NORMAL, 'pl.plan_status' => Plan::PLAN_STATUS_ENABLE];
            $standard_result = Standard::findJoin($alias, $join, $select, $where, true, false);
            $scenes = json_decode($standard_result['scenes'], true);
            //如果连没有检查项目详情都没有，说明传错了，直接跳过
            if ($standard_result) {
                $time = date('Y-m-d H:i:s');
                //有protocol_id为协议类检查项目，没有为非协议类
                if ($standard_result['protocol_id']) {
                    $cycle_list = $standard_result['excute_cycle_list'] ? json_decode($standard_result['excute_cycle_list'], true) : [];
                    $survey_result = Survey::findCountSurvey($item['store_id'], $item['standard_id'], $item['tool_id'], $cycle_list, $time);
                    $standard_result['re_photo_time'] = $standard_result['re_photo_time'] ? $standard_result['re_photo_time'] : 0;
                    $left_time = $standard_result['excute_count'] + $standard_result['re_photo_time'];
                    //不在检查周期内的left_time为0
                    if ($survey_result[2]) {
                        $left_time = $left_time - $survey_result[0];
                        $left_time = ($left_time >= 0) ? $left_time : 0;
                    } else {
                        $left_time = 0;
                    }
                    //如果有走访详情的就是进入了大中台系统，如果没有就是待检查
                    if ($survey_result[1]) {
                        $last = array_pop($survey_result[1]);
                        $alias = '';
                        $join = [];
                        $select = ['survey_code survey_id', 'standard_id', 'result', 'qc_result', 'qc_status'];
                        $where = ['and'];
                        $where[] = ['survey_code' => $last['survey_code']];
                        $where[] = ['standard_id' => $item['standard_id']];
                        $engine_result = EngineResult::findJoin($alias, $join, $select, $where, true, false);
                        //有引擎详情的进入是否有引擎结果的判断，没有的话就是正在检查中
                        if ($engine_result) {
                            foreach ($scenes as $item1) {
                                $data = [];
                                $data['activation_id'] = $item1['activationID'];
                                $data['activation_name'] = $item1['activationName'];
                                $data['sub_activity_id'] = $item1['sub_activity_id'];
                                $data['output_list_result'] = [];
                                //生动化结果
                                $lively_result = '';
                                foreach ($item1['outputList'] as $item2) {
                                    if (isset($output_info[$item2['node_index']])) {
                                        $result_tmp = $engine_result['qc_status'] == EngineResult::ENGINE_RESULT_QC_DOWN ? $engine_result['qc_result'] : $engine_result['result'];
                                        //有引擎结果的即为所有的流程已完成，没有的话就是正在检查中
                                        if ($result_tmp) {
                                            $engine_result['result_status'] = self::RESULT_STATUS_DONE;
                                            foreach (json_decode($result_tmp, true) as $item3) {
                                                //如果node_index相同，说明就是生动化项绑定的输出项
                                                if ($item2['node_index'] == $item3['node_index']) {
                                                    $lively_result = ($lively_result === '') ? self::ACTIVATION_RESULT_SUCCESS : $lively_result;
                                                    $output_result = [
                                                        'id' => isset($item2['id']) ? $item2['id'] : $output_info[$item2['node_index']]['id'],
                                                        'node_name' => $item2['node_name'],
                                                        'output' => $item3['output']
                                                    ];
                                                    $data['output_list_result'][] = $output_result;
                                                    $lively_result = $item3['output'] ? $lively_result : self::ACTIVATION_RESULT_FAIL;
                                                }
                                            }
                                        } else {
                                            $engine_result['result_status'] = self::RESULT_STATUS_ING;
                                            $output_result = [
                                                'id' => isset($item2['id']) ? $item2['id'] : $output_info[$item2['node_index']]['id'],
                                                'node_name' => $item2['node_name'],
                                                'output' => null
                                            ];
                                            $data['output_list_result'][] = $output_result;
                                            $lively_result = self::ACTIVATION_RESULT_ING;
                                        }
                                    }
                                }
                                $data['activation_result'] = ($lively_result === '') ? self::ACTIVATION_RESULT_FAIL : $lively_result;
                                $engine_result['activation_results'][] = $data;
                            }
                            $engine_result['left_time'] = $left_time;
                            $engine_result['store_id'] = $item['store_id'];
                            $engine_result['contract_id'] = $standard_result['contract_id'];
                            unset($engine_result['result']);
                            unset($engine_result['qc_result']);
                            unset($engine_result['qc_status']);
                        } else {
                            $engine_result['result_status'] = self::RESULT_STATUS_ING;
                            $engine_result['left_time'] = $left_time;
                            $engine_result['store_id'] = $item['store_id'];
                            $engine_result['contract_id'] = $standard_result['contract_id'];
                            $engine_result['standard_id'] = $item['standard_id'];
                            $engine_result['survey_id'] = $last['survey_code'];
                            foreach ($scenes as $item1) {
                                $data = [];
                                $data['activation_id'] = $item1['activationID'];
                                $data['activation_name'] = $item1['activationName'];
                                $data['sub_activity_id'] = $item1['sub_activity_id'];
                                $data['output_list_result'] = [];
                                foreach ($item1['outputList'] as $item2) {
                                    if (isset($output_info[$item2['node_index']])) {
                                        $output_result = [
                                            'id' => isset($item2['id']) ? $item2['id'] : $output_info[$item2['node_index']]['id'],
                                            'node_name' => $item2['node_name'],
                                            'output' => null
                                        ];
                                        $data['output_list_result'][] = $output_result;
                                    }
                                }
                                $data['activation_result'] = self::ACTIVATION_RESULT_ING;
                                $engine_result['activation_results'][] = $data;
                            }
                        }
                    } else {
                        $engine_result['result_status'] = self::RESULT_STATUS_DEFAULT;
                        $engine_result['left_time'] = $left_time;
                        $engine_result['store_id'] = $item['store_id'];
                        $engine_result['contract_id'] = $standard_result['contract_id'];
                        $engine_result['standard_id'] = $item['standard_id'];
                        $engine_result['survey_id'] = null;
                        foreach ($scenes as $item1) {
                            $data = [];
                            $data['activation_id'] = $item1['activationID'];
                            $data['activation_name'] = $item1['activationName'];
                            $data['sub_activity_id'] = $item1['sub_activity_id'];
                            $data['output_list_result'] = [];
                            foreach ($item1['outputList'] as $item2) {
                                if (isset($output_info[$item2['node_index']])) {
                                    $output_result = [
                                        'id' => isset($item2['id']) ? $item2['id'] : $output_info[$item2['node_index']]['id'],
                                        'node_name' => $item2['node_name'],
                                        'output' => null
                                    ];
                                    $data['output_list_result'][] = $output_result;
                                }
                            }
                            $data['activation_result'] = self::ACTIVATION_RESULT_DEFAULT;
                            $engine_result['activation_results'][] = $data;
                        }
                    }
                } else {
                    $standard_result['re_photo_time'] = $standard_result['re_photo_time'] ? $standard_result['re_photo_time'] : 0;
                    $left_time = 1 + $standard_result['re_photo_time'];
                    $engine_model = PlanService::getSamePlanModel($standard_result['plan_id'], $item['store_id']);
//                    $alias = 's';
//                    $join = [
//                        ['type' => 'JOIN',
//                            'table' => Image::tableName() . ' i',
//                            'on' => 'i.survey_code = s.survey_code'],
//                        ['type' => 'LEFT JOIN',
//                            'table' => EngineResult::tableName() . ' e',
//                            'on' => 'e.survey_code = s.survey_code and e.standard_id = i.standard_id']
//                    ];
//                    $select = ['s.survey_code survey_id', 'e.standard_id', 'e.result', 'e.qc_result', 'e.qc_status'];
//                    $where = ['s.store_id' => $item['store_id'], 'i.standard_id' => $item['standard_id'], 's.tool_id' => $item['tool_id']];
//                    //要倒序取最新的
//                    $engine_result = Survey::findJoin($alias, $join, $select, $where, true, false, 'survey_time DESC');
                    //有引擎详情的进入是否有引擎结果的判断，没有的话就是待检查
                    if ($engine_model) {
                        $count = $engine_model->count();
                        $left_time = ($left_time - $count) > 0 ? $left_time - $count : 0;
                        $select = ['e.survey_code survey_id', 'e.standard_id', 'e.result', 'e.qc_result', 'e.qc_status'];
                        $engines = $engine_model->select($select)->asArray()->all();
                        $engine_result = array_pop($engines);
                        //有引擎结果的即为所有的流程已完成，没有的话就是正在检查中
                        $result_tmp = $engine_result['qc_status'] == EngineResult::ENGINE_RESULT_QC_DOWN ? $engine_result['qc_result'] : $engine_result['result'];
                        if ($result_tmp) {
                            $engine_result['result_status'] = self::RESULT_STATUS_DONE;
                            $engine_result['left_time'] = $left_time;
                            $engine_result['store_id'] = $item['store_id'];
                            $engine_result['contract_id'] = null;
                            foreach ($scenes as $item1) {
                                $data = [];
                                $data['activation_id'] = null;
                                $data['activation_name'] = null;
                                $data['sub_activity_id'] = $item1['sub_activity_id'];
                                $engine_result['activation_results'][] = $data;
                            }
                            foreach (json_decode($result_tmp, true) as $item2) {
//                                $alias = '';
//                                $join = [];
//                                $select = ['id'];
//                                $where = ['node_index' => $item2['node_index'], 'standard_id' => $item['standard_id']];
//                                $id = RuleOutputInfo::findJoin($alias, $join, $select, $where, true, false)['id'];
                                if (isset($output_info[$item2['node_index']]) && ($output_info[$item2['node_index']]['is_vividness'] == 1)) {
                                    $output_result = [
                                        'id' => $output_info[$item2['node_index']]['id'],
                                        'node_name' => $item2['node_name'],
                                        'output' => $item2['output']
                                    ];
                                    $tmp[$output_info[$item2['node_index']]['sub_activity_id']][] = $output_result;
                                }
                            }
                            foreach ($engine_result['activation_results'] as &$item3) {
                                $item3['output_list_result'] = isset($tmp[$item3['sub_activity_id']]) ? $tmp[$item3['sub_activity_id']] : [];
                                $item3['activation_result'] = $item3['output_list_result'] ? self::ACTIVATION_RESULT_SUCCESS : self::ACTIVATION_RESULT_DEFAULT;
                                foreach ($item3['output_list_result'] as $item4) {
                                    $item3['activation_result'] = $item4['output'] ? $item3['activation_result'] : self::ACTIVATION_RESULT_FAIL;
                                }
                            }
                        } else {
                            $engine_result['result_status'] = self::RESULT_STATUS_ING;
                            //非协议类暂定没有重拍次数
                            $engine_result['left_time'] = $left_time;
                            $engine_result['store_id'] = $item['store_id'];
                            $engine_result['contract_id'] = null;
                            //走访都没有要手动添加字段
                            $engine_result['standard_id'] = $item['standard_id'];
                            $engine_result['survey_id'] = null;
                            foreach ($scenes as $item1) {
                                $output_list_result = [];
                                foreach ($output_info as $item2) {
                                    if (($item1['sub_activity_id'] == $item2['sub_activity_id']) && ($item2['is_vividness'] == 1)) {
                                        $tmp['id'] = $item2['id'];
                                        $tmp['node_name'] = $item2['node_name'];
                                        $tmp['output'] = null;
                                        $output_list_result[] = $tmp;
                                    }
                                }
                                $data['activation_id'] = null;
                                $data['activation_name'] = null;
                                $data['sub_activity_id'] = $item1['sub_activity_id'];
                                $data['activation_result'] = self::ACTIVATION_RESULT_ING;
                                $data['output_list_result'] = $output_list_result;
                                $engine_result['activation_results'][] = $data;
                            }
                        }
                        unset($engine_result['result']);
                        unset($engine_result['qc_result']);
                        unset($engine_result['qc_status']);
                    } else {
//                        //非协议拍照次数固定为1
//                        $left_time = 1;
//                        if ($survey_result[1]) {
//                            $left_time = ($left_time >= 0) ? $left_time : 0;
//                        }
//                        $engine_result['result_status'] = self::RESULT_STATUS_DEFAULT;
//                        //非协议类暂定没有重拍次数
//                        $engine_result['left_time'] = $left_time;
//                        $engine_result['store_id'] = $item['store_id'];
//                        $engine_result['contract_id'] = null;
//                        foreach ($scenes as $item1) {
//                            $data = [];
//                            $data['activation_id'] = null;
//                            $data['activation_name'] = null;
//                            $data['sub_activity_id'] = $item1['sub_activity_id'];
//                            $data['activation_result'] = self::ACTIVATION_RESULT_DEFAULT;
//                            $data['output_list_result'] = [];
//                            $engine_result['activation_results'][] = $data;
//                        }
                        //没有走访数据的话直接返回空值
                        $engine_result = [];
                    }
                }
                if ($engine_result) {
                    $engine_result['tool_id'] = $item['tool_id'];
                    $result[] = $engine_result;
                }
            } else {
                continue;
            }
        }
        return $this->success($result);
    }

    /**
     * 对外查询拍照详情
     * @return array
     * @throws InvalidConfigException
     */
    public function actionGetPhotoInfo010()
    {
        $body = Yii::$app->getRequest()->getBodyParams();
        if (!isset($body['standard_list']) || !is_array($body['standard_list'])) {
            return $this->error('缺少参数或格式有误，请检查');
        }
//        if (isset($body['tool_id'])) {
//            $check = $this->check($body, ['tool_id']);
//            if (!$check[0]) {
//                return $this->error($check[1]);
//            }
//        }
        $send_data = [];
        foreach ($body['standard_list'] as $item) {
//        //要单独取出所有的plan与当前日期对比，如果当前是在空档期的话直接跳过
            $plans = Plan::findAllArray(['standard_id' => $item['standard_id'], 'tool_id' => $item['tool_id'], 'plan_status' => Plan::PLAN_STATUS_ENABLE]);
            $time = date('Y-m-d H:i:s');
            $start = $end = '';
            foreach ($plans as $item1) {
                if ($item1['end_time'] > $time && $time > $item1['start_time']) {
                    $start = $item1['start_time'];
                    $end = $item1['end_time'];
                    $rectification_model = $item1['rectification_model'];
                    $short_cycle = $item1['short_cycle'];
                    $plan_id = $item1['id'];
                }
            }
            if (!$start) continue;
            $alias = 's';
            $join = [
                ['type' => 'LEFT JOIN',
                    'table' => ProtocolTemplate::tableName() . ' p',
                    'on' => 'p.id = s.protocol_id'],
//                ['type' => 'LEFT JOIN',
//                    'table' => Plan::tableName() . ' pl',
//                    'on' => 'pl.standard_id = s.id']
            ];
            $select = ['p.excute_cycle_list', 'p.contract_id', 's.scenes', 's.protocol_id', 'p.excute_count'];
            $where = ['s.id' => $item['standard_id']];
            $standard_result = Standard::findJoin($alias, $join, $select, $where, true, false);
            $last = [];
            if ($standard_result) {
                //根据计划周期模式判断
                if (!empty(json_decode($short_cycle, true))) {
//                if ($standard_result['short_cycle']) {
//                    $survey_result = Survey::findCountSurvey($item['store_id'], $item['standard_id'], $item['tool_id'], $cycle_list, $time);
                    $select = ['s.survey_code', 's.survey_time', 'sa.activation_name', 'i.sub_activity_id'];
                    $join = [
                        ['type' => 'JOIN',
                            'table' => Image::tableName() . ' i',
                            'on' => 'i.survey_code = s.survey_code'],
                        ['type' => 'LEFT JOIN',
                            'table' => SubActivity::tableName() . ' sa',
                            'on' => 'i.sub_activity_id = sa.id'],
                    ];
                    $where = ['and'];
                    $where[] = ['s.store_id' => $item['store_id']];
                    $where[] = ['i.standard_id' => $item['standard_id']];
                    $where[] = ['s.tool_id' => $item['tool_id']];
                    $model = PlanService::getSamePlanModel($plan_id, $item['store_id'], $short_cycle, $join, $where);
                    if (!$model) {
                        continue;
                    }
                    $survey_result = $model->select($select)->asArray()->all();
                    if ($survey_result) {
                        $last = array_pop($survey_result);
                    }
                } else {
                    //todo 非协议类也需要判断上一次拍照的时间是否在当前的plan周期内
                    $alias = 's';
                    $join = [
                        ['type' => 'JOIN',
                            'table' => Image::tableName() . ' i',
                            'on' => 'i.survey_code = s.survey_code'],
                        ['type' => 'LEFT JOIN',
                            'table' => SubActivity::tableName() . ' sa',
                            'on' => 'i.sub_activity_id = sa.id'],
                    ];
                    $select = ['s.survey_code', 's.survey_time', 'sa.activation_name', 'i.sub_activity_id'];
                    $where = [];
                    $where[] = 'and';
                    $where[] = ['s.store_id' => $item['store_id']];
                    $where[] = ['i.standard_id' => $item['standard_id']];
                    $where[] = ['s.tool_id' => $item['tool_id']];
                    $survey_result = Survey::findJoin($alias, $join, $select, $where);
                    if ($survey_result) {
                        $last = array_pop($survey_result);
                    }
                }
                if ($last) {
                    $alias = 'i';
                    $join = [
                        ['type' => 'LEFT JOIN',
                            'table' => ImageUrl::tableName() . ' iu',
                            'on' => 'iu.image_id = i.id']
                    ];
                    $select = ['i.scene_id_name', 'i.survey_code', 'i.scene_id', 'i.scene_code', 'i.get_photo_time', 'iu.image_url'];
                    $where = ['i.survey_code' => $last['survey_code'], 'i.standard_id' => $item['standard_id']];
                    $result = Image::findJoin($alias, $join, $select, $where);
                    //如果不在最新的周期内就跳过
                    if ($last['survey_time'] > $end || $last['survey_time'] < $start) {
                        continue;
                    }
                    $data = [
                        'standard_id' => $item['standard_id'],
                        'survey_id' => $last['survey_code'],
                        'survey_time' => $last['survey_time'],
                        'activation_id' => $last['sub_activity_id'],
                        'activation_name' => $last['activation_name'],
                    ];
                    foreach ($result as $v) {
                        $unique = $v['survey_code'] . $v['scene_id'];
                        $code_info = [
                            'scene_id_name' => $v['scene_id_name'],
                            'scene_code' => $v['scene_code'],
                            'scene_id' => $v['scene_id'],
                            'get_photo_time' => $v['get_photo_time'],
                        ];
                        if (!isset($data['scene_info'][$unique])) {
                            $data['scene_info'][$unique] = $code_info;
                        }
                        if (!isset($data['scene_info'][$unique]['image_url'])) {
                            $data['scene_info'][$unique]['image_url'] = [];
                        }
                        if ($v['image_url']) {
                            $data['scene_info'][$unique]['image_url'][] = $v['image_url'];
                        }
                    }
                    if (isset($data['scene_info'])) {
                        $data['scene_info'] = array_values($data['scene_info']);
                    } else {
                        $data['scene_info'] = [];
                    }
//                        return $this->success($data);
                    $data['tool_id'] = $item['tool_id'];
                    $send_data[] = $data;
                }
            }
//            else {
//                return $this->error('standard_id不存在，请检查');
//            }
        }
        return $this->success($send_data);
    }

    /**
     * 验证方法
     * @param $data
     * @param array $check
     * @return array
     */
    public function check($data, $check = [])
    {

        foreach ($check as $item) {
            switch ($item) {
                case 'tool_id':
                    if (empty(Tools::findOneArray(['id' => $data['tool_id']]))) {
                        return [false, '没有该执行工具，请检查'];
                    }
                    break;
                case 'is_ir':
                    if (!in_array($data['is_ir'], [0, 1])) {
                        return [false, 'is_ir值有误，请检查'];
                    }
                    break;
                case 'store_id':
                    if (empty(Store::findOneArray(['store_id' => $data['store_id']]))) {
                        return [false, '没有该售点id，请检查'];
                    }
                    break;
                case 'question_id':
                    $scene_id = Scene::findOneArray(['scene_code' => $data['scene_code']], ['id'])['id'];
//                    if (empty(Question::findOneArray(['id' => $data['question_id'], 'scene_type_id' => $scene_id]))) {
                    if (empty(Question::find()->where(['id' => $data['question_id'], 'scene_type_id' => $scene_id])->select(['*'])->asArray()->one())) {
                        return [false, '问卷与场景id不匹配，请检查'];
                    }
                    break;
                case 'scene_id_name':
                    if (empty($data['scene_id_name'])) {
                        return [false, '字段scene_id_name不能为空'];
                    }
                    break;
                case 'img_type':
                    if (!in_array($data['img_type'], [0, 1, 2])) {
                        return [false, '字段img_type只能为0,1,2'];
                    }
                    break;
                case 'examiner_id':
                    if (strlen($data['examiner_id']) > 11) {
                        return [false, '字段examiner_id不能大于11位'];
                    }
                    break;
                case 'scene_id':
                    if (strlen($data['scene_id']) > 50) {
                        return [false, '字段scene_id不能大于50位'];
                    }
                    break;
                case 'survey_date':
                    if ($data['survey_date'] != "" && !strtotime($data['survey_date'])) {
                        return [false, '日期字段survey_date格式非法'];
                    }
                    break;
                case 'survey_time':
                    if ($data['survey_time'] != "" && !strtotime($data['survey_time'])) {
                        return [false, '日期字段survey_time格式非法'];
                    }
                    break;
                case 'scene_code':
                    if (!isset($data['scene_code']) || empty($data['scene_code'])) {
                        return [false, '字段images内的scene_code字段缺失'];
                    }
                    break;
                case 'images.scene_id_name':
                    if (!isset($data['scene_id_name']) || empty($data['scene_id_name'])) {
                        return [false, '字段images内的scene_id_name字段缺失'];
                    }
                    break;
                case 'urls':
                    if (!isset($data['urls'])) {
                        return [false, '字段images内的urls字段缺失'];
                    }
                    break;
                case 'standard_id':
                    $standard_id = Standard::findOne(['id' => $data['standard_id']]);
                    if (!$standard_id || $standard_id->standard_status == Standard::STATUS_DEFAULT) {
                        return [false, '查无此检查项目或者未配置计划或者检查项目是初始状态，请检查'];
                    }
                    //如果检查项目禁用了的话，要连检查项目快照表
                    if ($standard_id->standard_status == Standard::STATUS_DISABLED) {
                        $alias = 's';
                        $join = [
                            ['type' => 'LEFT JOIN',
                                'table' => ProtocolTemplate::tableName() . ' p',
                                'on' => 'p.id = s.protocol_id'],
                            ['type' => 'LEFT JOIN',
                                'table' => Plan::tableName() . ' pl',
                                'on' => 'pl.standard_id = s.standard_id'],
                        ];
                        $select = ['s.protocol_id', 'p.excute_count', 'p.excute_cycle_list', 'pl.re_photo_time'
                            , 'pl.plan_status', 'pl.status', 'pl.tool_id', 'pl.start_time', 'pl.end_time', 'pl.short_cycle'];
                        $where = ['s.standard_id' => $data['standard_id'], 's.is_standard_disable' => SurveyStandard::IS_STANDARD_DISABLE_YES, 'pl.status' => Plan::DEL_STATUS_NORMAL,
                            'pl.plan_status' => Plan::PLAN_STATUS_ENABLE, 'pl.tool_id' => $data['tool_id']];
                        $image_result = SurveyStandard::findJoin($alias, $join, $select, $where);
                    } else {
                        $alias = 's';
                        $join = [
                            ['type' => 'LEFT JOIN',
                                'table' => ProtocolTemplate::tableName() . ' p',
                                'on' => 'p.id = s.protocol_id'],
                            ['type' => 'LEFT JOIN',
                                'table' => Plan::tableName() . ' pl',
                                'on' => 'pl.standard_id = s.id'],
                        ];
                        $select = ['s.protocol_id', 'p.excute_count', 'p.excute_cycle_list', 'pl.re_photo_time'
                            , 'pl.plan_status', 'pl.status', 'pl.tool_id', 'pl.start_time', 'pl.end_time', 'pl.short_cycle'];
                        $where = ['s.id' => $data['standard_id'], 'pl.status' => Plan::DEL_STATUS_NORMAL,
                            'pl.plan_status' => Plan::PLAN_STATUS_ENABLE, 'pl.tool_id' => $data['tool_id']];
                        $image_result = Standard::findJoin($alias, $join, $select, $where);
                    }
                    if (!$image_result) return [false, '无对应plan，请检查'];
                    //非协议类直接返回
                    if (!$image_result[0]['protocol_id']) return [true, ''];
                    if (!$image_result[0]['excute_count']) {
                        return [false, '无对应zft协议或者无检查次数，请检查'];
                    }
                    if ($image_result[0]['re_photo_time'] != 0 && !$image_result[0]['re_photo_time']) {
                        return [false, '无对应检查计划，请检查'];
                    }
                    //做一个是否协议执行周期的空档期的判断
                    $cycle_list = json_decode($image_result[0]['short_cycle'], true);
                    $flag = false;
                    //暂且使用拍照时间做验证
                    $time = $data['get_photo_time'];
                    if (!$cycle_list) {
                        if ($image_result[0]['excute_cycle_list']) {
                            $excute_cycle_list = json_decode($image_result[0]['excute_cycle_list'], true);
                            foreach ($excute_cycle_list as $v) {
                                $start = date('Y-m-d', strtotime($v['cycleFromDate'])) . ' 00:00:00';
                                $end = date('Y-m-d', strtotime($v['cycleToDate'])) . ' 23:59:59';
                                if ($time > $start && $time < $end) {
                                    $flag = true;
                                    break;
                                }
                            }
                            if (!$flag) {
                                return [false, '不在检查计划的执行周期内，请勿拍照上传'];
                            }
                        } else {
                            $start = $image_result[0]['start_time'];
                            $end = $image_result[0]['end_time'];
                            if ($time <= $start && $time >= $end) {
                                $flag = true;
                            }
                        }
                        if (!$flag) {
                            return [false, '不在检查计划执行周期内，请勿拍照上传'];
                        }
                    } else {
                        foreach ($cycle_list as $v) {
                            $start = $v['start_time'] . ' 00:00:00';
                            $end = $v['end_time'] . ' 23:59:59';
//                            $time = date('Y-m-d H:i:s');
                            if ($time > $start && $time < $end) {
                                $flag = true;
                                break;
                            }
                        }
                        if (!$flag) {
                            return [false, '不在检查计划的执行周期内，请勿拍照上传'];
                        }
                    }
                    //以上逻辑都过了，就判断该次拍照是否超过检查次数+整改次数
                    $count = $image_result[0]['excute_count'] + $image_result[0]['re_photo_time'];
                    $alias = 's';
                    $join = [
                        ['type' => 'LEFT JOIN',
                            'table' => Image::tableName() . ' i',
                            'on' => 's.id = i.standard_id'],
                        ['type' => 'LEFT JOIN',
                            'table' => Survey::tableName() . ' su',
                            'on' => 'su.survey_code = i.survey_code'],
                    ];
                    $select = ['i.survey_code', 'su.survey_time'];
                    $where = ['s.id' => $data['standard_id'], 'su.store_id' => $data['store_id']];
                    $survey_result = Standard::findJoin($alias, $join, $select, $where);
                    $survey_list = [];
                    foreach ($survey_result as $v) {
                        if ($start < $v['survey_time'] && $end > $v['survey_time'] && (isset($v['survey_code']) && !empty($v['survey_code']))
                            && !in_array($v['survey_code'], $survey_list)) {
                            $survey_list[] = $v['survey_code'];
                        }
                    }
                    if (count($survey_list) >= $count) {
                        return [false, '已超过整改次数，请检查'];
                    }
                    break;
                case 'survey_list.tool_id':
                    if (!isset($data['tool_id']) || !in_array($data['tool_id'], [self::CP_TOOL_ID, self::SFA_TOOL_ID])) {
                        return [false, 'tool_id未传或者有误，请检查'];
                    }
                    break;

                default:
                    break;
            }
        }
        return [true, ''];
    }


    private function getGuid()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            return $uuid;
        }
    }

    /**
     * 获取单个售点的检查数据
     * 只能给 sfa, cp 用
     * 拍照场景和问卷
     * @return array
     */
    public
    function actionGetStoreCheckData003()
    {
        $form = Yii::$app->request->post();
        $model = new storeCheckDataModel();
        $model->load($form, '');
        $result = [];
        if ($model->validate()) {
            switch ($model->tool_id) {
                case Tools::TOOL_ID_SEA:
                    // 触发一次生成售点检查数据
                    $date = date('Y-m-d');
                    if ($model->generateCheckData($date)) {
                        $result = SEA::getStoreCheckData($model);
                    }
                    break;
                case Tools::TOOL_ID_CP:
                    break;
                case Tools::TOOL_ID_SFA:
                    /**
                     * 供SFA获取某个指定售点的活动详情（支持协议类活动和非协议类活动），
                     * 包括活动ID（standard_id和协议ID，后者可空）、活动名称、活动描述、生动化及其下属的描述、
                     * 成功图像与陈列要求、拍照场景code以活动为单位聚合）
                     */
                default:
                    $result = SFA::getStoreCheckData($model);
            }
            if ($model->hasErrors()) {
                return $this->error($model->getErrStr());
            } else {
                return $this->success($result);
            }
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 12.活动详情查询
     * @return array
     */
    public
    function actionGetStandardCheckData012()
    {
        $form = Yii::$app->request->post();
        $model = new standardCheckDataModel();
        $model->load($form, '');
        if ($model->validate()) {
            $result = SFA::getStandardCheckData($model->contract_id);

            return $this->success($result);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 售点全店聚合场景
     */
    public
    function actionGetStoreFullSceneData013()
    {
        $form = Yii::$app->request->post();
        $model = new storeCheckDataModel();
        $model->load($form, '');
        $result = [];
        if ($model->validate()) {
//            $date = date('Y-m-d');
            $model->start_date = $model->start_date == '' ? date('Y-m-d') : date('Y-m-d');
            if ($model->generateCheckData($model->start_date)) {
                $result = SEA::getStoreCheckData($model);
            }

            if ($model->hasErrors()) {
                return $this->error($model->getErrStr());
            } else {
                return $this->success($result);
            }
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 14.cp请求活动详情查询
     * @return array
     */
    public
    function actionGetCpStandardCheckData014()
    {
        $form = Yii::$app->request->post();
        $model = new standardCheckDataModel();
        $model->load($form, '');
        $model->tool_id = Tools::TOOL_ID_CP;
        if ($model->validate()) {
            $result = CP::getStandardCheckData($model->company_code);

            return $this->success($result);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 15.根据协议id获取售点列表
     * @return array
     */
    public
    function actionGetStoreListByContractId015()
    {
        $form = Yii::$app->request->post();
        $model = new RequestProtocolStore();
        $model->load($form, '');
        if ($model->validate()) {
            if ($model->contract_id == '') {
                $result = PlanService::getStoreListByStandardId($model->tool_id, $model->standard_id, $model->page, $model->page_size);
            } else {
                $result = PlanService::getStoreListByContractId($model->tool_id, $model->contract_id, $model->page, $model->page_size);
            }
            $result['contract_id'] = $model->contract_id;

            return $this->success($result);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 16.SFA活动统计详情接口
     * http://confluence.lingmou.ai:8002/pages/viewpage.action?pageId=9699336  60
     * @return array
     */
    public
    function actionGetStandardInfo016()
    {
        $form = Yii::$app->request->post();
        $model = new RequestGetStandardInfo();
        $model->load($form, '');
        if ($model->validate()) {
            $result = PlanService::getStandardInfo($model);
            return $this->success($result);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 17.根据渠道获取场景问卷和售点问卷
     * @return array
     */
    public
    function actionGetIneStandardInfo017()
    {
        $form = Yii::$app->request->post();
        $model = new apiIneChannelModel();
        $model->load($form, '');
        if ($model->validate()) {
            $result = PlanService::getChannelInfo($model);

            return $this->success($result);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 18.获取报表列表
     * @return array
     */
    public
    function actionGetIneReport018()
    {
        $form = Yii::$app->request->post();
        $model = new apiIneReportListModel();
        $model->load($form, '');
        if ($model->validate()) {
            $result = ReportService::GetSurveyListReport($model);

            return $this->success(['list' => $result]);
        } else {
            return $this->error($model->getErrStr());
        }
    }

    /**
     * 19.获取关键子表得分项目/分数
     * @return array
     */
    public
    function actionGetIneScoreConfig019()
    {
        $form = Yii::$app->request->post();

        return ReportService::IneScoreConfig($form['channel_id'], $form['year'], $form['survey_code']);
    }

    /**
     * 20.ine售点报告详情
     * @return array
     */
    public
    function actionGetIneReportInfo020()
    {
        $form = Yii::$app->request->post();
        if (!isset($form['survey_code']) || !$form['survey_code']) {
            return $this->error('参数survey不正确，请检查！');
        }
        $survey_code = $form['survey_code'];
        $where[] = 'and';
        $where[] = ['=', 's.survey_code', $survey_code];
        $where[] = ['=', 's.survey_status', Survey::SURVEY_END];
        $select = ['s.id', 's.survey_code', 's.store_id', 's.store_name', 's.survey_time', 's.store_address address', 's.sub_channel_id', 'c.channel_name channel_id_label',
            'c.id channel_id', 'r.ine_total_points ine_score', 'r.result', 'r.standard_id'];
        $data = Survey::find()->alias('s')
            ->leftJoin('sys_survey_ine_channel i', 'i.survey_code=s.survey_code')
            ->leftJoin('sys_ine_channel c', 'c.id=i.ine_channel_id')
            ->leftJoin('sys_engine_result r', 'r.survey_code=s.survey_code')
            ->select($select)->groupBy('r.id')
            ->where($where)->select($select)->asArray()->one();


        if (isset($data['id'])) {
            $data['ine_score'] = $data['ine_score'] ?? '';
            // 从售点获取地址
            if (empty($data['address']) && !empty($data['store_id'])) {
                $address = Store::findOne(['store_id' => $data['store_id']]);
                $data['address'] = isset($address['address']) ? $address['address'] : '';
            }
//            $mainChannel = ChannelSub::find()->alias('s')
//                ->leftJoin('sys_channel_main m', 'm.id=s.main_id')
//                ->where(['s.id' => $data['sub_channel_id']])->select(['m.id', 'm.name'])->asArray()->one();
//            if (isset($mainChannel['id'])) {
//                $data['channel_id'] = $mainChannel['id'];
//                $data['channel_id_label'] = $mainChannel['name'];
//            }

            // 设置子节点
            $ruleOutput = RuleOutputInfo::getResultMapByStandardId($data['standard_id']);
            $nodes = json_decode($data['result'], true);
            unset($data['result']);
            $tmp = [];
            foreach ($nodes as $node) {
                // 小数转换
                if (is_float($node['output'])) {
                    $node['output'] = round($node['output'], 2);
                }
                if (is_array($node['output'])) {
                    $node['output'] = json_encode($node['output'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($ruleOutput[$node['node_index']])) {
                    $node_index = $node['node_index'];
                    $v = $ruleOutput[$node_index];
                    $tmp[$node_index] = $node['output'];
                    if ($v['formats'] === '') {
                        $tmp[$node_index] = $node['output'];
                    } else {
                        $tmp[$node_index] = Format::outputFormat($node['output'], json_decode($v['formats'], true));
                    }
                    if ($node['output'] === false) {
                        $tmp[$node_index] = '无';
                    }
                    if ($node['output'] === true) {
                        $tmp[$node_index] = '有';
                    }
                }
            }
            $outputs = array_combine(array_column($nodes, 'node_index'), array_column($nodes, 'output'));
            $snapshots = IneConfigSnapshot::find()->alias('s')
                ->leftJoin('sys_engine_result r', 'r.ine_config_timestamp_id=s.ine_config_timestamp_id')
                ->select(['s.ine_config_id', 's.title ine_config_title', 's.max_score ine_max_score', 's.p_id', 's.level', 's.node_index', 's.display_style'])
                ->indexBy('ine_config_id')->where(['r.survey_code' => $survey_code, 's.display' => 1, 's.tree_display' => 1])->asArray()->all();
            foreach ($snapshots as &$snapshot) {
                $snapshot['ine_score'] = isset($outputs[$snapshot['node_index']]) ? $outputs[$snapshot['node_index']] : '';
                $snapshot['display_value'] = isset($tmp[$snapshot['node_index']]) ? $tmp[$snapshot['node_index']] : '';
            }
            foreach ($snapshots as &$snapshot) {
                if (isset($snapshots[$snapshot['p_id']]) && $snapshots[$snapshot['p_id']]['display_style'] == 0) {
                    $snapshot['display_value'] = $snapshot['ine_score'] > 0 ? '是' : '否';
                }
            }
            $data['ine_group'] = IneConfigService::getTree($snapshots, 0, 'ine_sub', 'ine_config_id');

            return $this->success($data);
        } else {
            return $this->error("没有对应数据");
        }
    }

    /**
     * 铺货明细列表
     * @return array
     */
    public
    function actionGetIneDistribution021()
    {
        $form = Yii::$app->request->post();
        if (!isset($form['survey_code']) || !$form['survey_code']) {
            return $this->error('参数survey不正确，请检查！');
        }
        $survey = Survey::findOne(['survey_code' => $form['survey_code']]);

        $result = [];
        if ($survey) {
            foreach ($survey->image as $v) {
                if ($v->imageReport) {
                    if ($v->imageReport->result) {
                        //直接套用施展的解析识别结果的方法
                        $re = ReportService::GetSkuInfo($v->imageReport->result);
                        foreach ($re['category_list'] as $v1) {
                            foreach ($v1['list'] as $v2) {
                                //该处要对同sku_id的做聚合
                                // 这里只返回ine sku
                                if ($v2['ine_id'] > 0) {
                                    $count = isset($result[$v2['sku_id']]) ? $result[$v2['sku_id']]['count'] + count($v2['objects']) : count($v2['objects']);
                                    $result[$v2['sku_id']] = [
                                        'sku_id' => $v2['sku_id'],
                                        'sku_name' => $v2['sku_name'],
                                        'count' => $count,
                                        'sku_property_id' => $v1['category_id'],
                                        'sku_property_label' => $v1['category_name'],
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            $result = array_values($result);
        }
        return $this->success($result);
    }
}