<?php
namespace app\http\affiche\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {

    public function __construct()
    {
        parent::__construct();
    }

    public function actionIndex()
    {
        $ad_id = intval(I('get.ad_id'));
        if (empty($ad_id)) {
            $this->redirect('/');
        }
        $act = ! empty($_GET['act']) ? I('get.act') : '';
        if ($act == 'js') {
            /* 编码转换 */
            if (empty($_GET['charset'])) {
                $_GET['charset'] = 'UTF8';
            }
            header('Content-type: application/x-javascript; charset=' . ($_GET['charset'] == 'UTF8' ? 'utf-8' : $_GET['charset']));

            $url = __URL__;
            $str = "";

            /* 取得广告的信息 */
            $time=gmtime();
            $sql = 'SELECT ad.ad_id, ad.ad_name, ad.ad_link, ad.ad_code FROM {pre}touch_ad AS ad LEFT JOIN {pre}touch_ad_position AS p ON ad.position_id = p.position_id WHERE ad.ad_id = '.$ad_id.' and  '.$time.' >= ad.start_time and  '.$time.' <= ad.end_time ';

            $ad_info = $this->db->query($sql);
            $ad_info = $ad_info[0];

            if (! empty($ad_info)) {
                /* 转换编码 */
                if ($_GET['charset'] != 'UTF8') {
                    $ad_info['ad_name'] = ecs_iconv('UTF8', $_GET['charset'], $ad_info['ad_name']);
                    $ad_info['ad_code'] = ecs_iconv('UTF8', $_GET['charset'], $ad_info['ad_code']);
                }

                /* 初始化广告的类型和来源 */
                $_GET['type'] = ! empty($_GET['type']) ? intval($_GET['type']) : 0;
                $_GET['from'] = ! empty($_GET['from']) ? urlencode($_GET['from']) : '';

                $str = '';
                switch ($_GET['type']) {
                    case '0':
                        /* 图片广告 */
                        $src = (strpos($ad_info['ad_code'], 'http://') === false && strpos($ad_info['ad_code'], 'https://') === false) ? $url . "/$ad_info[ad_code]" : $ad_info['ad_code'];
                        $str = '<a href="' . $url . url('affiche/index', array(
                                'ad_id' => $ad_info['ad_id']
                            )) . '&from=' . $_GET['from'] . '&uri=' . urlencode($ad_info['ad_link']) . '" target="_blank">' . '<img src="' . $src . '" border="0" alt="' . $ad_info['ad_name'] . '" /></a>';
                        break;

                    case '1':
                        /* Falsh广告 */
                        $src = (strpos($ad_info['ad_code'], 'http://') === false && strpos($ad_info['ad_code'], 'https://') === false) ? $url . '/' . $ad_info['ad_code'] : $ad_info['ad_code'];
                        $str = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0"> <param name="movie" value="' . $src . '"><param name="quality" value="high"><embed src="' . $src . '" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash"></embed></object>';
                        break;

                    case '2':
                        /* 代码广告 */
                        $str = $ad_info['ad_code'];
                        break;

                    case 3:
                        /* 文字广告 */
                        $str = '<a href="' . url('affiche/index', array(
                                'ad_id' => $ad_info['ad_id'],
                                'from' => $_GET['from'],
                                'uri' => urlencode($ad_info['ad_link'])
                            )) . '" target="_blank">' . nl2br(htmlspecialchars(addslashes($ad_info['ad_code']))) . '</a>';
                        break;
                }
            }
            echo "document.writeln('$str');";
        } else {
            $site_name = ! empty($_GET['from']) ? htmlspecialchars(I('get.from')) : addslashes(L('self_site'));
            /* 商品的ID */
            $goods_id = ! empty($_GET['goods_id']) ? intval(I('get.goods_id')) : 0;
            /* 存入SESSION中,购物后一起存到订单数据表里 */
            $_SESSION['from_ad'] = $ad_id;
            $_SESSION['referer'] = stripslashes($site_name);
            /* 如果是商品的站外JS */
            if ($ad_id == '-1') {
                $datezw['from_ad']= '-1';
                $datezw['referer']= $site_name;
                $count = $this->model->table('touch_adsense')
                    ->where($datezw)
                    ->count();
                if ($count > 0) {
                    $sql = "UPDATE {pre}touch_adsense SET clicks = clicks + 1 WHERE from_ad = '-1' AND referer = '" . $site_name . "'";
                } else {
                    $sql = "INSERT INTO {pre}touch_adsense (from_ad, referer, clicks) VALUES ('-1', '" . $site_name . "', '1')";
                }
                $this->model->query($sql);
                $dategd['goods_id']= $goods_id;
                $row = $this->model->table('goods')
                    ->field('goods_name')
                    ->where($dategd)
                    ->find();
                $this->redirect('goods/index/index', array('id' => $goods_id));
            } else {
                /* 更新站内广告的点击次数 */
                 $sql = "UPDATE {pre}touch_ad SET click_count=click_count+1 WHERE ad_id ='$ad_id'";
                 $this->db->query($sql);
                    $data['from_ad'] = $ad_id;
                    $data['referer'] = $site_name;
                    $count = $this->model->table('touch_adsense')
                        ->where($data)
                        ->count();
                if ($count > 0) {
                    $sql = "UPDATE {pre}touch_adsense SET clicks=clicks+1 WHERE from_ad ='$ad_id' AND referer = '$site_name'";
                } else {
                    $sql = "INSERT INTO {pre}touch_adsense (from_ad, referer, clicks) VALUES ('" . $ad_id . "', '" . $site_name . "', '1')";
                }
                $this->model->query($sql);
                $data2['ad_id']=$ad_id;
                $ad_info = $this->model->table('touch_ad')
                    ->field('*')
                    ->where($data2)
                    ->find();
                /* 跳转到广告的链接页面 */
                if (! empty($ad_info['ad_link'])) {
                    $uri = (strpos($ad_info['ad_link'], 'http://') === false && strpos($ad_info['ad_link'], 'https://') === false) ? __URL__ . urldecode($ad_info['ad_link']) : urldecode($ad_info['ad_link']);
                } else {
                    $uri = __URL__;
                }
                $uri = str_replace('&amp;', '&', $uri);
                redirect($uri);
            }
        }
    }
}