<?php
/**
 * Created by PhpStorm.
 * User: wudaji
 * Date: 2020/1/14
 * Time: 17:39
 */

namespace api\controllers;

use api\models\CheckType;
use api\models\EngineResult;
use api\models\Image;
use api\models\Plan;
use api\models\ProtocolLively;
use api\models\Question;
use api\models\QuestionAnswer;
use api\models\QuestionOption;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\share\SceneType;
use api\models\Standard;
use api\models\RuleOutputInfo;
use api\models\Store;
use api\models\SubActivity;
use api\models\Survey;
use api\models\SurveyQuestion;
use api\models\User;
use api\models\ProtocolTemplate;
use api\service\rule\RuleService;
use api\service\zft\Protocol;
use phpDocumentor\Reflection\Types\Self_;
use yii\helpers\BaseInflector;
use Yii;

class RuleController extends BaseApi
{
    const ACCESS_ANY = [
        'scene-list',
        'check-type-list',
        'standard-output-detail',
        'scene-output-detail',
        'bu-list',
        'standard-status',
        'standard-protocol-detail',
        'standard-protocol-search',
        'test',
        'search-standard-list',
        'search-tool-list',
    ];

    /**
     * 查询检查类型列表
     * @return array
     */
    public function actionCheckTypeList()
    {
        $params = Yii::$app->request->bodyParams;
        $pageSize = (isset($params['pageSize']) && !empty($params['pageSize'])) ? $params['pageSize'] : 10;
        $page = (isset($params['page']) && !empty($params['page'])) ? $params['page'] : 1;
        $type = (isset($params['type']) && !empty($params['type'])) ? $params['type'] : '';
        $data = CheckType::getTypeListWithPackage($pageSize, $page, $type);
        if (isset($data)) {
            foreach ($data['list'] as &$v) {
                $v['create_time'] = date('Y-m-d H:i', $v['create_time']);
            }
            return $this->success($data);
        } else {
            return $this->error("查询异常，请检查");
        }
    }

