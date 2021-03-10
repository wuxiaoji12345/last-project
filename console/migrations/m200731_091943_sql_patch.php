<?php

use yii\db\Migration;

/**
 * Class m200731_091943_sql_patch
 */
class m200731_091943_sql_patch extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "
        ALTER TABLE `sys_rule_output_info` MODIFY COLUMN `standard_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '标准id' AFTER `id`;
        ALTER TABLE `sys_rule_output_info` ADD COLUMN `statistical_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '统计项目id' AFTER `standard_id`;
        ALTER TABLE `sys_rule_output_info` ADD INDEX `statistical_id`(`statistical_id`, `node_index`) USING BTREE;
        ";
        $this->execute($sql);

        $sql = "ALTER TABLE `sys_standard` ADD COLUMN `protocol_id` int(11) NOT NULL DEFAULT 0 COMMENT '协议模板id' AFTER `check_type_id`;
        ALTER TABLE `sys_standard` MODIFY COLUMN `setup_step` tinyint(1) NOT NULL DEFAULT 0 COMMENT '设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置整改, 5完成设置，6生动化映射' AFTER `scenes`;";
        $this->execute($sql);

        $sql = "ALTER TABLE `sys_sub_activity` ADD COLUMN `activation_id` int(11) NOT NULL DEFAULT 0 COMMENT '生动化编号' AFTER `standard_id`;";
        $this->execute($sql);

        $sql = "ALTER TABLE `sys_survey` MODIFY COLUMN `sub_activity_id` int(11) NOT NULL DEFAULT 0 COMMENT '子活动id' AFTER `store_id`;";
        $this->execute($sql);

        $sql = "ALTER TABLE `sys_survey_question` MODIFY COLUMN `update_time` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) COMMENT 'db更新时间' AFTER `question_id`;";
        $this->execute($sql);

        $sql = "ALTER TABLE `sys_tools` ADD COLUMN `sort` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序' AFTER `overtime`;";
        $this->execute($sql);

        $sql = "INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (70, '检查计划', '', 'plan', 'finish', '/plan/finish', 'SEAP-PLAN-0003', 'SEAP-PLAN-0001', '完成设置', '编辑权限', 0, 1, 1586417067, 1586417067, '2020-07-17 15:43:05');
        INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (71, '检查计划', '', 'plan', 'download-protocol-store', '/plan/download-protocol-store', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划签约售点下载', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-07-17 16:02:56');
        INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (72, '检查计划', '', 'plan', 'protocol-store-list-progress', '/plan/protocol-store-list-progress', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划签约售点下载 进度查询', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-07-17 16:02:56');
        INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (73, '检查项目', 'MENU_0002/ACTION_0005', 'rule', 'lively-with-rule-output', '/rule/lively-with-rule-output', 'SEAP-SURVEY-0006', 'SEAP-SURVEY-0004', '绑定输出项与生动化项', '编辑权限', 0, 1, 1586417067, 1586417067, '2020-04-21 13:58:36');";
        $this->execute($sql);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200731_091943_sql_patch cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200731_091943_sql_patch cannot be reverted.\n";

        return false;
    }
    */
}
