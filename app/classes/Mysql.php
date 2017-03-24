<?php
namespace app\classes;

use Think\Model;

class Mysql extends Model
{

    public function errno()
    {
        return mysql_errno();
    }

    /**
     * SQL查询
     * @access public
     * @param string $sql  SQL指令
     * @param mixed $parse  是否需要解析SQL
     * @return mixed
     */
    public function query($sql,$parse=false) {
        if (stripos(trim($sql), 'show') === 0 || stripos(trim($sql), 'select') === 0 || stripos(trim($sql), 'desc') === 0) {
            return parent::query($sql, $parse);
        } else {
            return parent::execute($sql, $parse); //不是查询条件，直接执行
        }
    }

    /**
     * 指定当前的数据表
     * @access public
     * @param mixed $table
     * @return Model
     */
    public function table($table) {
        $this->options['table'] = $this->tablePrefix . $table;
        return $this;
    }

    /* 仿真 Adodb 函数 */
    public function selectLimit($sql, $num, $start = 0)
    {
        if ($start == 0) {
            $sql .= ' LIMIT ' . $num;
        } else {
            $sql .= ' LIMIT ' . $start . ', ' . $num;
        }
        return $this->query($sql);
    }

    public function getOne($sql, $limited = false)
    {
        if ($limited == true) {
            $sql = trim($sql . ' LIMIT 1');
        }

        $res = $this->query($sql);
        if ($res !== false) {
            $row = isset($res[0]) ? $res[0] : array();
            if ($row !== false) {
                return reset($row);
            } else {
                return '';
            }
        } else {
            return array();
        }
    }

    public function getAll($sql)
    {
        $res = $this->query($sql);
        if ($res !== false) {
            $arr = array();
            foreach ($res as $row) {
                $arr[] = $row;
            }
            return $arr;
        } else {
            return array();
        }
    }

    public function getRow($sql, $limited = false)
    {
        if ($limited == true) {
            $sql = trim($sql . ' LIMIT 1');
        }

        $res = $this->query($sql);
        return isset($res[0]) ? $res[0] : array();
    }

    public function getCol($sql)
    {
        $res = $this->query($sql);
        if ($res !== false) {
            $arr = array();
            foreach ($res as $row) {
                $arr[] = current($row);
            }

            return $arr;
        } else {
            return array();
        }
    }

    public function autoReplace($table, $field_values, $update_values, $where = '')
    {
        $field_descs = $this->getAll('DESC ' . $table);

        $primary_keys = array();
        foreach ($field_descs AS $value)
        {
            $field_names[] = $value['Field'];
            if ($value['Key'] == 'PRI')
            {
                $primary_keys[] = $value['Field'];
            }
        }

        $fields = $values = array();
        foreach ($field_names AS $value)
        {
            if (array_key_exists($value, $field_values) == true)
            {
                $fields[] = $value;
                $values[] = "'" . $field_values[$value] . "'";
            }
        }

        $sets = array();
        foreach ($update_values AS $key => $value)
        {
            if (array_key_exists($key, $field_values) == true)
            {
                if (is_int($value) || is_float($value))
                {
                    $sets[] = $key . ' = ' . $key . ' + ' . $value;
                }
                else
                {
                    $sets[] = $key . " = '" . $value . "'";
                }
            }
        }

        $sql = '';
        if (empty($primary_keys))
        {
            if (!empty($fields))
            {
                $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
            }
        }
        else
        {            
            if (empty($where))
            {
                $where = array();
                foreach ($primary_keys AS $value)
                {
                    if (is_numeric($value))
                    {
                        $where[] = $value . ' = ' . $field_values[$value];
                    }
                    else
                    {
                        $where[] = $value . " = '" . $field_values[$value] . "'";
                    }
                }
                $where = implode(' AND ', $where);
            }

            if ($where && (!empty($sets) || !empty($fields)))
            {
                if (intval($this->getOne("SELECT COUNT(*) FROM $table WHERE $where")) > 0)
                {
                    if (!empty($sets))
                    {
                        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
                    }
                }
                else
                {
                    if (!empty($fields))
                    {
                        $sql = 'REPLACE INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
                    }
                }
            }
        }

        if ($sql)
        {
            return $this->query($sql);
        }
        else
        {
            return false;
        }
    }

    public function autoExecute($table, $field_values, $mode = 'INSERT', $where = '')
    {
        $field_names = $this->getCol('DESC ' . $table);

        $sql = '';
        if ($mode == 'INSERT') {
            $fields = $values = array();
            foreach ($field_names AS $value) {
                if (array_key_exists($value, $field_values) == true) {
                    $fields[] = $value;
                    $values[] = "'" . $field_values[$value] . "'";
                }
            }

            if (!empty($fields)) {
                $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
            }
        } else {
            $sets = array();
            foreach ($field_names AS $value) {
                if (array_key_exists($value, $field_values) == true) {
                    $sets[] = $value . " = '" . $field_values[$value] . "'";
                }
            }

            if (!empty($sets)) {
                $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
            }
        }

        if ($sql) {
            return $this->query($sql);
        } else {
            return false;
        }
    }

    /**
     * 过滤表字段
     * @param type $table
     * @param type $data
     * @return type
     */
    public function filter_field($table, $data) {
        $field = $this->table($table)->getDbFields();
        $res = array();
        foreach ($field as $field_name) {
            if (array_key_exists($field_name, $data) == true) {
                $res[$field_name] = $data[$field_name];
            }
        }
        return $res;
    }

}