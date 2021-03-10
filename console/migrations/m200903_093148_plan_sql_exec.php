<?php

use api\models\CheckType;
use api\models\Standard;
use yii\db\Migration;
use api\models\Plan;

/**
 * Class m200903_093148_plan_sql_exec
 */
class m200903_093148_plan_sql_exec extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = 'update '. Plan::tableName(). ' left join '. Standard::tableName().' s on s.id = standard_id set rectification_model = '. Plan::RECTIFICATION_MODEL_WITH_CYCLE.
            ' where check_type_id = '. CheckType::SHORT_AGREEMENTS['check_type_id'];

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200903_093148_plan_sql_exec cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200903_093148_plan_sql_exec cannot be reverted.\n";

        return false;
    }
    */
}
