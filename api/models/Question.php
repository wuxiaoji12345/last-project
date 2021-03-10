<?php

namespace api\models;

use api\models\share\Scene;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "{{%question}}".
 *
 * @property int $id 主键id
 * @property string $title 问卷名称
 * @property string $company_code 厂房
 * @property string $bu_code BU
 * @property string $user_id 用户
 * @property int $business_type_id 业务类型 id
 * @property int $question_type 问题题型 1是非，2填空, 3可选填写填，4选择题
 * @property int $type 问卷类型1售点，2场景
 * @property int $scene_type_id 场景类型id
 * @property int $merge_type 答案合并的类型: 0无，1相加，2全部“是”，3任意为“是”
 * @property int $is_ir 是否IR问卷：0非IR，1是IR，2共享
 * @property string $content 问题内容
 * @property int $required 是否必填：0非必填，1必填
 * @property int $question_status 0.未启用；1.启用；2.禁用；
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 *
 * @property array $options db更新时间
 */
class Question extends baseModel
{
    const HAS_3004 = true;
    const BU_FLAG = true;

    const ENABLE_STATUS_FIELD = 'question_status';
    public $options;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%question}}';
    }

    const TYPE_STORE = 1;               // 售点
    const TYPE_SCENE = 2;               // 场景
    const TYPE_ARR = [
        self::TYPE_STORE => '售点',
        self::TYPE_SCENE => '场景'
    ];

    const QUESTION_TYPE_BOOL = 1;       // 是非
    const QUESTION_TYPE_INPUT = 2;      // 填空
    const QUESTION_TYPE_INPUT_SELECT = 3;      // 可选填空
    const QUESTION_TYPE_SELECT = 4;      // 选择

    const QUESTION_TYPE_ARRAY = [
        self::QUESTION_TYPE_BOOL,
        self::QUESTION_TYPE_INPUT,
        self::QUESTION_TYPE_INPUT_SELECT,
        self::QUESTION_TYPE_SELECT,
    ];

    const QUESTION_LABEL = [
        self::QUESTION_TYPE_BOOL => '是非题',
        self::QUESTION_TYPE_INPUT => '填空题',
        self::QUESTION_TYPE_INPUT_SELECT => '可选填空题',
        self::QUESTION_TYPE_SELECT => '选择题',
    ];

    const MERGE_TYPE_NULL = 0;      // 无
    const MERGE_TYPE_ADD = 1;       // 相加
    const MERGE_TYPE_ALL = 2;       // 全部为“是”
    const MERGE_TYPE_ONE = 3;       // 任意为“是”

    const MERGE_TYPE_ARR = [
        self::MERGE_TYPE_NULL => '无',
        self::MERGE_TYPE_ADD => '相加',
        self::MERGE_TYPE_ALL => '全部为“是”',
        self::MERGE_TYPE_ONE => '任意为“是”',
    ];

    const QUESTION_STATUS_DEFAULT = 0;      // 未启用
    const QUESTION_STATUS_ACTIVE = 1;       // 启用
    const QUESTION_STATUS_DISABLE = 2;      // 禁用

    const REQUIRED_NO = 0;                  // 非必填
    const REQUIRED_YES = 1;                 // 必填

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['question_type', 'type', 'required', 'company_code', 'user_id'], 'required'],
            [['question_type', 'type', 'scene_type_id', 'merge_type', 'is_ir', 'question_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['title'], 'string', 'max' => 100],
            [['content'], 'string', 'max' => 50],
            ['question_status', 'in', 'range' => [self::QUESTION_STATUS_DEFAULT, self::QUESTION_STATUS_ACTIVE, self::QUESTION_STATUS_DISABLE]],
            ['question_type', 'in', 'range' => self::QUESTION_TYPE_ARRAY],
            ['merge_type', 'in', 'range' => [self::MERGE_TYPE_NULL, self::MERGE_TYPE_ADD, self::MERGE_TYPE_ALL, self::MERGE_TYPE_ONE]],
            ['type', 'in', 'range' => [self::TYPE_STORE, self::TYPE_SCENE]],
            ['required', 'in', 'range' => [self::REQUIRED_NO, self::REQUIRED_YES]],
            ['scene_type_id', 'default', 'value' => 0],
            ['merge_type', 'default', 'value' => 0],
            [['question_type'], 'validateOption'],
            [['question_type'], 'validateIr'],
            [['bu_code', 'business_type_id', 'options'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'title' => '问卷名称',
            'business_type_id' => '业务类型',
            'question_type' => '问题题型',
            'company_code' => '厂房',
            'bu_code' => 'BU',
            'user_id' => '用户ID',
            'type' => '问卷类型',
            'scene_type_id' => '场景类型id',
            'merge_type' => '答案合并的类型',
            'is_ir' => 'IR问卷',
            'content' => '问题内容',
            'required' => '是否必填',
            'question_status' => '问卷状态',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }
    /**
     * 查询问卷列表
     * pager = ['page'=> 3, 'pageSize'=> 20] pageSize 尽量不要大于2000
     * @param $select
     * @param $where
     * @param $pager
     * @param bool $arr_flag
     * @param array $order
     * @return array
     */
    public static function getList($select, $where, $pager, $arr_flag = true, $order = [])
    {

        $query = Question::find();
        $query->select($select);
        $query->andFilterWhere(['or', ['=', new Expression(self::tableName() . '.id'), $where['question_keywords']],
            ['like', new Expression(self::tableName() . '.title'), $where['question_keywords']]]);
        $query->andFilterWhere(['type' => $where['question_type']]);
//        $query->andFilterWhere(['=', new Expression(Question::tableName() . '.scene_type_id'), $where['scene_type']]);
        $query->andFilterWhere(['is_ir' => $where['is_ir']]);

        $scenes = Scene::getSmallScene(['scenes_type_id' => $where['scenes_type_id'], 'scenes_code' => $where['scenes_code']]);
        $scenes_code = array_column($scenes, 'id');
        $query->andFilterWhere(['in', new Expression(Question::tableName() . '.scene_type_id'), $scenes_code]);

        if ($where['created_start'] != '') {
            $query->andFilterWhere(['>=', new Expression(self::tableName() . '.created_at'), $where['created_start']]);
        }
        if ($where['created_end'] != '') {
            $query->andFilterWhere(['<=', new Expression(self::tableName() . '.created_at'), $where['created_end']]);
        }

        $bu_condition = User::getBuCondition(self::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        $query->andWhere(['=', new Expression(self::tableName() . '.status'), self::DEL_STATUS_NORMAL]);

        // company_bu 字段要特殊处理
        User::buFilterSearch($query, $where['company_bu'], Question::class);

        if ($arr_flag) {
            $query->asArray();
        }
        if (!empty($order)) {
            $query->orderBy($order);
        }
        $count = $query->count();

        $page = $pager['page'];
        $pageSize = $pager['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);
        $data = $query->all();
        return ['list' => $data, 'count' => (int)$count];
    }

    public static function getOneQuestion($select, $question_id)
    {
        $query = Question::find();
        $query->andWhere(['and',
            ['=', self::tableName() . '.id', $question_id],
            ['=', self::tableName() . '.status', Question::DEL_STATUS_NORMAL],
        ]);
        $query->select($select);

        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(static::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        return $query->asArray()->one();
    }

    /**
     * 获取带场景的问卷信息
     * @param $where
     * @param $select
     * @return array|ActiveRecord[]
     */
    public static function GetSceneQuestionList($where, $select)
    {
        preg_match("/dbname=([^;]*)/", Yii::$app->db2->dsn, $matches);

        $database = $matches[1];

        $query = self::find()->alias('q')
            ->leftJoin($database . '.sys_scene s', 's.id = q.scene_type_id')
            ->select($select)
            ->andFilterWhere($where)
            ->asArray();
        //排序
        $order_by = ['q.created_at' => SORT_DESC, 'q.id' => SORT_DESC];

        return $query->orderBy($order_by)->all();
    }

    /**
     * @return bool
     */
    public function validateOption()
    {
        if ($this->question_type == self::QUESTION_TYPE_INPUT_SELECT || $this->question_type == self::QUESTION_TYPE_SELECT) {
            if($this->options == null)
                $this->options = QuestionOption::findAll(['question_id'=> $this->id]);
            if (empty($this->options)) {
                $this->addError('business_type_id', '选项不能为空');
                return false;
            }
            // 可选填空题：用户自定义选项、限制为数值且最多两位小数（大中台生成任务给执行工具
            if ($this->question_type == self::QUESTION_TYPE_INPUT_SELECT) {

                $reg = '/^\d+\.?\d?\d?$/';
//                preg_match($reg, $bu, $match);
                foreach ($this->options as $option) {
                    if (!is_numeric($option['value'])) {
                        $this->addError('business_type_id', '可选填空题时，值必须为数字');
                        return false;
                    }

                    // 最多是2位小数

                    if (!preg_match($reg, $option['value'])) {
                        $this->addError('business_type_id', '可选填空题时，值必须为数字');
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public function validateIr()
    {
        if ($this->is_ir != 0 && $this->question_type == self::QUESTION_TYPE_SELECT) {
            $this->addError('question_type', '只有非IR问卷可以配置选择题');
            return false;
        }
    }

    /**
     * 保存问卷选项
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        if (!empty($this->options)) {
            QuestionOption::updateAll([QuestionOption::DEL_FIELD => QuestionOption::DEL_STATUS_DELETE], ['question_id' => $this->id]);
            if ($this->question_type == self::QUESTION_TYPE_INPUT_SELECT || $this->question_type == self::QUESTION_TYPE_SELECT) {
                foreach ($this->options as $key => $option) {
                    if (empty($option['id'])) {
                        $tmpOption = new QuestionOption();
                        $tmpOption['question_id'] = $this->id;
                    } else {
                        $tmpOption = QuestionOption::find()->where(['id' => $option['id']])->one(null, false);
                        if ($tmpOption == null) {
                            $tmpOption = new QuestionOption();
                            $tmpOption['question_id'] = $this->id;
                        }
                    }
                    $tmpOption['option_index'] = $key;
                    $tmpOption['value'] = $option['value'];
                    $tmpOption['name'] = $option['value'];
                    $tmpOption[QuestionOption::DEL_FIELD] = QuestionOption::DEL_STATUS_NORMAL;
                    $tmpOption->save();
                }
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    public function getOptions()
    {
        return $this->hasMany(QuestionOption::class, ['question_id' => 'id']);
    }
}