<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2018/8/14
 * Time: 下午2:58
 */
namespace api\modules\api\controllers;

use api\models\LogApi;
use common\libs\file_log\LOG;
use Yii;

class BaseApi extends \common\controllers\BaseApi
{
    public function init()
    {
        parent::init();
        self::logApi();
        LOG::log(Yii::$app->request->url);
        LOG::log(json_encode(Yii::$app->request->post(), JSON_UNESCAPED_UNICODE));
    }

    private function logApi()
    {
        $m = new LogApi();
        $m->request_uri = Yii::$app->request->getPathInfo();
        $m->ip = Yii::$app->request->getUserIP();
        $m->ua = Yii::$app->request->getUserAgent();
        $m->data = json_encode(array(
                                   'post' => $_POST,
                                   'get' => $_GET,
                                   'input' => file_get_contents("php://input")
                               ));
        $m->save();
        Yii::$app->params['log_id'] = $m->id. ' '. base64_encode(microtime());
    }


    public function success($data = null, $code = 200, $msg = 'success')
    {
        return ['data' => $data, 'code' => $code, 'msg' => $msg, 'status' => true, 'output' => true];
    }

    public function error($msg = '', $code = 0, $data = null)
    {
        $msg = $msg ? $msg : $this->responseMsg;
        return ['data' =>$data, 'code' => $code, 'msg' => $msg, 'status' => false, 'output' => true];
    }
}