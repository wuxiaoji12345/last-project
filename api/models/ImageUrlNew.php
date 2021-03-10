<?php


namespace api\models;


use yii\db\ActiveRecord;

class ImageUrlNew extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%image_url_new}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['image_id', 'rebroadcast_status', 'is_rebroadcast', 'similarity_status', 'is_similarity', 'status'], 'integer'],
            [['update_time', 'similarity_result'], 'safe'],
            [['image_url'], 'string', 'max' => 300],
            [['image_key'], 'string', 'max' => 150],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键',
            'image_id' => 'image表id',
            'image_url' => '图片url',
            'update_time' => '更新时间',
            'image_key' => '图片KEY',
            'rebroadcast_status' => '翻拍图检测 0 未参加 1 参加',
            'is_rebroadcast' => '0 正常 1 翻拍',
            'similarity_status' => '相似图检测 0 未参加 1 参加',
            'is_similarity' => '0 正常 1 相似',
            'similarity_result' => '相似图结果',
            'status' => 'Status',
        ];
    }
}