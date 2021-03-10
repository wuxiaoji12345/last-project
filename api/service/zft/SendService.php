<?php


namespace api\service\zft;


use api\models\ActivationSendZftInfo;
use api\models\EngineResult;
use api\models\Plan;
use api\models\ProtocolStore;
use api\models\ProtocolTemplate;
use api\models\RuleOutputInfo;
use api\models\Standard;
use api\models\SubActivity;
use api\models\Survey;
use api\models\Tools;
use api\service\plan\PlanService;
use common\libs\ding\Ding;
use common\libs\file_log\LOG;

class SendService
{
    const OUTPUT_SUCCESS = 1; //输出状态成功
    const OUTPUT_FAIL = 0; //输出状态失败

    /**
     * 新的拼凑条件发送给CP方法
     * @param $data
     * @return mixed|string
     */
    public static function newSendResultToCP($data)
    {
        if (isset($data['is_qc']) && $data['is_qc']) {
            $data['context'] = [
                'standard_id' => $data['standard_id'],
                'store_id' => $data['store_id'],
                'tool_id' => $data['tool_id'],
                'survey_code' => $data['survey_code']
            ];
        }
        $alias = '';
        $join = [];
        $select = ['id', 'node_index', 'node_name', 'sub_activity_id', 'is_vividness'];
        $where = ['standard_id' => $data['context']['standard_id']];
        $output_info = RuleOutputInfo::findJoin($alias, $join, $select, $where, true, true, '', 'node_index');

        $alias = 's';
        $join = [
            ['type' => 'LEFT JOIN',
                'table' => ProtocolTemplate::tableName() . ' p',
                'on' => 'p.id = s.protocol_id'],
            ['type' => 'LEFT JOIN',
                'table' => Plan::tableName() . ' pl',
                'on' => 'pl.standard_id = s.id']
        ];
        $select = ['p.excute_cycle_list', 'p.contract_id', 's.scenes', 's.protocol_id', 'p.excute_count', 'pl.re_photo_time'];
        $where = ['s.id' => $data['context']['standard_id'], 'pl.status' => Plan::PLAN_STATUS_ENABLE];
        $standard_result = Standard::findJoin($alias, $join, $select, $where, true, false);
        $scenes = json_decode($standard_result['scenes'], true);
        $cycle_list = $standard_result['excute_cycle_list'] ? json_decode($standard_result['excute_cycle_list'], true) : [];
        $time = date('Y-m-d H:i:s');
        $survey_result = Survey::findCountSurvey($data['context']['store_id'], $data['context']['standard_id'], $data['context']['tool_id'], $cycle_list, $time);
        //有protocol_id为协议类检查项目，没有为非协议类
        if ($standard_result['protocol_id']) {
            $left_time = $standard_result['excute_count'] + $standard_result['re_photo_time'];
            $left_time = $left_time - $survey_result[0];
            $left_time = ($left_time >= 0) ? $left_time : 0;
            $engine_result = [
                'result' => $data['output_list'],
                'surveyId' => $data['context']['survey_code'],
                'standardId' => $data['context']['standard_id'],
            ];
            $standard_status = '';
            foreach ($scenes as $item1) {
                $result = [];
                $result['actId'] = $item1['activationID'];
//                    $result['activation_name'] = $item1['activationName'];
                $result['subActivityId'] = $item1['sub_activity_id'];
                $result['outputList'] = [];
                //生动化结果
                $lively_result = '';
                foreach ($item1['outputList'] as $item2) {
                    if (isset($output_info[$item2['node_index']])) {
                        foreach ($engine_result['result'] as $item3) {
                            //如果node_index相同，说明就是生动化项绑定的输出项
                            if ($item2['node_index'] == $item3['node_index']) {
                                $lively_result = ($lively_result === '') ? self::OUTPUT_SUCCESS : $lively_result;
                                $standard_status = ($standard_status === '') ? self::OUTPUT_SUCCESS : $standard_status;
                                $output_result = [
                                    'id' => isset($item2['id']) ? $item2['id'] : $output_info[$item2['node_index']]['id'],
                                    'nodeName' => $item2['node_name'],
                                    'output' => (int)$item3['output']
                                ];
                                $result['outputList'][] = $output_result;
                                $lively_result = $item3['output'] ? $lively_result : self::OUTPUT_FAIL;
                                $standard_status = $item3['output'] ? $standard_status : self::OUTPUT_FAIL;
                            }
                        }
                    }
                }
                $result['output'] = ($lively_result === '') ? self::OUTPUT_FAIL : $lively_result;
                $engine_result['checkResult'][] = $result;
            }
            $engine_result['fixLeft'] = $left_time;
            $engine_result['storeId'] = $data['context']['store_id'];
            $engine_result['contractId'] = $standard_result['contract_id'];
            $engine_result['output'] = ($standard_status === '') ? self::OUTPUT_FAIL : $standard_status;
            unset($engine_result['result']);
        } else {
            $engine_result = [
                'result' => $data['output_list'],
                'surveyId' => $data['context']['survey_code'],
                'standardId' => $data['context']['standard_id'],
            ];
            //非协议类暂定没有重拍次数
            $engine_result['fixLeft'] = 0;
            $engine_result['storeId'] = $data['context']['store_id'];
            $engine_result['contractId'] = null;
            foreach ($scenes as $item1) {
                $result = [];
                $result['actId'] = null;
//                    $result['activation_name'] = null;
                $result['subActivityId'] = $item1['sub_activity_id'];
                $engine_result['checkResult'][] = $result;
            }
            foreach ($engine_result['result'] as $item2) {
                if (isset($output_info[$item2['node_index']]) && ($output_info[$item2['node_index']]['is_vividness'] == 1)) {
                    $output_result = [
                        'id' => $output_info[$item2['node_index']]['id'],
                        'nodeName' => $item2['node_name'],
                        'output' => (int)$item2['output']
                    ];
                    $tmp[$output_info[$item2['node_index']]['sub_activity_id']][] = $output_result;
                }
            }
            $standard_status = '';
            foreach ($engine_result['checkResult'] as &$item3) {
                $standard_status = ($standard_status === '') ? self::OUTPUT_SUCCESS : $standard_status;
                $item3['outputList'] = isset($tmp[$item3['subActivityId']]) ? $tmp[$item3['subActivityId']] : [];
                $item3['output'] = $item3['outputList'] ? self::OUTPUT_SUCCESS : self::OUTPUT_FAIL;
                foreach ($item3['outputList'] as $item4) {
                    $item3['output'] = $item4['output'] ? $item3['output'] : self::OUTPUT_FAIL;
                    $standard_status = $item4['output'] ? $standard_status : self::OUTPUT_FAIL;
                }
            }
            $engine_result['output'] = ($standard_status === '') ? self::OUTPUT_FAIL : $standard_status;
            unset($engine_result['result']);
        }
//            $engine_result['tool_id'] = $data['context']['tool_id'];
        $engine_result['storeId'] = $data['context']['store_id'];
        $url = \Yii::$app->params['new_cp_url'];
        \Helper::curlQueryLog($url, $engine_result, true);

        //更新发送qc的状态
        $engine_model = EngineResult::findOne(['standard_id' => $data['context']['standard_id'], 'survey_code' => $data['context']['survey_code']]);
        if (isset($data['is_qc'])) {
            $engine_model->send_cp_status = ($engine_model->send_cp_status == EngineResult::SEND_CP_STATUS_ONCE_YES) ?
                EngineResult::SEND_CP_STATUS_ALL_YES : EngineResult::SEND_CP_STATUS_SECOND_YES;
        } else {
            $engine_model->send_cp_status = EngineResult::SEND_CP_STATUS_ONCE_YES;
        }
        if (!$engine_model->save()) LOG::log('发送qc状态保存失败');

        return true;
    }


