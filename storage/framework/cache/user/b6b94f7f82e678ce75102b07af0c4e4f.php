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


<div class="con mb-7">
	<header class="user-header-box">
		<div class="padding-all">
			<a href="<?php echo url('profile/index');?>">
				<?php if($info['user_picture'] !=='' ) { ?>
				<div class="heaer-img"><img src="<?php echo ($info['user_picture']); ?>"></div>
				<?php } else { ?>
				<div class="heaer-img"><img src="<?php echo elixir('img/no_image.jpg');?>"></div>
				<?php } ?>
			</a>
			<a href="<?php echo url('profile/index');?>" class="box-flex">
				<div class="header-admin">

					<h4 class="ellipsis-one f-07"><?php echo ($info['nick_name']); ?></h4>
					<p class="color-whie f-03 m-top02"><?php echo ($rank['user_rank']['rank_name']); ?></p>
				</div>
			</a>
			<div class="header-icon">
				<!-- <a class="youxiang" href="<?php echo url('index/messagelist');?>">
					<?php if($cache_info ) { ?>
					<div class="tishi-tag"></div>
					<?php } ?>
					<label class="f-03">消息</label>
				</a> -->

				<a class="shezhi" href="<?php echo url('profile/index');?>">
					<i class="iconfont icon-shezhi"></i>
				</a>
			</div>
		</div>
		</a>
	</header>
	<!--order-list-->
	<section class="b-color-f user-function-list">
		<a href="<?php echo url('order/index',array('status'=>0));?>">
			<div class="dis-box padding-all wallet-bt">
				<h3 class="box-flex"><i class="iconfont icon-iconfontquanbudingdan color-red"></i>我的订单</h3>
				<div class="box-flex f-05 text-right onelist-hidden jian-top">全部订单</div>
				<span class="t-jiantou"><i class="iconfont icon-jiantou tf-180 jian-top"></i></span>
			</div>
		</a>
		<ul class="user-order-list g-s-i-title-2 dis-box text-center ">
			<a href="<?php echo url('user/order/index', array('status'=>1));?>" class="box-flex">

				<li>
					<h4><i class="iconfont icon-daifukuan"></i></h4>
					<p class="t-remark3">待付款</p>
					<?php if($pay_count > 0) { ?>
					<div class="user-list-num"><?php echo $pay_count; ?></div>
					<?php } ?>
				</li>
			</a>
			<?php if($team) { ?>
			<a href="<?php echo url('team/user/index');?>" class="box-flex">

				<li>
					<h4><i class="iconfont icon-daifukuan"></i></h4>
					<p class="t-remark3">待拼团</p>
					<?php if($team_num > 0) { ?>
					<div class="user-list-num"><?php echo $team_num; ?></div>
					<?php } ?>
				</li>
			</a>
			<?php } ?>
			<a href="<?php echo url('user/order/index',array('status'=>2));?>" class="box-flex">
				<li>
					<h4><i class="iconfont icon-wodetubiaosvg03"></i></h4>
					<p class="t-remark3">待收货</p>
					<?php if($confirmed_count > 0) { ?>
					<div class="user-list-num"><?php echo $confirmed_count; ?></div>
					<?php } ?>
				</li>
			</a>
			<a href="<?php echo url('user/index/comment_list');?>" class="box-flex">
				<li>
					<h4><i class="iconfont icon-daipingjia"></i></h4>
					<p class="t-remark3">待评价</p>
					<?php if($not_comment > 0) { ?>
					<div class="user-list-num"><?php echo $not_comment; ?></div>
					<?php } ?>
				</li>
			</a>
			<a href="<?php echo url('user/refound/index');?>" class="box-flex">
				<li>
					<h4><i class="iconfont icon-tuihuanhuo"></i></h4>
					<p class="t-remark3">退换货</p>
					<?php if($return_count > 0) { ?>
					<div class="user-list-num"><?php echo $return_count; ?></div>
					<?php } ?>
				</li>
			</a>
		</ul>
	</section>
	<!--money-list-->
	<section class="m-top08 user-function-list b-color-f">
		<a href="<?php echo url('user/account/index');?>">
			<div class="dis-box padding-all wallet-bt">
				<h3 class="box-flex"><i class="iconfont icon-qianbao  color-fe"></i>我的钱包</h3>
				<!-- <div class="box-flex f-05 text-right onelist-hidden jian-top">资金管理</div> -->
				<!-- <span class="t-jiantou"><i class="iconfont icon-jiantou tf-180 jian-top"></i></span> -->
			</div>
		</a>
		<ul class="user-order-list  dis-box text-center">
			<!-- <a href="<?php echo url('user/account/index');?>" class="box-flex">
				<li>
					<h4 class="ellipsis-one"><?php echo ($user_pay['user_money']); ?></h4>
					<p class="t-remark3">余额</p>
				</li>
			</a> -->
			<a href="<?php echo url('user/account/bonus');?>" class="box-flex">

				<li>
					<h4 class="ellipsis-one"><?php echo $bonus; ?></h4>
					<p class="t-remark3">红包</p>
				</li>
			</a>
			<a href="javascript:;" class="box-flex">
				<li>
					<h4 class="ellipsis-one"><?php echo ($user_pay['pay_points']); ?></h4>
					<p class="t-remark3">积分</p>
				</li>
			</a>
			<a href="<?php echo url('user/account/coupont');?>" class="box-flex">
				<li>
					<?php if($couponses == '' ) { ?>
					<h4 class="ellipsis-one">0</h4> <?php } else { ?>
					<h4 class="ellipsis-one"><?php echo $couponses; ?></h4> <?php } ?>
					<p class="t-remark3">优惠券</p>
				</li>
			</a>
		</ul>
	</section>
	<!--function-nav-list-->
	<nav class="b-color-f user-nav-box m-top08">
		<div class="box ul-4 text-c b-color-f">
			<a href="<?php echo url('user/index/collectionlist');?>">
				<label><i class="iconfont icon-favorgoods color-fe"></i></label>
				<p class="f-03 col-7">收藏的商品</p>
			</a>
			<a href="<?php echo url('user/index/storelist');?>" class="">
				<label><i class="iconfont icon-collect-shop color-289"></i></label>
				<p class="f-03 col-7">关注的店铺</p>
			</a>

			<?php if($share) { ?>
			<a href="<?php echo url('user/index/affiliate');?>">
				<label><i class="iconfont icon-fenxiang1 color-e72"></i></label>
				<p class="f-03 col-7">我的分享</p>
			</a>
			<?php } ?>
			<a href="<?php echo url('user/index/helpcenter');?>">
				<label><i class="iconfont icon-bangzhu color-f9c"></i></label>
				<p class="f-03 col-7">帮助中心</p>
			</a>
			<?php if($drp) { ?>
			<a href="<?php echo url('drp/index/index');?>">
				<label><i class="iconfont icon-dianpu1 color-red"></i></label>
				<p class="f-03 col-7">我的微店</p>
			</a>
			<?php } ?>
			<a href="<?php echo url('user/crowd/index');?>">
				<label><i class="iconfont icon-zhongchouxuanzhong color-ff7"></i></label>
				<p class="f-03 col-7">微筹广场</p>
			</a>
			<?php if($team) { ?>
			<a href="<?php echo url('team/index/index');?>">
				<label><i class="iconfont icon-pintuan color-98 color-f9c"></i></label>
				<p class="f-03 col-7">拼团频道</p>
			</a>
			<?php } ?>
			<a href="<?php echo url('merchants/index/index');?>">
				<label><i class="iconfont icon-iconfontruzhu color-98"></i></label>
				<p class="f-03 col-7">商家入驻</p>
			</a>
			<a href="<?php echo url('user/index/history');?>">
				<label><i class="iconfont icon-liulanjilu color-c78"></i></label>
				<p class="f-03 col-7">浏览记录</p>
			</a>

		</div>
	</nav>

