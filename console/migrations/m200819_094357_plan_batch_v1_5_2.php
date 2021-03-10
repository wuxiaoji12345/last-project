<?php

use api\models\Plan;
use yii\db\Migration;

/**
 * Class m200819_094357_plan_batch_v1_5_2
 */
class m200819_094357_plan_batch_v1_5_2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_plan_batch` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `batch_name` varchar(255) NOT NULL DEFAULT '' COMMENT '批量名称',
                  `tool_id` int(11) NOT NULL DEFAULT '0' COMMENT '执行工具',
                  `company_code` varchar(16) NOT NULL DEFAULT '' COMMENT '厂房',
                  `bu_code` varchar(16) NOT NULL DEFAULT '' COMMENT 'BU',
                  `file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '导入文件名',
                  `start_time` varchar(10) NOT NULL DEFAULT '' COMMENT '开始时间',
                  `end_time` varchar(10) NOT NULL DEFAULT '' COMMENT '结束时间',
                  `batch_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态0默认，1启用，2禁用',
                  `note` varchar(0) NOT NULL DEFAULT '' COMMENT '备注',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `update_time` (`update_time`)
                ) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COMMENT='批量检查计划';";
        $this->execute($sql);

        $this->addColumn(Plan::tableName(), 'plan_batch_id', "int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '批量创建时的id' AFTER `bu_code`");

        $sql = "INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`) VALUES (85, '检查计划（长期协议）', '', 'plan-batch', 'list', '/plan-batch/list', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划（长期协议）', '查看/查询权限', 0, 1, 1586417067, 1586417067);";
        $this->execute($sql);
        $sql = "INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (86, '检查计划（长期协议）', '', 'plan-batch', 'view', '/plan-batch/view', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划（长期协议）', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-08-19 19:52:57');";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200819_094357_plan_batch_v1_5_2 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200819_094357_plan_batch_v1_5_2 cannot be reverted.\n";

        return false;
    }
    */
}
