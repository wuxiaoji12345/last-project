<?php

use yii\db\Migration;

/**
 * Class m201230_093828_v_1_8_3_plan_route
 */
class m201230_093828_v_1_8_3_plan_route extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $sql = "-- 售点线路字段
//                ALTER TABLE `sys_plan`
//                ADD COLUMN `route_code_str` text NULL COMMENT '售点线路' AFTER `delete_store_option`;
//                -- 历史数据
//                SET session group_concat_max_len=15000;
//                update sys_plan p1 LEFT JOIN (SELECT p.id, GROUP_CONCAT(distinct route_code) route_code_str from sys_plan p
//                LEFT JOIN sys_plan_store_relation r on p.id = r.plan_id
//                LEFT JOIN sys_store s on s.store_id = r.store_id
//                GROUP BY p.id) p2 on p2.id = p1.id
//                set p1.route_code_str = if(p2.route_code_str is null,'',p2.route_code_str);
//                ";
//        Yii::$app->db->createCommand($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201230_093828_v_1_8_3_plan_route cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201230_093828_v_1_8_3_plan_route cannot be reverted.\n";

        return false;
    }
    */
}
