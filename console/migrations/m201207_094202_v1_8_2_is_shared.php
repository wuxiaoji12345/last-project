<?php

use yii\db\Migration;

/**
 * Class m201207_094202_v1_8_2_is_shared
 */
class m201207_094202_v1_8_2_is_shared extends Migration
{
    public function init()
    {
        $this->db = 'db2';
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "ALTER TABLE `sys_check_store_question` 
                ADD COLUMN `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否必填 0不是，1是' AFTER `question_options`;";

        $sql .= "replace INTO `sys_channel_main`(`id`, `name`, `code`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (999, '非INE', 'NOTINE', 1, 1, 1, '2020-12-02 11:55:49');
                UPDATE sys_channel_sub set main_id = 999 where main_id = 0;";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $sql = "ALTER TABLE `sys_check_store_question` 
DROP COLUMN `is_required`;";
        $this->execute($sql);
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201207_094202_v1_8_2_is_shared cannot be reverted.\n";

        return false;
    }
    */
}
