<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m200921_075836_replan
 */
class m200921_075836_replan extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => '100',
            'module' => '统计项目',
            'web_function_id' => 'MENU_0009',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0001',
            'menu_function_id' => 'STATICS-FUN-0001',
            'name' => '统计项目',
            'note' => '菜单权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '101',
            'module' => '统计项目',
            'web_function_id' => 'MENU_0009/ACTION_0001',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0001',
            'menu_function_id' => 'STATICS-FUN-0001',
            'name' => '筛选条件选择',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '102',
            'module' => '统计项目',
            'web_function_id' => 'MENU_0009/ACTION_0002',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0001',
            'menu_function_id' => 'STATICS-FUN-0001',
            'name' => '查询按钮',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '103',
            'module' => '统计重跑',
            'web_function_id' => 'MENU_0010',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0006',
            'menu_function_id' => 'STATICS-FUN-0004',
            'name' => '统计重跑',
            'note' => '菜单权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '104',
            'module' => '统计重跑',
            'web_function_id' => 'MENU_0010/ACTION_0001',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0004',
            'menu_function_id' => 'STATICS-FUN-0004',
            'name' => '筛选条件选择',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '105',
            'module' => '统计重跑',
            'web_function_id' => 'MENU_0010/ACTION_0002',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0006',
            'menu_function_id' => 'STATICS-FUN-0004',
            'name' => '查询按钮',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '106',
            'module' => '统计项目',
            'web_function_id' => 'MENU_0009/ACTION_0006',
            'controller' => 'statistical',
            'action' => 'statistical-add',
            'action_url' => '/statistical/statistical-add',
            'function_id' => 'STATICS-FUN-0002',
            'menu_function_id' => 'STATICS-FUN-0001',
            'name' => '编辑按钮',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);

        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0010/ACTION_0001', 'function_id' => 'STATICS-FUN-0006', 'menu_function_id'=> 'STATICS-FUN-0004'], ['id' => 70]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0010/ACTION_0005', 'function_id' => 'STATICS-FUN-0005', 'menu_function_id'=> 'STATICS-FUN-0004'], ['id' => 71]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0010/ACTION_0005', 'function_id' => 'STATICS-FUN-0005', 'menu_function_id'=> 'STATICS-FUN-0004'], ['id' => 72]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0009/ACTION_0002', 'function_id' => 'STATICS-FUN-0006', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 73]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0009/ACTION_0005', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 74]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0009/ACTION_0009', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 75]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => 'MENU_0009/ACTION_0004', 'function_id' => 'STATICS-FUN-0003', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 76]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => '', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 77]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => '', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 78]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => '', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 79]);
        $this->update(FunctionPermission::tableName(), ['web_function_id' => '', 'function_id' => 'STATICS-FUN-0002', 'menu_function_id'=> 'STATICS-FUN-0001'], ['id' => 80]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200921_075836_replan cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200921_075836_replan cannot be reverted.\n";

        return false;
    }
    */
}
