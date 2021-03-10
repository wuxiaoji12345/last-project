<?php

use yii\db\Migration;
use api\models\Plan;

/**
 * Class m200821_032330_sys_plan_v1_5
 */
class m200821_032330_sys_plan_v1_5 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Plan::tableName(), 'rectification_model', 'tinyint(1) NOT NULL DEFAULT 3 COMMENT \'1按检查时间，2按检查周期，3无\' AFTER `reward_value`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200821_032330_sys_plan_v1_5 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200821_032330_sys_plan_v1_5 cannot be reverted.\n";

        return false;
    }
    */
}
