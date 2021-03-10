<?php

use api\models\RuleOutputInfo;
use yii\db\Migration;
use api\models\RuleOutputInfo;

/**
 * Class m200720_114406_rule_output_info
 */
class m200720_114406_rule_output_info_v1_4_3 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        return $this->up();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return $this->down();
    }


    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->addColumn(RuleOutputInfo::tableName(), 'formats', 'varchar(255) NOT NULL DEFAULT \'\' COMMENT \'输出项格式化参数\' AFTER `tag`');
    }

    public function down()
    {
        $this->dropColumn(RuleOutputInfo::tableName(), 'formats');
        return false;
    }

}
