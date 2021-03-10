<?php

use yii\db\Migration;

/**
 * Class m200804_035648_batch_index
 */
class m200804_035648_batch_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200804_035648_batch_index cannot be reverted.\n";

        return false;
    }

    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $sql = "ALTER TABLE `sys_survey` 
                    DROP INDEX `survey_code`,
                    DROP INDEX `store_id`,
                    ADD INDEX `survey_code`(`survey_code`) USING HASH,
                    ADD INDEX `store_id`(`store_id`) USING HASH;
                ALTER TABLE `init_table` 
                    ADD INDEX `created_at`(`created_at`),
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_engine_result` 
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_image` 
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_image_url` 
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_plan_store_relation` 
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_question` 
                    ADD INDEX `company_code`(`company_code`),
                    ADD INDEX `type`(`type`),
                    ADD INDEX `scene_type_id`(`scene_type_id`),
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_question_answer` 
                    ADD INDEX `store_id`(`store_id`) USING HASH,
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_result_node` 
                    ADD INDEX `rule_output_node_id`(`rule_output_node_id`),
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_rule_output_info` 
                    ADD INDEX `update_time`(`update_time`);
                ALTER TABLE `sys_survey_question` 
                    ADD INDEX `update_time`(`update_time`);
                    ";
        $this->execute($sql);
    }

    public function down()
    {
        echo "m200804_035648_batch_index cannot be reverted.\n";

        return false;
    }

}
