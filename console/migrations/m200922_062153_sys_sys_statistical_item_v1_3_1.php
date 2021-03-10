<?php

use yii\db\Migration;
use api\models\StatisticalItem;

/**
 * Class m200922_062153_sys_sys_statistical_item_v1_3_1
 */
class m200922_062153_sys_sys_statistical_item_v1_3_1 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn(StatisticalItem::tableName(), 'item_status', 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'启用状态0未启用，1启用，2禁用\' AFTER `setup_step`');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200922_062153_sys_sys_statistical_item_v1_3_1 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200922_062153_sys_sys_statistical_item_v1_3_1 cannot be reverted.\n";

        return false;
    }
    */
}
