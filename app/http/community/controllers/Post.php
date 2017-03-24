<?php
namespace app\http\community\controllers;

use app\http\base\controllers\Frontend;

class Post extends Frontend
{
    private $user_id;

    public function __construct()
    {
        parent::__construct();
        $this->checkLogin();
        $files = array(
            'order',
            'clips'
        );
        $this->load_helper($files);
        $this->user_id = $_SESSION['user_id'];

        if(MODULE_NAME == 'community' && strtolower(CONTROLLER_NAME) == 'post'){
            $community = 1;
            $this->assign('community', $community);
        }
    }

    private function checkLogin()
    {
        if (!$_SESSION['user_id']) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', array('back_act' => $url)));
            exit;
        }
    }

    public function actionIndex()
    {
        $goods_id = I('goods_id');
        $order_id = I('order_id');
        $commentid = I('post.commentid');
        if($commentid){
            $this->assign('commentid', $commentid);
        }
        $title = I('title');
        $content = I('content');
        if ($goods_id) {
            $postgoods = get_goods_info($goods_id);
        }
        $this->assign('title', $title);
        $this->assign('content', $content);
        $this->assign('order_id', $order_id);
        $this->assign('postgoods', $postgoods);
        $this->assign('action', ACTION_NAME);
        $this->assign('page_title', '发帖');
        $this->display();
    }

    public function actionAddpost()
    {
        $title = I('post.title');
        $content = I('post.content');
        $commentid = I('post.commentid');
        $this->assign('title', $title);
        $this->assign('content', $content);
        $this->assign('commentid', $commentid);
        $postgoods = array();
        $sql = "SELECT order_id FROM {pre}order_info WHERE user_id=$this->user_id AND pay_status = " . PS_PAYED . " AND shipping_status = " . SS_RECEIVED . " ";
        $order_id_array = $this->db->getAll($sql);
        foreach ($order_id_array as $k => $v) {
            $goods_list = order_goods($v['order_id']);
            foreach ($goods_list as $kkk => $vvv) {
                $array = array('goods_id' => $vvv['goods_id'],'order_id' => $v['order_id']);
                array_push($postgoods, $array);
            }
        }
        $postgoods = array_unique($postgoods);
        foreach ($postgoods as $k => $v) {
            $sql = "SELECT goods_thumb FROM {pre}goods WHERE goods_id = $v[goods_id]";
            $goods_thumb = $this->db->getOne($sql);
            $postgoods[$k]['goods_thumb'] = get_image_path($goods_thumb);

        }
        $this->assign('postgoods', $postgoods);
        $this->assign('action', ACTION_NAME);
        $this->assign('page_title', '添加关联商品');
        $this->display();
    }

    public function actionChangeaddpost()
    {
        if (IS_AJAX) {
            $result = array('error' => '', 'content' => '');
            $postgoodstype = I('postgoodstype', 1, 'intval');
            if (!in_array($postgoodstype, array(1, 2, 3, 4))) {
                $result['error'] = '1';
            } else {
                $postgoods = array();
                switch ($postgoodstype) {
                    case $postgoodstype == 2: //购物车商品
                        $cart_goods = get_cart_goods('', 0);
                        if (is_array($cart_goods['goods_list'])) {
                            foreach ($cart_goods['goods_list'] as $k => $v) {
                                if (is_array($v['goods_list'])) {
                                    foreach ($v['goods_list'] as $kk => $vv) {
                                        $postgoods[] = $vv;
                                    }
                                }
                            }
                        }
                        break;
                    case $postgoodstype == 3: //收藏的商品
                        $sql = "SELECT count(rec_id) as max FROM {pre}collect_goods WHERE user_id=$this->user_id ";
                        $count = $this->db->getOne($sql);
                        $collection_goods = get_collection_goods($this->user_id, $count);
                        $postgoods = $collection_goods['goods_list'];
                        break;
                    case $postgoodstype == 4: //浏览的商品
                        $postgoods = historys();
                        break;
                    default:
                        $postgoods = array();
                        $sql = "SELECT order_id FROM {pre}order_info WHERE user_id=$this->user_id AND pay_status = " . PS_PAYED . " AND shipping_status = " . SS_RECEIVED . " ";
                        $order_id_array = $this->db->getAll($sql);
                        foreach ($order_id_array as $k => $v) {
                            $goods_list = order_goods($v['order_id']);
                            foreach ($goods_list as $kkk => $vvv) {
                                $array = array('goods_id' => $vvv['goods_id'],'order_id' => $v['order_id']);
                                array_push($postgoods, $array);
                            }
                        }
                        $postgoods = array_unique($postgoods);
                        foreach ($postgoods as $k => $v) {
                            $sql = "SELECT goods_thumb FROM {pre}goods WHERE goods_id = $v[goods_id]";
                            $goods_thumb = $this->db->getOne($sql);
                            $postgoods[$k]['goods_thumb'] = get_image_path($goods_thumb);

                        }
                        $this->assign('postgoods', $postgoods);
                        break;
                }
                $this->assign('postgoods', $postgoods);
                $result['content'] = $this->fetch('goods');
            }
            die(json_encode($result));
        }
    }

    /**
     * 发帖处理 TODO OSS图片
    */
    public function actionAddcom()
    {
        $data = I('');
        if (empty($data['goods_id'])) {
            show_message('请选择一件关联商品');
        }
        if (empty($data['commentid'])) {
            show_message('请选择帖子主题');
        }
        if (empty($data['content'])) {
            show_message('请填写帖子内容');
        }

        if($data['commentid'] == 4 && ($data['order_id'] && $data['order_id'] != 0)){
            $rank = I('comment_rank', 5, 'intval');
            $sql = "select user_id from " . $GLOBALS['ecs']->table('goods') . " where goods_id = '$data[goods_id]'";
            $ru_id = $this->model->getOne($sql);
            $status = 1 - $GLOBALS['_CFG']['comment_check'];
            $return = array(
                'comment_type' => '0',
                'id_value' => $data['goods_id'],
                'email'  => $_SESSION['email'],
                'user_name' => $_SESSION['user_name'],
                'content' => $data['content'],
                'comment_rank' => $rank,
                'comment_server' => 5,
                'comment_delivery' => 5,
                'add_time' => gmtime(),
                'ip_address' => real_ip(),
                'status' => $status,
                'parent_id' => '0',
                'user_id' => $_SESSION['user_id'],
                'single_id' => '0',
                'order_id' => $data['order_id'],
                'ru_id' => $ru_id
            );
            $cid = $this->model->table('comment')->data($return)->add();
            // 支持多张图片
            if (!empty($_FILES['pic']['name'])) {
                // 保存附件
                $result = $this->upload('data/cmt_img');

                $cmt_img = array();
                if($result['error'] <= 0){
                    $num = 0;
                    foreach ($result['url'] as $file) {
                        $cmt_img[$num]['user_id'] = $_SESSION['user_id'];
                        $cmt_img[$num]['order_id'] = $data['order_id'];
                        $cmt_img[$num]['goods_id'] = $data['goods_id'];
                        $cmt_img[$num]['comment_id'] = $cid;
                        $cmt_img[$num]['comment_img'] = $file['url'];
                        $cmt_img[$num]['img_thumb'] = $file['url'];
                        $num++;
                    }
                }
                $this->model->table('comment_img')->addAll($cmt_img);
            }
            show_message('晒单成功', '查看帖子', url('community/index/detail', array('id' => $cid,'type' => $data['commentid'])), 'success');
        }else{
            if (empty($data['title'])) {
                show_message('请填写标题');
            }
            $return = array(
                'dis_type' => $data['commentid'],
                'goods_id' => $data['goods_id'],
                'user_id'  => $_SESSION['user_id'],
                'dis_title' => $data['title'],
                'dis_text' => $data['content'],
                'user_name' => $_SESSION['user_name'],
                'add_time' => gmtime()
            );
            $dis_id = $this->model->table('discuss_circle')->data($return)->add();
        }
        show_message('发帖成功', '查看帖子', url('community/index/detail', array('id' => $dis_id,'type' => $data['commentid'])),'success');
    }

}