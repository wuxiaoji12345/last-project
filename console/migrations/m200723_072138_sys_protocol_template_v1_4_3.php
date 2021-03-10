<?php

use yii\db\Migration;

/**
 * Class m200723_072138_sys_protocol_template_v1_4_3
 */
class m200723_072138_sys_protocol_template_v1_4_3 extends Migration
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
//        $this->addColumn(\api\models\ProtocolTemplate::tableName(), 'company_code', 'varchar(16) NOT NULL DEFAULT \'\' COMMENT \'厂房code\' AFTER `contract_code`');
    }

    public function down()
    {
//        $this->dropColumn(\api\models\ProtocolTemplate::tableName(), 'company_code');

        return false;
    }
}
