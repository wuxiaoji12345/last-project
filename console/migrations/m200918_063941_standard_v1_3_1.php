<?php

use api\models\Standard;
use yii\db\Migration;

/**
 * Class m200918_063941_standard_v1_3_1
 */
class m200918_063941_standard_v1_3_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Standard::tableName(), 'is_deleted', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否被用户删除，0:否 1:是\' AFTER `set_vividness`');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(Standard::tableName(), 'is_deleted');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200918_063941_standard_v1_3_1 cannot be reverted.\n";

        return false;
    }
    */
}
