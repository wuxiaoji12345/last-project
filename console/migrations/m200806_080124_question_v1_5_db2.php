<?php

use api\models\share\CheckStoreQuestion;
use yii\db\Migration;

/**
 * Class m200806_080124_question_v1_5_db2
 */
class m200806_080124_question_v1_5_db2 extends Migration
{
    public function init()
    {
        $this->db = 'db2';
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = "ALTER TABLE `sys_check_store_question` 
                    ADD COLUMN `business_type_id` INT ( 11 ) NOT NULL DEFAULT 0 COMMENT '业务类型id' AFTER `question_title`,
                    ADD COLUMN `business_type_sort` INT ( 11 ) NOT NULL DEFAULT 0 COMMENT '业务类型排序 降序' AFTER `business_type_id`,
                    ADD COLUMN `business_type_label` VARCHAR ( 255 ) NOT NULL DEFAULT '' COMMENT '业务类型名称' AFTER `business_type_id`,
                    MODIFY COLUMN `question_type` TINYINT ( 1 ) NOT NULL DEFAULT 0 COMMENT '问题题型 1=是非，2=填空,3可选填空题，4选择题' AFTER `business_type_label`,
                    ADD COLUMN `question_options` text NULL COMMENT '问卷选项  问题题型为3、4时有值' AFTER `is_ir`;";

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200806_080124_question_v1_5_db2 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200806_080124_question_v1_5_db2 cannot be reverted.\n";

        return false;
    }
    */
}
