<?php

use yii\db\Migration;

/**
 * Class m201207_134040_sys_survey_ine_channel
 */
class m201207_134040_sys_survey_ine_channel extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_survey_ine_channel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `survey_code` varchar(100) NOT NULL DEFAULT '' COMMENT '走访code',
  `ine_channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'ine渠道id',
  `ine_config_timestamp_id` int(20) NOT NULL DEFAULT '0' COMMENT 'ine配置时间戳',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
  `created_at` int(11) NOT NULL COMMENT '添加时间',
  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'db更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4  COMMENT='走访ine渠道中间表';";
        Yii::$app->db->createCommand($sql)->execute();
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201207_134040_sys_survey_ine_channel cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201207_134040_sys_survey_ine_channel cannot be reverted.\n";

        return false;
    }
    */
}
