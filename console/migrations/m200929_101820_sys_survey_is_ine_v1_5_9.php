<?php

use yii\db\Migration;

/**
 * Class m200929_101820_sys_survey_is_ine_v1_5_9
 */
class m200929_101820_sys_survey_is_ine_v1_5_9 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "UPDATE `sys_engine_result` t2
 LEFT JOIN sys_survey t1 on t1.survey_code = t2.survey_code
 LEFT JOIN sys_standard ON sys_standard.id = t2.standard_id
 LEFT JOIN sys_check_type ON sys_check_type.id = sys_standard.check_type_id 
 SET is_ine =1
 where sys_check_type.id = 1;";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200929_101820_sys_survey_is_ine_v1_5_9 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200929_101820_sys_survey_is_ine_v1_5_9 cannot be reverted.\n";

        return false;
    }
    */
}
