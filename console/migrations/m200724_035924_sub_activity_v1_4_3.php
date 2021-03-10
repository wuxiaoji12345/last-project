<?php

use yii\db\Migration;

/**
 * Class m200724_035924_sub_activity_v1_4_3
 */
class m200724_035924_sub_activity_v1_4_3 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        return $this->up();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return $this->down();
    }


    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->addColumn(\api\models\SubActivity::tableName(), 'activation_name', 'varchar(255) NOT NULL DEFAULT \'\' COMMENT \'生动化名称\' AFTER `activation_id`');
    }

    public function down()
    {
        $this->dropColumn(\api\models\SubActivity::tableName(), 'activation_name');

        return false;
    }
}
