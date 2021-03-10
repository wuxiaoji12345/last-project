<?php

namespace api\models;

use Exception;
use Yii;
use yii\base\BaseObject;

class CopyStandardLogic extends BaseObject
{
    //校验生动化和问卷，并且推送目标环境大中台
    public static function pushToTargetCheck($ruleCode, $title)
    {
        $post = Yii::$app->request->bodyParams;
        $tmp = explode('_', Yii::$app->params['project_id']);
        $env = strtolower(end($tmp));
        $url = Yii::$app->params['copy_standard_url'][$env]['get_engine_used_data'];
        $postData = ['rule_code' => $ruleCode];
        $token[] = 'token:' . $post['token'];
        $response = \Helper::curlQueryLog($url, $postData, true, 300, $token);
        if ($response['code'] != 200) return [false, 'get_engine_used_data error:'.$response['message']];
        $rule = $response['data']['rule'];
        $subActivityIds = $engineSubActivityIds = [];
        $quesIds = $engineQuesIds = [];
        $sceneTypes = $engineSceneTypes = [];
        $sceneCodes = $engineSceneCodes = [];
        //构造引擎数据
        foreach ($rule['sceneGroups'] as $sceneGroup) {
            $engineSubActivityIds[] = $sceneGroup['sub_activity_id'];
            foreach ($sceneGroup['sceneType'] as $sceneType) {
                $engineSceneTypes[] = $sceneType['scene_type'];
            }
            foreach ($sceneGroup['sceneCode'] as $sceneCode) {
                $engineSceneCodes[] = $sceneCode['scene_code'];
            }
            foreach ($sceneGroup['questionnaires'] as $ques) {
                $engineQuesIds[] = $ques['questionnaire_id'];
            }
        }
        //构造大中台本地数据
        $standard = Standard::find()
            ->where(['engine_rule_code' => $ruleCode])
            ->asArray()
            ->one(null, false);
        $standard['subActivity'] = SubActivity::find()
            ->where(['standard_id' => $standard['id']])
            ->asArray()->all(null, false);
        $standard['output'] = RuleOutputInfo::find()
            ->where(['standard_id' => $standard['id']])
            ->asArray()->all(null, false);
        $standard['title'] = !empty($title) ? $title : $standard['title'];
        $scenes = json_decode($standard['scenes'], true);
        if (!$scenes) return [false, 'standard的scenes为空'];
        //生动化
        foreach ($standard['subActivity'] as $subActivity) {
            $subActivityIds[] = $subActivity['id'];
            $qIr = json_decode($subActivity['question_manual_ir'], true);
            $q = json_decode($subActivity['question_manual'], true);
            if ($qIr) {
                foreach ($qIr as $val) {
                    $quesIds[] = $val['id'];
                }
            }
            if ($q) {
                foreach ($q as $val) {
                    $quesIds[] = $val['id'];
                }
            }
         }
        foreach ($scenes as $scene) {
             //问卷
            foreach ($scene['question_manual_ir'] as $qIr) {
                $quesIds[] = $qIr['id'];
            }
            foreach ($scene['question_manual'] as $q) {
                $quesIds[] = $q['id'];
            }
            //场景
            foreach ($scene['scenes_type_id'] as $sceneType) {
                $sceneTypes[] = $sceneType;
            }
            foreach ($scene['scenes_code'] as $sceneCode) {
                $sceneCodes[] = $sceneCode;
            }
        }
        //去重
        $quesIds  = array_unique($quesIds);
        //引擎的生动化问卷必须是大中台的子集
        $subActivityDiff = array_diff($engineSubActivityIds, $subActivityIds);
        if (count($subActivityDiff) > 0) {
            $text = implode(',', $subActivityDiff);
            return [false, "复制项目存在已删除的生动化{$text}，无法复制，请修改源项目后复制"];
        }
        $QuesDiff = array_diff($engineQuesIds, $quesIds);
        if (count($QuesDiff) > 0) {
            $text = implode(',', $QuesDiff);
            return [false, "复制项目存在已删除的问卷{$text}，无法复制，请修改源项目后复制"];
        }
        $sceneTypeDiff = array_diff($engineSceneTypes, $sceneTypes);
        if (count($sceneTypeDiff) > 0) {
            $text = implode(',', $sceneTypeDiff);
            return [false, "复制项目存在已删除的主场景{$text}，无法复制，请修改源项目后复制"];
        }
        $sceneCodeDiff = array_diff($engineSceneCodes, $sceneCodes);
        if (count($sceneCodeDiff) > 0) {
            $text = implode(',', $sceneCodeDiff);
            return [false, "复制项目存在已删除的子场景{$text}，无法复制，请修改源项目后复制"];
        }
        //构建问卷和选项
        $question = Question::find()
            ->with('options')
            ->where(['id' => $quesIds])
            ->asArray()->all(null, false);
        $data['standard'] = $standard;
        $data['sub_activity'] = $standard['subActivity'];
        $data['question'] = $question;
        $data['output'] = $standard['output'];
        $data['token'] = $post['token'];
        $data['rule'] = $rule;
        //释放变量 减少内存开销
        unset($data['standard']['subActivity']);
        unset($data['standard']['output']);
        //开始推送大中台数据、引擎数据到目标环境API
        $url = Yii::$app->params['copy_standard_url'][$env]['push_data_to_target_check'];
        $response = \Helper::curlQueryLog($url, $data, true, 300, $token);
        if ($response['code'] != 200) return [false, $response['message']];
        return [true, $response['data']];
    }
    

}
