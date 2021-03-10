<?php
/**
 * 常驻服务继承类
 */

namespace console\controllers;

use common\libs\ding\Ding;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use yii\base\Exception;
use yii\console\Controller;
use yii\helpers\FileHelper;
use Yii;

/**
 * Class ServiceController
 * @package console\controllers
 * @property Ding $ding
 */
class ServiceController extends Controller
{
    protected $runnable = true;
    public $ding = null;

    public function init()
    {
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);

        $this->ding = Ding::getInstance();
        parent::init();
    }

    /**
     * Handle process signals.
     *
     * @param int $signal The signal code to handle
     */
    public function handleSignal($signal)
    {
        $this->runnable = false;
    }

    /**
     * @param $bodyForm
     * @param $search_task_id
     * @param $file_name
     * @return array
     * @throws Exception
     */
    protected function generateFileName($bodyForm, $search_task_id, $file_name = '')
    {

        if ($file_name == '') {
            $file_name = $bodyForm['start_time'] . '_' . $bodyForm['end_time'] . '_' . '_' . $search_task_id . '.xlsx';
        } else {
            $file_name .= '.xlsx';
        }

        $relativePath = '/tmp/' . date('Ymd') . '/';
        $path = Yii::getAlias('@api') . '/web' . $relativePath;

        if (!file_exists($path)) {
            FileHelper::createDirectory($path);
        }
        return ['file_name' => $file_name, 'full_name' => $path . $file_name, 'relative_path' => $relativePath];
    }

    /**
     * 截获异常报错
     * @param $e \Exception|string
     */
    public function catchError($e)
    {
        Yii::error($e);
        if (is_string($e)) {
            $string = $e;
        } else if ($e instanceof Exception) {
            $trace = array_slice($e->getTrace(), 0, 3);
            $string = json_encode($trace, JSON_UNESCAPED_UNICODE);
        } else {
            $string = $e->getMessage();
        }

        Yii::getLogger()->flush(true);
        $this->ding->sendTxt($this->action->id . "\n" . $string);
        sleep(1);
    }

    public function finally(){
        Yii::getLogger()->flush(true);
        Yii::$app->db->close();
        Yii::$app->db2->close();
    }

    /**
     * 生成下载文件
     * @param $redisFormKey
     * @param $redisFormProgress
     * @param $headers
     * @param $title
     */
    public function generateDownloadFile($redisFormKey, $redisFormProgress, $headers, $title){

        $QueueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue'][$redisFormKey];
        $queuePrefix = Yii::$app->params['project_id'] . '_' . Yii::$app->params['redis_queue'][$redisFormProgress];
        $pageSize = 1000;
        do {
            try {
                $page = 1;
                $bodyForm = Yii::$app->remq->dequeue($QueueName);
                if ($bodyForm == null) {
                    continue;
                }
                $bodyForm['page'] = $page;
                $bodyForm['page_size'] = $pageSize;
                $search_task_id = $bodyForm['search_task_id'];
                $cacheKey = $queuePrefix . '_' . $search_task_id;
                $classModel = $bodyForm['class'];
                $getQuery = $bodyForm['query'];

                $totalPageNum = ceil($bodyForm['count'] / $bodyForm['page_size']);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $cache = Yii::$app->redis;
                $c = 'A';
                $headerRow = 1; // 表头在第几行
//                $headers = [
//                    'store_id' => '售点id',
//                    'note' => '备注',
//                ];
                $column = array_keys($headers);

                foreach ($headers as $k => $name) {
                    $sheet->setCellValue($c . $headerRow, $name);
                    $sheet->getColumnDimension($c)->setWidth('20');
                    $c++;
                }
                $row = $headerRow + 1;
//                $query = PlanStoreTmp::find()->where(['plan_id' => $plan_id, ])->limit($pageSize)->asArray();
                $query = $classModel::$getQuery($bodyForm);
                $count = $query->count();
                while ($page <= $totalPageNum) {
                    $data = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

                    // 写入excel
                    foreach ($data as $index => $datum) {
                        $tcOrd = ord('A');
                        foreach ($column as $idx => $item) {
                            $tc = chr($tcOrd + $idx);
                            $sheet->setCellValue($tc . $row, $datum[$item]);
                        }
                        $row++;
                    }
                    $cache->expire($cacheKey, 300);
                    $progress = floor($page / $totalPageNum * 100);

                    // 下面保存文件还需要消耗些时间，这里只返回99%
                    if ($progress > 99) {
                        $progress = 99;
                    }

                    $cache->set($cacheKey, json_encode([
                        'count' => $count,
                        'progress' => $progress,
                        'data' => null
                    ]));
                    $cache->expire($cacheKey, 300);
                    $page++;
                }
                // todo 名称有可能有冲突
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileFull = $this->generateFileName($bodyForm, $search_task_id, $title. '_'. $search_task_id);
                $filename = $fileFull['file_name'];
                $fileFullName = $fileFull['full_name'];
                $relativePath = $fileFull['relative_path'];
                $writer->save($fileFullName);
                $cache->set($cacheKey, json_encode([
                    'count' => $count,
                    'progress' => 100,
                    'data' => [
                        'file_path' => $relativePath . $filename
                    ]
                ]));
                $cache->expire($cacheKey, 300);
            } catch (\Exception $e) {
                $this->ding->sendTxt($e->getMessage());
                Yii::error($e);
                sleep(1);
            } finally {
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);
    }
}