    /**
     * 拼凑条件发送给CP
     * @param $data
     * @return mixed
     */
    public static function sendResultToCP($data)
    {
        if (isset($data['is_qc']) && $data['is_qc']) {
            $data['context'] = [
                'standard_id' => $data['standard_id'],
                'store_id' => $data['store_id'],
                'tool_id' => $data['tool_id'],
                'survey_code' => $data['survey_code']
            ];
        }
        $url = \Yii::$app->params['cp_url'];
        $head = false;
        $rule_output_node_id = RuleOutputInfo::findAllArray(['standard_id' => $data['context']['standard_id'],
            'is_vividness' => RuleOutputInfo::IS_VIVIDNESS_YES, 'sub_activity_id' => $data['context']['sub_activity_id']], ['id', 'node_index']);
        foreach ($rule_output_node_id as $item) {
            foreach ($data['output_list'] as $v) {
                if ($item['node_index'] == $v['node_index']) {
                    $send_data['result'][] = [
                        'node_index' => $v['node_index'],
                        'node_name' => $v['node_name'],
                        'output' => $v['output']
                    ];
                }
            }
        }
        $sub_activity = SubActivity::findOneArray(['id' => $data['context']['sub_activity_id']], ['scenes_type_id', 'scenes_code']);
        $send_data['survey_id'] = $data['context']['survey_code'];
        $send_data['tool_id'] = $data['context']['tool_id'];
        $send_data['sub_activity_id'] = $data['context']['sub_activity_id'];
        $send_data['plan_id'] = $data['context']['plan_id'];
        $send_data['result_time'] = $data['result_time'];
        $send_data['scene_type'] = json_decode($sub_activity['scenes_type_id'], true);
        $send_data['scene_code'] = json_decode($sub_activity['scenes_code'], true);
        $send_data = json_encode($send_data);
        return self::curlQuery($url, $send_data, $head);
    }

