<?php

use api\models\Replan;
use yii\db\Migration;

/**
 * Class m200918_032602_replan
 */
class m200918_032602_replan extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Replan::tableName(), 'standard_id', 'int(11) NOT NULL DEFAULT 0 COMMENT \'指定检查项目\' AFTER `bu_code`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200918_032602_replan cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200918_032602_replan cannot be reverted.\n";

        return false;
    }
    */
}
