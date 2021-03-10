<?php

namespace api\service\rule;

use api\models\RuleOutputInfo;

class RuleService
{
    public static function joinSceneAndOutput(&$param, $all_type, $all_scene)
    {
        foreach ($param as &$v) {
            $name = '';
            if ($v['is_all_scene'] == RuleOutputInfo::IS_ALL_SCENE_YES) {
                $name = '全场景';
            }
            $scene_type = is_array($v['scene_type']) ? $v['scene_type'] : json_decode($v['scene_type'], true);
            $scene_code = is_array($v['scene_code']) ? $v['scene_code'] : json_decode($v['scene_code'], true);
            if (!empty($scene_type)) {
                foreach ($scene_type as $item) {
                    if (!$name) {
                        $name .= $all_type[$item]['name'];
                    } else {
                        $name .= '、' . $all_type[$item]['name'];
                    }
                }
            }
            if (!empty($scene_code)) {
                $code_name = '';
                foreach ($scene_code as $item) {
                    if (!$code_name) {
                        $code_name .= $all_scene[$item]['scene_code_name'];
                    } else {
                        $code_name .= '、' . $all_scene[$item]['scene_code_name'];
                    }
                }
                if ($name != '') {
                    $name = $name . ';' . $code_name;
                } else {
                    $name = $code_name;
                }
            }
            $v['node_name'] = $name . ':' . $v['node_name'];
        }
        return $param;
    }
}