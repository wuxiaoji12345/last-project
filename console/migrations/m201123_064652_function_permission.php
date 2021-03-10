<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201123_064652_function_permission
 */
class m201123_064652_function_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => '走访记录',
            'web_function_id' => 'MENU_0016',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-INE-0003',
            'menu_function_id' => 'SEAP-INE-0003',
            'name' => '菜单',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '走访记录',
            'web_function_id' => 'MENU_0016/ACTION_0002',
            'controller' => 'interview',
            'action' => 'list',
            'action_url' => '/interview/list',
            'function_id' => 'SEAP-INE-0007',
            'menu_function_id' => 'SEAP-INE-0007',
            'name' => '走访列表',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '下载走访记录列表',
            'web_function_id' => 'MENU_0016/ACTION_0005',
            'controller' => 'download',
            'action' => 'interview',
            'action_url' => '/download/interview',
            'function_id' => 'SEAP-INE-0007',
            'menu_function_id' => 'SEAP-INE-0007',
            'name' => '下载走访记录列表',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '走访详情',
            'web_function_id' => 'MENU_0016/ACTION_0004',
            'controller' => 'interview',
            'action' => 'detail',
            'action_url' => '/interview/detail',
            'function_id' => 'SEAP-INE-0008',
            'menu_function_id' => 'SEAP-INE-0008',
            'name' => '走访详情',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '评分情况',
            'web_function_id' => 'MENU_0016/ACTION_0004',
            'controller' => 'interview',
            'action' => 'score-list',
            'action_url' => '/interview/score-list',
            'function_id' => 'SEAP-INE-0008',
            'menu_function_id' => 'SEAP-INE-0008',
            'name' => '评分情况',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '细分指标项',
            'web_function_id' => 'MENU_0016/ACTION_0004',
            'controller' => 'interview',
            'action' => 'target-list',
            'action_url' => '/interview/target-list',
            'function_id' => 'SEAP-INE-0008',
            'menu_function_id' => 'SEAP-INE-0008',
            'name' => '细分指标项',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1598843993',
            'updated_at' => '1598843993'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201123_064652_function_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201123_064652_function_permission cannot be reverted.\n";

        return false;
    }
    */
}
