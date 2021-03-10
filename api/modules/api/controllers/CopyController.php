<?php
//复制检查项目控制器
namespace api\modules\api\controllers;

use api\models\CopyStandardLogic;
use api\models\User;
use api\models\Standard;
use api\models\Question;
use api\models\QuestionOption;
use api\models\SubActivity;
use api\models\RuleOutputInfo;
use Codeception\Example;
use Yii;
use Exception;

class CopyController extends BaseApi
{
    //接受大中台源数据和引擎源数据
    public function actionReceiveData()
    {
        $post = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($post, ['standard', 'sub_activity', 'question', 'rule', 'output'])) {
            return $this->error();
        }
        //id映射关系数组
        $standardIdMap = $subActIdMap = $quesIdMap = $quesOpIdMap = [];
        try { 
            //开启事务
            $transaction = Yii::$app->getDb()->beginTransaction();
            //插入问卷和选项
            foreach ($post['question'] as $question) {
                //判断标准 BU+问卷题型+问卷维度+场景类型+问卷名称
                $existedQuestion = Question::find()
                    ->where(['bu_code' => $question['bu_code']])
                    ->andWhere(['question_type' => $question['question_type']])
                    ->andWhere(['type' => $question['type']])
                    ->andWhere(['scene_type_id' => $question['scene_type_id']])
                    ->andWhere(['title' => $question['title']])->asArray()
                    ->one(null, false);
                if ($existedQuestion) {
                    //已经存在的问卷，id和原来一致
                    $quesIdMap[$question['id']] = $existedQuestion['id'];
                    if (empty($question['options']))  continue;
                    //选项也和原来一致
                    foreach ($question['options'] as $option) {
                        $quesOpIdMap[$option['id']] = $option['id'];
                    }
                } else {
                    //插入新问卷
                    $newQuestion = new Question();
                    $insertData = $question;
                    unset($insertData['id']);
                    $newQuestion->setAttributes($insertData, false);
                    if (!$newQuestion->save()) {
                        throw new Exception($newQuestion->getErrStr());
                    }
                    $quesIdMap[$question['id']] = $newQuestion->id;
                    //新问卷直接插入选项
                    if (empty($question['options']))  continue;
                    foreach ($question['options'] as $option) {
                        $insertData = $option;
                        unset($insertData['id']);
                        $newOption = new QuestionOption();
                        $newOption->setAttributes($insertData, false);
                        if (!$newOption->save()) {
                            throw new Exception($newOption->getErrStr());
                        }
                        $quesOpIdMap[$option['id']] = $newOption->id;
                    }
                }
            }
            //插入生动化 先放入一个旧的standard_id，后面要更新成新的
            foreach ($post['sub_activity'] as $subActivity) {
                $insertData = $subActivity;
                //去除转义
                $insertData['scenes_type_id'] = stripslashes($insertData['scenes_type_id']);
                $insertData['scenes_code'] = stripslashes($insertData['scenes_code']);
                $qIrs = json_decode($subActivity['question_manual_ir'], true);
                $qs = json_decode($subActivity['question_manual'], true);
                foreach ($qIrs as &$qIr) {
                    if (!isset($quesIdMap[$qIr['id']])) {
                        throw new Exception("IR问卷{$qIr['id']}:{$qIr['title']}没有映射id");
                    }
                    $qIr['id'] = $quesIdMap[$qIr['id']];
                }
                foreach ($qs as &$q) {
                    if (!isset($quesIdMap[$q['id']])) {
                        throw new Exception("非IR问卷{$q['id']}:{$q['title']}没有映射id");
                    }
                    $q['id'] = $quesIdMap[$q['id']];
                }
                $insertData['question_manual_ir'] = json_encode($qIrs);
                $insertData['question_manual'] = json_encode($qs);
                unset($insertData['id']);
                $newSubActivity = new SubActivity();
                $newSubActivity->setAttributes($insertData, false);
                if (!$newSubActivity->save()) {
                    throw new Exception($newSubActivity->getErrStr());
                }
                $subActIdMap[$subActivity['id']] = $newSubActivity->id;
            }
            //插入检查项目
            $insertData = $post['standard'];
            $insertData['origin_id'] = $post['standard']['id'];
            //处理scenes
            $scenes = json_decode($insertData['scenes'], true);
            foreach ($scenes as &$scene) {
                foreach ($scene['question_manual_ir'] as $key => $qIr) {
                    if (!isset($quesIdMap[$qIr['id']])) {
                        throw new Exception("question_manual_ir的IR问卷{$qIr['id']}:{$qIr['title']}没有映射id");
                    }
                    $scene['question_manual_ir'][$key]['id'] = $quesIdMap[$qIr['id']];
                }
                foreach ($scene['question_manual'] as $key => $q) {
                    if (!isset($quesIdMap[$q['id']])) {
                        throw new Exception("question_manual的IR问卷{$q['id']}:{$q['title']}没有映射id");
                    }
                    $scene['question_manual'][$key]['id'] = $quesIdMap[$q['id']];
                }
                foreach ($scene['multipleSelection'] as $key => $selects) {
                    if (empty($selects)) continue;
                    foreach ($selects as $k => $select) {
                        if (!isset($quesIdMap[$select['id']])) {
                            throw new Exception("multipleSelection的IR问卷{$select['id']}:{$select['title']}没有映射id");
                        }
                        $scene['multipleSelection'][$key][$k]['id'] = $quesIdMap[$select['id']];
                    }
                }
                $scene['sub_activity_id'] = $subActIdMap[$scene['sub_activity_id']];
            }
            $insertData['scenes'] = json_encode($scenes);
            //处理QC
            if (!empty($insertData['need_qc_data'])) {
                $needQcData = json_decode($insertData['need_qc_data']);
                if (!empty($needQcData)) {
                    foreach ($needQcData as $subActivityId => $index) {
                        if (!isset($subActIdMap[$subActivityId])) {
                            throw new Exception("QC数据生动化:{$subActivityId}没有映射id");
                        }
                        $newQcData[$subActIdMap[$subActivityId]] = $index;
                    }
                    $insertData['need_qc_data'] = json_encode($newQcData);
                }
            }
            unset($insertData['id']);
            unset($insertData['engine_rule_code']);
            $newStandard = new Standard();
            $newStandard->setAttributes($insertData, false);
            if (!$newStandard->save()) {
                throw new Exception($newStandard->getErrStr());
            }
            $standardIdMap[$post['standard']['id']] = $newStandard->id;
            //把之前插入的生动化里检查项目ID全部替换
            $newSubactIds = array_values($subActIdMap);
            SubActivity::updateAll(['standard_id' => $newStandard->id], ['id' => $newSubactIds]);
            //插入输出项
            foreach ($post['output'] as $output) {
                $insertData = $output;
                unset($insertData['id']);
                $insertData['standard_id'] = $newStandard->id;
                //某些输出项（售点）没有生动化就默认0
                $insertData['sub_activity_id'] = $subActIdMap[$output['sub_activity_id']] ?? 0;
                $newOutput = new RuleOutputInfo();
                $newOutput->setAttributes($insertData, false);
                if (!$newOutput->save()) {
                    throw new Exception($newOutput->getErrStr());
                }
            }
            //把ID映射关系推送给引擎源数据
            $data['standardIdMap'] = $standardIdMap;
            $data['subActIdMap'] = $subActIdMap;
            $data['quesIdMap'] = $quesIdMap;
            $data['quesOpIdMap'] = $quesOpIdMap;
            $data['rule'] = $post['rule'];
            $tmp = explode('_', Yii::$app->params['project_id']);
            $env = strtolower(end($tmp));
            $url = Yii::$app->params['copy_standard_url'][$env]['push_data_to_target_engine'];
            $token[] = 'token:' . $post['token'];
            $response = \Helper::curlQueryLog($url, $data, true, 300, $token);
            if ($response['code'] != 200) {
                throw new Exception($response['msg'] ?? $response['message']);
            };
            //引擎插入成功  回写rule_code
            $newStandard->engine_rule_code = $response['data'];
            if (!$newStandard->save()) {
                throw new Exception($newStandard->getErrStr());
            }
            $transaction->commit();
            return $this->success($newStandard->engine_rule_code);
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error($e->getMessage());
        } 
       
    }

}