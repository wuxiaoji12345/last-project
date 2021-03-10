<?php

use yii\db\Migration;
use api\models\share\ChannelGroup;

/**
 * Class m201113_060619_sys_channel_group_smart_128
 */
class m201113_060619_sys_channel_group_smart_128 extends Migration
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
        $this->addColumn(ChannelGroup::tableName(), 'sort', 'int(11) NOT NULL DEFAULT 0 COMMENT \'排序\' AFTER `id`');
        $sql = "UPDATE `shared`.`sys_channel_group` SET `sort` = 2 WHERE `id` = 1;
UPDATE `shared`.`sys_channel_group` SET `sort` = 4 WHERE `id` = 2;
UPDATE `shared`.`sys_channel_group` SET `sort` = 1 WHERE `id` = 3;
UPDATE `shared`.`sys_channel_group` SET `sort` = 5 WHERE `id` = 4;
UPDATE `shared`.`sys_channel_group` SET `sort` = 3 WHERE `id` = 5;
UPDATE `shared`.`sys_channel_group` SET `sort` = 16 WHERE `id` = 6;
UPDATE `shared`.`sys_channel_group` SET `sort` = 6 WHERE `id` = 7;
UPDATE `shared`.`sys_channel_group` SET `sort` = 9 WHERE `id` = 8;
UPDATE `shared`.`sys_channel_group` SET `sort` = 8 WHERE `id` = 9;
UPDATE `shared`.`sys_channel_group` SET `sort` = 10 WHERE `id` = 10;
UPDATE `shared`.`sys_channel_group` SET `sort` = 11 WHERE `id` = 11;
UPDATE `shared`.`sys_channel_group` SET `sort` = 7 WHERE `id` = 12;
UPDATE `shared`.`sys_channel_group` SET `sort` = 12 WHERE `id` = 13;
UPDATE `shared`.`sys_channel_group` SET `sort` = 13 WHERE `id` = 14;
UPDATE `shared`.`sys_channel_group` SET `sort` = 14 WHERE `id` = 15;
UPDATE `shared`.`sys_channel_group` SET `sort` = 15 WHERE `id` = 16;
UPDATE `shared`.`sys_channel_group` SET `sort` = 17 WHERE `id` = 17;
";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201113_060619_sys_channel_group_smart_128 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201113_060619_sys_channel_group_smart_128 cannot be reverted.\n";

        return false;
    }
    */
}
