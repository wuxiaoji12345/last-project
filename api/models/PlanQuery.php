<?php


namespace api\models;


use api\models\share\ChannelSub;
use yii\db\Expression;
use yii\db\Query;
use Yii;

class PlanQuery extends bQuery
{
    /**
     * 检查计划QC走访列表
     * @param $params
     * @param array $select
     * @param bool $flag
     * @return bQuery|\yii\db\ActiveQuery
     */
    public static function getQcSurveyQuery($params, $select = [], $flag = true)
    {
        if ($select == []) {
            $select = ['e.*',
                'su.store_id',
                'su.survey_time',
                'su.tool_id',
                's.company_code',
                's.bu_code',
                's.sub_channel_code',
                's.region_code',
                's.location_code',
                's.location_name',
                's.supervisor_name',
                's.route_code',
            ];
        }
//        $query = EngineResult::find()
//            ->select(['id' => new Expression('max(e.id)'), new Expression('sum(e.qc_status) ss')])
//            ->alias('e')
////            ->where(['e.plan_id' => $params['plan_id'], 'qc_status' => EngineResult::ENGINE_RESULT_QC_DEFAULT])
//            ->where(['e.plan_id' => $params['plan_id']])
//            ->joinWith('survey.store');
        $qc_status = $flag ? EngineResult::ENGINE_RESULT_QC_DEFAULT : EngineResult::ENGINE_RESULT_QC_IGNORE;
        $query = EngineResult::find()->alias('e')->asArray()
            ->select($select)->where(['e.plan_id' => $params['plan_id'],
                'is_need_qc' => EngineResult::IS_NEED_QC_YES,
                'qc_status' => $qc_status,
                'result_status' => EngineResult::RESULT_STATUS_DONE,
            ]);
        $query->leftJoin(Survey::tableName() . ' su', 'su.survey_code = e.survey_code');
        $query->leftJoin(Store::tableName() . ' s', 'su.store_id = s.store_id');
        if (isset($params['start_time']) && $params['start_time'] != '' && isset($params['end_time']) && $params['end_time'] != '') {
            $query->andWhere(['between', 'survey_time', $params['start_time'] . ' 00:00:00', $params['end_time'] . ' 23:59:59']);
        } else if (isset($params['start_time']) && $params['start_time'] != '') {
            $query->andWhere(['>=', 'survey_time', $params['start_time'] . ' 00:00:00']);
        } else if (isset($params['end_time']) && $params['end_time'] != '') {
            $query->andWhere(['<=', 'survey_time', $params['end_time'] . ' 23:59:59']);
        }
        //大区
        if (isset($params['region_code']) and !empty($params['region_code'])) {
            $query->andWhere(['s.region_code' => $params['region_code']]);
        }
        $query->andFilterWhere(['e.survey_code' => $params['survey_code']]);
        $query->andFilterWhere(['su.store_id' => $params['store_id']]);
        $query->andFilterWhere(['s.location_code' => $params['location_code']]);
        $query->andFilterWhere(['s.supervisor_name' => $params['supervisor_name']]);
        $query->andFilterWhere(['s.route_code' => $params['route_code']]);
        $query->andFilterWhere(['pass_status' => $params['pass_status']]);
        //增加次渠道类型筛选
        if (!empty($params['channel_id_main'])) {
            $subChannel = ChannelSub::findOneArray(['id' => $params['channel_id_main']], ['id', 'code']);
            $query->andFilterWhere(['sub_channel_code' => $subChannel['code']]);
        }
//        $query->groupBy(['e.plan_id', Survey::tableName() . '.store_id']);
//        $query->having('ss = 0');

//        $tmpQuery = new Query();
//        $tmpQuery->from('(' . $query->createCommand()->getRawSql() . ') tt')
//            ->select(['id']);
//        $lastQuery = EngineResult::find()->asArray();
//        $lastQuery->select($select)->alias('e')
//            ->leftJoin(Survey::tableName() . ' su', 'su.survey_code = e.survey_code')
//            ->leftJoin(Store::tableName() . ' s', 's.store_id = su.store_id')
//            ->where(['in', 'e.id', $tmpQuery]);
//        return $lastQuery;

        $bu_condition = User::getBuCondition(Survey::class,
            Yii::$app->params['user_info']['company_code'],
            $where['bu_code'] = Yii::$app->params['user_info']['bu_code'],
            !Yii::$app->params['user_is_3004'], 'su');
        if (!empty($bu_condition))
            $query->andWhere($bu_condition);

        return $query;
    }
}