<?php
// SEA

namespace api\service\tools;

use api\models\apiModels\storeCheckDataModel;
use api\models\ProtocolTemplate;
use api\models\RuleOutputInfo;
use api\models\share\Store;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Tools;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

class CP extends Tools
{
    /**
     * @param $company_code
     * @return array
     */
    public static function getStandardCheckData($company_code)
    {
        // 先找到ZFT协议
//        $protocol_ids = array_column($templates, 'id');
        $query = Standard::find()
            ->select([
                new Expression(Standard::tableName() . '.id'),
                new Expression(Standard::tableName() . '.company_code'),
                'title', 'protocol_id', 'description', 'scenes', 'standard_status', 'bu_code'])
            ->joinWith('protocol')->where([
                'and',
                ['>', 'protocol_id', 0],
                ['=', new Expression(Standard::tableName() . '.company_code'), $company_code],
                ['in', 'standard_status', [Standard::STATUS_AVAILABLE, Standard::STATUS_DISABLED]],
                ['>=', 'sign_to_date', date('Ymd')]
            ])->indexBy('protocol_id');

        $standards = $query->asArray()->all();
        $standard_ids = array_column($standards, 'id');
        $outputAll = RuleOutputInfo::findAllArray(['standard_id' => $standard_ids], '*');
        $outputAll = ArrayHelper::index($outputAll, 'node_index', 'standard_id');
        $subActivityAll = SubActivity::findAllArray(['standard_id' => $standard_ids], ['*']);
        $subActivityAll = ArrayHelper::index($subActivityAll, 'id', 'standard_id');

        $protocol_ids = array_column($standards, 'protocol_id');
        $templates = ProtocolTemplate::findAllArray(['id' => $protocol_ids]);

        $result = [];
        foreach ($templates as $template) {
            if (!isset($standards[$template['id']])) {
                continue;
            }
            $item = [
                'contract_id' => $template['contract_id'],
                'contract_name' => $template['contract_name'],
            ];
            $standard = $standards[$template['id']];
            $item['title'] = $standard['title'];
            $item['description'] = $standard['description'];
            $item['standard_id'] = $standard['id'];
            $item['company_code'] = $standard['company_code'];
            $item['bu_code'] = $standard['bu_code'];
            $item['standard_status'] = $standard['standard_status'];
            $item['sign_date'] = [
                'sign_from_date' => $template['sign_from_date'],
                'sign_to_date' => $template['sign_to_date'],
            ];
            // 协议执行时间
            $item['plan_time'] = [
                'start_time' => $template['excute_from_date'],
                'end_time' => $template['excute_to_date'],
            ];
            $item['excute_cycle_list'] = json_decode($template['excute_cycle_list'], true);

            $item['sub_activity_list'] = [];
            $rule_output = isset($outputAll[$standard['id']]) ? $outputAll[$standard['id']] : [];
            $subActivity = isset($subActivityAll[$standard['id']]) ? $subActivityAll[$standard['id']] : [];

            $scenes = json_decode($standard['scenes'], true);
            $scenes = ArrayHelper::index($scenes, 'sub_activity_id');

            foreach ($subActivity as $sub) {
                if(!isset($scenes[$sub['id']])){
                    continue;
                }
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
    }
}