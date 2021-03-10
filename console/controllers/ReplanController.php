<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

use api\models\Replan;
use api\models\ReplanQuery;
use api\models\ReplanSurvey;
use Yii;

class ReplanController extends ServiceController
{
    /**
     * 重跑计划创建后生成走访关联明细表
     */
    public function actionReplanCreate()
    {
        do {
            try {
                $QueueName = Yii::$app->remq::getQueueName('redis_queue', 'replan_create_list');
                $id = Yii::$app->remq->dequeue($QueueName);
                if ($id == null) {
                    continue;
                }
                $page = 1;
                $pageSize = 10000;
                $model = Replan::findOne(['id' => $id]);
                if ($model == null) {
                    $this->catchError('【重跑统计】生成走访明细失败，未找到重跑记录，id:' . $id);
                    continue;
                }
                $searchForm = [
                    'start_time' => $model->start_time,
                    'end_time' => $model->end_time,
                    'sub_channel_code' => explode(',', $model->sub_channel_code),
                    'tool_id' => $model->tool_id,
                    'check_scope' => $model->check_scope,
                    'standard_id' => $model->standard_id,
                    'statistical_id' => $model->statistical_id,
                    'company_bu' => explode(',', $model->filter_company_bu)
                ];
                $query = Replan::findSurvey($searchForm);

                $count = $query->count();
                $totalPageNum = ceil($count / $pageSize);
                $field = ['replan_id', 'survey_code'];
                $tran = Yii::$app->db->beginTransaction();
                $insertFlag = true;
                while ($page <= $totalPageNum) {
                    $pager = ['page' => $page, 'page_size' => $pageSize];
                    $query->page($pager);
                    $data = $query->all();
                    $insertData = [];
                    foreach ($data as $datum) {
                        $insertData[] = ['replan_id' => $model->id, 'survey_code' => $datum['survey_code']];
                    }

                    //执行批量添加
                    $insertFlag = Yii::$app->db->createCommand()->batchInsert(ReplanSurvey::tableName(), $field, $insertData)->execute() || $insertFlag;
                    $page++;
                }
                if ($insertFlag) {
//                    $model->replan_status = Replan::STATUS_RUNNING;
                    ReplanSurvey::removeDuplicate($model->id);
//                    $model->save();
                    $tran->commit();
                } else {
                    $this->catchError('【重跑统计】生成走访明细数据失败，id:' . $id);
                    $tran->rollBack();
                }
            } catch (\Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::getLogger()->flush(true);
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }

    /**
     * 走访关联明细数据推送队列送引擎计算
     * 1次送100条，每处理一次暂停10s
     */
    public function actionPushEngine()
    {
        do {
            try {
                // 先查询出1个未开始的统计计划
                $replan = Replan::findOneArray(['replan_status' => Replan::STATUS_DEFAULT]);
                if ($replan == null) {
                    continue;
                }
                // 重跑走访数据还未入库
                $query = ReplanQuery::surveyQuery($replan['id']);
                $total = $query->count();
                if ($total == 0) {
                    continue;
                }
                $replanQueueName = Yii::$app->remq::getQueueName('redis_queue', 'replan_status_prefix') . $replan['id'];
                $redisReplanIncr = Yii::$app->remq::incr($replanQueueName);
                Yii::$app->remq::setExpire($replanQueueName, 30);
                if ($redisReplanIncr > 1) {
                    continue;
                }
                $page = 1;
                $page_size = 100;
                $maxPage = ceil($total / $page_size);
                Replan::updateAll(['replan_status' => Replan::STATUS_RUNNING], ['id' => $replan['id']]);
                $queName = Yii::$app->remq::getQueueName('queue', 'calculation_task', 1);
                while ($page <= $maxPage) {
                    $pager = ['page' => $page, 'page_size' => $page_size];
                    $list_len = Yii::$app->remq::llen($queName);
                    $do_count = 0;
                    // 重跑先推500,50000可能会导致队列内存不够
                    while ($list_len > 500) {
                        $list_len = Yii::$app->remq::llen($queName);
//                        if ($do_count % 10 == 0) {
//                            $this->ding->sendTxt('【重跑计划】PushEngine 推送队列超时 ' . $queName . ' 队列数量：' . $list_len . ' 次数：' . $do_count);
//                        }
                        $do_count++;
                        sleep(60);
                    }
                    $data = $query->page($pager)->all();
                    $pushArr = [];
                    $ids = array_column($data, 'id');
                    foreach ($data as $datum) {
                        $pushArr[] = json_encode(['replan_id' => $datum['replan_id'], 'survey_code' => $datum['survey_code']]);
                    }
                    // 推送队列
                    if (!empty($pushArr))
                        $res = Yii::$app->remq->enqueueList($queName, ...$pushArr);
                    if ($res) {
                        ReplanSurvey::updateAll(['re_status' => ReplanSurvey::STATUS_RUNNING], ['id' => $ids]);
                    }
                    $page++;
                }

            } catch (\Exception $e) {
                $this->catchError($e);
            } finally {
                Yii::getLogger()->flush(true);
                sleep(10);
                Yii::$app->db->close();
                Yii::$app->db2->close();
                pcntl_signal_dispatch();
            }
        } while ($this->runnable);

    }
}