<?php

use yii\db\Migration;
use api\models\CheckType;

/**
 * Class m200814_072000_check_type_v1_5
 */
class m200814_072000_check_type_v1_5 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->update(CheckType::tableName(),['title' => '长期协议'],'id = 6');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200814_072000_check_type_v1_5 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200814_072000_check_type_v1_5 cannot be reverted.\n";

        return false;
    }
    */
}
