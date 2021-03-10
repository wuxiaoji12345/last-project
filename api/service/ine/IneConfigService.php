<?php


namespace api\service\ine;


use api\models\CheckType;
use api\models\IneChannel;
use api\models\IneConfig;
use api\models\IneConfigSnapshot;
use api\models\RuleOutputInfo;
use api\models\share\Scene;
use api\models\share\SceneType;
use api\models\Standard;
use common\libs\file_log\LOG;
use Yii;
use yii\helpers\ArrayHelper;

class IneConfigService
{
    /**
     * 获取单个ine渠道的所有ine配置，树形结构
     *
     * @param $ine_channel_id
     * @return array
     */
    public static function getListTreeByChannelId($ine_channel_id)
    {
        //获取指定ine渠道的所有ine配置
        $ine_config_list = IneConfig::find()
            ->where([
                'ine_channel_id' => $ine_channel_id
            ])
            ->select(['id', 'p_id', 'title', 'output_type', 'max_score', 'level', 'rule_output_id', 'node_index', 'report_examer', 'report_admin'])
            ->orderBy(['sort' => SORT_ASC])
            ->asArray()->all();
        //获取映射的所有引擎输出项信息，拼接配置输出项名称、场景名称
        $rule_output_id_arr = ArrayHelper::getColumn($ine_config_list, 'rule_output_id', []);
        $rule_output_list = RuleOutputInfo::findAllArray(['id' => $rule_output_id_arr], ['*'], 'id');
        $rule_output_list = self::joinSceneNameWithOutput($rule_output_list);
        foreach ($ine_config_list as &$ine_config) {
            $ine_config['node_name'] = isset($rule_output_list[$ine_config['rule_output_id']]) ? $rule_output_list[$ine_config['rule_output_id']]['node_name'] : '';
            $ine_config['scence_name'] = isset($rule_output_list[$ine_config['rule_output_id']]) ? $rule_output_list[$ine_config['rule_output_id']]['scence_name'] : '';
        }
        //转为树形结构
        return self::getTree($ine_config_list);
    }

