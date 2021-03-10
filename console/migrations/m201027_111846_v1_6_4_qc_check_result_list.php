<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m201027_111846_v1_6_4_qc_check_result_list
 */
class m201027_111846_v1_6_4_qc_check_result_list extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0014/ACTION_0004',
            'controller' => 'qc',
            'action' => 'manual-check-result-list',
            'action_url' => '/qc/manual-check-result-list',
            'function_id' => 'SEAP-VERI-0006',
            'menu_function_id' => 'SEAP-VERI-0005',
            'name' => 'QC人工复核结果列表',
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
        echo "m201027_111846_v1_6_4_qc_check_result_list cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_111846_v1_6_4_qc_check_result_list cannot be reverted.\n";

        return false;
    }
    */
}
