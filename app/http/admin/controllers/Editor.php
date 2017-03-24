<?php
namespace app\http\admin\controllers;

use app\http\base\controllers\Backend;
use app\classes\Compile;
use ectouch\Http;

class Editor extends Backend
{

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
        $this->load_helper(array('scfunction', 'scecmoban'));
    }

    /**
     * 编辑控制台
     */
    public function actionIndex()
    {
        $this->display();
    }

    /**
     * 保存模块预览配置
     * post: /index.php?m=admin&c=editor&a=preview
     * param:
     * return:
     */
    public function actionPreview()
    {
        $data = input('post.data');
        if (!empty($data)) {
            $data = $this->transform($data);
            Compile::setModule('preview', $data);
            $this->response(array('error' => 0, 'data' => $data));
        }
        $this->response(array('error' => 1, 'msg' => 'fail'));
    }

    /**
     * 保存模块配置
     * post: /index.php?m=admin&c=editor&a=save
     * param:
     * return:
     */
    public function actionSave()
    {
        $data = input('post.data');
        if (!empty($data)) {
            $data = $this->transform($data);
            Compile::setModule('index', $data);
            $this->response(array('error' => 0, 'data' => $data));
        }
        $this->response(array('error' => 1, 'msg' => 'fail'));
    }

    /**
     * 清除已有配置
     * post: /index.php?m=admin&c=editor&a=clean
     */
    public function actionClean()
    {
        if (Compile::cleanModule()) {
            $this->response(array('error' => 0, 'msg' => 'success'));
        }
        $this->response(array('error' => 1, 'msg' => 'fail'));
    }

    /**
     * 返回图库列表
     * post: /index.php?m=admin&c=editor&a=picture
     * param:
     * return:
     */
    public function actionPicture()
    {
        $thumb = input('post.thumb');
        $page = input('post.page', 1);
        $condition = array(
            'ru_id' => 0,
            'album_id' => 99
        );
        $list = $this->db->table('pic_album')->where($condition)->order('pic_id desc')->limit(15)->page($page)->select();
        $res = array();
        foreach ($list as $key => $vo) {
            $res[$key]['id'] = $vo['pic_id'];
            $res[$key]['desc'] = $vo['pic_name'];
            $res[$key]['img'] = get_image_path($vo['pic_file']);
            $res[$key]['isSelect'] = false;
        }
        if (empty($res)) {
            $this->response(array('error' => 1, 'msg' => 'fail'));
        } else {
            $total = $this->db->table('pic_album')->where($condition)->count();
            $this->response(array('error' => 0, 'total' => $total, 'data' => $res));
        }
    }

    /**
     * 图片删除
     * post: /index.php?m=admin&c=editor&a=removepicture
     * param:
     * return:
     */
    public function actionRemovePicture()
    {
        $condition = array(
            'ru_id' => 0,
            'pic_id' => input('pic_id')
        );
        $picture = $this->db->table('pic_album')->where($condition)->find();
        if (empty($picture)) {
            $this->response(array('error' => 1, 'msg' => 'fail'));
        }
        $picturePath = dirname(ROOT_PATH) . '/' . $picture['pic_file'];
        if (is_file($picturePath)) {
            $this->fs->remove($picturePath);
            $this->db->table('pic_album')->where($condition)->delete();
            $this->response(array('error' => 0, 'msg' => 'success'));
        }
        $this->response(array('error' => 1, 'msg' => 'not found'));
    }

    /**
     * 上传图片
     * post: /index.php?m=admin&c=editor&a=upload
     * param:
     * return:
     */
    public function actionUpload()
    {
        $res = $this->upload('data/gallery_album/original_img/');
        if ($res['error'] === 0) {
            // 建立相册
            $condition = array(
                'album_id' => 99
            );
            $album = $this->db->table('gallery_album')->where($condition)->find();
            if (empty($album)) {
                $data = array(
                    'album_id' => 99,
                    'ru_id' => 0,
                    'album_mame' => '手机端可视化相册',
                    'sort_order' => 50,
                    'add_time' => gmtime()
                );
                $this->db->table('gallery_album')->add($data);
            }

            // 保存图片到数据库
            $upinfo = $res['url']['file'];
            $data = array(
                'pic_name' => $upinfo['name'],
                'album_id' => 99,
                'pic_file' => $upinfo['url'],
                'pic_thumb' => '',
                'pic_size' => $upinfo['size'],
                'pic_spec' => $upinfo['name'],
                'ru_id' => 0,
                'add_time' => gmtime(),
            );
            $this->db->table('pic_album')->add($data);
        }
    }

    /**
     * 翻译POST数据类型
     * @param array $data
     * @return array
     */
    private function transform($data = array())
    {
        if (!empty($data)) {
            foreach ($data as $key => $vo) {
                if (is_array($vo)) {
                    $data[$key] = $this->transform($vo);
                } else {
                    if ($vo === 'true') {
                        $data[$key] = true;
                    }
                    if ($vo === 'false' || $key === 'setting') {
                        $data[$key] = false;
                    }
                }
            }
            return $data;
        }
    }
}

           