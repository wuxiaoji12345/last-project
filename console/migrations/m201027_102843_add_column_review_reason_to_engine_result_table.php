<?php

use yii\db\Migration;

/**
 * Class m201027_102843_add_column_review_reason_to_engine_result_table
 */
class m201027_102843_add_column_review_reason_to_engine_result_table extends Migration
{
    public function up()
    {
        $this->addColumn('{{%engine_result}}', 'review_reason', 'VARCHAR(200) COMMENT "QC修改原因"');
    }
    
    public function down()
    {
        $this->dropColumn('{{%engine_result}}', 'review_reason');
    }
}
