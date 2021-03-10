<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201027_070808_v1_6_4_qc_permission
 */
class m201027_070808_v1_6_4_qc_permission extends Migration
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
            'action' => 'manual-review-list',
            'action_url' => '/qc/manual-review-list',
            'function_id' => 'SEAP-VERI-0003',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => 'QC人工复核任务列表',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603782958',
            'updated_at' => '1603782958'
        ]);

        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0013/ACTION_0006',
            'controller' => 'qc',
            'action' => 'survey-list',
            'action_url' => '/qc/survey-list',
            'function_id' => 'SEAP-VERI-0004',
            'menu_function_id' => 'SEAP-VERI-0002',
            'name' => 'QC走访列表',
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
        $this->delete(FunctionPermission::tableName(), ['id'=> 120]);
        $this->delete(FunctionPermission::tableName(), ['id'=> 120]);
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_070808_v1_6_4_qc_permission cannot be reverted.\n";

        return false;
    }
    */
}
