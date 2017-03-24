<?php
defined('IN_ECTOUCH') or die('Deny Access');

use app\classes\Integrate;

class ecshop extends Integrate
{
    private $is_ecshop = 1;

    /**
     * 构造方法
     * @param $cfg
     */
    public function __construct($cfg)
    {
        parent::__construct(array());
        $this->user_table = 'users';
        $this->field_id = 'user_id';
        $this->ec_salt = 'ec_salt';
        $this->field_name = 'user_name';
        $this->field_pass = 'password';
        $this->field_email = 'email';
        $this->field_gender = 'sex';
        $this->field_bday = 'birthday';
        $this->field_reg_date = 'reg_time';
        $this->need_sync = false;
        $this->is_ecshop = 1;
    }


    /**
     * 检查指定用户是否存在及密码是否正确(重载基类check_user函数，支持zc加密方法)
     * @param string $username
     * @param null $password
     * @return int
     */
    public function check_user($username, $password = null)
    {
        if ($this->charset != 'UTF8') {
            $post_username = ecs_iconv('UTF8', $this->charset, $username);
        } else {
            $post_username = $username;
        }

        if ($password === null) {
            $sql = "SELECT " . $this->field_id .
                " FROM " . $this->table($this->user_table) .
                " WHERE " . $this->field_name . "='" . $post_username . "'";

            return $this->db->getOne($sql);
        } else {
            $sql = "SELECT user_id, password, salt,ec_salt " .
                " FROM " . $this->table($this->user_table) .
                " WHERE user_name='$post_username'";
            $row = $this->db->getRow($sql);
            $ec_salt = $row['ec_salt'];
            if (empty($row)) {
                return 0;
            }

            if (empty($row['salt'])) {
                if ($row['password'] != $this->compile_password(array('password' => $password, 'ec_salt' => $ec_salt))) {
                    return 0;
                } else {
                    if (empty($ec_salt)) {
                        $ec_salt = rand(1, 9999);
                        $new_password = md5(md5($password) . $ec_salt);
                        $sql = "UPDATE " . $this->table($this->user_table) . "SET password= '" . $new_password . "',ec_salt='" . $ec_salt . "'" .
                            " WHERE user_name='$post_username'";
                        $this->db->query($sql);

                    }
                    return $row['user_id'];
                }
            } else {
                /* 如果salt存在，使用salt方式加密验证，验证通过洗白用户密码 */
                $encrypt_type = substr($row['salt'], 0, 1);
                $encrypt_salt = substr($row['salt'], 1);

                /* 计算加密后密码 */
                $encrypt_password = '';
                switch ($encrypt_type) {
                    case ENCRYPT_ZC :
                        $encrypt_password = md5($encrypt_salt . $password);
                        break;
                    /* 如果还有其他加密方式添加到这里  */
                    //case other :
                    //  ----------------------------------
                    //  break;
                    case ENCRYPT_UC :
                        $encrypt_password = md5(md5($password) . $encrypt_salt);
                        break;

                    default:
                        $encrypt_password = '';

                }

                if ($row['password'] != $encrypt_password) {
                    return 0;
                }

                $sql = "UPDATE " . $this->table($this->user_table) .
                    " SET password = '" . $this->compile_password(array('password' => $password)) . "', salt=''" .
                    " WHERE user_id = '$row[user_id]'";
                $this->db->query($sql);

                return $row['user_id'];
            }
        }
    }
}
