<?php

use yii\db\Migration;

/**
 * Class m200805_085612_question_v1_5
 */
class m200805_085612_question_v1_5 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_question_option` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `question_id` int(11) unsigned NOT NULL COMMENT '问卷id',
                  `option_index` int(11) NOT NULL DEFAULT '0' COMMENT '问卷选项index',
                  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '选项名称',
                  `value` varchar(255) NOT NULL DEFAULT '' COMMENT '选项值',
                  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `idx_question_id` (`question_id`),
                  KEY `update_time` (`update_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='问卷选项表';";
        $this->execute($sql);
        $sql = "CREATE TABLE `sys_question_business_type` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
                  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '业务类型',
                  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序 降序',
                  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
                  `created_at` int(11) NOT NULL COMMENT '添加时间',
                  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
                  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `update_time` (`update_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='问卷业务类型';";
        $this->execute($sql);
        $sql = "ALTER TABLE `sys_question` 
                  ADD COLUMN `business_type_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务类型' AFTER `title`,
                  MODIFY COLUMN `question_type` tinyint(1) NOT NULL COMMENT '问题题型 1是非，2填空, 3可选填空填，4选择题' AFTER `business_type_id`;";
        $this->execute($sql);

        $sql = "INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('非IR问卷', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('汽水铺货', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('汽水排面', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('果汁铺货', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('果汁排面', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('包装水铺货', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('包装水排面', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('即饮茶铺货', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('即饮茶排面', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('酸味奶铺货', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('酸味奶排面', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('价格沟通', 1596622026, 1596622026);
                INSERT INTO `sys_question_business_type`(`title`, `created_at`, `updated_at`) VALUES ('NARTD排面', 1596622026, 1596622026);
                ";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200805_085612_question_v1_5 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200805_085612_question_v1_5 cannot be reverted.\n";

        return false;
    }
    */
}
