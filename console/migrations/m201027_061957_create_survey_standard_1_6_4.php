<?php

use yii\db\Migration;

/**
 * Class m201027_061957_create_survey_standard_1_6_4
 */
class m201027_061957_create_survey_standard_1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_survey_standard` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `project_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '项目id',
                  `survey_code` varchar(100) NOT NULL DEFAULT '' COMMENT '走访号',
                  `standard_id` bigint(11) unsigned NOT NULL DEFAULT '0' COMMENT '检查项目id',
                  `user_id` varchar(32) NOT NULL DEFAULT '' COMMENT '用户id',
                  `company_code` varchar(16) NOT NULL DEFAULT '' COMMENT '厂房code',
                  `bu_code` varchar(16) NOT NULL DEFAULT '' COMMENT 'bu_code',
                  `check_type_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '检查类型',
                  `protocol_id` int(11) NOT NULL DEFAULT '0' COMMENT '协议模板id',
                  `title` varchar(32) NOT NULL DEFAULT '' COMMENT '标题别名',
                  `image` text COMMENT '图片',
                  `description` text COMMENT '检查要求描述',
                  `engine_rule_code` varchar(64) NOT NULL DEFAULT '' COMMENT '规则配置id',
                  `set_rule` tinyint(1) DEFAULT '0' COMMENT '是否已经设置过规则 0 未设置 1 已设置',
                  `question_manual_ir` text NOT NULL COMMENT 'ir问卷',
                  `question_manual` text NOT NULL COMMENT '非ir问卷',
                  `scenes_ir_id` text NOT NULL COMMENT '场景id 废弃',
                  `scenes` mediumtext NOT NULL COMMENT '场景集合',
                  `setup_step` tinyint(1) NOT NULL DEFAULT '0' COMMENT '设置步骤0初始化，1创建检查项目，2配置拍照，3设置规则，4设置整改, 5完成设置，6生动化映射',
                  `photo_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拍照类别：0、普通模式，1随报随拍',
                  `standard_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '启用状态0未启用，1启用，2禁用',
                  `is_change` tinyint(1) NOT NULL DEFAULT '0' COMMENT '规则问卷修改状态，0无修改，1有修改',
                  `pos_score` int(3) NOT NULL DEFAULT '100' COMMENT '标准满分',
                  `set_main` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否设置主检查项 0 初始状态 1 是 2 否',
                  `set_vividness` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否设置生动化项 0 初始状态 1 是 2 否',
                  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否被用户删除，0:否 1:是',
                  `is_need_qc` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要qc：0初始状态，1需要qc，2不需要',
                  `need_qc_data` varchar(1000) NOT NULL DEFAULT '' COMMENT '需要qc的生动化数据',
                  `status` tinyint(1) unsigned zerofill NOT NULL DEFAULT '1' COMMENT '删除标记0删除，1有效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `survey_code` (`survey_code`,`standard_id`) USING HASH,
                  KEY `standard_id` (`standard_id`) USING HASH
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='走访检查项目表'";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201027_061957_create_survey_standard_1_6_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_061957_create_survey_standard_1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}