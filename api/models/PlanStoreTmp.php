<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%plan_store_tmp}}".
 *
 * @property int $id
 * @property int $plan_id 检查计划id
 * @property int $import_type 导入类型0导入，1剔除
 * @property string $store_id 售点id
 * @property int $check_status 校验是否通过0未校验，1通过，2失败
 * @property string $note 备注
 * @property string $update_time 更新时间
 */
class PlanStoreTmp extends baseModel
{
    const DEL_FLAG = false;

    const IMPORT_TYPE_ADD = 0;                  // 添加
    const IMPORT_TYPE_DELETE = 1;               // 剔除

    const CHECK_STATUS_DEFAULT = 0;
    const CHECK_STATUS_PASS = 1;                // 导入成功
    const CHECK_STATUS_FAIL = 2;                // 导入失败
    const CHECK_STATUS_FILTER_PASS = 3;         // 匹配成功
    const CHECK_STATUS_FILTER_FAIL = 4;         // 匹配失败
    const CHECK_STATUS_REAL_FAIL = 5;           // 完全失败
    const CHECK_STATUS_TEMPORARY_SCREEN = 6;    // 导入临时筛选
    const CHECK_STATUS_IMPORT_TMP_FAIL = 7;     // 导入临时状态失败
    const CHECK_STATUS_IMPORT_TMP_SUCCESS = 8;  // 导入临时状态成功

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_store_tmp}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['plan_id', 'store_id', 'check_status'], 'required'],
            [['plan_id', 'check_status', 'import_type'], 'integer'],
            [['update_time'], 'safe'],
            [['store_id'], 'string', 'max' => 16],
            [['note'], 'string', 'max' => 64],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'plan_id' => '检查计划id',
            'import_type' => '导入类型',
            'store_id' => '售点id',
            'check_status' => '校验是否通过',
            'note' => '备注',
            'update_time' => '更新时间',
        ];
    }

    public function getStore(){
        return $this->hasOne(Store::class, ['store_id'=> 'store_id']);
    }

    public static function removeStore($plan_id, $import_type, $store_id = [])
    {
        $sql = 'DELETE 
                FROM
                    {{%plan_store_tmp}}
                WHERE
                    plan_id = :plan_id and import_type = :import_type';
        $param = ['plan_id' => $plan_id, 'import_type'=> $import_type];
        if (!empty($store_id)) {
            $sql .= ' AND store_id in (';
            foreach ($store_id as $key => $item) {
                $sql .= ':store_id_' . $key . ',';
                $param[':store_id_' . $key] = $item;
            }
            // 去除最后一个逗号
            $sql = substr($sql, 0, -1);
            $sql .= ')';
        }
        Yii::$app->db->createCommand($sql, $param)->execute();
    }

    /**
     * 去除筛选条件进来的售点
     * @param $plan_id
     * @throws \yii\db\Exception
     */
//    public static function removeFilterStore($plan_id)
//    {
//        $sql = 'DELETE
//                FROM
//                    {{%plan_store_tmp}}
//                WHERE
//                    plan_id = :plan_id';
//        $param = ['plan_id' => $plan_id];
//
//        Yii::$app->db->createCommand($sql, $param)->execute();
//        self::deleteAll(['plan_id' => $plan_id, 'check_status' => [
//            self::CHECK_STATUS_REAL_FAIL,
//            self::CHECK_STATUS_FILTER_IMPORT
//        ]]);
//    }

    /**
     * @param $plan_id
     * @param $import_type
     * @throws \yii\db\Exception
     */
    public static function removeDuplicate($plan_id, $import_type)
    {
        $sql = 'DELETE 
                FROM
                    ' . self::tableName() . ' 
                WHERE
                    plan_id = :plan_id and import_type=:import_type
                    AND id NOT IN ( SELECT id FROM ( SELECT min( id ) AS id FROM ' . self::tableName() . '  WHERE plan_id = :plan_id and import_type=:import_type GROUP BY store_id ) AS b )';
        Yii::$app->db->createCommand($sql, ['plan_id' => $plan_id, 'import_type'=> $import_type])->execute();
    }


}
