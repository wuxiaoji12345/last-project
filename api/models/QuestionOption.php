<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "sys_question_option".
 *
 * @property int $id 主键id
 * @property int $question_id 问卷id
 * @property int $option_index 问卷选项index
 * @property string $name 选项名称
 * @property string $value 选项值
 * @property string $note 备注
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class QuestionOption extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_question_option';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['question_id', 'name'], 'required'],
            [['question_id', 'option_index', 'status'], 'integer'],
            [['name', 'value', 'note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'question_id' => '问卷id',
            'option_index' => '问卷选项index',
            'name' => '选项名称',
            'value' => '选项值',
            'note' => '备注',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
