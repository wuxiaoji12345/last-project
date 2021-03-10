<?php


namespace api\models\apiModels;


use api\models\Tools;
use yii\base\Model;
use Yii;

class apiBaseModel extends Model
{
    public $tool_id = null;

    public $token = null;
    public $timestamp = null;
    public $login = null;
    public function rules()
    {
        return [
            // todo 去除token校验
//            [['token', 'timestamp'], 'required'],
            ['token', 'validateToken']
        ];
    }

    /**
     * 校验model数组字段的数量上限
     * @param $attr
     * @param $params
     */
    public function arrayCountLimit($attr, $params)
    {
        if (is_array($this->$attr)) {
            if (count($this->$attr) > $params['max']) {
                $this->addError($attr, '数组数量不能大于' . $params['max']);
            }
        } else {
            $this->addError($attr, '必须为数组');
        }
    }

    public function getErrStr($all = true)
    {
        if (!$this->hasErrors()) {
            return '';
        }
        if ($all) {
            $strArr = [];
            $errors = $this->getErrorSummary(true);
            foreach ($errors as $error) {
                $strArr[] = $error;
            }
            $errStr = implode(' ', $strArr);
        } else {
            $err = $this->getFirstErrors();
            $attr = array_keys($err);
            $errStr = $err[$attr[0]];
        }
        return $errStr;
    }


    /**
     * 验证token是否有效
     */
    public function validateToken()
    {
        // todo 去除token校验
        return true;
        $queueName = Yii::$app->remq::getQueueName('queue_tool_token');
        $tokenAll = Yii::$app->remq::getString($queueName);
        if ($tokenAll == null) {
            $tokenAll = [];
        }
        if (in_array($this->token, $tokenAll)) {
            $this->login = true;
            return true;
        }
        $tools = Tools::findAllArray([]);
        foreach ($tools as $tool) {
            $md5token_real = \Helper::md5token($this->timestamp, $tool['token']);
            if ($md5token_real == $this->token) {
                $tokenAll[$tool['id']] = $md5token_real;
                $this->login = true;
                Yii::$app->remq::setString($queueName, $tokenAll);
                return true;
            }
        }
        $this->addError('token', 'token无效');
        return false;
    }
}