<?php

use yii\db\Migration;
use api\models\Image;

/**
 * Class m200903_065025_sys_image_v1_4_debug
 */
class m200903_065025_sys_image_v1_4_debug extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn(Image::tableName(), 'number', 'int(11) NOT NULL DEFAULT 0 COMMENT \'图片数量\' AFTER `img_prex_key`');
        $this->addColumn(Image::tableName(),'is_key','tinyint(1) NOT NULL DEFAULT 0 COMMENT \'客户上传图片模式： 0上传url 1上传key\' AFTER `number`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200903_065025_sys_image_v1_4_debug cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200903_065025_sys_image_v1_4_debug cannot be reverted.\n";

        return false;
    }
    */
}
