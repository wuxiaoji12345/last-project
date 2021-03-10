<?php

use api\models\EngineResult;
use api\models\Image;
use api\models\Plan;
use yii\db\Migration;

/**
 * Class m200731_084237_v1_4_patch
 */
class m200731_084237_v1_4_patch extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // ALTER TABLE `check`.`sys_engine_result` ADD COLUMN `replan_id` int(11) NOT NULL DEFAULT 0 COMMENT '规则重跑id' AFTER `standard_id`;
        // ALTER TABLE `check`.`sys_engine_result` ADD COLUMN `statistical_id` int(11) NOT NULL DEFAULT 0 COMMENT '统计项目id' AFTER `replan_id`;
        // ALTER TABLE `check`.`sys_engine_result` ADD INDEX `statistical_id`(`statistical_id`) USING BTREE;
        $this->createIndex('statistical_id', EngineResult::tableName(), 'statistical_id');


        // ALTER TABLE `check`.`sys_image` ADD COLUMN `standard_id` int(11) NOT NULL DEFAULT 0 COMMENT '标准id' AFTER `scene_id_name`;
        // ALTER TABLE `check`.`sys_image` ADD COLUMN `get_photo_time` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '拍照时间' AFTER `number`;
        // ALTER TABLE `check`.`sys_image` MODIFY COLUMN `survey_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '走访号' AFTER `id`;
        // ALTER TABLE `check`.`sys_image` MODIFY COLUMN `scene_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景类型' AFTER `survey_code`;
        // ALTER TABLE `check`.`sys_image` MODIFY COLUMN `scene_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景id' AFTER `scene_code`;
        // ALTER TABLE `check`.`sys_image` MODIFY COLUMN `scene_id_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景名称' AFTER `scene_id`;
        // ALTER TABLE `check`.`sys_image` MODIFY COLUMN `tool_id` int(11) NOT NULL DEFAULT 0 COMMENT '执行工具id' AFTER `standard_id`;
        // ALTER TABLE `check`.`sys_image` ADD INDEX `standard_id`(`standard_id`) USING BTREE;
        $this->addColumn(Image::tableName(), 'standard_id', 'int(11) NOT NULL DEFAULT 0 COMMENT \'标准id\' AFTER `scene_id_name`');

        $this->alterColumn(Image::tableName(), 'survey_code', "varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '走访号' AFTER `id`");
        $this->alterColumn(Image::tableName(), 'scene_code', "varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景类型' AFTER `survey_code`");
        $this->alterColumn(Image::tableName(), 'scene_id', "varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景id' AFTER `scene_code`");
        $this->alterColumn(Image::tableName(), 'scene_id_name', "varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '工具端场景名称' AFTER `scene_id`");
        $this->alterColumn(Image::tableName(), 'tool_id', "int(11) NOT NULL DEFAULT 0 COMMENT '执行工具id' AFTER `standard_id`");

        $this->createIndex('standard_id', Image::tableName(), 'standard_id');

        // ALTER TABLE `check`.`sys_plan` ADD COLUMN `editable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否可以变更，0不可以，1可以' AFTER `re_photo_time`;
        // ALTER TABLE `check`.`sys_plan` MODIFY COLUMN `tool_id`     varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '执行工具' AFTER `project_id`;
        $this->addColumn(Plan::tableName(), 'editable', "tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否可以变更，0不可以，1可以' AFTER `re_photo_time`");
        $this->alterColumn(Plan::tableName(), 'tool_id', "varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '执行工具' AFTER `project_id`");

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200731_084237_v1_4_patch cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200731_084237_v1_4_patch cannot be reverted.\n";

        return false;
    }
    */
}
