<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2020/7/16
 * Time: 14:05
 */

namespace console\controllers;


use api\models\apiModels\mediaResultModel;
use api\models\Image;
use api\models\ImageReport;
use api\models\ImageUrl;
use api\models\share\Scene;
use api\models\Store;
use api\models\Survey;
use api\models\SurveyScene;

class GetMediDataController extends ServiceController
{
    const TOOL = [
        'AP001' => 3,
        'AP002' => 4,
        'AP003' => 5,
        'AP004' => 6,
        'AP005' => 7,
        'AP006' => 9,
        'OTHER' => 0
    ];
    const TOOL_LIST = ['AP001', 'AP002', 'AP003', 'AP004', 'AP005', 'AP006'];
    const TOOL_WRONG = 'tool有误';

    const SLEEP_TIME = 50000;

    /**
     * 直接从本地文件获取medi的数据
     * @param string $path
     */
    public function actionGetData($path = '')
    {
        $runtimePath = \Yii::getAlias('@runtime');
        $error_seek_path = $runtimePath . "/error_seek.txt";
        $seek_path = $runtimePath . "/seek.txt";

        //创建指针文件
        if (!is_file($seek_path)) {
            $seek_handle = fopen($seek_path, 'x');
            fwrite($seek_handle, 0);
            fclose($seek_handle);
        }
        //创建出现报错存储详情文件
        if (!is_file($error_seek_path)) {
            $seek_handle = fopen($error_seek_path, 'x');
            fclose($seek_handle);
        } else {
            $re_error_seek_path = $runtimePath . "/re_error_seek.txt";
            $this->storedProcedure($error_seek_path, $seek_path, $re_error_seek_path);
            unlink($error_seek_path);
            $seek_handle = fopen($error_seek_path, 'x');
            fclose($seek_handle);
        }
//        $path = !empty($path) ? $path : "/mnt/tmpfile/2020-07-16_23:30:01_medi.txt";
        $path = !empty($path) ? $path : $runtimePath . '/2020-07-14_medi.txt';
        $this->storedProcedure($path, $seek_path, $error_seek_path);
    }

    public function whiteErrorSeek($path, $seek)
    {
        $handle = fopen($path, 'a');
        fwrite($handle, $seek);
        fclose($handle);
    }

