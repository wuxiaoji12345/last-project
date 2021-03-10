<?php

namespace api\models;

use yii\behaviors\TimestampBehavior;
use Yii;
/**
 * This is the model class for table "{{%image_similarity}}".
 *
 * @property int $id 主键
 * @property int $image_id image表id
 * @property string $image_key 图片key
 * @property string $survey_code 走访号
 * @property string $similarity_survey_code 相似走访号
 * @property int $similarity_image_id 相似图image表id
 * @property string $similarity_image_key 相似图图片key
 * @property float $similarity_number 相似图置信度值
 * @property int $similarity_cause 相似图原因
 * @property int $similarity_status 判定是否相似
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 */
class ImageSimilarity extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%image_similarity}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['image_id'], 'integer'],
            [['image_key'], 'string', 'max' => 300],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键',
            'image_id' => 'image表id',
            'image_key' => '图片key',
            'survey_code' => '走访号',
            'similarity_image_id' => '相似图image表id',
            'similarity_image_key' => '相似图key',
            'similarity_survey_code' => '走访号',
            'similarity_number' => '相似置信度',
            'similarity_cause' => '相似原因',
            'similarity_status' => '判定是否相似'
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => time()
            ]
        ];
    }

    public static function saveSimilarity($value)
    {
        $model = new self();
        $model->image_id = $value['image_id'];
        $model->image_key = $value['image_key'];
        $model->survey_code = $value['survey_code'];
        $model->similarity_image_id = $value['similarity_image_id'];
        $model->similarity_image_key = $value['similarity_image_key'];
        $model->similarity_survey_code = $value['similarity_survey_code'];
        $model->similarity_number = $value['similarity_number'];
        $model->similarity_cause = $value['similarity_cause'];
        $model->similarity_status = $value['similarity_status'];
        if ($model->save()) {
            return [true, '成功'];
        } else {
            return [false, '相似图存储失败，请检查'];
        }
    }

    /**
     * 获取相似图列表
     * @param $where
     * @param null $page
     * @param null $page_size
     * @return array|string|\yii\db\ActiveRecord|\yii\db\ActiveRecord[]|null
     */
    public static function getList($where, $page=null, $page_size=null)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's'
        );
        if (!empty($bu_condition)) $where = ['and', $where, $bu_condition];

        $alias = 'sim';
        $join = [];
        $join[] = [
            'type' => 'JOIN',
            'table' => Survey::tableName() . ' s',
            'on' => 'sim.survey_code = s.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => EngineResult::tableName() . ' e',
            'on' => 's.survey_code = e.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Survey::tableName() . ' ss',
            'on' => 'sim.similarity_survey_code = ss.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' img',
            'on' => 'sim.image_id = img.id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' iu',
            'on' => 'img.id = iu.image_id and iu.image_key = sim.image_key'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' imgs',
            'on' => 'sim.similarity_image_id = imgs.id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' ius',
            'on' => 'imgs.id = ius.image_id and ius.image_key = sim.similarity_image_key'
        ];
        $select = [
            'sim.id',
            'sim.similarity_cause',
            'sim.survey_code',
            'sim.similarity_survey_code',
            's.store_id',
            's.route_code',
            's.survey_time',
            's.tool_id',
            'ss.store_id as similarity_store_id',
            'ss.route_code as similarity_route_code',
            'ss.survey_time as similarity_survey_time',
            'ss.tool_id as similarity_tool_id',
            'img.standard_id',
            'img.scene_id_name',
            'imgs.scene_id_name as similarity_scene_id_name',
            'imgs.standard_id as similarity_standard_id',
            'iu.image_url',
            'ius.image_url as similarity_image_url',
            'iu.image_key',
            'ius.image_key as similarity_image_key',
        ];
        $pageArr = empty($page) && empty($page_size) ? null : ['page' => $page, 'page_size' => $page_size];
        return self::findJoin($alias, $join, $select, $where, true, true, 'sim.created_at DESC', '', 'sim.id', '', $pageArr,false);
    }

    public static function getOneByID($where,$order='')
    {
        $alias = 'sim';
        $join = [];
        $join[] = [
            'type' => 'JOIN',
            'table' => Survey::tableName() . ' s',
            'on' => 'sim.survey_code = s.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Survey::tableName() . ' ss',
            'on' => 'sim.similarity_survey_code = ss.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' img',
            'on' => 'sim.image_id = img.id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' imgs',
            'on' => 'sim.similarity_image_id = imgs.id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' iu',
            'on' => 'img.id = iu.image_id and iu.image_key = sim.image_key'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' ius',
            'on' => 'imgs.id = ius.image_id and ius.image_key = sim.similarity_image_key'
        ];
        $select = [
            'sim.id',
            'sim.similarity_cause',
            'sim.image_key',
            'sim.survey_code',
            'sim.similarity_image_key',
            'sim.similarity_survey_code',
            's.store_id',
            's.route_code',
            's.survey_time',
            'ss.store_id as similarity_store_id',
            'ss.route_code as similarity_route_code',
            'ss.survey_time as similarity_survey_time',
            'img.standard_id',
            'imgs.standard_id as similarity_standard_id',
            'iu.image_url',
            'ius.image_url as similarity_image_url',
        ];
        return self::findJoin($alias, $join, $select, $where, true, false, $order, '', '', '', '');
    }
}
