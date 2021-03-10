<?php

namespace api\models\share;

use Yii;

/**
 * This is the model class for table "{{%equipment}}".
 *
 * @property int $id id
 * @property string $outlet_num 客户编号
 * @property string $equipment_asset_code 冷饮设备资产编号
 * @property int $equipment_type 冷饮设备类型 10-现调机 12-展示柜 15	-自贩机
 * @property string $equipment_sub_type 冷饮设备子类型
 * @property string $equipment_model_name 冷饮设备型号名称
 * @property double $equipment_door_num 冷饮设备门数
 * @property int $equipment_status_cd 冷饮设备状态 70-在用 80-账面移机 90-拉修补投
 * @property string $create_by 数据创建者
 * @property string $create_date 数据创建时间
 * @property string $update_by 数据更新者
 * @property string $update_date 数据更新时间
 */
class Equipment extends \api\models\baseModel
{
    const EQUIPMENT_TYPE_ONE = 10;       // 现调机
    const EQUIPMENT_TYPE_TWO = 12;       // 展示柜
    const EQUIPMENT_TYPE_THREE = 15;     // 自贩机
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%equipment}}';
    }

    /**
     * @return null|object|\yii\db\Connection
     * @throws \yii\base\InvalidConfigException
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
            [['equipment_asset_code', 'equipment_type'], 'required'],
            [['equipment_type', 'equipment_status_cd'], 'integer'],
            [['equipment_door_num'], 'number'],
            [['create_date', 'update_date'], 'safe'],
            [['outlet_num', 'equipment_asset_code', 'equipment_model_name'], 'string', 'max' => 50],
            [['equipment_sub_type', 'create_by', 'update_by'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'id',
            'outlet_num' => '客户编号',
            'equipment_asset_code' => '冷饮设备资产编号',
            'equipment_type' => '冷饮设备类型 10-现调机 12-展示柜 15	-自贩机',
            'equipment_sub_type' => '冷饮设备子类型',
            'equipment_model_name' => '冷饮设备型号名称',
            'equipment_door_num' => '冷饮设备门数',
            'equipment_status_cd' => '冷饮设备状态 70-在用 80-账面移机 90-拉修补投',
            'create_by' => '数据创建者',
            'create_date' => '数据创建时间',
            'update_by' => '数据更新者',
            'update_date' => '数据更新时间',
        ];
    }
}
