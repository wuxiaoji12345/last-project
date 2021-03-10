<?php

use yii\db\Migration;

/**
 * Class m201023_143020_create_image_similarity
 */
class m201023_143020_create_image_similarity extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%image_similarity}}', [
            'id' => $this->primaryKey(),
            'image_id' => $this->integer(11)->notNull()->comment('场景报告ID'),
            'image_key' => $this->string(150)->notNull()->comment('图片KEY'),
            'survey_code' => $this->string(150)->notNull()->comment('走访号'),
            'similarity_image_id' => $this->integer(11)->notNull()->comment('相似场景ID'),
            'similarity_image_key' => $this->string(150)->notNull()->comment('图片KEY'),
            'similarity_survey_code' => $this->string(150)->notNull()->comment('相似走访号'),
            'similarity_number' => $this->float()->notNull()->comment('相似置信度'),
            'similarity_cause' => $this->smallInteger(1)->notNull()->defaultValue(0)->comment('判定相似原因 1.不同线路，不同售点，不同时间 2.不同线路，不同售点，相同时间 3.相同线路，不同售点，不同时间 4.相同线路，不同售点，相同时间 5.相同线路，相同售点，不同时间 6.相同线路，相同售点，相同时间'),
            'similarity_status' => $this->smallInteger(1)->notNull()->defaultValue(1)->comment('状态 0判定不相似 1判定相似'),
            'status' => $this->smallInteger(1)->notNull()->defaultValue(1)->comment('状态 0逻辑删除 1正常'),
            'created_at' => $this->integer(11)->notNull()->comment('创建时间'),
            'updated_at' => $this->integer(11)->notNull()->comment('最后更新时间')
        ],"CHARACTER SET utf8 ENGINE=InnoDB");
        $this->createIndex('index_image_id', '{{%image_similarity}}', ['image_id']);
        $this->createIndex('index_survey_code', '{{%image_similarity}}', ['survey_code']);
        $this->createIndex('index_similarity_image_id', '{{%image_similarity}}', ['similarity_image_id']);
        $this->createIndex('index_similarity_survey_code', '{{%image_similarity}}', ['similarity_survey_code']);
        $this->createIndex('index_similarity_cause', '{{%image_similarity}}', ['similarity_cause']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%image_similarity}}');

        return true;
    }
}
