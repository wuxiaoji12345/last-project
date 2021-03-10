<?php

namespace api\models;

use Yii;
use yii\db\Exception;

/**
 * This is the model class for table "{{%replan_survey}}".
 *
 * @property int $id 主键id
 * @property int $replan_id 重跑计划
 * @property string $survey_code 走访号
 * @property int $re_status 重跑状态 0未开始，1重跑中，2已完成
 */
class ReplanSurvey extends baseModel
{
    const DEL_FLAG = false;
    const STATUS_DEFAULT = 0;
    const STATUS_RUNNING = 1;
    const STATUS_FINISHED = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%replan_survey}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['replan_id'], 'required'],
            [['replan_id', 're_status'], 'integer'],
            [['survey_code'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'replan_id' => '重跑计划',
            'survey_code' => '走访号',
            're_status' => '重跑状态',
        ];
    }

    /**
     * 统计计划去除重复的走访
     * 由于入库时，如果有新数据进来，会导致分页入库的数据重复
     * @param $replan_id
     * @throws Exception
     */
    public static function removeDuplicate($replan_id)
    {
        $sql = 'DELETE 
                FROM
                    ' . ReplanSurvey::tableName() . ' 
                WHERE
                    replan_id = :replan_id
                    AND id NOT IN ( SELECT id FROM ( SELECT min( id ) AS id FROM ' . ReplanSurvey::tableName() . '  WHERE replan_id = :replan_id GROUP BY survey_code ) AS b )';
        Yii::$app->db->createCommand($sql, ['replan_id' => $replan_id])->execute();
    }
}
