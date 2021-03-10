<?php

namespace api\models\share;

use Yii;
use api\models\baseModel;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%check_store_list}}".
 *
 * @property int $id 主键id
 * @property int $tool_id 执行工具id
 * @property int $store_id 售点id
 * @property string $task_id 批次号
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class CheckStoreList extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%check_store_list}}';
    }

    /**
     * @return object|Connection|null
     * @throws InvalidConfigException
     */
    public static function getDb()
    {
        return Yii::$app->get('db2');
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tool_id', 'store_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['store_id', 'task_id', 'created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['task_id'], 'string', 'max' => 64],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'tool_id' => '执行工具id',
            'store_id' => '售点id',
            'task_id' => '批次号',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
