<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201026_025654_plan_batch_permission
 */
class m201026_025654_plan_batch_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => '115',
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'excel-import',
            'action_url' => '/plan-batch/excel-import',
            'function_id' => 'SEAP-PLAN-0006',
            'menu_function_id' => 'SEAP-PLAN-0005',
            'name' => '协议门店关系文件上传',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603681236',
            'updated_at' => '1603681236'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '116',
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'excel-import-progress',
            'action_url' => '/plan-batch/excel-import-progress',
            'function_id' => 'SEAP-PLAN-0006',
            'menu_function_id' => 'SEAP-PLAN-0005',
            'name' => '协议门店关系文件上传进度查询',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603681236',
            'updated_at' => '1603681236'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '117',
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'excel-import-fail-download',
            'action_url' => '/plan-batch/excel-import-fail-download',
            'function_id' => 'SEAP-PLAN-0006',
            'menu_function_id' => 'SEAP-PLAN-0005',
            'name' => '协议门店关系文件上传失败记录下载',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603681236',
            'updated_at' => '1603681236'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => '118',
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0005',
            'controller' => 'plan-batch',
            'action' => 'excel-import-fail-download-progress',
            'action_url' => '/plan-batch/excel-import-fail-download-progress',
            'function_id' => 'SEAP-PLAN-0006',
            'menu_function_id' => 'SEAP-PLAN-0005',
            'name' => '协议门店关系文件上传失败记录下载进度查询',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603681236',
            'updated_at' => '1603681236'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201026_025654_plan_batch_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201026_025654_plan_batch_permission cannot be reverted.\n";

        return false;
    }
    */
}
