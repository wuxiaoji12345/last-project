<?php

use yii\db\Migration;

/**
 * Class m201214_073545_scene_and_scene_type_v1_8_3
 */
class m201214_073545_alter_scene_and_scene_type_v1_8_3 extends Migration
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
        $sql = "ALTER TABLE `sys_scene`
                    ADD COLUMN `scene_maxcount` smallint(6) NOT NULL DEFAULT 0 COMMENT '场景数量限制' AFTER `scene_code_name`,
                    ADD COLUMN `scene_need_recognition` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否走图像识别，1:是 0:否' AFTER `scene_maxcount`,
                    ADD COLUMN `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除，0:否 1:是' AFTER `sort`;
                ALTER TABLE `sys_scene_type`
                    ADD COLUMN `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除，0:否 1:是' AFTER `name`;";

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201214_073545_scene_and_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201214_073545_scene_and_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }
    */
}
