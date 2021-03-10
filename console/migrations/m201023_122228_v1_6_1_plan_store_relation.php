<?php

use api\models\PlanStoreRelation;
use yii\db\Migration;
use api\models\Store;

/**
 * Class m201023_122228_v1_6_1_plan_store_relation
 */
class m201023_122228_v1_6_1_plan_store_relation extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn(PlanStoreRelation::tableName(), 'id', 'bigint(10) UNSIGNED NOT NULL AUTO_INCREMENT FIRST');
        $this->createIndex('market_channel',Store::tableName(), 'market_channel');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201023_122228_v1_6_1_plan_store_relation cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201023_122228_v1_6_1_plan_store_relation cannot be reverted.\n";

        return false;
    }
    */
}
