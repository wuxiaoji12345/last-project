<?php

use yii\db\Migration;
use api\models\FunctionPermission;

/**
 * Class m201029_055145_v1_6_4_qc_check_result_list_download
 */
class m201029_055145_v1_6_4_qc_check_result_list_download extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'module' => 'QC模块',
            'web_function_id' => 'MENU_0014/ACTION_0005',
            'controller' => 'qc',
            'action' => 'manual-check-result-list-download',
            'action_url' => '/qc/manual-check-result-list-download',
            'function_id' => 'SEAP-VERI-0006',
            'menu_function_id' => 'SEAP-VERI-0005',
            'name' => 'QC人工复核结果列表下载',
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
        echo "m201029_055145_v1_6_4_qc_check_result_list_download cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201029_055145_v1_6_4_qc_check_result_list_download cannot be reverted.\n";

        return false;
    }
    */
}
