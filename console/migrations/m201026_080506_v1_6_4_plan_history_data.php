<?php

use api\models\PlanStoreRelation;
use api\models\PlanStoreTmp;
use yii\db\Migration;

/**
 * 检查计划售点关系逻辑调整，历史数据兼容
 * Class m201026_080506_v1_6_4_plan_history_data
 */
class m201026_080506_v1_6_4_plan_history_data extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "insert into ". PlanStoreTmp::tableName(). " (plan_id, store_id, check_status) (select plan_id, store_id, 3 from ". PlanStoreRelation::tableName() . ")";
        Yii::$app->db->createCommand($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201026_080506_v1_6_4_plan_history_data cannot be reverted.\n";

        return false;
    }
    */
}
