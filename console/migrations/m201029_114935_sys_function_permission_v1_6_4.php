<?php

use yii\db\Migration;
use api\models\FunctionPermission;

/**
 * Class m201029_114935_sys_function_permission_v1_6_4
 */
class m201029_114935_sys_function_permission_v1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => '检查项目',
            'web_function_id' => '',
            'controller' => 'rule',
            'action' => 'sub-output-detail',
            'action_url' => '/rule/sub-output-detail',
            'function_id' => 'SEAP-SURVEY-0006',
            'menu_function_id' => 'SEAP-SURVEY-0004',
            'name' => '带生动化层级的所有输出项',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603797796',
            'updated_at' => '1603797796'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0013',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-VERI-0002',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => '菜单',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603797796',
            'updated_at' => '1603797796'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0014',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-VERI-0005',
            'menu_function_id' => 'SEAP-VERI-0005',
            'name' => '菜单',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603797796',
            'updated_at' => '1603797796'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0014/ACTION_0002',
            'controller' => 'qc',
            'action' => 'manual-check-result-list',
            'action_url' => 'qc/manual-check-result-list',
            'function_id' => 'SEAP-VERI-0006',
            'menu_function_id' => 'SEAP-VERI-0005',
            'name' => '查询按钮',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603797796',
            'updated_at' => '1603797796'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0013/ACTION_0002',
            'controller' => 'qc',
            'action' => 'manual-review-list',
            'action_url' => 'qc/manual-review-list',
            'function_id' => 'SEAP-VERI-0003',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => '查询按钮',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603797796',
            'updated_at' => '1603797796'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201029_114935_sys_function_permission_v1_6_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201029_114935_sys_function_permission_v1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}
