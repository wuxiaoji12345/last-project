<?php

use yii\db\Migration;
use api\models\EngineResult;

/**
 * Class m201024_100552_sys_enging_result_v1_6_4
 */
class m201024_100552_sys_enging_result_v1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(EngineResult::tableName(), 'qc_result', 'mediumtext NULL COMMENT \'引擎计算结果qc后的结果\' AFTER `result_time`');
        $this->addColumn(EngineResult::tableName(), 'qc_status', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'qc状态：0初始状态，1qc已完成，2放弃qc\' AFTER `qc_result`');
        $this->addColumn(EngineResult::tableName(), 'is_need_qc', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否需要qc：0初始状态 1需要qc 2不需要qc\' AFTER `qc_status`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(EngineResult::tableName(), 'qc_result');
        $this->dropColumn(EngineResult::tableName(), 'qc_status');
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201024_100552_sys_enging_result_v1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}
