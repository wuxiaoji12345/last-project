<?php

use yii\db\Migration;

/**
 * Class m200902_083104_function_permission
 */
class m200902_083104_function_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "UPDATE `sys_function_permission` SET `function_id` = 'SEAP-PLAN-0007', `menu_function_id` = 'SEAP-PLAN-0005' WHERE `id` in (85, 86);";
        $sql .= "UPDATE `sys_function_permission` SET `function_id` = 'SEAP-PLAN-0006', `menu_function_id` = 'SEAP-PLAN-0005' WHERE `id` in (88,89,90,91,92,93);";
        $sql .= "UPDATE `sys_function_permission` SET `function_id` = 'SEAP-PLAN-0005', `menu_function_id` = 'SEAP-PLAN-0005' WHERE `id` in (94, 95);";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200902_083104_function_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200902_083104_function_permission cannot be reverted.\n";

        return false;
    }
    */
}
