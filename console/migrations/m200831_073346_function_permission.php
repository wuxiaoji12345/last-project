<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * 更新前端web function_id
 * Class m200831_073346_function_permission
 */
class m200831_073346_function_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => 88,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0007',
            'controller' => 'plan-batch',
            'action' => 'enable',
            'action_url' => '/plan-batch/enable',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划启用',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 89,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0008',
            'controller' => 'plan-batch',
            'action' => 'disable',
            'action_url' => '/plan-batch/disable',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划禁用',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 90,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'save',
            'action_url' => '/plan-batch/save',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划新增',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 91,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'import-progress',
            'action_url' => '/plan-batch/import-progress',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划编进度查询',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 93,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0006',
            'controller' => 'plan-batch',
            'action' => 'save',
            'action_url' => '/plan-batch/save',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划编辑',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);

        $this->insert(FunctionPermission::tableName(), [
            'id' => 94,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划菜单',
            'note' => '查看权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 95,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0002',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划查询',
            'note' => '查看权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);

        $this->update(FunctionPermission::tableName(), ['web_function_id'=> 'MENU_0007/ACTION_0003'], ['id'=> 85]);
        $this->update(FunctionPermission::tableName(), ['web_function_id'=> 'MENU_0007/ACTION_0004'], ['id'=> 86]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200831_073346_function_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200831_073346_function_permission cannot be reverted.\n";

        return false;
    }
    */
}
