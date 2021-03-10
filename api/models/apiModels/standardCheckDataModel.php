<?php

namespace api\models\apiModels;


/**
 * @property string contract_id
 * @property string company_code
 * Class standardCheckDataModel
 * @package api\models\apiModels
 */
class standardCheckDataModel extends apiBaseModel
{
    public $contract_id;
    public $company_code;

    public function rules()
    {
        return [
            [['company_code'], 'integer'],
            [['contract_id'], 'each', 'rule' => ['integer']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'contract_id' => 'ZFT协议ID',
            'company_code' => '厂房',
        ];
    }
}