<?php
$json = '{ "a": [ { "b": "c" }, "d" ], "x": 1}';
function get($json,$str){
    $json = json_decode($json,true);
    $arr1 =  explode('[',$str);
    foreach ($json as $k => $v){
        if($str == $k){
            return $v;
        }
        if($arr1[0] == $k){
            $tmp = $v;
        }
    }
    unset($k);
    unset($v);

    $arr2 =  explode(']',$arr1[1]);
    if($arr2[1] == ''){
        return $tmp[$arr2[0]];
    } else {
        $key = substr($arr2[1],1);
        return $tmp[$arr2[0]][$key];
    }
}
print_r(get($json, "a[0].b"));
print_r(get($json, "a[1]"));
print_r(get($json, "x"));
