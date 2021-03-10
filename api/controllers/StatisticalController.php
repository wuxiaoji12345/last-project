<?php

namespace api\controllers;

use api\models\Replan;
use api\models\RuleOutputInfo;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\SceneType;
use api\models\StatisticalItem;
use api\models\User;
use Yii;

class StatisticalController extends BaseApi
{
    const ACCESS_ANY = [
        'drop-list',
        'sub-channel-list',
    ];

    /**
     * 重跑统计项目下拉列表
     * @return array
     */
    public function actionDropList()
    {
        $data = StatisticalItem::findAllArray([], ['id', 'title'], '', true, 'created_at desc, id asc');

        return $this->success(['list' => $data]);
    }

    /**
     * 统计项目列表
     * @return array
     */
    public function actionStatisticalList()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['page', 'page_size'])) {
            $this->error();
        }
        $where[] = 'and';
        if (!empty($params['create_time_start'])) {
            $where[] = ['>=', 'created_at', strtotime($params['create_time_start'] . ' 00:00:00')];
        }
        if (!empty($params['create_time_end'])) {
            $where[] = ['<=', 'created_at', strtotime($params['create_time_end'] . ' 23:59:59')];
        }
        if (!empty($params['statistical_id'])) {
            $where[] = ['or', ['id' => $params['statistical_id']], ['like', 'title', $params['statistical_id']]];
        }
        if (!empty($params['company_bu'])) {
            foreach ($params['company_bu'] as $v) {
                $company_bu = explode('_', $v);
                $company_code[] = $company_bu[0];
                if (isset($company_bu[1])) {
                    $bu_code[] = $company_bu[1];
                }
            }
            $where[] = ['in', 'company_code', $company_code];
            if (!empty($bu_code)) {
                $where[] = ['in', 'bu_code', $bu_code];
            }
        }
        $select = ['created_at', 'id', 'title', 'setup_step', 'company_code', 'bu_code', 'setup_step'];
        $where[] = ['=', 'status', StatisticalItem::DEL_STATUS_NORMAL];
        $data = StatisticalItem::findStatisticalList($where, $select, $params);
        $bu_list = OrganizationRelation::companyBu();
        $user = Yii::$app->params['user_info'];
        foreach ($data['list'] as &$v) {
            $v['created_at'] = date('Y-m-d h:i:s', $v['created_at']);
            $v['same_bu'] = ($user['company_code'] == $v['company_code'] && $user['bu_code'] == $v['bu_code']);
            $bu_name = '';
            if (!empty($v['company_code'])) {
                $bu_name = isset($bu_list[$v['company_code'] . '_' . $v['bu_code']]) ? $bu_list[$v['company_code'] . '_' . $v['bu_code']] : '';
            }
            $v['bu_name'] = $bu_name ? $bu_name : User::COMPANY_CODE_ALL_LABEL;
        }
        return $this->success($data);
    }

    /**
     * 新建统计项目
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionStatisticalAdd()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['title'])) {
            $this->error();
        }
        $add_url = Yii::$app->params['url']['rule_host'] . 'api/engine/rule-save';
//            $edit_url = Yii::$app->params['url']['rule_host'] . '/api/engine/rule-save';
        $url_head = Yii::$app->params['url']['rule_web'] . '?code=';
        $transaction = Yii::$app->getDb()->beginTransaction();
        $result = StatisticalItem::addStatistical($params);
        if ($result[0]) {
            $rule_data['statistical_id'] = $result[1];
            //如果有code，就不用再调用规则引擎接口。反之则调用
            if (!empty($result[2])) {
                $rule_url = $url_head . $result[2] . '&token=' . $params['token'];
            } else {
                $token[] = 'token:' . $params['token'];
                $engine_result = \Helper::curlQueryLog($add_url, $rule_data, true, 300, $token);
                if ($engine_result['code'] != 200) {
                    $transaction->rollBack();
                    return $engine_result;
                }
                StatisticalItem::saveRuleCode($result[1], $engine_result['data']['rule_code']);
                $rule_url = $url_head . $engine_result['data']['rule_code'] . '&token=' . $params['token'];
            }
            if (!empty($params['output_info']) && !empty($params['statistical_id'])) {
                $i = 1;
                foreach ($params['output_info'] as $v) {
                    $sort_result = RuleOutputInfo::updateSort($params['statistical_id'], $v['node_index'], $i, true);
                    if (!$sort_result[0]) {
                        $transaction->rollBack();
                        return $this->error($sort_result[1]);
                    }
                    $i++;
                }
            }
            $transaction->commit();

            $array['statistical_id'] = $result[1];
            $array['rule_url'] = $rule_url;
            return $this->success($array);
        } else {
            $transaction->rollBack();
            return $this->error($result[1]);
        }
    }

    /**
     * 统计项目删除
     * @return array
     */
    public function actionStatisticalDelete()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['statistical_id'])) {
            $this->error();
        }
        $check = Replan::findOne(['statistical_id' => $params['statistical_id']]);
        if ($check) {
            return $this->error('统计项目配置的统计任务正在进行中，暂不可删除');
        }
        $result = StatisticalItem::updateAll([StatisticalItem::DEL_FIELD => StatisticalItem::DEL_STATUS_DELETE], ['id' => $params['statistical_id']]);
        if ($result) {
            return $this->success();
        } else {
            return $this->error();
        }
    }

    /**
     * 单个统计项目详情
     * @return array
     */
    public function actionStatisticalDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['statistical_id'])) {
            $this->error();
        }
        $result = StatisticalItem::findOneArray(['id' => $params['statistical_id']]);
        if ($result['engine_code']) {
            $url = Yii::$app->params['url']['rule_host'] . 'api/engine/finish-status';
            $token[] = 'token:' . $params['token'];
            $send_data['rule_code'] = $result['engine_code'];
            $engine_status = \Helper::curlQueryLog($url, $send_data, true, 300, $token);
            if (!$engine_status['data']['is_finished']) {
                $result['is_running'] = '';
                $result['is_efficient'] = false;
                return $this->success($result);
            }
        }
        $check = Replan::findOne(['and', ['statistical_id' => $params['statistical_id']], ['in', 'replan_status', [Replan::STATUS_RUNNING, Replan::STATUS_PAUSE]]]);
        if ($result) {
            $result['is_running'] = $check ? true : false;
            $result['is_efficient'] = true;
            return $this->success($result);
        } else {
            return $this->error();
        }
    }

    /**
     * 统计项目完成接口
     * @return array
     */
    public function actionStatisticalFinish()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['statistical_id'])) {
            $this->error();
        }
        $result = StatisticalItem::statisticalFinish(['setup_step' => StatisticalItem::SETUP_STEP_FINISH], ['id' => $params['statistical_id']]);
        if ($result[0]) {
            return $this->success();
        } else {
            return $this->error($result[1]);
        }
    }

    /**
     * 修改引擎输出项输出顺序(废弃)
     * @return array
     */
    public function actionEditOutputIndex()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['statistical_id'])) {
            if (!empty($params['output_info'])) {
                $i = 1;
                foreach ($params['output_info'] as $v) {
                    $result = RuleOutputInfo::updateSort($params['statistical_id'], $v['node_index'], $i, true);
                    if (!$result[0]) {
                        return $this->error($result[1]);
                    }
                    $i++;
                }
            }
        } else {
            return $this->error();
        }
        return $this->success($params['output_info']);
    }

    /**
     * 单独调取引擎输出项
     * @return array
     */
    public function actionStatisticalOutputDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['statistical_id'])) {
            $where = ['status' => RuleOutputInfo::DEL_STATUS_NORMAL, 'statistical_id' => $params['statistical_id']];
            $select = ['node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'tag'];
            $rule_output_field = RuleOutputInfo::getOutputInfo($where, $select);
            $all_type = SceneType::findAllArray([], ['*'], 'id');
            $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
            foreach ($rule_output_field as &$v) {
                $name = '';
                if ($v['is_all_scene'] == 1) {
                    $name = '全场景';
                }
                $scene_type = json_decode($v['scene_type'], true);
                $scene_code = json_decode($v['scene_code'], true);
                if (!empty($scene_type)) {
                    foreach ($scene_type as $item) {
                        if (!$name) {
                            $name .= $all_type[$item]['name'];
                        } else {
                            $name .= '、' . $all_type[$item]['name'];
                        }
                    }
                }
                if (!empty($scene_code)) {
                    $code_name = '';
                    foreach ($scene_code as $item) {
                        if (!$code_name) {
                            $code_name .= $all_scene[$item]['scene_code_name'];
                        } else {
                            $code_name .= '、' . $all_scene[$item]['scene_code_name'];
                        }
                    }
                    if ($name != '') {
                        $name = $name . ';' . $code_name;
                    } else {
                        $name = $code_name;
                    }
                }
                $v['node_name'] = $name . ':' . $v['node_name'];
            }
//            $where['status'] = RuleOutputInfo::DEL_STATUS_DELETE;
//            $delete = RuleOutputInfo::getOutputInfo($where, $select);
//            foreach ($delete as &$v) {
//                $name = '';
//                if ($v['is_all_scene'] == 1) {
//                    $name = '全场景';
//                }
//                $scene_type = json_decode($v['scene_type'], true);
//                $scene_code = json_decode($v['scene_code'], true);
//                if (!empty($scene_type)) {
//                    $code_where = [
//                        'and',
//                        ['in', 'id', $scene_type]
//                    ];
//                    $select1 = ['name'];
//                    $type_array = array_column(SceneType::findTypeList($code_where, $select1), 'name');
//                    $type_name = implode($type_array, '、');
//                    $name = $type_name;
//                }
//                if (!empty($scene_code)) {
//                    $code_where = [
//                        'and',
//                        ['in', 'scene_code', $scene_code]
//                    ];
//                    $select1 = ['scene_code_name'];
//                    $code_array = array_column(Scene::findSceneList($code_where, $select1), 'scene_code_name');
//                    $code_name = implode($code_array, '、');
//                    if ($name != '') {
//                        $name = $name . ';' . $code_name;
//                    } else {
//                        $name = $code_name;
//                    }
//                }
//                $v['node_name'] = $name . ':' . $v['node_name'];
//            }
//            $is_change = Standard::getIsChange($params['standard_id']);
            $data['rule_output_field'] = $rule_output_field;
//            $data['delete'] = $delete;
//            $data['is_change'] = $is_change['is_change'];
            return $this->success($data);
        } else {
            return $this->error();
        }
    }
}
