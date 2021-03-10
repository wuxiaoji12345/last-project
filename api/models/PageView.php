<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%page_view}}".
 *
 * @property int $id
 * @property string $env 系统名
 * @property int $image_report_survey_click 图片查看模块，以走访为维度的接口调用次数
 * @property int $image_report_click 图片查看模块，以单张图片为维度的接口调用次数
 * @property int $start_time 开启计数时间
 * @property int $last_time 最后一次计数时间
 */
class PageView extends \yii\db\ActiveRecord
{
    const NOT_TAKE_CLICK = 0;
    const HAS_SURVEY_CLICK = 1;
    const NOT_HAS_SURVEY_CLICK = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%page_view}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['env'], 'required'],
            [['image_report_survey_click', 'image_report_click', 'start_time', 'last_time'], 'integer'],
            [['env'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'env' => '系统名',
            'image_report_survey_click' => '图片查看模块，以走访为维度的接口调用次数',
            'image_report_click' => '图片查看模块，以单张图片为维度的接口调用次数',
            'start_time' => '开启计数时间',
            'last_time' => '最后一次计数时间',
        ];
    }

    public static function saveClick($name, $search)
    {
        $model = self::findOne(['env' => $name]);
        if (!$model) {
            $model = new self();
            $model->start_time = time();
            $model->env = $name;
        }
        if ($search == self::HAS_SURVEY_CLICK) {
            $model->image_report_survey_click = $model->image_report_survey_click ? $model->image_report_survey_click + 1 : 1;
        } elseif ($search == self::NOT_HAS_SURVEY_CLICK) {
            $model->image_report_click = $model->image_report_click ? $model->image_report_click + 1 : 1;
        }
        $model->last_time = time();
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false,$model->getErrors()];
        }
    }
}