<?php
/**
 * Created by PhpStorm.
 * User: liushizhan
 * Date: 2019/6/7
 * Time: 下午10:45
 */

namespace console\controllers;

/**
 * 同步售点主数据
 * 需要先把其他全量数据同步完，再同步售点数据
 */

use api\models\share\MarketSegment;
use api\models\share\OrganizationRelation;
use api\models\share\Store;
use api\models\share\StoreBelong;
use common\libs\file_log\LOG;
use common\libs\sftp\SFTP;
use Exception;
use Yii;
use yii\helpers\ArrayHelper;

class StoreController extends BaseController
{
    const BATCH_INSERT_AMOUNT = 1000;

    const STORE_PATH = 'ftp';
    const ORGANIZATION_PATH = 'ftp';
    const AREA_PATH = 'ftp';

    const STORE_MODEL = [
        'store_id',
        'name',
        'address',
//        'storekeeper',
//        'phone',
        'sub_channel_code',
        'company_code',
        'bu_code',
        'region_code',
        'location_code',
        'location_name',
//        'supervisor_name',
        'route_code',
        'route_name',
        'market_channel',
        'carboy_indicator',
        'fsv_indicator',
        'ka_indicator',
        'market_segment_code',
        'sap_create_date',
        'town_code',
        'status_code',
        'status_desc',
        'biz_type_extens',
        'biz_type_extens_desc',
        'created_at',
        'updated_at'
    ];

    const ORGANIZATION_MODEL = [
        'company_code',
        'company_name',
        'bu_code',
        'bu_name',
        'region_code',
        'region_name',
        'location_code',
        'location_name',
        'supervisor_code',
        'supervisor_name',
        'route_code',
        'created_at',
        'updated_at'
    ];

    const MARKET_SEGMENT_MODEL = [
        'company_code',
        'city_code',
        'city_name',
        'town_code',
        'town_name',
        'market_segment',
        'market_segment_desc',
        'created_at',
        'updated_at'
    ];

    public function actionTest()
    {
        LOG::log('ttt');
    }

    /**
     * 售点数据同步
     * 必须先同步组织架构
     * 增量同步
     */
    public function actionSyncStore()
    {
        try {
            $config = Yii::$app->params['store_sftp']['customer'];
            $sftp = new SFTP($config);
            $ls = $sftp->get_file_list(self::STORE_PATH);
            $reg = '/^\d+_CustomerMaster_' . date('Y') . '\d+/';
//            $reg = '/^3042_CustomerMaster_20200313100000/';
            $match = preg_grep($reg, $ls);
            $runtimePath = Yii::getAlias('@runtime');
            set_time_limit(0);
            foreach ($match as $file_name) {
                LOG::log('主数据售点数据同步：' . $file_name);
                $remote_path = self::STORE_PATH . DIRECTORY_SEPARATOR . $file_name;
                $local_path = $runtimePath . DIRECTORY_SEPARATOR . $file_name;
                $success = $sftp->download($remote_path, $local_path);
//                $success = true;
                // 下载成功
                LOG::log('下载成功：' . $file_name);
                if ($success) {
                    $file = @fopen($local_path, 'r');
                    $bu = OrganizationRelation::findAllArray([], ['id', 'company_code', 'bu_code', 'location_code'], 'location_code');
                    // 不记得为什么要判断营业所了
                    $locations = StoreBelong::findAllArray(['type' => StoreBelong::TYPE_LOCATION], '*', 'code');
                    // 使用批量插入，每1000条插入一次数据库
                    $row_number = 0;
                    $page = [];
                    while (!feof($file)) {
                        $row = fgets($file);
                        if ($row == '') {
                            continue;
                        }
                        $row_number++;
                        $tmp = explode('^', $row);
                        if (count($tmp) < 25) {
                            $this->ding->sendTxt('数据格式有问题：' . $row);
                            continue;
                        }
                        $page[] = $tmp;
                        if (count($page) < self::BATCH_INSERT_AMOUNT) {
                            continue;
                        }
                        $this->syncStorePage($page, $bu, $locations);

                        // 断开连接，好像有影响
                        Yii::$app->db->close();
                        Yii::$app->db2->close();
                        $page = [];
                    }
                    if (!empty($page)) {
                        $this->syncStorePage($page, $bu, $locations);
                    }
                    fclose($file);
                    // 删除sftp文件
                    $sftp->del_file($remote_path);
                    LOG::log('共入库售点数据：' . $row_number . ' 条，文件名：' . $file_name);
                    LOG::log('删除文件：' . $file_name);
                }
            }

        } catch (Exception $e) {
            Yii::error($e);
            $this->ding->sendTxt($e->getMessage());
            Yii::getLogger()->flush(true);
        } finally {
            Yii::$app->db->close();
            Yii::$app->db2->close();
        }
    }

