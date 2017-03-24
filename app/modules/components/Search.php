<?php
return array(
    "id" => "mod-search",
    "module" => "search",
    "setting" => false,
    "data" => array(
        "location" => '1',
        "msg" => '1',
        "text" => "商品/店铺搜索",
        "isFixed" => array(
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
        "isFixedSel" => '0',
        "isSearchFilter" => true,
        "isLocation" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "显示"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "隐藏"
            )
        ),
        "isLocationSel" => '0',
        "headerStyle" => array(
            "bgStyle" => "#ec5151",
        ),
        "isSearchLeft" => true,
        "isMessage" => array(
            array(
                "key" => '0',
                "type" => "radio",
                "text" => "显示"
            ),
            array(
                "key" => '1',
                "type" => "radio",
                "text" => "隐藏"
            )),
        "isMessageSel" => '0',
        "isSearchRight" => true
    )
);