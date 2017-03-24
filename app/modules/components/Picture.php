<?php
return array(
    "id" => "mod-picture",
    "module" => "picture",
    "setting" => false,
    "data" => array(
        "showStyle" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "轮播显示",
                "picSizeKey" => array('0')
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "分开显示",
                "picSizeKey" => array('0', '1')
            )
        ),
        "picSize" => array(
            array(
                "key" => "0",
                "type" => "radio",
                "text" => "大图"
            ),
            array(
                "key" => "1",
                "type" => "radio",
                "text" => "小图",
            )
        ),
        "imgList" => array(),
        "isStyleSel" => '0',
        "isSizeSel" => '0',
        "isSmall" => false
    )
);