<?php

use yii\db\Migration;
use api\models\RuleOutputInfo;

/**
 * Class m200918_100735_sys_rule_output_info_v1_3
 */
class m200918_100735_sys_rule_output_info_v1_3 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropIndex('standard_id', RuleOutputInfo::tableName());
        $this->dropIndex('statistical_id', RuleOutputInfo::tableName());
        $this->createIndex('unique', RuleOutputInfo::tableName(), ['standard_id', 'statistical_id', 'node_index'], true);
        $this->addColumn(RuleOutputInfo::tableName(), 'standard_status', 'tinyint(1) NOT NULL DEFAULT 1 COMMENT \'输出项删除时规则的状态 0未启用 1已启用\' AFTER `formats`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200918_100735_sys_rule_output_info_v1_3 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200918_100735_sys_rule_output_info_v1_3 cannot be reverted.\n";

        return false;
    }
    */
}
