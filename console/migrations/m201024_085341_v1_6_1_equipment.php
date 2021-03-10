<?php

use api\models\share\Equipment;
use api\models\Store;
use yii\db\Migration;

/**
 * 根据冰柜主数据的记录，将售点表 has_equipment字段更新为 1有冰柜
 * Class m201024_085341_v1_6_1_equipment
 */
class m201024_085341_v1_6_1_equipment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        preg_match("/dbname=([^;]*)/", Yii::$app->db2->dsn, $matches);
        $database = $matches[1];

        $sql = 'update ' . Store::tableName() . ' s INNER JOIN `' . $database . '`.' .
            Equipment::tableName() . ' e on s.store_id = e.outlet_num and e.equipment_type = 12 set has_equipment ='. Store::HAS_EQUIPMENT_YES;
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201024_085341_v1_6_1_equipment cannot be reverted.\n";

        return false;
    }
    */
}
