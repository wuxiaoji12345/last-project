<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201027_130104_v1_6_4_qc_permission
 */
class m201027_130104_v1_6_4_qc_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0013/ACTION_0004',
            'controller' => 'qc',
            'action' => 'survey-next',
            'action_url' => '/qc/survey-next',
            'function_id' => 'SEAP-VERI-0004',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => '下一条QC走访号',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0013/ACTION_0004',
            'controller' => 'qc',
            'action' => 'survey-ignore',
            'action_url' => '/qc/survey-ignore',
            'function_id' => 'SEAP-VERI-0004',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => '放弃复核',
            'note' => '编辑权限',
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
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_130104_v1_6_4_qc_permission cannot be reverted.\n";

        return false;
    }
    */
}
