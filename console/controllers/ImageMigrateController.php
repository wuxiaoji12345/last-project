<?php


namespace console\controllers;


use api\models\Image;
use api\models\ImageReport;
use api\models\ImageUrl;
use common\libs\ding\Ding;
use Yii;
use yii\db\Expression;

class ImageMigrateController extends ServiceController
{
    /**
     * @var int 迁移最小ID
     */
    public $min_id;

    /**
     * @var int 迁移最大ID
     */
    public $max_id;

    const PAGE_NUM = 20;
    const PROGRESS_NUM = 1000;

    const CODE_DB_ERROR = 1001;
    const CODE_RESULT_ERROR = 1002;
    const CODE_IMAGE_FILES_EROOR = 1003;
    const CODE_NOT_MATCH = 1004;
    const CODE_UNEXPECT_SITUATION = 1005;
    const CODE_UNEXPECT_ERROR = 1006;

    public function options($actionID)
    {
        return ['min_id', 'max_id'];
    }

    public function optionAliases()
    {
        return ['min' => 'min_id', 'max' => 'max_id'];
    }

    /**
     * 图片迁移脚本，将全部历史图片迁移到swire桶
     * 命令执行示例：./yii image-migrate/run -min=1 -max=10000
     */
    public function actionRun()
    {
        set_time_limit(0);
        //1、查询image_report历史数据，除去老medi图片
        $query = ImageReport::find()->alias('r')
                    ->select('r.*')
                    ->leftJoin(Image::tableName(). ' i', 'r.photo_id = i.id')
                    ->where(new Expression("i.tool_id in (1,2,8)"));
        if (!is_null($this->min_id)) {
            $query->andFilterWhere(['>=', 'r.id', $this->min_id]);
        }
        if (!is_null($this->max_id)) {
            $query->andFilterWhere(['<=', 'r.id', $this->max_id]);
        }
        $query->andFilterWhere(['=', 'r.report_status', 2]);
        //2、循环读取image_report表，获取result.image_files中的key，更新到image_url表
        $total = $query->count();
        $i = $success_num = $fail_num = 0;
        $start_time = date('Y-m-d H:i:s');
        echo "图片迁移开始，" . date('Y-m-d H:i:s') . PHP_EOL;
        foreach ($query->each(self::PAGE_NUM) as $image_report) {
            echo "report" . $image_report['id'] . "开始，" . date('Y-m-d H:i:s') . PHP_EOL;
            $i++;
            try {
                $result = json_decode($image_report['result'], true);
                if (!empty($result) && !empty($result['image_files'])) {
                    //获取对应的image_id的所有image_url记录，循环更新
                    $bool = true;
                    $code = 0;
                    $image_url_list = ImageUrl::find()->where(['image_id' => $image_report['photo_id']])->all();
                    if (count($result['image_files']) == count($image_url_list)) {
                        foreach ($image_url_list as $key => $image_url) {
                            if (isset($result['image_files'][$key])) {
                                $image_url->image_key = $result['image_files'][$key];
                                if (!$image_url->save()) {
                                    $code = self::CODE_DB_ERROR;
                                    $bool = false;
                                }
                            } else {
                                $code = self::CODE_IMAGE_FILES_EROOR;
                                $bool = false;
                            }
                        }
                    }
                    //兼容重推图片情况
                    else if (count($result['image_files']) < count($image_url_list)) {
                        if ((count($image_url_list) % count($result['image_files'])) == 0) {
                            foreach ($image_url_list as $key => $image_url) {
                                $index = $key % count($result['image_files']);
                                if (isset($result['image_files'][$index])) {
                                    $image_url->image_key = $result['image_files'][$index];
                                    if (!$image_url->save()) {
                                        $code = self::CODE_DB_ERROR;
                                        $bool = false;
                                    }
                                } else {
                                    $code = self::CODE_IMAGE_FILES_EROOR;
                                    $bool = false;
                                }
                            }
                        } else {
                            $code = self::CODE_NOT_MATCH;
                            $bool = false;
                        }
                    } else {
                        $code = self::CODE_UNEXPECT_SITUATION;
                        $bool = false;
                    }
                    if ($bool) {
                        $success_num++;
                    } else {
                        $fail_num++;
                        $this->enqueueFailQueue($image_report->toArray(), $code);
                    }
                } else {
                    $fail_num++;
                    $this->enqueueFailQueue($image_report->toArray(), self::CODE_RESULT_ERROR);
                }
                echo "report" . $image_report['id'] . "结束，" . date('Y-m-d H:i:s') . PHP_EOL;
                //3、发送进度提示消息
                if ($i % self::PROGRESS_NUM == 0) {
                    Ding::getInstance()->sendTxt('迁移进度:' . $i * 100 / $total . '%');
                }
                //错误条数过多触发终止程序
                if ($fail_num >= $total * 0.5) {
                    $end_time = date('Y-m-d H:i:s');
                    echo '图片迁移脚本错误数过多，异常退出';
                    Ding::getInstance()->sendTxt('图片迁移脚本错误数过多，异常退出,开始时间:' . $start_time . ',结束时间:' . $end_time . ',共' . $total . '条,成功' . $success_num . '条,失败' . $fail_num . '条');
                    exit;
                }
            } catch (\Exception $e) {
                $this->enqueueFailQueue($image_report->toArray(), self::CODE_UNEXPECT_ERROR);
                $fail_num++;
            }
        }
        $end_time = date('Y-m-d H:i:s');
        echo "图片迁移结束，" . date('Y-m-d H:i:s') . PHP_EOL;
        //4、脚本执行完成后发送钉钉通知
        Ding::getInstance()->sendTxt('图片迁移脚本执行完毕,开始时间:' . $start_time . ',结束时间:' . $end_time . ',共' . $total . '条,成功' . $success_num . '条,失败' . $fail_num . '条');
    }

