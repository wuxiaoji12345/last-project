<?php

use yii\db\Migration;

/**
 * Class m201215_063727_delete_channel_scene_type_v1_8_3
 */
class m201215_063727_delete_channel_scene_type_v1_8_3 extends Migration
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
//        $sql = "delete from sys_channel_scene_type where channel_id = 999 and scene_type = 14";
//
//        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201215_063727_delete_channel_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201215_063727_delete_channel_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }
    */
}
