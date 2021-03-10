<?php

use yii\db\Migration;

/**
 * Class m201217_064424_add_img_type_to_image_url
 */
class m201217_064424_add_img_type_to_image_url extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(\api\models\ImageUrl::tableName(), 'img_type', "tinyint(1) default 1 not null comment '1:图像识别,2:问卷留底,3:售点纬度图片'");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201217_064424_add_img_type_to_image_url cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201217_064424_add_img_type_to_image_url cannot be reverted.\n";

        return false;
    }
    */
}
