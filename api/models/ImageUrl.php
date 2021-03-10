<?php

namespace api\models;

/**
 * This is the model class for table "{{%image_url}}".
 *
 * @property int $id 主键
 * @property int $image_id image表id
 * @property string $image_url 图片url
 * @property string $image_key 图片KEY
 * @property int $rebroadcast_status 相似图检测 0 未参加 1 参加
 * @property int $is_rebroadcast 0 正常 1 翻拍
 * @property int $similarity_status 0 未参加相似图检测 1 参加相似检测
 * @property int $is_similarity 0 正常 1 相似
 * @property array $similarity_result 相似图结果
 * @property int $img_type 1:图像识别,2:问卷留底,3:售点纬度图片
 * @property int $status 状态
 * @property string $update_time 更新时间
 */
class ImageUrl extends baseModel
{
    const DEL_FLAG = false;
    /*
     1：图像识别
     2：问卷留底
     3：售点纬度图片
     */
    const IMAGE_NEED_DISTINGUISH = 1;
    const IMAGE_QUESTIONNAIRE = 2;
    const IMAGE_STORE = 3;

    const NOT_QUESTIONNAIRE_IMAGE = [
        self::IMAGE_NEED_DISTINGUISH,
        self::IMAGE_STORE,
    ];
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%image_url}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['image_id', 'rebroadcast_status', 'is_rebroadcast', 'similarity_status', 'is_similarity', 'status','img_type'], 'integer'],
            [['similarity_result', 'update_time'], 'safe'],
            [['image_url'], 'string', 'max' => 300],
            [['image_key'], 'string', 'max' => 150],
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
            'image_url' => '图片url',
            'image_key' => '图片key',
            'rebroadcast_status' => '翻拍检测状态 0未参加 1参加',
            'is_rebroadcast' => '是否翻拍 0正常 1是',
            'similarity_status' => '相似检测状态 0未参加 1参加',
            'is_similarity' => '是否相似 0正常 1相似',
            'similarity_result' => '相似数据',
            'status' => '删除标识：1有效，0无效',
            'img_type' => '1:图像识别,2:问卷留底,3:售点纬度图片',
            'update_time' => '更新时间',
        ];
    }

    public function behaviors()
    {
        return [
        ];
    }

    /**
     * 问卷
     * @return \yii\db\ActiveQuery
     */
    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    /**
     * 存储单张图片及原始url
     * @param $value
     * @param bool $is_other
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveImageUrl($value,$key = ['image_id','image_url','image_key'])
    {
//        $model = new ImageUrl();
//        $model->load($param, '');
//        if ($model->save()) {
//            return [true, $model->attributes['id']];
//        } else {
//            return [false, '单张图片存储失败，请检查'];
//        }
        $model= \Yii::$app->db->createCommand()->batchInsert(self::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '图片存储失败，请检查'];
        }
    }

    public static function getIdByImageId($imageID)
    {
        $model= self::find()->select('id')->where(['image_id'=>$imageID])->asArray()->all();
        if ($model) {
            return [true, $model];
        } else {
            return [false, '数据不存在'];
        }
    }

    public static function getKeyById($id)
    {
        $model= self::find()->select('image_key')->where(['id'=>$id])->asArray()->limit(1)->one();
        if ($model && !empty($model['image_key'])) {
            return $model['image_key'];
        }
        return false;
    }

    public static function saveImageKey($id,$key)
    {
        $model = self::find()
            ->where(['id' => $id])
            ->one();
        if($model) {
            $model->image_key = $key;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, '存储失败'];
            }
        } else {
            return [false, '图片数据不存在'];
        }
    }

    public static function saveRebroadcast($where, $params)
    {
        $model = self::findOne($where);
        if ($model) {
            $model->rebroadcast_status = $params['rebroadcast_status'];
            $model->is_rebroadcast = $params['is_rebroadcast'];
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, '存储失败'];
            }
        } else {
            return [false, '图片数据不存在'];
        }
    }

    public static function saveSimilarity($where, $params)
    {
        $model = self::findOne($where);
        if ($model) {
            $model->similarity_status = $params['similarity_status'];
            $model->is_similarity = $params['is_similarity'];
            $model->similarity_result = $params['similarity_result'];
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, '存储失败'];
            }
        } else {
            return [false, '图片数据不存在'];
        }
    }
}
