<?php

use api\models\SubActivity;
use yii\db\Migration;

/**
 * Class m200617_081103_sub_activity_v1_4
 */
class m200617_081103_sub_activity_v1_4 extends Migration
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
//        $this->alterColumn(SubActivity::tableName(), 'describe', 'varchar(5000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT "" COMMENT "描述" AFTER `image`');
        return true;
    }

    public function down()
    {
//        $this->alterColumn(SubActivity::tableName(), 'describe','varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT "" COMMENT "描述" AFTER `image`');
        return true;
    }
}
