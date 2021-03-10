<?php

use yii\db\Migration;

/**
 * Class m200923_110820_sys_page_view_v1_3_1
 */
class m200923_110820_sys_page_view_v1_3_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = 'CREATE TABLE `sys_page_view` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `env` varchar(255) NOT NULL COMMENT \'系统名\',
  `image_report_survey_click` int(11) NOT NULL DEFAULT \'0\' COMMENT \'图片查看模块，以走访为维度的接口调用次数\',
  `image_report_click` int(11) NOT NULL DEFAULT \'0\' COMMENT \'图片查看模块，以单张图片为维度的接口调用次数\',
  `start_time` int(11) NOT NULL DEFAULT \'0\' COMMENT \'开启计数时间\',
  `last_time` int(11) NOT NULL DEFAULT \'0\' COMMENT \'最后一次计数时间\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200923_110820_sys_page_view_v1_3_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200923_110820_sys_page_view_v1_3_1 cannot be reverted.\n";

        return false;
    }
    */
}
