<?php

use yii\db\Migration;

/**
 * Class m201113_060532_sys_store_rb_smart_128
 */
class m201113_060532_sys_store_rb_smart_128 extends Migration
{
    public function init()
    {
        $this->db = 'db2';
        parent::init();
    }
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "UPDATE `sys_store_rb` SET is_sale_rb = 0 WHERE company_code IN (3040,3041);";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201113_060532_sys_store_rb_smart_128 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201113_060532_sys_store_rb_smart_128 cannot be reverted.\n";

        return false;
    }
    */
}