    /**
     * 组织架构同步
     * store_belong 添加数据
     *
     */
    public function actionSyncOrganization()
    {
        try {
            $config = Yii::$app->params['store_sftp']['customer'];
            $sftp = new SFTP($config);
            $ls = $sftp->get_file_list(self::STORE_PATH);
            $reg = '/^OrgStructureInfo_' . date('Y') . '\d+/';
//            $reg = '/^OrgStructureInfo_20200312100000/';
            $match = preg_grep($reg, $ls);
            $runtimePath = Yii::getAlias('@runtime');
            set_time_limit(0);
            // $match 和售点不同，组织架构应该只有1个文件
            foreach ($match as $file_name) {
                $remote_path = self::STORE_PATH . DIRECTORY_SEPARATOR . $file_name;
                $local_path = $runtimePath . DIRECTORY_SEPARATOR . $file_name;
                LOG::log('主数据组织架构：' . $file_name);
                $success = $sftp->download($remote_path, $local_path);
                LOG::log('下载成功：' . $file_name);
//                $success = true;
                // 下载成功
                if ($success) {
                    // 使用批量插入，每1000条插入一次数据库
                    $insert_data = [];
                    $time = time();
                    // 组织架构数据基本在1万条数据以内，数据全部取出处理
                    $data = $this->fileToArray($local_path, ['0']);
                    if (empty($data)) {
                        continue;
                    }
                    // 有可能有厂房新增和删除，这里不能按厂房维度进行提交
                    $tran = Yii::$app->db2->beginTransaction();
                    OrganizationRelation::deleteAll();
                    foreach ($data as $company_code => $datum) {
                        // 不需要找出旧数据，直接删除新增
//                        $relation = OrganizationRelation::findAllArray(['company_code' => $company_code]);
                        $relation = [];

                        $oldNumber = count($relation);
                        foreach ($datum as $key => $item) {
                            $one = [
                                'company_code' => $item[0],     // 'company_code',
                                'company_name' => $item[1],     // 'company_name',
                                'bu_code' => $item[2],     // 'bu_code',
                                'bu_name' => $item[3],     // 'bu_name',
                                'region_code' => $item[4],     // 'region_code',
                                'region_name' => $item[5],     // 'region_name',
                                'location_code' => $item[6],     // 'location_code',
                                'location_name' => $item[7],     // 'location_name',
                                'supervisor_code' => $item[8],     // 'supervisor_code',
                                'supervisor_name' => $item[9],     // 'supervisor_name',
                                'route_code' => $item[10],          // 'route_code'
                                'created_at' => $time,          // 'created_at',
                                'updated_at' => $time,          // 'updated_at'
                            ];
                            if ($key >= $oldNumber) {
                                // 直接新增
                                $insert_data[] = array_values($one);
                            } else {
                                // 更新
                                $oldRelation = $relation[$key];
                                if (
                                    $oldRelation['company_name'] != $one['company_name']
                                    || $oldRelation['bu_code'] != $one['bu_code']
                                    || $oldRelation['bu_name'] != $one['bu_name']
                                    || $oldRelation['region_code'] != $one['region_code']
                                    || $oldRelation['region_name'] != $one['region_name']
                                    || $oldRelation['location_code'] != $one['location_code']
                                    || $oldRelation['location_name'] != $one['location_name']
                                    || $oldRelation['supervisor_code'] != $one['supervisor_code']
                                    || $oldRelation['supervisor_name'] != $one['supervisor_name']
                                    || $oldRelation['route_code'] != $one['route_code']
                                ) {
                                    unset($one['created_at']);
                                    OrganizationRelation::updateAll($one, ['id' => $oldRelation['id']]);
                                }
                            }
                            if (count($insert_data) == self::BATCH_INSERT_AMOUNT) {
                                $this->batchInsert(OrganizationRelation::tableName(), self::ORGANIZATION_MODEL, $insert_data);
                                $insert_data = [];
                            }
                        }
                    }
                    if (!empty($insert_data)) {
                        $this->batchInsert(OrganizationRelation::tableName(), self::ORGANIZATION_MODEL, $insert_data);
                    }

                    // 基础表需要同步
                    $this->updateStoreBelong();
                    $tran->commit();
                    //  删除sftp文件
                    $sftp->del_file($remote_path);
                    LOG::log('删除组织架构文件：' . $file_name);
                }
            }

        } catch (Exception $e) {
            if (isset($tran)) {
                $tran->rollBack();
            }
            Yii::error($e);
            $this->ding->sendTxt($e->getMessage());
        } finally {
            Yii::$app->db->close();
            Yii::$app->db2->close();
        }
    }

