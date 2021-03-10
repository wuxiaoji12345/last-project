<?php

namespace api\models\share;

use api\models\baseModel;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%market_segment}}".
 *
 * @property int $id 主键id
 * @property string $company_code 公司代码
 * @property string $city_code 市县代码
 * @property string $city_name 市县代码描述
 * @property string $town_code 乡镇代码
 * @property string $town_name 乡镇代码描述
 * @property string $market_segment 市场区隔
 * @property string $market_segment_desc 市场区隔描述
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class MarketSegment extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%market_segment}}';
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
            [['market_segment', 'created_at', 'updated_at'], 'required'],
            [['status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['company_code'], 'string', 'max' => 4],
            [['city_code', 'town_code'], 'string', 'max' => 10],
            [['city_name', 'market_segment_desc'], 'string', 'max' => 255],
            [['town_name'], 'string', 'max' => 50],
            [['market_segment'], 'string', 'max' => 1],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'company_code' => '公司代码',
            'city_code' => '市县代码',
            'city_name' => '市县代码描述',
            'town_code' => '乡镇代码',
            'town_name' => '乡镇代码描述',
            'market_segment' => '市场区隔',
            'market_segment_desc' => '市场区隔描述',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function findAllArray($where, $select = ['*'], $index = '', $bu_filter_flag = false, $group_by = null)
    {
        if (static::DEL_FLAG == true) {
            $where['status'] = static::DEL_STATUS_NORMAL;
        }

        if ($bu_filter_flag) {
            $user_info = Yii::$app->params['user_info'];
            $bu_condition = User::getBuCondition(static::class,
                $user_info['company_code'],
                $user_info['bu_code'],
                !Yii::$app->params['user_is_3004']
            );
            if (!empty($bu_condition))
                $where = ['and', $where, $bu_condition];
        }

        $query = self::find()->where($where)->select($select)->groupBy($group_by)->asArray();
        if ($index != '') {
            $query->indexBy($index);
        }
        return $query->all();
    }
}
