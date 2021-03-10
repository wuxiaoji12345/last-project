<?php

namespace api\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%survey_standard}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property string $survey_code 走访号
 * @property string $standard_id 检查项目id
 * @property string $user_id 用户id
 * @property string $company_code 厂房code
 * @property string $bu_code bu_code
 * @property int $check_type_id 检查类型
 * @property int $protocol_id 协议模板id
 * @property string $title 标题别名
 * @property string $image 图片
 * @property string $description 检查要求描述
 * @property string $engine_rule_code 规则配置id
 * @property int $set_rule 是否已经设置过规则 0 未设置 1 已设置
 * @property string $question_manual_ir ir问卷
 * @property string $question_manual 非ir问卷
 * @property string $scenes_ir_id 场景id 废弃
 * @property string $scenes 场景集合
 * @property int $setup_step 设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置整改, 5完成设置，6生动化映射
 * @property int $photo_type 拍照类别：0、普通模式，1随报随拍
 * @property int $standard_status 启用状态0未启用，1启用，2禁用
 * @property int $is_change 规则问卷修改状态，0无修改，1有修改
 * @property int $pos_score 标准满分
 * @property int $set_main 是否设置主检查项 0 初始状态 1 是 2 否
 * @property int $set_vividness 是否设置生动化项 0 初始状态 1 是 2 否
 * @property int $is_deleted 是否被用户删除，0:否 1:是
 * @property int $is_need_qc 是否需要qc：0初始状态，1需要qc，2不需要
 * @property int $need_qc_data 需要qc的生动化数据
 * @property int $is_standard_disable 是否是禁用的检查计划 0不是 1是禁用的（用于区分两种快照）
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SurveyStandard extends baseModel
{
    const IS_STANDARD_DISABLE_YES = 1;       //是禁用状态的检查项目
    const IS_STANDARD_DISABLE_NO = 0;      //不是

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey_standard}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'standard_id', 'check_type_id', 'protocol_id', 'set_rule', 'setup_step', 'photo_type',
                'standard_status', 'is_change', 'pos_score', 'set_main', 'set_vividness', 'is_deleted', 'status',
                'created_at', 'updated_at', 'is_need_qc', 'is_standard_disable'], 'integer'],
            [['image', 'description', 'question_manual_ir', 'question_manual', 'scenes_ir_id', 'scenes'], 'string'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['user_id', 'title'], 'string', 'max' => 32],
            [['company_code', 'bu_code'], 'string', 'max' => 16],
            [['engine_rule_code'], 'string', 'max' => 64],
            [['need_qc_data'], 'string', 'max' => 1000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'project_id' => '项目id',
            'survey_code' => '走访号',
            'standard_id' => '检查项目id',
            'user_id' => '用户id',
            'company_code' => '厂房code',
            'bu_code' => 'bu_code',
            'check_type_id' => '检查类型',
            'protocol_id' => '协议模板id',
            'title' => '标题别名',
            'image' => '图片',
            'description' => '检查要求描述',
            'engine_rule_code' => '规则配置id',
            'set_rule' => '是否已经设置过规则 0 未设置 1 已设置',
            'question_manual_ir' => 'ir问卷',
            'question_manual' => '非ir问卷',
            'scenes_ir_id' => '场景id 废弃',
            'scenes' => '场景集合',
            'setup_step' => '设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置整改, 5完成设置，6生动化映射',
            'photo_type' => '拍照类别：0、普通模式，1随报随拍',
            'standard_status' => '启用状态0未启用，1启用，2禁用',
            'is_change' => '规则问卷修改状态，0无修改，1有修改',
            'pos_score' => '标准满分',
            'set_main' => '是否设置主检查项 0 初始状态 1 是 2 否',
            'set_vividness' => '是否设置生动化项 0 初始状态 1 是 2 否',
            'is_deleted' => '是否被用户删除，0:否 1:是',
            'is_need_qc' => '是否需要qc：0初始状态，1需要qc，2不需要',
            'need_qc_data' => '需要qc的生动化数据',
            'is_standard_disable' => '是否是禁用的检查计划 0不是 1是禁用的（用于区分两种快照）',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 根据走访号和检查项目id获取检查项目数据
     *
     * @param $survey_list
     *
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getTitleByIds($survey_list)
    {
        return self::find()->select(['id', 'survey_code', 'standard_id', 'title', 'need_qc_data'])
            ->where(['survey_code' => $survey_list])->indexBy('survey_code')->asArray()->all();
    }

    /**
     * 获取检查类型名称
     *
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午1:56
     *
     * @param $survey_list_all
     *
     * @return Replan[]|array|ActiveRecord[]
     */
    public static function getCheckTypeNameByIds($survey_list_all)
    {
        return self::find()
            ->select(['id' => 's.standard_id', 't.title'])
            ->alias('s')
            ->leftJoin(CheckType::tableName() . ' t', 's.check_type_id=t.id')
            ->where(['s.survey_code' => $survey_list_all])
            ->indexBy('standard_id')
            ->asArray()
            ->all();
    }
}
