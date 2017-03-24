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
<body>
<form  name="formReturn" method="post" action="<?php echo url('user/refound/submit_return');?>" enctype="multipart/form-data" class="validform">
    <input type="hidden" name="return_type" value="0">
    <input name="return_rec_id" value="<?php echo $return_rec_id; ?>" type="hidden" />
    <input name="return_g_id" value="<?php echo $return_g_id; ?>" type="hidden" />
    <input name="return_g_number" value="<?php echo $return_g_number; ?>" type="hidden" />
    <div class="con mb-7" id="checkPage">
        <!--商品list -s-->
        <section class="flow-checkout-pro j-flow-checkout-pro b-bor">
            <div class="product-list-small b-color-f dis-box">
                <ul class="box-flex flow-checkout-bigpic" style="display:block;">
                    <li>
                        <div class="product-div">
                            <img class="product-list-img" src="<?php echo ($goods['goods_thumb']); ?>">
                            <div class="product-text">
                                <h4><?php echo ($goods['goods_name']); ?></h4>
                                <p>
                                    <span class="p-price t-first "><em>¥</em><?php echo ($goods['goods_price']); ?><small class="fr t-remark">x<?php echo ($goods['goods_number']); ?></small></span>
                                </p>
                                <p class="dis-box p-t-remark"><?php echo ($goods['goods_attr']); ?></p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
        <!--商品list -e-->
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7">申请服务<em>*</em></h4>
            <div class="select-one-1 ect-bg-colorf user-service n-user-service">
                <ul class="dis-box j-get-one ect-radio-2 j-refound-type">
                    <li class="ect-select  list-select promotion my-but-pre j-return-rom">
                        <input name="return_type" type="radio" id="msg-type0" checked="checked" value="0">
                        <label class="ts-1 dis-block" for="msg-type0">维修<i></i></label>
                    </li>
                    <li class="ect-select my-but-pre j-return-attribute">
                        <input name="return_type" type="radio" id="msg-type1" value="2">
                        <label class="ts-1 dis-block" for="msg-type1">换货<i></i></label>
                    </li>
                    <li class="ect-select list-select hasgoods my-but-pre j-return-rom">
                        <input name="return_type" type="radio" id="msg-type2" value="1">
                        <label class="ts-1 dis-block" for="msg-type2">退货<i></i></label>
                    </li>
                </ul>
            </div>
        </section>
        <!--换货属性-->
        <section class="user-return-list-box  b-color-f m-top08 user-return-attribute-box j-refound-spec">
            <script id="j-refound" type="text/html">
                <%if spec_list != ''%>
                <h4 class="f-05 col-7">换货属性<em>*</em></h4>
                    <section>
                            <%each spec_list as spec key%>
                            <input type="hidden" name="attr_val[]" id="spec_<%key%>">
                            <div class="select-one user-service n-user-service s-return-box m-top1px">
                                <p class="f-03 col-7"><%#spec.name%>:</p>
                                <!-- 判断属性是复选还是单选 -->
                                <ul class="select-one j-get-one m-top04">
                                    <%each spec.values as spec_value%>
                                    <a class="ect-select dis-flex position-rel fl" href="javascript:;">
                                        <label class="ts-1 j-select-spec" id="for_<%key%>" value="<%spec_value.id%>"><%#spec_value.label%></label>
                                    </a>
                                    <%/each%>
                                </ul>
                            </div>
                            <%/each%>
                    </section>
                <%/if%>
            </script>
        </section>
        <!--换货属性-->
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7 j-refound-cause">退货原因<em>*</em></h4>
            <div class="user-return-content position-rel">
                <select class="select" name="parent_id" datatype="*" nullmsg="退货原因不能为空">
                    <?php echo $cause_list; ?>
                </select>
                <i class="iconfont icon-more tf-90 ts-5"></i>
            </div>
        </section>
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7">商品数量<em>*</em></h4>
            <div class="div-num dis-box m-top08">
                <a class="num-less" data-min-num="1"></a>
                <input class="box-flex" type="text" value="1" name="goods_number" id="goods_number" readonly  nullmsg="换货数量不能为空" datatype="n" errormsg="换货数量为数字">
                <a class="num-plus" data-max-num="<?php echo ($goods['goods_number']); ?>"></a>
            </div>

        </section>
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7 j-refound-description">退款说明<em>*</em></h4>
            <div class="user-return-cont-right position-rel">
                <textarea rows="3" name="return_brief" placeholder="请填写退款描述"></textarea>
            </div>
        </section>
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <input type="hidden" name="credentials" value="0">
            <li class="select-three j-select-all">
                <section class="j-get-i-more">
                    <header class="of-hidden dis-box">
                        <div class="ect-select box-flex">
                            <label class="dis-box label-this-all">
                                <em class=" box-flex f-06 col-3 n-ti-name"> 有质检报告</em>
                                <i class="j-select-btn"></i>
                            </label>
                        </div>
                    </header>
                </section>
            </li>
        </section>
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7">图片信息</h4>
            <div class="form-group add-img-n-maxbox"style="margin-bottom:0">
                <div class=" ">
                     <div class="over-n position-rel n-apply-img-box" name="add_img"></div>
                     <div class="n-add-ts-cont">
                        <input type='hidden' name='textfield' id='textfield' class='txt' />
                	 </div>
                </div>
                <li class="user-return-img position-rel text-c m-top10">
                    <h5 class="m-top08"><i class="iconfont icon-jiahao"></i></h5>
                    <p class="f-03 col-9">上传凭证</p>
                    <input type="file" name="file" class="file" id="file" size="28" onchange="document.getElementById('textfield').value=this.value;UpladFile()" />
                </li>
                <!--图片放大-->
                <div class="weui-gallery" id="gallery">
                    <span class="weui-gallery__img" id="galleryImg"></span>
                    <div class="weui-gallery__opr">
                        <a href="javascript:" class="weui-gallery__del galleryDel">
                            <i class="weui-icon-delete weui-icon_gallery-delete"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!--个人信息-->
        <section class="user-return-list-box padding-all b-color-f m-top08">
            <h4 class="f-05 col-7">个人信息<em>*</em></h4>
            <ul class="user-register of-hidden">
                <li class="text-all dis-box j-text-all">
                    <div class="box-flex input-text">
                        <input class="j-input-text" type="text" name="addressee" placeholder="收货人" datatype="*" nullmsg="请输入收货人姓名" >
                        <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
                    </div>
                </li>
                <li class="text-all dis-box j-text-all">
                    <div class="box-flex input-text">
                        <input class="j-input-text" type="tel" name="mobile" placeholder="手机号码"  datatype="m" errormsg="手机号码格式不正确" nullmsg="请填写手机号码">
                        <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
                    </div>
                </li>
                <li class="text-all dis-box j-text-all">
                    <div class="box-flex input-text">
                        <input class="j-input-text" type="text" placeholder="请输入邮箱">
                        <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
                    </div>
                </li>
                <!--address-start-->
                <li class="text-all" id="editAddressForm">
                    <input type="hidden" id="province_id" name="province_region_id" value="<?php echo ($consignee_list['province_id']); ?>">
                    <input type="hidden" id="city_id" name="city_region_id" value="<?php echo ($consignee_list['city_id']); ?>">
                    <input type="hidden" id="district_id" name="district_region_id" value="<?php echo ($consignee_list['district_id']); ?>">
                    <input type="hidden" id="town_id" name="town_region_id" value="<?php echo ($consignee_list['town_region_id']); ?>">
                    <input type="hidden" id="village_id" name="village_region_id" value="">
                    <input type="hidden" id="region_id" name="region_id" value="">
                    <input type="hidden" id="address_id" name="address_id" value="<?php echo ($consignee_list['address_id']); ?>">
                    <div class="address-box" id="selectAddressBtn" region-data="">
                            <label class="fl col-9 f-06">所在地区</label>
                            <span class="fl text-all-span addressdetail" id="addressLabelId"><?php echo $user_address; ?></span>
                            <span class="t-jiantou fr"><i class="iconfont icon-jiantou tf-180"></i></span>
                    </div>
                </li>
                 <!--address-end-->
                <li class="text-all dis-box j-text-all">
                    <div class="user-return-cont-left">详细地址 :</div>
                    <div class="box-flex">
                        <div class="user-return-cont-right position-rel">
                            <textarea rows="3" name="return_address" tip="请填写详细地址" altercss="user-return-cont-left" class="user-return-cont-left" datatype="*" nullmsg="请填写详细地址">请填写详细地址</textarea>
                        </div>
                    </div>
                </li>
                <li class="text-all dis-box">
                    <div class="user-return-cont-left">留言 :</div>
                    <div class="box-flex">
                        <div class="user-return-cont-right position-rel">
                            <textarea rows="3" name="return_remark" tip="请填写留言" altercss="user-return-cont-left" class="user-return-cont-left">请填写留言</textarea>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
        <div class="padding-all user-bg m-top12">
            <h4 class="f-05 col-6 mb-1"> 服务说明</h4 >
            <p class="f-03 col-9">1.附件赠品，退换货时请一并退回。</p>
            <p class="f-03 col-9 m-top04">2.关于物流损，请您在收到货时务必仔细检查，如发现商品外包装或商品本身外观存在异常，需当场向派送人员指出，并拒收整个包裹；如您在收到货后发现外观异常，请在收货24小时内提交退换货申请，如超时未申请，将无法受理。</p>
            <p class="f-03 col-9 m-top04">3.关于商品实物与网站描述不符；商创保证所出商品均为正品行货，并与时下市场上同样直流商品一致，但因厂家会在没有任何提前通知的情况下更改产品包装，产地或者一些附件，所以我们无法确保您收到的货物与商城图片、产地、附件说明完全一致。</p>
            <p class="f-03 col-9 m-top04">4、如果您在使用时对商品质量表示置疑，您可出具相关书面鉴定，我们会按照国家法律规定予以处理。</p>
        </div>
    <section class="filter-btn dis-box">
        <input type="submit" class="btn-submit box-flex click-show-attr add-to-cart" value="提交申请">
    </section>
    </div>
