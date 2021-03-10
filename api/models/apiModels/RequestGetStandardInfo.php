<?php


namespace api\models\apiModels;

use api\models\share\OrganizationRelation;
use api\models\share\StoreBelong;
use api\models\Store;

/**
 * Class RequestGetStandardInfo
 * @property string $company_code
 * @property string $bu_code
 * @property string $route_code
 * @property string $date
 * @package api\models\apiModels
 */
class RequestGetStandardInfo extends apiBaseModel
{
    public $company_code;
    public $bu_code;
    public $route_code;
    public $date;

    public function rules()
    {
        return [
            [['company_code', 'route_code'], 'required'],
            [['bu_code', 'date'], 'string'],
            [['route_code'], 'validateRouteExist']
        ];
    }

    public function attributeLabels()
    {
        return [
            'company_code' => '厂房',
            'bu_code' => 'BU',
            'route_code' => '线路',
            'date' => '日期',
        ];
    }

    /**
     * 验证线路是否存在
     * @return false
     */
    public function validateRouteExist()
    {
        $route_code = StoreBelong::findOneArray(['code' => $this->route_code, 'type' => StoreBelong::TYPE_ROUTE]);
        // 先注释，这里storeBelong表生产没有数据
//        if ($route_code == null) {
//            $this->addError('route_code', '线路code不存在');
//            return false;
//        }
        return true;
    }

    public function load($data, $formName = null)
    {
        if (empty($data['date'])) {
            $data['date'] = date('Y-m-d');
        }
        if (empty($data['bu_code'])) {
            $data['bu_code'] = '0001';
        }
//        $data['date'] = $data['date'] . ' 00:00:00';
        return parent::load($data, $formName);
    }
}