    /**
     * 售点数据同步，是在共享库，所以要用 db2
     * @param $table_name
     * @param $field
     * @param $data
     */
    private function batchInsert($table_name, $field, $data)
    {
        $res = Yii::$app->db2->createCommand()->batchInsert($table_name, $field, $data)->execute();
        if (!$res) {
            $this->ding->sendTxt('售点主数据批量插入失败，第一条数据：' . json_encode($field) . '，' . json_encode($data[0][0]));
        }
    }

    /**
     * 文件转换为数组
     * @param $path
     * @param $group
     * @return array
     */
    private function fileToArray($path, $group)
    {
        $content = file_get_contents($path);
        $content = explode("\n", $content);
        $result = [];
        foreach ($content as $item) {
            if ($item == '') {
                continue;
            }
            $result[] = explode('^', $item);
        }

        $result = ArrayHelper::index($result, null, $group);
        return $result;
    }

    /**
     * 将组织架构表的各种code对应的name更新到store_belong表
     */
    private function updateStoreBelong()
    {
        // 按company_code 等名维度进行处理
        $arr = [
            [
                'code_field' => 'company_code',
                'name_field' => 'company_name',
                'type' => StoreBelong::TYPE_COMPANY,
                'group_by' => ['company_code']
            ],
            [
                'code_field' => 'region_code',
                'name_field' => 'region_name',
                'type' => StoreBelong::TYPE_REGION,
                'group_by' => ['region_code']
            ],
            [
                'code_field' => 'location_code',
                'name_field' => 'location_name',
                'type' => StoreBelong::TYPE_LOCATION,
                'group_by' => ['location_code']
            ]
        ];
        foreach ($arr as $item) {
            $this->updateStoreBelongData($item['type'], $item['code_field'], $item['name_field'], $item['group_by']);
        }

    }

    private function updateStoreBelongData($type, $code_field, $name_field, $group_by)
    {

        $res = OrganizationRelation::find()->select([$code_field, $name_field])->groupBy($group_by)->asArray()->all();
        foreach ($res as $item) {
            if ($item[$code_field] == '') {
                continue;
            }
            $old = StoreBelong::findOne(['type' => $type, 'code' => $item[$code_field]]);
            if ($old == null) {
                $old = new StoreBelong();
                $old->code = $item[$code_field];
            }
            $old->name = $item[$name_field];
            $old->type = $type;
            $old->value = $item[$code_field];
            $old->save();
        }
    }

