<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201119_113944_ine_permission_v1_8_1
 */
class m201119_113944_ine_permission_v1_8_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE配置',
            'web_function_id' => 'MENU_0015',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-INE-0002',
            'menu_function_id' => 'SEAP-INE-0002',
            'name' => 'INE配置菜单',
            'note' => '菜单权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1605786457',
            'updated_at' => '1605786457'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE配置',
            'web_function_id' => 'MENU_0015/ACTION_0004',
            'controller' => 'ine',
            'action' => 'index',
            'action_url' => '/ine/index',
            'function_id' => 'SEAP-INE-0004',
            'menu_function_id' => 'SEAP-INE-0002',
            'name' => 'INE配置入口',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1605786457',
            'updated_at' => '1605786457'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE配置',
            'web_function_id' => 'MENU_0015/ACTION_0004',
            'controller' => 'ine',
            'action' => 'detail',
            'action_url' => '/ine/detail',
            'function_id' => 'SEAP-INE-0004',
            'menu_function_id' => 'SEAP-INE-0002',
            'name' => 'INE配置详情',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1605786457',
            'updated_at' => '1605786457'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE配置',
            'web_function_id' => 'MENU_0015/ACTION_0006',
            'controller' => 'ine',
            'action' => 'save',
            'action_url' => '/ine/save',
            'function_id' => 'SEAP-INE-0005',
            'menu_function_id' => 'SEAP-INE-0002',
            'name' => 'INE配置暂存',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE配置',
            'web_function_id' => 'MENU_0015/ACTION_0006',
            'controller' => 'ine',
            'action' => 'publish',
            'action_url' => '/ine/publish',
            'function_id' => 'SEAP-INE-0005',
            'menu_function_id' => 'SEAP-INE-0002',
            'name' => 'INE配置保存且生效',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE走访记录',
            'web_function_id' => 'MENU_0016/ACTION_0004',
            'controller' => 'interview',
            'action' => 'historical-score',
            'action_url' => '/interview/historical-score',
            'function_id' => 'SEAP-INE-0006',
            'menu_function_id' => 'SEAP-INE-0003',
            'name' => '历史得分图表接口',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'INE走访记录',
            'web_function_id' => 'MENU_0016/ACTION_0004',
            'controller' => 'interview',
            'action' => 'historical-score-info',
            'action_url' => '/interview/historical-score-info',
            'function_id' => 'SEAP-INE-0006',
            'menu_function_id' => 'SEAP-INE-0003',
            'name' => '月度走访得分明细',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201119_113944_ine_permission_v1_8_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201119_113944_ine_permission_v1_8_1 cannot be reverted.\n";

        return false;
    }
    */
}
