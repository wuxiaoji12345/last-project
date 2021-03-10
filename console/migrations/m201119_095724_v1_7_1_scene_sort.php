<?php

use api\models\share\Scene;
use yii\db\Migration;

/**
 * Class m201119_095724_v1_7_1_scene_sort
 */
class m201119_095724_v1_7_1_scene_sort extends Migration
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
        $this->addColumn(Scene::tableName(), 'sort', "int(11) NOT NULL DEFAULT '999' COMMENT '排序 升序' AFTER `scene_status`");
        $sql = "update sys_scene set sort = 2 where scene_code = 'KOCOOLER';
                update sys_scene set sort = 4 where scene_code = 'PEPSICOOLER';
                update sys_scene set sort = 5 where scene_code = 'TINGHSINCOOLER';
                update sys_scene set sort = 6 where scene_code = 'NONGFUCOOLER';
                update sys_scene set sort = 7 where scene_code = 'CESTBONCOOLER';
                update sys_scene set sort = 8 where scene_code = 'WAHAHACOOLER';
                update sys_scene set sort = 9 where scene_code = 'WINDCOOLER';
                update sys_scene set sort = 10 where scene_code = 'CUSTCOOLER';
                update sys_scene set sort = 1 where scene_code = 'RACK';
                update sys_scene set sort = 11 where scene_code = 'KOGROUND';
                update sys_scene set sort = 12 where scene_code = 'PEPSIGROUND';
                update sys_scene set sort = 13 where scene_code = 'TINGHSINGROUND';
                update sys_scene set sort = 14 where scene_code = 'NONGFUGROUND';
                update sys_scene set sort = 15 where scene_code = 'CESTBONGROUND';
                update sys_scene set sort = 16 where scene_code = 'WAHAHAGROUND';
                update sys_scene set sort = 17 where scene_code = 'OTHERSGROUND';
                update sys_scene set sort = 18 where scene_code = 'KOFRAME';
                update sys_scene set sort = 19 where scene_code = 'PEPSIFRAME';
                update sys_scene set sort = 20 where scene_code = 'TINGHSINFRAME';
                update sys_scene set sort = 21 where scene_code = 'NONGFUFRAME';
                update sys_scene set sort = 22 where scene_code = 'CESTBONFRAME';
                update sys_scene set sort = 23 where scene_code = 'WAHAHAFRAME';
                update sys_scene set sort = 24 where scene_code = 'OTHERSFRAME';
                update sys_scene set sort = 25 where scene_code = 'KOBOX';
                update sys_scene set sort = 26 where scene_code = 'PEPSIBOX';
                update sys_scene set sort = 27 where scene_code = 'TINGHSINBOX';
                update sys_scene set sort = 28 where scene_code = 'NONGFUBOX';
                update sys_scene set sort = 29 where scene_code = 'CESTBONBOX';
                update sys_scene set sort = 30 where scene_code = 'WAHAHABOX';
                update sys_scene set sort = 31 where scene_code = 'KOSTATION';
                update sys_scene set sort = 32 where scene_code = 'PEPSISTATION';
                update sys_scene set sort = 33 where scene_code = 'TINGHSINSTATION';
                update sys_scene set sort = 34 where scene_code = 'NONGFUSTATION';
                update sys_scene set sort = 35 where scene_code = 'CESTBONSTATION';
                update sys_scene set sort = 36 where scene_code = 'WAHAHASTATION';
                update sys_scene set sort = 37 where scene_code = 'KOMULTIPLE';
                update sys_scene set sort = 38 where scene_code = 'PEPSIMULTIPLE';
                update sys_scene set sort = 39 where scene_code = 'TINGHSINMULTIPLE';
                update sys_scene set sort = 40 where scene_code = 'NONGFUMULTIPLE';
                update sys_scene set sort = 41 where scene_code = 'CESTBONMULTIPLE';
                update sys_scene set sort = 42 where scene_code = 'WAHAHAMULTIPLE';
                update sys_scene set sort = 43 where scene_code = 'DISPLAYSTAND';
                update sys_scene set sort = 44 where scene_code = 'VERTICALRACK';
                update sys_scene set sort = 45 where scene_code = 'STACKDISPLAY';
                update sys_scene set sort = 46 where scene_code = 'CASHIERDISPLAY';
                update sys_scene set sort = 47 where scene_code = 'POSM';
                update sys_scene set sort = 48 where scene_code = 'MENU';
                update sys_scene set sort = 49 where scene_code = 'OTHERSDISPLAY';
                update sys_scene set sort = 50 where scene_code = 'SHOPBOARD';
                update sys_scene set sort = 51 where scene_code = 'NORMALDISPLAY';
                update sys_scene set sort = 3 where scene_code = 'KOLAGERWIRERACK';
                update sys_scene set sort = 999 where sort = 0;
                ";

        Yii::$app->db2->createCommand($sql)->execute();

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(Scene::tableName(), 'sort');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201119_095724_v1_7_1_scene_sort cannot be reverted.\n";

        return false;
    }
    */
}
