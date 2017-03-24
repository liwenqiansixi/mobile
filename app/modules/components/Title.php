<?php
return array(
    "id" => "mod-title",
    "module" => "title",
    "setting" => false,
    "data" => array(
        "title" => "标题",
        "traditionStyle" => array(
            "key" => "0",
            "isShow" => "block",
            "text" => "传统样式",
            "bgStyle" => "#ffffff",
            "fitTitle" => "",
            "linkTitle" => "",
            "link" => "",
            "isAddBtnShow" => "block",
            "isAddLinkShow" => "none",
            "isTextSel" => '1',
            "fitAText" => array("文本", "日期")
        ),
        "wxStyle" => array(
            "key" => "1",
            "isShow" => "none",
            "text" => "微信图文样式",
            "dataTime" => "",
            "author" => "",
            "linkTitle" => "",
            "link" => "",
            "isWxfitShow" => "none",
            "isDataTime" => "none",
            "isAuthor" => "none",
            "isLinkTitle" => "none"
        ),
        "showStyle" => array(
            array(
                "type" => "radio",
                "text" => "居左显示",
                "value" => "text-left"
            ),
            array(
                "type" => "radio",
                "text" => "居中显示",
                "value" => "text-center"
            ),
            array(
                "type" => "radio",
                "text" => "居右显示",
                "value" => "text-right"
            )
        ),
        "isStyleSel" => "0",
        "isShowStyle" => "text-left",
    )
);