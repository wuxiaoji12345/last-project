<?php

namespace api\models;

use Yii;
use yii\data\Pagination;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%check_type}}".
 *
 * @property int $id 主键id
 * @property int $project_id 项目id
 * @property string $title 名称
 * @property string $value 值
 * @property string $note 备注
 * @property int $type 检查类型的类型 0：非协议类 1：协议类
 * @property int $active_status 启用状态：0初始化，1启用，2禁用
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class CheckType extends baseModel
{
    const IS_PROTOCOL_NO = 0;      //  非协议
    const IS_PROTOCOL_YES = 1;       // 协议类

    const ACTIVE_STATUS_DEFAULT = 0;
    const ACTIVE_STATUS_ENABLE = 1;
    const ACTIVE_STATUS_DISABLE = 2;

    const IS_PROTOCOL_DESCRIBE = [
        self::IS_PROTOCOL_NO => '非协议类检查项目',
        self::IS_PROTOCOL_YES => '协议类检查项目'
    ];

    const INE = [
        'check_type_id' => 1,
        'title' => 'INE'
    ];
    const SHORT_AGREEMENTS = [
        'check_type_id' => 5,
        'title' => '短期协议'
    ];
    const LONG_AGREEMENTS = [
        'check_type_id' => 6,
        'title' => '长期协议'
    ];
    const INE_AGREEMENTS = [
        'check_type_id' => 1,
        'title' => 'INE'
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%check_type}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'active_status', 'status', 'type'], 'integer'],
            [['update_time'], 'safe'],
            [['title'], 'string', 'max' => 64],
            [['value', 'note'], 'string', 'max' => 255],
            ['created_at', 'default', 'value' => time()],
            ['updated_at', 'default', 'value' => time()]
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
            'title' => '名称',
            'value' => '值',
            'note' => '备注',
            'type' => '检查类型的类型 0：非协议类 1：协议类',
            'active_status' => '启用状态',
            'status' => '删除标识',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 带分页查询场景类型列表
     * @param $pageSize
     * @param $page
     * @param $type
     * @return array|ActiveRecord[]
     */
    public static function getTypeListWithPackage($pageSize, $page, $type)
    {
        $where = ['status' => self::DEL_STATUS_NORMAL];
        if ($type) {
            $where['type'] = $type;
        }
        if ($page < 0) {
            $query = self::find();
            // 需要去除协议类 长期协议的类型，也就是id为6 直接去除
            $list = $query
                ->select(['id', 'title', 'created_at create_time', 'active_status', 'type'])
                ->where($where)
//                ->andWhere(['<>', 'id', CheckType::LONG_AGREEMENTS['check_type_id']])
                ->orderBy('created_at DESC')
                ->asArray()->all();
            $count = count($list);
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        } else {
            $page -= 1;
            $pages = new Pagination(['pageSize' => $pageSize, 'page' => $page]);
            $query = self::find();
            $list = $query->select(['id', 'title', 'created_at create_time', 'active_status', 'type'])
                ->where($where)
//                ->andWhere(['<>', 'id', CheckType::LONG_AGREEMENTS['check_type_id']])
                ->orderBy('created_at DESC');
            $count = $list->count();
            $list = $list->offset($pages->offset)->limit($pages->limit)
                ->asArray()
                ->all();
            return $data = [
                'count' => $count,
                'list' => $list
            ];
        }
    }

    /**
     * 通过名称判断该条检查类型是否已存在
     * @param $title
     * @return array|ActiveRecord|null
     */
    public static function getCheckTypeWithTitle($title)
    {
        return $data = self::find()
            ->where(['title' => $title])
            ->one();
    }

    /**
     * 新增检查类型
     * @param $data
     * @return array
     */
    public static function addCheckType($data)
    {
        $model = new self();
        $model->title = $data['title'];
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false, $model->errors];
        }
    }

    /**
     * 修改检查项目类型的启用状态
     * @param $where
     * @param $active_status
     * @return array
     */
    public static function doCheckTypeSwitch($where, $active_status)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->active_status = $active_status;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'check_type_id不存在'];
        }
    }

    /**
     * 删除检查项目类型
     * @param $where
     * @param $status
     * @return array
     */
    public static function doCheckTypeDelete($where, $status)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->status = $status;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'check_type_id不存在'];
        }
    }

    /**
     * 修改项目类型
     * @param $where
     * @param $title
     * @return array
     */
    public static function doCheckTypeEdit($where, $title)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->title = $title;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->errors];
            }
        } else {
            return [false, 'id不存在'];
        }
    }

}