    /**
     * 重跑失败数据
     */
    public function actionRerun()
    {
        $total = $success_num = $fail_num = 0;
        while (($image_report = Yii::$app->remq->dequeue($this->getFailQueueName(), false)) != null) {
            $image_report = $image_report['data'];
            $total++;
            echo "report" . $image_report['id'] . "重跑开始，" . date('Y-m-d H:i:s') . PHP_EOL;
            try {
                if (!empty($image_report['image_files'])) {
                    //获取对应的image_id的所有image_url记录，循环更新
                    $bool = true;
                    $image_url_list = ImageUrl::find()->where(['image_id' => $image_report['photo_id']])->all();
                    if (count($image_report['image_files']) == count($image_url_list)) {
                        foreach ($image_url_list as $key => $image_url) {
                            if (isset($image_report['image_files'][$key])) {
                                $image_url->image_key = $image_report['image_files'][$key];
                                if (!$image_url->save()) {
                                    $bool = false;
                                    echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                                    Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $image_url->getErrors());
                                }
                            } else {
                                $bool = false;
                                echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                                Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $this->getErrorMessage(self::CODE_IMAGE_FILES_EROOR));
                            }
                        }
                    }
                    //兼容重推图片情况
                    else if (count($image_report['image_files']) < count($image_url_list)) {
                        if ((count($image_url_list) % count($image_report['image_files'])) == 0) {
                            foreach ($image_url_list as $key => $image_url) {
                                $index = $key % count($image_report['image_files']);
                                if (isset($image_report['image_files'][$index])) {
                                    $image_url->image_key = $image_report['image_files'][$index];
                                    if (!$image_url->save()) {
                                        $bool = false;
                                        echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                                        Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $image_url->getErrors());
                                    }
                                } else {
                                    $bool = false;
                                    echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                                    Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $this->getErrorMessage(self::CODE_IMAGE_FILES_EROOR));
                                }
                            }
                        } else {
                            $bool = false;
                            echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                            Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $this->getErrorMessage(self::CODE_NOT_MATCH));
                        }
                    } else {
                        $bool = false;
                        echo "report" . $image_report['id'] . "重跑失败，" . PHP_EOL;
                        Ding::getInstance()->sendTxt('report'. $image_report['id'] .'重跑失败'. $this->getErrorMessage(self::CODE_UNEXPECT_SITUATION));
                    }
                    if ($bool) {
                        $success_num++;
                    } else {
                        $fail_num++;
                    }
                } else {
                    $fail_num++;
                }
                echo "report" . $image_report['id'] . "重跑结束，" . date('Y-m-d H:i:s') . PHP_EOL;
            } catch (\Exception $e) {
                $fail_num++;
            }
        }
        echo '图片迁移失败重跑脚本执行完毕,共' . $total . '条,成功' . $success_num . '条,失败' . $fail_num . '条';
        Ding::getInstance()->sendTxt('图片迁移失败重跑脚本执行完毕,共' . $total . '条,成功' . $success_num . '条,失败' . $fail_num . '条');
    }

    /**
     * 失败数据入队
     *
     * @param array $data
     * @param int $code
     * @return mixed
     */
    private function enqueueFailQueue($data = [], $code = 0)
    {
        $queue_name = $this->getFailQueueName();
        $result = json_decode($data['result'], true);
        $queue_data = [
            'id' => $data['id'],
            'photo_id' => $data['photo_id'],
            'image_files' => $result['image_files'],
        ];
        $message = $this->getErrorMessage($code);
        return Yii::$app->remq->enqueue($queue_name, ['data' => $queue_data, 'message' => $message, 'code' => $code]);
    }

    public function getErrorMessage($code)
    {
        $error_list = [
            self::CODE_DB_ERROR => 'save to db error',
            self::CODE_IMAGE_FILES_EROOR => 'image_report.result.image_files error',
            self::CODE_NOT_MATCH => 'image_files num not match image_url.count',
            self::CODE_RESULT_ERROR => 'image_report.result error',
            self::CODE_UNEXPECT_SITUATION => 'unexpected situation',
            self::CODE_UNEXPECT_ERROR => 'unexpected error'
        ];
        return isset($error_list[$code]) ? $error_list[$code] : '';
    }

    private function getFailQueueName()
    {
        return 'MES:IMAGE_MIGRATE:FAIL_' . gethostname();
    }
}