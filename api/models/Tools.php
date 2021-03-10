<?php

namespace api\models;


/**
 * This is the model class for table "{{%tools}}".
 *
 * @property int $id 主键id
 * @property string $name 前端工具名
 * @property string $owner 工具所属者
 * @property string $token token
 * @property int $overtime  过期时间
 * @property int $tool_status 工具状态 0禁用，1启用
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class Tools extends baseModel
{
    const STATUS_AVAILABLE = 1;     // 正常
    const STATUS_DISABLED = 0;      // 禁用

    const TOOL_ID_SEA = 1;      // 固定几个执行工具的id
    const TOOL_ID_MEDI_SEA = 5;      // 固定几个执行工具的id
    const TOOL_ID_CP = 2;
    const TOOL_ID_SFA = 8;
    const TOOL_ID_SEA_LEADER = 10;

    const ALL_INE_TOOL_ID = [self::TOOL_ID_SEA, self::TOOL_ID_SEA_LEADER];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%tools}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['overtime', 'tool_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time', 'token'], 'safe'],
            [['name', 'owner'], 'string', 'max' => 20],
            [['token'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'name' => '前端工具名',
            'owner' => '工具所属者',
            'token' => 'token',
            'overtime' => ' 过期时间',
            'tool_status' => '工具状态',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 通过ID获取工具名称
     *
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午6:27
     *
     * @param $tool_ids
     *
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getName($tool_ids)
    {
        return self::find()->select(['id', 'name'])->where(['id' => $tool_ids])->indexBy('id')->asArray()->all();
    }
}
