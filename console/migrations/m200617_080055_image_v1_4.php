<?php

use api\models\Image;
use yii\db\Migration;

/**
 * Class m200617_080055_image_v1_4
 */
class m200617_080055_image_v1_4 extends Migration
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
        $this->addColumn(Image::tableName(), 'get_photo_time', 'timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'拍照时间\' AFTER `number`');
        $this->addColumn(Image::tableName(), 'sub_activity_id', 'int(11) NOT NULL DEFAULT 0 COMMENT "子活动id" AFTER `get_photo_time`');
        return true;
    }

    public function down()
    {
        $this->dropColumn(Image::tableName(), 'sub_activity_id');
        return true;
    }
}