    /**
     * 配置暂存
     *
     * @param $ine_channel_id
     * @param $standard_id
     * @param array $ine_config_list
     * @return bool
     */
    public static function save($ine_channel_id, $standard_id, $ine_config_list = [])
    {
        $transaction = Yii::$app->getDb()->beginTransaction();
        try {
            //更新ine_channel表，standard_id字段和ine_status改为2(暂存状态)
            IneChannel::updateAll([
                'standard_id' => $standard_id,
                'ine_status' => IneChannel::INE_STATUS_SAVED
            ], ['id' => $ine_channel_id]);
            //批量更新ine_config表，保存配置信息
            foreach ($ine_config_list as $ine_config) {
                IneConfig::updateAll([
                    'rule_output_id' => $ine_config['rule_output_id'],
                    'node_index' => $ine_config['node_index'],
                    'report_examer' => $ine_config['report_examer'],
                    'report_admin' => $ine_config['report_admin'],
                ], [
                    'ine_channel_id' => $ine_channel_id,
                    'id' => $ine_config['ine_config_id'],
                ]);
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            LOG::log('INE配置暂存失败，'. $e->getMessage() . ' line:'. $e->getLine());
            throw new \Exception('暂存失败');
        }
        return true;
    }

    /**
     * 配置保存并生效
     *
     * @param $ine_channel_id
     * @param $standard_id
     * @param array $ine_config_list
     * @return bool
     * @throws \Exception
     */
    public static function publish($ine_channel_id, $standard_id, $ine_config_list = [])
    {
        //1、校验数据
        //1-1、判断检查项目是否已启用、是否已被绑定
        $standard = Standard::findOneArray([
            'id' => $standard_id,
            'standard_status' => Standard::STATUS_AVAILABLE,
            'check_type_id' => CheckType::INE['check_type_id'],
            'is_deleted' => Standard::NOT_DELETED
        ]);
        if (!$standard) {
            throw new \Exception('检查项目尚未启用，请先确认');
        }
        $ine_channel = IneChannel::findOneArray(['standard_id' => $standard_id, 'ine_status' => IneChannel::INE_STATUS_PUBLISHED]);
        if ($ine_channel && $ine_channel['id'] != $ine_channel_id) {
            throw new \Exception('检查项目已被绑定，请先确认');
        }
        //1-2、判断所有INE细分指标项是否都配置了映射
        $ine_config_all = IneConfig::findAllArray(['ine_channel_id' => $ine_channel_id], ['*'], 'id');
        //去除2、3级无得分(其他指标)的细分项
        $ine_other_configs = [];
        foreach ($ine_config_all as $key => $ine_config) {
            if ($ine_config['level'] == IneConfig::SUBDIVISION_LEVEL_TWO && intval($ine_config['max_score']) == 0 ||
                $ine_config['level'] == IneConfig::SUBDIVISION_LEVEL_THREE && intval($ine_config['max_score']) == 0) {
                $ine_other_configs[$key] = $ine_config;
                unset($ine_config_all[$key]);
            }
        }
        $ine_config_id_all = ArrayHelper::getColumn($ine_config_all, 'id', []);
        $ine_config_ids = ArrayHelper::getColumn($ine_config_list, 'ine_config_id', []);
        $ine_config_rule_output_ids = ArrayHelper::getColumn($ine_config_list, 'rule_output_id', []);
        //判断传参配置输出项是否与配置表所有细分项数量一致
        if (array_diff($ine_config_id_all, $ine_config_ids)) {
            throw new \Exception('仍有INE细分指标项未配置映射，请先完成');
        }
        //判断传参配置输出项ID是否全不为0
        if (in_array(0, $ine_config_rule_output_ids) || in_array('0', $ine_config_rule_output_ids)) {
            throw new \Exception('仍有INE细分指标项未配置映射，请先完成');
        }
        //1-3、判断规则输出项类型是否被删除
        $ine_config_rule_output_list = RuleOutputInfo::findAllArray(['id' => $ine_config_rule_output_ids], ['*'], 'id');
        if (count($ine_config_rule_output_list) < count($ine_config_list)) {
            throw new \Exception('有N条规则引擎输出项被删除，请完成全部指标项的配置');
        }
        //1-4、判断INE细分项与规则输出项类型不匹配
        foreach ($ine_config_list as $key => $ine_config) {
            if (($ine_config_all[$ine_config['ine_config_id']]['output_type'] == IneConfig::OUTPUT_TYPE_BOOL && $ine_config_rule_output_list[$ine_config['rule_output_id']]['output_type'] != RuleOutputInfo::OUTPUT_TYPE_BOOL) ||
                in_array($ine_config_all[$ine_config['ine_config_id']]['output_type'], [IneConfig::OUTPUT_TYPE_NUMBER, IneConfig::OUTPUT_TYPE_OTHER])  && !in_array($ine_config_rule_output_list[$ine_config['rule_output_id']]['output_type'], [RuleOutputInfo::OUTPUT_TYPE_NUMBER])) {
                throw new \Exception('仍有INE细分项与规则输出项类型不匹配，请先确认');
            }
        }
        $now = time();
        $update_user = ArrayHelper::getValue(Yii::$app->params, 'user_info.user_id', 0);
        $transaction = Yii::$app->getDb()->beginTransaction();
        try {
            //2、更新ine_channel表，standard_id字段和ine_status改为1(保存并生效)
            IneChannel::updateAll([
                'standard_id' => $standard_id,
                'ine_status' => IneChannel::INE_STATUS_PUBLISHED,
                'last_publish_time' => $now
            ], ['id' => $ine_channel_id]);
            foreach ($ine_config_list as $ine_config) {
                //3、批量更新ine_config表，保存配置信息
                IneConfig::updateAll([
                    'rule_output_id' => $ine_config['rule_output_id'],
                    'node_index' => $ine_config['node_index'],
                    'report_examer' => $ine_config['report_examer'],
                    'report_admin' => $ine_config['report_admin'],
                    'update_user' => $update_user,
                ], [
                    'id' => $ine_config['ine_config_id'],
                ]);
                //4、批量创建ine_config_snapshot表数据
                unset($ine_config_all[$ine_config['ine_config_id']]['created_at'], $ine_config_all[$ine_config['ine_config_id']]['updated_at']);
                $ine_config_snapshot_model = new IneConfigSnapshot();
                $ine_config_snapshot_model->load($ine_config_all[$ine_config['ine_config_id']], '');
                $ine_config_snapshot_model->ine_config_timestamp_id = $now;
                $ine_config_snapshot_model->ine_config_id = $ine_config['ine_config_id'];
                $ine_config_snapshot_model->ine_channel_id = $ine_channel_id;
                $ine_config_snapshot_model->standard_id = $standard_id;
                $ine_config_snapshot_model->rule_output_id = $ine_config['rule_output_id'];
                $ine_config_snapshot_model->node_index = $ine_config['node_index'];
                $ine_config_snapshot_model->report_examer = $ine_config['report_examer'];
                $ine_config_snapshot_model->report_admin = $ine_config['report_admin'];
                $ine_config_snapshot_model->update_user = $update_user;
                $ine_config_snapshot_model->created_at = $now;
                $ine_config_snapshot_model->updated_at = $now;
                if (!$ine_config_snapshot_model->save()) {
                    throw new \Exception('快照表保存失败，'.$ine_config_snapshot_model->getErrStr());
                }
            }
            foreach ($ine_other_configs as $ine_config) {
                //5、批量创建其他指标的ine_config_snapshot表数据
                unset($ine_config_all[$ine_config['id']]['created_at'], $ine_config_all[$ine_config['id']]['updated_at']);
                $ine_config_snapshot_model = new IneConfigSnapshot();
                $ine_config_snapshot_model->load($ine_other_configs[$ine_config['id']], '');
                $ine_config_snapshot_model->ine_config_timestamp_id = $now;
                $ine_config_snapshot_model->ine_config_id = $ine_config['id'];
                $ine_config_snapshot_model->ine_channel_id = $ine_channel_id;
                $ine_config_snapshot_model->standard_id = $standard_id;
                $ine_config_snapshot_model->update_user = $update_user;
                $ine_config_snapshot_model->created_at = $now;
                $ine_config_snapshot_model->updated_at = $now;
                if (!$ine_config_snapshot_model->save()) {
                    throw new \Exception('其他指标快照表保存失败，'.$ine_config_snapshot_model->getErrStr());
                }
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            LOG::log('INE配置保存并生效失败，'. $e->getMessage() . ' line:'. $e->getLine());
            throw new \Exception('保存并生效失败');
        }
        return true;
    }

    /**
     * 数组转为分级层级结构
     *
     * @param array $data
     * @param int $pid
     * @param string $children
     * @param string $id
     * @return array
     */
    public static function getTree($data = [], $pid = 0, $children = 'children', $id = 'id')
    {
        $tree = [];
        foreach($data as $k => $v) {
            if($v['p_id'] == $pid) {
                //找到所有儿子节点
                $v[$children] = self::getTree($data, $v[$id], $children, $id);
                $tree[] = $v;
            }
        }
        return $tree;
    }

    /**
     * 规则引擎输出项列表加场景名称字段
     *
     * @param $rule_output_list
     * @return mixed
     */
    public static function joinSceneNameWithOutput(&$rule_output_list)
    {
        $all_type = SceneType::findAllArray([], ['*'], 'id');
        $all_scene = Scene::findAllArray([], ['*'], 'scene_code');
        foreach ($rule_output_list as &$v) {
            $name = '';
            if ($v['is_all_scene'] == RuleOutputInfo::IS_ALL_SCENE_YES) {
                $name = '全场景';
            }
            $scene_type = is_array($v['scene_type']) ? $v['scene_type'] : json_decode($v['scene_type'], true);
            $scene_code = is_array($v['scene_code']) ? $v['scene_code'] : json_decode($v['scene_code'], true);
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
            $v['scence_name'] = $name;
            unset($v['scene_type'], $v['scene_code'], $v['is_all_scene']);
        }
        return $rule_output_list;
    }
}