<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%image}}".
 *
 * @property int $id 主键id
 * @property string $survey_code 走访号
 * @property string $scene_code 工具端场景类型
 * @property int $scene_id 工具端场景id
 * @property string $scene_id_name 工具端场景名称
 * @property int $standard_id 标准id
 * @property int $tool_id 执行工具id
 * @property int $img_type 图片类型：0图像识别，1不走图像识别，2共享
 * @property string $img_prex_key cos key 前缀
 * @property int $number 图片数量
 * @property int $is_key 客户上传图片模式： 0上传url 1上传key
 * @property string $url 图片url
 * @property string $get_photo_time 拍照时间
 * @property string $sub_activity_id 子活动id
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class Image extends \api\models\baseModel
{
    const IMG_DISCRIMINATE = 0;     // 图像识别
    const IMG_QUESTION_COPY = 1;      // 问卷留底
    const IMG_SHARE = 2;      // 图片共享

    const IS_KEY_NO = 0;      // 上传图片url
    const IS_KEY_YES = 1;      // 上传图片key

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%image}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tool_id', 'img_type', 'number', 'status', 'created_at', 'updated_at', 'standard_id', 'sub_activity_id', 'is_key'], 'integer'],
            [['survey_code'], 'required'],
            [['update_time', 'get_photo_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['scene_code', 'scene_id_name'], 'string', 'max' => 50],
            [['img_prex_key'], 'string', 'max' => 150],
            [['scene_id'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'survey_code' => '走访号',
            'scene_code' => '工具端场景类型',
            'scene_id' => '工具端场景id',
            'scene_id_name' => '工具端场景名称',
            'tool_id' => '执行工具id',
            'standard_id' => '标准id',
            'img_type' => '图片类型：0图像识别，1问卷留底，2共享',
            'img_prex_key' => 'cos key 前缀',
            'number' => '图片数量',
            'is_key' => '客户上传图片模式',
            'get_photo_time' => '拍照时间',
            'sub_activity_id' => '子活动id',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public function getImageUrl()
    {
        return $this->hasMany(ImageUrl::class, ['image_id' => 'id']);
    }

    public function getSubActivity()
    {
        return $this->hasOne(SubActivity::class, ['id' => 'sub_activity_id']);
    }

    public function getImageReport()
    {
        return $this->hasOne(ImageReport::class, ['photo_id' => 'id']);
    }

    public function getStandard()
    {
        return $this->hasOne(Standard::class, ['id' => 'standard_id']);
    }

    /**
     * 存储执行工具上传图片
     * @param $param
     * @return array
     * @throws \yii\db\Exception
     */
    public static function saveImage($param)
    {
        $param['sub_activity_id'] = isset($param['sub_activity_id']) && !empty($param['sub_activity_id']) ? $param['sub_activity_id'] : 0;
        $param['standard_id'] = isset($param['standard_id']) ? $param['standard_id'] : 0;
        $param['created_at'] = time();
        $param['updated_at'] = time();
        $param['survey_code'] = (string)$param['survey_code'];
        $param['img_prex_key'] = '';
        $param['number'] = isset($param['number']) ? $param['number'] : 0;
        $param['is_key'] = isset($param['is_key']) ? $param['is_key'] : 0;
        $sql = "INSERT INTO
                sys_image(survey_code,scene_id,tool_id,img_type,scene_code,scene_id_name,standard_id,sub_activity_id,created_at,updated_at,img_prex_key,number,is_key)
                VALUE(:survey_code,:scene_id,:tool_id,:img_type,:scene_code,:scene_id_name,:standard_id,:sub_activity_id,:created_at,:updated_at,:img_prex_key,:number,:is_key)
                ON DUPLICATE KEY UPDATE tool_id= :tool_id,img_type=:img_type,scene_code=:scene_code
                ,scene_id_name=:scene_id_name,standard_id=:standard_id,sub_activity_id=:sub_activity_id,created_at=:created_at,
                updated_at=:updated_at,img_prex_key=:img_prex_key,number=:number,is_key=:is_key";
        Yii::$app->db->createCommand($sql, $param)->execute();
        $id = self::findOneArray(['survey_code' => $param['survey_code'], 'scene_id' => $param['scene_id']])['id'];
        if ($id) {
            return [true, $id];
        } else {
            return [false, '存储image失败'];
        }
    }

    /**
     * 存储COS返回的该组图片的key
     * @param $id
     * @param $key
     * @return array
     */
    public static function saveCosKey($id, $key, $count)
    {
        $model = self::find()
            ->where(['id' => $id])
            ->one();
        if ($model) {
            $model->img_prex_key = $key;
            $model->number = $count;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->getErrors()];
            }
        } else {
            return [false, '无此image_id'];
        }
    }

    /**
     * 获取单张识别图的所有答案及问题内容
     * @param $where
     * @return array
     */
    public static function findImageReport($where)
    {
        return $model = self::find()->alias('i')
            ->leftJoin('sys_question_answer qa', 'i.id = qa.photo_id')
            ->leftJoin('sys_question q', 'q.id = qa.question_id')
            ->leftJoin('sys_image_report ir', 'ir.photo_id = i.id')
            ->leftJoin('sys_survey_scene s', 's.survey_id = i.survey_code and s.scene_id = i.scene_id')
            ->select(['qa.answer', 'q.title', 'q.question_type', 'qa.question_id', 'q.content', 'i.img_prex_key', 'i.number', 'i.id img_id'
                , 'ir.result', 'ir.url', 's.asset_name', 's.asset_code', 's.asset_type', 'qa.question_image', 'q.type'])
            ->Where($where)
            ->asArray()
            ->all();
    }

    /**
     * 查询需要送引擎计算的信息
     * @param $where
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getReportToCalculation($where)
    {
        return $list = self::find()->alias('i')
            ->leftJoin('sys_image_report ir', 'i.id = ir.photo_id')
            ->select(['i.scene_code', 'ir.result', 'i.id img_id', 'i.standard_id', 'i.sub_activity_id'])
            ->Where($where)
            ->asArray()
            ->all();
    }

    /**
     * 查询完整的（包含问卷id与结果）需要送引擎计算的信息
     * @param $where
     * @return Replan[]|array|\yii\db\ActiveRecord[]
     */
    public static function getAllToCalculation($where)
    {
        return $list = self::find()->alias('i')
            ->leftJoin(ImageReport::tableName() . ' ir', 'i.id = ir.photo_id')
            ->leftJoin(QuestionAnswerQc::tableName() . ' qa', 'i.id = qa.image_id')
            ->select(['i.scene_code', 'ir.result', 'i.id img_id', 'i.standard_id', 'i.scene_id', 'i.sub_activity_id', 'qa.question_id', 'qa.answer'])
            ->Where($where)
            ->asArray()
            ->all();
    }

    public static function findImageAndReport($where, $select)
    {
        return $list = self::find()->alias('i')
            ->leftJoin('sys_image_report ir', 'i.id = ir.photo_id')
            ->select($select)
            ->Where($where)
            ->asArray()
            ->one();
    }

    public static function getStandardId($where)
    {
        return $model = self::find()->where($where)->asArray()->limit(1)->one();
    }
}