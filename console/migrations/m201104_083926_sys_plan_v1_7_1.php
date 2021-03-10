<?php

use yii\db\Migration;
use api\models\Plan;

/**
 * Class m201104_083926_sys_plan_v1_7_1
 */
class m201104_083926_sys_plan_v1_7_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Plan::tableName(), 'short_cycle', 'varchar(1000) NOT NULL DEFAULT \'\' COMMENT \'短周期模式的配置时间\' AFTER `is_qc`');
        $this->alterColumn(Plan::tableName(), 'rectification_model', 'tinyint(1) NOT NULL DEFAULT 3 COMMENT \'2最小周期内有限次整改，3无\' AFTER `reward_value`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201104_083926_sys_plan_v1_7_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201104_083926_sys_plan_v1_7_1 cannot be reverted.\n";

        return false;
    }
    */
}
