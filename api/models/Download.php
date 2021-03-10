<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%download}}".
 *
 * @property int $id
 * @property int $uid 操作人
 * @property string $task_id 任务ID
 * @property string $file_path 文件地址
 * @property string $file_name 文件名称
 * @property string $file_size 文件大小
 * @property string $download_url 下载地址
 * @property int $download_num 下载次数
 * @property int $download_status 状态 0 初始 1任务中 2成功
 * @property int $status 状态 0 删除 1
 * @property int $created_at 创建时间
 * @property int $updated_at 最后更新时间
 */
class Download extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%download}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['uid'], 'required'],
            [['uid', 'download_num', 'download_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['task_id', 'file_name'], 'string', 'max' => 50],
            [['file_path', 'download_url'], 'string', 'max' => 150],
            [['file_size'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'uid' => '操作人',
            'task_id' => '任务ID',
            'file_path' => '文件地址',
            'file_name' => '文件名称',
            'file_size' => '文件大小',
            'download_url' => '下载地址',
            'download_num' => '下载次数',
            'download_status' => '状态 0 初始 1任务中 2成功',
            'status' => '状态 0 删除 1',
            'created_at' => '创建时间',
            'updated_at' => '最后更新时间',
        ];
    }
}
