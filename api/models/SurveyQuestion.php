<?php

namespace api\models;

/**
 * This is the model class for table "{{%survey_question}}".
 *
 * @property int $id 主键id
 * @property int $survey_id 走访表主键id
 * @property int $plan_id 检查计划主键id
 * @property int $question_id 问卷id
 */
class SurveyQuestion extends baseModel
{
    const DEL_FLAG = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey_question}}';
    }

    public function behaviors()
    {
        return [
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['survey_id', 'plan_id', 'question_id'], 'required'],
            [['survey_id', 'plan_id', 'question_id'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'survey_id' => '走访表主键id',
            'plan_id' => '检查计划主键id',
            'question_id' => '问卷id'
        ];
    }

    public function getPlan()
    {
        return $this->hasOne(Plan::class, ['id' => 'plan_id']);
    }

    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    /**
     * 批量更新走访问题表
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveSurveyQuestion($value)
    {
//        $model = new self;
//        $model->survey_id = (int)$survey_id;
//        $model->plan_id = (int)$plan_id;
//        $model->question_id = (int)$question_id;
        $key = ['survey_id','plan_id','question_id'];
        $model= \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '走访问题表存储失败，请检查'];
        }
    }
}