    public function storedProcedure($path, $seek_path, $error_seek_path)
    {
        $handle = fopen($path, 'r');
        $form = new mediaResultModel();
        while (!feof($handle) && $this->runnable) {
            try {
                $seek_handle = fopen($seek_path, 'w');
                //在medi数据取一行
                $tmp = fgets($handle);
                //获得目前指针位置
                $seek = ftell($handle);
                fwrite($seek_handle, $seek);
                fclose($seek_handle);
                if (!empty($tmp)) {
                    $body = json_decode($tmp, true);
                    if (!isset($body['tool_id']) || empty($body['tool_id']) || !in_array($body['tool_id'], self::TOOL_LIST)) {
                        $body['tool_id'] = 'OTHER';
                    }
                    $form->load($body, '');
                    if ($form->validate()) {
                        if (!strtotime($form->survey_time)) {
                            usleep(self::SLEEP_TIME);
                            continue;
                        }
                        //场景code如果不存在就直接返回
                        $check = Scene::findOneArray(['scene_code' => $form->scene_code]);
                        if (empty($check)) {
                            usleep(self::SLEEP_TIME);
                            continue;
                        }
                        $store_id = $form->store_id;
                        $where = ['st.store_id' => $store_id];
                        $select = ['ch.name', 'ch.id', 'st.company_code', 'st.bu_code', 'st.location_name', 'st.supervisor_name', 'st.route_code'];
                        $store_result = \api\models\share\Store::getChannelSubInfo($where, $select);
                        $survey['sub_channel_name'] = !empty($store_result) && $store_result['name'] != null ? $store_result['name'] : '';
                        $survey['sub_channel_id'] = !empty($store_result) && $store_result['id'] != null ? $store_result['id'] : 0;
                        $survey['survey_code'] = $form->survey_id;
                        $survey['store_id'] = $form->store_id;
                        $survey['tool_id'] = self::TOOL[$form->tool_id];
                        //直接从王河来的media数据走访时间用走访号解析取得
                        $survey['survey_time'] = date('Y-m-d H:i:s', strtotime(substr($form->survey_id, 0, 14)));
                        $survey['photo_url'] = $form->photo_url;
                        $survey['sub_activity_id'] = isset($body['sub_activity_id']) && !empty($body['sub_activity_id']) ? $body['sub_activity_id'] : 0;
                        $survey['plan_id'] = isset($form->plan_id) && !empty($form->plan_id) ? $form->plan_id : 0;
                        $survey['survey_status'] = $form->survey_status;
                        $survey['company_code'] = $store_result['company_code'] ? $store_result['company_code'] : '';
                        $survey['bu_code'] = $store_result['bu_code'] ? $store_result['bu_code'] : '';
                        $survey['location_name'] = $store_result['location_name'] ? $store_result['location_name'] : '';
                        $survey['supervisor_name'] = $store_result['supervisor_name'] ? $store_result['supervisor_name'] : '';
                        $survey['route_code'] = $store_result['route_code'] ? $store_result['route_code'] : '';
                        $transaction = \Yii::$app->db->beginTransaction();
                        //考虑重推执行覆盖策略
                        $survey_result = Survey::saveSurveyCover($survey);
                        if (!$survey_result[0]) {
                            $transaction->rollBack();
                            \Yii::error('错误：' . $survey_result[1]);
                            $this->whiteErrorSeek($error_seek_path, $tmp);
                            usleep(self::SLEEP_TIME);
                            continue;
                        }
                        $survey_id = $survey_result[1];


                        $image['survey_code'] = $form->survey_id;
                        $image['tool_id'] = self::TOOL[$form->tool_id];
                        $image['img_type'] = !empty($images) ? Image::IMG_DISCRIMINATE : Image::IMG_QUESTION_COPY;
                        $image['scene_code'] = $form->scene_code;
                        $image['scene_id'] = $form->scene_id;
                        $image['scene_id_name'] = $form->scene_id_name;
                        $image_result = Image::saveImage($image);
                        if (!$image_result[0]) {
                            $transaction->rollBack();
                            \Yii::error('错误：' . $image_result[1]);
                            $this->whiteErrorSeek($error_seek_path, $tmp);
                            usleep(self::SLEEP_TIME);
                            continue;
                        }
                        $re = ImageUrl::findOne(['image_id' => $image_result[1]]);
                        if ($re) {
                            ImageUrl::deleteAll(['image_id' => $image_result[1]]);
                        }


                        $scene_exist = SurveyScene::findOne(['scene_id' => $form->scene_id, 'survey_id' => $survey_id]);
                        if (empty($scene_exist)) {
                            $survey_scene['survey_id'] = $form->survey_id;
                            $survey_scene['tool_id'] = self::TOOL[$form->tool_id];
                            $survey_scene['scene_code'] = $form->scene_code;
                            $survey_scene['scene_id'] = $form->scene_id;
                            $survey_scene['scene_id_name'] = $form->scene_id_name;
                            $survey_scene_result = SurveyScene::saveSurveyScene($survey_scene);
                            if (!$survey_scene_result[0]) {
                                $transaction->rollBack();
                                \Yii::error('错误：' . $survey_scene_result[1]);
                                $this->whiteErrorSeek($error_seek_path, $tmp);
                                usleep(self::SLEEP_TIME);
                                continue;
                            }
                        }


                        if (!empty($form->result)) {
                            $image_report['survey_id'] = $form->survey_id;
                            $image_report['photo_id'] = $image_result[1];
                            $image_report['origin_type'] = 1;
                            $image_report['url'] = $form->result_img;
                            $image_report['result'] = is_array($form->result) ? json_encode($form->result) : $form->result;
                            $image_report['scene_type'] = $form->scene_type;
                            $image_report['report_status'] = ImageReport::REPORT_STATUS_END;
                            $image_report_result = ImageReport::createImageReport($image_report);
                            if (!$image_report_result[0]) {
                                $transaction->rollBack();
                                \Yii::error('错误：' . $image_report_result[1]);
                                $this->whiteErrorSeek($error_seek_path, $tmp);
                                usleep(self::SLEEP_TIME);
                                continue;
                            }
                        }


                        $images = $form->photo_url;
                        $url = [];
                        $image_url_arr = [];
                        if (!empty($images)) {
                            foreach ($images as $k=>$v) {
                                if (isset($v['url'])) {
                                    $image_url = [];
                                    $image_url[] = $image_result[1];
                                    $image_url[] = $v['url'];
                                    $image_url[] = isset($key) ? $key . '_' . $k . '.jpg' : '';
                                    $url[] = $v['url'];
                                    $image_url_arr[] = $image_url;
                                }
                            }
                        }
                        if (!empty($image_url_arr)) {
                            $url_result = ImageUrl::saveImageUrl($image_url_arr);
                            if (!$url_result[0]) {
                                $transaction->rollBack();
                                \Yii::error('错误：' . $url_result[1]);
                                $this->whiteErrorSeek($error_seek_path, $tmp);
                                usleep(self::SLEEP_TIME);
                                continue;
                            }
                        }
                        $transaction->commit();


                        //入判断是否能送引擎计算队列
                        $data['survey_id'] = $body['survey_id'];
                        $projectId = \Yii::$app->params['project_id'];
                        $queue = \Yii::$app->remq->enqueue(\Yii::$app->params['queue']['calculation_task'] . $projectId, $data);
                        if (!$queue) {
                            \Yii::error('走访号：' . $body['survey_id'] . '发送规则引擎失败');
                            usleep(self::SLEEP_TIME);
                            continue;
                        }
                        \Yii::info('走访号：' . $data['survey_id'] . ' 图片ID：' . $image_result[1] . '已存储');
                        print_r('走访号：' . $data['survey_id'] . ' 图片ID：' . $image_result[1] . "已存储 \n");
                        usleep(self::SLEEP_TIME);
                    } else {
                        $err = $form->getErrors();
                        print_r($err);
                        \Yii::error($err .'详情：' .$tmp);
                        $this->whiteErrorSeek($error_seek_path, $tmp);
                        usleep(self::SLEEP_TIME);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                \Yii::error($e);
//                $this->whiteErrorSeek($error_seek_path, ftell($handle));
                usleep(self::SLEEP_TIME);
                continue;
            }
        }
        fclose($handle);
        $seek_handle = fopen($seek_path, 'w+');
        fwrite($seek_handle, 'done');
        fclose($seek_handle);
    }
}