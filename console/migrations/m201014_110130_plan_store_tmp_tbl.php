<?php

use yii\db\Migration;

/**
 * Class m201014_110130_plan_store_tmp_tbl
 */
class m201014_110130_plan_store_tmp_tbl extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%plan_store_tmp}}', [
            'id' => 'BIGINT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'plan_id' => "INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '检查计划id'",
            'store_id' => "varchar(16) NOT NULL DEFAULT '' COMMENT '售点id'",
            'import_type' => "tinyint(1) NOT NULL DEFAULT '0' COMMENT '导入类型0导入，1剔除'",
            'check_status' => "tinyint(1) NOT NULL DEFAULT '0' COMMENT '校验是否通过0未校验，1通过，2失败，3匹配成功，4匹配失败，5完全失败，6导入临时筛选，7导入临时状态失败，8导入临时状态成功'",
            'note' => "varchar(64) NOT NULL DEFAULT '' COMMENT '备注'",
            'update_time' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间'",
            'PRIMARY KEY (`id`)',
            'KEY `store_id` (`store_id`) USING BTREE',
            'KEY `plan_id` (`plan_id`,`check_status`) USING BTREE'
        ], "CHARACTER SET utf8 ENGINE=InnoDB");

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%plan_store_tmp}}');

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201014_110130_plan_store_tmp_tbl cannot be reverted.\n";

        return false;
    }
    */
}
