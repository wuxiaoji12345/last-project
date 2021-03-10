<?php

namespace api\models\share;

use api\models\baseModel;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\Pagination;
use yii\db\Connection;

/**
 * This is the model class for table "{{%channel_sub}}".
 *
 * @property int $id 主键id
 * @property int $main_id 主渠道id
 * @property string $name 次渠道名称
 * @property string $code 次渠道编码
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ChannelSub extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%channel_sub}}';
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
            [['main_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['created_at', 'updated_at'], 'required'],
            [['update_time'], 'safe'],
            [['name'], 'string', 'max' => 64],
            [['code'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'main_id' => '主渠道id',
            'name' => '次渠道名称',
            'code' => '次渠道编码',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function getAll($select = ['id', 'name', 'code'], $index = 'id')
    {
        return self::find()->asArray()->select($select)->where([self::DEL_FIELD => self::DEL_STATUS_NORMAL])->indexBy($index)->all();
    }

    /**
     * 次渠道列表
     * @param $pageSize
     * @param $page
     * @param $select
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getChannelNameList($pageSize, $page, $select)
    {
        if (empty($page) || $page < 0) {
            $query = self::find();
            return $query->select($select)
                ->Where([ChannelSub::DEL_FIELD => ChannelSub::DEL_STATUS_NORMAL])
                ->asArray()
                ->all();
        } else {
            $page -= 1;
            $pages = new Pagination(['pageSize' => $pageSize, 'page' => $page]);
            $query = self::find();
            return $query->offset($pages->offset)->limit($pages->limit)
                ->Where([ChannelSub::DEL_FIELD => ChannelSub::DEL_STATUS_NORMAL])
                ->select($select)
                ->asArray()
                ->all();
        }
    }

    /**
     * 根据主渠道ID获取次渠道编号
     *
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午2:31
     *
     * @param $main_ids
     *
     * @return \api\models\Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getSubChannelCodeByMainID($main_ids)
    {
        return self::find()->select(['code'])->where(['main_id' => $main_ids])->asArray()->all();
    }

}