    /**
     * 同步市场区隔
     */
    public function actionSyncMarket()
    {
        try {
            $config = Yii::$app->params['store_sftp']['customer'];
            $sftp = new SFTP($config);
            $ls = $sftp->get_file_list(self::STORE_PATH);
            $reg = '/^MarketSegment_' . date('Y') . '\d+/';
//            $reg = '/^MarketSegment_20200313100000/';
            $match = preg_grep($reg, $ls);
            $runtimePath = Yii::getAlias('@runtime');
            set_time_limit(0);
            // $match 和售点不同，组织架构应该只有1个文件
            foreach ($match as $file_name) {
                $remote_path = self::STORE_PATH . DIRECTORY_SEPARATOR . $file_name;
                $local_path = $runtimePath . DIRECTORY_SEPARATOR . $file_name;
                LOG::log('主数据区隔同步：' . $file_name);
                $success = $sftp->download($remote_path, $local_path);
                LOG::log('下载成功：' . $file_name);
//                $success = true;
                // 下载成功
                if ($success) {
                    // 使用批量插入，每1000条插入一次数据库
                    $insert_data = [];
                    $time = time();
                    // 组织架构数据基本在1万条数据以内，数据全部取出处理
                    $data = $this->fileToArray($local_path, ['0']);
                    if (empty($data)) {
                        continue;
                    }
                    // 先找出数据库中的数据
                    $tran = Yii::$app->db2->beginTransaction();
                    MarketSegment::deleteAll();
                    foreach ($data as $company_code => $datum) {
//                        $relation = MarketSegment::findAllArray(['company_code' => $company_code]);
                        $relation = [];

                        $oldNumber = count($relation);
                        foreach ($datum as $key => $item) {
                            $one = [
                                'company_code' => $item[0],
                                'city_code' => $item[1],
                                'city_name' => $item[2],
                                'town_code' => $item[3],
                                'town_name' => $item[4],
                                'market_segment' => $item[5],
                                'market_segment_desc' => $item[6],
                                'created_at' => $time,
                                'updated_at' => $time
                            ];
                            if ($key >= $oldNumber) {
                                // 直接新增
                                $insert_data[] = array_values($one);
                            } else {
                                // 更新
                                $oldRelation = $relation[$key];
                                if (
                                    $oldRelation['company_code'] != $one['company_code']
                                    || $oldRelation['city_code'] != $one['city_code']
                                    || $oldRelation['city_name'] != $one['city_name']
                                    || $oldRelation['town_code'] != $one['town_code']
                                    || $oldRelation['town_name'] != $one['town_name']
                                    || $oldRelation['market_segment'] != $one['market_segment']
                                    || $oldRelation['market_segment_desc'] != $one['market_segment_desc']
                                ) {
                                    unset($one['created_at']);
                                    MarketSegment::updateAll($one, ['id' => $oldRelation['id']]);
                                }
                            }
                            if (count($insert_data) == self::BATCH_INSERT_AMOUNT) {
                                $this->batchInsert(MarketSegment::tableName(), self::MARKET_SEGMENT_MODEL, $insert_data);
                                $insert_data = [];
                            }
                        }
                    }
                    if (!empty($insert_data)) {
                        $this->batchInsert(MarketSegment::tableName(), self::MARKET_SEGMENT_MODEL, $insert_data);
                    }
                    $tran->commit();

                    //  删除sftp文件
                    $sftp->del_file($remote_path);
                    LOG::log('区隔文件删除：' . $file_name);
                }
            }
        } catch (Exception $e) {
            if (isset($tran)) {
                $tran->rollBack();
            }
            Yii::error($e);
            $this->ding->sendTxt($e->getMessage());
        } finally {
            Yii::$app->db->close();
            Yii::$app->db2->close();
        }
    }

