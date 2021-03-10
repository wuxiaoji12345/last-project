<?php

use api\models\Store;
use yii\db\Migration;

/**
 * Class m201020_120810_v1_6_1_store
 */
class m201020_120810_v1_6_1_store extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Store::tableName(), 'has_equipment', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT "是否有冰柜：0无，1有" AFTER `biz_type_extens_desc`');
        $this->createIndex('company_code', Store::tableName(), 'company_code');
        $this->createIndex('sap_create_date', Store::tableName(), 'sap_create_date');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(Store::tableName(), 'has_equipment');
        $this->dropIndex('company_code', Store::tableName());
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201020_120810_v1_6_1_store cannot be reverted.\n";

        return false;
    }
    */
}
