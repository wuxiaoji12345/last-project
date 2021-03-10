<?php

use yii\db\Migration;

/**
 * Class m201027_062028_create_survey_sub_activity_1_6_4
 */
class m201027_062028_create_survey_sub_activity_1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_survey_sub_activity` (
                  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
                  `survey_code` varchar(100) NOT NULL DEFAULT '' COMMENT '走访号',
                  `sub_activity_id` int(10) NOT NULL DEFAULT '0' COMMENT '子活动表ID',
                  `standard_id` int(11) NOT NULL DEFAULT '0' COMMENT '成功图像标准id',
                  `activation_id` int(11) NOT NULL DEFAULT '0' COMMENT '生动化编号',
                  `activation_name` varchar(255) NOT NULL DEFAULT '' COMMENT '生动化名称',
                  `scenes_type_id` json NOT NULL COMMENT '主场景id组',
                  `scenes_code` json NOT NULL COMMENT '次场景code组',
                  `question_manual_ir` text NOT NULL COMMENT 'IR问卷组',
                  `question_manual` text NOT NULL COMMENT '非IR问卷组',
                  `image` text CHARACTER SET utf8 NOT NULL COMMENT '标准示例图片',
                  `describe` text CHARACTER SET utf8 NOT NULL COMMENT '描述',
                  `is_standard_disable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是禁用的检查计划 0不是 1是禁用的（用于区分两种快照）',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `standard_id` (`standard_id`) USING BTREE,
                  KEY `survey_code` (`survey_code`,`standard_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='走访子活动表'";
        $this->execute($sql);
    }
    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201027_062028_create_survey_sub_activity_1_6_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_062028_create_survey_sub_activity_1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}