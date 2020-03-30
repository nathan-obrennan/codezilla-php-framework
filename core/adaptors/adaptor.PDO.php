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

class PDO_Adaptor
{
    public $debug = false;

    public $active = false;
    public $driver = 'sqlsrv';
    public $bt = '`';
    public $db_file;
    public $db_host;
    public $db_port;
    public $db_name;
    public $db_prefix;
    public $db_user;
    public $db_pass;
    public $db_Authentication;     // Azure SQL can use ActiveDirectoryPassword
    public $opts;
    public $pdo;
    public $read_only;

    /******************************************************************************
     *
     * function __construct($debug)
     *
    ******************************************************************************/
    public function __construct($params = null)
    {
        log_message('debug', __CLASS__, 'Database Adaptor forced DEBUG enabled', $this->debug);

        // PDO::ATTR_EMULATE_PREPARES   => false // only valid on the PDOStatement object

        $this->opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }

        // disable backtick usage for sqlsrv
        if (isset($this->driver))
            if ($this->driver == 'sqlsrv')
                $this->bt = '';

        if ($this->active()) {
            return true;
        }else {
            log_message('fatal', __CLASS__, 'failed to connect to the requested database.', $this->debug);
            return false;
        }
        log_message('debug', __CLASS__, 'Database Adaptor Class Instantiated', $this->debug);
    }

    /******************************************************************************
     *
     * function active()
     * @return bool
     *
     * If the database connection is already active this will return true
     * If the database connection is not available, it will execute the _connect method
     * If it cannot connect to the database it will return false
     *
    ******************************************************************************/
    public function active()
    {
        log_message('debug', __CLASS__, 'adaptor.PDO->active()', $this->debug);
        if ($this->active)
            return true;
        if ($this->_connect())
            return true;
        return false;
    }

    /******************************************************************************
     *
     * function _connect()
     * @return bool
     *
     * Returns true after a successful connection to the specified database
     * Returns false if this is not able to connect
     *
    ******************************************************************************/
    public function _connect()
    {
        log_message('debug', __CLASS__, 'connect()', $this->debug);
        if ($this->driver == 'sqlite') {
            $this->opts = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ];
            log_message('debug', __CLASS__, 'connect()->driver = sqlite', $this->debug);
            if (!file_exists($this->db_file)) {
                // can we create the file?
                if ($fh = fopen($this->db_file, 'w')) {
                    define('INITIAL_CONFIG', 'true');
                    fclose($fh);
                }
                else {
                    log_message('fatal', __CLASS__, 'connect() Cannot create or access the specified SQLite DB file: ' .$this->db_file, $this->debug);
                    die('Cannot create or access the specified SQLite DB file: '.$this->db_file);
                }
            }
            if (file_exists($this->db_file)) {
                log_message('debug', __CLASS__, 'connect() found the specified SQLite db file.', $this->debug);
                if (!is_writable($this->db_file)) {
                    $this->read_only = true;
                    // the dbfile is not writable
                    log_message('inof', __CLASS__, 'connect() The specified SQLite db file is read only.', $this->debug);
                }
                log_message('debug', __CLASS__, 'connect() the specified SQLite db file is writable.', $this->debug);
            }

            log_message('debug', __CLASS__, 'adaptor.PDO::'.$this->driver.' Database in use', $this->debug);
            try {
                $this->pdo = new PDO($this->driver.':'.$this->db_file, '', '', $this->opts);
                if (is_object($this->pdo)) {
                    $this->active = true;
                    log_message('debug', __CLASS__, 'adaptor.PDO::'.$this->driver.' Connected Successfully', $this->debug);
                    return true;
                }
            }
            catch(Exception $e) {
                log_message('fatal', __CLASS__, 'adaptor.PDO::'.$this->driver.' Connect failed ', $this->debug);
                show($e);
                die(print_r($e->getMessage()));
            }
            return false;
        }
        if ($this->driver == 'sqlsrv') {
            log_message('debug', __CLASS__, 'connect()->driver = sqlsrv', $this->debug);

            // build the connection string
            $connection = 'sqlsrv:Server='.$this->db_host.';Database='.$this->db_name;
            if (isset($this->db_Authentication))
                $connection .= ';Authentication='.$this->db_Authentication;

            try {
                $this->pdo = new PDO($connection, $this->db_user, $this->db_pass, $this->opts);
            }
            catch(Exception $e) {
                show($e);
                die(print_r($e->getMessage()));
            }

            if (is_object($this->pdo)) {
                $this->active = true;
            }
        }
        return $this->active;
    }

    /******************************************************************************
     *
     * function delete($sql)
     * @param array $sql the array of data to delete from the database
     * A properly formatted query array with FROM and WHERE elements
     *
    ******************************************************************************/
    public function delete($sql)
    {
        if (is_array($sql)) {
            $s = 'DELETE ';
            $from = $this->_sanitize($sql['from']);
            $s .= "\nFROM {$this->bt}{$this->db_prefix}{$from}{$this->bt}";
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
                            $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$key}{$this->bt} = ";
                            if (is_array($xval)) {
                                $xcount = 0;
                                foreach($xval as $ycolumn) {
                                    $xycolumn = $this->_sanitize($ycolumn);
                                    ($xcount == 0)
                                        ? $s .= "{$this->bt}{$xycolumn}{$this->bt}"
                                        : $s .= ".{$this->bt}{$xycolumn}{$this->bt}";
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
                log_message('debug', __CLASS__, 'adaptor.PDO->delete() the query: '.PHP_EOL.$s, true);
            }

            try {
                if ($res = $this->pdo->query($s)) {
                    return true;
                }
            }
            catch(Exception $e) {
                show($e);
                die(print_r($e->getMessage()));
            }
        }
        return false;
    }

    public function getColumns($table)
    {
        if (is_string($table)) {
            $table_name = $this->_sanitize($table);
            $s = "SELECT column_name FROM information_schema.columns WHERE table_name = '$table_name'";
            $stmt = $this->pdo->prepare($s);
            try {
                if ($stmt->execute()) {
                    $columns = array();
                    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($raw_data as $outer_key => $array) {
                        foreach($array as $inner_key => $value) {
                            $columns[$value] = $value;
                        }
                    }
                    return $columns;
                }
            }
            catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function insert($array)
     * @param array the array of data to insert
     * @return int This will return the insertId if available or false
     *
     * This will insert the data into the database and if possible return an
     * insertId or the error
    ******************************************************************************/
    public function insert($array)
    {
        if (isset($array)) {
            if (isset($array['insert'])) {
                if (is_array($array['insert']) && count($array['insert']) > 0) {
                    foreach($array['insert'] as $xtable => $data) {
                        $params = array();
                        $table = $this->_sanitize($xtable);
                        $s = "INSERT INTO \n\t{$this->bt}{$this->db_prefix}${table}{$this->bt} ";
                        // get column names
                        $count = 0;
                        foreach($array['insert'][$table][0] as $xcol => $vals) {
                            $column = $this->_sanitize($xcol);
                            ($count == 0)
                                ? $s .= "({$this->bt}$column{$this->bt}"
                                : $s .= ",{$this->bt}$column{$this->bt}";
                            $count++;
                        }
                        $s .= ")";
                        $s .= "\nVALUES ";
                        // now get the values
                        // id is the index of the tables array of data
                        $total_inserts = count($data);
                        foreach($data as $id) {
                            $q = $s;
                            $params = array();
                            $q .= "\n\t";
                            $count = 0;
                            foreach($id as $val) {
                                ($count == 0)
                                    ? $q .= "(?"
                                    : $q .= ", ?";
                                $params[] = $val;
                                $count++;
                            }
                            $q .= ")";
                            // submit the query
                            log_message('debug', __CLASS__, 'adaptor.PDO->insert() the query: '.PHP_EOL.$q, $this->debug);
                            if ($stmt = $this->pdo->prepare($q)) {
                                if ($stmt->execute($params)) {
                                    if ($total_inserts == 1)
                                        return $this->pdo->lastInsertId();
                                }
                                else {
                                    log_message('debug', __CLASS__, 'adaptor.PDO->insert() (1) pdo execute failed', $this->debug);
                                    return $this->pdo->errorInfo();
                                }
                            }
                            else {
                                log_message('debug', __CLASS__, 'adaptor.PDO->insert() (1) pdo prepare failed', $this->debug);
                                return $this->pdo->errorInfo();
                            }
                        }


                    }
                }
            }
        }
        return false;
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
    public function select($array, $output = 'object')
    {
        log_message('debug', __CLASS__, 'connect()->fetch()', $this->debug);
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
                                $s .= "\t{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column_name}{$this->bt} AS $column_special, ";
                            }
                        }else {
                            $column_name = $this->_sanitize($column);
                            $s .= "\t{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column_name}{$this->bt}, ";
                        }
                    }
                }
            }
            $s = mb_substr(trim($s), 0, -1);
            $from = $this->_sanitize($array['from']);
            $s .= "\nFROM {$this->bt}{$this->db_prefix}{$from}{$this->bt}";
            if (isset($array['join'])) {
                foreach($array['join'] as $xtype => $tables) {
                    if ($xtype == 'left' || $xtype == 'right' || $xtype == 'inner') {
                        $type = mb_strtoupper($xtype);
                        if (is_array($tables)) {
                            foreach($tables as $xtable => $matches) {
                                $table = $this->_sanitize($xtable);
                                $s .= "\n{$type} JOIN {$this->bt}{$this->db_prefix}{$table}{$this->bt} ON ";
                                $count = 0;
                                foreach($matches as $ytable => $ycolumn) {
                                    $table = $this->_sanitize($ytable);
                                    $column = $this->_sanitize($ycolumn);
                                    if ($count == 0) {
                                        $s .= "\n\t{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt} = ";
                                    }else {
                                        $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt}";
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
                            ($count == 0)
                                ? $s .= "\nWHERE "
                                : $s .= "\n\tAND ";
                            $key = $this->_sanitize($xkey);
                            $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$key}{$this->bt} = ";
                            if (is_array($xval)) {
                                $xcount = 0;
                                foreach($xval as $ycolumn) {
                                    $xycolumn = $this->_sanitize($ycolumn);
                                    ($xcount == 0)
                                        ? $s .= "{$this->bt}{$xycolumn}{$this->bt}"
                                        : $s .= ".{$this->bt}{$xycolumn}{$this->bt}";
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
            if (isset($array['group'])) {
                if (is_array($array['group']) && count($array['group']) > 0) {
                    $s .= "\nGROUP BY ";
                    $count = 0;
                    foreach($array['group'] as $xtable => $xcolumn) {
                        $table = $this->_sanitize($xtable);
                        $column = $this->_sanitize($xcolumn);
                        ($count == 0)
                            ? $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt}"
                            : $s .= ", {$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt}";
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
                                halt('Check your SQL ORDER Type: must be ASC or DESC');
                            $column = $this->_sanitize($xcolumn);
                            ($count == 0)
                                ? $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt} $type"
                                : $s .= ", {$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$column}{$this->bt} $type";
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
            log_message('debug', __CLASS__, 'adaptor.PDO->fetch() the query: '.PHP_EOL.$s, $this->debug);
            if ($res = $this->pdo->query($s)) {
                if ($output == 'array') {
                    log_message('debug', __CLASS__, 'adaptor.PDO->fetch() returning data as array', $this->debug);
                    while($row = $res->fetch(PDO::FETCH_NUM)) {
                        $data[] = $row;
                    }
                }elseif ($output == 'assoc') {
                    log_message('debug', __CLASS__, 'adaptor.PDO->fetch() returning data as associative array', $this->debug);
                    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
                        $data[] = $row;
                    }
                }else {
                    log_message('debug', __CLASS__, 'adaptor.PDO->fetch() returning data as object', $this->debug);
                    while($row = $res->fetch(PDO::FETCH_OBJ)) {
                        $data[] = $row;
                    }
                }
                if (isset($data)) {
                    return $data;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function _sanitize($string)
     * @param string The string to sanitize
     *
     * PDO does not provide any means of sanitizing data similar to MySQLi,
     * instead, we will use the mb_escape from the functions file. This is not
     * super safe, but better than nothing until something else is written.
     *
    ******************************************************************************/
    public function _sanitize($string)
    {
        if (function_exists('htmlentities')) {
            return htmlentities($string, ENT_QUOTES);
        }
        return mb_escape($string);
    }

    /******************************************************************************
     *
     * function update($array)
     * @param array the array of data to select from the database
     * After validating the array via the Database class this method
     * will execute the update query
     *
    ******************************************************************************/
    public function update($array)
    {
        if (is_array($array)) {
            $params = array();
            if (isset($array['update'])) {
                $s = 'UPDATE ';
                if (is_array($array['update']) && count($array['update']) > 0) {
                    $count = 0;
                    foreach($array['update'] as $table => $columns) {
                        $s .= "\n\t{$this->bt}{$this->db_prefix}$table{$this->bt} \nSET ";
                        foreach($columns as $column => $value) {
                            ($count == 0)
                                ? $s .= "\n\t{$this->bt}$column{$this->bt} = ?"
                                : $s .= ",\n\t{$this->bt}$column{$this->bt} = ?";
                            $params[] = $value;
                            $count++;
                        }
                    }
                }
            }
            if (isset($array['where'])) {
                if (is_array($array['where']) && count($array['where']) > 0) {
                    $count = 0;
                    foreach($array['where'] as $table => $column) {
                        foreach ($column as $key => $xval) {
                            ($count == 0)
                                ? $s .= "\nWHERE "
                                : $s .= "\n\tAND ";
                            $s .= "{$this->bt}{$this->db_prefix}{$table}{$this->bt}.{$this->bt}{$key}{$this->bt} = ";
                            if (is_array($xval)) {
                                // The values matching are another table.column
                                $xcount = 0;
                                foreach($val as $ycolumn) {
                                    ($xcount == 0)
                                        ? $s .= "{$this->bt}{$ycolumn}{$this->bt}"
                                        : $s .= ".{$this->bt}{$ycolumn}{$this->bt}";
                                    $xcount++;
                                }
                            }else {
                                $s .= "?";
                                $params[] = $xval;
                            }
                            $count++;
                        }
                        $count++;
                    }
                }
            }
            log_message('debug', __CLASS__, 'adaptor.PDO->update() the query: '.PHP_EOL.$s, $this->debug);
            if ($stmt = $this->pdo->prepare($s)) {
                $count = 1;
                foreach($params as $key => $val) {
                    $stmt->bindValue($count, $val);
                    $count++;
                }
                if ($stmt->execute())
                    return true;
            }
        }
        return false;
    }

    public function __destruct(){}

}
