<?php

use yii\db\Migration;
use api\models\EngineResult;

/**
 * Class m201118_102018_sys_engine_result_v1_8_1
 */
class m201118_102018_sys_engine_result_v1_8_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(EngineResult::tableName(), 'ine_total_points', "decimal(10, 2) NOT NULL DEFAULT 0 COMMENT 'ine总分' AFTER `pass_status`");
        $this->addColumn(EngineResult::tableName(), 'ine_config_timestamp_id', "int(20) NOT NULL DEFAULT 0 COMMENT 'ine配置时间戳' AFTER `ine_total_points`");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201118_102018_sys_engine_result_v1_8_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201118_102018_sys_engine_result_v1_8_1 cannot be reverted.\n";

        return false;
    }
    */
}
