<?php

use yii\db\Migration;
use api\models\FunctionPermission;

/**
 * Class m201027_022815_sys_function_permission_v1_6_2
 */
class m201027_022815_sys_function_permission_v1_6_2 extends Migration
{
    /**
     * {@inheritdoc}
     * INSERT INTO `sys_function_permission` (`id`, `module`, `web_function_id`, `controller`, `action`, `action_url`, `function_id`, `menu_function_id`, `name`, `note`, `sys_used`, `status`, `created_at`, `updated_at`, `update_time`)
    VALUES
    (113, '图片查看下载', '', 'report', 'report-image', '/download/report-image-download', 'SEAP-RESULT-0008',
     * 'SEAP-RESULT-0008', '图片下载', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-10-25 16:22:07'),
    (114, '获取图片列表下载状态', 'MENU_0008/ACTION_0005', 'report', 'report-image',
     * '/download/report-image-download-progress', 'SEAP-RESULT-0008', 'SEAP-RESULT-0008', '图片下载', '查看/查询权限', 0, 1, 1586417067, 1586417067, '2020-10-25 16:49:32');
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => '图片查看下载',
            'web_function_id' => 'MENU_0008/ACTION_0005',
            'controller' => 'download',
            'action' => 'report-image-download',
            'action_url' => '/download/report-image-download',
            'function_id' => 'SEAP-RESULT-0009',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '图片下载',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '获取图片列表下载状态',
            'web_function_id' => 'MENU_0008/ACTION_0004',
            'controller' => 'download',
            'action' => 'report-image-download-progress',
            'action_url' => '/download/report-image-download-progress',
            'function_id' => 'SEAP-RESULT-0009',
            'menu_function_id' => 'SEAP-RESULT-0008',
            'name' => '获取图片下载状态',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图',
            'web_function_id' => 'MENU_0012',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'SEAP-SIM-0001',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '菜单',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-下载',
            'web_function_id' => 'MENU_0012/ACTION_0005',
            'controller' => 'download',
            'action' => 'similar-image-download',
            'action_url' => '/download/similar-image-download',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图-下载',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-下载状态',
            'web_function_id' => 'MENU_0012/ACTION_0004',
            'controller' => 'download',
            'action' => 'similar-image-download-progress',
            'action_url' => '/download/similar-image-download-progress',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图-下载状态',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-列表',
            'web_function_id' => 'MENU_0012/ACTION_0002',
            'controller' => 'similar',
            'action' => 'list',
            'action_url' => '/similar/list',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-详情',
            'web_function_id' => 'MENU_0012/ACTION_0004',
            'controller' => 'similar',
            'action' => 'report',
            'action_url' => '/similar/report',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-导出列表',
            'web_function_id' => 'MENU_0012/ACTION_0002',
            'controller' => 'download',
            'action' => 'similar-list',
            'action_url' => '/download/similar-list',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => '相似图-导出任务删除',
            'web_function_id' => 'MENU_0012/ACTION_0009',
            'controller' => 'download',
            'action' => 'del',
            'action_url' => '/download/del',
            'function_id' => 'SEAP-SIM-0002',
            'menu_function_id' => 'SEAP-SIM-0001',
            'name' => '相似图',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067',
            'update_time' => '2020-10-25 16:22:07'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201027_022815_sys_function_permission_v1_6_2 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_022815_sys_function_permission_v1_6_2 cannot be reverted.\n";

        return false;
    }
    */
}
