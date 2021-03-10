<?php

use api\models\Survey;
use yii\db\Migration;

/**
 * Class m200731_031952_survey_v1_4
 */
class m200731_031952_survey_v1_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200731_031952_survey_v1_4 cannot be reverted.\n";

        return false;
    }


    public function up()
    {
        $this->addColumn(Survey::tableName(), 'send_engine', 'tinyint(1) NOT NULL DEFAULT 3 COMMENT \'发送规则引擎状态：0未发送，1已发送，2发送超时，3引擎结果已返回\' AFTER `survey_status`');
        $this->alterColumn(Survey::tableName(), 'send_engine', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'发送规则引擎状态：0未发送，1已发送，2发送超时，3引擎结果已返回\' AFTER `survey_status`');
    }

    public function down()
    {
        $this->dropColumn(Survey::tableName(), 'send_engine');
        return false;
    }

}
