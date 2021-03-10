<?php

use yii\db\Migration;

/**
 * Class m201118_063117_sys_question_answer_v_1_8_1
 */
class m201118_063117_sys_question_answer_v_1_8_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "ALTER TABLE `sys_question_answer` 
ADD COLUMN `question_image` varchar(1000) NOT NULL DEFAULT '' COMMENT '问卷留底照片' AFTER `answer`,
ADD COLUMN `question_image_key` varchar(1000) NOT NULL DEFAULT '' COMMENT '留底照片cos云的key' AFTER `question_image`;";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201118_063117_sys_question_answer_v_1_8_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201118_063117_sys_question_answer_v_1_8_1 cannot be reverted.\n";

        return false;
    }
    */
}
