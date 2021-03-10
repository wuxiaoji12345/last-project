<?php

namespace api\models\share;

use api\models\baseModel;
use api\models\User;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;

/**
 * This is the model class for table "{{%store_belong}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property string $name 名称
 * @property string $value 值
 * @property int $type 类型，0其他，1厂房，2BU,3大区，4营业所，5主任，6线路
 * @property string $code 外部编号
 * @property string $note 备注
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class StoreBelong extends baseModel
{
    const TYPE_DEFAULT = 0;     // 其他
    const TYPE_COMPANY = 1;     // 厂房
    const TYPE_BU = 2;     // BU
    const TYPE_REGION = 3;     // 大区
    const TYPE_LOCATION = 4;     // 营业所
    const TYPE_SUPERVISOR = 5;     // 主任
    const TYPE_ROUTE = 6;     // 线路

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%store_belong}}';
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
            [['project_id'], 'default', 'value' => 0],
            [['project_id', 'type', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['name', 'value', 'code'], 'string', 'max' => 50],
            [['note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'project_id' => '项目id',
            'name' => '名称',
            'value' => '值',
            'type' => '类型，0其他，1厂房，2BU,3大区，4营业所，5主任，6线路',
            'code' => '外部编号',
            'note' => '备注',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * @param $type
     * @param array $select
     * @param string $index
     * @param bool $bu_filter_flag
     * @return array|ActiveRecord[]
     */
    public static function getAll($type, $select = ['id', 'name', 'value', 'code'], $index = 'code', $bu_filter_flag = false)
    {
        $query = self::find()->asArray()->select($select)->indexBy($index);

        $where = [self::DEL_FIELD => self::DEL_STATUS_NORMAL, 'type' => $type];
        if($bu_filter_flag){
            $user_info = Yii::$app->params['user_info'];
            $bu_condition = User::getBuCondition(static::class,
                $user_info['company_code'],
                $user_info['bu_code'],
                !Yii::$app->params['user_is_3004']
            );
            if(!empty($bu_condition))
                $where = ['and', $where, $bu_condition];
        }
        $query->where($where);

        return $query->all();
    }

    /**
     * 根据大区编号获取大区名称
     *
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午12:05
     *
     * @param $code
     *
     * @return \api\models\Replan[]|array|ActiveRecord[]
     */
    public static function getNameByIds($code){
        return self::find()->select(['code', 'name'])->where(['code' => $code])->indexBy('code')->asArray()->all();
    }

}
