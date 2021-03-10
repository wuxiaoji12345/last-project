<?php

namespace api\models;

use api\models\share\Scene;
use yii\db\ActiveRecord;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "{{%question_answer}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property int $store_id 售点id
 * @property int $photo_id 图片id
 * @property resource $survey_id 走访号
 * @property string $scene_code 工具端场景类型
 * @property int $scene_id 工具端场景id
 * @property string $scene_id_name 工具端场景名称
 * @property int $tool_id 执行工具
 * @property int $question_id 问卷id
 * @property string $answer 答案
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class QuestionAnswer extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%question_answer}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'store_id', 'photo_id', 'scene_id', 'tool_id', 'question_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['photo_id', 'store_id'], 'required'],
            [['update_time'], 'safe'],
            [['survey_id'], 'string', 'max' => 100],
            [['scene_code', 'scene_id_name'], 'string', 'max' => 50],
            [['answer'], 'string', 'max' => 255],
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
            'store_id' => '售点id',
            'photo_id' => '图片id',
            'survey_id' => '走访号',
            'scene_code' => '工具端场景类型',
            'scene_id' => '工具端场景id',
            'scene_id_name' => '工具端场景名称',
            'tool_id' => '执行工具',
            'question_id' => '问卷id',
            'answer' => '答案',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public function getSurvey()
    {
        return $this->hasOne(Survey::class, ['survey_code' => 'survey_id']);
    }

    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    public function getQuestionOptions()
    {
        return $this->hasMany(QuestionOption::class, ['id' => 'answer']);
//        return $this->hasMany(QuestionOption::class, ['id' => 'answer'])->andOnCondition(['question_type' => Question::QUESTION_TYPE_SELECT]);
    }

    public function getTool()
    {
        return $this->hasOne(Tools::class, ['id' => 'tool_id']);
    }

    public function getImage()
    {
//        return $this->hasOne(Image::class, ['scene_id' => 'scene_id', 'survey_code' => 'survey_id']);
        return $this->hasOne(Image::class, ['id' => 'photo_id']);
    }

    /**
     * 存储单个问题答案
     * @param $key
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveAnswer($key, $value)
    {
        $model = \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '问题答案存储失败，请检查'];
        }
    }

    /**
     * 获取单张识别图的所有答案及问题内容
     * @param $where
     * @return array
     */
    public static function getImageAnswer($where)
    {
        return self::find()->alias('qa')
            ->leftJoin('sys_question q', 'q.id = qa.question_id')
            ->select(['qa.answer', 'q.title', 'q.question_type', 'q.content'])
            ->Where($where)
            ->asArray()
            ->all();
    }

    /**
     * 获取单张识别图的所有答案及合并类型
     * @param $where
     * @return array|ActiveRecord[]
     */
    public static function getAnswer($where)
    {
        return self::find()->alias('qa')
            ->leftJoin('sys_question q', 'q.id = qa.question_id')
            ->select(['qa.question_id', 'qa.answer', 'q.question_type', 'q.merge_type', 'photo_id'])
            ->Where($where)
            ->indexBy('photo_id')
            ->asArray()
            ->all();
    }

    public static function getAnswerData($bodyForm)
    {
        $query = self::answerDataQuery($bodyForm);
        $count = $query->count();

        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => (int)$count, 'list' => $data];
    }

    public static function answerDataQuery($bodyForm)
    {
        $query = QuestionAnswer::find()->select(['survey_time', 'rate_type', 'rate_value',
            'type',
            new Expression('if(' . QuestionOption::tableName() . '.id is null,answer, ' . QuestionOption::tableName() . '.value) answer'),
            new Expression(Survey::tableName() . '.store_id'),
            new Expression(Survey::tableName() . '.store_name'),
            new Expression(Survey::tableName() . '.survey_code'),
            new Expression(Survey::tableName() . '.is_inventory'),
            new Expression(Tools::tableName() . '.name'),
            new Expression(QuestionAnswer::tableName() . '.survey_id'),
            new Expression(QuestionAnswer::tableName() . '.tool_id'),
            new Expression(QuestionAnswer::tableName() . '.question_id'),
            new Expression(QuestionAnswer::tableName() . '.scene_code'),
            new Expression(QuestionAnswer::tableName() . '.photo_id'),
//            new Expression(QuestionAnswer::tableName() . '.scene_id'),
//            new Expression(QuestionAnswer::tableName() . '.scene_id_name'),
            new Expression(SurveyQuestion::tableName() . '.plan_id'),
            new Expression(Survey::tableName() . '.company_code'),
            new Expression(Survey::tableName() . '.bu_code'),
            new Expression(Image::tableName() . '.scene_id'),
            new Expression(Image::tableName() . '.scene_id_name'),
            'standard_title' => new Expression(Standard::tableName() . '.title'),
            'question_title' => new Expression(Question::tableName() . '.title'),
            'answer_id' => new Expression(QuestionAnswer::tableName() . '.id'),
        ])->asArray();
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.survey_code'), $bodyForm['survey_code']]);
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.tool_id'), $bodyForm['tool_id']]);
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.store_id'), $bodyForm['store_id']]);
        //增加是否清单店的选择项
        $query->andFilterWhere(['=', new Expression(Survey::tableName() . '.is_inventory'), $bodyForm['is_inventory']]);
        $query->andFilterWhere(['=', new Expression(Plan::tableName() . '.standard_id'), $bodyForm['standard_id']]);
        $query->andFilterWhere(['rate_type' => $bodyForm['rate_type']]);
        $query->andFilterWhere(['=', new Expression(Question::tableName() . '.type'), $bodyForm['type']]);
        // 大小场景
        $scenes = Scene::getSmallScene(['scenes_type_id' => $bodyForm['scenes_type_id'], 'scenes_code' => $bodyForm['scenes_code']]);
        $code = array_column($scenes, 'scene_code');
        $query->andFilterWhere(['in', new Expression(QuestionAnswer::tableName() . '.scene_code'), $code]);

        // bu筛选
        $bu_condition = User::getBuCondition(Question::class,
            Yii::$app->params['user_info']['company_code'],
            Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004']);
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        // company_bu 字段要特殊处理
        User::buFilterSearch($query, $bodyForm['company_bu'], Question::class);

        $query->joinWith('survey');
        //  走访会对应多个检查计划和检查项目
        $query->joinWith('survey.surveyQuestion');
        $query->joinWith('survey.surveyQuestion.plan');
        $query->joinWith('survey.surveyQuestion.plan.standard');
        $query->joinWith('question');
        $query->joinWith('tool');
        $query->joinWith('image');
        $query->joinWith('survey.store');
        $query->with(['image.standard' => function ($query) {
            $query->select('title, id');
        }]);
        $query->leftJoin(QuestionOption::tableName(), QuestionOption::tableName() . '.id = ' . QuestionAnswer::tableName() . '.answer');

        if ($bodyForm['start_time'] != '' && $bodyForm['end_time'] != '') {
            $query->andFilterWhere(['between', 'survey_time', $bodyForm['start_time'], $bodyForm['end_time'] . ' 23:59:59']);
        }
        if ($bodyForm['start_time'] != '' && $bodyForm['end_time'] == '') {
            $query->andFilterWhere(['>=', 'survey_time', $bodyForm['start_time']]);
        }
        if ($bodyForm['start_time'] == '' && $bodyForm['end_time'] != '') {
            $query->andFilterWhere(['<=', 'survey_time', $bodyForm['end_time'] . $bodyForm['end_time'] . ' 23:59:59']);
        }
        $query->andFilterWhere(['like', new Expression(Question::tableName() . '.title'), $bodyForm['question_title']]);
        $query->andWhere(['or',
            new Expression(SurveyQuestion::tableName() . '.question_id' . ' = ' . QuestionAnswer::tableName() . '.question_id'),
            new Expression(SurveyQuestion::tableName() . '.id is null')]);
        $query->andWhere(['=', new Expression(QuestionAnswer::tableName() . '.status'), QuestionAnswer::DEL_STATUS_NORMAL]);
        $query->andWhere(['=', new Expression(Image::tableName() . '.status'), QuestionAnswer::DEL_STATUS_NORMAL]);

        $query->groupBy(['store_id', QuestionAnswer::tableName() . '.survey_id', SurveyQuestion::tableName() . '.plan_id', 'question_id']);
        return $query;
    }
}
