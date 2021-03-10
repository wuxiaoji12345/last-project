<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m200713_065153_permission_add_plan_protocol_download
 */
class m200713_065153_permission_add_plan_protocol_download extends Migration
{
    /**
     * {@inheritdoc}
     * INSERT INTO `check_test`.`sys_function_permission`(`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`)
     * VALUES (NULL, '检查计划', '', 'plan', 'download-protocol-store', '/plan/download-protocol-store', 'SEAP-PLAN-0002',
     * 'SEAP-PLAN-0001', '检查计划签约售点下载', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-04-21 13:59:53')
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'download-protocol-store',
            'action_url' => '/plan/download-protocol-store',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '检查计划签约售点下载',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'protocol-store-list-progress',
            'action_url' => '/plan/protocol-store-list-progress',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '检查计划签约售点下载 进度查询',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200713_065153_permission_add_plan_protocol_download cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200713_065153_permission_add_plan_protocol_download cannot be reverted.\n";

        return false;
    }
    */
}
