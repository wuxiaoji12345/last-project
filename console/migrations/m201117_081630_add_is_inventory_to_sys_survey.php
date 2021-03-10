<?php

use yii\db\Migration;
use api\models\Survey;
/**
 * Class m201117_081630_add_is_inventory_to_sys_survey
 */
class m201117_081630_add_is_inventory_to_sys_survey extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Survey::tableName(), 'is_inventory', "tinyint not null default 1 comment '是否清单店 1是0否' after route_code");
        $this->addColumn(Survey::tableName(), 'ine_channel_id', "varchar(50)  NOT NULL DEFAULT '' COMMENT 'ine渠道id' AFTER `is_inventory`");
        $this->addColumn(Survey::tableName(), 'store_name', "varchar(50) NOT NULL DEFAULT '' COMMENT '非清单店售点名称' AFTER `ine_channel_id`");
        $this->addColumn(Survey::tableName(), 'store_address', "varchar(200) NOT NULL DEFAULT '' COMMENT '非清单店售点地址' AFTER `store_name`");
        $this->addColumn(Survey::tableName(), 'year', "int(4) NOT NULL DEFAULT 0 COMMENT '指定ine规则的年份' AFTER `store_address`");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(Survey::tableName(), 'is_inventory');
        $this->dropColumn(Survey::tableName(), 'ine_channel_id');
        $this->dropColumn(Survey::tableName(), 'store_name');
        $this->dropColumn(Survey::tableName(), 'store_address');
        $this->dropColumn(Survey::tableName(), 'year');

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201117_081630_add_is_inventory_to_sys_survey cannot be reverted.\n";

        return false;
    }
    */
}
