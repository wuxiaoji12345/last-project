<?php

use api\models\Plan;
use yii\db\Migration;

/**
 * Class m200928_125259_plan_rectification
 */
class m200928_125259_plan_rectification extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "update ". Plan::tableName(). " p LEFT JOIN sys_standard s on p.standard_id = s.id
                    set rectification_model = if(s.protocol_id = 0, 3, 2), rectification_option = if(s.protocol_id = 0,  '', re_photo_time) ";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200928_125259_plan_rectification cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200928_125259_plan_rectification cannot be reverted.\n";

        return false;
    }
    */
}
