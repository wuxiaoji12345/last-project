<?php

namespace api\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "sys_rule_output_info".
 *
 * @property int $id
 * @property int $standard_id 标准id
 * @property int $statistical_id 统计项目id
 * @property int $node_index 规则引擎id
 * @property string $node_name 输出项名称
 * @property int $output_type 输出项类型 1 数值型 2布尔型 3待定
 * @property int $is_all_scene 是否全场景 0 否 1 是
 * @property string $scene_type 输出项场景类型
 * @property string $scene_code 输出项场景code
 * @property string $sub_activity_id 子活动id
 * @property int $is_score 是否得分项 0 否 1是
 * @property int $is_main 是否主输出项 0 否 1 是
 * @property int $is_vividness 是否是生动化项 0否 1是
 * @property int $sort_id 大中台排序id
 * @property int $tag 最新变动状态 0正常 1最近新增 2最近删除
 * @property int $standard_status 输出项删除时规则的状态 0未启用 1已启用
 * @property string $formats 输出项格式化参数
 * @property int $status 删除状态 0删除 1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class RuleOutputInfo extends baseModel
{
    const IS_MAIN_YES = 1;
    const IS_MAIN_NO = 0;

    const IS_SCORE_YES = 1;
    const IS_SCORE_NO = 0;

    const OUTPUT_TYPE_DEFAULT = 0;
    const OUTPUT_TYPE_NUMBER = 1;
    const OUTPUT_TYPE_BOOL = 2;
    const OUTPUT_TYPE_OTHER = 3;

    const IS_VIVIDNESS_YES = 1;//是生动化项
    const IS_VIVIDNESS_NO = 0;//非生动化项

    const IS_ALL_SCENE_YES = 1;
    const IS_ALL_SCENE_NO = 0;

    const OUTPUT_STATUS_DEFAULT = 0; //最新变动状态 0正常
    const OUTPUT_STATUS_NEWLY_INCREASED = 1; //1最近新增
    const OUTPUT_STATUS_NEWLY_DELETED = 2; //2最近删除

    const STANDARD_START_YES = 1;
    const STANDARD_START_NO = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_rule_output_info';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['node_index', 'node_name'], 'required'],
            [['id', 'standard_id', 'statistical_id', 'node_index', 'sort_id', 'tag', 'status', 'created_at', 'updated_at'
                , 'output_type', 'is_main', 'is_score', 'is_vividness', 'sub_activity_id', 'is_all_scene', 'standard_status'], 'integer'],
            [['update_time'], 'safe'],
            [['node_name'], 'string', 'max' => 5000],
            [['scene_type'], 'string', 'max' => 255],
            [['scene_code'], 'string', 'max' => 1000],
            [['formats'], 'string', 'max' => 255],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'standard_id' => '标准id',
            'statistical_id' => '统计项目id',
            'node_index' => '规则引擎id',
            'node_name' => '输出项名称',
            'sort_id' => '大中台排序id',
            'tag' => '最新变动状态 0正常 1最近新增 2最近删除',
            'output_type' => '输出项类型 1 数值型 2布尔型 3待定',
            'is_all_scene' => '是否全场景 0 否 1 是',
            'scene_type' => '输出项场景类型',
            'scene_code' => '输出项场景code',
            'sub_activity_id' => '子活动id',
            'is_score' => '是否得分项 0 否 1是',
            'is_main' => '是否主输出项 0 否 1 是',
            'is_vividness' => '是否是生动化项 0否 1是',
            'standard_status' => '输出项删除时规则的状态 0未启用 1已启用',
            'formats' => '输出项格式化参数',
            'status' => '删除状态 0删除 1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

//    /**
//     * 获取output详情
//     * @param $where
//     * @param $select
//     * @return array|ActiveRecord[]
//     */
//    public static function findOutputInfo($where, $select)
//    {
//        return $data = self::find()
//            ->select($select)
//            ->where($where)
//            ->asArray()
//            ->all();
//    }

    /**
     * 获取output详情
     * @param $where
     * @param $select
     * @return array|ActiveRecord[]
     */
    public static function getOutputInfo($where, $select)
    {
        return self::find()
            ->select($select)
            ->where($where)
            ->orderBy('sort_id')
            ->groupBy('node_index')
            ->asArray()
            ->all();
    }

    public function getSubActivity()
    {
        return $this->hasOne(SubActivity::class, ['id' => 'sub_activity_id']);
    }

    /**
     * 批量插入或者更新引擎输出项
     * @param $id
     * @param $data
     * @return array
     * @throws \yii\db\Exception
     */
    public static function updateOutput($id, $data)
    {
        $sql1 = 'insert into ' . self::tableName() . ' (status,tag,node_index,node_name,output_type,is_all_scene,scene_type,scene_code,sub_activity_id,standard_id,statistical_id,standard_status,formats,created_at,updated_at,is_vividness) values ';
        $sql2 = 'on duplicate key update status = values(status),tag = values(tag),node_name = values(node_name),output_type = values(output_type),
        is_all_scene = values(is_all_scene),scene_type = values(scene_type),scene_code = values(scene_code),sub_activity_id = values(sub_activity_id),standard_status = values(standard_status),formats = values(formats),updated_at = values(updated_at),is_vividness = values(is_vividness)';

        $values = '';

        foreach ($data as $v) {
            $v = is_array($v) ? $v : json_decode($v, true);
            $output_type = isset($v['output_type']) ? $v['output_type'] : 0;
            $is_all_scene = (int)$v['is_all_scene'];
            $scene_type = json_encode($v['scene_type']);
            $scene_code = json_encode($v['scene_code']);
            $formats = json_encode($v['formats']);
            $standard_id = ($id[0] == 'standard_id') ? $id[1] : 0;
            $statistical_id = ($id[0] == 'statistical_id') ? $id[1] : 0;
            $standard_status = self::STANDARD_START_YES;
            $created_at = time();
            $updated_at = time();
            if ($values !== '') {
                $values .= ',';
            }
            if($v['sub_activity_id']){
                if (is_array($v['sub_activity_id'])) {
                    foreach ($v['sub_activity_id'] as $item) {
                        $values .= "(1, 1, '" . $v['node_index'] . "', '" . $v['node_name'] . "', '" . $output_type . "', '"
                            . $is_all_scene . "', '" . $scene_type . "', '" . $scene_code . "', '" . $item . "', '" . $standard_id . "', '" . $statistical_id . "', " . $standard_status . ",'" . $formats . "', " . $created_at . ", " . $updated_at . ", 1),";
                    }
                    $values = substr($values,0,-1);
                } else {
                    $sub_activity_id = $v['sub_activity_id'] ? $v['sub_activity_id'] : 0;
                    $values .= "(1, 1, '" . $v['node_index'] . "', '" . $v['node_name'] . "', '" . $output_type . "', '"
                        . $is_all_scene . "', '" . $scene_type . "', '" . $scene_code . "', '" . $sub_activity_id . "', '" . $standard_id . "', '" . $statistical_id . "', " . $standard_status . ",'" . $formats . "', " . $created_at . ", " . $updated_at . ", 1)";
                }
            } else {
                $values .= "(1, 1, '" . $v['node_index'] . "', '" . $v['node_name'] . "', '" . $output_type . "', '"
                    . $is_all_scene . "', '" . $scene_type . "', '" . $scene_code . "', 1, '" . $standard_id . "', '" . $statistical_id . "', " . $standard_status . ",'" . $formats . "', " . $created_at . ", " . $updated_at . ", 1)";
            }
        }
        $query = $sql1 . $values . $sql2 . ';';
        \Yii::$app->db->createCommand($query)->execute();
        return [true];
    }

    /**
     * 删除引擎输出项（逻辑删除）
     * @param $where
     * @param $id
     * @return array
     */
    public static function deleteOutput($where, $id)
    {
        if ($id[0] == 'standard_id') {
            $standard_model = Standard::findOne(['id' => $id[1]]);
            if (!$standard_model) {
                return [false, '无此检查项目，请检查'];
            }
            $standard_status = ($standard_model->standard_status == Standard::STATUS_DEFAULT) ? self::STANDARD_START_NO : self::STANDARD_START_YES;

        } else {
            $statistical_model = StatisticalItem::findOne(['id' => $id[1]]);
            if (!$statistical_model) {
                return [false, '无此重跑项目，请检查'];
            }
            $standard_status = ($statistical_model->item_status == StatisticalItem::STATUS_DEFAULT) ? self::STANDARD_START_NO : self::STANDARD_START_YES;
        }
        self::updateAll(['standard_status' => $standard_status, 'status' => self::DEL_STATUS_DELETE, 'tag' => self::OUTPUT_STATUS_NEWLY_DELETED], $where);
    }

    /**
     * 更新引擎输出项输出顺序
     * @param $id
     * @param $node_index
     * @param $sort_id
     * @return array
     */
    public static function updateSort($id, $node_index, $sort_id, $flag = false)
    {
        if ($flag) {
            $model = self::findOne(['statistical_id' => $id, 'node_index' => $node_index]);
        } else {
            $model = self::findOne(['standard_id' => $id, 'node_index' => $node_index]);
        }
        if ($model) {
            $model->sort_id = $sort_id;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                $err = $model->getErrStr();
                return [false, $err];
            }
        } else {
            return [false, '数据不存在'];
        }
    }

    public static function find()
    {
        return \Yii::createObject(ActiveQuery::class, [get_called_class()]);
    }

    /**
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午6:10
     *
     * @param array $standard_id_arr
     *
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getQcOutputInfo($standard_id_arr = [])
    {
        $query = self::find();

        //输出项删除时规则还未启用不展示在规则引擎结果里
        if (!empty($standard_id_arr)) {
            $query->andWhere(['standard_status' => RuleOutputInfo::STANDARD_START_YES, 'standard_id' => $standard_id_arr]);
        }

        return $query->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id', 'status', 'formats'])
            ->orderBy(['status' => SORT_DESC, 'sort_id' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * 根据检查项目获取所有的规则引擎结果列表
     *
     * @param int|array $standard_id_arr
     * @return array [{"node_index" => 11, "label" => "规则引擎输出项"}]
     */
    public static function getResultMapByStandard($standard_id_arr)
    {
        $list = self::find()
            ->andWhere(['standard_status' => RuleOutputInfo::STANDARD_START_YES, 'standard_id' => $standard_id_arr])
            ->orderBy(['status' => SORT_DESC, 'sort_id' => SORT_ASC])
            ->asArray()
            ->all();

        $map = [];
        foreach ($list as $item) {
            $map[] = ['node_index' => $item['node_index'], 'label' => $item['node_name']];
        }

        return $map;
    }

    /**
     * 获取单个输出配置信息
     * @param $standardId
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getResultMapByStandardId($standardId)
    {
        $ruleOutput = RuleOutputInfo::find()
            ->select(['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'sort_id', 'status', 'formats'])
            ->where(['and', ['=', 'status', 1], ['=', 'standard_id', $standardId]])->groupBy('standard_id,node_index')
            ->orderBy(['status' => SORT_DESC, 'sort_id' => SORT_ASC])
            ->indexBy('node_index')->asArray()->all();
        return $ruleOutput;
    }
}
