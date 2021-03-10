<?php

use yii\db\Migration;

/**
 * Class m201113_071108_sys_snapshot_rule_output_info_smart_143
 */
class m201113_071108_sys_snapshot_rule_output_info_smart_143 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_snapshot_rule_output_info` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rule_output_info_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '规则引擎输出项表id',
  `standard_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '标准id',
  `statistical_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '统计项目id',
  `node_index` int(11) NOT NULL COMMENT '规则引擎id',
  `node_name` text NOT NULL COMMENT '输出项名称',
  `output_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '输出项类型 0待定 1 数值型 2布尔型 ',
  `sub_activity_id` int(11) NOT NULL DEFAULT '0' COMMENT '子活动id',
  `is_all_scene` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否全场景 0 否 1 是',
  `scene_type` varchar(255) NOT NULL DEFAULT '' COMMENT '输出项场景类型',
  `scene_code` varchar(1000) NOT NULL DEFAULT '' COMMENT '输出项场景code',
  `is_main` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否主输出项 0 否 1 是',
  `is_score` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否得分项 0 否 1是',
  `is_vividness` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是生动化项 0否 1是',
  `sort_id` int(11) NOT NULL DEFAULT '0' COMMENT '大中台排序id',
  `tag` tinyint(1) NOT NULL DEFAULT '0' COMMENT '最新变动状态 0正常 1最近新增 2最近删除',
  `formats` varchar(255) NOT NULL DEFAULT '' COMMENT '输出项格式化参数',
  `standard_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '输出项删除时规则的状态 0未启用 1已启用',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除状态 0删除 1有效',
  `created_at` int(11) NOT NULL COMMENT '添加时间',
  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique` (`standard_id`,`statistical_id`,`node_index`,`sub_activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='引擎输出项明细快照表';";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201113_071108_sys_snapshot_rule_output_info_smart_143 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201113_071108_sys_snapshot_rule_output_info_smart_143 cannot be reverted.\n";

        return false;
    }
    */
}
