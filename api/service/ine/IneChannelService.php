<?php


namespace api\service\ine;


use api\models\IneChannel;
use api\models\IneConfig;
use api\models\RuleOutputInfo;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

class IneChannelService
{
    /**
     * 获取所有ine渠道配置，按年分组
     *
     * @return array
     */
    public static function getAllGroupByYear()
    {
        $channel_list = $tmp_ine_config_list = [];
        //获取所有渠道
        $ine_channel_list = IneChannel::find()->asArray()->all();
        //获取所有年份，降序排列
        $year_list = array_unique(ArrayHelper::getColumn($ine_channel_list, 'year', []));
        rsort($year_list);
        //读取配置表，按渠道分组，获取指标项总数和未映射指标项个数
        $ine_config_list = IneConfig::find()
            ->select(['sum(if(rule_output_id=0,1,0)) as no_map_count', 'count(*) as total', 'ine_channel_id'])
            ->where(new Expression('level = 1 or (level = 2 and max_score != 0) or (level = 3 and max_score != 0) or level = 4'))
            ->groupBy('ine_channel_id')->asArray()->all();
        //获取删除的检查项目规则输出项，按渠道分组
        $deleted_rule_list = IneConfig::find()
            ->alias('c')
            ->leftJoin(RuleOutputInfo::tableName(). ' r', 'c.rule_output_id = r.id')
            ->select(['count(*) as total', 'ine_channel_id'])
            ->where(['r.status' => RuleOutputInfo::DEL_STATUS_DELETE])
            ->groupBy('ine_channel_id')->asArray()->all();
        //计算实际未配置的细分指标项数
        foreach ($ine_config_list as $k => $v) {
            foreach ($deleted_rule_list as $deleted_rule) {
                if ($v['ine_channel_id'] == $deleted_rule['ine_channel_id']) {
                    $v['no_map_count'] = $v['no_map_count'] + $deleted_rule['total'];
                }
            }
            $tmp_ine_config_list[$v['ine_channel_id']] = $v;
        }
        //按年份对渠道进行分组
        foreach ($year_list as $k => $year) {
            $channel_list[$k]['year'] = $year;
            $channel_list[$k]['list'] = [];
            foreach ($ine_channel_list as $ine_channel) {
                if ($ine_channel['year'] == $year) {
                    array_push($channel_list[$k]['list'], [
                        'id' => $ine_channel['id'],
                        'channel_name' => $ine_channel['channel_name'],
                        'total' => isset($tmp_ine_config_list[$ine_channel['id']]) ? $tmp_ine_config_list[$ine_channel['id']]['total'] : 0,
                        'no_map_count' => isset($tmp_ine_config_list[$ine_channel['id']]) ? $tmp_ine_config_list[$ine_channel['id']]['no_map_count'] : 0,
                        'last_publish_time' => $ine_channel['last_publish_time'] ? date('Y-m-d H:i:s', $ine_channel['last_publish_time']) :'',
                    ]);
                }
            }
        }
        return $channel_list;
    }

    /**
     * 根据ID获取单个ine渠道信息
     *
     * @param $ine_channel_id
     * @return array|\yii\db\ActiveRecord|null
     */
    public static function getOneById($ine_channel_id)
    {
        return IneChannel::findOneArray(['id' => $ine_channel_id]);
    }
}