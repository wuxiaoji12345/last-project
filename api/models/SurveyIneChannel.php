<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%survey_ine_channel}}".
 *
 * @property int $id 主键id
 * @property string $survey_code 走访code
 * @property int $ine_channel_id ine渠道id
 * @property int $ine_config_timestamp_id ine配置时间戳
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class SurveyIneChannel extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey_ine_channel}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ine_channel_id', 'ine_config_timestamp_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'survey_code' => '走访code',
            'ine_channel_id' => 'ine渠道id',
            'ine_config_timestamp_id' => 'ine配置时间戳',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
