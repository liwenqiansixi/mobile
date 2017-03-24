<?php
return array(
    "id" => "mod-nav",
    "module" => "nav",
    "setting" => false,
    "data" => array(
        "showStyle" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "两列"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "三列"
            ),
            array(
                "key" => '2',
                "type" => "radio",
                "text" => "四列"
            ),
            array(
                "key" => '3',
                "type" => "radio",
                "text" => "五列"
            )
        ),
        "imgList" => array(),
        "showText" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "是"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "否"
            )
        ),
        "showGap" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "外边距"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "内边距"
            )
        ),
        "showBorder" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "是"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "否"
            )
        ),
        "widthSize" => "20%",
        "isStyleSel" => '3',
        "isTextSel" => '0',
        "isBorderSel" => '1',
        "isGapSel" => array('0', '1'),
        "showGapClass" => array(
            'nav-gap-in' => true,
            'nav-gap-out' => true,
            'nav-border' => false
        ),
        "isShowText" => true,
        "isShowBorder" => false
    )
);