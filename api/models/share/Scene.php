<?php

namespace api\models\share;

use api\models\baseModel;
use api\models\CheckType;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;

/**
 * This is the model class for table "{{%scene}}".
 *
 * @property int $id 主键
 * @property int $scene_type 场景类型
 * @property string $scene_code 工具端场景类型code
 * @property string $scene_code_name 工具端场景类型名称
 * @property int $scene_maxcount 场景数量限制
 * @property int $scene_need_recognition 是否走图像识别，1:是 0:否
 * @property int $scene_status 场景状态：0禁用，1正常
 * @property int $sort 排序 升序
 * @property int $is_deleted 是否删除，0:否 1:是
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at
 * @property int $updated_at
 * @property string $update_time 更新时间
 */
class Scene extends baseModel
{
    const WHOLE_STORE = '999';
    const INE_NEED_DELETE = [
        'SHOPBOARD'
    ];

    const IS_DELETED = 1;       //已被删除
    const NOT_DELETED = 0;      //未被删除

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%scene}}';
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
            [['scene_type', 'scene_maxcount', 'scene_need_recognition', 'scene_status', 'sort', 'is_deleted', 'status', 'created_at', 'updated_at'], 'integer'],
            [['scene_code', 'sort', 'created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['scene_code', 'scene_code_name'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键',
            'scene_type' => '场景类型',
            'scene_code' => '工具端场景类型code',
            'scene_code_name' => '工具端场景类型名称',
            'scene_maxcount' => '场景数量限制',
            'scene_need_recognition' => '是否走图像识别，1:是 0:否',
            'scene_status' => '场景状态：0禁用，1正常',
            'sort' => '排序 升序',
            'is_deleted' => '是否删除，0:否 1:是',
            'status' => '删除标记0删除，1有效',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'update_time' => '更新时间',
        ];
    }

    public static function getAll($select = ['id', 'scene_type', 'scene_code', 'scene_code_name'], $index = 'id', $and_where = '')
    {
        $query = self::find()->asArray()->select($select)->where([self::DEL_FIELD => self::DEL_STATUS_NORMAL]);
        if ($and_where) $query->andWhere($and_where);
        return $query->indexBy($index)->orderby(['sort'=> SORT_ASC])->all();
    }

    /**
     * 获取场景code信息
     * @param $and_where
     * @param $select
     * @return array|ActiveRecord[]
     */
    public static function findSceneList($and_where, $select)
    {
        $query = self::find();
        return $query->select($select)
            ->andFilterWhere($and_where)
            ->asArray()
            ->all();
    }

    /**
     * 获取带场景的问卷信息
     * @param $where
     * @param $select
     * @return array|ActiveRecord[]
     */
    public static function GetSceneQuestionList($where, $select)
    {
        $query = self::find();
        return $query->alias('s')
            ->leftJoin('check.sys_question q', 's.id = q.scene_type_id')
            ->select($select)
            ->andFilterWhere($where)
            ->asArray()
            ->all();
    }

    /**
     * 根据选择的场景结构体返回所有的小场景
     * @param $data array 含scenes_type_id，scenes_code 2字段  ['scenes_type_id' => $bodyForm['scenes_type_id'], 'scenes_code' => $bodyForm['scenes_code']]
     * @param $select string|array
     * @param bool $is_ine
     * @return array|ActiveRecord[]
     */
    public static function getSmallScene($data, $select = '*', $is_ine = false)
    {
        //INE要删除的场景
        $and_where = $is_ine ? ['!=', 'scene_code', Scene::INE_NEED_DELETE[0]] : '';
        $result = [];
        // 全店
        if (!is_array($data['scenes_type_id'])) {
            $data['scenes_type_id'] = [$data['scenes_type_id']];
        }
        if (in_array(self::WHOLE_STORE, $data['scenes_type_id'])) {
            $result = array_values(Scene::getAll(['id', 'scene_type', 'scene_code', 'scene_code_name'], 'id', $and_where));
            //INE要删除的场景
            if ($is_ine) {
                foreach ($result as $k => $v) {
                    if ($v['scene_code'] == self::INE_NEED_DELETE[0]) unset($result[$k]);
                }
            }
            return $result;
        }

        if (!empty($data['scenes_type_id'])) {
            $scenes = self::findAllArray(['scene_type' => $data['scenes_type_id']], $select, 'id');
            $result = array_merge($result, $scenes);
        }

        if (!empty($data['scenes_code'])) {
            $scenes = self::findAllArray(['scene_code' => $data['scenes_code']], $select, 'id');
            $result = array_merge($result, $scenes);
        }
        //INE要删除的场景
        if ($is_ine) {
            foreach ($result as $k => $v) {
                if ($v['scene_code'] == self::INE_NEED_DELETE[0]) unset($result[$k]);
            }
        }
        //按sort排序
        $distance = array_column($result, 'sort');
        array_multisort($distance, SORT_ASC, $result);
        return $result;
    }

    /**
     * 获取场景类型名称
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午2:43
     *
     * @param $scene_code_arr
     *
     * @return \api\models\Replan[]|array|ActiveRecord[]
     */
    public static function getSceneCodeName($scene_code_arr)
    {
        return self::find()->select(['scene_code', 'scene_code_name'])
            ->where(['scene_code' => $scene_code_arr])
            ->asArray()
            ->all();
    }
}
