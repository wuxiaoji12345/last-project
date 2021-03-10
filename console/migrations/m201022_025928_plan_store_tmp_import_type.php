<?php

use api\models\PlanStoreTmp;
use yii\db\Migration;

/**
 * Class m201022_025928_plan_store_tmp_import_type
 */
class m201022_025928_plan_store_tmp_import_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(PlanStoreTmp::tableName(), 'import_type', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT \'导入类型0导入，1剔除\' AFTER `plan_id`');
        $this->createIndex('import_type', PlanStoreTmp::tableName(), 'import_type');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(PlanStoreTmp::tableName(), 'import_type');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201022_025928_plan_store_tmp_import_type cannot be reverted.\n";

        return false;
    }
    */
}
