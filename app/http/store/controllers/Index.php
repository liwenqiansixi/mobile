<?php
namespace app\http\store\controllers;

use app\http\base\controllers\Frontend;
use ectouch\QRcode;
use Think\Image;

class Index extends Frontend
{

    private $region_id = 0;
    private $area_info = array();
    private $user_id = 0;
    private $review_goods;
    private $lat;
    private $lng;

    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/other.php'));
        $this->user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        //初始化位置信息
        $this->init_params();
        $this->review_goods = $GLOBALS['_CFG']['review_goods'] == 1 ? ' and review_status>2 ' : '';
        $this->assign('area_id', $this->area_info['region_id']);
        $this->assign('warehouse_id', $this->region_id);
    }

    public function actionIndex()
    {
        $keywords = I('get.where', '');
        $keywords = str_replace(",jia,", "+", $keywords);
        $keywords = mysql_like_quote(trim($keywords));
        $type = I('get.type', '');
        $this->assign('keywords', $keywords);
        $this->assign('type', $type);
        if (IS_AJAX) {
            $condition = ' 1=1 ';
            $keywords = I('keywords');
            if ($keywords) {
                $type = I('type', '');
                if (!empty($type)) {
                    if ($type == 1) {
                        $condition .= " AND a.user_shopMain_category LIKE '%" . $keywords . ":%'";
                    } elseif ($type == 2) {
                        if (empty($_SESSION['keywordwhere']) || $_SESSION['keywordwhere'] != $keywords) {
                            if (!empty($keywords)) {
                                /*记录搜索历史记录*/
                                if (!empty($_COOKIE['ECS']['keywords'])) {
                                    $history = explode(',', $_COOKIE['ECS']['keywords']);
                                    array_unshift($history, $keywords); //在数组开头插入一个或多个元素
                                    $history = array_unique($history);  //移除数组中的重复的值，并返回结果数组。
                                    cookie('ECS[keywords]', implode(',', $history));
                                } else {
                                    cookie('ECS[keywords]', $keywords);
                                }
                            }
                            $_SESSION['keywordwhere'] = $keywords;
                        }
                        $condition .= " AND a.rz_shopName LIKE '%" . $keywords . "%'";
                    }
                }
            }

            $cat_id = I('post.cat_id', 0);
            $store_user = get_cat_store_list($cat_id);
            // $province = I('post.province', 0);
            $city = I('post.city_id', 0);
            // $district = I('post.district', 0);
            $lat = I('lat', 0);
            $lng = I('lng', 0);
            $order = I('order');
            $sort = I('sort', 'DESC');
            $page = I('page', 1);
            // 点击分类
            if ($cat_id) {
                $condition .= " AND a.user_id in(" . $store_user . ")";
            }
            // 点击地区
            if (!empty($city)) {
                $condition .= " AND b.city =" . $city;
            }
            // 排序
            $order .= ' ' . $sort;
            // 偏移量
            $offset = 5;
            $limit = ' limit ' . (($page - 1) * $offset) . ',' . $offset;

            $count = "SELECT count(*) as count FROM {pre}merchants_shop_information as a LEFT JOIN {pre}seller_shopinfo as b ON a.user_id=b.ru_id
                    WHERE " . $condition . ' and a.is_street = 1 and a.merchants_audit = 1';
            if ($lat == 0 && $lng == 0) {
                //当浏览器不支持地图
                $sql = "SELECT * FROM {pre}merchants_shop_information as a LEFT JOIN {pre}seller_shopinfo as b ON a.user_id=b.ru_id
                    WHERE " . $condition . ' and a.is_street = 1 and a.merchants_audit = 1 order by a.shop_id '. $sort . $limit;
            }

            if (!empty($lat) || !empty($lng)) {
                //当浏览器支持地图
                $sql = 'SELECT b.*,a.*,( 6371 * acos( cos( radians(' . $lat . ') ) * cos( radians( b.latitude ) ) * cos( radians( b.longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( b.latitude ) ) )) AS distance FROM {pre}merchants_shop_information as a LEFT JOIN {pre}seller_shopinfo as b ON a.user_id=b.ru_id
                    WHERE ' . $condition . ' and a.merchants_audit = 1 and a.is_street = 1 order by ' . $order . $limit;
            }

            // 缓存优先
            $cache_id = md5($sql);
            $result = read_static_cache($cache_id);
            if($result !== false){
                exit(json_encode($result));
            }

            // 总数
            $counts = $this->db->getOne($count);
            $store_list = $this->db->getAll($sql);
            foreach ($store_list as $key) {
                //评分 start
                if ($key['user_id'] > 0) {
                    //商家所有商品评分类型汇总
                    $merchants_goods_comment = get_merchants_goods_comment($key['user_id']);
                }
                //评分 end
                //店铺关注人数
                $gaze = $this->model->table('collect_store')->where(array('ru_id'=>$key['user_id']))->count('user_id');
                //取出店铺商品
                $sql = "SELECT * FROM {pre}goods WHERE user_id=" . $key['user_id'] . " AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0 " . $this->review_goods . "ORDER BY goods_id desc LIMIT 4";
                $goods = $this->db->getAll($sql);
                if ($_SESSION['user_id'] > 0) {
                    $sql = "SELECT rec_id FROM {pre}collect_store WHERE user_id=" . $_SESSION['user_id'] . " AND ru_id=" . $key['user_id'];
                    $status = $this->db->getOne($sql);
                    $status = $status > 0 ? 'active' : '';
                }
                //是否关注
                $goods = count($goods) > 0 ? $goods : 0;
                $goodsarr = array();
                if ($goods) {
                    foreach ($goods as $gkey) {
                        $goodsarr[] = get_goods_info($gkey['goods_id'], $this->region_id, $this->area_info['region_id']);
                    }
                    foreach ($goodsarr as $k => $val) {
                        $goodsarr[$k]['promote_price'] = empty($val['promote_price']) ? strip_tags(price_format(0)) : strip_tags($val['promote_price']);
                    }
                } else {
                    $goodsarr = 0;
                }
                //取出距离
                $distance = round($key['distance'], 3);
                $info[] = array(
                    'shop_id' => $key['shop_id'],
                    'url' => build_uri('store', array('stid' => $key['user_id'])),
                    'user_id' => $key['user_id'],
                    'shop_name' => get_shop_name($key['user_id'], 1),
                    'shop_logo' => get_image_path(ltrim($key['logo_thumb'], '../')),
                    'commentrank' => $merchants_goods_comment['cmt']['commentRank']['zconments']['score'],//商品评分
                    'commentrank_bg' => $this->cmt($merchants_goods_comment['cmt']['commentRank']['zconments']['score']),//商品评分背景
                    'commentserver' => $merchants_goods_comment['cmt']['commentServer']['zconments']['score'],//服务评分
                    'commentserver_bg' => $this->cmt($merchants_goods_comment['cmt']['commentServer']['zconments']['score']),//服务评分背景
                    'commentdelivery' => $merchants_goods_comment['cmt']['commentDelivery']['zconments']['score'],//时效评分
                    'commentdelivery_bg' => $this->cmt($merchants_goods_comment['cmt']['commentDelivery']['zconments']['score']),//时效评分背景
                    'commentrank_font' => $this->font($merchants_goods_comment['cmt']['commentRank']['zconments']['score']),
                    'commentrank_box' => $this->boxbg($merchants_goods_comment['cmt']['commentRank']['zconments']['score']),
                    'commentserver_font' => $this->font($merchants_goods_comment['cmt']['commentServer']['zconments']['score']),
                    'commentserver_box' => $this->boxbg($merchants_goods_comment['cmt']['commentServer']['zconments']['score']),
                    'commentdelivery_font' => $this->font($merchants_goods_comment['cmt']['commentDelivery']['zconments']['score']),
                    'commentdelivery_box' => $this->boxbg($merchants_goods_comment['cmt']['commentDelivery']['zconments']['score']),
                    'gaze_number' => $gaze[0],//关注人数
                    'gaze_status' => $status,//当前用户是否关注
                    'goods' => $goodsarr,
                    'title' => $title = count($goods) > 0 ? '爆款商品' : '',
                    'distance' => $distance,
                );
            }

            $result = array('list' => $info, 'totalPage' => ceil($counts / $offset));
            write_static_cache($cache_id, $result);

            die(json_encode($result));
            return;
        }
        // 一级分类
        $category = $this->db->getAll('SELECT cat_id, cat_name, cat_alias_name,touch_icon FROM {pre}category WHERE parent_id=0 and is_show=1 ORDER BY sort_order ASC, cat_id ASC');
        foreach ($category as $key => $val) {
            $category[$key]['cat_alias_name'] = empty($val['cat_alias_name']) ? $val['cat_name'] : $val['cat_alias_name'];
            $category[$key]['touch_icon']=get_image_path($val['touch_icon']);
        }
        // $category = get_category_parentChild_tree1();
        $this->assign('category', $category);
        // 一级地区
        $province = $this->model->table('region')->where(array('parent_id' => 1))->select();
        $this->assign('province', $province);
        // 页面标题
        $this->assign('page_title', L('shop_street'));
        $this->display();
    }

    public function actionRegion()
    {
        $id = I('city');
        $city = $this->model->table('region')->where(array('parent_id' => $id))->select();
        die(json_encode(array('list' => $city, 'html' => 1)));
    }

    public function actionAddCollect()
    {
        $shopid = I('shopid', 0, 'intval');
        if (!empty($shopid) && $_SESSION['user_id'] > 0) {
            $status = $this->db->getRow('SELECT user_id, rec_id FROM {pre}collect_store WHERE ru_id=' . $shopid . " AND user_id=" . $_SESSION['user_id']);
            if (count($status) > 0) {
                $this->db->query('DELETE FROM {pre}collect_store WHERE rec_id=' . $status['rec_id']);
                die(json_encode(array('error' => 2, 'msg' => L('cancel_attention'))));
            } else {
                $this->db->query("INSERT INTO {pre}collect_store (user_id, ru_id, add_time, is_attention) VALUES (" . $_SESSION['user_id'] . ",'$shopid'," . time() . ",1)");
                die(json_encode(array('error' => 1, 'msg' => L('attentioned'))));
            }
        } else {
            die(json_encode(array('error' => 0, 'msg' => L('please_login'))));
        }

    }

    public function actionShopInfo()
    {
        $userid = I('id', '0', 'intval');
        $sql = "SELECT * FROM {pre}merchants_shop_information as a
	          JOIN {pre}seller_shopinfo as b ON a.user_id=b.ru_id
	          WHERE user_id=" . $userid;
        $data = $this->db->getRow($sql);

        if (empty($userid) || $data['user_id'] != $userid) {
            ecs_header("Location: " . url('store/index/index'));
        }
        if ($_SESSION['user_id'] > 0) {
            $sql = "SELECT rec_id FROM {pre}collect_store WHERE user_id=" . $_SESSION['user_id'] . " AND ru_id=" . $data['user_id'];
            $status = $this->db->getOne($sql);
            $status = $status > 0 ? 'active' : '';
        };
        $sql = "SELECT count(user_id) as a FROM {pre}collect_store WHERE ru_id=" . $data['user_id'];
        $follow = $this->db->getOne($sql);
        $follow = empty($follow) ? 0 : $follow;
        $cat = get_user_store_category($data['user_id']);
        $cat = array_slice($cat, 0, 8);
        $sql = "SELECT goods_id FROM {pre}goods  WHERE user_id=" . $data['user_id'] . " and is_on_sale=1 and is_alone_sale=1 and is_delete=0 " . $this->review_goods . " LIMIT 6";
        $list = $this->db->getAll($sql);
        $sql = "SELECT img_url FROM {pre}seller_shopslide WHERE ru_id=" . $data['user_id'] . " AND is_show=1";
        $flash = $this->db->getRow($sql);
        $flash['img_url'] = (stripos($flash['img_url'], '../') === false) ? '../' . $flash['img_url'] : $flash['img_url'];
        if ($list) {
            foreach ($list as $key => $val) {
                $list[$key] = get_goods_info($val['goods_id'], $this->region_id, $this->area_info['region_id']);
            }
            foreach ($list as $key => $val) {
                if (empty($val['promote_price'])) {
                    $list[$key]['promote_price'] = price_format(0);
                }
            }
        }
        $info = $this->shopdata($data);
        if ($info === false) {
            $this->redirect('store/index/index');
        }
        $info['shop_category'] = $cat;
        $info['count_gaze'] = $follow;
        $info['gaze_status'] = $status;
        $info['goods_list'] = $list;
        $this->assign('info', $info);
        $this->assign('page_title', $info['shop_name']);
        $this->display();
    }

    public function actionProList()
    {
        $type = I('type', '');
        $ru_id = I('ru_id', '');
        $keyword = I('keyword', '');
        $bid = I('bid', '');
        $cat_id = I('cat_id', '');
        $bigcat = I('bigcat', '');
        $whereinfo = I('where', '');
        $order = I('order', '');
        $sort = I('sort', '1');
        if($sort == 3){
            $sort = 'g.sales_volume';
        }
        if($sort == 4){
            $sort = 'shop_price';
        }
        if($cat_id == 0){
            $children = '';
        }else{
            $children = get_children($cat_id, 0, 0, 'merchants_category', "g.user_cat");
        }

        if (IS_AJAX) {
            $size = I('size', 6);
            $page = I('post.page', 1, 'intval');
            

            $list = store_get_goods($children, $bid, 0, 0, '', $size, $page, $sort, $order, $ru_id, $this->region_id, $this->area_info['region_id'], $keyword, $type);
            $maxpage = store_get_goods($children, $bid, 0, 0, '', 0, 0, $sort, $order, $ru_id, $this->region_id, $this->area_info['region_id'], $keyword, $type);

            /*记录搜索历史记录*/
            if (!empty($_COOKIE['ECS']['keywords'])) {
                $history = explode(',', $_COOKIE['ECS']['keywords']);
                array_unshift($history, $keyword); //在数组开头插入一个或多个元素
                $history = array_unique($history);  //移除数组中的重复的值，并返回结果数组。
                cookie('ECS[keywords]', implode(',', $history));
            } else {
                cookie('ECS[keywords]', $keyword);
            }
            $show = empty($list) ? 0 : 1;
            die(json_encode(array('list' => $list, 'totalPage' => ceil($maxpage / $size), 'show' => $show)));
        }
        $sql = "SELECT bid, bank_name_letter, brandName FROM {pre}merchants_shop_brand WHERE user_id=" . $ru_id;
        $brand = $this->db->getAll($sql);
        $category = get_user_store_category($ru_id);
        $page = empty($page) ? 0 : $page;
        $this->assign('category', $category);
        $this->assign('bigcat', $bigcat);//顶级分类
        $this->assign('brand', $brand);
        $this->assign('page', $page);
        $this->assign('type', $type);
        $this->assign('ru_id', $ru_id);
        $this->assign('cat_id', $cat_id);
        $this->assign('keyword', $keyword);//关键字
        $this->assign('bid', $bid);//分类
        $this->assign('where', '');
        $this->assign('page_title', '店铺商品列表');
        $this->display();
    }

    private function shopdata($data = array())
    {
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        if (empty($user_id)) {
            return false;
        }
        $shop_expiredatestart = strtotime($data['shop_expiredatestart']);
        $info['count_goods'] = $this->sql('user_id=' . $user_id . "   AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0" . $this->review_goods);//所有商品
        $info['count_goods_new'] = $this->sql('is_new = 1 AND user_id=' . $user_id . "   AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0" . $this->review_goods);//所有新品
        $info['count_goods_promote'] = $this->sql('is_promote = 1 AND user_id=' . $user_id . "   AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0" . $this->review_goods);//促销品
        $info['count_bonus'] = $this->sql($user_id , '');//优惠卷总数
        $info['bonus_all'] = $this->sql($user_id, '', 1);//优惠卷
        $info['shop_id'] = $data['shop_id'];
        $info['ru_id'] = $data['user_id'];
        $info['shop_logo'] = get_image_path(ltrim($data['logo_thumb'], '../'));
        $info['shop_name'] = get_shop_name($data['user_id'], 1);
        $info['shop_desc'] = $data['shop_name'];
        $info['shop_start'] = date('Y年m月d日', $shop_expiredatestart);
        $info['shop_address'] = $data['shop_address'];
        $info['shop_flash'] = get_image_path($data['street_thumb']);
        $info['shop_wangwang'] = $this->dokf($data['kf_ww']);
        $info['shop_qq'] = $this->dokf($data['kf_qq']);
        $info['shop_tel'] = $data['kf_tel'];
        $info['is_im'] = $data['is_im'];
        $info['meiqia'] = $data['meiqia'];
        $info['kf_appkey'] = $data['kf_appkey'];
        //评分 start
        if ($data['user_id'] > 0) {
            //商家所有商品评分类型汇总
            $merchants_goods_comment = get_merchants_goods_comment($data['user_id']);
        }
        //评分 end
        //是否关注
        if ($_SESSION['user_id'] > 0) {
            $sql = "SELECT rec_id FROM {pre}collect_store WHERE user_id=" . $_SESSION['user_id'] . " AND ru_id=" . $data['shop_id'];
            $status = $this->db->getOne($sql);
            $status = $status > 0 ? 'active' : '';
        }
        $info['commentrank'] = $merchants_goods_comment['cmt']['commentRank']['zconments']['score'] . '分';//商品评分
        $info['commentserver'] = $merchants_goods_comment['cmt']['commentServer']['zconments']['score'] . '分';//服务评分
        $info['commentdelivery'] = $merchants_goods_comment['cmt']['commentDelivery']['zconments']['score'] . '分';//时效评分
        $info['commentrank_font'] = $this->font($merchants_goods_comment['cmt']['commentRank']['zconments']['score']);
        $info['commentserver_font'] = $this->font($merchants_goods_comment['cmt']['commentServer']['zconments']['score']);
        $info['commentdelivery_font'] = $this->font($merchants_goods_comment['cmt']['commentDelivery']['zconments']['score']);
        $info['gaze_status'] = $status;//当前用户是否关注

        return $info;
    }

    public function sql($where, $type = 1, $data = '')
    {
        $time = gmtime();

        if ($type == 1) {
            $sql = "SELECT goods_id FROM {pre}goods WHERE " . $where;
            $info = $this->db->getAll($sql);
            return count($info);
        } else {
            $sql="SELECT * FROM {pre}coupons WHERE (`cou_type` = 3 OR `cou_type` = 4 ) AND `cou_end_time` >$time AND (( instr(`cou_ok_user`, $_SESSION[user_rank]) ) or (`cou_ok_user`=0)) AND review_status = 3 AND ru_id=".$where;
            $info = $this->db->getAll($sql);
            if ($data == '') {
                return count($info);
            } else {
                foreach ($info as $key => $val) {
                    $info[$key]['cou_man'] = intval($val['cou_man']);
                    $info[$key]['cou_money'] = intval($val['cou_money']);
                }
                return $info;
            }
        }
    }




    /**
     * 店铺详情
     */
    public function actionShopAbout()
    {
        $ru_id = I('ru_id', '0', 'intval');
        $sql = "SELECT * FROM {pre}merchants_shop_information as a
              JOIN {pre}seller_shopinfo as b ON a.user_id=b.ru_id
              WHERE user_id=" . $ru_id;
        $data = $this->db->getRow($sql);

        $sql = "SELECT count(user_id) as a FROM {pre}collect_store WHERE ru_id=" . $data['user_id'];
        $follow = $this->db->getOne($sql);

        $info = $this->shopdata($data);
        $info['count_gaze'] = intval($follow);
        
        //二维码内容
        $url = url('', '', true, true) . '&ru_id=' . $ru_id;

        // 纠错级别：L、M、Q、H
        $errorCorrectionLevel = 'M';
        // 点的大小：1到10
        $matrixPointSize = 8;
        $file = dirname(ROOT_PATH) . '/data/attached/shop_qrcode/';
        if(!file_exists($file)){
            make_dir($file, 0777);
        }
        // 水印logo
//        $qrcode_logo = ROOT_PATH . 'resources/assets/img/shop_app_icon.png';
//        $qrcode_logo_out = $file . 'qrcode_logo.png';

        $filename = $file . 'shop_qrcode_' . $ru_id . $errorCorrectionLevel . $matrixPointSize . '.png';
        if(!file_exists($filename)) {
            $code = QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
//            // 添加水印
//            $img = new Image();
//            // 生成水印缩略图
//            $img->open($qrcode_logo)->thumb(80, 80)->save($qrcode_logo_out);
//            // 生成原图+水印
//            $img->open($filename)->water($qrcode_logo_out, 5, 100)->save($filename);
        }
        // 同步OSS数据
        if(config('shop.open_oss') == 1){
            $image_name = $this->ossMirror($filename, 'data/attached/shop_qrcode/');
        }else{
            $image_name = 'data/attached/shop_qrcode/' . basename($filename);
        }
        $qrcode_url = get_image_path($image_name);
        $info['code'] = $qrcode_url;
     
        $this->assign('info', $info);
        $this->assign('page_title', $info['shop_name']);
        $this->display();
    }

    /*
     * 处理客服QQ、旺旺数组
     */
    private function dokf($kf)
    {
        if ($kf) {
            $kf_tmp = array_filter(preg_split('/\s+/', $kf));
            $kf_tmp = explode("|", $kf_tmp[0]);
            if (!empty($kf_tmp[1])) {
                $res = $kf_tmp[1];
            } else {
                $res = "";
            }

        } else {
            $res = "";
        }
        return $res;
    }

    public function font($key)
    {
        if ($key > 4) {
            return L('height');
        } elseif ($key > 3) {
            return L('middle');
        } else {
            return L('low');
        }
    }

    public function cmt($num)
    {
        if ($num >= 4) {
            $str = 't-first';
        } elseif ($num > 3) {
            $str = 't-center';
        } else {
            $str = 't-low';
        }
        return $str;
    }

    public function boxbg($num)
    {
        if ($num >= 4) {
            $str = '';
        } elseif ($num > 3) {
            $str = 'em-p-center';
        } else {
            $str = 'em-p-low';
        }
        return $str;
    }

    /**
     * 初始化参数
     */
    private function init_params()
    {
        #需要查询的IP start
        if (!isset($_COOKIE['province'])) {
            $area_array = get_ip_area_name();

            if ($area_array['county_level'] == 2) {
                $date = array('region_id', 'parent_id', 'region_name');
                $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
                $city_info = get_table_date('region', $where, $date, 1);

                $date = array('region_id', 'region_name');
                $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);

            } elseif ($area_array['county_level'] == 1) {
                $area_name = $area_array['area_name'];

                $date = array('region_id', 'region_name');
                $where = "region_name = '$area_name'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
                $city_info = get_table_date('region', $where, $date, 1);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            }
        }
        #需要查询的IP end
        $order_area = get_user_order_area($this->user_id);
        $user_area = get_user_area_reg($this->user_id); //2014-02-25

        if ($order_area['province'] && $this->user_id > 0) {
            $this->province_id = $order_area['province'];
            $this->city_id = $order_area['city'];
            $this->district_id = $order_area['district'];
        } else {
            //省
            if ($user_area['province'] > 0) {
                $this->province_id = $user_area['province'];
                cookie('province', $user_area['province']);
                $this->region_id = get_province_id_warehouse($this->province_id);
            } else {
                $sql = "select region_name from " . $this->ecs->table('region_warehouse') . " where regionId = '" . $province_info['region_id'] . "'";
                $warehouse_name = $this->db->getOne($sql);

                $this->province_id = $province_info['region_id'];
                $cangku_name = $warehouse_name;
                $this->region_id = get_warehouse_name_id(0, $cangku_name);
            }
            //市
            if ($user_area['city'] > 0) {
                $this->city_id = $user_area['city'];
                cookie('city', $user_area['city']);
            } else {
                $this->city_id = $city_info[0]['region_id'];
            }
            //区
            if ($user_area['district'] > 0) {
                $this->district_id = $user_area['district'];
                cookie('district', $user_area['district']);
            } else {
                $this->district_id = $district_info[0]['region_id'];
            }
        }
        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;
        $child_num = get_region_child_num($this->province_id);
        if ($child_num > 0) {
            $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
        } else {
            $this->city_id = '';
        }
        $child_num = get_region_child_num($this->city_id);
        if ($child_num > 0) {
            $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
        } else {
            $this->district_id = '';
        }
        $this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
        $goods_warehouse = get_warehouse_goods_region($this->province_id); //查询用户选择的配送地址所属仓库
        if ($goods_warehouse) {
            $this->regionId = $goods_warehouse['region_id'];
            if ($_COOKIE['region_id'] && $_COOKIE['regionid']) {
                $gw = 0;
            } else {
                $gw = 1;
            }
        }
        if ($gw) {
            $this->region_id = $this->regionId;
            cookie('area_region', $this->region_id);
        }
        cookie('goodsId', $this->goods_id);
        $sellerInfo = get_seller_info_area();
        if (empty($this->province_id)) {
            $this->province_id = $sellerInfo['province'];
            $this->city_id = $sellerInfo['city'];
            $this->district_id = 0;
            cookie('province', $this->province_id);
            cookie('city', $this->city_id);
            cookie('district', $this->district_id);
            $this->region_id = get_warehouse_goods_region($this->province_id);
        }
        //ecmoban模板堂 --zhuo end 仓库
        $this->area_info = get_area_info($this->province_id);
    }

}
