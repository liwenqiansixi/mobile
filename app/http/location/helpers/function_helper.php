<?php

use ectouch\Pinyin;


/**
 * 获取首字母
 * @param unknown $str
 * @return string
 */
function getLetter($str)
{
    $i = 0;
    while ($i < strlen($str)) {
        $tmp = bin2hex(substr($str, $i, 1));
        if ($tmp >= 'B0') { //汉字
            $object = new Pinyin();
            $pyobj = $object->output($str);
            $pinyin = isset($pyobj[0]) ? $pyobj[0] : '';
            return strtoupper(substr($pinyin, 0, 1));
            $i += 2;
        } else {
            return strtoupper(substr($str, $i, 1));
            $i++;
        }
    }
}

