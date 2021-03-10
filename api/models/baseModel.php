<?php

namespace api\models;

use yii\behaviors\TimestampBehavior;
use yii\data\Pagination;
use yii\db\ActiveRecord;
use yii\db\StaleObjectException;
use Yii;

class baseModel extends ActiveRecord
{
    const HAS_3004 = false;     // 本表是否有company_code为3004的数据，筛选条件可以不考虑
    const ENABLE_STATUS_FIELD = ''; //启用状态字段不为空说明权限过滤要验证启用状态
    const ENABLE_STATUS_DEFAULT = 0; //一般启用状态的初始化值为0

    const DEL_FLAG = true;
    const DEL_STATUS_DELETE = 0; // db删除
    const DEL_STATUS_NORMAL = 1; // 正常
    const DEL_FIELD = 'status'; // 删除标识字段

    const BU_FLAG = false;  // 是否有company_code, bu_code, user_id 字段

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => time()
            ]
        ];
    }

    /**
     * @param $where
     * @param array $select
     * @param string $index
     * @param boolean $bu_filter_flag
     * @param string $order
     * @return array|ActiveRecord[]
     */
    public static function findAllArray($where, $select = ['*'], $index = '', $bu_filter_flag = false, $order = '')
    {
        $where = self::normalFilter($where, $bu_filter_flag);

        $query = self::find()->where($where)->select($select)->asArray();
        if ($index != '') {
            $query->indexBy($index);
        }
        if ($order != '') {
            $query->orderBy($order);
        }
        return $query->all();
    }

    /**
     * @param $where
     * @param array $select
     * @param boolean $bu_filter_flag
     * @return array|ActiveRecord|null
     */
    public static function findOneArray($where, $select = ['*'], $bu_filter_flag = false)
    {
        $where = self::normalFilter($where, $bu_filter_flag);

        $query = self::find()->where($where)->select($select)->asArray();
        return $query->one();
    }

    /**
     * 基础筛选条件
     * @param $where
     * @param $bu_filter_flag
     * @return array
     */
    public static function normalFilter($where, $bu_filter_flag)
    {
        if (static::DEL_FLAG == true) {
            $where = self::delFilter($where);
        }
        if ($bu_filter_flag) {
            $where = self::buFilter($where);
        }
        return $where;
    }

    /**
     * 删除标识
     * @param $where
     * @return array
     */
    private static function delFilter($where)
    {
        $where = ['and', $where];
        $where[] = ['=', 'status', static::DEL_STATUS_NORMAL];
        return $where;
    }

    /**
     * bu筛选
     * @param $where
     * @return array
     */
    private static function buFilter($where)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(static::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        return $where;
    }

    /**
     * @return false|int
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function delete()
    {
        if (static::DEL_FLAG == true) {
            $this->{self::DEL_FIELD} = self::DEL_STATUS_DELETE;
            $pri_keys = $this->primaryKey();
            // 暂时只考虑1个主键
            return $this::updateAll([self::DEL_FIELD => self::DEL_STATUS_DELETE], [$pri_keys[0] => $this->{$pri_keys[0]}]);
        } else {
            return parent::delete();
        }
    }

    /**
     * 获取错误信息
     * @param bool $all 是否全部返回
     * @return string
     */
    public function getErrStr($all = true)
    {
        if (!$this->hasErrors()) {
            return '';
        }
        if ($all) {
            $strArr = [];
            $errors = $this->getErrorSummary(true);
            foreach ($errors as $error) {
                $strArr[] = $error;
            }
            $errStr = implode(' ', $strArr);
        } else {
            $err = $this->getFirstErrors();
            $attr = array_keys($err);
            $errStr = $err[$attr[0]];
        }
        return $errStr;
    }

    /**
     * {@inheritdoc}
     * @param $bu_filter_flag boolean
     * @return static|ActiveRecord
     */
    public static function findOne($condition, $bu_filter_flag = false)
    {
        if (static::DEL_FLAG) {
            $condition[static::DEL_FIELD] = static::DEL_STATUS_NORMAL;
        }
        if ($bu_filter_flag) {
            $user_info = Yii::$app->params['user_info'];
            $bu_condition = User::getBuCondition(static::class,
                $user_info['company_code'],
                $user_info['bu_code'],
                !Yii::$app->params['user_is_3004']
            );
            if (!empty($bu_condition))
                $condition = ['and', $condition, $bu_condition];
        }
        return parent::find()->where($condition)->one();
    }

    public static function findAll($condition)
    {
        if (static::DEL_FLAG) {
            $condition[static::DEL_FIELD] = static::DEL_STATUS_NORMAL;
        }
        return parent::findAll($condition);
    }

    public function load($data, $formName = null)
    {
        if (static::BU_FLAG && $this->isNewRecord && isset(Yii::$app->params['user_info'])) {
            $data['company_code'] = Yii::$app->params['user_info']['company_code'];
            $data['bu_code'] = Yii::$app->params['user_info']['bu_code'];
            $data['user_id'] = Yii::$app->params['user_info']['id'];
        }
        return parent::load($data, $formName);
    }

    public static function findJoin($alias, $join, $select, $where, $asArray = true, $all = true, $order = '', $index = '', $group = '', $with = [], $pages = [], $debug = false, $has_delete = false)
    {
        $model = parent::find();
        $prefix = '';
        if ($alias) {
            $model->alias($alias);
            $prefix = $alias . '.';
        }
        foreach ($join as $v) {
            $model->join($v['type'], $v['table'], $v['on']);
        }
        if (!isset($where[0]) || $where[0] != 'and') {
            if (static::DEL_FLAG && !$has_delete) {
                $where[$prefix . static::DEL_FIELD] = static::DEL_STATUS_NORMAL;
            }
            $model->select($select)->where($where);
        } else {
            if (static::DEL_FLAG && !$has_delete) {
                $where[] = [$prefix . static::DEL_FIELD => static::DEL_STATUS_NORMAL];
            }
            $model->select($select)->andWhere($where);
        }
        if ($group) {
            $model->groupBy($group);
        }
        if ($pages) {
            $pagination = new Pagination(['pageSize' => $pages['page_size'], 'page' => $pages['page']]);
            $count = $model->count();
            $model->offset($pagination->offset)->limit($pagination->limit);
        }
        if ($with) {
            foreach ($with as $v) {
                $model->with($v);
            }
        }
        if ($order) {
            $model->orderBy($order);
        }
        if ($asArray) {
            $model->asArray();
        }
        if ($index) {
            $model->indexBy($index);
        }
        if ($debug) {
            return $model->createCommand()->getRawSql();
        }
        if ($pages) {
            $list = $model->all();
            return [
                'list' => $list,
                'count' => (int)$count
            ];
        }
        if ($all) {
            return $model->all();
        } else {
            return $model->one();
        }
    }

    public static function find()
    {
        return new bQuery(get_called_class());
    }

    /**
     * @param $attribute
     * @param $class string baseModel
     * @param $id
     * @param string $primary_key
     */
    public function getOne($attribute, $class, $id, $primary_key = 'id')
    {
        /* @var $class baseModel */
        $this->{$attribute} = $class::findOne([$primary_key => $id]);
    }

    /**
     * 批量插入
     * @param $value
     * @param $key
     * @return array
     * @throws \yii\db\Exception
     */
    public static function batchSave($value, $key)
    {
        $model = \Yii::$app->db->createCommand()->batchInsert(static::tableName(), $key, $value)->execute();
        if ($model) {
            return [true, '成功'];
        } else {
            return [false, '批量插入失败'];
        }
    }

    /**
     * 获取表的所有字段名
     * @param bool $trim
     * @param string $table_name
     * @return array
     */
    public static function getModelKey($trim = false, $table_name = '')
    {
        if (!$table_name) $table_name = static::tableName();
        $tableSchema = Yii::$app->db->schema->getTableSchema($table_name);
        $data = \yii\helpers\ArrayHelper::getColumn($tableSchema->columns, 'name', false);
        if ($trim) {
            unset($data[0]);
            array_pop($data);
            $data = array_values($data);
        }
        return $data;
    }

    /**
     * 通用批量插入
     * @param $value
     * @return array
     * @throws \yii\db\Exception
     */
    public static function trimBatchSave($value)
    {
        return self::batchSave($value, self::getModelKey(true));
    }

    /**
     * 自动拼凑不存在即插入存在即更新的语句
     * @param $class_name
     * @param $value
     * @param bool $is_values
     * @return string
     */
    public static function makeDataWithDuplicate($class_name, $value, $is_values = false)
    {
        $sql1 = $sql2 = $sql3 = "";
        if ($is_values) {
            foreach ($value as $flag => $tmp) {
                $part = "";
                foreach ($tmp as $k => $v) {
                    if ($flag == 0) {
                        $sql1 .= $k . ",";
                        $sql3 .= $k . "=VALUES(" . $k . "),";
                    }
                    $part .= "'" . $v . "',";
                }
                $sql2 .= '(' . substr($part, 0, -1). '),';
            }
            $sql1 = substr($sql1, 0, -1);
            $sql2 = substr($sql2, 0, -1);
            $sql3 = substr($sql3, 0, -1);
            return "INSERT INTO " . $class_name . "(" . $sql1 . ")" . " VALUES" . $sql2 . " ON DUPLICATE KEY UPDATE " . $sql3;
        } else {
            foreach ($value as $k => $v) {
                $sql1 .= $k . ",";
                $sql2 .= ":" . $k . ",";
                $sql3 .= $k . "=:" . $k . ",";
            }
            $sql1 = substr($sql1, 0, -1);
            $sql2 = substr($sql2, 0, -1);
            $sql3 = substr($sql3, 0, -1);
            return "INSERT INTO " . $class_name . "(" . $sql1 . ")" . " VALUE(" . $sql2 . ") ON DUPLICATE KEY UPDATE " . $sql3;
        }
    }

    /**
     * 通用完成不存在即插入存在即更新的操作
     * @param $class_name
     * @param $value
     * @param bool $is_values
     * @return int
     * @throws \yii\db\Exception
     */
    public static function insertOrUpdate($class_name, $value, $is_values = false)
    {
        $value = !$is_values ? $value : [];
        return self::commandExec(self::makeDataWithDuplicate($class_name, $value, $is_values), $value);
    }

    /**
     * 原生执行sql
     * @param $sql
     * @param $value
     * @return int
     * @throws \yii\db\Exception
     */
    public static function commandExec($sql, $value)
    {
        return Yii::$app->db->createCommand($sql, $value)->execute();
    }
}