</div>
<!--悬浮菜单s-->
<div class="filter-top" id="scrollUp">
	<i class="iconfont icon-jiantou"></i>
</div>
<footer class="footer-nav dis-box">
	<a href="<?php echo url('/');?>" class="box-flex nav-list">
		<i class="nav-box i-home"></i><span>首页</span>
	</a>
	<a href="<?php echo url('category/index/index');?>" class="box-flex nav-list">
		<i class="nav-box i-cate"></i><span>分类</span>
	</a>
	<a href="javascript:;" class="box-flex nav-list j-search-input">
		<i class="nav-box i-shop"></i><span>搜索</span>
	</a>
	<a href="<?php echo url('cart/index/index');?>" class="box-flex position-rel nav-list">
		<i class="nav-box i-flow"></i><span>购物车</span>
	</a>
	<?php if($filter) { ?>
	<a href="<?php echo url('drp/user/index');?>" class="box-flex nav-list active">
		<i class="nav-box i-user"></i><span><?php echo $custom; ?>中心</span>
	</a>
	<?php } elseif ($community) { ?>
	<a href="<?php echo url('community/index/index');?>" class="box-flex nav-list active">
		<i class="nav-box i-user"></i><span>社区</span>
	</a>
	<?php } else { ?>
	<a href="<?php echo url('user/index/index');?>" class="box-flex nav-list active">
		<i class="nav-box i-user"></i><span>我</span>
	</a>
	<?php } ?>
</footer>
<!--悬浮菜单e-->
		<script>
			/*店铺信息商品滚动*/
			var swiper = new Swiper('.j-g-s-p-con', {
				scrollbarHide: true,
				slidesPerView: 'auto',
				centeredSlides: false,
				grabCursor: true
			});

       $(function(){
        //清除搜索记录
        var history = <?php if($history) { echo $history; } else { ?>""<?php } ?>;
        $(".clear_history").click(function(){
            if(history){
	            $.get("<?php echo url('user/index/clear_history');?>", '', function(data){
	        		if(data.status == 1){
			            $(".clearHistory").remove();
	                }
	            }, 'json');
            }
        });
    })
</script>
</body>

</html>