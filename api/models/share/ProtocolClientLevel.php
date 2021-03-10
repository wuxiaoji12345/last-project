<?php

namespace api\models\share;

use Yii;

/**
 * This is the model class for table "{{%protocol_client_level}}".
 *
 * @property int $id
 * @property int $client_level 协议客户级别
 * @property string $swire_describe 太古协议客户级别描述
 * @property string $smart_describe SmartMEDI前端显示字段
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ProtocolClientLevel extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%protocol_client_level}}';
    }

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
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
            [['client_level', 'created_at', 'updated_at'], 'required'],
            [['client_level', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['swire_describe', 'smart_describe'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'client_level' => '协议客户级别',
            'swire_describe' => '太古协议客户级别描述',
            'smart_describe' => 'SmartMEDI前端显示字段',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
