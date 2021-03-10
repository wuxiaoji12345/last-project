<?php

namespace api\models;


/**
 * This is the model class for table "{{%plan_store_relation}}".
 *
 * @property int $id
 * @property int $plan_id 检查计划id
 * @property string $store_id 售点id
 */
class PlanStoreRelation extends baseModel
{
    const DEL_FLAG = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_store_relation}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['plan_id', 'store_id'], 'required'],
            [['plan_id'], 'integer'],
            [['store_id'], 'string', 'max' => 16],
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
            'store_id' => '售点id',
        ];
    }

    public function getCheckStore()
    {
        return $this->hasMany(CheckStoreList::class, ['store_id' => 'store_id']);
    }

    public function getStore()
    {
        return $this->hasOne(Store::class, ['store_id' => 'store_id']);
    }

    public static function addNoRepeat($screen_list,$plan_id)
    {
        $sql1 = 'insert into ' . self::tableName() . ' (plan_id,store_id) values ';
        $sql2 = 'on duplicate key update plan_id = values(plan_id),store_id = values(store_id)';

        $values = '';

        foreach ($screen_list as $v) {
            $store_id = $v;
            if ($values !== '') {
                $values .= ',';
            }
            $values .= "(" . $plan_id . ", '" . $store_id. "')";
        }

        $query = $sql1 . $values . $sql2 . ';';
        \Yii::$app->db->createCommand($query)->execute();
        return [true];
    }

	public static function getPlanId($where)
    {
        $list = self::find()->select('plan_id')->where($where)->asArray()->all();
        $result = [];
        if($list){
            array_walk_recursive($list, function($value) use (&$result) {
                array_push($result, $value);
            });
        }
        return array_unique($result);
    }
}