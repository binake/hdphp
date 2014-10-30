<?php

// .-----------------------------------------------------------------------------------
// |  Software: [HDPHP framework]
// |   Version: 2013.01
// |      Site: http://www.hdphp.com
// |-----------------------------------------------------------------------------------
// |    Author: 向军 <houdunwangxj@gmail.com>
// | Copyright (c) 2012-2013, http://houdunwang.com. All Rights Reserved.
// |-----------------------------------------------------------------------------------
// |   License: http://www.apache.org/licenses/LICENSE-2.0
// '-----------------------------------------------------------------------------------

/**
 * Mysql数据库基类
 * @package     Db
 * @subpackage  Driver
 * @author      后盾向军 <houdunwangxj@gmail.com>
 */
abstract class Db implements DbInterface
{

    protected $table = NULL; //表名
    public $fieldData; //字段数组
    public $lastQuery; //最后发送的查询结果集
    public $pri = null; //默认表主键
    public $opt = array(); //SQL 操作
    public $opt_old = array(); //上次操作参数
    public $lastSql; //最后发送的SQL
    public $error = NULL; //错误信息
    protected $cacheTime = NULL; //查询操作缓存时间单位秒
    protected $dbPrefix; //表前缀

    /**
     * 将eq等替换为标准的SQL语法
     * @var  array
     */
    protected $condition = array(
        "eq" => " = ", "neq" => " <> ",
        "gt" => " > ", "egt" => " >= ",
        "lt" => " < ", "elt" => " <= ",
    );

    /**
     * 数据库连接
     * 根据配置文件获得数据库连接对象
     * @param string $table
     * @return Object   连接对象
     */
    public function connect($table)
    {
        //通过数据驱动如MYSQLI连接数据库
        if ($this->connectDb()) {
            if ($table) {
                $this->dbPrefix = C("DB_PREFIX");
                $this->table($table);
                $this->fieldData = $this->opt['fieldData'];
                $this->table = $this->opt['table'];
                $this->pri = $this->opt['pri'];
            }
            //初始化关联参数
            $this->optInit();
            return $this->link;
        } else {
            halt("数据库连接出错了请检查数据库配置");
        }
    }

    /**
     * 初始化表字段与主键及发送字符集
     * @param string $table 表名
     * @return array
     */
    public function table($table)
    {
        $this->optInit();
        if ($table) {
            $data = $this->getTableFields($table);
            $this->opt['table'] = $table;
            $this->opt['pri'] = $data['pri'];
            $this->opt['fieldData'] = $data['fieldData'];
            return true;
        }
        return false;
    }

    /**
     * 获得表结构及主键
     * 查询表结构获得所有字段信息，用于字段缓存
     * @access private
     * @param string $table
     * @return array
     */
    public function getTableFields($table)
    {
        $name = C('DB_DATABASE') . '.' . $table;
        //字段缓存
        if (!DEBUG && F($name, false, APP_TABLE_PATH)) {
            $fieldData = F($name, false, APP_TABLE_PATH);
        } else {
            $sql = "show columns from `$table`";
            if (!$result = $this->query($sql)) {
                halt("表{$table}不存在");
            }
            $fieldData = array();
            foreach ($result as $res) {
                $f ['field'] = $res ['Field'];
                $f ['type'] = $res ['Type'];
                $f ['null'] = $res ['Null'];
                $f ['field'] = $res ['Field'];
                $f ['key'] = ($res ['Key'] == "PRI" && $res['Extra']) || $res ['Key'] == "PRI";
                $f ['default'] = $res ['Default'];
                $f ['extra'] = $res ['Extra'];
                $fieldData [$res ['Field']] = $f;
            }
            DEBUG && F($name, $fieldData, APP_TABLE_PATH);
        }
        $pri = '';
        foreach ($fieldData as $v) {
            if ($v['key']) $pri = $v['field'];
        }
        return array('table' => $table, 'pri' => $pri, 'fieldData' => $fieldData);
    }

    /**
     * 查询操作归位
     * @access public
     * @return void
     */
    public function optInit()
    {
        $this->opt_old = $this->opt;
        $this->cacheTime = NULL; //SELECT查询缓存时间
        $this->error = NULL;
        $opt = array(
            'table' => $this->table,
            'pri' => $this->pri,
            'field' => '',
            'fieldData' => $this->fieldData,
            'where' => '',
            'like' => '',
            'group' => '',
            'having' => '',
            'order' => '',
            'limit' => '',
            'in' => '',
            'cache' => ''
        );
        return $this->opt = array_merge($this->opt, $opt);
    }