    /**
     * 新建检查类型
     * @return array
     */
    public function actionCheckTypeAdd()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['title'])) {
            $repeat = CheckType::getCheckTypeWithTitle($params['title']);
            if (!$repeat) {
                $data = $params;
                $result = CheckType::addCheckType($data);
                if ($result[0]) {
                    return $this->success($result[1]);
                } else {
                    return $this->error($result[1]);
                }
            } else {
                return $this->error('类型名已存在');
            }
        } else {
            return $this->error();
        }

    }

    /**
     * 检查类型开启/关闭
     * @return array
     */
    public function actionCheckTypeSwitch()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['check_type_id', 'active_status'])) {
            $where = ['id' => $params['check_type_id']];
            $active_status = $params['active_status'];
            $result = CheckType::doCheckTypeSwitch($where, $active_status);
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 删除检查类型
     * @return array
     */
    public function actionCheckTypeDelete()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['check_type_id'])) {
            $where = ['id' => $params['check_type_id']];
            $status = CheckType::DEL_STATUS_DELETE;
            $result = CheckType::doCheckTypeDelete($where, $status);
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 检查项目列表
     * @return array
     */
    public function actionStandardList()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['page_size']) && isset($params['page']) && isset($params['company_bu'])) {
            $table = Standard::tableName();
            $where[] = 'and';
            if (!empty($params['create_time_start'])) {
                $where[] = ['>=', $table . '.created_at', strtotime($params['create_time_start'] . ' 00:00:00')];
            }
            if (!empty($params['create_time_end'])) {
                $where[] = ['<=', $table . '.created_at', strtotime($params['create_time_end'] . ' 23:59:59')];
            }
            if (!empty($params['standard_id'])) {
                $where[] = ['or', ['=', $table . '.id', $params['standard_id']], ['like', $table . '.title', $params['standard_id']]];
            }
            if (!empty($params['check_type_id'])) {
                $where[] = ['=', 'c.id', $params['check_type_id']];
            }
            if (!empty($params['company_bu'])) {
                foreach ($params['company_bu'] as $v) {
                    $company_bu = explode('_', $v);
                    $company_code[] = $company_bu[0];
                    if (isset($company_bu[1])) {
                        $bu_code[] = $company_bu[1];
                    }
                }
                $where[] = ['in', $table . '.company_code', $company_code];
                $where[] = ['in', $table . '.bu_code', $bu_code];
            }
            $where[] = ['=', $table . '.is_deleted', Standard::NOT_DELETED];
            $data = Standard::getStandardAll($where, $params['page_size'], $params['page']);
            if (isset($data)) {
                $bu_list = OrganizationRelation::companyBu();
                $user = Yii::$app->params['user_info'];
                foreach ($data['list'] as &$v) {
                    $v['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                    $v['same_bu'] = ($user['company_code'] == $v['company_code'] && $user['bu_code'] == $v['bu_code']);
//                    $v['type'] = CheckType::IS_PROTOCOL_DESCRIBE[$v['type']];
                    $bu_name = '';
                    if (!empty($v['company_code'])) {
                        $key = $v['company_code'] . '_' . $v['bu_code'];
                        $bu_name = isset($bu_list[$key]) ? $bu_list[$key] : User::COMPANY_CODE_ALL_LABEL;
                    }
                    $v['bu_name'] = !empty($bu_name) ? $bu_name : User::COMPANY_CODE_ALL_LABEL;
                }
                return $this->success($data);
            } else {
                return $this->error("查询异常，请检查");
            }
        } else {
            return $this->error("缺少入参，请检查");
        }
    }

    /**
     * 检查项目详情
     * @return array
     */
    public function actionStandardDetail()
    {
        $params = Yii::$app->request->bodyParams;
        $url_head = Yii::$app->params['url']['rule_web'] . '?code=';
        if ($this->check($params, ['standard_id'])) {
            $result = Standard::checkProtocol($params);
            if (isset($result[2])) {
                $data = $result[2];
                $type = CheckType::findOneArray(['id' => $data['check_type_id']]);
                $data['create_time'] = date('Y-m-d H:i', $data['created_at']);
                $data['image'] = !empty($data['image']) ? json_decode($data['image'], true) : '';
                $data['scenes_ir_id'] = !empty($data['scenes_ir_id']) ? json_decode($data['scenes_ir_id'], true) : '';
                $data['rule_url'] = $url_head . $data['engine_rule_code'] . '&token=' . $params['token'];
                $data['type'] = $type['type'];
                $data['check_type_title'] = $type['title'];
                $data['standard_id'] = $data['id'];
                return $this->success($data);
            }
            return $this->error('id不存在');
        } else {
            return $this->error();
        }
    }

    /**
     * 单独调取引擎输出项
     * @return array
     */
    public function actionStandardOutputDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $is_change = Standard::getIsChange($params['standard_id']);
            //获取规则完成状态
            if ($is_change['engine_rule_code']) {
                $url = Yii::$app->params['url']['rule_host'] . 'api/engine/finish-status';
                $token[] = 'token:' . $params['token'];
                $send_data['rule_code'] = $is_change['engine_rule_code'];
                $engine_status = \Helper::curlQueryLog($url, $send_data, true, 300, $token);
                if (!$engine_status['data']['is_finished']) {
                    $data['rule_output_field'] = [];
                    $data['delete'] = [];
                    $data['is_change'] = $is_change['is_change'];
                    return $this->success($data);
                }
            }
            $where = ['status' => RuleOutputInfo::DEL_STATUS_NORMAL, 'standard_id' => $params['standard_id']];
            $select = ['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'tag', 'output_type'];
            $rule_output_field = RuleOutputInfo::getOutputInfo($where, $select);
            $all_type = SceneType::findAllArray([], ['*'], 'id');
            $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
            foreach ($rule_output_field as &$v) {
                $name = '';
                if ($v['is_all_scene'] == RuleOutputInfo::IS_ALL_SCENE_YES) {
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
            $where['status'] = RuleOutputInfo::DEL_STATUS_DELETE;
            $delete = RuleOutputInfo::getOutputInfo($where, $select);
            foreach ($delete as &$v) {
                $name = '';
                if ($v['is_all_scene'] == RuleOutputInfo::IS_ALL_SCENE_YES) {
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
            $data['rule_output_field'] = $rule_output_field;
            $data['delete'] = $delete;
            $data['is_change'] = $is_change['is_change'];
            return $this->success($data);
        } else {
            return $this->error();
        }
    }

    /**
     * 查询带场景层级的bool输出项
     * @return array
     */
    public function actionSceneOutputDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $where = ['status' => RuleOutputInfo::DEL_STATUS_NORMAL, 'output_type' => RuleOutputInfo::OUTPUT_TYPE_BOOL,
                'standard_id' => $params['standard_id']];
            $select = ['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_vividness'];
            $rule_output_field = RuleOutputInfo::getOutputInfo($where, $select);
            $data = [];
            $items = [];
            $all_type = SceneType::findAllArray([], ['*'], 'id');
            $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
            foreach ($rule_output_field as $item) {
                $scene_type = json_decode($item['scene_type'], true);
                $scene_code = json_decode($item['scene_code'], true);
                $name = '';
                if (!empty($scene_type)) {
                    if ($scene_type[0] == SceneType::SCENE_TYPE_ALL) {
                        $name = '全场景';
                    } else {
                        foreach ($scene_type as $v) {
                            if (!$name) {
                                $name .= $all_type[$v]['name'];
                            } else {
                                $name .= '、' . $all_type[$v]['name'];
                            }
                        }
                    }
                }
                if (!empty($scene_code)) {
                    $code_name = '';
                    foreach ($scene_code as $v) {
                        if (!$code_name) {
                            $code_name .= $all_scene[$v]['scene_code_name'];
                        } else {
                            $code_name .= '、' . $all_scene[$v]['scene_code_name'];
                        }
                    }
                    if ($name != '') {
                        $name = $name . ';' . $code_name;
                    } else {
                        $name = $code_name;
                    }
                }
                $output = [
                    'node_index' => $item['node_index'],
                    'node_name' => $item['node_name'],
                    'id' => $item['id'],
                    'is_vividness' => $item['is_vividness']
                ];
                $items[$name]['name'] = $name;
                $items[$name]['outputs'][] = $output;
            }
            $set_vividness = Standard::findOneArray(['id' => $params['standard_id']], ['set_vividness']);
            $data['set_vividness'] = isset($set_vividness) ? $set_vividness['set_vividness'] : Standard::SET_VIVIDNESS_NO;
            $data['items'] = array_values($items);
            return $this->success($data);
        } else {
            return $this->error();
        }
    }

    /**
     * 检查项目开启/关闭
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionStandardSwitch()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id', 'standard_status'])) {
            $where = ['id' => $params['standard_id']];
            $standard_status = $params['standard_status'];
            $result = Standard::doStandardSwitch($where, $standard_status, $params['standard_id'], $params);
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 删除检查项目
     * @return array
     */
    public function actionStandardDelete()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $where = ['id' => $params['standard_id']];
            $is_deleted = Standard::IS_DELETED;
            $result = Standard::doStandardDelete($where, $is_deleted);
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 新建检查项目【第二步】或修改
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionStandardEdit()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id', 'scenes'])) {
            $transaction = Yii::$app->getDb()->beginTransaction();
            $change = 0;
            $check_type = [];
            $check_code = [];
            $scenes = is_array($params['scenes']) ? $params['scenes'] : json_decode($params['scenes'], true);
            $items = [];
            $rule_data = [];
            $is_protocol = 1;
            //判断scenes条目，如果原有的和现在设置的条目数不一致，即为修改了
            //特殊情况：删了一个又加一个，总条目一样但内容不一样，这个由下面的子活动判断组判断
            $scenes_info = Standard::findOneArray(['id' => $params['standard_id']], ['scenes']);
            if (!empty($scenes_info['scenes'])) {
                $scenes_arr = json_decode($scenes_info['scenes'], true);
                $old_count = count($scenes_arr);
                $new_count = count($scenes);
                if ($old_count != $new_count) {
                    $change = 1;
                }
            }
            //不管是新增还是修改，首先把该规则下的原有子活动全部逻辑删除，以便再添加
            SubActivity::updateAll(['status' => SubActivity::DEL_STATUS_DELETE], ['standard_id' => $params['standard_id']]);
            $not_import_change = 0;
            foreach ($scenes as $k => $v) {
                //非协议类检查项目不允许添加同样的场景
                $rule_need['scene_type'] = $v['scenes_type_id'];
                $rule_need['scene_code'] = $v['scenes_code'];
                if (!isset($v['activationID'])) {
                    $is_protocol = 0;
                    if (!empty($v['scenes_type_id'])) {
                        if (!empty($check_type)) {
                            foreach ($check_type as $item) {
                                if ($v['scenes_type_id'] == $item) {
                                    $transaction->rollBack();
                                    return $this->error('不允许两个场景类别都选择同一类场景');
                                }
                            }
                        }
                        $check_type[] = $v['scenes_type_id'];
                    }

                    if (!empty($v['scenes_code'])) {
                        if (!empty($check_code)) {
                            foreach ($check_code as $item) {
                                if ($v['scenes_code'] == $item) {
                                    $transaction->rollBack();
                                    return $this->error('不允许两个场景类别都选择同一类场景');
                                }
                            }
                        }
                        $check_code[] = $v['scenes_code'];
                    }
                    //！！！此处如果是协议类，经讨论只取字段activationName，非协议类只取字段sub_activity_name
                    $scenes[$k]['sub_activity_name'] = isset($scenes[$k]['sub_activity_name'])
                    && $scenes[$k]['sub_activity_name'] ? $scenes[$k]['sub_activity_name'] : '生动化' . ($k + 1);
                    $v['sub_activity_name'] = $scenes[$k]['sub_activity_name'];
                } else {
                    $scenes[$k]['sub_activity_name'] = isset($scenes[$k]['sub_activity_name'])
                    && $scenes[$k]['sub_activity_name'] ? $scenes[$k]['sub_activity_name'] : '生动化' . $v['activationID'];
                    $v['sub_activity_name'] = $scenes[$k]['activationName'];
                }
                $v['sub_activity_name'] = $scenes[$k]['sub_activity_name'];
                $code_id = Scene::findAllArray(['in', 'scene_code', $v['scenes_code']], ['id','scene_code']);
                $id_array = array_column($code_id, 'id');
                $code_array = array_column($code_id, 'scene_code');
                if (isset($v['scenes_type_id'][0]) && $v['scenes_type_id'][0] !== SceneType::SCENE_TYPE_ALL) {
                    $type_code = Scene::findAllArray(['in', 'scene_type', $v['scenes_type_id']], ['id','scene_code']);
                    $type_code_id = array_column($type_code, 'id');
                    $type_code = array_column($type_code, 'scene_code');
                    $id_array = array_merge($id_array, $type_code_id);
                    $code_array = array_merge($code_array, $type_code);
                }
                $id_array = array_unique($id_array);
                $code_array = array_unique($code_array);
                //将所有的scene_code再复制回scenes里
                $scenes[$k]['scenes_code'] = $v['scene_code'] = $code_array;
                if (!empty($v['question_manual_ir']) && count($code_array) > 1) {
                    $transaction->rollBack();
                    return $this->error('选则了手动模式问卷，就不能选择多个场景code，请检查');
                }
                $v['standard_id'] = $params['standard_id'];
                if (empty($v['scenes_type_id']) || $v['scenes_type_id'][0] !== SceneType::SCENE_TYPE_ALL) {
                    $scenes_type = Scene::findAllArray(['in', 'scene_code', $v['scenes_code']], ['scene_type']);
                    $scenes_type = array_column($scenes_type, 'scene_type');
                    $v['scenes_type_id'] = array_merge($scenes_type, $v['scenes_type_id']);
                    $v['scenes_type_id'] = array_unique($v['scenes_type_id']);
                    $v['scenes_type_id'] = array_values($v['scenes_type_id']);
                }
                if (isset($v['sub_activity_id'])) {
                    $sub_result = SubActivity::editSubActivity($v);
                    if (!$sub_result[0]) {
                        $transaction->rollBack();
                        return $this->error($sub_result[1]);
                    }
                    if ($sub_result[2]) {
                        $change = 1;
                    }
                    $not_import_change = $not_import_change ? $not_import_change : $sub_result[3];
                } else {
                    $sub_result = SubActivity::saveSubActivity($v);
                    if (!$sub_result[0]) {
                        $transaction->rollBack();
                        return $this->error($sub_result[1]);
                    }
                    $change = 1;
                    $scenes[$k]['sub_activity_id'] = $sub_result[1];
                }
                //拼接问题类型入数组且验证问卷
                $rule_need['questionnaire'] = [];
                $question_manual_id = array_column($v['question_manual'], 'id');
//                $question_type_arr = Question::findAllArray(['in', 'id', $question_manual_id], ['question_type', 'title', 'scene_type_id', 'id']);
                $alias = 'q';
                $join = [];
                $join[] = [
                    'type' => 'LEFT JOIN',
                    'table' => QuestionOption::tableName() . ' qo',
                    'on' => 'qo.question_id = q.id'
                ];
                $select = ['q.question_type', 'q.title', 'q.scene_type_id', 'q.id question_id', 'qo.id option_id', 'qo.name option_name', 'qo.value option_value', 'q.type', 'q.status'];
                $where = [];
                $where[] = 'and';
                $where[] = ['in', 'q.id', $question_manual_id];
//                $where[] = ['qo.status' => QuestionOption::DEL_STATUS_NORMAL];
                $question_type_arr = Question::findJoin($alias, $join, $select, $where, $asArray = true, $all = true, $order = '', $index = '', $group = '',$with = '',$pages = '', $debug = false, $has_delete = true);
                foreach ($question_type_arr as $item) {
                    if (!in_array($item['scene_type_id'], $id_array) && (!isset($v['scenes_type_id'][0]) || $v['scenes_type_id'][0] != SceneType::SCENE_TYPE_ALL) && $item['status'] != Question::DEL_STATUS_DELETE) {
                        $transaction->rollBack();
                        return $this->error('非ir问卷：' . $item['title'] . '与所选场景不匹配，请检查！');
                    }
                    $rule_need['questionnaire'][$item['question_id']]['id'] = $item['question_id'];
                    $rule_need['questionnaire'][$item['question_id']]['title'] = $item['title'];
                    $rule_need['questionnaire'][$item['question_id']]['type'] = $item['type'];
                    if ($item['question_type'] == Question::QUESTION_TYPE_SELECT) {
                        $rule_need['questionnaire'][$item['question_id']]['option_info'][] = [
                            'option_id' => $item['option_id'],
                            'option_value' => $item['option_value']
                        ];
                    }
                    $rule_need['questionnaire'][$item['question_id']]['question_type'] = $item['question_type'];
                }
                $rule_need['questionnaire'] = array_values($rule_need['questionnaire']);

                $rule_need['questionnaire_ir'] = [];
                $question_manual_ir_id = array_column($v['question_manual_ir'], 'id');
//                $question_type_arr = Question::findAllArray(['in', 'id', $question_manual_ir_id], ['question_type', 'title', 'scene_type_id', 'id']);
                $alias = 'q';
                $join = [];
                $join[] = [
                    'type' => 'LEFT JOIN',
                    'table' => QuestionOption::tableName() . ' qo',
                    'on' => 'qo.question_id = q.id'
                ];
                $select = ['q.question_type', 'q.title', 'q.scene_type_id', 'q.id question_id', 'qo.id option_id', 'qo.name option_name', 'qo.value option_value', 'q.type', 'q.status'];
                $where = [];
                $where[] = 'and';
                $where[] = ['in', 'q.id', $question_manual_ir_id];
//                $where[] = ['qo.status' => QuestionOption::DEL_STATUS_NORMAL];
                $question_type_arr = Question::findJoin($alias, $join, $select, $where, $asArray = true, $all = true, $order = '', $index = '', $group = '',$with = '',$pages = '', $debug = false, $has_delete = true);
                foreach ($question_type_arr as $item) {
                    if (!in_array($item['scene_type_id'], $id_array) && (!isset($v['scenes_type_id'][0]) || $v['scenes_type_id'][0] != SceneType::SCENE_TYPE_ALL) && $item['status'] != Question::DEL_STATUS_DELETE) {
                        $transaction->rollBack();
                        return $this->error('ir问卷：' . $item['title'] . '与所选场景不匹配，请检查！');
                    }
                    $rule_need['questionnaire_ir'][$item['question_id']]['id'] = $item['question_id'];
                    $rule_need['questionnaire_ir'][$item['question_id']]['title'] = $item['title'];
                    $rule_need['questionnaire_ir'][$item['question_id']]['type'] = $item['type'];
                    if ($item['question_type'] == Question::QUESTION_TYPE_SELECT) {
                        $rule_need['questionnaire_ir'][$item['question_id']]['option_info'][] = [
                            'option_id' => $item['option_id'],
                            'option_value' => $item['option_value']
                        ];
                    }
                    $rule_need['questionnaire_ir'][$item['question_id']]['question_type'] = $item['question_type'];
                }
                $rule_need['questionnaire_ir'] = array_values($rule_need['questionnaire_ir']);
                $rule_need['sub_activity_id'] = $scenes[$k]['sub_activity_id'];
                $rule_need['sub_activity_name'] = $scenes[$k]['sub_activity_name'];
                $rule_need['sub_activity_describe'] = $scenes[$k]['describe'];
                if (isset($v['scenes_type_id'][0]) && $v['scenes_type_id'][0] == SceneType::SCENE_TYPE_ALL) {
                    $rule_need['is_all_scene'] = RuleOutputInfo::IS_ALL_SCENE_YES;
                } else {
                    $rule_need['is_all_scene'] = RuleOutputInfo::IS_ALL_SCENE_NO;
                }
                $items[] = $rule_need;
            }
            $rule_data['scene_list'] = $items;

            $data['scenes'] = json_encode($scenes);
            $data['standard_id'] = $params['standard_id'];
            $data['is_change'] = $change;
            $where = ['id' => $params['standard_id']];
            $result = Standard::editStandard($where, $data);
            $add_url = Yii::$app->params['url']['rule_host'] . 'api/engine/rule-save';
            $url_head = Yii::$app->params['url']['rule_web'] . '?code=';
            if ($result[0]) {
                $rule_data['standard_id'] = $result[1]['id'];
                $engine_rule_code = Standard::getEngineRuleCode($result[1]['id']);
                //如果有code且子活动没有变化，就不用再调用规则引擎接口。反之则调用
                if (!empty($engine_rule_code['engine_rule_code']) && $change === 0 && $not_import_change === 0) {
                    $rule_url = $url_head . $engine_rule_code['engine_rule_code'] . '&token=' . $params['token'];
                } else {
                    $token[] = 'token:' . $params['token'];
                    $engine_result = \Helper::curlQueryLog($add_url, $rule_data, true, 300, $token);
                    if ($engine_result['code'] != 200) {
                        $transaction->rollBack();
                        return $this->error($engine_result);
                    }
                    Standard::saveRuleCode($result[1]['id'], $engine_result['data']['rule_code']);
                    $rule_url = $url_head . $engine_result['data']['rule_code'] . '&token=' . $params['token'];
                }

                $array['id'] = $result[1]['id'];
                $array['rule_url'] = $rule_url;
                $array['is_change'] = $result[2];
                if ($array['is_change'] == Standard::IS_CHANGE) {
                    RuleOutputInfo::updateAll(['status' => 0], ['standard_id' => $params['standard_id']]);
                }
                //sft判断是否协议有变化
                if ($is_protocol) {
                    $protocol_change = Standard::checkProtocol($params);
                    if ($protocol_change[0] && $protocol_change[1]) {
                        $transaction->rollBack();
                        return $this->error('规则绑定协议有变化，请检查');
                    }
                }
                $transaction->commit();
                return $this->success($array);
            } else {
                $transaction->rollBack();
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 规则引擎查询token是否有效
     * @return array
     */
    public function actionValidateToken()
    {
        $params = Yii::$app->request->bodyParams;
        $queue_name = Yii::$app->params['project_id'] . '_' . Yii::$app->params['swire_user_info'];
        $queue_name .= $params['token'];
        $user_cache = Yii::$app->remq::getString($queue_name);
        if ($user_cache != null) {
            $data['auth'] = true;
            return $this->success($data);
        } else {
            $data['auth'] = false;
            return $this->error($data);
        }
    }

    /**
     * 新建检查项目【第一步】
     * @return array
     */
    public function actionStandardAdd()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['check_type_id', 'title'])) {
            $check_type_type = CheckType::findOneArray(['id' => $params['check_type_id']])['type'];
            if ($check_type_type == 1 && !isset($params['protocol_code'])) {
                return $this->error('协议类检查类型protocol_code字段必传');
            }
            $use_info = Yii::$app->params['user_info'];
            $params['company_code'] = $use_info['company_code'];
            $params['bu_code'] = isset($use_info['bu_code']) ? $use_info['bu_code'] : '';
            $params['user_id'] = isset($use_info['id']) ? $use_info['id'] : '';
            if (isset($params['standard_id']) && !empty($params['standard_id'])) {
                $where = ['id' => $params['standard_id']];
                $result = Standard::editStandard($where, $params, 1);
                if ($result[0] && isset($params['protocol_code']) && !empty($params['protocol_code'])) {
                    $re = Standard::checkProtocol($params, $result[2]);
                    if (!$re[0]) {
                        return $this->error('协议不存在，请检查');
                    }
                }
            } else {
                $result = Standard::addStandard($params);
                if ($result[0] && isset($params['protocol_code']) && !empty($params['protocol_code'])) {
                    $params['standard_id'] = $result[1];
                    $re = Standard::checkProtocol($params);
                    if (!$re[0]) {
                        return $this->error('协议不存在，请检查');
                    }
                }
            }
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 修改检查项目的设置步骤
     * @return array
     */
    public function actionStandardReviseSetupStep()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id', 'setup_step'])) {
            $protocol_change = Standard::checkProtocol($params);
            if (isset($protocol_change[2]) && $protocol_change[2]['setup_step'] == Standard::SETUP_STEP_SET_RULE) {
                $url = Yii::$app->params['url']['rule_host'] . 'api/engine/finish-status';
                $token[] = 'token:' . $params['token'];
                $send_data['rule_code'] = $protocol_change[2]['engine_rule_code'];
                $engine_status = \Helper::curlQueryLog($url, $send_data, true, 300, $token);
                if (!$engine_status['data']['is_finished']) {
                    return $this->error('规则未保存，请继续设置');
                }
            }
            $where = ['id' => $params['standard_id']];
            $setup_step = $params['setup_step'];
            $result = Standard::doReviseSetupStep($where, $setup_step);
            if ($protocol_change[0] && $protocol_change[1]) {
                return $this->error('规则绑定协议有变化，请检查');
            }
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 修改检查类型
     * @return array
     */
    public function actionCheckTypeEdit()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['title', 'id'])) {
            $where = ['id' => $params['id']];
            $title = $params['title'];
            $result = CheckType::doCheckTypeEdit($where, $title);
            if ($result[0]) {
                return $this->success($result[1]);
            } else {
                return $this->error($result[1]);
            }
        } else {
            return $this->error();
        }
    }

    /**
     * 修改引擎输出项输出顺序
     * @return array
     */
    public function actionEditOutputIndex()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            if (!empty($params['output_info'])) {
                $i = 1;
                foreach ($params['output_info'] as $v) {
                    $result = RuleOutputInfo::updateSort($params['standard_id'], $v['node_index'], $i);
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
     * 返回得分项可选输出项列表
     * @return array
     */
    public function actionNumberOutput()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $set_main = Standard::findOneArray(['id' => $params['standard_id']], ['set_main']);
            $list = RuleOutputInfo::findAllArray(['output_type' => RuleOutputInfo::OUTPUT_TYPE_NUMBER, 'standard_id' => $params['standard_id']], ['id', 'node_name', 'is_score']);
            $data = [
                'set_main' => isset($set_main) ? $set_main['set_main'] : RuleOutputInfo::IS_MAIN_NO,
                'list' => $list
            ];
        } else {
            return $this->error();
        }
        return $this->success($data);
    }

    /**
     * 返回主检查项可选输出项列表
     * @return array
     */
    public function actionBoolOutput()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $data = RuleOutputInfo::findAllArray(['output_type' => RuleOutputInfo::OUTPUT_TYPE_BOOL, 'standard_id' => $params['standard_id']], ['id', 'node_name', 'is_main']);
        } else {
            return $this->error();
        }
        return $this->success($data);
    }

    /**
     * 保存设置的得分项与主检查项
     * @return array
     */
    public function actionMainOutputSave()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['set_main', 'standard_id'])) {
            //全部置刷新重新赋值
            Standard::updateAll(['set_main' => $params['set_main']], ['id' => $params['standard_id']]);
            RuleOutputInfo::updateAll(['is_score' => RuleOutputInfo::IS_SCORE_NO, 'is_main' => RuleOutputInfo::IS_MAIN_NO], ['standard_id' => $params['standard_id']]);
            if (isset($params['score_output']) && !empty($params['score_output'])) {
                RuleOutputInfo::updateAll(['is_score' => RuleOutputInfo::IS_SCORE_YES], ['id' => $params['score_output']]);
            }
            if (isset($params['main_output']) && !empty($params['main_output'] && $params['set_main'] != RuleOutputInfo::OUTPUT_STATUS_NEWLY_DELETED)) {
                RuleOutputInfo::updateAll(['is_main' => RuleOutputInfo::IS_MAIN_YES], ['in', 'id', $params['main_output']]);
            }
        } else {
            return $this->error();
        }
        return $this->success('设置整改要求成功');
    }

    /**
     *全量场景查询
     * @return array
     */
    public function actionSceneList()
    {
        $select = ['st.id type_id', 'st.name type_name', 's.scene_code', 's.scene_code_name'];
        $result = SceneType::GetAllScene($select);
        $data = [];
        foreach ($result as $v) {
            $data[$v['type_id']]['type_id'] = $v['type_id'];
            $data[$v['type_id']]['type_name'] = $v['type_name'];
            $code_info = [
                'scene_code' => $v['scene_code'],
                'scene_code_name' => $v['scene_code_name'],
            ];
            $data[$v['type_id']]['code_info'][] = $code_info;
        }
        if (isset($data[SceneType::SCENE_TYPE_ALL])) {
            unset($data[SceneType::SCENE_TYPE_ALL]);
        }
        return $this->success($data);
    }

    /**
     * 新需求的大场景问卷查询
     * @return array
     */
    public function actionSceneQuestionList()
    {
        $params = Yii::$app->request->bodyParams;
        $type = !empty($params['type']) ? $params['type'] : Question::TYPE_SCENE;
        $params['question_id'] = isset($params['question_id']) ? $params['question_id'] : '';
        $params['is_ir'] = isset($params['is_ir']) ? $params['is_ir'] : '';
        $where = [
            'and',
//            ['in', 's.scene_code', $code],
            ['=', 'q.type', $type],
            ['or',
                ['=', 'q.id', $params['question_id']],
                ['like', 'q.title', $params['question_id']]
            ],
            ['=', 'is_ir', $params['is_ir']],
            ['q.status' => Question::DEL_STATUS_NORMAL],
            ['q.question_status' => Question::QUESTION_STATUS_ACTIVE]
        ];
        // 场景问卷需要筛选场景
        if ($params['scene_type'] != [SceneType::SCENE_TYPE_ALL]) {
            $scenes = Scene::getSmallScene(['scenes_type_id' => $params['scene_type'], 'scenes_code' => $params['scene_code']]);
            $code = array_column($scenes, 'scene_code');
            $where[] = ['in', 's.scene_code', $code];
        }

        $select = ['q.id', 'q.type', 's.scene_code_name scene_type_label', 'q.title', 'scene_code', 'is_ir'];
        $data = Question::GetSceneQuestionList($where, $select);
        return $this->success($data);
    }

    /**
     * 设置检查项目生动化标准
     * @return array
     */
    public function actionVividnessOutputSave()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            if (!empty($params['set_vividness'])) {
                Standard::updateAll(['set_vividness' => $params['set_vividness']], ['id' => $params['standard_id']]);
                //初始化生动项
                RuleOutputInfo::updateAll(['is_vividness' => RuleOutputInfo::IS_VIVIDNESS_NO], ['standard_id' => $params['standard_id']]);
                if (isset($params['vividness_output']) && !empty($params['vividness_output'])) {
                    $output = [];
                    foreach ($params['vividness_output'] as $v) {
                        if (empty($v['output'])) {
                            return $this->error('有生动化场景未配置输出项，请检查');
                        }
                        $output = array_merge($output, $v['output']);
                    }
                    RuleOutputInfo::updateAll(['is_vividness' => RuleOutputInfo::IS_VIVIDNESS_YES], ['id' => $output]);
                }
            }
        } else {
            return $this->error();
        }
        return $this->success('设置生动化项成功');
    }

    /**
     * 返回bu列表
     * @return array
     */
    public function actionBuList()
    {
        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != User::COMPANY_CODE_ALL) {
            $result = OrganizationRelation::findOneArray(['company_code' => $user['company_code'], 'bu_code' => $user['bu_code']]);
            $company_bu = $user['company_code'] . '_' . $user['bu_code'];
            $bu_name = $result['bu_name'];
            $data[] = [
                'company_bu' => $company_bu,
                'bu_name' => $bu_name
            ];
        } else {
            $result = OrganizationRelation::find()->groupBy(['company_code', 'bu_code', 'bu_name'])->asArray()->all();
            foreach ($result as $v) {
                $company_bu = $v['company_code'] . '_' . $v['bu_code'];
                $bu_name = $v['bu_name'];
                $data[] = [
                    'company_bu' => $company_bu,
                    'bu_name' => $bu_name
                ];
            }
        }
        $params = Yii::$app->request->bodyParams;
        if (!isset($params['has_3004']) || $params['has_3004']) {
            array_unshift($data, ['company_bu' => User::COMPANY_CODE_ALL . '_', 'bu_name' => User::COMPANY_CODE_ALL_LABEL]);
        }
        // federated 超时问题尝试
        Yii::$app->db2->close();
        return $this->success($data);
    }

    /**
     * 查询规则是否使用过
     * @return array
     */
    public function actionStandardStatus()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['standard_id'])) {
            return $this->error();
        }
        $result = Plan::getStandardStatus($params);
        $is_used = $result ? true : false;
        return $this->success(['standard_id' => $params['standard_id'], 'is_used' => $is_used]);
    }

    /**
     * 保存生动化项与规则引擎输出项对应关系
     * @return array
     */
    public function actionLivelyWithRuleOutput()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['standard_id', 'scenes', 'is_need_qc'])) {
            return $this->error();
        }
        if ($params['is_need_qc'] == Standard::IS_NEED_QC_YES) {
            if (!isset($params['need_qc_data']) || !$params['need_qc_data']) {
                return $this->error('需要qc的生动化数据 不能为空');
            }
        }
        if (isset($params['is_protocol']) && $params['is_protocol']) {
            foreach ($params['scenes'] as $v) {
                if (!isset($v['outputList']) || !$v['outputList']) {
                    return $this->error('协议类每个生动化下必须绑定输出项');
                }
            }
        } else {
            foreach ($params['scenes'] as $v) {
                if (!isset($v['outputList']) || !$v['outputList']) {
                    if (isset($flag2)) {
                        return $this->error('非协议类下挂载的输出项机制，必须为全部挂载或全部不挂载');
                    }
                    $flag1 = true;
                } else {
                    if (isset($flag1)) {
                        return $this->error('非协议类下挂载的输出项机制，必须为全部挂载或全部不挂载');
                    }
                    $flag2 = true;
                }
            }
        }
        $protocol_change = Standard::checkProtocol($params);
        if ($protocol_change[0] && $protocol_change[1]) {
            return $this->error('规则绑定协议有变化，请检查');
        }
        $where = ['id' => $params['standard_id']];
        $params['is_change'] = Standard::NOT_CHANGE;
        $params['scenes'] = json_encode($params['scenes']);
        $params['need_qc_data'] = json_encode($params['need_qc_data']);
        $result = Standard::editStandard($where, $params);
        if ($result[0]) {
            Standard::updateAll(['setup_step' => Standard::SETUP_STEP_TRANSFORM], ['id' => $params['standard_id']]);
            return $this->success($result[1]);
        } else {
            return $this->error($result[1]);
        }
    }

    /**
     * 获取检查项目关联的ZFT协议信息
     * @method post
     * @return array ZFT协议信息
     */
    public function actionStandardProtocolDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['standard_id'])) {
            return $this->error();
        }

        $protocol = [];
        //获取检查项目
        $standard = Standard::findOne(['id' => $params['standard_id']]);
        if (empty($standard)) {
            return $this->error("检查项目不存在");
        }

        if ($standard['protocol_id'] > 0) {
            //获取关联的ZFT协议信息
            if (!empty($standard->protocol)) {
                $protocol = $standard->protocol->getAttributes(null, ["id"]);
                $protocol['protocol_id'] = $standard['protocol_id'];
            }
            $protocol['protocol_type'] = $standard['check_type_id'];
        }
        return $this->success($protocol);
    }

    /**
     * 根据协议编号搜索协议
     * @return array
     */
    public function actionStandardProtocolSearch()
    {
        $params = Yii::$app->request->bodyParams;
        if (!$this->check($params, ['contract_code'])) {
            return $this->error();
        }

        //1、curl 调用SamrtMEDI系统，获取协议信息
        $request_url = Yii::$app->params['zft_url'] . '/api/getContractList';
        $user = Yii::$app->params['user_info'];
        $request_params = [
            'companyCode' => $user['company_code'],
            'contractCode' => $params['contract_code']
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
                $exist_contract_info = ProtocolTemplate::findOne(['contract_id' => $contract['contractID'], 'company_code' => $user['company_code']]);
                //3、更新或保存对应的协议数据
                if ($exist_contract_info) {
                    $exist_contract_info->load($convert_contract, '');
                    $exist_contract_info->company_code = $user['company_code'];
                    $exist_contract_info->save();
                    $convert_contract['protocol_id'] = $exist_contract_info->id;
                } else {
                    $model = new ProtocolTemplate();
                    $model->load($convert_contract, '');
                    $model->company_code = $user['company_code'];
                    $model->save();
                    $convert_contract['protocol_id'] = $model->id;
                }
            }
        }
        //4、返回协议信息
        if ($convert_contract) {
            //现在检查项目类型也由ZFT带过来
            $result = [];
            if ($convert_contract['contract_type'] == 11) {
                $result['check_type'] = CheckType::SHORT_AGREEMENTS;
            } else {
                $result['check_type'] = CheckType::LONG_AGREEMENTS;
            }
            $result['convert_contract'] = $convert_contract;
            return $this->success($result);
        } else {
            return $this->error("该协议code不存在");
        }
    }

    /**
     * 阿拉伯数字转中文字符
     * @param $num
     * @return string
     */
    public function numeral($num)
    {
        $china = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        $arr = str_split($num);
        $data = '';
        for ($i = 0; $i < count($arr); $i++) {
            $data .= $china[$arr[$i]];
        }
        return $data;
    }

    /**
     * 测试方法
     */
    public function actionTest()
    {
        $where = ['i.survey_code' => '9b042ea5-fc9b-445d-a2fa-7daf5af4c1ec', 'i.status' => Image::DEL_STATUS_NORMAL];
        $result = Image::getAllToCalculation($where);
        $queue = true;
        $has_standard = false;
        foreach ($result as $v) {
            if ($v['standard_id'] != 0) {
                $has_standard = true;
                $res['scene_code'] = $v['scene_code'];
                $res['sub_activity_id'] = $v['sub_activity_id'];
                $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                $send_list[$v['standard_id']]['results'][$v['scene_id']]['scene_code'] = $res['scene_code'];
                $send_list[$v['standard_id']]['results'][$v['scene_id']]['sub_activity_id'] = $res['sub_activity_id'];
                $send_list[$v['standard_id']]['results'][$v['scene_id']]['result'] = $res['result'];
                $send_list[$v['standard_id']]['results'][$v['scene_id']]['questionnaires'][$v['question_id']] = $v['answer'];
                $send_list[$v['standard_id']]['img_list'][] = $v['img_id'];
//                    $send_list[$v['standard_id']]['tool_id'] = $v['tool_id'];
                $standard_list[] = $v['standard_id'];
                $img_list[] = $v['img_id'];
            } else {
                $res['scene_code'] = $v['scene_code'];
                $res['result'] = !empty($v['result']) ? json_decode($v['result'], true) : [];
                $results[$v['scene_id']]['scene_code'] = $res['scene_code'];
                $results[$v['scene_id']]['result'] = $res['result'];
                $results[$v['scene_id']]['questionnaires'][$v['question_id']] = $v['answer'];
                $img_list[] = $v['img_id'];
            }
        }
        print_r($result);
        die;
    }


    public function mergeQuestion($questions)
    {
        $questionnaire = [];
        foreach ($questions as $v) {
            if (isset($v['question_id']) && isset($v['question_id'])) {
                if (isset($questionnaire[$v['question_id']])) {
                    if (is_numeric($v['answer'])) {
                        if ($v['merge_type'] == 1) {
                            $questionnaire[$v['question_id']] = $questionnaire[$v['question_id']] + $v['answer'];
                        } else if ($v['merge_type'] == 2) {
                            $questionnaire[$v['question_id']] = ($questionnaire[$v['question_id']] == 1 && $v['answer'] == 1 ? 1 : 0);
                        } else if ($v['merge_type'] == 3) {
                            $questionnaire[$v['question_id']] = ($questionnaire[$v['question_id']] == 1 || $v['answer'] == 1 ? 1 : 0);
                        }
                    }
                } else {
                    $questionnaire[$v['question_id']] = $v['answer'];
                }
            }
        }
        return $questionnaire;
    }

    public function actionSearchStandardList()
    {
        $params = Yii::$app->request->bodyParams;
        return Standard::searchStandard($params);
    }

    public function actionSearchToolList()
    {
        $params = Yii::$app->request->bodyParams;
        return Standard::searchTool($params);
    }

    /**
     * 在创建检查项目中的跳转规则引擎的时也要发送规则引擎需要的数据
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionSendInfoToEngine()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $transaction = Yii::$app->getDb()->beginTransaction();
            $change = 0;
            $items = [];
            $rule_data = [];
            $is_protocol = 1;
            $array = [];
            $scenes_info = Standard::findOneArray(['id' => $params['standard_id']], ['scenes']);
            if ($scenes_info) {
                $scenes = json_decode($scenes_info['scenes'], true);
                foreach ($scenes as $k => $v) {
                    //非协议类检查项目不允许添加同样的场景
                    $rule_need['scene_type'] = $v['scenes_type_id'];
                    $rule_need['scene_code'] = $v['scenes_code'];
                    if (!isset($v['activationID'])) {
                        $is_protocol = 0;
                        $scenes[$k]['sub_activity_name'] = isset($scenes[$k]['sub_activity_name'])
                        && $scenes[$k]['sub_activity_name'] ? $scenes[$k]['sub_activity_name'] : '生动化' . ($k + 1);
                    } else {
                        $scenes[$k]['sub_activity_name'] = isset($scenes[$k]['sub_activity_name'])
                        && $scenes[$k]['sub_activity_name'] ? $scenes[$k]['sub_activity_name'] : '生动化' . $v['activationID'] . '： ' . $v['activationName'];
                    }
                    $v['sub_activity_name'] = $scenes[$k]['sub_activity_name'];
                    $code_id = Scene::findAllArray(['in', 'scene_code', $v['scenes_code']], ['id']);
                    $code_array = array_column($code_id, 'id');
                    if (isset($v['scenes_type_id'][0]) && $v['scenes_type_id'][0] !== SceneType::SCENE_TYPE_ALL) {
                        $type_code = Scene::findAllArray(['in', 'scene_type', $v['scenes_type_id']], ['id']);
                        $type_code = array_column($type_code, 'id');
                        $code_array = array_merge($code_array, $type_code);
                    }
                    $code_array = array_unique($code_array);
                    if (!empty($v['question_manual_ir']) && count($code_array) > 1) {
                        $transaction->rollBack();
                        return $this->error('选则了手动模式问卷，就不能选择多个场景code，请检查');
                    }
                    $v['standard_id'] = $params['standard_id'];
                    if (empty($v['scenes_type_id']) || $v['scenes_type_id'][0] !== SceneType::SCENE_TYPE_ALL) {
                        $scenes_type = Scene::findAllArray(['in', 'scene_code', $v['scenes_code']], ['scene_type']);
                        $scenes_type = array_column($scenes_type, 'scene_type');
                        $v['scenes_type_id'] = array_merge($scenes_type, $v['scenes_type_id']);
                        $v['scenes_type_id'] = array_unique($v['scenes_type_id']);
                        $v['scenes_type_id'] = array_values($v['scenes_type_id']);
                    }
                    if (!isset($v['sub_activity_id'])) {
                        $sub_result = SubActivity::saveSubActivity($v);
                        if (!$sub_result[0]) {
                            $transaction->rollBack();
                            return $this->error($sub_result[1]);
                        }
                        $change = 1;
                        $scenes[$k]['sub_activity_id'] = $sub_result[1];
                    }
                    //拼接问题类型入数组且验证问卷
                    $rule_need['questionnaire'] = [];
                    $question_manual_id = array_column($v['question_manual'], 'id');
//                $question_type_arr = Question::findAllArray(['in', 'id', $question_manual_id], ['question_type', 'title', 'scene_type_id', 'id']);
                    $alias = 'q';
                    $join = [];
                    $join[] = [
                        'type' => 'LEFT JOIN',
                        'table' => QuestionOption::tableName() . ' qo',
                        'on' => 'qo.question_id = q.id'
                    ];
                    $select = ['q.question_type', 'q.title', 'q.scene_type_id', 'q.id question_id', 'qo.id option_id', 'qo.name option_name', 'qo.value option_value'];
                    $where = [];
                    $where[] = 'and';
                    $where[] = ['in', 'q.id', $question_manual_id];
//                $where[] = ['qo.status' => QuestionOption::DEL_STATUS_NORMAL];
                    $question_type_arr = Question::findJoin($alias, $join, $select, $where);
                    foreach ($question_type_arr as $item) {
                        if (!in_array($item['scene_type_id'], $code_array) && (!isset($v['scenes_type_id'][0]) || $v['scenes_type_id'][0] != SceneType::SCENE_TYPE_ALL)) {
                            $transaction->rollBack();
                            return $this->error('非ir问卷：' . $item['title'] . '与所选场景不匹配，请检查！');
                        }
                        $rule_need['questionnaire'][$item['question_id']]['id'] = $item['question_id'];
                        $rule_need['questionnaire'][$item['question_id']]['title'] = $item['title'];
                        if ($item['question_type'] == Question::QUESTION_TYPE_SELECT) {
                            $rule_need['questionnaire'][$item['question_id']]['option_info'][] = [
                                'option_id' => $item['option_id'],
                                'option_value' => $item['option_value']
                            ];
                        }
                        $rule_need['questionnaire'][$item['question_id']]['question_type'] = $item['question_type'];
                    }
                    $rule_need['questionnaire'] = array_values($rule_need['questionnaire']);
                    $rule_need['questionnaire_ir'] = [];
                    $question_manual_ir_id = array_column($v['question_manual_ir'], 'id');
//                $question_type_arr = Question::findAllArray(['in', 'id', $question_manual_ir_id], ['question_type', 'title', 'scene_type_id', 'id']);
                    $alias = 'q';
                    $join = [];
                    $join[] = [
                        'type' => 'LEFT JOIN',
                        'table' => QuestionOption::tableName() . ' qo',
                        'on' => 'qo.question_id = q.id'
                    ];
                    $select = ['q.question_type', 'q.title', 'q.scene_type_id', 'q.id question_id', 'qo.id option_id', 'qo.name option_name', 'qo.value option_value'];
                    $where = [];
                    $where[] = 'and';
                    $where[] = ['in', 'q.id', $question_manual_ir_id];
//                $where[] = ['qo.status' => QuestionOption::DEL_STATUS_NORMAL];
                    $question_type_arr = Question::findJoin($alias, $join, $select, $where);
                    foreach ($question_type_arr as $item) {
                        if (!in_array($item['scene_type_id'], $code_array) && (!isset($v['scenes_type_id'][0]) || $v['scenes_type_id'][0] != SceneType::SCENE_TYPE_ALL)) {
                            $transaction->rollBack();
                            return $this->error('ir问卷：' . $item['title'] . '与所选场景不匹配，请检查！');
                        }
                        $rule_need['questionnaire_ir'][$item['question_id']]['id'] = $item['question_id'];
                        $rule_need['questionnaire_ir'][$item['question_id']]['title'] = $item['title'];
                        if ($item['question_type'] == Question::QUESTION_TYPE_SELECT) {
                            $rule_need['questionnaire_ir'][$item['question_id']]['option_info'][] = [
                                'option_id' => $item['option_id'],
                                'option_value' => $item['option_value']
                            ];
                        }
                        $rule_need['questionnaire_ir'][$item['question_id']]['question_type'] = $item['question_type'];
                    }
                    $rule_need['questionnaire_ir'] = array_values($rule_need['questionnaire_ir']);
                    $rule_need['sub_activity_id'] = $scenes[$k]['sub_activity_id'];
                    $rule_need['sub_activity_name'] = $scenes[$k]['sub_activity_name'];
                    $rule_need['sub_activity_describe'] = isset($scenes[$k]['describe']) ? $scenes[$k]['describe'] : '';
                    if (isset($v['scenes_type_id'][0]) && $v['scenes_type_id'][0] == SceneType::SCENE_TYPE_ALL) {
                        $rule_need['is_all_scene'] = 1;
                    } else {
                        $rule_need['is_all_scene'] = 0;
                    }
                    $items[] = $rule_need;
                }
                $rule_data['scene_list'] = $items;

                $data['is_change'] = $change;
                $where = ['id' => $params['standard_id']];
                $model = Standard::findOne($where, true);
                $model->scenes = json_encode($scenes);
                $model->save();

                $add_url = Yii::$app->params['url']['rule_host'] . 'api/engine/rule-save';
                $rule_data['standard_id'] = $params['standard_id'];
                $token[] = 'token:' . $params['token'];
                $engine_result = \Helper::curlQueryLog($add_url, $rule_data, true, 300, $token);
                if ($engine_result['code'] != 200) {
                    $transaction->rollBack();
                    return $this->error($engine_result);
                }

                $array['is_change'] = $change;
                if ($array['is_change'] == Standard::IS_CHANGE) {
                    RuleOutputInfo::updateAll(['status' => 0], ['standard_id' => $params['standard_id']]);
                }
                //sft判断是否协议有变化
                if ($is_protocol) {
                    $protocol_change = Standard::checkProtocol($params);
                    if ($protocol_change[0] && $protocol_change[1]) {
                        $transaction->rollBack();
                        return $this->error('规则绑定协议有变化，请检查');
                    }
                }
            }
            $transaction->commit();
            return $this->success($array);

        } else {
            return $this->error();
        }
    }

    /**
     * 带生动化层级的所有输出项
     * @return array
     */
    public function actionSubOutputDetail()
    {
        $params = Yii::$app->request->bodyParams;
        if ($this->check($params, ['standard_id'])) {
            $with = [['ruleOutputInfo' => function ($query) {
                $query->select('id, node_index, node_name, output_type type,  scene_type, scene_code, is_all_scene, sub_activity_id')->where(['status' => RuleOutputInfo::DEL_STATUS_NORMAL]);
            }]];
            $data = SubActivity::findJoin('', [], ['id', 'activation_name name',], ['standard_id' => $params['standard_id']], true, true, '', '', '', $with);
            $all_type = SceneType::findAllArray([], ['*'], 'id');
            $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
            foreach ($data as &$v) {
                RuleService::joinSceneAndOutput($v['ruleOutputInfo'], $all_type, $all_scene);
                $v['sub_activity_id'] = $v['id'];
                $v['outputs'] = $v['ruleOutputInfo'];
                unset($v['ruleOutputInfo']);
                unset($v['id']);
                foreach ($v['outputs'] as &$item) {
                    unset($item['sub_activity_id']);
                    unset($item['scene_type']);
                    unset($item['scene_code']);
                    unset($item['is_all_scene']);
                }
            }
            return $this->success($data);
        } else {
            return $this->error('无参数，请检查');
        }
    }

}