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

/******************************************************************************
 *
 * Codezilla classes
 *
 * classes must be named in StudlyCaps
 * methods must be named in camelCase
 * properties must be named in under_score
 * constants must be UPPERCASE
 * protected words (true, false, null) must be LOWERCASE (ha ha!)
 *
******************************************************************************/

/******************************************************************************
 *
 * Database class
 *
 * This class is responsible for basic CRUD interactions with the different
 * database adaptors available. The 'database' option can be specified
 * in the master config file for which type of database you would like to use
 * and will utilize the proper adaptor necessary. This is essentially a wrapper.
 *
******************************************************************************/
class Database
{
    public $debug   = false;
    public $active  = false;
    public $adaptor;
    public $db_apikey;
    public $db_Authentication;
    public $db_file;
    public $db_host;
    public $db_name;
    public $db_pass;
    public $db_port;
    public $db_prefix;
    public $db_user;
    public $driver;
    public $errno;
    public $error;
    public $error_list;

    protected $db;

    public function __construct($params = null)
    {
        log_message('debug', __CLASS__, 'forced DEBUG enabled', $this->debug);
        // process params
        if (!is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }
    }

    public function active()
    {
        return $this->active;
    }

    private function catchErrors()
    {
        // if there are errors, let's set them now
        if ($this->adaptor == 'MySQLi') {
            if (!empty($this->db->mysqli->connect_errno)) {
                $this->errno = $this->db->mysqli->connect_errno;
                $this->error = $this->db->mysqli->connect_error;
                log_message('debug', __CLASS__, 'Database Errors: '.$this->errno.' -- '.$this->error);
            }
        }
    }