    /**
     * 查找记录
     * @param string $where
     * @return array|string
     */
    public function select($where)
    {
        //设置条件
        $where && $this->where($where);
        //去除WHERE尾部AND OR
        $this->parseWhereLogic($this->opt['where']);
        //设置字段
        if (empty($this->opt['field'])) {
            $this->opt['field'] = ' * ';
        }
        $sql = 'SELECT ' . $this->opt['field'] . ' FROM ' . $this->opt['table'] .
            $this->opt['where'] . $this->opt['group'] . $this->opt['having'] .
            $this->opt['order'] . $this->opt['limit'];
        return $this->query($sql);
    }

    /**
     * SQL中的REPLACE方法，如果存在与插入记录相同的主键或unique字段进行更新操作
     * @param array $data
     * @param string $type
     * @return array|bool
     */
    public function insert($data, $type = 'INSERT')
    {
        $value = $this->formatField($data);
        empty($value) && halt("没有任何数据用于 INSERT");
        $sql = $type . " INTO " . $this->opt['table'] . "(" . implode(',', $value['fields']) . ")" .
            "VALUES (" . implode(',', $value['values']) . ")";
        return $this->exe($sql);
    }

    /**
     * 更新数据
     * @access      public
     * @param  mixed $data
     * @return mixed
     */
    public function update($data)
    {
        //验证条件
        if (empty($this->opt['where'])) {
            if (isset($data[$this->opt['pri']])) {
                $this->opt['where'] = " WHERE " . $this->opt['pri'] . " = " . intval($data[$this->opt['pri']]);
            } else {
                halt('UPDATE更新语句必须输入条件');
            }
        }
        $data = $this->formatField($data);
        empty($data) && halt("没有任何数据用于 UPDATE");
        $sql = "UPDATE " . $this->opt['table'] . " SET ";
        foreach ($data['fields'] as $n => $field) {
            $sql .= $field . "=" . $data['values'][$n] . ',';
        }
        //移除WHERE AND OR
        $this->parseWhereLogic($this->opt['where']);
        $sql = trim($sql, ',') . $this->opt['where'] . $this->opt['limit'];
        return $this->exe($sql);
    }

    /**
     * 删除方法
     * @param $data
     * @return bool
     */
    public function delete($data = array())
    {
        $data && $this->where($data);
        empty($this->opt['where']) && halt('DELETE删除语句必须输入条件');
        //移除WHERE AND OR
        $this->parseWhereLogic($this->opt['where']);
        $sql = "DELETE FROM " . $this->opt['table'] . $this->opt['where'] . $this->opt['limit'];
        return $this->exe($sql);
    }

    /**
     * 格式化SQL操作参数 字段加上标识符  值进行转义处理
     * @param array $vars 处理的数据
     * @return array
     */
    public function formatField($vars)
    {
        //格式化的数据
        $data = array();
        foreach ($vars as $k => $v) {
            //校验字段与数据
            if ($this->isField($k)) {
                $data['fields'][] = "`" . $k . "`";
                $v = $this->escapeString($v);
                $data['values'][] = is_numeric($v) ? $v : "\"$v\"";
            }
        }
        return $data;
    }

    //移除where结尾的OR AND
    private function parseWhereLogic(&$where, $action = 'remove')
    {
        if ($action == 'remove') {
            $where = preg_replace('/(XOR|OR|AND)\s*$/i', '', $where);
        } else {
            $where = preg_match('/(XOR|OR|AND)\s*$/i', $where) ? $where : $where . ' AND ';
        }
    }

