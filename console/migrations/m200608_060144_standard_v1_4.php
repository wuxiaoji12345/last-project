<?php

use yii\db\Migration;
use api\models\Standard;

/**
 * Class m200608_060144_standard_v1_4
 */
class m200608_060144_standard_v1_4 extends Migration
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
        $this->addColumn(Standard::tableName(), 'photo_type', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT "拍照类别：0、普通模式，1随报随拍" AFTER `setup_step`');
        return true;
    }

    public function down()
    {
        $this->dropColumn(Standard::tableName(), 'photo_type');
        return true;
    }
}
