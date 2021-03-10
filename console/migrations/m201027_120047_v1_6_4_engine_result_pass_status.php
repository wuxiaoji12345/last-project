<?php

use yii\db\Migration;
use api\models\EngineResult;

/**
 * Class m201027_120047_v1_6_4_engine_result_pass_status
 */
class m201027_120047_v1_6_4_engine_result_pass_status extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(EngineResult::tableName(), 'pass_status', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'生动化都合格为1，不合格为0\' AFTER `result_status`');
        $this->addColumn(EngineResult::tableName(), 'send_cp_status', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'推送cp状态：0默认未推送，1正常推送成功，2正常推送失败，3正常与qc推送都成功，4正常与qc推送都失败，5正常成功qc失败，6正常失败qc成功\' AFTER `send_zft_fail`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(EngineResult::tableName(), 'pass_status');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_120047_v1_6_4_engine_result_pass_status cannot be reverted.\n";

        return false;
    }
    */
}
