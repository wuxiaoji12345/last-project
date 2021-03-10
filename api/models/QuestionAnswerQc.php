<?php

namespace api\models;

use Yii;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "{{%question_answer_qc}}".
 *
 * @property int $id 主键id
 * @property int $image_id 图片id
 * @property string $survey_code 走访号
 * @property int $plan_id 检查计划id
 * @property string $scene_code 工具端场景类型
 * @property string $scene_id 工具端场景id
 * @property string $scene_id_name 工具端场景名称
 * @property int $question_id 问卷id
 * @property string $answer 答案
 * @property string $question_image 问卷留底照片
 * @property string $question_image_key 留底照片cos云的key
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class QuestionAnswerQc extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%question_answer_qc}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['image_id', 'plan_id', 'created_at', 'updated_at'], 'required'],
            [['image_id', 'plan_id', 'question_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['scene_code', 'scene_id', 'scene_id_name'], 'string', 'max' => 50],
            [['answer'], 'string', 'max' => 255],
            [['question_image', 'question_image_key'], 'string', 'max' => 1000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'image_id' => '图片id',
            'survey_code' => '走访号',
            'plan_id' => '检查计划id',
            'scene_code' => '工具端场景类型',
            'scene_id' => '工具端场景id',
            'scene_id_name' => '工具端场景名称',
            'question_id' => '问卷id',
            'answer' => '答案',
            'question_image' => '问卷留底照片',
            'question_image_key' => '留底照片cos云的key',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 问卷
     * @return \yii\db\ActiveQuery
     */
    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    /**
     * 图片
     * @return \yii\db\ActiveQuery
     */
    public function getImage()
    {
        return $this->hasOne(Image::class, ['id' => 'image_id']);
    }

    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }

    public static function saveQc($value)
    {
//        $model = new self;
//        $model->survey_id = (int)$survey_id;
//        $model->plan_id = (int)$plan_id;
//        $model->question_id = (int)$question_id;
        $key = self::getModelKey();
        unset($key[0]);
        array_pop($key);
        $key = array_values($key);
        return self::batchSave($value,$key);
    }
}