    /**
     * 按页更新数据
     * @param $page
     * @param $bu
     * @param $locations
     */
    private function syncStorePage($page, $bu, $locations)
    {
        $time = time();
        // 判断是新增还是修改
        // 拿到1000条数据后，再来走校验逻辑，一次入库数据可以小于 1000
        $store_ids = array_column($page, 0);
        $location_ids = array_column($page, 9);
        $location_ids = array_unique($location_ids);
        $stores = Store::findAllArray(['store_id' => $store_ids], ['*'], 'store_id');
//                        $store = Store::findOneArray(['store_id' => $tmp[0]]);
//                        $location = StoreBelong::findOneArray(['type' => StoreBelong::TYPE_LOCATION, 'code' => $tmp[9]]);
        $insert_data = [];
        foreach ($page as $tmp) {
            $store = isset($stores[$tmp[0]]) ? $stores[$tmp[0]] : null;
            if (!isset($locations[$tmp[9]])) {
//                $this->ding->sendTxt('营业所代码不存在：'. $tmp[9]. '，售点文件：'. $remote_path);
                $location_name = '';
            } else {
                $location_name = $locations[$tmp[9]]['name'];
            }
            // 'bu_code', location_code能找到唯一bu_code
            $bu_code = isset($bu[$tmp[9]])?$bu[$tmp[9]]['bu_code']:'';
            $one = [
                'store_id' => $tmp[0],         // 'store_id',
                'name' => $tmp[1],         // 'name',
                'address' => $tmp[2],         // 'address',
                'sub_channel_code' => $tmp[3],         // 'sub_channel_code',
                'company_code' => $tmp[5],         // 'company_code',
                'bu_code' => $bu_code,
                'region_code' => $tmp[7],         // 'region_code',
                'location_code' => $tmp[9],         // 'location_code',
                'location_name' => $location_name,         // 'location_name',
                'route_code' => $tmp[11],        // 'route_code',
                'route_name' => $tmp[11],        // 'route_code',
                'market_channel' => $tmp[12],
                'carboy_indicator' => $tmp[14],
                'fsv_indicator' => $tmp[15],
                'ka_indicator' => $tmp[16],
                'market_segment_code' => $tmp[17],
                'sap_create_date' => $tmp[19],
                'town_code' => $tmp[20],
                'status_code' => $tmp[21],
                'status_desc' => $tmp[22],
                'biz_type_extens' => $tmp[23],
                'biz_type_extens_desc' => $tmp[24],
                'created_at' => $time,           // 'created_at',
                'updated_at' => $time,           // 'updated_at'
            ];
            if ($store == null) {
                $insert_data[] = array_values($one);
            } else {
                // 数据对比有修改才更新
                if (
                    $store['name'] != $one['name']
                    || $store['address'] != $one['address']
                    || $store['sub_channel_code'] != $one['sub_channel_code']
                    || $store['company_code'] != $one['company_code']
                    || $store['bu_code'] != $one['bu_code']
                    || $store['region_code'] != $one['region_code']
                    || $store['location_code'] != $one['location_code']
                    || $store['location_name'] != $one['location_name']
                    || $store['route_code'] != $one['route_code']
                    || $store['market_channel'] != $one['market_channel']
                    || $store['carboy_indicator'] != $one['carboy_indicator']
                    || $store['fsv_indicator'] != $one['fsv_indicator']
                    || $store['ka_indicator'] != $one['ka_indicator']
                    || $store['market_segment_code'] != $one['market_segment_code']
                    || $store['sap_create_date'] != $one['sap_create_date']
                    || $store['town_code'] != $one['town_code']
                    || $store['status_code'] != $one['status_code']
                    || $store['status_desc'] != $one['status_desc']
                    || $store['biz_type_extens'] != $one['biz_type_extens']
                    || $store['biz_type_extens_desc'] != $one['biz_type_extens_desc']
                ) {
                    unset($one['created_at']);
                    Store::updateAll($one, ['store_id' => $store['store_id']]);
                }
            }

        }
        if (!empty($insert_data)) {
            $this->batchInsert(Store::tableName(), self::STORE_MODEL, $insert_data);
        }
    }
}