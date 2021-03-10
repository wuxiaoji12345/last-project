<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2018/8/14
 * Time: 下午2:58
 */

namespace api\controllers;

use api\models\LogApi;
use api\models\User;
use Codeception\Util\HttpCode;
use common\components\REMQ;
use common\libs\file_log\LOG;
use Yii;
use yii\base\Exception;

/**
 * @property User $USER
 *
 * Class BaseApi
 * @package api\controllers
 */
class BaseApi extends \common\controllers\BaseApi
{
    const ACCESS_CONTROL = true;
    const ACCESS_ANY = [];      // 不需要校验的权限列表

    public function init()
    {
        parent::init();
        self::logApi();
        LOG::log(Yii::$app->request->url);
        LOG::log(json_encode(Yii::$app->request->post(), JSON_UNESCAPED_UNICODE));
        if (!SITE_ACCESS_CONTROL) {
            // token写入缓存
            // 从数据库找1个账号
            $user_arr = User::findOneArray(['id' => SITE_ACCESS_MOCK_USER]);
            if (empty($user_arr)) {
                throw new Exception('mock用户id无法在数据库中找到');
            }
//            $user_str = '{"user_id":"30060001","bu_code":"0001","swire_bu_code":"HZ01","display_name":"SEA+\u6d4b\u8bd5\u8d26\u62371","company_code":"3006","token":"","id":5}';
            $user = [
                'id' => $user_arr['id'],
                'company_code' => $user_arr['company_code'],
                'bu_code' => $user_arr['bu_code'],
                'swire_bu_code' => $user_arr['bu_code'],
                'display_name' => $user_arr['display_name'],
                'token' => $user_arr['token'],
                'user_id' => $user_arr['user_id'],
            ];
            $bodyForm = Yii::$app->request->bodyParams;
            $token = isset($bodyForm['token']) ? $bodyForm['token'] : $user_arr['token'];
            $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user_info'];
            $queue_name .= $token;
            $user['token'] = $token;

            Yii::$app->remq::setString($queue_name, $user);
            Yii::$app->remq::setExpire($queue_name, User::TOKEN_EXPIRE_TIME);
        }
    }

    public function beforeAction($action)
    {
        if (Yii::$app->request->isOptions) {
            return parent::beforeAction($action);
        }
        // 校验权限
        if (static::ACCESS_CONTROL) {
            $ctl_id = $this->id;
            $action_id = $action->id;
            $url = '/' . $ctl_id . '/' . $action_id;
            $bodyForm = Yii::$app->request->bodyParams;
            if (!isset($bodyForm['token'])) {
                Yii::$app->response->data = $this->error('请求非法，必须带token', HttpCode::BAD_REQUEST);
                parent::beforeAction($action);
                return false;
            }

            $token = $bodyForm['token'];
            // 先获取用户信息
            $user = User::getSwireUser($token);
            if ($user == null) {
                Yii::$app->response->data = $this->error('token无效或已过期', HttpCode::UNAUTHORIZED);
                parent::beforeAction($action);
                return false;
            }
            Yii::$app->params['user_info'] = $user;
            Yii::$app->params['user_is_3004'] = $user->company_code == User::COMPANY_CODE_ALL;
            if (in_array($action_id, static::ACCESS_ANY)) {
                return parent::beforeAction($action);
            }
            // 再获取用户权限列表
            $function_list = User::getFunctionList($token);
            if ($function_list == null) {
                Yii::$app->response->data = $this->error('没有权限', HttpCode::FORBIDDEN);
                parent::beforeAction($action);
                return false;
            }

            $function_id = User::getFunctionId($url);
            // 校验权限
            if (!in_array($function_id, $function_list)) {
                Yii::$app->response->data = $this->error('没有权限', HttpCode::FORBIDDEN);
                parent::beforeAction($action);
                return false;
            }
        }
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        LOG::log(json_encode($result, JSON_UNESCAPED_UNICODE));
        return parent::afterAction($action, $result);
    }

