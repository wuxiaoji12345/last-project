<?php

use yii\db\Migration;

/**
 * Class m201217_033403_update_tool_v1_8_3
 */
class m201217_033403_update_tool_v1_8_3 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "update `sys_tools` set `name`='SEA+管理层巡店' where `name`='SEA+高管巡店';";

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201217_033403_update_tool_v1_8_3 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201217_033403_update_tool_v1_8_3 cannot be reverted.\n";

        return false;
    }
    */
}
