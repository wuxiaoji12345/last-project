<?php

use yii\db\Migration;

/**
 * Class m201120_122126_v1_8_1_ine_config
 */
class m201120_122126_v1_8_1_ine_config extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_ine_channel` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `year` int(11) NOT NULL DEFAULT '0' COMMENT '年份',
                  `channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '渠道id',
                  `channel_code` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道code',
                  `channel_name` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道名称',
                  `standard_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '检查项目ID',
                  `is_ine` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否ine，0非ine,1是ine',
                  `ine_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'INE配置状态，0未配置，1已发布，2暂存',
                  `last_publish_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后一次配置保存并生效时间',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `update_time` (`update_time`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='Ine渠道表';";

        Yii::$app->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE `sys_ine_config` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `ine_channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'ine渠道id',
                  `p_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '父级id',
                  `channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '渠道id',
                  `group` varchar(10) NOT NULL DEFAULT '' COMMENT '展示分组',
                  `group_title` varchar(255) NOT NULL DEFAULT '' COMMENT '展示分组名称',
                  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '指标项标题',
                  `output_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '输出项类型 0待定 1 数值型 2布尔型 3百分比',
                  `level` tinyint(4) NOT NULL DEFAULT '0' COMMENT '层级，1-4',
                  `report_examer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '检查员报表是否展示 ，0不展示 ，1展示不',
                  `report_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '高管报表是否展示  0不展示 ，1展示 ',
                  `rule_output_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '检查项目输出项id',
                  `node_index` int(20) NOT NULL DEFAULT '0' COMMENT '引擎输出项id',
                  `max_score` varchar(100) NOT NULL DEFAULT '' COMMENT '满分',
                  `sort` smallint(4) NOT NULL DEFAULT '0' COMMENT '排序,值小在前',
                  `sub_level` tinyint(4) NOT NULL DEFAULT '0' COMMENT '子级层级，如果2级项此字段为4，代表页面展示时，2级下面直接展示4级指标项',
                  `display` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否展示 0不展示 ，1展示 ，详情直接不展示',
                  `tree_display` tinyint(1) NOT NULL DEFAULT '1' COMMENT '详情树形结构下，是否展示（如果不展示 ，会直接展示下一级），0不展示 ，1展示',
                  `display_style` tinyint(1) NOT NULL DEFAULT '1' COMMENT '详情展示样式 0tab展示, 1分组展示4组',
                  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                  `update_user` bigint(20) NOT NULL DEFAULT '0' COMMENT '更新用户id',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `update_time` (`update_time`),
                  KEY `ine_channel_id` (`ine_channel_id`) USING BTREE
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='ine指标项配置表';";

        Yii::$app->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE `sys_ine_config_snapshot` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `ine_config_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'ine_config表主键ID',
                  `ine_config_timestamp_id` int(20) NOT NULL DEFAULT '0' COMMENT 'ine配置时间戳',
                  `ine_channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'ine渠道id',
                  `p_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '父级id',
                  `channel_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '渠道id',
                  `standard_id` int(11) NOT NULL DEFAULT '0' COMMENT '检查项目ID',
                  `group` varchar(10) NOT NULL DEFAULT '' COMMENT '展示分组',
                  `group_title` varchar(255) NOT NULL DEFAULT '' COMMENT '展示分组名称',
                  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '指标项标题',
                  `output_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '输出项类型 0待定 1 数值型 2布尔型 3百分比',
                  `level` tinyint(4) NOT NULL DEFAULT '0' COMMENT '层级，1-4',
                  `report_examer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '检查员报表是否展示 ，0不展示 ，1展示不',
                  `report_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '高管报表是否展示  0不展示 ，1展示 ',
                  `rule_output_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '检查项目输出项id',
                  `node_index` int(20) NOT NULL DEFAULT '0' COMMENT '引擎输出荐id',
                  `max_score` varchar(100) NOT NULL DEFAULT '' COMMENT '满分',
                  `sort` smallint(4) NOT NULL DEFAULT '0' COMMENT '排序,值小在前',
                  `sub_level` tinyint(4) NOT NULL DEFAULT '0' COMMENT '子级层级，如果2级项此字段为4，代表页面展示时，2级下面直接展示4级指标项',
                  `display` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否展示 0不展示 ，1展示 ，详情直接不展示',
                  `tree_display` tinyint(1) NOT NULL DEFAULT '1' COMMENT '详情树形结构下，是否展示（如果不展示 ，会直接展示下一级），0不展示 ，1展示',
                  `display_style` tinyint(1) NOT NULL DEFAULT '1' COMMENT '详情展示样式 0tab展示, 1分组展示4组',
                  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                  `update_user` bigint(20) NOT NULL DEFAULT '0' COMMENT '更新用户id',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `update_time` (`update_time`),
                  KEY `ine_channel_id` (`ine_channel_id`) USING BTREE,
                  KEY `idx_id` (`ine_config_timestamp_id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='ine指标项配置暂存快照表';";

        Yii::$app->db->createCommand($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201120_122126_v1_8_1_ine_config cannot be reverted.\n";

        return false;
    }
    */
}
