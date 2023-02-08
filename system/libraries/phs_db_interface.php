<?php
namespace phs\libraries;

interface PHS_Db_interface
{
    // Getter and setter for connection settings
    public function connection_settings($connection_name, $conn_settings = false);

    public function test_connection($connection_name = false) : bool;

    /**
     * Do the query and return query ID
     *
     * @param string|array $query
     * @param bool|string $connection_name
     *
     * @return bool|\mysqli_result|\MongoDB\Driver\WriteResult|\SQLite3Result
     */
    public function query($query, $connection_name = false);

    // Escape strings
    public function escape($fields, $connection_name = false);

    // Returns last inserted ID
    public function last_inserted_id();

    // Getter and setter for boolean which should tell if errors should be displayed or not
    public function display_errors($var = null);

    // Getter and setter for queries number for current driver
    public function queries_number(bool $incr = false) : int;

    /**
     * Fetch associative array from database resource
     * @param $qid
     * @return null|array
     */
    public function fetch_assoc($qid) : ?array;

    /**
     * Returns number of records from database resource
     * @param mixed $qid Query id
     *
     * @return int|string
     */
    public function num_rows($qid) : int;

    // Returns an INSERT query string for table $table_name for $insert_arr data
    public function quick_insert($table_name, $insert_arr, $connection_name = false, $params = false);

    // Returns an EDIT query string for table $table_name for $edit_arr data with $where_arr conditions
    public function quick_edit($table_name, $edit_arr, $connection_name = false, $params = false);

    // Suppress any errors database driver might throw
    public function suppress_errors();

    // Restore error handling functions as before suppress_errors() method was called
    public function restore_errors_state();

    /**
     * @param bool|array $dump_params Array containing dump parameters
     *
     * @return array|bool Returns populated $dump_params array or false on error
     */
    public function dump_database($dump_params = false);
}
