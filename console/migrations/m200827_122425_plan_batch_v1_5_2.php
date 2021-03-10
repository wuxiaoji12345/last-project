<?php

use yii\db\Migration;

/**
 * Class m200827_122425_plan_batch_v1_5_2
 */
class m200827_122425_plan_batch_v1_5_2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "ALTER TABLE `sys_plan_batch` 
                    ADD COLUMN `rectification_model` tinyint(1) NOT NULL DEFAULT '3' COMMENT '0未配置，1按检查时间，2按检查周期，3无' AFTER `end_time`,
                    ADD COLUMN `rectification_option` text NULL COMMENT '整改次数' AFTER `rectification_model`;";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200827_122425_plan_batch_v1_5_2 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200827_122425_plan_batch_v1_5_2 cannot be reverted.\n";

        return false;
    }
    */
}
