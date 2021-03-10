<?php

use yii\db\Migration;

/**
 * Class m201117_103705_insert_to_tools
 */
class m201117_103705_insert_to_tools extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("INSERT INTO `sys_tools` (`id`, `name`, `owner`, `token`, `overtime`, `sort`, `tool_status`, `status`, `created_at`, `updated_at`, `update_time`)
VALUES
	(10, 'SEA+高管巡店', NULL, 'wrEKy13LOH62lDA7ZJvTo8NPFcYtBf4q', NULL, 3, 1, 1, 0, 0, '2020-11-17 18:38:40');
");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201117_103705_insert_to_tools cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201117_103705_insert_to_tools cannot be reverted.\n";

        return false;
    }
    */
}
