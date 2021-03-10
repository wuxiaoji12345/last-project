<?php

namespace api\models;

use Yii;
use yii\data\Pagination;

/**
 * This is the model class for table "{{%statistical_item}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property string $user_id 用户id
 * @property string $company_code 厂房code
 * @property string $bu_code bu_code
 * @property string $title 标题别名
 * @property string $engine_code 计算引擎配置id
 * @property int $setup_step 设置步骤0初始化，1设置规则，2完成设置
 * @property int $item_status 启用状态0未启用，1启用，2禁用
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class StatisticalItem extends baseModel
{
    const BU_FLAG = true;

    const SETUP_STEP_DEFAULT = 0;
    const SETUP_STEP_RULE = 1;
    const SETUP_STEP_FINISH = 2;

    const STATUS_DEFAULT = 0;       // 初始状态
    const STATUS_AVAILABLE = 1;     // 启用
    const STATUS_DISABLED = 2;      // 禁用

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%statistical_item}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['company_code', 'user_id'], 'required'],
            [['project_id', 'setup_step', 'item_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['title'], 'string', 'max' => 32],
            [['company_code', 'bu_code'], 'string', 'max' => 16],
            [['engine_code'], 'string', 'max' => 64],
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
            'user_id' => '用户id',
            'company_code' => '厂房code',
            'bu_code' => 'bu_code',
            'title' => '标题别名',
            'engine_code' => '计算引擎配置id',
            'setup_step' => '设置步骤0初始化，1设置规则，2完成设置',
            'item_status' => '启用状态0未启用，1启用，2禁用',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticalItemQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticalItemQuery(get_called_class());
    }

    /**
     * 统计项目列表查询
     * @param $where
     * @param $select
     * @param $params
     * @return array
     */
    public static function findStatisticalList($where, $select, $params)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        if (empty($params['page']) || $params['page'] < 0) {
            $query = self::find();
            $list = $query->select($select)
                ->andWhere($where)
                ->orderBy('created_at DESC')
                ->asArray()
                ->all();
            $count = count($list);
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        } else {
            $params['page'] -= 1;
            $pages = new Pagination(['pageSize' => $params['page_size'], 'page' => $params['page']]);
            $query = self::find();
            $list = $query->select($select)
                ->andWhere($where)
                ->orderBy('created_at DESC');
            $count = $list->count();
            $list = $list->offset($pages->offset)->limit($pages->limit)
                ->asArray()
                ->all();
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        }
    }

    /**
     * 新建或修改统计项目
     * @param $params
     * @return array
     */
    public static function addStatistical($params)
    {
        if (!empty($params['statistical_id'])) {
            $model = self::findOne(['id' => $params['statistical_id']]);
        } else {
            $model = new self;
        }
        if ($model) {
            $model->load($params, '');
            if ($model->save()) {
                return [true, $model->attributes['id'], $model->attributes['engine_code']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, '新建统计规则失败或standard_id不存在'];
        }
    }

    /**
     * 保存规则配置id
     * @param $id
     * @param $rule_code
     * @return array
     */
    public static function saveRuleCode($id, $rule_code)
    {
        $model = self::findOne(['id' => $id], true);
        $user = Yii::$app->params['user_info'];
        if ($model) {
            if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
                return [false, '并非本区隔创建的统计项目，不允许保存规则配置'];
            }
            $model->engine_code = $rule_code;
            if ($model->save()) {
                return [true];
            } else {
                return [false, $model->getErrors()];
            }
        } else {
            return [false, '统计项目id不存在'];
        }
    }

    public static function statisticalFinish($data, $where)
    {
        $model = self::findOne($where, true);
        $user = Yii::$app->params['user_info'];
        if ($model) {
            if (!($user['company_code'] == $model->company_code && $user['bu_code'] == $model->bu_code)) {
                return [false, '并非本区隔创建的统计项目，不允许修改状态'];
            }
            if ($model->setup_step == self::SETUP_STEP_RULE || $model->setup_step == self::SETUP_STEP_FINISH) {
                $model->setup_step = self::SETUP_STEP_FINISH;
                $model->item_status = self::STATUS_AVAILABLE;
                if ($model->save()) {
                    return [true,''];
                } else {
                    return [false, $model->getErrors()];
                }
            } else {
                return [false, '步骤不对，不允许修改'];
            }
        } else {
            return [false, '统计项目id不存在'];
        }
    }
}
