<?php


namespace api\controllers;

use api\models\FunctionPermission;
use api\models\User;
use Yii;

class UserController extends BaseApi
{
    const ACCESS_CONTROL = false;

    /**
     * 返回用户权限列表
     * curl从太古实时获取
     * @return array
     */
    public function actionFunctionList()
    {
        $bodyForm = Yii::$app->request->bodyParams;

        if (!$this->isPost() || !$this->check($bodyForm, ['token'])) {
            return $this->error();
        }
        $token = $bodyForm['token'];
        $function_list = User::getFunctionList($token);
        $web_function = FunctionPermission::findAllArray(['function_id'=> $function_list]);
        $result = array_column($web_function, 'web_function_id');
        $user = User::getSwireUser($token);
        return ['function_list' => $result, 'company_code'=> $user['company_code'], 'bu_code'=> $user['bu_code']];
    }

    /**
     * 清除用户token和权限列表缓存
     * @return array
     */
    public function actionSignOut(){
        $bodyForm = Yii::$app->request->bodyParams;

        if (!$this->isPost() || !$this->check($bodyForm, ['token'])) {
            return $this->error();
        }
        $token = $bodyForm['token'];

        $user = User::getSwireUser($token);
        if($user != null){
            $user->resetCache();
        }

        return $this->success();
    }

    public function actionClearFunctionMapCache(){
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_function_map'];
        Yii::$app->remq::del($queue_name);
    }
}