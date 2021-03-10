<?php

namespace api\models;

use phpDocumentor\Reflection\Types\Self_;
use Yii;

/**
 * This is the model class for table "{{%image_report}}".
 *
 * @property int $id 主键id
 * @property string $task_id 任务ID
 * @property string $photo_id 图片id
 * @property string $survey_id 走访号
 * @property int $origin_type 1=图片，2=视频
 * @property int $scene_type 图像识别返回的场景类型
 * @property string $result 报告内容
 * @property string $url 结果图url
 * @property int $report_status 报告回执状态：0初始状态，1识别中，2完成
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ImageReport extends \api\models\baseModel
{
    const REPORT_STATUS_INITIAL = 0;     // 初始状态
    const REPORT_STATUS_DOING = 1;      // 识别中
    const REPORT_STATUS_END = 2;      // 完成

    const REPORT_TYPE_IMAGE = 1;
    const REPORT_TYPE_VIDEO = 2;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%image_report}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['origin_type', 'scene_type', 'report_status', 'status', 'photo_id', 'created_at', 'updated_at'], 'integer'],
            [['result'], 'string'],
            [['update_time'], 'safe'],
            [['task_id'], 'string', 'max' => 20],
            [['survey_id'], 'string', 'max' => 50],
            [['url'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'task_id' => '任务ID',
            'photo_id' => '图片id',
            'survey_id' => '走访号',
            'origin_type' => '1=图片，2=视频',
            'scene_type' => '图像识别返回的场景类型',
            'result' => '报告内容',
            'url' => '结果图url',
            'report_status' => '报告回执状态：0初始状态，1识别中，2完成',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 存储图像识别结果
     * @param $where
     * @param $params
     * @return array
     */
    public static function saveRecognitionResult($where, $params)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->result = $params['result'];
            $model->scene_type = $params['scene_type'];
            $model->url = $params['url'];
            $model->report_status = self::REPORT_STATUS_END;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, '存储失败'];
            }
        } else {
            return [false, '图片report_id不存在'];
        }
    }

    public static function saveMediaResult($where, $result)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->result = $result;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, '存储失败'];
            }
        } else {
            return [false, '图片report_id不存在'];
        }
    }

    /**
     * 创建新的图像识别回执
     * @param $param
     * @return array
     */
    public static function createImageReport($param)
    {
        $model = self::findOne(['photo_id' => $param['photo_id']]);
        if(!$model){
            $model = new self();
        }
        $model->load($param, '');
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false, $model->getErrors()];
        }
    }

    /**
     * 修改图片report状态
     * @param $where
     * @return array
     */
    public static function changeReportStatus($where, $status)
    {
        $model = self::find()
            ->where($where)
            ->one();
        if ($model) {
            $model->report_status = $status;
            if ($model->save()) {
                return [true, $model->attributes['id']];
            } else {
                return [false, $model->getErrors()];
            }
        } else {
            return [false, '图片report_id不存在'];
        }
    }

    /**
     * 验证是否还有未识别的图像
     * @param $where
     * @return array|null|\yii\db\ActiveRecord
     */
    public static function checkReportStatus($where)
    {
        return self::find()
            ->andWhere($where)
            ->asArray()
            ->one();
    }


    /**
     * 查询需要送引擎计算的信息
     * @param $where
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getReportToCalculation($where)
    {
        return $list = self::find()->alias('ir')
            ->leftJoin('sys_image i', 'i.id = ir.photo_id')
            ->select(['i.scene_id', 'ir.result', 'i.id'])
            ->Where($where)
            ->asArray()
            ->all();
    }

}