    private function logApi()
    {
//        $m = new LogApi();
//        $m->request_uri = Yii::$app->request->getPathInfo();
//        $m->ip = Yii::$app->request->getUserIP();
//        $m->ua = Yii::$app->request->getUserAgent();
//        $m->data = json_encode(array(
//            'post' => $_POST,
//            'get' => $_GET,
//            'input' => file_get_contents("php://input")
//        ));
//        $m->save();
//        Yii::$app->params['log_id'] = $m->id . ' ' . base64_encode(microtime());
        Yii::$app->params['log_id'] = base64_encode(microtime());
    }


    public function success($data = null, $code = 200, $msg = 'success')
    {
        return ['data' => $data, 'code' => $code, 'msg' => $msg, 'status' => true, 'output' => true];
    }

    public function error($msg = '', $code = -1, $data = [])
    {
        $msg = $msg ? $msg : $this->responseMsg;
        return ['data' => $data, 'code' => $code, 'msg' => $msg, 'status' => false, 'output' => true];
    }

    protected function downloadPushQueue($redis_queue_key, $where, $classModel, $dataFunction, $query = '')
    {
        $where['page'] = 1;
        $where['page_size'] = 1;

//        $data = QuestionAnswer::getAnswerData($where);
        $data = $classModel::$dataFunction($where);
        $where['count'] = $data['count'];

        if ($data['count'] > 1040000) {
            return $this->error('导出的数量（' . $data['count'] . '）大于 excel 最大行数，请联系研发部门手动导出');
        }

        $time = time();
        // 这里不要动 $QueueName
        $QueueName = Yii::$app->params['project_id'] . '_' . $redis_queue_key . '_' . $time;
        $search_export_task_id = Yii::$app->redis->incr($QueueName);
        Yii::$app->redis->expire($QueueName, 5);

        $search_task_id = $time . '_' . $search_export_task_id;
        // 这里不要动 $QueueName
        $QueueName = Yii::$app->params['project_id'] . '_' . $redis_queue_key;
        Yii::$app->remq->enqueue($QueueName, array_merge($where, ['search_task_id' => $search_task_id, 'class' => $classModel, 'query' => $query]));

        return ['status' => true, 'search_task_id' => $search_task_id];
    }

    /**
     * 简化版本推送下载队列
     * @param $data
     * @return array
     */
    protected function downloadPushQueueSimple($data)
    {
        if ($data['count'] > 1040000) {
            return $this->error('导出的数量（' . $data['count'] . '）大于 excel 最大行数，请联系研发部门手动导出');
        }
        $redis_queue_key = Yii::$app->params['redis_queue']['download_list'];
        $time = time();
        $QueueName = Yii::$app->params['project_id'] . '_' . $redis_queue_key . '_' . $time;
        $search_export_task_id = Yii::$app->redis->incr($QueueName);
        Yii::$app->redis->expire($QueueName, 5);

        $search_task_id = $time . '_' . $search_export_task_id;
        $QueueName = Yii::$app->params['project_id'] . '_' . $redis_queue_key;
        Yii::$app->remq->enqueue($QueueName, array_merge($data, ['search_task_id' => $search_task_id]));

        return ['status' => true, 'search_task_id' => $search_task_id];
    }

    protected function makeWhere($data, $params)
    {
        $where = [];
        $where[] = 'and';
        foreach ($data as $item1) {
            foreach ($item1[0] as $k => $v) {
                //0在php中也是判断为空
                if (isset($params[$k]) && ($params[$k] || $params[$k] === "0")) {
                    $where[] = [$item1[1], $v, $params[$k]];
                }
            }
        }
        return $where;
    }

    public function DownloadProcess($bodyForm, $redisKey)
    {
        if (!$this->isPost() || !$this->check($bodyForm, ['search_task_id'])) {
            return $this->error();
        }
        $search_task_id = $bodyForm['search_task_id'];
//        $cacheKey = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['plan_excel_store_download_process_prefix'] . '_' . $search_task_id;
        $cacheKey = Yii::$app->remq::getQueueName('redis_queue', $redisKey) . '_' . $search_task_id;
        $result = Yii::$app->remq::getString($cacheKey);

        if ($result != null) {
            return $result;
        } else {
            return ['progress' => 0];
        }
    }
}