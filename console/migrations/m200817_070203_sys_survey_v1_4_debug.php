<?php

use yii\db\Migration;
use api\models\Survey;

/**
 * Class m200817_070203_sys_survey_v1_4_debug
 */
class m200817_070203_sys_survey_v1_4_debug extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn(Survey::tableName(), 'send_engine', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'发送规则引擎状态：0未发送，1已发送，2发送超时，3引擎结果已返回，4没有命中计划\' AFTER `survey_status`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200817_070203_sys_survey_v1_4_debug cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200817_070203_sys_survey_v1_4_debug cannot be reverted.\n";

        return false;
    }
    */
}