    /**
     * SQL查询条件
     * @param mixed $opt 链式操作中的WHERE参数
     * @return string
     */
    public function where($opt)
    {
        $where = '';
        if (empty($opt)) return;
        if (is_numeric($opt)) {
            $where .= ' ' . $this->opt['pri'] . "=$opt ";
            $this->parseWhereLogic($where);
        } else if (is_string($opt)) {
            $where .= " $opt ";
            $this->parseWhereLogic($where, 'add');
        } else if (is_array($opt)) {
            foreach ($opt as $key => $set) {
                if ($key[0] == '_') {
                    switch (strtolower($key)) {
                        case '_query':
                            parse_str($set, $q);
                            $this->where($q);
                            break;
                        case '_string':
                            $where .= $set;
                            $this->parseWhereLogic($where, 'add');
                            break;
                    }
                } else if (is_numeric($key)) { //参数为字符串
                    $where .= $set;
                    $this->parseWhereLogic($where, 'add');
                } else if ($this->isField($key)) { //参数为数组
                    if (!is_array($set)) {
                        $logic = isset($opt['_logic']) ? " {$opt['_logic']} " : ' AND '; //连接方式
                        $where .= " $key " . "='$set' " . $logic;
                    } else {
                        $logic = isset($opt['_logic']) ? " {$opt['_logic']} " : ' AND '; //连接方式
                        $logic = isset($set['_logic']) ? " {$set['_logic']} " : $logic; //连接方式
                        //连接方式
                        if (is_string(end($set)) && in_array(strtoupper(end($set)), array('AND', 'OR', 'XOR'))) {
                            $logic = ' ' . current($set) . ' ';
                        }
                        reset($set); //数组指针回位
                        //如: $map['username'] = array(array('gt', 3), array('lt', 5), 'AND');
                        if (is_array(current($set))) {
                            foreach ($set as $exp) {
                                if (is_array($exp)) {
                                    $exp['_logic'] = strtoupper($logic);
                                    $t[$key] = $exp;
                                    $this->where($t);
                                }
                            }
                        } else {
                            $option = !is_array($set[1]) ? explode(',', $set[1]) : $set[1]; //参数
                            switch (strtoupper($set[0])) {
                                case 'IN':
                                    $value = '';
                                    foreach ($option as $v) {
                                        $value .= is_numeric($v) ? $v . "," : "'" . $v . "',";
                                    }
                                    $value = trim($value, ',');
                                    $where .= " $key " . " IN ($value) $logic";
                                    break;
                                case 'NOTIN':
                                    $value = '';
                                    foreach ($option as $v) {
                                        $value .= is_numeric($v) ? $v . "," : "'" . $v . "',";
                                    }
                                    $value = trim($value, ',');
                                    $where .= " $key " . " NOT IN ($value) $logic";
                                    break;
                                case 'BETWEEN':
                                    $where .= " $key " . " BETWEEN " . $option[0] . ' AND ' . $option[1] . $logic;
                                    break;
                                case 'NOTBETWEEN':
                                    $where .= " $key " . " NOT BETWEEN " . $option[0] . ' AND ' . $option[1] . $logic;
                                    break;
                                case 'LIKE':
                                    foreach ($option as $v) {
                                        $where .= " $key " . " LIKE '$v' " . $logic;
                                    }
                                    break;
                                case 'NOLIKE':
                                    foreach ($option as $v) {
                                        $where .= " $key " . " NO LIKE '$v'" . $logic;
                                    }
                                    break;
                                case 'EQ':
                                    $where .= " $key " . '=' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'NEQ':
                                    $where .= " $key " . '<>' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'GT':
                                    $where .= " $key " . '>' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'EGT':
                                    $where .= " $key " . '>=' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'LT':
                                    $where .= " $key " . '<' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'ELT':
                                    $where .= " $key " . '<=' . (is_numeric($set[1]) ? $set[1] : "'{$set[1]}'") . $logic;
                                    break;
                                case 'EXP':
                                    $where .= " $key " . $set[1] . $logic;
                                    break;
                            }
                        }
                    }
                }
            }
        }
        if (empty($this->opt['where']) && !empty($where)) {
            $this->opt['where'] = ' WHERE ';
        }
        $this->opt['where'] .= $where;
    }

    /**
     * 字段集
     * @param mixed $data
     * @param boolean $exclude 排除字段
     * @return mixed
     */
    public function field($data, $exclude = false)
    {
        //字符串时转为数组
        if (is_string($data)) {
            $data = explode(",", $data);
        }
        //排除字段
        if ($exclude) {
            $_data = $data;
            $data = array_keys($this->opt['fieldData']);
            foreach ($_data as $name => $field) {
                if (in_array($field, $data)) {
                    unset($data[$name]);
                }
            }
        }
        $field = empty($this->opt['field']) ? '' : $this->opt['field'] . ',';
        foreach ($data as $name => $d) {
            if (is_string($name)) {
                $field .= $name . ' AS ' . $d . ",";
            } else {
                $field .= $d . ',';
            }
        }
        return $this->opt['field'] = substr($field, 0, -1);
    }

