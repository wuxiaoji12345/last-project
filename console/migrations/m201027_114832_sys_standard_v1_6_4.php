<?php

use yii\db\Migration;
use api\models\Standard;
use api\models\RuleOutputInfo;

/**
 * Class m201027_114832_sys_standard_v1_6_4
 */
class m201027_114832_sys_standard_v1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Standard::tableName(), 'is_need_qc', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否需要qc：0初始状态，1需要qc，2不需要\' AFTER `is_deleted`');
        $this->addColumn(Standard::tableName(), 'need_qc_data', 'varchar(1000) NOT NULL DEFAULT \'\' COMMENT \'需要qc的生动化数据\' AFTER `is_need_qc`');
        $this->dropIndex('unique', RuleOutputInfo::tableName());
        $this->createIndex('unique', RuleOutputInfo::tableName(), ['standard_id', 'statistical_id', 'node_index', 'sub_activity_id'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201027_114832_sys_standard_v1_6_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_114832_sys_standard_v1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}
