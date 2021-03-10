<?php

use yii\db\Migration;

/**
 * Class m201028_104900_alter_survey
 */
class m201028_104900_alter_survey extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%survey}}', 'region_code', $this->string(15)->null()->comment("大区编码"));
        $this->createIndex('index_region_code', '{{%survey}}', ['region_code']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%survey}}', 'region_code');

        return true;
    }
}