    /**
     * 验证字段是否全法
     * @param $field 字段名
     * @return bool
     */
    protected function isField($field)
    {
        return is_string($field) && isset($this->opt['fieldData'][$field]);
    }

    /**
     * limit 操作
     * @param mixed $data
     * @return type
     */
    public function limit($data)
    {
        $this->opt['limit'] = " LIMIT $data ";
    }

    /**
     * SQL 排序 ORDER
     * @param type $data
     */
    public function order($data)
    {
        $this->opt['order'] = " ORDER BY $data ";
    }

    /**
     * 分组操作
     * @param type $opt
     */
    public function group($opt)
    {
        $this->opt['group'] = " GROUP BY $opt";
    }

    /**
     * 分组条件having
     * @param type $opt
     */
    public function having($opt)
    {
        $this->opt['having'] = " HAVING $opt";
    }

    /**
     * 设置查询缓存时间
     * @param $time
     */
    public function cache($time = -1)
    {
        $this->cacheTime = is_numeric($time) ? $time : -1;
    }

    /**
     * 判断表名是否存在
     * @param $table 表名
     * @param bool $full 是否加表前缀
     * @return bool
     */
    public function isTable($table, $full = true)
    {
        //不为全表名时加表前缀
        if (!$full) $table = C('DB_PREFIX') . $table;
        $info = $this->query('show tables');
        foreach ($info as $n => $d) {
            if ($table == current($d)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获得最后一条SQL
     * @return type
     */
    public function getLastSql()
    {
        return $this->lastSql;
    }

    /**
     * 获得所有SQL语句
     * @return type
     */
    public function getAllSql()
    {
        return Debug::$sqlExeArr;
    }

    /**
     * 将查询SQL压入调试数组 show语句不保存
     * @param void
     */
    protected function debug($sql)
    {
        $this->lastSql = $sql;
        if (DEBUG && !preg_match("/^\s*show/i", $sql)) {
            Debug::$sqlExeArr[] = $sql;
        }
    }

    //错误处理
    protected function error($error)
    {
        $this->error = $error;
        if (DEBUG) {
            halt($this->error);
        } else {
            log_write($this->error);
        }
    }

    /**
     * 获得所有表信息
     * @return  array
     */
    public function getAllTableInfo()
    {
        $info = $this->query("SHOW TABLE STATUS FROM " . C("DB_DATABASE"));
        $arr = array();
        $arr['total_size'] = 0; //总大小
        $arr['total_row'] = 0; //总条数
        foreach ($info as $k => $t) {
            $arr['table'][$t['Name']]['tablename'] = $t['Name'];
            $arr['table'][$t['Name']]['engine'] = $t['Engine'];
            $arr['table'][$t['Name']]['rows'] = $t['Rows'];
            $arr['table'][$t['Name']]['collation'] = $t['Collation'];
            $charset = $arr['table'][$t['Name']]['collation'] = $t['Collation'];
            $charset = explode("_", $charset);
            $arr['table'][$t['Name']]['charset'] = $charset[0];
            $arr['table'][$t['Name']]['datafree'] = $t['Data_free'];
            $arr['table'][$t['Name']]['size'] = $t['Data_free'] + $t['Data_length'];
            $data = $this->getTableFields($t['Name']);
            $arr['table'][$t['Name']]['field'] = $data['fieldData'];
            $arr['table'][$t['Name']]['primarykey'] = $data['pri'];
            $arr['table'][$t['Name']]['autoincrement'] = $t['Auto_increment'] ? $t['Auto_increment'] : '';
            $arr['total_size'] += $arr['table'][$t['Name']]['size'];
            $arr['total_row']++;
        }
        return $arr;
    }

    /**
     * 获得数据库或表大小
     */
    public function getSize($table)
    {
        $table = C('DB_PREFIX') . $table;
        $sql = "show table status from " . C("DB_DATABASE");
        $row = $this->query($sql);
        $size = 0;
        foreach ($row as $v) {
            if (in_array(strtolower($v['Name']), $table)) {
                $size = $v['Data_length'] + $v['Index_length'];
            }
        }
        return $size;
    }
}