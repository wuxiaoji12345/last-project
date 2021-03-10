<?php

use yii\db\Migration;
use api\models\EngineResult;

/**
 * Class m200608_060156_survey_v1_4
 */
class m200608_060156_engine_result_v1_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        return $this->up();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return $this->down();
    }

    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->addColumn(EngineResult::tableName(), 'statistical_id', "int(11) NOT NULL DEFAULT 0 COMMENT '统计项目id' AFTER `replan_id`");
        $this->addColumn(EngineResult::tableName(), 'is_rectify', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT "是否整改拍照：0、非整改，1是整改" AFTER `statistical_id`');
        $this->addColumn(EngineResult::tableName(), 'p_survey_code', 'varchar(100) NOT NULL DEFAULT "" COMMENT "父走访号" AFTER `is_rectify`');
        $this->addColumn(EngineResult::tableName(), 'send_zft_status', 'tinyint(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT "ZFT推送状态：0、默认未推送，1推送中，2推送成功，3推送失败" AFTER `p_survey_code`');
        $this->addColumn(EngineResult::tableName(), 'send_zft_fail', 'varchar(200) NOT NULL DEFAULT "" COMMENT "ZFT推送失败原因" AFTER `send_zft_status`');
        return true;
    }

    public function down()
    {
        $this->dropColumn(EngineResult::tableName(), 'is_rectify');
        $this->dropColumn(EngineResult::tableName(), 'p_survey_code');
        $this->dropColumn(EngineResult::tableName(), 'send_zft_status');
        $this->dropColumn(EngineResult::tableName(), 'send_zft_fail');
        return true;
    }
}
