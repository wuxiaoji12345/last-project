<?php
// 智付通

namespace api\service\tools;

use api\models\apiModels\standardCheckDataModel;
use api\models\apiModels\storeCheckDataModel;
use api\models\CheckType;
use api\models\Plan;
use api\models\ProtocolTemplate;
use api\models\RuleOutputInfo;
use api\models\share\Scene;
use api\models\SnapshotRuleOutputInfo;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Survey;
use api\models\SurveyStandard;
use api\models\Tools;
use yii\helpers\ArrayHelper;

class SFA extends Tools
{
    /**
     * 获取售点检查数据
     * @param $model storeCheckDataModel
     * @return array
     */
    public static function getStoreCheckData($model)
    {
        $plansAll = Plan::getPlanByStore($model->tool_id, $model->start_date, $model->end_date, $model->store_id);
        $standard_ids = array_column($plansAll, 'standard_id');
        $outputAll = RuleOutputInfo::findAllArray(['standard_id' => $standard_ids], '*');
        $outputAll = ArrayHelper::index($outputAll, 'node_index', 'standard_id');
        $standards = Standard::findAllArray(['id' => $standard_ids], ['id', 'protocol_id', 'standard_status', 'title', 'description', 'scenes'], 'id');
        //禁用状态的检查项目的详情要从快照表里取
        //输出项也要从快照表里取
        $disable_standards = [];
        if ($standards) {
            foreach ($standards as $k => $v) {
                if ($v['standard_status'] == Standard::STATUS_DISABLED) {
                    $disable_standards[] = $v['id'];
                    if (isset($outputAll[$k])) unset($outputAll[$k]);
                    unset($standards[$k]);
                }
            }
        }
        if ($disable_standards) {
            $disable_standard = SnapshotStandard::find()->where(['standard_id' => $disable_standards])
                ->select(['standard_id id', 'protocol_id', 'standard_status', 'title', 'description', 'scenes', 'max(id)'])
                ->groupBy('standard_id')
                ->indexBy('id')
                ->asArray()->all();
            $disable_output = SnapshotRuleOutputInfo::find()->select(['*']);
            $s_model = SnapshotRuleOutputInfo::find()
                ->where(['standard_id' => $disable_standards, 'status' => SnapshotRuleOutputInfo::DEL_STATUS_NORMAL])
                ->select(['max(snapshot_standard_id)']);
            $disable_output->where('snapshot_standard_id = (' . $s_model->createCommand()->getRawSql() . ')');
            $disable_output = $disable_output->asArray()->all();
            $disable_standard = SurveyStandard::findAllArray(['standard_id' => $disable_standards, 'is_standard_disable' => SurveyStandard::IS_STANDARD_DISABLE_YES],
                ['standard_id id', 'protocol_id', 'standard_status', 'title', 'description', 'scenes'], 'id');
            $disable_output = SnapshotRuleOutputInfo::findAllArray(['standard_id' => $disable_standards]);
            $disable_output = ArrayHelper::index($disable_output, 'node_index', 'standard_id');
            $outputAll = $outputAll + $disable_output;
            $standards = $standards + $disable_standard;
        }
        $protocol_ids = array_column($standards, 'protocol_id');
        $templates = ProtocolTemplate::findAllArray(['id' => $protocol_ids], ['id', 'excute_count', 'excute_cycle_list'], 'id');
        foreach ($plansAll as $k => &$item) {
            //如果是ine检查项目，要删除需要删除的场景，目前只有一个是招牌
            $is_ine = $item['check_type_id'] == CheckType::INE_AGREEMENTS['check_type_id'] ?: false;
            $scenes_type_id_all = [];
            $scenes_code_all = [];
            if ($item['contract_id'] == null) {
                $item['contract_id'] = '';
                $item['contract_name'] = '';
            }
            $rule_output = $outputAll[$item['standard_id']]??[];
            //禁用状态的检查项目的详情要从快照表里取
            $item['title'] = isset($standards[$item['standard_id']]['title']) ? $standards[$item['standard_id']]['title'] : '';
            $item['description'] = isset($standards[$item['standard_id']]['description']) ? $standards[$item['standard_id']]['description'] : '';
            $item['sub_activity_list'] = [];
//            foreach ($item['standard']['subActivity'] as $sub) {
            $standard_scenes = isset($standards[$item['standard_id']]['scenes']) ? json_decode($standards[$item['standard_id']]['scenes'], true) : [];
            if (empty($standard_scenes)) {
                unset($plansAll[$k]);
                continue;
            }
            $hasActivity = false;
            $scenes = json_decode($standards[$item['standard_id']]['scenes'], true);
            $scenes = ArrayHelper::index($scenes, 'sub_activity_id');
            foreach ($standard_scenes as $sub) {
                $one_sub = [];
                $tmp_output = [];
                if (isset($scenes[$sub['sub_activity_id']]) && isset($scenes[$sub['sub_activity_id']]['outputList'])) {
                    $tmp_scene = $scenes[$sub['sub_activity_id']];
                    $tmp_output = $tmp_scene['outputList'];
                }
                if (isset($scenes[$sub['sub_activity_id']])) {
                    $tmp_scene = $scenes[$sub['sub_activity_id']];
                    $one_sub['activation_id'] = $tmp_scene['activationID'] ?? '';
                    $one_sub['activation_name'] = $tmp_scene['activationName'] ?? $sub['sub_activity_name'];
                } else {
                    $item['contract_id'] = '';
                    $item['contract_name'] = '';
                    $one_sub['activation_id'] = $sub['sub_activity_id'];
                    $one_sub['activation_name'] = $sub['sub_activity_name'];
                }

                $one_sub['activation_id'] = (string)$one_sub['activation_id'];
                $one_sub['sub_activity_id'] = (string)$sub['sub_activity_id'];
                $one_sub['sub_activity_describe'] = $sub['describe'];
                $scenes_code = $sub['scenes_code'];
                $scenes_type_id = $sub['scenes_type_id'];
                $scenes_code_all = array_merge($scenes_code_all, $scenes_code);
                $scenes_type_id_all = array_merge($scenes_type_id_all, $scenes_type_id);
                $one_sub['scenes'] = Scene::getSmallScene(['scenes_type_id' => $scenes_type_id, 'scenes_code' => $scenes_code],
                    ['id', 'scene_type', 'scene_code', 'scene_code_name', 'scene_maxcount', 'scene_need_recognition', 'sort'], $is_ine);
                $one_sub['image'] = $sub['image'];
                $one_sub['output_list'] = [];
                foreach ($tmp_output as $tmp_output_one) {
                    if (isset($rule_output[$tmp_output_one['node_index']])) {
                        $node_index = $rule_output[$tmp_output_one['node_index']];
                        $tmp_node = ['id' => $node_index['id'], 'node_name' => $node_index['node_name']];
                        $one_sub['output_list'][] = $tmp_node;
                    }
                }
                $item['sub_activity_list'][] = $one_sub;
                $hasActivity = isset($sub['outputList']) && !empty($sub['outputList']);
            }
            $item['all_scenes'] = Scene::getSmallScene(['scenes_type_id' => $scenes_type_id_all, 'scenes_code' => $scenes_code_all], ['id', 'scene_type', 'scene_code', 'scene_code_name', 'scene_maxcount', 'scene_need_recognition', 'sort'],$is_ine);
            $item['plan_time'] = ['start_time' => $item['start_time'], 'end_time' => $item['end_time']];
            // 活动整改次数
            @$template = $templates[$standards[$item['standard_id']]['protocol_id']];
            $cycle_time = json_decode($template['excute_cycle_list'], true);

            $time = date('Y-m-d H:i:s');
            $short_cycle = json_decode($item['short_cycle'], true) ?:
                [['start_time' => substr($item['start_time'], 0, 10), 'end_time' => substr($item['end_time'], 0, 10)]];
            $cost_count = Survey::findCountSurvey($model->store_id, $item['standard_id'], $model->tool_id, $cycle_time, $time, $short_cycle);
            unset($item['start_time']);
            unset($item['end_time']);
            // 第3个出参为空档期标识 true 为正常检查， false 为空档期
            if ($template != null) {
                // 整改拍照次数
                $item['re_time'] = $item['re_photo_time'] + $template['excute_count'];
                if ($cost_count[2]) {
                    $item['left_time'] = $item['re_time'] - $cost_count[0];
                } else {
                    $item['left_time'] = 0;
                }

            } else {
                $item['re_time'] = $item['re_photo_time'] + 1;
                $item['left_time'] = $item['re_time'] - $cost_count[0];
                if ($cost_count[2]) {
                    $item['left_time'] = $item['re_time'] - $cost_count[0];
                } else {
                    $item['left_time'] = 0;
                }
            }
            // scenes outputList是否为空或字段不存在 返回false
            $item['rephoto_limit'] = $hasActivity;
            $item['left_time'] = $item['left_time'] < 0 ? 0 : $item['left_time'];
            $item['activation_list'] = json_decode($item['activation_list'], true);
            $item['short_cycle'] = $cost_count[3];
            unset($item['scenes']);
            unset($item['standard']);
            unset($item['activation_list']);
        }
        return array_values($plansAll);
    }

