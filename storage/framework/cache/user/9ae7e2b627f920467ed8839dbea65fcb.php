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
<div class="con">
	<?php if($comment_list) { ?>
	<div class="user-evaluation">
		<section class="product-list product-list-small">
			<ul>
				<?php $n=1;if(is_array($comment_list)) foreach($comment_list as $comment) { ?>
				<li>
					<div class="product-div">
						<a class="product-div-link" href="<?php echo url('goods/index/index',array('id'=>$comment['goods_id']));?>"></a>
						<img class="product-list-img" src="<?php echo ($comment['goods_thumb']); ?>" />
						<div class="product-text">
							<h4><?php echo ($comment['goods_name']); ?></h4>
                            <p class="fl"><span class="p-price t-first "><?php echo ($comment['goods_price']); ?></span></p>
							<a
								href="<?php echo url('user/index/add_comment',array('rec_id'=>$comment['rec_id']));?>" class="btn-submit1 fr" style="z-index:1000;">评价晒单</a>
						</div>
					</div>
				</li>
				<?php $n++;}unset($n); ?>
			</ul>
		</section>
	</div>
	<?php } else { ?>
	<div class="no-div-message">
		<i class="iconfont icon-biaoqingleiben"></i>
		<p>亲，您还没有需要评价的订单哦～！</p>
	</div>
	<?php } ?>
</div>
</body>
</html>