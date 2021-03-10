<?php

use yii\db\Migration;

/**
 * Class m201023_143523_create_download
 */
class m201023_143523_create_download extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%download}}', [
            'id' => $this->primaryKey(),
            'task_id' => $this->string(50)->null()->comment('任务ID'),
            'uid' => $this->integer(11)->notNull()->comment('操作人'),
            'file_path' => $this->string(150)->null()->comment('文件地址'),
            'file_name' => $this->string(50)->null()->comment('文件名称'),
            'file_size' => $this->string(10)->null()->comment('文件大小'),
            'download_url' => $this->string(150)->null()->comment('下载地址'),
            'download_num' => $this->integer(11)->null()->comment('下载次数'),
            'download_status' => $this->smallInteger(1)->defaultValue(0)->comment('状态 0 初始 1任务中 2成功'),
            'status' => $this->smallInteger(1)->defaultValue(1)->comment('状态 0 删除 1正常'),
            'created_at' => $this->integer(11)->notNull()->comment('创建时间'),
            'updated_at' => $this->integer(11)->notNull()->comment('最后更新时间')
        ],"CHARACTER SET utf8 ENGINE=InnoDB");
        $this->createIndex('index_uid', '{{%download}}', ['uid']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%download}}');

        return true;
    }
}
