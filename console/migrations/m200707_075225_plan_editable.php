<?php

use api\models\Plan;
use yii\db\Migration;

/**
 * Class m200707_075225_plan_editable
 */
class m200707_075225_plan_editable extends Migration
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
        $this->addColumn(Plan::tableName(), 'editable', 'tinyint(1) NOT NULL DEFAULT 1 COMMENT \'是否可以变更，0不可以，1可以\' AFTER `re_photo_time`');
    }

    public function down()
    {
        $this->dropColumn(Plan::tableName(), 're_photo_time');

        return false;
    }

}
