<?php

use yii\db\Migration;

/**
 * Class m200903_030103_sys_activation_send_zft_info
 */
class m200903_030103_sys_activation_send_zft_info extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = 'CREATE TABLE `sys_activation_send_zft_info` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `standard_id` int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'成功图像标准id\',
  `survey_code` varchar(100) NOT NULL DEFAULT \'\' COMMENT \'走访code\',
  `activation_id` int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'生动化编号\',
  `activation_name` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'生动化名称\',
  `store_id` varchar(16) NOT NULL DEFAULT \'\' COMMENT \'售点id\',
  `output_list` text NOT NULL COMMENT \'绑定的输出项组\',
  `protocol_id` int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'协议模板id\',
  `is_standard` int(11) NOT NULL DEFAULT \'0\' COMMENT \'ZFT的isStandard字段\',
  `outlet_contract_id` int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'zft客户协议id\',
  `check_count_field` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'额外检查的字段（排面数，地堆数，层数）\',
  `activation_status` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'生动化检查结果的状态： 2未检查 1检查成功 0检查失败\',
  `all_activation_status` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'该检查项目所有生动化的状态： 2未检查 1检查成功 0检查失败\',
  `is_send_zft` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'是否已推送zft：0未推送 1推送成功 2推送失败\',
  `send_zft_time` int(11) NOT NULL DEFAULT \'0\' COMMENT \'发送zft的时间\',
  `status` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'删除标识：1有效，0无效\',
  `created_at` int(11) NOT NULL COMMENT \'添加时间\',
  `updated_at` int(11) NOT NULL COMMENT \'业务更新时间\',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT \'db更新时间\',
  PRIMARY KEY (`id`),
  KEY `standard_id` (`standard_id`) USING BTREE,
  KEY `activation_id` (`activation_id`),
  KEY `store_id` (`store_id`),
  KEY `protocol_id` (`protocol_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT=\'生动化发送zft详情表\'';
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200903_030103_sys_activation_send_zft_info cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200903_030103_sys_activation_send_zft_info cannot be reverted.\n";

        return false;
    }
    */
}
