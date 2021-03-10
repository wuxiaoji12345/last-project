<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m200831_031747_function_permission
 */
class m200831_031747_function_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => 92,
            'module' => '检查计划（长期协议）',
            'web_function_id' => 'MENU_0007/ACTION_0009',
            'controller' => 'plan-batch',
            'action' => 'delete-batch',
            'action_url' => '/plan-batch/delete-batch',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '批量检查计划删除',
            'note' => '编辑权限',
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
        echo "m200831_031747_function_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200831_031747_function_permission cannot be reverted.\n";

        return false;
    }
    */
}
