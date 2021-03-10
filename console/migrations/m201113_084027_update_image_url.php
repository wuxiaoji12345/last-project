<?php

use yii\db\Migration;

/**
 * Class m201113_084027_update_image_url
 */
class m201113_084027_update_image_url extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%image_url}}', 'image_key', $this->string(150)->null()->defaultValue('')->comment("图片KEY"));
        $this->addColumn('{{%image_url}}', 'rebroadcast_status', $this->smallInteger(1)->null()->defaultValue(1)->comment("翻拍图检测 0 未参加 1 参加"));
        $this->addColumn('{{%image_url}}', 'is_rebroadcast', $this->smallInteger(1)->null()->defaultValue(0)->comment("0 正常 1 翻拍"));
        $this->addColumn('{{%image_url}}', 'similarity_status', $this->smallInteger(1)->null()->defaultValue(0)->comment("相似图检测 0 未参加 1 参加"));
        $this->addColumn('{{%image_url}}', 'is_similarity', $this->smallInteger(1)->null()->defaultValue(0)->comment("0 正常 1 相似"));
        $this->addColumn('{{%image_url}}', 'similarity_result', $this->json()->null()->comment("相似图结果"));
        $this->addColumn('{{%image_url}}', 'status', $this->smallInteger(1)->null()->defaultValue(1));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%image_url}}', 'image_key');
        $this->dropColumn('{{%image_url}}', 'rebroadcast_status');
        $this->dropColumn('{{%image_url}}', 'is_rebroadcast');
        $this->dropColumn('{{%image_url}}', 'similarity_status');
        $this->dropColumn('{{%image_url}}', 'is_similarity');
        $this->dropColumn('{{%image_url}}', 'similarity_result');
        $this->dropColumn('{{%image_url}}', 'status');

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201113_084027_update_image_url cannot be reverted.\n";

        return false;
    }
    */
}
