<?php

use yii\db\Migration;

/**
 * Class m200928_141548_statisi
 */
class m200928_141548_statisi extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (74, '统计项目', 'MENU_0009/ACTION_0005', 'statistical', 'statistical-add', '/statistical/statistical-add', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '新增统计项目', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (75, '统计项目', 'MENU_0009/ACTION_0009', 'statistical', 'statistical-delete', '/statistical/statistical-delete', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '删除统计项目', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (76, '统计项目', 'MENU_0009/ACTION_0004', 'statistical', 'statistical-detail', '/statistical/statistical-detail', 'STATICS-FUN-0003', 'STATICS-FUN-0001', '单个统计项目详情', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (77, '统计项目', '', 'statistical', 'statistical-finish', '/statistical/statistical-finish', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '统计项目完成', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (78, '统计项目', '', 'statistical', 'edit-output-index', '/statistical/edit-output-index', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '修改输出项索引值', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (79, '统计项目', '', 'statistical', 'statistical-output-detail', '/statistical/statistical-output-detail', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '单独调取引擎输出项', '编辑权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (80, '统计项目检查结果', '', 'report', 'statistical-engine-result-list', '/report/statistical-engine-result-list', 'STATICS-FUN-0002', 'STATICS-FUN-0001', '统计项目检查结果列表', '查看/查询权限', 0, 1, 1, 1, '2020-09-22 20:09:48');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (81, '检查项目', 'MENU_0002/ACTION_0005', 'rule', 'lively-with-rule-output', '/rule/lively-with-rule-output', 'SEAP-SURVEY-0006', 'SEAP-SURVEY-0004', '绑定输出项与生动化项', '编辑权限', 0, 1, 1586417067, 1586417067, '2020-04-21 13:58:36');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (82, '检查计划', '', 'plan', 'finish', '/plan/finish', 'SEAP-PLAN-0003', 'SEAP-PLAN-0001', '完成设置', '编辑权限', 0, 1, 1586417067, 1586417067, '2020-06-09 16:42:36');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (83, '检查计划', '', 'plan', 'download-protocol-store', '/plan/download-protocol-store', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划签约售点下载', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-09-02 15:09:23');
                INSERT INTO `sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (84, '检查计划', '', 'plan', 'protocol-store-list-progress', '/plan/protocol-store-list-progress', 'SEAP-PLAN-0002', 'SEAP-PLAN-0001', '检查计划签约售点下载 进度查询', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-09-02 15:09:25');
            ";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200928_141548_statisi cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200928_141548_statisi cannot be reverted.\n";

        return false;
    }
    */
}
