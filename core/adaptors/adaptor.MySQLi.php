
<?php
/******************************************************************************
 *
 * Codezilla PHP Framework
 * Author  : Nathan O'Brennan
 * Email   : nathan@codezilla.xyz
 * Date    : Sun 22 Mar 2020 02:12:11 AM CDT
 * Website : https://codezilla.xyz
 * Version : 1.0
 *
******************************************************************************/
defined('BASEPATH') OR exit('No direct script access allowed');

class MySQLi_Adaptor
{
    public   $debug = false;
    public   $connect_errno = false;

    public     $db_host;
    public     $db_name;
    public     $db_prefix;
    public     $db_user;
    public     $db_pass;
    public     $db_port = '3066';
    public     $last_query;
    public     $mysqli;

    /******************************************************************************
     *
     * function __construct($db_host, $db_name, $db_user, $db_pass)
     * @param string db_host the ip address or fqdn of the host to connect to
     * @param string db_name the name of the database to use
     * @param string db_user the user to connect as
     * @param string db_pass the password for the user
     *
     * If no connection information is passed then it will attempt to connect via
     * the defined parameters in the Config.php file. If you want to utilize
     * multiple database object, or maintain a connection to another server
     * then you must create a new object and provide these parameters.
     *
    ******************************************************************************/
    public function __construct($params = null)
    {
        log_message('debug', __CLASS__, 'Database Adaptor forced DEBUG enabled', $this->debug);

        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }

        if (isset($debug)) {
            if ($this->debug === false)
                ($debug) ? $this->debug = true : $this->debug = false;
        }

