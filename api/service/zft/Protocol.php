<?php


namespace api\service\zft;


use api\models\Plan;
use api\models\ProtocolTemplate;
use api\models\Standard;
use yii\helpers\BaseInflector;
use Yii;

class Protocol
{
    /**
     * 根据zft协议id更新检查计划执行时间
     * @param $protocol_id integer ProtocolTemplate主键id
     * @return boolean
     */
    public static function syncPlanTime($protocol_id)
    {
        $protocol = ProtocolTemplate::findOneArray(['id' => $protocol_id]);
        if ($protocol == null) {
            return false;
        }

        $standard = Standard::findOneArray(['protocol_id' => $protocol_id]);
        if ($standard == null) {
            return true;
        }

        $plan = Plan::findOneArray(['standard_id' => $standard['id']]);
        if ($plan != null) {
            Plan::updateAll(['start_time' => \Helper::dateTimeFormat($protocol['excute_from_date']), 'end_time' => \Helper::dateTimeFormat($protocol['excute_to_date'])],
                ['id' => $plan['id'], Plan::DEL_FIELD => Plan::DEL_STATUS_NORMAL]);
        }

        return true;
    }

    /**
     * 根据加密规则获得发送curl的head头
     * @param $timestamp
     * @return array
     */
    public static function getZftToken($timestamp)
    {
        $api_key = \Yii::$app->params['zft_api_key'];
        $token = md5($timestamp.md5($api_key).$timestamp);
        $head[] = 'x-access-token: '.$token;
        $head[] = 'timestamp: '.$timestamp;
//        $head[] = 'x-access-token: ABC';
        return $head;
    }

    public static function getZftTemplate($company_code, $contract_code){
        //1、curl 调用SamrtMEDI系统，获取协议信息
        $request_url = Yii::$app->params['zft_url'] . '/api/getContractList';
        $request_params = [
            'companyCode' => $company_code,
            'contractCode' => $contract_code
        ];
        $request_url = $request_url . '?' . http_build_query($request_params);
        $request_header = Protocol::getZftToken(time());
        $request_header[] = 'Content-type: text/json';
        $curl_response = \Helper::curlQueryLog($request_url, [], false, 300, $request_header);
        $convert_contract = [];
        if (!empty($curl_response)) {
            $curl_response_array = $curl_response;
            if (!empty($curl_response_array['resultCode']) && $curl_response_array['resultCode'] == 200
                && !empty($curl_response_array['contractList']) && isset($curl_response_array['contractList'][0])) {
                $contract = $curl_response_array['contractList'][0];
                //驼峰转小写加下划线
                foreach ($contract as $key => $val) {
                    $convert_contract[BaseInflector::camel2id($key, '_')] = $val;
                }
                if (isset($contract['status'])) {
                    //状态字段处理
                    $convert_contract['protocol_status'] = $contract['status'];
                    unset($convert_contract['status']);
                }
                //json 格式字段处理
                if (isset($convert_contract['activation_list']) && isset($convert_contract['excute_cycle_list'])) {
                    $convert_contract['activation_list'] = json_encode($convert_contract['activation_list']);
                    $convert_contract['excute_cycle_list'] = json_encode($convert_contract['excute_cycle_list']);
                }
                //2、查询数据库是否存在协议数据
                $exist_contract_info = ProtocolTemplate::findOne(['contract_id' => $contract['contractID'], 'company_code' => $company_code]);
                //3、更新或保存对应的协议数据
                if ($exist_contract_info) {
                    $exist_contract_info->load($convert_contract, '');
                    $exist_contract_info->company_code = $company_code;
                    $exist_contract_info->save();
                    $convert_contract['protocol_id'] = $exist_contract_info->id;
                    return $exist_contract_info;
                } else {
                    $model = new ProtocolTemplate();
                    $model->load($convert_contract, '');
                    $model->company_code = $company_code;
                    $model->save();
                    $convert_contract['protocol_id'] = $model->id;
                    return $model;
                }
            }
        }

        return false;
    }
}