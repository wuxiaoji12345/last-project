<?php

namespace api\models\share;

use api\models\baseModel;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%channel_main}}".
 *
 * @property int $id 主键id
 * @property string $name 渠道名称
 * @property string $code 渠道编码
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ChannelMain extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%channel_main}}';
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
            [['status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['name'], 'string', 'max' => 64],
            [['code'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'name' => '渠道名称',
            'code' => '渠道编码',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function getAll($select = ['id', 'name', 'code'], $index = null)
    {
        return self::find()->asArray()->select($select)->where([self::DEL_FIELD => self::DEL_STATUS_NORMAL])->indexBy($index)->all();
    }
}
