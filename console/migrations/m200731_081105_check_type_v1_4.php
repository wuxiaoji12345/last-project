<?php

use api\models\CheckType;
use yii\db\Migration;

/**
 * Class m200731_081105_check_type_v1_4
 */
class m200731_081105_check_type_v1_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // ALTER TABLE `check`.`sys_check_type` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '检查类型的类型 0：非协议类 1：协议类' AFTER `note`;
        $this->addColumn(CheckType::tableName(), 'type', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'检查类型的类型 0：非协议类 1：协议类\' AFTER `note`');
        $this->insert(CheckType::tableName(), [
            'id' => '5',
            'project_id' => '0',
            'title' => '短期协议',
            'value' => '',
            'note' => '',
            'type' => CheckType::IS_PROTOCOL_YES,
            'active_status' => CheckType::ACTIVE_STATUS_ENABLE,
            'status' => CheckType::DEL_STATUS_NORMAL,
            'created_at' => time(),
            'updated_at' => time()
        ]);
        $this->insert(CheckType::tableName(), [
            'id' => '6',
            'project_id' => '0',
            'title' => 'TOP协议',
            'value' => '',
            'note' => '',
            'type' => CheckType::IS_PROTOCOL_YES,
            'active_status' => CheckType::ACTIVE_STATUS_ENABLE,
            'status' => CheckType::DEL_STATUS_NORMAL,
            'created_at' => time(),
            'updated_at' => time()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200731_081105_check_type_v1_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200731_081105_check_type_v1_4 cannot be reverted.\n";

        return false;
    }
    */
}
