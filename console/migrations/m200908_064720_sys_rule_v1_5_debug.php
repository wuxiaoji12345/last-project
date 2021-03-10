<?php

use yii\db\Migration;
use api\models\FunctionPermission;

/**
 * Class m200908_064720_sys_rule_v1_5_debug
 */
class m200908_064720_sys_rule_v1_5_debug extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => '96',
            'module' => '图片查看',
            'web_function_id' => 'MENU_0008/ACTION_0003',
            'controller' => 'report',
            'action' => 'report-image',
            'action_url' => '/report/report-image',
            'function_id' => 'SEAP-RESULT-0009',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '图片列表',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '97',
            'module' => '图片查看',
            'web_function_id' => 'MENU_0008',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-RESULT-0008',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '菜单',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '98',
            'module' => '图片查看',
            'web_function_id' => 'MENU_0008/ACTION_0001',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-RESULT-0009',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '筛选条件选择',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '99',
            'module' => '图片查看',
            'web_function_id' => 'MENU_0008/ACTION_0002',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-RESULT-0009',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '查询按钮',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200908_064720_sys_rule_v1_5_debug cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200908_064720_sys_rule_v1_5_debug cannot be reverted.\n";

        return false;
    }
    */
}
