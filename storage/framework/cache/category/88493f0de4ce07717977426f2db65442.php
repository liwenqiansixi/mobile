<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black"/>
    
    <meta name="format-detection" content="telephone=no"/>
    <meta charset="utf-8">
    <meta name="description" content="<?php echo $description; ?>"/>
    <meta name="keywords" content="<?php echo $keywords; ?>"/>
 
    <title><?php echo $page_title; ?></title>
    <?php echo global_assets('css');?>
    <script type="text/javascript">var ROOT_URL = '/mobile/';</script>
    <?php echo global_assets('js');?>
    <?php if($is_wechat) { ?>
    <script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.1.0.js"></script>
 
    <script type="text/javascript">
        // 分享内容
        var shareContent = {
            title: '<?php echo $page_title; ?>',
            desc: '<?php echo $description; ?>',
            link: '',
            imgUrl: '<?php if($page_img) { echo $page_img; } else { echo elixir("img/wxsdk.png", true); } ?>'
        };
        $(function(){
            var url = window.location.href;
            var jsConfig = {
                debug: false,
                jsApiList: [
                    'onMenuShareTimeline',
                    'onMenuShareAppMessage',
                    'onMenuShareQQ',
                    'onMenuShareWeibo',
                    'onMenuShareQZone'
                ]
            };
            $.post('<?php echo url("wechat/jssdk/index");?>', {url: url}, function (res) {
                if(res.status == 200){
                    jsConfig.appId = res.data.appId;
                    jsConfig.timestamp = res.data.timestamp;
                    jsConfig.nonceStr = res.data.nonceStr;
                    jsConfig.signature = res.data.signature;
                    // 配置注入
                    wx.config(jsConfig);
                    // 事件注入
                    wx.ready(function () {
                        wx.onMenuShareTimeline(shareContent);
                        wx.onMenuShareAppMessage(shareContent);
                        wx.onMenuShareQQ(shareContent);
                        wx.onMenuShareWeibo(shareContent);
                        wx.onMenuShareQZone(shareContent);
                    });
                }
            }, 'json');
        })
    </script>
    <?php } ?>

</head>
<body>
    <p style="text-align:right; display:none;"><?php echo config('shop.stats_code');?></p>
<div id="loading"><img src="<?php echo elixir('img/loading.gif');?>" /></div>
<div class="search-div j-search-div ts-3">
    <section class="search">
        <form action="<?php echo url('/');?>?m=category&a=search" method="post" id="search-form">
        <div class="text-all dis-box j-text-all text-all-back">
            <a class="a-icon-back j-close-search" href="javascript:;"><i class="iconfont icon-back"></i></a>
            <div class="box-flex input-text">
                <label class="search-check j-search-check" data="2">
                    <span>商品</span><i class="iconfont icon-xiajiantou"></i>
                </label>
                <input class="j-input-text" type="text" name="keyword" placeholder="<?php if($keywords) { echo $keywords; } else { ?>商品/店铺搜索<?php } ?>" />
                <input type="hidden" name="type_select" value="2" />
                <input type="hidden" name="isself" value="<?php echo $isself; ?>" />
                <input type="hidden" name="brand" value="<?php echo $brand_id; ?>" />
                <input type="hidden" name="price_min" value="<?php echo $price_min; ?>" />
                <input type="hidden" name="price_max" value="<?php echo $price_max; ?>" />
                <input type="hidden" name="filter_attr" value="<?php echo $filter_attr; ?>"/>
                <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
            </div>
            <a type="button" class="btn-submit" onclick="$('#search-form').submit()">搜索</a>
        </div>
        </form>
    </section>
    <section class="search-con">
        <div class="history-search">
            <!--<div class="swiper-wrapper">
                <div class="swiper-slide">-->
                    <p>
                        <label class="fl">热门搜索</label>
                    </p>
                    <ul class="hot-search a-text-more">
                        <?php $n=1;if(is_array($hot_keywords)) foreach($hot_keywords as $v) { ?>
                        <li class="w-3"><a href="<?php echo url('category/index/search', array('keyword'=>$v));?>"><span class="onelist-hidden"><?php echo $v; ?></span></a></li>
                        <?php $n++;}unset($n); ?>
                    </ul>
                    <p class="hos-search">
                        <label class="fl">最近搜索</label>
                        <span class="fr clear_history onelist-hidden" ><i class="iconfont icon-xiao10"></i></span>
                    </p>
                    <?php if($history_keywords) { ?>
                    <ul class="hot-search a-text-more a-text-one search-new-list" id="search-con">
                        <?php $n=1;if(is_array($history_keywords)) foreach($history_keywords as $v) { ?>
                        <?php if($v) { ?>
                        <li><a href="<?php echo url('category/index/search', array('keyword'=>$v));?>"><span class="onelist-hidden"><?php echo $v; ?></span></a></li>
                        <?php } ?>
                        <?php $n++;}unset($n); ?>
                    </ul>
                    <?php } else { ?>
                    <div class="no-div-message">
                        <p>暂无搜索记录</p>
                    </div>
                    <?php } ?>
                <!--</div>
            </div>-->
        <div class="swiper-scrollbar"></div>
        </div>
    </section>
    <footer class="close-search j-close-search">
        点击关闭
    </footer>
