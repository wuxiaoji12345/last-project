<?php declare(strict_types=1);


namespace unit\models;


use api\service\qc\ReviewService;
use Codeception\Test\Unit;

class QcTest extends Unit
{
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * 测试人工复核任务列表
     *
     * User: hanhyu
     * Date: 2020/10/26
     * Time: 下午6:45
     */
    public function testGetManualReviewList()
    {
        $searchForm = [
            'created_time' => '2020-10-20~2020-10-26',
            'check_time'   => '2020-10-20~2020-10-26',
            'page'         => 1,
            'page_size'    => 10,
            'title'        => 10,
            'tool_id'      => 10,
        ];

        $res = ReviewService::getManualReviewList($searchForm);

        error_log(json_encode($res, 320));
    }

    /**
     * 测试人工复核结果列表
     *
     * User: hanhyu
     * Date: 2020/10/28
     * Time: 下午3:26
     */
    public function testGetManualCheckResultList()
    {
        $searchForm = [
            //"start_time"      => "1603869066",
            //"end_time"        => "1603869766",
            "tool_id"         => "2",
            "standard_id"     => "199",
            "survey_code"     => "wudajiceshi4",
            "channel_id"      => "8",
            "store_id"        => "0514566646",
            "region_code"     => "Y01001",
            "location_name"   => "杭州",
            "supervisor_name" => "",
            "route_name"      => "0013",
            "qc_status"       => "",
            "page"            => "0",
            "page_size"       => "10",
        ];

        $res = ReviewService::getManualCheckResultList($searchForm);

        error_log(json_encode($res, 320));

        self::assertEquals(1, $res['count'], 'check result total not equals 1');
        self::assertNotEmpty($res['list'], 'check result is empty');
        self::assertEquals($searchForm['location_name'], $res['list'][0]['location_name'], 'check result location name not equals 1');
    }

}
