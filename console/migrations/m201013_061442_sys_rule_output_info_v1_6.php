<?php

use yii\db\Migration;
use api\models\RuleOutputInfo;

/**
 * Class m201013_061442_sys_rule_output_info_v1_6
 */
class m201013_061442_sys_rule_output_info_v1_6 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn(RuleOutputInfo::tableName(), 'scene_code', 'varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT \'\' COMMENT \'输出项场景code\' AFTER `scene_type`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201013_061442_sys_rule_output_info_v1_6 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201013_061442_sys_rule_output_info_v1_6 cannot be reverted.\n";

        return false;
    }
    */
}