</div>
<script type="text/javascript">

    $(function(){
        //清除搜索记录
        var history = <?php if($history_keywords) { echo $history_keywords; } else { ?>""<?php } ?>;
        $(".clear_history").click(function(){
            if(history && $("ul#search-con").length > 0){
                $.get("<?php echo url('category/index/clear_history');?>", '', function(data){
                    if(data.status){
                        $("#search-con").remove();
                        var no = '<div class="no-div-message"><p>暂无搜索记录</p></div>';
                        $(".hos-search").after(no);
                    }
                }, 'json');
            }
        });
    })
</script>


<div class="con">
    <div class="category-top blur-div">
        <header>
            <section class="search">
                <div class="text-all dis-box j-text-all text-all-back">
                    <a class="a-icon-back j-close-search" href="javascript:history.go(-1);"><i class="iconfont icon-back"></i></a>
                    <div class="box-flex input-text n-input-text i-search-input">
                        <a class="a-search-input j-search-input" href="javascript:void(0)"></a>
                        <i class="iconfont icon-sousuo"></i>
                        <input class="j-input-text" type="text" placeholder="商品/店铺搜索" />
                        <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
                    </div>
                    <?php if($cat_id) { ?>
                    <a href="javascript:void(0)" class="s-filter j-s-filter">筛选</a>
                    <?php } ?>
                </div>
            </section>
        </header>
        <aside>
            <div class="menu-left" id="sidebar">
                <div class="swiper-scroll">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <ul>
                                <?php $n=1;if(is_array($category)) foreach($category as $key=>$val) { ?>

                                <li data="<?php echo url('category/index/childcategory', array('id'=>$val['id']));?>" data-id="<?php echo ($val['id']); ?>"><?php echo sub_str($val['name'], 4,'');?></li>

                                <?php $n++;}unset($n); ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <section class="menu-right padding-all">
           <div class="loading"><img src="<?php echo elixir('img/loading.gif');?>" /></div>
            <!--<ul class="mune-no-img"></ul>-->
            <ul class="child_category"></ul>
            <script id="category" type="text/html">
            <%each category as value%>
                    <%if value.cat_id%>
                    <a href="<%value.url%>"><h5><%value.name%></h5></a>
                    <!--<ul class="mune-no-img">-->
                    <ul>
                    <%each value.cat_id as cat%>
                        <li class="w-3"><a href="<%cat.url%>"></a><img src="<%cat.cat_img%>" alt="<%cat.name%>" /><span><%cat.name%></span></li>
                    <%/each%>
                    </ul>
                    <%else%>
                    <li class="w-3"><a href="<%value.url%>"></a><img src="<%value.cat_img%>" alt="<%value.name%>" /><span><%value.name%></span></li>
                    <%/if%>
            <%/each%>
            </script>
        </section>
    </div>
    	<!--悬浮菜单s-->
	<div class="filter-top filter-top-index" id="scrollUp">
		<i class="iconfont icon-jiantou"></i>
	</div>

	<footer class="footer-nav dis-box">
		<a href="<?php echo url('/');?>" class="box-flex nav-list">
			<i class="nav-box i-home"></i><span>首页</span>
		</a>
		<a href="<?php echo url('category/index/index');?>" class="box-flex nav-list  active">
			<i class="nav-box i-cate"></i><span>分类</span>
		</a>
		<a href="javascript:;" class="box-flex nav-list j-search-input">
			<i class="nav-box i-shop"></i><span>搜索</span>
		</a>
		<a href="<?php echo url('cart/index/index');?>" class="box-flex position-rel nav-list">
			<i class="nav-box i-flow"></i><span>购物车</span>
		</a>
		<?php if($filter) { ?>
		<a href="<?php echo url('drp/user/index');?>" class="box-flex nav-list">
			<i class="nav-box i-user"></i><span><?php echo $custom; ?>中心</span>
		</a>
		<?php } elseif ($community) { ?>
		<a href="<?php echo url('community/index/index');?>" class="box-flex nav-list">
			<i class="nav-box i-user"></i><span>社区</span>
		</a>
		<?php } else { ?>
		<a href="<?php echo url('user/index/index');?>" class="box-flex nav-list">
			<i class="nav-box i-user"></i><span>我</span>
		</a>
		<?php } ?>
	</footer>
	<!--悬浮菜单e-->

<script type="text/javascript">
$(function(){
    var cat_id = 0;
    ajaxAction($("#sidebar li:first"), $("#sidebar li:first").attr("data"), $("#sidebar li:first").attr("data-id"));
    $("#sidebar li").click(function(){
        var li = $(this);
        var url = $(this).attr("data");
        var id = $(this).attr("data-id");
        ajaxAction(li, url, id);
    });
    function ajaxAction(obj, url, id){
        if(cat_id != id){
            $.ajax({
                type: 'get',
                url: url,
                data: '',
                cache: true,
                async: false,
                dataType: 'json',
                beforeSend: function(){
                    $(".loading").show();
                },
                success: function(result){
                    if(typeof(result.code) == 'undefined'){
                        $(".child_category").animate({
                            scrollTop: 0
                        }, 0);
                        template.config('openTag', '<%');
                        template.config('closeTag', '%>');
                        var html = template('category', result);
                        $(".child_category").html(html);
                        //$(".child_category ul").html(result);
                        obj.addClass("active").siblings("li").removeClass("active");
                    }
                    else{
                        d_messages(result.message);
                    }
                },
                complete: function(){
                    $(".loading").hide();
                }
            });
            cat_id = id;
        }
    }
})
</script>
	</body>
</html>