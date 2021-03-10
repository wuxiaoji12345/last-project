<?php

use yii\db\Migration;

/**
 * Class m201023_103505_create_plan_batch_tmp_v1_6
 */
class m201023_103505_create_plan_batch_tmp_v1_6 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "CREATE TABLE `sys_plan_batch_tmp` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `file_id` varchar(80) NOT NULL COMMENT '文件唯一标识',
                  `plan_batch_id` int(11) DEFAULT '0' COMMENT '批量检查计划',
                  `contract_code` varchar(255) NOT NULL DEFAULT '' COMMENT '协议编号',
                  `store_id` varchar(16) NOT NULL DEFAULT '' COMMENT '售点编号',
                  `check_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '校验是否通过，0未校验，1通过，2失败',
                  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '检查不通过错误原因',
                  `import_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '导入状态，是否生成检查计划，0未导入 1导入成功 2导入失败',
                  `status` tinyint(1) DEFAULT '1' COMMENT '删除标记0删除，1有效',
                  `created_at` int(11) DEFAULT '0' COMMENT ' 创建时间',
                  `updated_at` int(11) DEFAULT '0' COMMENT '更新时间',
                  `update_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
                  PRIMARY KEY (`id`),
                  KEY `idx_file_id` (`file_id`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='批量创建检查计划临时表'";
        $this->execute($sql);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201023_103505_create_plan_batch_tmp_v1_6 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201023_103505_create_plan_batch_tmp_v1_6 cannot be reverted.\n";

        return false;
    }
    */
}
