<?php
/**
 * Created by PhpStorm.
 * User: wudaji
 * Date: 2020/1/14
 * Time: 17:39
 */
namespace api\controllers;

use common\components\COS;
use common\controllers\BaseApi;
use Yii;

class CosController extends BaseApi
{
    /**
     * 上传图片到cos的控制器
     * @return array
     */
    public function actionConfig()
    {
        $post = Yii::$app->request->post();
        if ($this->isPost() && $this->check($post, ['type'])) {
            $key = Yii::$app->params['h5key'];
//            if ($post['key'] != md5($key)) {
//                return $this->error('key不正确');
//            }
            if ($post['type'] == 1) {
                $bucket = Yii::$app->params['tencentcos']['bucket'];
            } else {
                $bucket = Yii::$app->params['tencentcos']['bucket-video'];
            }
            $config = [
                'auth' => COS::getTempKeys($bucket),
                'config' => [
                    'bucket' => $bucket,
                    'region' => Yii::$app->params['tencentcos']['region']
                ]
            ];
            return $this->success($config);
        }
        return $this->error();
    }
}