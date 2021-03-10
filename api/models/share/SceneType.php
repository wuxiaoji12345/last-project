<?php

namespace api\models\share;

use Yii;
use api\models\baseModel;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * This is the model class for table "{{%scene_type}}".
 *
 * @property int $id 主键
 * @property string $name 场景类型
 * @property int $is_deleted 是否删除，0:否 1:是
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at
 * @property int $updated_at
 * @property string $update_time 更新时间
 */
class SceneType extends baseModel
{
    const SCENE_TYPE_ALL = 999;     // 全店
    const SCENE_TYPE_ALL_LABEL = '全店';     // 全店

    const IS_DELETED = 1;       //已被删除
    const NOT_DELETED = 0;      //未被删除

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%scene_type}}';
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
            [['name', 'created_at', 'updated_at'], 'required'],
            [['is_deleted', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['name'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键',
            'name' => '场景类型',
            'is_deleted' => '是否删除，0:否 1:是',
            'status' => '删除标记0删除，1有效',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'update_time' => '更新时间',
        ];
    }

    /**
     * 获取所有场景
     * @param $select
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function GetAllScene($select)
    {
        $query = self::find();
        return $query->alias('st')
            ->leftJoin('sys_scene s', 'st.id = s.scene_type')
            ->select($select)
            ->where([
                'st.status' => SceneType::DEL_STATUS_NORMAL,
                's.status' => Scene::DEL_STATUS_NORMAL,
                'st.is_deleted' => SceneType::NOT_DELETED,
                's.is_deleted' => Scene::NOT_DELETED,
            ])
            ->asArray()
            ->all();
    }

    /**
     *场景类型列表
     * @param $and_where
     * @param $select
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function findTypeList($and_where, $select)
    {
        $query = self::find();
        return $query->select($select)
            ->andFilterWhere($and_where)
            ->asArray()
            ->all();
    }
}