    public function connect()
    {
        if ($this->adaptor) {
            // verify this adaptor exists
            $adaptor_file = CORE . DIRECTORY_SEPARATOR . 'adaptors' . DIRECTORY_SEPARATOR . 'adaptor.' . $this->adaptor . '.php';
            if (file_exists($adaptor_file)) {
                require_once($adaptor_file);
            }else {
                log_message('fatal', __CLASS__, 'connect() database was defined but invalid. No adaptor was available. Dropping adaptor.');
                unset($this->adaptor);
            }
        }

        if (!$this->adaptor) {
            log_message('fatal', __CLASS__, 'connect() database was defined but invalid. No adaptor was available.');
            die('Invalid database adaptor requested');
        }

        if ($this->adaptor) {
            $db_adaptor = $this->adaptor . '_Adaptor';
            if ($this->adaptor == 'SQLite') {
                $params = array(
                    'db_file'   => $this->db_file,
                    'debug'     => $this->debug
                );
                ($this->db = new $db_adaptor($params))
                    ?: halt('The Database Adaptor could not make a valid connection, or appears to be inactive.');
            }
            elseif ($this->adaptor == 'MySQLi') {
                $params = array(
                    'db_host'   => $this->db_host,
                    'db_port'   => $this->db_port,
                    'db_name'   => $this->db_name,
                    'db_prefix' => $this->db_prefix,
                    'db_user'   => $this->db_user,
                    'db_pass'   => $this->db_pass,
                    'debug'     => $this->debug
                );
                ($this->db = new $db_adaptor($params))
                    ?: halt('The Database Adaptor could not make a valid connection, or appears to be inactive.');
            }
            // This is for Microsoft Azure SQL Databases using Active Directory Authentication
            elseif ($this->adaptor == 'AzureSQLAD') {
                $params = array(
                    'driver'    => $this->driver,
                    'db_host'   => $this->db_host,
                    'db_port'   => $this->db_port,
                    'db_name'   => $this->db_name,
                    'db_prefix' => $this->db_prefix,
                    'db_user'   => $this->db_user,
                    'db_pass'   => $this->db_pass,
                    'db_Authentication' => $this->db_Authentication,
                    'db_apikey' => $this->db_apikey,
                    'debug'     => $this->debug
                );
                ($this->db = new $db_adaptor($params))
                    ?: halt('The Database Adaptor could not make a valid connection, or appears to be inactive.');
            }
            elseif ($this->adaptor == 'PDO') {
                $params = array(
                    'driver'    => $this->driver,
                    'db_host'   => $this->db_host,
                    'db_port'   => $this->db_port,
                    'db_name'   => $this->db_name,
                    'db_prefix' => $this->db_prefix,
                    'db_user'   => $this->db_user,
                    'db_pass'   => $this->db_pass,
                    'db_file'   => $this->db_file,
                    'debug'     => $this->debug
                );
                ($this->db = new $db_adaptor($params))
                    ?: halt('The Database Adaptor could not make a valid connection, or appears to be inactive.');
            }
        }

        $this->catchErrors();

        // return active or not
        if ($this->db->active())
            $this->active = $this->db->active();
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);
        return $this->active;
    }

    /******************************************************************************
     *
     * function initial_config()
     *
     * Each Adaptor has a special method for creating the initial tables
     * during the application installation. Execute that method here.
     *
    ******************************************************************************/
    public function initial_config()
    {
        if (!$this->active)
            $this->connect();
        if (!$this->active)
            die('Database connection could not be established after multiple attempts.');
        if ($this->db->initial_config())
            return true;
        return false;
    }

    /******************************************************************************
     *
     * function delete($sql)
     * @param array $sql a properly formatted array for deletion
     *
     * Verifies the delete format is met and passes this query on to the adaptor
     * for execution.
     *
    ******************************************************************************/
    public function delete($sql)
    {
        log_message('debug', __CLASS__, 'delete()', $this->debug);
        if (is_array($sql)) {
            if ($this->_validateArray($sql)) {
                return $this->db->delete($sql);
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function execute($array)
     * @param array The key => values of the sql to execute
     *
     * The execute method is intended to execute an SQL job without requiring a
     * response, ideally used by cron or various other one off type jobs.
     *
    ******************************************************************************/
    public function execute($query, $validate = true)
    {
        if (isset($query)) {
            if ($validate) {
                if ($this->_validateArray($query)) {
                    if ($result = $this->db->execute($query)) {
                        return true;
                    }
                }
            }else {
                // just run it, this is probably raw sql
                if ($result = $this->db->execute($query)) {
                    return true;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function getColumns($table)
     * @param string $table The table to gather columns from
     * @return array An array containing column names
     *
    ******************************************************************************/
    public function getColumns($table)
    {
        if (!empty($table)) {
            if ($columns = $this->db->getColumns($table))
                return $columns;
        }
        return false;
    }

    /******************************************************************************
     *
     * function insert($array)
     * @param array The key => values of the sql to execute
     * @return bool a simple true or false for success or fail
     *
     * This method will execute an SQL insert query returning true or false if multiple
     * sets of values are inserted or return the last insert id.
     *
    ******************************************************************************/
    public function insert($query)
    {
        log_message('debug', __CLASS__, 'insert()', $this->debug);
        if (is_array($query)) {
            log_message('debug', __CLASS__, 'insert() is array', $this->debug);
            if (isset($query['insert'])) {
                log_message('debug', __CLASS__, 'insert() insert is set', $this->debug);
                if ($this->_validateArray($query)) {
                    log_message('debug', __CLASS__, 'insert() sending data to db->insert()');
                    return $this->db->insert($query);
                }
                else {
                    log_message('debug', __CLASS__, 'insert() query failed validation', $this->debug);
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function sanitize($string)
     * @param string The string to be cleaned
     * @return string
     *
     * This method will attempt to clean a string of harmful components using the
     * adapter method, if available, otherwise it will use the security class
     *
    ******************************************************************************/
    public function sanitize($string) {
        if (method_exists($this->db, 'sanitize')) {
            return $this->db->sanitize($string);
        }
        else {
            return $this->security->sanitize($string);
        }
    }

    /******************************************************************************
     *
     * function select($array)
     * @param array The key => values of the sql to execute
     *
     * The select method will utilitize the proper adaptor and return the data
     * that has been requested, typically in object form.
     *
    ******************************************************************************/
    public function select($query, $output = 'object', $debug = false)
    {
        log_message('debug', __CLASS__, 'select()', $this->debug);
        if (is_array($query)) {
            log_message('debug', __CLASS__, 'select() query is array', $this->debug);
            if ($this->_validateArray($query)) {
                log_message('debug', __CLASS__, 'select() passed validation', $this->debug);
                // process the output...
                if (isset($query['select'])) {
                    $outcome = $this->db->select($query, $output, $debug);
                    if (isset($outcome->errno)) {
                        $this->errno = $outcome->errno;
                        $this->error = $outcome->error;
                        $this->error_list = $outcome->error_list;
                        log_message('fatal', __CLASS__, 'select(): '.$this->error, $this->debug);
                        halt('Database error occurred.');
                    }
                    return $outcome;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function raw_query($query)
     * @param string A raw SQL query to execute
     *
     * DO Not Use This method. Period. It executes a raw sql query on the selected
     * database without any verification. This method was added purely
     * for the creation of initial database tables.
     *
    ******************************************************************************/
    public function rawquery($sql)
    {
        log_message('debug', __CLASS__, 'rawquery()', $this->debug);
        if ($res = $this->db->execute($sql))
            return $res;
        return false;
    }

    /******************************************************************************
     *
     * function update($array)
     * @param array The key => values of the sql to execute
     * @return bool a simple true or false for success or fail
     *
     * This method will execute an SQL update query returning true or false
     *
    ******************************************************************************/
    public function update($query)
    {
        log_message('debug', __CLASS__, 'update()', $this->debug);
        if (is_array($query)) {
            if ($this->_validateArray($query)) {
                if (isset($query['update'])) {
                    return $this->db->update($query);
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function _validateArray($array)
     * @param array to validate
     *
     * This method will simply validate the schema of the array and ensure
     * the required parts are available. This will not sanitize or protect
     * your database in any way. It only makes sure the array should work.
     *
     * $query['insert']['log'][] = array('date' => $log['date'], 'priority' => $log['priority'], 'message' => $log['message']);
     *
     * $query['select']['table_1'] = array('column_a', 'column_b');
     * $query['select']['table_2'] = array('column_a', 'column_b');
     * $query['select']['table_3'] = array('column_a', 'column_b', array('column_c' => 'special'));
     *
     * $query['update']['configuration'] = array('cron_last' => $xtime);
     *
     * $query['from'] = 'table1';
     *
     * $query['join']['left'] = array(
     *  'table2' => array(
     *      'table1' => 'column_a',
     *      'table2' => 'column_a'
     *  ),
     *  'table3' => array(
     *      'table1' => 'column_b',
     *      'table3' => 'column_b'
     *  )
     * );
     *
     * $query['join']['right'] = array(
     *  'table2' => array(
     *      'table1' => 'column_a',
     *      'table2' => 'column_a'
     *  ),
     *  'table3' => array(
     *      'table1' => 'column_b',
     *      'table3' => 'column_b'
     *  )
     * );
     *
     *
     * // WHERE result matches a value
     * $query['where']['table_1'] = array('column_a' => 'value', 'column_b' => 'value');
     * $query['where']['table_2'] = array('column_a' => 'value', 'column_b' => 'value');
     *
     * // WHERE result matches another table.column
     * $query['where']['table_3'] = array('column_a' => array('table_2', 'column_a'));
     *
     * // WHERE result matches a value in this_column OR that_column
     * $query['where]['table_3'] = array('OR' => array('column_a', 'column_b'), 'value');
     *
     *
     * $query['order'] = array(
     *  'table_1' => array('column_a' => 'ASC', 'column_b' => 'DESC'),
     *  'table_2' => array('column_b' => 'DESC')
     * );
     * $query['group'] = array('table1' => 'column_a', 'table2' => 'column_c');
     * $query['limit'] = 100;
     *
     *
    ******************************************************************************/
    private function _validateArray($array)
    {
        if (is_array($array)) {

            // validate ['insert']
            if (isset($array['insert'])) {
                (is_array($array['insert']) && count($array['insert']) > 0)
                    ?: halt('INSERT must be in the form of an array');
                foreach($array['insert'] as $table => $data) {
                    (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                        ?: halt('SQL UPDATE contains invalid characters.');
                    foreach($data as $vals) {
                        foreach($vals as $column => $val) {
                            (!preg_match('/[^a-z_\-0-9.-]/i', $column))
                                ?: halt('SQL UPDATE contains invalid characters.');
                        }
                    }
                }
            }

            // validate ['select']
            if (isset($array['select'])) {
                (isset($array['select']))
                    ?: halt('SELECT must be preset in the statement');

                // validate must be an array
                (is_array($array['select']) && count($array['select']) > 0)
                    ?: halt("Your SELECT statement must be in array form.");

                foreach($array['select'] as $table => $columns) {
                    // the table will be set but column can be a nested array
                    (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                        ?: halt('SQL SELECT contains invalid characters.');

                    if (is_array($columns)) {
                        foreach($columns as $column_idx => $column_name) {
                            (!preg_match('/[^a-z_\-0-9.-]/i', $column_idx))
                                ?: halt('SQL SELECT contains invalid characters in column name');
                            if (is_array($column_name)) {
                                foreach($column_name as $col => $spec) {
                                    (!preg_match('/[^a-z_\-0-9.-]/i', $col))
                                        ?: halt('SQL SELECT contains invalid characters in column name when requesting a special return.');
                                    (!preg_match('/[^a-z_\-0-9.-]/i', $spec))
                                        ?: halt('SQL SELECT contains invalid characters in special return of column name');
                                }
                            }else {
                                (!preg_match('/[^a-z_\-0-9.-]/i', $column_name))
                                    ?: halt('SQL SELECT contains invalid characters in the column name.');
                            }
                        }
                    }
                }
            }

            // validate ['update']
            if (isset($array['update'])) {
                (is_array($array['update']) && count($array['update']) > 0)
                    ?: halt('UPDATE must be in the form of an array');

                foreach($array['update'] as $table => $columns) {
                    (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                        ?: halt('SQL UPDATE contains invalid characters.');

                    if (is_array($columns)) {
                        foreach($columns as $column_name => $update_value) {
                            (!preg_match('/[^a-z_\-0-9.-]/i', $column_name))
                                ?: halt('SQL UPDATE contains invalid characters in column name');
                        }
                    }
                }
            }

            // validate $array['from']
            if (isset($array['from'])) {
                (isset($array['from']) && !empty($array['from']))
                    ? // good
                    : halt('FROM must be preset in the SQL statement');

                if (isset($array['from'])) {
                    (!preg_match('/[^a-z_\-0-9.-]/i', $array['from']))
                        ?: halt('SQL FROM contains invalid characters.');
                }
            }

            // validate the joins -- more complex --
            if (isset($array['join'])) {
                if (is_array($array['join'])) {
                    foreach($array['join'] as $type => $tables) {
                        if ($type == 'left' || $type == 'right' || $type == 'inner') {
                            // validate the "table" array
                            if (is_array($tables)) {
                                foreach($tables as $table => $matches) {
                                    if (is_array($table))
                                        halt('The name of the table cannot be an array');
                                    (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                                        ?: halt('SQL JOIN contains invalid characters in the table name.');
                                    if (!is_array($matches))
                                        halt('The matches for your join tables must be an array');
                                    foreach($matches as $table => $column) {
                                        (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                                            ?: halt('SQL JOIN contains invalid characters in the matched table name.');
                                        (!preg_match('/[^a-z_\-0-9.-]/i', $column))
                                            ?: halt('SQL FROM contains invalid characters in the matched column name.');
                                    }
                                }
                            }else {
                                halt('The tables in your JOIN must be an array');
                            }
                        }else {
                            halt('Type of JOIN is invalid');
                        }
                    }
                }else {
                    halt('SQL joins must be an array');
                }
            }

            // WHERE
            if (isset($array['where'])) {
                if (is_array($array['where']) && count($array['where']) > 0) {
                    foreach($array['where'] as $table => $column) {
                        // validate table
                        (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                            ?: halt('SQL WHERE contains invalid characters in the matched table name: '.$table);
                        // validate column is array with column and value
                        foreach ($column as $key => $val) {
                            (!preg_match('/[^a-z_\-0-9.-]/i', $key))
                                ?: halt('SQL WHERE contains invalid characters in the matched table name: '.$key);
                            // if $val is an array then we are matching another table
                            if (is_array($val)) {
                                foreach($val as $xtable => $xcolumn) {
                                    (!preg_match('/[^a-z_\-0-9.-]/i', $xtable))
                                        ?: halt('SQL WHERE contains invalid characters in the matched table name: '.$xtable);
                                    (!preg_match('/[^a-z_\-0-9.-]/i', $xcolumn))
                                        ?: halt('SQL WHERE contains invalid characters in the matched column name: '.$xcolumn);
                                }
                            }
                        }
                    }
                }else {
                    halt('Where must be in array form.');
                }
            }

            // GROUP BY
            if (isset($array['group'])) {
                if (is_array($array['group']) && count($array['group']) > 0) {
                    foreach($array['group'] as $table => $column) {
                        (!is_array($table))
                            ?: halt('An array was given while expecting a table name. Check your GROUP clause.');
                        (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                            ?: halt('SQL GROUP contains invalid characters in the table name.');
                        (!is_array($column))
                            ?: halt('An array was given while expect a column name. Check your GROUP clause.');
                        (!preg_match('/[^a-z_\-0-9.-]/i', $column))
                            ?: halt('SQL GROUP contains invalid characters in the column name.');
                    }
                }
            }

            // ORDER BY
            if (isset($array['order'])) {
                if (is_array($array['order']) && count($array['order']) > 0) {
                    foreach($array['order'] as $table => $data) {
                        (!preg_match('/[^a-z_\-0-9.-]/i', $table))
                            ?: halt('SQL ORDER contains invalid characters in the table name you wish to order by.');
                        foreach($data as $column => $type) {
                            if (($type != 'ASC') && ($type != 'DESC'))
                                halt('Check your SQL ORDER Type: must be ASC or DESC');
                            (!preg_match('/[^a-z_\-0-9.-]/i', $column))
                                ?: halt('SQL ORDER contains invalid characters in the column name you wish to order by.');
                        }
                    }
                }
            }

            // LIMIT
            if (isset($array['limit'])) {
                if (! is_numeric($array['limit'])) {
                    halt('LIMIT must be numeric. Check your SQL Syntax.');
                }
            }
        }
        return true;
    }

    public function __destruct()
    {
        log_message('debug', __CLASS__, '_destruct() self __destruct ing', $this->debug);
    }
}
