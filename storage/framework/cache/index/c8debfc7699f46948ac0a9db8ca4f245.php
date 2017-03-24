<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name=viewport content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0">
    <title><?php echo $page_title; ?></title>
    <link rel=stylesheet href="//at.alicdn.com/t/font_om8zpb0ccjnel8fr.css" media="screen" title="no title">
    <link rel=stylesheet href="//at.alicdn.com/t/font_hbz3xcq1mqk9be29.css" media="screen" title="no title">
    <link href=/mobile/statics/css/app.cf5835bfdd81a516b956b31650adb1ee.css rel=stylesheet>
    <script>window.ROOT_URL = '/mobile/';</script>
    <style type="text/css">
    header.search-fixed {position: fixed; width: 100%;top: 0; z-index: 20;}
    .product-list figure h4 {min-height: 3.7rem; height: 4rem; line-height: 2rem}
    <?php if($user_status == 0) { ?>
    .price em{ display: none}
    .product-list figure .price em, .spike-swiper .swiper-slide .price em {display: none}
    <?php } ?>
    /*兼容导航菜单不支持css3属性*/
    .announcement{line-height: 5.2rem; display: box; display: -webkit-box; display:flex; display:-webkit-flex;}
    .announ-left{float:left;line-height:initial;}
    .announ-left img{vertical-align:midden;}
    .announ-right {float:right;}
    .announ-center {float:left;width:64%; -webkit-box-flex:1; box-flex:1;}
    /*搜索*/
    .mod-search header {display:box; display:-webkit-box; display:flex; display:-webkit-flex; }
    .mod-search .header-search { -webkit-box-pack:center; -moz-box-pack:center;-ms-flex-align:center; -webkit-align-items:center;-moz-align-items:center;}
    .mod-search .search-left {float:left; padding-top: .6rem; padding-bottom: .6rem;}
    .mod-search .search-center  {  width: 64%;float:left;font-size: 1.4rem; margin-top: .6rem; margin-bottom: .6rem; }
    .mod-search .search-right{ float:right; margin-top: .6rem; margin-bottom: .6rem;}

    .spike header{overflow:hidden;}
    .spike .header-center{ width:50%; float:left; height: initial;padding-top: .6rem; padding-bottom: .6rem;}
    .spike .header-left{float:left;  padding-top: .6rem; padding-bottom: .6rem;}
    .spike .header-right {float:right;   padding-top: .6rem; padding-bottom: .6rem}
    .tabdown ul {width:100%;}
    .tabdown ul li {width:20%;float:left;}
    </style>
</head>
<body>
<div id="app"></div>
<script type=text/javascript src=/mobile/statics/js/manifest.43f8edcfdfbbef265aa0.js></script>
<script type=text/javascript src=/mobile/statics/js/vendor.e02207ee40aa6a89e40b.js></script>
<script type=text/javascript src=/mobile/statics/js/app.0b09ca89496ea7be877f.js></script>
</body>
</html>