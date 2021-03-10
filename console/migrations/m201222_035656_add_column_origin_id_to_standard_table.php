<?php

use yii\db\Migration;

/**
 * Class m201222_035656_add_column_origin_id_to_standard_table
 */
class m201222_035656_add_column_origin_id_to_standard_table extends Migration
{
    public function up()
    {
        $this->addColumn('{{%standard}}', 'origin_id', 'INT(11) DEFAULT NULL COMMENT "复制源ID"');
    }
    
    public function down()
    {
        $this->dropColumn('{{%standard}}', 'origin_id');
    }
}
