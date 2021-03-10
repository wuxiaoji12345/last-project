<?php

namespace api\models;

use yii\db\Expression;
use yii\db\Query;

/**
 * This is the ActiveQuery class for [[Replan]].
 *
 * @see Replan
 */
class ReplanQuery extends bQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return Replan[]|array
     */
    public function all($db = null, $delete_flag = true)
    {
        return parent::all($db, $delete_flag);
    }

    /**
     * {@inheritdoc}
     * @return Replan|array|null
     */
    public function one($db = null, $delete_flag = true)
    {
        return parent::one($db);
    }

    /**
     *
     */
    public static function listQuery()
    {
        return Replan::find()->asArray();
    }

    /**
     * 获取未推送队列走访号
     * @param integer $replan_id
     * @return Query
     */
    public static function surveyQuery($replan_id)
    {
        return ReplanSurvey::find()->asArray()
            ->select([])
            ->andWhere(['replan_id' => $replan_id]);
//        return Replan::find()->asArray()
//            ->alias('re')
//            ->select([
//                new Expression('su.*')])
//            ->innerJoinWith('replanSurvey su')
//            ->andWhere(['replan_status' => Replan::STATUS_RUNNING, 're_status' => ReplanSurvey::STATUS_DEFAULT]);
    }
}
