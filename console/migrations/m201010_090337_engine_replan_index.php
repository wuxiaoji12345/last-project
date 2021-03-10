<?php

use api\models\EngineResult;
use yii\db\Migration;

/**
 * Class m201010_090337_engine_replan_index
 */
class m201010_090337_engine_replan_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createIndex('replan_id', EngineResult::tableName(), 'replan_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201010_090337_engine_replan_index cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201010_090337_engine_replan_index cannot be reverted.\n";

        return false;
    }
    */
}
