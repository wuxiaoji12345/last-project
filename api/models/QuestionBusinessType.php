<?php

namespace api\models;


/**
 * This is the model class for table "sys_question_business_type".
 *
 * @property int $id 主键id
 * @property string $title 业务类型
 * @property int $sort 排序 降序
 * @property string $note 备注
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class QuestionBusinessType extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_question_business_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sort', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['title'], 'string', 'max' => 64],
            [['note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'title' => '业务类型',
            'sort' => '排序 降序',
            'note' => '备注',
            'status' => '删除标识',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
