<?php

use yii\db\Migration;
use api\models\Plan;
use api\models\PlanBatch;

/**
 * Class m201026_111305_batch_v1_6_4
 */
class m201026_111305_batch_v1_6_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "ALTER TABLE " . Plan::tableName(). " 
                ADD COLUMN `is_push_zft` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否推送ZFT，1推送、2不推送' AFTER `status`,
                ADD COLUMN `is_qc` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否人工复核，1需要、2不需要' AFTER `is_push_zft`;";
        $this->execute($sql);

        $sql = "ALTER TABLE " . PlanBatch::tableName(). " 
                ADD COLUMN `is_push_zft` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否推送ZFT，1推送、2不推送' AFTER `status`,
                ADD COLUMN `is_qc` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否人工复核，1需要、2不需要' AFTER `is_push_zft`;";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201026_111305_batch_v1_6_4 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201026_111305_batch_v1_6_4 cannot be reverted.\n";

        return false;
    }
    */
}