        if ($this->active()) {
            return true;
        }else {
            log_message('fatal', __CLASS__, 'failed to connect to the requested database.', $this->debug);
            return false;
        }
        log_message('debug', __CLASS__, 'Database Adaptor Class Instantiated', $this->debug);
    }

    public function initial_config()
    {
        global $code;
        $mysql_install_script = file_get_contents(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'codezilla_mysql.sql');
        $mysql_install_script = str_replace('{DBPREFIX}', $code->environment->dbprefix, $mysql_install_script);
        if ($this->mysqli->multi_query($mysql_install_script))
            return true;
        return false;
    }

    public function active()
    {
        if ($this->connect_errno) {
            return $this->connect_errno;
        }
        if (is_object($this->mysqli)) {
            return true;
        }else {
            // try to connect
            if ($this->_connect()) {
                return true;
            }
        }
        return false;
    }

    public function close()
    {
        if ($this->active()) {
            $this->mysqli->close();
        }
    }

    public function _connect()
    {
        log_message('debug', __CLASS__, '_connect()', $this->debug);
        $this->mysqli = new mysqli($this->db_host.':'.$this->db_port, $this->db_user, $this->db_pass);
        if (!$this->mysqli->connect_errno) {
            log_message('debug', __CLASS__, '_connect() connected successfully', $this->debug);
            if ($this->mysqli->select_db($this->db_name)) {
                log_message('debug', __CLASS__, '_connect() database selected: '.$this->db_name, $this->debug);
                $this->mysqli->set_charset('utf8');
                return true;
            }
        }
        else {
            log_message('debug', __CLASS__, '_connect() failed', $this->debug);
            $this->connect_errno = $this->mysqli->connect_errno;
        }
        return false;
    }

    /******************************************************************************
     *
     * function delete($sql)
     * @param array $sql the array of data to delete from the database
     * A properly formatted query array with FROM and WHERE elements
     *
    ******************************************************************************/
    public function delete($sql, $debug = false)
    {
        if (is_array($sql)) {
            $s = 'DELETE ';
            $from = $this->_sanitize($sql['from']);
            $s .= "\nFROM `{$this->db_prefix}{$from}`";
            if (isset($sql['where'])) {
                if (is_array($sql['where']) && count($sql['where']) > 0) {
                    $count = 0;
                    foreach($sql['where'] as $xtable => $column) {
                        $table = $this->_sanitize($xtable);
                        foreach ($column as $xkey => $xval) {
                            ($count == 0)
                                ? $s .= "\nWHERE "
                                : $s .= "\n\tAND ";
                            $key = $this->_sanitize($xkey);
                            $s .= "`{$this->db_prefix}{$table}`.`{$key}` = ";
                            if (is_array($xval)) {
                                $xcount = 0;
                                foreach($xval as $ycolumn) {
                                    $xycolumn = $this->_sanitize($ycolumn);
                                    ($xcount == 0)
                                        ? $s .= "`{$xycolumn}`"
                                        : $s .= ".`{$xycolumn}`";
                                    $xcount++;
                                }
                            }else {
                                $val = $this->_sanitize($xval);
                                $s .= "'$val'";
                            }
                            $count++;
                        }
                        $count++;
                    }
                }
            }
            if (($this->debug) || ($debug)) {
                log_message('debug', __CLASS__, 'delete() the query: '.PHP_EOL.$s, true);
            }
            $this->last_query = $s;
            if ($res = $this->mysqli->query($s)) {
                return true;
            }
            else {
                $this->_sendFailedQuery();
            }
        }
    }

    public function execute($sql)
    {
        return $this->mysqli->query($sql);
    }

    public function getColumns($table)
    {
        if (isset($table)) {
            $table_name = $this->_sanitize($table);
            $s = "SELECT column_name FROM information_schema.columns WHERE table_name = '$table_name'";
            if ($result = $this->mysqli->query($s)) {
                $columns = array();
                while($row=$result->fetch_object()) {
                    $columns[$row->column_name] = $row->column_name;
                }
                return $columns;
            }
        }
        return false;
    }

    public function insert($array, $debug = false)
    {
        if (isset($array)) {
            if (isset($array['insert'])) {
                if (is_array($array['insert']) && count($array['insert']) > 0) {
                    $rowcount = count($array['insert']);
                    foreach($array['insert'] as $xtable => $data) {
                        $table = $this->_sanitize($xtable);
                        $s = "INSERT INTO \n\t`{$this->db_prefix}{$table}` ";
                        // get column names
                        $count = 0;
                        foreach($array['insert'][$table][0] as $xcol => $vals) {
                            $column = $this->_sanitize($xcol);
                            ($count == 0)
                                ? $s .= "(`$column`"
                                : $s .= ",`$column`";
                            $count++;
                        }
                        $s .= ")";
                        $s .= "\nVALUES ";
                        // now get the values
                        $master = 0;
                        foreach($data as $id) {
                            ($master == 0)
                                ? $s .= "\n\t"
                                : $s .= ",";
                            $count = 0;
                            foreach($id as $xval) {
                                $val = $this->_sanitize($xval);
                                ($count == 0)
                                    ? $s .= "('$val'"
                                    : $s .= ", '$val'";
                                $count++;
                            }
                            $s .= ")";
                            $master++;
                        }
                        if (($this->debug) || ($debug)) {
                            log_message('debug', __CLASS__, 'insert() the query: '.PHP_EOL.$s, true);
                        }
                        $this->last_query = $s;
                        if ($rowcount == 1) {
                            if ($res = $this->mysqli->query($s)) {
                                return $this->mysqli->insert_id;
                            }
                            else {
                                if (is_object($this->mysqli) && isset($this->mysqli->error)) {
                                    $this->_sendFailedQuery();
                                    return false;
                                }
                            }
                        }
                        elseif ($rowcount > 1) {
                            if (!$res = $this->mysqli->query($s)) {
                                $this->_sendFailedQuery();
                                return false;
                            }
                        }
                    }
                }
            }
        }
    }

    /******************************************************************************
     *
     * function select($array, $output)
     * @param array the array of data to select from the database
     * @param string $output The data returned can be either an object (default) or an array.
     * After validating the array via the Database class this method
     * will execute the actual query and return the response.
     *
     * When returning data as an ARRAY you may request an associative array (ASSOC)
     * or an indexed array (ARRAY)
     *
    ******************************************************************************/
    public function select($array, $output = 'object', $debug = false)
    {
        $output = strtolower($output);
        if (is_array($array)) {
            if (isset($array['select'])) {
                $s = 'SELECT ';
                foreach($array['select'] as $xtable => $columns) {
                    $table = $this->_sanitize($xtable);
                    foreach($columns as $column) {
                        $s .= "\n";
                        if (is_array($column)) {
                            foreach($column as $xcolumn_name => $xcolumn_special) {
                                $column_name = $this->_sanitize($xcolumn_name);
                                $column_special = $this->_sanitize($xcolumn_special);
                                $s .= "\t`{$this->db_prefix}{$table}`.`{$column_name}` AS $column_special, ";
                            }
                        }else {
                            $column_name = $this->_sanitize($column);
                            $s .= "\t`{$this->db_prefix}{$table}`.`{$column_name}`, ";
                        }
                    }
                }
            }
            $s = mb_substr(trim($s), 0, -1);
            $from = $this->_sanitize($array['from']);
            $s .= "\nFROM `{$this->db_prefix}{$from}`";
            if (isset($array['join'])) {
                foreach($array['join'] as $xtype => $tables) {
                    if ($xtype == 'left' || $xtype == 'right' || $xtype == 'inner') {
                        $type = mb_strtoupper($xtype);
                        if (is_array($tables)) {
                            foreach($tables as $xtable => $matches) {
                                $table = $this->_sanitize($xtable);
                                $s .= "\n{$type} JOIN `{$this->db_prefix}{$table}` ON ";
                                $count = 0;
                                foreach($matches as $ytable => $ycolumn) {
                                    $table = $this->_sanitize($ytable);
                                    $column = $this->_sanitize($ycolumn);
                                    if ($count == 0) {
                                        $s .= "\n\t`{$this->db_prefix}{$table}`.`{$column}` = ";
                                    }else {
                                        $s .= "`{$this->db_prefix}{$table}`.`{$column}`";
                                    }
                                    $count++;
                                }
                            }
                        }
                    }
                }
            }
            if (isset($array['where'])) {
                if (is_array($array['where']) && count($array['where']) > 0) {
                    $count = 0;
                    foreach($array['where'] as $xtable => $column) {
                        $table = $this->_sanitize($xtable);
                        foreach ($column as $xkey => $xval) {
                            if ($xkey === 'OR') {
                                if (isset($array['where'][$table][0])) {
                                    $or_value = $this->_sanitize($array['where'][$table][0]);
                                    foreach($xval as $xor_column) {
                                        $or_column = $this->_sanitize($xor_column);
                                        if ($count == 0)
                                            $s .= "\nWHERE (`{$this->db_prefix}{$table}`.`{$or_column}` = '$or_value')";
                                        else
                                            $s .= "\nOR (`{$this->db_prefix}{$table}`.`{$or_column}` = '$or_value')";
                                        $count++;
                                    }
                                }
                                break;
                            }
                            else {
                                ($count == 0)
                                    ? $s .= "\nWHERE "
                                    : $s .= "\n\tAND ";
                                $key = $this->_sanitize($xkey);
                                $s .= "`{$this->db_prefix}{$table}`.`{$key}` = ";
                                if (is_array($xval)) {
                                    $xcount = 0;
                                    foreach($xval as $ycolumn) {
                                        $xycolumn = $this->_sanitize($ycolumn);
                                        ($xcount == 0)
                                            ? $s .= "`{$xycolumn}`"
                                            : $s .= ".`{$xycolumn}`";
                                        $xcount++;
                                    }
                                }else {
                                    $val = $this->_sanitize($xval);
                                    $s .= "'$val'";
                                }
                                $count++;
                            }
                        }
                        $count++;
                    }
                }
            }
            if (isset($array['group'])) {
                if (is_array($array['group']) && count($array['group']) > 0) {
                    $s .= "\nGROUP BY ";
                    $count = 0;
                    foreach($array['group'] as $xtable => $xcolumn) {
                        $table = $this->_sanitize($xtable);
                        $column = $this->_sanitize($xcolumn);
                        ($count == 0)
                            ? $s .= "`{$this->db_prefix}{$table}`.`{$column}`"
                            : $s .= ", `{$this->db_prefix}{$table}`.`{$column}`";
                        $count++;
                    }
                }
            }
            if (isset($array['order'])) {
                if (is_array($array['order']) && count($array['order']) > 0) {
                    $s .= "\nORDER BY ";
                    $count = 0;
                    foreach($array['order'] as $xtable => $data) {
                        $table = $this->_sanitize($xtable);
                        foreach($data as $xcolumn => $type) {
                            if (($type != 'ASC') && ($type != 'DESC'))
                                log_message('debug', __CLASS__, 'Check your SQL ORDER Type: must be ASC or DESC');
                            $column = $this->_sanitize($xcolumn);
                            ($count == 0)
                                ? $s .= "`{$this->db_prefix}{$table}`.`{$column}` $type"
                                : $s .= ", `{$this->db_prefix}{$table}`.`{$column}` $type";
                            $count++;
                        }
                    }
                }
            }
            if (isset($array['limit'])) {
                if (is_numeric($array['limit'])) {
                    $limit =(int) $array['limit'];
                    $s .= "\nLIMIT {$limit}";
                }
            }
            if (($this->debug) || ($debug)) {
                log_message('debug', __CLASS__, 'fetch() the query: '.PHP_EOL.$s, true);
            }
            $this->last_query = $s;
            $this->_sendFailedQuery();
            if ($res = $this->mysqli->query($s)) {
                $data = array();
                if ($output == 'array') {
                    log_message('debug', __CLASS__, 'fetch() returning data as array', $this->debug);
                    if ($res->num_rows == 0) {
                        return false;
                    }elseif ($res->num_rows == 1) {
                        return $res->fetch_array();
                    }elseif ($res->num_rows > 1) {
                        while($row = $res->fetch_array()) {
                            $data[] = $row;
                        }
                        return $data;
                    }
                }elseif ($output == 'assoc') {
                    log_message('debug', __CLASS__, 'fetch() returning data as associative array', $this->debug);
                    if ($res->num_rows == 0) {
                        return false;
                    }elseif ($res->num_rows == 1) {
                        return $res->fetch_assoc();
                    }elseif ($res->num_rows > 1) {
                        while($row = $res->fetch_assoc()) {
                            $data[] = $row;
                        }
                        return $data;
                    }
                }else {
                    log_message('debug', __CLASS__, 'fetch() returning data as object', $this->debug);
                    if ($res->num_rows == 0) {
                        return false;
                    }elseif ($res->num_rows == 1) {
                        log_message('debug', __CLASS__, 'single row returned: '.$s, $this->debug);
                        return $res->fetch_object();
                    }elseif ($res->num_rows > 1) {
                        while($row = $res->fetch_object()) {
                            $data[] = $row;
                        }
                        return $data;
                    }
                }
            }
            else {
                $this->_sendFailedQuery();
            }
        }
    }

    /******************************************************************************
     *
     * function _sanitize($string)
     * @access public
     * @param string The string to sanitize
     *
     * Use the real_escape_string feature of MySQLi to clean this string
     * as much as possible to ensure safe usage with the database.
     *
    ******************************************************************************/
    public function _sanitize($string)
    {
        if (method_exists($this->mysqli, 'real_escape_string')) {
            return $this->mysqli->real_escape_string($string);
        }
        return mb_escape($string);
    }

    public function _sendFailedQuery()
    {
        if (isset($this->mysqli->errno)) {
            global $code;
            if (is_object($code)) {
                $error = new stdClass();
                $error->errno = $this->mysqli->errno;
                $error->error = $this->mysqli->error;
                $error->error_list = $this->mysqli->error_list;
                if (!empty($error->errno))
                    $code->sendInfo($code->support, 'MySQLi Adaptor Query Failure', '<h1>Error Information</h1><pre>'.print_r($error, true).'</pre><h2>SQL Information</h2><pre>'.print_r($this, true).'</pre>');
            }
        }
    }

    /******************************************************************************
     *
     * function update($array)
     * @param array the array of data to select from the database
     * After validating the array via the Database class this method
     * will execute the update query
     *
    ******************************************************************************/
    public function update($array, $debug = false)
    {
        if (is_array($array)) {
            if (isset($array['update'])) {
                $s = 'UPDATE ';
                if (is_array($array['update']) && count($array['update']) > 0) {
                    $count = 0;
                    foreach($array['update'] as $xtable => $columns) {
                        $table = $this->_sanitize($xtable);
                        $s .= "\n\t`{$this->db_prefix}$table` \nSET ";
                        foreach($columns as $xcolumn => $xvalue) {
                            $column = $this->_sanitize($xcolumn);
                            $value  = $this->_sanitize($xvalue);
                            ($count == 0)
                                ? $s .= "\n\t`{$this->db_prefix}$table`.`$column` = '$value'"
                                : $s .= ",\n\t`{$this->db_prefix}$table`.`$column` = '$value'";
                            $count++;
                        }
                    }
                }
            }
            if (isset($array['where'])) {
                if (is_array($array['where']) && count($array['where']) > 0) {
                    $count = 0;
                    foreach($array['where'] as $xtable => $column) {
                        $table = $this->_sanitize($xtable);
                        foreach ($column as $xkey => $xval) {
                            ($count == 0)
                                ? $s .= "\nWHERE "
                                : $s .= "\n\tAND ";
                            $key = $this->_sanitize($xkey);
                            $s .= "`{$this->db_prefix}{$table}`.`{$key}` = ";
                            if (is_array($xval)) {
                                $xcount = 0;
                                foreach($xval as $ycolumn) {
                                    $xycolumn = $this->_sanitize($ycolumn);
                                    ($xcount == 0)
                                        ? $s .= "`{$xycolumn}`"
                                        : $s .= ".`{$xycolumn}`";
                                    $xcount++;
                                }
                            }else {
                                $val = $this->_sanitize($xval);
                                $s .= "'$val'";
                            }
                            $count++;
                        }
                        $count++;
                    }
                }
            }
            if (($this->debug) || ($debug)) {
                log_message('debug', __CLASS__, 'update() the query: '.PHP_EOL.$s, true);
            }
            $this->last_query = $s;
            if ($res = $this->mysqli->query($s)) {
                return true;
            }
            else {
                $this->_sendFailedQuery();
            }
        }
        return false;
    }

    public function __destruct()
    {
        if (!$this->mysqli->connect_errno) {
            log_message('debug', __CLASS__, '_destruct() closing MySQLi database connection', $this->debug);
            $this->mysqli->close();
        }
    }
}
