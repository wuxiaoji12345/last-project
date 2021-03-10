<?php

namespace api\controllers;

use api\models\CopyStandardLogic;
use api\models\User;
use api\models\Standard;
use api\models\Question;
use api\models\QuestionOption;
use api\models\SubActivity;
use Codeception\Example;
use Yii;
use Exception;

class CopyStandardController extends BaseApi
{
    const ACCESS_ANY = [
        'copy'
    ];

    //逻辑全部放到CopyStandardLogic，方便移植到console
    public function actionCopy()
    {
        //校验类前端要求全部返回200
        $post = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($post, ['standard_id'])) {
            return $this->success('', 200, $this->responseMsg);
        }
        //title重复性校验
        if (!empty($post['title'])) {
            $existedStandard = Standard::find()->where(['title' => $post['title']])->one();
            if ($existedStandard) return $this->success('', 200, "检查项目标题已存在");
        }
        $standard = Standard::findOne(['id' => $post['standard_id']]);
        list($rs, $data) = CopyStandardLogic::pushToTargetCheck($standard->engine_rule_code, $post['title']);
        //前端要求报错也返回200
        if ($rs === true) {
            $newStandard = Standard::find()->select(['id', 'title'])
                ->where(['engine_rule_code' => $data])->asArray()->one();
            return $this->success($newStandard);
        } else {
            return $this->success('', 200, $data);
        }
    }
}