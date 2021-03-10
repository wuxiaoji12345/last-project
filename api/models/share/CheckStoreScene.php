<?php

namespace api\models\share;

use Yii;
use api\models\baseModel;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%check_store_scene}}".
 *
 * @property int $id 主键id
 * @property int $tool_id 执行工具id
 * @property int $store_id 售点id
 * @property string $date 检查日期
 * @property string $task_id 批次号
 * @property string $scene_code 场景id
 * @property string $survey_id 走访号
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class CheckStoreScene extends baseModel
{
    const DEL_FLAG = false;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%check_store_scene}}';
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
            [['tool_id', 'store_id', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['date'], 'string', 'max' => 12],
            [['task_id'], 'string', 'max' => 64],
            [['scene_code', 'survey_id'], 'string', 'max' => 100],
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
            'date' => '检查日期',
            'task_id' => '批次号',
            'scene_code' => '场景id',
            'survey_id' => '走访号',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
