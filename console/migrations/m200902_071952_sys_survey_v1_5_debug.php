<?php

use yii\db\Migration;
use api\models\Survey;

/**
 * Class m200902_071952_sys_survey_v1_5_debug
 */
class m200902_071952_sys_survey_v1_5_debug extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createIndex('Joint_index', Survey::tableName(), ['survey_status', 'company_code', 'bu_code', 'status', 'tool_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200902_071952_sys_survey_v1_5_debug cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200902_071952_sys_survey_v1_5_debug cannot be reverted.\n";

        return false;
    }
    */
}
