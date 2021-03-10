<?php

namespace api\models\share;

use api\models\baseModel;
use api\models\Plan;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\db\Connection;
use yii\db\Expression;

/**
 * This is the model class for table "{{%store}}".
 *
 * @property int $id
 * @property int $store_id 售点编号
 * @property string $name 售点名称
 * @property string $address 售点地址
 * @property string $bu_code bu编号
 * @property string $region_code 大区编号
 * @property int $location_code 营业所编号
 * @property string $storekeeper 店主姓名
 * @property int $phone 店主手机
 * @property string $sub_channel_code 售点所属次渠道编号，下同
 * @property int $company_code 厂房编号
 * @property string $location_name 营业所名称
 * @property string $supervisor_name 主任名称
 * @property int $route_code 线路编号
 * @property string $route_name 线路名
 * @property int $status_code 售点状态 0未启用，1启用，2已关闭
 */
class Store extends baseModel
{
    const DEL_FLAG = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%store}}';
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
            [['id', 'store_id', 'address', 'bu_code', 'region_code', 'location_code', 'storekeeper', 'sub_channel_code', 'company_code', 'location_name', 'supervisor_name', 'route_code', 'route_name'], 'required'],
            [['id', 'store_id', 'location_code', 'phone', 'company_code', 'route_code', 'status_code'], 'integer'],
            [['name', 'address', 'location_name', 'route_name'], 'string', 'max' => 50],
            [['bu_code'], 'string', 'max' => 5],
            [['region_code', 'storekeeper', 'sub_channel_code', 'supervisor_name'], 'string', 'max' => 10],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'store_id' => '售点编号',
            'name' => '售点名称',
            'address' => '售点地址',
            'bu_code' => 'bu编号',
            'region_code' => '大区编号',
            'location_code' => '营业所编号',
            'storekeeper' => '店主姓名',
            'phone' => '店主手机',
            'sub_channel_code' => '售点所属次渠道编号，下同',
            'company_code' => '厂房编号',
            'location_name' => '营业所名称',
            'supervisor_name' => '主任名称',
            'route_code' => '线路编号',
            'route_name' => '线路名',
            'status_code' => '售点状态 0未启用，1启用，2已关闭',
        ];
    }

    public function getLocation()
    {
        return $this->hasOne(StoreBelong::class, ['code' => 'location_code', 'type' => new Expression(StoreBelong::TYPE_LOCATION)]);
    }
    public function getSupervisor()
    {
        return $this->hasOne(StoreBelong::class, ['code' => 'supervisor_name', 'type' => new Expression(StoreBelong::TYPE_SUPERVISOR)]);
    }
    public function getRoute()
    {
        return $this->hasOne(StoreBelong::class, ['code' => 'route_code', 'type' => new Expression(StoreBelong::TYPE_ROUTE)]);
    }


    public static function getChannelSubInfo($where,$select)
    {
        preg_match("/dbname=([^;]*)/", \Yii::$app->db2->dsn, $matches);
        $database = $matches[1];
        return $list = self::find()->alias('st')
            ->leftJoin($database.'.sys_channel_sub ch', 'st.sub_channel_code=ch.code')
            ->select($select)
            ->where($where)
            ->asArray()
            ->one();
    }

    /**
     * 获取售点检查问卷数据的sql
     * @param $date
     * @param $task_id
     * @param $tool_id
     * @return ActiveQuery
     */
    public function getCheckQuestionDataQuery($date, $task_id, $tool_id){
        $query = CheckStoreQuestion::find()
            ->select([
                'id',
                'question_id',
                'question_title',
                'question_type',
                'business_type_id',
                'business_type_sort',
                'business_type_label',
                'question_options',
                'scene_code',
                'type',
                'is_ir',
                'must_take_photo'
            ])
        ->where(['date'=> $date, 'task_id'=> $task_id, 'tool_id'=> $tool_id]);

        $query->asArray();
        return $query;
    }

    /**
     * 获取售点检查场景数据的sql
     * @param $date
     * @param $task_id
     * @param $tool_id
     * @return ActiveQuery
     */
    public function getCheckSceneDataQuery($date, $task_id, $tool_id){
        $query = CheckStoreScene::find()
            ->alias('c')
            ->leftJoin(Scene::tableName().' s','c.scene_code = s.scene_code')
            ->select([
                'c.id',
                'c.scene_code',
                's.scene_type',
                's.scene_code_name',
                's.scene_maxcount',
                's.scene_need_recognition',
            ])
            ->where(['date'=> $date, 'task_id'=> $task_id, 'tool_id'=> $tool_id]);

        $query->asArray();
        return $query;
    }

}
