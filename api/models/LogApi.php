<?php

namespace api\models;


use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%log_api}}".
 *
 * @property int $id
 * @property int $p_id 父id
 * @property string $time 时间戳
 * @property string $request_uri 请求地址
 * @property string $ip 请求来源IP地址
 * @property string $ua 浏览器标识
 * @property string $data 发送的数据
 * @property string $output 返回数据
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 */
class LogApi extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%log_api}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['p_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['time'], 'safe'],
            [['ua', 'data', 'output'], 'string'],
            [['request_uri'], 'string', 'max' => 100],
            [['ip'], 'string', 'max' => 15],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'p_id' => '父id',
            'time' => '时间戳',
            'request_uri' => '请求地址',
            'ip' => '请求来源IP地址',
            'ua' => '浏览器标识',
            'data' => '发送的数据',
            'output' => '返回数据',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}