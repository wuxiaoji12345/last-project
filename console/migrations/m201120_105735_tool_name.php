<?php

use api\models\Tools;
use yii\db\Migration;

/**
 * Class m201120_105735_tool_name
 */
class m201120_105735_tool_name extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "update ". Tools::tableName(). " set name = 'MEDI-SSM' where id = 6;";
        $sql .= "update ". Tools::tableName(). " set name = 'MEDI-CP' where id = 7;";

        Yii::$app->db->createCommand($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201120_105735_tool_name cannot be reverted.\n";

        return false;
    }
    */
}
