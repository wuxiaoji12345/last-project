<?php

use yii\db\Migration;

/**
 * Class m200929_113656_sys_scene_v1_5_9
 */
class m200929_113656_sys_scene_v1_5_9 extends Migration
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
        $sql = "INSERT INTO `sys_scene`(`scene_type`, `scene_code`, `scene_code_name`, `scene_status`, `status`, `created_at`, `updated_at`, `update_time`) 
VALUES (17, 'KOLAGERWIRERACK', 'KO大包装铁丝货架', 1, 1, 1580817027, 1580817027, '2020-04-21 14:59:24');
INSERT INTO `sys_scene_type` VALUES (17, '生动化二次陈列', 1, 1580817027, 1580817027, '2020-04-21 15:10:43');";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200929_113656_sys_scene_v1_5_9 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200929_113656_sys_scene_v1_5_9 cannot be reverted.\n";

        return false;
    }
    */
}
