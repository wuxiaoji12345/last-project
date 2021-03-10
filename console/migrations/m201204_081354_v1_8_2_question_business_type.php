<?php

use yii\db\Migration;

/**
 * Class m201204_081354_v1_8_2_question_business_type
 */
class m201204_081354_v1_8_2_question_business_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "truncate sys_question_business_type;
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (1, '汽水铺货问卷', 9, '', 1, 0, 0, '2020-12-10 18:56:54');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (2, '非汽水铺货问卷', 8, '', 1, 0, 0, '2020-12-10 18:56:56');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (3, '排面问卷', 7, '', 1, 0, 0, '2020-12-10 18:56:58');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (4, '冰柜问卷', 6, '', 1, 0, 0, '2020-12-10 18:57:00');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (5, '餐饮专属问卷', 5, '', 1, 0, 0, '2020-12-04 16:15:33');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (6, '渠道活动问卷', 4, '', 1, 0, 0, '2020-12-10 18:57:09');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (7, '价格沟通问卷', 3, '', 1, 0, 0, '2020-12-10 18:57:11');
               INSERT INTO `check_test`.`sys_question_business_type`(`id`, `title`, `sort`, `note`, `status`, `created_at`, `updated_at`, `update_time`) VALUES (8, '其他问卷', 2, '', 1, 0, 0, '2020-12-10 18:57:13');
               
               update sys_question set business_type_id = 8 where business_type_id > 8;";
        Yii::$app->db->createCommand($sql)->execute();
        return true;

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
        echo "m201204_081354_v1_8_2_question_business_type cannot be reverted.\n";

        return false;
    }
    */
}
