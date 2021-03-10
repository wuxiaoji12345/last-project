<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%ine_channel}}".
 *
 * @property string $id 主键id
 * @property int $year 年份
 * @property int $channel_id 渠道id
 * @property string $channel_code 渠道code
 * @property string $channel_name 渠道名称
 * @property int $standard_id 检查项目ID
 * @property int $is_ine 是否ine，0非ine,1是ine
 * @property int $ine_status INE配置状态0默认，1已完成，2未完成
 * @property int $last_publish_time 最后一次配置保存并生效时间
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class IneChannel extends baseModel
{
    /**
     * INE配置状态，0未配置，1已发布，2暂存
     */
    const INE_STATUS_DEFAULT = 0;
    const INE_STATUS_PUBLISHED = 1;
    const INE_STATUS_SAVED = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%ine_channel}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['year', 'channel_id', 'standard_id', 'is_ine', 'ine_status', 'last_publish_time', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['channel_code', 'channel_name'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'year' => '年份',
            'channel_id' => '渠道id',
            'channel_code' => '渠道code',
            'channel_name' => '渠道名称',
            'standard_id' => '检查项目ID',
            'is_ine' => '是否ine，0非ine,1是ine',
            'ine_status' => 'INE配置状态0默认，1已完成，2未完成',
            'last_publish_time' => '最后一次配置保存并生效时间',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
