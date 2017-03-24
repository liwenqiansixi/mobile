<?php
namespace app\http\index\controllers;

use app\http\site\controllers\Index;

class Api extends Index
{

    private $savePath = 'storage/app/diy';

    public function __construct()
    {
        config('show_page_trace', false);
        parent::__construct();
        $this->savePath = ROOT_PATH . $this->savePath;
        if (!is_dir($this->savePath)) {
            $this->fs->mkdir($this->savePath);
        }
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
    }

    /**
     * 首页信息
     */
    public function actionIndex()
    {
        $module = $this->getModule();
        if ($module === false) {
            $module = $this->init();
        }
        exit(json_encode($module));
    }

    /**
     * 管理数据
     */
    public function actionDashboard()
    {
        $data = input('post.data');
        if (!empty($data)) {
            $this->setModule('index', $data);
            $this->response(array('error' => 0, 'msg' => 'success'));
        }
        $this->response(array('error' => 1, 'msg' => 'fail'));
    }

    /**
     * 默认配置
     */
    private function init()
    {
        $aa = array(
            array(
                "id" => "mod_search124",
                "module" => "search",
                "setting" => false,
                "data" => array(
                    "location" => 1,
                    "msg" => 1,
                    "text" => "商品/店铺搜索",
                    "isFixed" => array(
                        array(
                            "key" => 0,
                            "type" => "radio",
                            "text" => "是"
                        ),
                        array(
                            "key" => 1,
                            "type" => "radio",
                            "text" => "否"
                        )
                    ),
                    "isFixedSel" => 0,
                    "isSearchFilter" => true,
                    "headerClass" => array(
                        "searchVisual" => true,
                        "searchFixed" => false
                    ),
                    "isLocation" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "显示"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "影藏"
                    )),
                    "isLocationSel" => 0,
                    "headerStyle" => array(
                        "bgStyle" => "#ec5151",
                    ),
                    "isSearchLeft" => true,
                    "isMessage" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "显示"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "影藏"
                    )),
                    "isMessageSel" => 0,
                    "isSearchRight" => true
                )
            ),


            array(
                "id" => "mod-6sfs23456",
                "module" => "picture",
                "setting" => true,
                "data" => array(
                    "pictureOption" => array(
                        "pagination" => ".swiper-pagination",
                        "lazyLoading" => false,
                        "autoplay" => 0,
                        "autoplayDisableOnInteraction" => false,
                        "lazyLoadingInPrevNextAmount" => 1
                    ),
                    "showStyle" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "轮播显示",
                        "picSizeKey" => array(0)
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "分开显示",
                        "picSizeKey" => array(0, 1)
                    )),
                    "picSize" => array(array(
                        "key" => "0",
                        "type" => "radio",
                        "text" => "大图"
                    ), array(
                        "key" => "1",
                        "type" => "radio",
                        "text" => "小图",
                    )),
                    "imgList" => array(array(
                        "img" => "http://shop.ectouch.cn/v2/data/attached/afficheimg/1475869586530689271.png",
                        "link" => "http://shop.ectouch.cn/v2/index.php?m=default&c=affiche&a=index&ad_id=1&uri=&u=0",
                        "desc" => "第一张图片描述"
                    ), array(
                        "img" => "http://shop.ectouch.cn/v2/data/attached/afficheimg/1475947014493634167.png",
                        "link" => "http://shop.ectouch.cn/v2/index.php?m=default&c=affiche&a=index&ad_id=2&uri=&u=0",
                        "desc" => "第二张图片描述"
                    ), array(
                        "img" => "http://shop.ectouch.cn/v2/data/attached/afficheimg/1475869618740468734.png",
                        "url" => "http://shop.ectouch.cn/v2/index.php?m=default&c=affiche&a=index&ad_id=25&uri=&u=0",
                        "desc" => "第三张图片描述"
                    )),
                    "isStyleSel" => 0,
                    "isSizeSel" => 0,
                    "isSmall" => false
                )
            ),


            array(
                "id" => "mod-323456",
                "module" => "nav",
                "setting" => false,
                "data" => array(
                    "showStyle" => array(
                        array(
                            "key" => 0,
                            "type" => "radio",
                            "text" => "两列"
                        ),
                        array(
                            "key" => 1,
                            "type" => "radio",
                            "text" => "三列"
                        ),
                        array(
                            "key" => 2,
                            "type" => "radio",
                            "text" => "四列"
                        ),
                        array(
                            "key" => 3,
                            "type" => "radio",
                            "text" => "五列"
                        )
                    ),
                    "imgList" => array(
                        array(
                            "desc" => "店铺街",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/store.png",
                            "link" => "#"
                        ),
                        array(
                            "desc" => "品牌街",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/brand.png",
                            "link" => "#"
                        ),
                        array(
                            "desc" => "促销活动",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/activity.png",
                            "link" => "#"
                        ),
                        array(
                            "desc" => "最新团购",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/groupbuy.png",
                            "link" => "#"
                        ),
                        array(
                            "desc" => "积分换购",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/exchange.png",
                            "link" => "#"
                        ),
                        array(
                            "desc" => "微社区",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/community.png",
                            "link" => "#"
                        ), array(
                            "desc" => "微筹",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/crowd_funding.png",
                            "link" => "#"
                        ), array(
                            "desc" => "拍卖活动",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/auction.png",
                            "link" => "#"
                        ), array(
                            "desc" => "超值礼包",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/package.png",
                            "link" => "#"
                        ), array(
                            "desc" => "专题汇",
                            "img" => "http://test.ectouch.cn/dscmall/data/attached/nav/topic.png",
                            "link" => "#"
                        )),
                    "showText" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "是"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "否"
                    )),
                    "showGap" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "外边距"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "内边距"
                    )),
                    "showBorder" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "是"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "否"
                    )),
                    "widthSize" => "20%",
                    "isStyleSel" => 3,
                    "isTextSel" => 0,
                    "isBorderSel" => 1,
                    "isGapSel" => array(0, 1),
                    "showGapClass" => array(
                        'nav-gap-in' => true,
                        'nav-gap-out' => true,
                        'nav-border' => false
                    ),
                    "isShowText" => true,
                    "isShowBorder" => false

                )
            ), array(
                "id" => "mod-7234512623",
                "module" => "line",
                "setting" => false,
                "data" => array()
            ), array(
                "id" => "mod-announcement123134",
                "module" => "announcement",
                "setting" => false,
                "data" => array(
                    "isAnnouncement" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "新闻模式",
                        "picSizeKey" => array(0)
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "快报模式",
                        "picSizeKey" => array(0, 1)
                    )),
                    "icon" => "http://test.dscmall.cn/mobile/statics/img/new-icon.png",
                    "isAnnouncementSel" => 0,
                    "isAnnounRight" => true,
                    "isAnnounText" => true,
                    "AnnounText" => "array(双12)全场五折，全国包邮，只限今日，欢迎快速抢购",
                    "announOption" => array(
                        "paginationClickable" => true,
                        "direction" => 'vertical', //纵向滚动
                        "loop" => true,
                        "autoplay" => 4000,
                        "speed" => 400,
                        "freeMode" => true,
                        "noSwiping" => true,
                    ),
                    "contList" => array(array(
                        "text" => "诺基亚6681手机广告欣赏诺基亚6681手机广告欣赏诺基亚6681手机广告欣赏诺基亚6681手机广告欣赏"
                    ), array(
                        "text" => "三星SGHU308说明书下载"
                    ), array(
                        "text" => "手机游戏下载"
                    ), array(
                        "text" => "3G知识普及"
                    )),
                )
            ), array(
                "id" => "mod-blank",
                "module" => "blank",
                "setting" => false,
                "data" => array()
            ), array(
                "id" => "mod-723456",
                "module" => "title",
                "setting" => false,
                "data" => array(
                    "title" => "国际海购",
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
                        "isTextSel" => 1,
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
                    "showStyle" => array(array(
                        "type" => "radio",
                        "text" => "居左显示",
                        "value" => "text-left"
                    ), array(
                        "type" => "radio",
                        "text" => "居中显示",
                        "value" => "text-center"
                    ), array(
                        "type" => "radio",
                        "text" => "居右显示",
                        "value" => "text-right"
                    )),
                    "isStyleSel" => "0",
                    "isShowStyle" => "text-left",
                )
            ), array(
                "id" => "mod-7234512623",
                "module" => "line",
                "setting" => false,
                "data" => array()
            ), array(
                "id" => "mod-323413212556",
                "module" => "nav",
                "setting" => false,
                "data" => array(
                    "showStyle" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "两列"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "三列"
                    ), array(
                        "key" => 2,
                        "type" => "radio",
                        "text" => "四列"
                    ), array(
                        "key" => 3,
                        "type" => "radio",
                        "text" => "五列"
                    )),
                    "imgList" => array(array(
                        "desc" => "全球精选",
                        "img" => "https://img.alicdn.com/tps/i2/100000335007575537/TB2zsAIXB0kpuFjSsziXXa.oVXa_!!0-2-jupush.png_.webp",
                        "link" => "#"
                    ), array(
                        "desc" => "环球品牌",
                        "img" => "https://img.alicdn.com/bao/upload/TB1qVT8OpXXXXamXVXXVoDZPXXX-372-441.jpg_220x10000q30s0.jpg_.webp",
                        "link" => "#"
                    ), array(
                        "desc" => "魅力惠",
                        "img" => "https://img.alicdn.com/tps/i1/TB1hnalOFXXXXbxXpXXVoDZPXXX-372-441.jpg_220x10000q30s0.jpg_.webp",
                        "link" => "#"
                    )),
                    "showText" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "是"
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "否"
                    )),
                    "showGap" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "外边距",
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "内边距",
                    )),
                    "showBorder" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "是",
                    ), array(
                        "key" => 1,
                        "type" => "radio",
                        "text" => "否",
                    )),
                    "widthSize" => "33.3333%",
                    "isStyleSel" => 1,
                    "isTextSel" => 1,
                    "isBorderSel" => 0,
                    "isGapSel" => array(1),
                    "showGapClass" => array(
                        'nav-gap-in' => true,
                        'nav-gap-out' => false,
                        'nav-border' => true
                    ),
                    "isShowText" => false,
                )
            ), array(
                "id" => "mod-blank",
                "module" => "blank",
                "setting" => false,
                "data" => array()
            ), array(
                "id" => "mod-spike",
                "module" => "spike",
                "setting" => false,
                "data" => array(
                    "spikeName" => "限时秒杀",
                    "endTime" => "2016/12/10 12:12:12",
                    "day" => "0",
                    "hour" => "0",
                    "min" => "0",
                    "sec" => "0",
                    "moreLink" => "#",
                    "spikeSwiper" => array(
                        "scrollbar" => '.swiper-scrollbar',
                        "scrollbarHide" => false,
                        "slidesPerView" => 3.6,
                        "paginationClickable" => true,
                        "freeMode" => true,
                    ),
                    "imgList" => array(array(
                        "desc" => "商品一",
                        "price" => "¥780.00",
                        "marketPrice" => "¥3212.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/113_thumb_G_1405033057428.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品二",
                        "price" => "¥120.00",
                        "marketPrice" => "¥343.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201512/thumb_img/146_thumb_G_1450288301351.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品三",
                        "price" => "¥1280.00",
                        "marketPrice" => "¥2213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201512/thumb_img/444_thumb_G_1450995273693.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品四",
                        "price" => "¥229.00",
                        "marketPrice" => "¥812.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/122_thumb_G_1405033816053.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品五",
                        "price" => "¥80.00",
                        "marketPrice" => "¥213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/115_thumb_G_1405033249469.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品六",
                        "price" => "¥76.00",
                        "marketPrice" => "¥329.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/118_thumb_G_1405033567278.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品七",
                        "price" => "¥780.00",
                        "marketPrice" => "¥3212.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/109_thumb_G_1405032377832.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品八",
                        "price" => "¥71.00",
                        "marketPrice" => "¥129.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/98_thumb_G_1405031439049.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "商品九",
                        "price" => "¥999.00",
                        "marketPrice" => "¥4213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/96_thumb_G_1405031306560.jpg",
                        "link" => "#"
                    )),
                    "showTag" => array(array(
                        "key" => 0,
                        "type" => "checkbox",
                        "text" => "价格"
                    ), array(
                        "key" => 1,
                        "type" => "checkbox",
                        "text" => "市场价"
                    )
                        // , array(
                        //   "key" => 2,
                        //   "type" => "checkbox",
                        //   "text" => "标题"
                        // )
                    ),
                    "showStyle" => array(array(
                        "key" => 0,
                        "type" => "radio",
                        "text" => "样式一"
                    ),
                        //  array(
                        //   "key" => 1,
                        //   "type" => "radio",
                        //   "text" => "样式二"
                        // )
                    ),
                    "isTagSel" => array(0, 1, 2),
                    "isShowStyle" => 0
                )
            ), array(
                "id" => "mod-blank",
                "module" => "blank",
                "setting" => false,
                "data" => array()
            ), array(
                "id" => "mod-723456",
                "module" => "title",
                "setting" => false,
                "data" => array(
                    "title" => "猜你喜欢",
                    "traditionStyle" => array(
                        "key" => "0",
                        "isShow" => "block",
                        "text" => "传统样式",
                        "bgStyle" => "#f0f3f6",
                        "fitTitle" => "",
                        "linkTitle" => "",
                        "link" => "",
                        "isAddBtnShow" => "block",
                        "isAddLinkShow" => "none",
                        "isTextSel" => 0,
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
                    ),
                    "showStyle" => array(array(
                        "type" => "radio",
                        "text" => "居左显示",
                        "value" => "text-left"
                    ), array(
                        "type" => "radio",
                        "text" => "居中显示",
                        "value" => "text-center"
                    ), array(
                        "type" => "radio",
                        "text" => "居右显示",
                        "value" => "text-right"
                    )),
                    "isStyleSel" => "0",
                    "isShowStyle" => "text-center",
                )
            ), array(
                "id" => "mod-72345123623",
                "module" => "product",
                "setting" => false,
                "data" => array(
                    "isStyleSel" => "1",
                    "showProductClass" => array(
                        "small" => false,
                        "big" => false
                    ),
                    "showStyle" => array(array(
                        "key" => "0",
                        "type" => "radio",
                        "text" => "大图"
                    ), array(
                        "key" => "1",
                        "type" => "radio",
                        "text" => "中图"
                    ), array(
                        "key" => "2",
                        "type" => "radio",
                        "text" => "小图"
                    )),
                    "isTagSel" => array(0, 2),
                    "showTag" => array(array(
                        "key" => 0,
                        "type" => "checkbox",
                        "text" => "库存"
                    ), array(
                        "key" => 1,
                        "type" => "checkbox",
                        "text" => "销量"
                    ), array(
                        "key" => 2,
                        "type" => "checkbox",
                        "text" => "购物车"
                    )),
                    "imgList" => array(array(
                        "desc" => "豪韵(HyperSound)6360家庭影院5.1电视音响木质组合音箱低音炮功放KTV套装",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥999.00",
                        "marketPrice" => "¥4213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201603/thumb_img/530_thumb_G_1457132840648.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "三星（SAMSUNG）UA58J50SW 58英寸 全高清LED电视 黑色",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥999.00",
                        "marketPrice" => "¥4213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201603/thumb_img/512_thumb_G_1457130136692.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "耐克NikeLebron Xi Premium詹姆斯11代鸳鸯篮球鞋男士 US8/41码",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥4310.00",
                        "marketPrice" => "¥4310.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201512/thumb_img/444_thumb_G_1450995273693.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "良品铺子 手撕牦牛肉（麻辣）165g 牛肉干 内蒙古 牦牛肉棒",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥35.00",
                        "marketPrice" => "¥4213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/82_thumb_G_1405029518456.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "意大利 olitalia奥尼 果醋（苹果味） 500ml",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥30.00",
                        "marketPrice" => "¥213.00",
                        "img" => "http://test.ectouch.cn/dscmall/images/201512/thumb_img/87_thumb_G_1449713951485.jpg",
                        "link" => "#"
                    ), array(
                        "desc" => "马来西亚进口Yame果爱什锦布丁(六种口味)110g*6杯",
                        "sale" => "1220",
                        "stock" => "997",
                        "price" => "¥999.00",
                        "marketPrice" => "¥12.90",
                        "img" => "http://test.ectouch.cn/dscmall/images/201407/thumb_img/89_thumb_G_1405030802674.jpg",
                        "link" => "#"
                    ))
                )
            ), array(
                "id" => "tabdown-72345623",
                "module" => "tabdown",
                "setting" => false,
                "data" => array()
            ));;
        return $aa;
    }

    /**
     * 保存可视化编辑的配置数据
     */
    private function setModule($file = 'index', $data = array())
    {
        if (!empty($data)) {
            $data = '<?php exit("no access");' . serialize($data);
            file_put_contents($this->savePath . '/' . $file . '.php', $data);
        }
    }

    /**
     * 获取可视化配置的数据
     */
    private function getModule($file = 'index')
    {
        $filePath = $this->savePath . '/' . $file . '.php';
        if (is_file($filePath)) {
            $data = file_get_contents($filePath);
            $data = str_replace('<?php exit("no access");', '', $data);
            return unserialize($data);
        }
        return false;
    }
}
