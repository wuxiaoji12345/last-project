<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%brand_side}}".
 *
 * @property int $id
 * @property string $name 品牌方名
 * @property int $sort 排序
 * @property int $brand_status 品牌方状态：0禁用，1启用
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class BrandSide extends \api\models\baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%brand_side}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sort', 'brand_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['name'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '品牌方名',
            'sort' => '排序',
            'brand_status' => '品牌方状态：0禁用，1启用',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