</form>

<!--地区选择 s-->
<div class="choose-address-page" id="chooseAddressPage">
	<div class="head-fix">
		<header>
			<div class="jd-index-header">
				<div class="jd-index-header-icon-back">
					<span id="goBack"></span>
				</div>
				<div class="jd-index-header-title"></div>
			</div>

		</header>
		<ul class="head-address-ul" id="headAddressUl">
			<li mytitle="0"></li>
			<li mytitle="1"></li>
			<li mytitle="2"></li>
			<li mytitle="3"></li>
			<li mytitle="4"></li>
		</ul>
	</div>
	<div class="address-container" id="addressContainer">
		<div class="address-content" id="addressContentDiv">
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
		</div>
	</div>
</div>
<!--地区选择 e-->
<!--引用js-->
<script type="text/javascript">

// 退换货点击选择地区
$(function ($) {
    getAddress();
});

    //退货属性
    $(".j-return-attribute").click(function() {
        $(".user-return-attribute-box").addClass("active");
        swiper_scroll();
    });
    $(".j-return-rom").click(function() {
        $(".user-return-attribute-box").removeClass("active");
    });
    //表单验证
    $(".validform").Validform({
        tipSweep : true,
        tiptype : function(msg){
            d_messages(msg);
        }
    });

    var xhr;
    function createXMLHttpRequest()
    {
        if(window.ActiveXObject)
        {
            xhr = new ActiveXObject("Microsoft.XMLHTTP");
        }
        else if(window.XMLHttpRequest)
        {
            xhr = new XMLHttpRequest();
        }
    }
    function UpladFile()
    {
        var fileObj = document.getElementById("file").files[0];
        if(fileObj == undefined){
            return false;
        }
        var rec_id  = $('input[name="return_rec_id"]').val();
        var FileController = "<?php echo url('user/refound/img_return');?>";
        var form = new FormData();
        form.append("myfile", fileObj);
        form.append("rec_id", rec_id);
        createXMLHttpRequest();
        xhr.onreadystatechange = handleStateChange;
        xhr.open("post", FileController, true);
        xhr.send(form);
    }
    function handleStateChange()
    {
        if(xhr.readyState == 4)
        {
            if (xhr.status == 200 || xhr.status == 0)
            {
                var result = xhr.responseText;
                var obj =  JSON.parse(result);
                if(obj.error == 1){
                    d_messages(obj.content);
                    return false;
                }
                var i = '';
                var new_img = '';

                $('div[name="add_img"]').empty();
                for (i=0;i<obj.length;i++){
                    new_img += "<div class='goods-info-img-box'><div class='goods-info-img'><img src='" + obj[i].pic + "' width='100%' data-index='"+obj[i].id+"'/></div></div>";
                    new_img += "<input name='img[]' type='hidden' value='"+obj[i].pic+"'>";
                }
                $('div[name="add_img"]').append(new_img);
            }
        }
    }
    var rec_id = $("input[name='return_rec_id']").val();

    $('div[name=add_img]').on( 'click','.clear_pictures',function(){
        $.post("<?php echo url('clear_pictures');?>", {rec_id:rec_id}, function(data){
            if(data.error == 0){
                $('div[name=add_img]').empty();
            }
        }, 'json');
    });

    //显示删除图片
    var $gallery = $("#gallery"),
        $galleryImg = $("#galleryImg");

    $('div[name=add_img]').on('click','img',function(){
        $galleryImg['attr']("style", this.getAttribute("style"));
        $galleryImg['attr']("data-index", this.getAttribute("data-index"));
        $galleryImg['css']("background-image", "url("+this.getAttribute("src")+")");
        $gallery['fadeIn'](100);
    });

    $gallery['on']("click", function(){
        $gallery['fadeOut'](100);
    });
    $('.galleryDel').on('click', function(){
        var index = $galleryImg['attr']("data-index");
        $.post("<?php echo url('clear_pictures');?>", {rec_id:rec_id, id:index}, function(data){
            if(data.error == 0){
                $('img[data-index='+index+']').parent().parent('.goods-info-img-box').remove();
            }
        }, 'json');
    });
    //


    $('.j-select-btn').click(function(){
        var cre = $('input[name="credentials"]').val();
        if(cre == 0){
            $('input[name="credentials"]').val(1);
        }else{
            $('input[name="credentials"]').val(0);
        }
    });

    //换货数量
    var num;
    var min_num;
    var max_num;
    $(".div-num a").click(function () {
        if ($(this).hasClass("num-less")) {
            num = parseInt($(this).siblings("input").val());
            min_num = parseInt($(this).attr("data-min-num"));
            if (num > min_num) {
                num -= 1;
                $(this).siblings("input").val(num);
            } else {
                d_messages("不能小于最小数量");
                $(this).siblings("input").val(min_num);
            }
            return false;
        }
        if ($(this).hasClass("num-plus")) {
            num = parseInt($(this).siblings("input").val());
            max_num = parseInt($(this).attr("data-max-num"));
            if (num < max_num) {
                num += 1;
                $(this).siblings("input").val(num);
            } else {
                d_messages("不能大超过最大数量");
                $(this).siblings("input").val(max_num);
            }
            return false;
        }
    });
    $('input[name=attr_num]').blur(function(){
        num = parseInt($(this).val());
        min_num = parseInt($(this).siblings('a.num-less').attr('data-min-num'));
        max_num = parseInt($(this).siblings('a.num-plus').attr('data-max-num'));
        if(num < min_num){
            d_messages("不能小于最小数量");
            $(this).val(min_num);
            return false;
        }else if(num > max_num){
            d_messages("不能大超过最大数量");
            $(this).val(max_num);
            return false;
        }
    });


    //退换货类型切换
    var reound_data = '';
    var html = '';
    $.ajaxSetup({
        async : false
    });
    $('.j-refound-type li').click(function(){
        if($(this).index() == 0){
            $('.j-refound-cause').html('维修原因<em>*</em>');
            $('.j-refound-description').html('维修说明<em>*</em>');
            $('.j-refound-description').parent().find('[name=return_brief]').attr('placeholder', '请填写维修说明')
        }else if($(this).index() == 1){
            $('.j-refound-cause').html('换货原因<em>*</em>');
            $('.j-refound-description').html('换货说明<em>*</em>');
            $('.j-refound-description').parent().find('[name=return_brief]').attr('placeholder', '请填写换货说明')

            var id = $('input[name=return_rec_id]').val();
            $('.j-refound-data').empty();

            if(reound_data == ''){
                $.post("<?php echo url('get_spec');?>", {id:id}, function(data){
                    if(data.error == 0){
                        reound_data = data;
                    }
                },'json','');
            }
            template.config('openTag', '<%');
            template.config('closeTag', '%>');
            var t = template('j-refound', {spec_list : reound_data.spec});
            $('.j-refound-spec').html(t);
            if(reound_data.spec != ''){
                $('.j-refound-spec').addClass('user-return-attribute-box');
            }else{
                $('.j-refound-spec').removeClass('user-return-attribute-box');
            }

        }else if($(this).index() == 2){
            $('.j-refound-cause').html('退货原因<em>*</em>');
            $('.j-refound-description').html('退货说明<em>*</em>');
            $('.j-refound-description').parent().find('[name=return_brief]').attr('placeholder', '请填写退货说明')
        }
    });
    //商品属性选择
    $('.j-refound-spec').on('click', '.j-select-spec', function(){
        var label_id = $(this).attr('id');
        label_id = label_id.replace('for', 'spec');
        $('#'+label_id).val($(this).attr('value'));
    });
</script>
</body>
</html>