    public static function curlQuery($url, $data, $head = false)
    {
        LOG::log($url);
        LOG::log($data);
        $headers = array(
            "Content-Type: application/json"
        );
        $ch = curl_init();
        $timeout = 300;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, " CURL Example beta");
        if ($head) {
            $headers = array_merge($headers, $head);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response == null) {
            $ding = Ding::getInstance();
            $ding->sendTxt("推送返回为null \nurl:" . $url . "\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        LOG::log($response);
        return $response;
    }

    /**
     * 拼凑条件发送给ZFT
     * @param $data
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function sendResultToZft($data)
    {
        $data['context'] = [
            'standard_id' => $data['standard_id'],
            'plan_id' => $data['plan_id'],
            'store_id' => $data['store_id'],
            'tool_id' => $data['tool_id'],
            'survey_code' => $data['survey_code'],
            'survey_time' => $data['survey_time'],
            'protocol_id' => $data['protocol_id'],
            'rectification_model' => $data['rectification_model'],
            'company_code' => $data['company_code'],
            'examiner_id' => $data['examiner_id'],
        ];
        return self::sendZFT($data);
    }

    /**
     * 发送ZFT过程
     * @param $data
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function sendZFT($data)
    {
        $url1 = \Yii::$app->params['zft_url'] . '/api/submitExecuteResult';
        $cycle_id = 0;
        $protocol = ProtocolTemplate::findOneArray(['id' => $data['context']['protocol_id'], 'protocol_status' => ProtocolTemplate::PROTOCOL_STATUS_ENABLE]);
        $outlet_contract_id = ProtocolStore::findOneArray(['contract_id' => $protocol['contract_id'],
            'outlet_id' => $data['context']['store_id'], 'store_status' => ProtocolStore::PROTOCOL_STATUS_ENABLE]);
        $standard = Standard::findOne(['id' => $data['context']['standard_id']]);
        if (!$standard) {
            return false;
        }
        $alias = 'e';
        $select = ['e.id'];
        $contract_id = $outlet_contract_id ? $outlet_contract_id['outlet_contract_id'] : 0;
        $scenes = json_decode($standard->scenes, true);
        $activation_info = [];
        $all_success = '';
        foreach ($scenes as $v) {
            $tmp['standard_id'] = $data['context']['standard_id'];
            $tmp['survey_code'] = $data['context']['survey_code'];
            $tmp['activation_id'] = $v['activationID'];
            $tmp['activation_name'] = $v['activationName'];
            $tmp['store_id'] = $data['context']['store_id'];
            $tmp['output_list'] = isset($v['outputList']) ? json_encode($v['outputList']) : '';
            $tmp['protocol_id'] = $data['context']['protocol_id'];
            $tmp['is_standard'] = $v['isStandard'];
            $tmp['outlet_contract_id'] = $contract_id;
            $execute_result = '';
            foreach ($v['outputList'] as $item) {
                foreach ($data['output_list'] as $item2) {
                    if ($item['node_index'] == $item2['node_index']) {
                        $execute_result = ($execute_result === '') ? 1 : $execute_result;
                        $execute_result = $item2['output'] ? $execute_result : 0;
                    }
                }
            }
            $execute_result = ($execute_result === '') ? 0 : $execute_result;
            $all_success = ($all_success === '') ? ActivationSendZftInfo::ALL_ACTIVATION_STATUS_SUCCESS : $all_success;
            $all_success = $execute_result ? $all_success : ActivationSendZftInfo::ALL_ACTIVATION_STATUS_FAIL;
            $tmp['activation_status'] = $execute_result ? ActivationSendZftInfo::ACTIVATION_STATUS_SUCCESS : ActivationSendZftInfo::ACTIVATION_STATUS_FAIL;
            $tmp['is_send_zft'] = ActivationSendZftInfo::SEND_ZFT_DEFAULT;
            $activation_info[] = $tmp;
        }
        foreach ($activation_info as &$v) {
            $v['all_activation_status'] = $all_success;
            $v['created_at'] = time();
            $v['updated_at'] = time();
        }
        ActivationSendZftInfo::saveActivationInfo($activation_info);
        //如果没有ZFT的签约数据，不发送
        if (!$outlet_contract_id) {
            return false;
        }

        $outlet_contract_id = $outlet_contract_id['outlet_contract_id'];
        if ($data['context']['rectification_model'] == Plan::RECTIFICATION_MODEL_WITH_CYCLE) {
            $executeCount = 1;
            $plan_info = Plan::findOneArray(['id' => $data['context']['plan_id']], ['start_time', 'end_time', 'short_cycle']);
            $excute_cycle_list = [];
            if (!empty($protocol['excute_cycle_list'])) {
                foreach (json_decode($protocol['excute_cycle_list'], true) as $v) {
                    $tmp['start_time'] = \Helper::dateTimeFormat($v['cycleFromDate'], 'Y-m-d');
                    $tmp['end_time'] = \Helper::dateTimeFormat($v['cycleToDate'], 'Y-m-d');
                    $tmp['cycle_id'] = $v['cycleID'];
                    $excute_cycle_list[] = $tmp;
                }
            }
            $cycle_list = $plan_info['short_cycle'] ?: json_encode($excute_cycle_list);
            $join[] = [
                'type' => 'JOIN',
                'table' => Survey::tableName() . ' s',
                'on' => 's.survey_code = e.survey_code'
            ];
            if (json_decode($cycle_list,true)) {
                $where = ['and'];
                $where[] = ['s.store_id' => $data['context']['store_id']];
                $where[] = ['e.standard_id' => $data['context']['standard_id']];
                //要确认是同一个plan
                $where[] = ['e.plan_id' => $data['context']['plan_id']];
                $where[] = ['send_zft_status' => EngineResult::SEND_ZFT_STATUS_SUCCESS];
                $model = PlanService::getSamePlanModel($data['context']['plan_id'], $data['context']['store_id'], $cycle_list, [], $where);
                if (!$model) return false;
                // 该时段已发送过的不返回
                $model->andWhere($where);
                $engine_result = $model->select('e.id')->all();
                if ($engine_result) {
                    return false;
                }
            } else {
                $time = $data['context']['survey_time'];
                //不在计划时间内的走访直接跳过
                if ($time < $plan_info['start_time'] || $time > $plan_info['end_time']) {
                    return false;
                }
                $alias = 'e';
                $select = ['e.id'];
                $where = ['and'];
                $where[] = ['>', 's.survey_time', $plan_info['start_time']];
                $where[] = ['<', 's.survey_time', $plan_info['end_time']];
                $where[] = ['s.store_id' => $data['context']['store_id']];
                $where[] = ['e.standard_id' => $data['context']['standard_id']];
                //要确认是同一个plan
                $where[] = ['e.plan_id' => $data['context']['plan_id']];
                $where[] = ['send_zft_status' => EngineResult::SEND_ZFT_STATUS_SUCCESS];
                // 该时段已发送过的不返回
                $engine_result = EngineResult::findJoin($alias, $join, $select, $where);
                if ($engine_result) {
                    return false;
                }
            }
        } else {
            return false;
        }

        $activation_list = [];
        //输出项全成功，生动化项才算是合格
        $all_success = '';
        foreach ($scenes as $v) {
            $execute_result = '';
            $activation_data['activationID'] = $v['activationID'];
            $activation_data['isStandard'] = $v['isStandard'];
            $activation_data['checkCount'] = 0;
            foreach ($v['outputList'] as $item) {
                foreach ($data['output_list'] as $item2) {
                    if ($item['node_index'] == $item2['node_index']) {
                        $execute_result = ($execute_result === '') ? 1 : $execute_result;
                        $execute_result = $item2['output'] ? $execute_result : 0;
                    }
                }
            }
            $all_success = ($all_success === '') ? true : $all_success;
            $activation_data['executeResult'] = ($execute_result === '') ? 0 : $execute_result;
            $all_success = ($activation_data['executeResult'] === 0) ? false : $all_success;
            $activation_list[] = $activation_data;
        }
        //获取该检查的总共检查次数
        array_pop($where);
        $engine_result = EngineResult::findJoin($alias, $join, $select, $where);
        $count = count($engine_result);
        //获取该检查设定的整改次数
        $alias = '';
        $join = [];
        $select = ['re_photo_time'];
        $where = [
            'id' => $data['context']['plan_id']
        ];
        $re_photo_time = Plan::findJoin($alias, $join, $select, $where, true, false)['re_photo_time'];
        //如果既没有达到整改的最后一次也不是全部成功，就不发送zft
        if ((($count < ($re_photo_time + $executeCount)) && !$all_success) || ($count > ($re_photo_time + $executeCount))) {
            return false;
        }
        $execute_list = [];
        $company_code = $data['context']['company_code'];
        $tool_name = Tools::findOneArray(['id' => $data['context']['tool_id']], ['name'])['name'];
        $execute_list[] = [
            'outletContractID' => (int)$outlet_contract_id,
            'outletNo' => $data['context']['store_id'],
            'executeCycleID' => $cycle_id,
            'executeCount' => (int)$executeCount,
            'executeDate' => date('YmdHis', strtotime($data['context']['survey_time'])),
            'executeBy' => $tool_name . '_' . $data['context']['examiner_id'],
            'surveyId' => $data['context']['survey_code'],
            'activationList' => $activation_list,
        ];
        $send_data = [
            'companyCode' => $company_code,
            'executeList' => $execute_list
        ];
        $header = Protocol::getZftToken(time());
        $re = \Helper::curlQueryLog($url1, $send_data, true, 300, $header);
        $engine_id = array_column($engine_result, 'id');
        if ($re) {
            if ($re['resultCode'] == 200) {
                EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_SUCCESS],
                    ['in', 'id', $engine_id]);
                ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_SUCCESS, 'send_zft_time' => time()],
                    ['standard_id' => $data['context']['standard_id'], 'survey_code' => $data['context']['survey_code']]);
            } else {
                EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => $re['resultMessage']],
                    ['in', 'id', $engine_id]);
                ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                    ['standard_id' => $data['context']['standard_id'], 'survey_code' => $data['context']['survey_code']]);
            }
        } else {
            EngineResult::updateAll(['send_zft_status' => EngineResult::SEND_ZFT_STATUS_FAIL, 'send_zft_fail' => 'ZFT无返回'],
                ['in', 'id', $engine_id]);
            ActivationSendZftInfo::updateAll(['is_send_zft' => ActivationSendZftInfo::SEND_ZFT_FAIL, 'send_zft_time' => time()],
                ['standard_id' => $data['context']['standard_id'], 'survey_code' => $data['context']['survey_code']]);
        }
        return true;
    }

    /**
     * 测试方法
     */
    public static function test()
    {
        $data = [
            'is_qc' => true,
            'standard_id' => 1091,
            'store_id' => '0515405007',
            'tool_id' => 2,
            'survey_code' => '20201105abbccdd5',
            'output_list' => json_decode('[{"node_index":6,"node_name":"\u6392\u9762\u662f\u5426\u5927\u4e8e[\u6570\u503c]","output":true},{"node_index":8,"node_name":"[\u7b5b\u9009SKU\u5217\u8868]\u6392\u9762\u6570","output":1},{"node_index":10,"node_name":"\u662f\u5426\u6709\u94fa\u8d27[\u7b5b\u9009SKU\u5217\u8868]","output":true},{"node_index":14,"node_name":"ko[\u7b5b\u9009SKU\u5217\u8868]\u6392\u9762\u5360\u6bd4\u662f\u5426\u5927\u4e8e\u7b49\u4e8e[\u6570\u503c]","output":true},{"node_index":17,"node_name":"[\u7b5b\u9009SKU\u5217\u8868]\u6392\u9762\u6570","output":1},{"node_index":19,"node_name":"\u662f\u5426\u6709\u94fa\u8d27[\u7b5b\u9009SKU\u5217\u8868]","output":true}]', true),
        ];
        SendService::newSendResultToCP($data);
    }
}