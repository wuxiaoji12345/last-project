<?php

namespace api\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[StatisticalItem]].
 *
 * @see StatisticalItem
 */
class StatisticalItemQuery extends ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticalItem[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticalItem|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
