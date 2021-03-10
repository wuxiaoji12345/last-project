<?php

namespace api\models;

use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * This is the ActiveQuery class for [[Replan]].
 *
 * @property $modelClass baseModel
 * @see Replan
 */
class bQuery extends ActiveQuery
{
    /**
     * {@inheritdoc}
     * @return Replan[]|array
     */
    public function all($db = null, $delete_flag = true)
    {
        if ($this->modelClass::DEL_FLAG && $delete_flag) {
            // 判断是否使用别名
            $this->andWhere(['=', new Expression($this->getTableNameAndAlias()[1] . '.' . $this->modelClass::DEL_FIELD), $this->modelClass::DEL_STATUS_NORMAL]);
        }
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return Replan|array|null
     */
    public function one($db = null, $delete_flag = true)
    {
        if ($this->modelClass::DEL_FLAG && $delete_flag) {
            // 判断是否使用别名
            $this->andWhere(['=', new Expression($this->getTableNameAndAlias()[1] . '.' . $this->modelClass::DEL_FIELD), $this->modelClass::DEL_STATUS_NORMAL]);
        }
        return parent::one($db);
    }

    public function count($q = '*', $db = null, $delete_flag = true)
    {
        if ($this->modelClass::DEL_FLAG && $delete_flag) {
            // 判断是否使用别名
            $this->andWhere(['=', new Expression($this->getTableNameAndAlias()[1] . '.' . $this->modelClass::DEL_FIELD), $this->modelClass::DEL_STATUS_NORMAL]);
        }
        return parent::count($q, $db);
    }

    /**
     * 分页
     * ['page'=> 2, 'page_size'=> 20]
     * @param $pager
     * @return ActiveQuery
     */
    public function page($pager)
    {
        return $this->offset(($pager['page'] - 1) * $pager['page_size'])->limit($pager['page_size']);
    }
}