    /**
     * @param $contract_ids array|string ZFT协议ID
     * @return array
     */
    public static function getStandardCheckData($contract_ids)
    {
        // 先找到ZFT协议
        $templates = ProtocolTemplate::findAllArray(['contract_id' => $contract_ids]);
        if (!empty($templates)) {
            $protocol_ids = array_column($templates, 'id');
            $standards = Standard::findAllArray(['protocol_id' => $protocol_ids, 'standard_status' => Standard::STATUS_AVAILABLE], ['id', 'protocol_id', 'scenes'], 'protocol_id');
            $standard_ids = array_column($standards, 'id');
            $outputAll = RuleOutputInfo::findAllArray(['standard_id' => $standard_ids], '*');
            $outputAll = ArrayHelper::index($outputAll, 'node_index', 'standard_id');
            $subActivityAll = SubActivity::findAllArray(['standard_id' => $standard_ids], ['*']);
            $subActivityAll = ArrayHelper::index($subActivityAll, 'id', 'standard_id');
            $result = [];
            foreach ($templates as $template) {
                if (!isset($standards[$template['id']])) {
                    continue;
                }
                $scenes_type_id_all = [];
                $scenes_code_all = [];
                $item = [
                    'contract_id' => $template['contract_id'],
                    'contract_name' => $template['contract_name'],
                ];
                $item['sub_activity_list'] = [];
                $standard = $standards[$template['id']];
                $rule_output = $outputAll[$standard['id']];
                $subActivity = $subActivityAll[$standard['id']];

                $scenes = json_decode($standard['scenes'], true);
                $scenes = ArrayHelper::index($scenes, 'sub_activity_id');

                foreach ($subActivity as $sub) {
                    $tmp_scene = $scenes[$sub['id']];
                    $tmp_output = $tmp_scene['outputList'];

//                    $one_sub['sub_activity_id'] = $sub['id'];
                    $one_sub['activation_id'] = $tmp_scene['activationID'];
                    $one_sub['activation_name'] = $tmp_scene['activationName'];
                    $one_sub['sub_activity_describe'] = $sub['describe'];
//                    $scenes_code = json_decode($sub['scenes_code'], true);
//                    $scenes_type_id = json_decode($sub['scenes_type_id'], true);
//                    $scenes_code_all = array_merge($scenes_code_all, $scenes_code);
//                    $scenes_type_id_all = array_merge($scenes_type_id_all, $scenes_type_id);
//                    $one_sub['scenes'] = Scene::getSmallScene(['scenes_type_id' => $scenes_type_id, 'scenes_code' => $scenes_code],
//                        ['id', 'scene_type', 'scene_code', 'scene_code_name']);
                    $one_sub['image'] = json_decode($sub['image'], true);
                    $one_sub['output_list'] = [];
                    foreach ($tmp_output as $tmp_output_one) {
                        if (isset($rule_output[$tmp_output_one['node_index']])) {
                            $node_index = $rule_output[$tmp_output_one['node_index']];
                            $tmp_node = ['id' => $node_index['id'], 'node_name' => $node_index['node_name']];
                            $one_sub['output_list'][] = $tmp_node;
                        }
                    }
                    $item['sub_activity_list'][] = $one_sub;
                }
                $result[] = $item;
            }
            return $result;
        } else {
            return [];
        }
    }
}