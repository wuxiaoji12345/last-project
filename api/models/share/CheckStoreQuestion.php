<?php

namespace api\models\share;

use Yii;
use api\models\baseModel;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%check_store_question}}".
 *
 * @property int $id 主键id
 * @property int $tool_id 执行工具id
 * @property int $store_id 售点id
 * @property string $date 检查日期
 * @property string $task_id 批次号
 * @property int $question_id 问题id
 * @property string $question_title 问题标题
 * @property int $question_type 问题题型 1=是非，2=填空
 * @property string $scene_code 场景类型code
 * @property int $type 问卷类型1售点，2场景
 * @property int $is_ir 是否IR问卷：0非IR，1是IR，2共享
 * @property int $must_take_photo 是否必须拍照，0不需要，1必须
 * @property string $survey_id 走访号
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class CheckStoreQuestion extends baseModel
{
    const DEL_FLAG = false;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%check_store_question}}';
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
            [['tool_id', 'store_id', 'question_id', 'question_type', 'type', 'is_ir', 'must_take_photo', 'created_at', 'updated_at'], 'integer'],
            [['scene_code', 'created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['date'], 'string', 'max' => 12],
            [['task_id'], 'string', 'max' => 64],
            [['question_title', 'scene_code', 'survey_id'], 'string', 'max' => 100],
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
            'question_id' => '问题id',
            'question_title' => '问题标题',
            'question_type' => '问题题型 1=是非，2=填空',
            'scene_code' => '场景类型code',
            'type' => '问卷类型1售点，2场景',
            'is_ir' => '是否IR问卷：0非IR，1是IR，2共享',
            'must_take_photo' => '是否必须拍照，0不需要，1必须',
            'survey_id' => '走访号',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
