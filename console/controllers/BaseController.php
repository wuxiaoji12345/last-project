<?php
/**
 * 手动执行脚本继承类
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

use common\libs\ding\Ding;
use Exception;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

class BaseController extends Controller
{
    public $ding = null;

    public function __construct($id, $module, $config = [])
    {
        $this->ding = Ding::getInstance();
        parent::__construct($id, $module, $config);
    }

    public $runnable = true;

    public function message($msg)
    {
        $params = [Console::FG_BLACK];
        $msg = Console::ansiFormat($msg, $params);
        Console::output("{$msg}");
    }

    public function success($msg, $withBackgroundColor = false)
    {
        $params = [Console::FG_GREEN];
        if ($withBackgroundColor) {
            $params = [Console::FG_BLACK, Console::BG_GREEN];
        }
        $msg = Console::ansiFormat($msg, $params);
        Console::output("{$msg}");
    }

    public function error($msg, $withBackgroundColor = false)
    {
        $params = [Console::FG_RED];
        if ($withBackgroundColor) {
            $params = [Console::FG_GREY, Console::BG_RED];
        }
        $msg = Console::ansiFormat($msg, $params);
        Console::output("{$msg}");
    }
    /**
     * @param $e Exception|string
     */
    public function catchError($e){
        Yii::error($e);
        $this->ding->sendTxt(is_string($e)?$e:$e->getMessage());
    }


    /**
     * 统一出队处理
     */
    public function actionDequeue()
    {
        do {
            try {
                $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue']['download_list'];
                $params = Yii::$app->remq->dequeue($QueueName);
                if ($params == null) {
                    continue;
                }
                $callback = $params['callback'];
                $callback[0] = new $callback[0];
                call_user_func($callback, $params);
            } catch (Exception $e) {
                Yii::error($e);
                $this->ding->sendTxt($e->getMessage());
                sleep(1);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }
}