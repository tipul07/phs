<?php

interface PHS_db_interface
{
    // Getter and setter for connection settings
    public function connection_settings( $connection_name, $mysql_settings = false );

    // Do the query and return query ID
    public function query( $query, $connection_name = false );

    // Escape strings
    public function escape( $fields, $connection_name = false );

    // Returns last inserted ID
    public function last_inserted_id();

    // Getter and setter for boolean which should tell if errors should be displayed or not
    public function display_errors( $var = null );

    // Getter and setter for queries number for current driver
    public function queries_number( $incr = false );

    // Fetch associative array from database resource
    public function fetch_assoc( $qid );

    // Returns number of records from database resource
    public function num_rows( $qid );

    // Returns an INSERT query string for table $table_name for $insert_arr data
    public function quick_insert( $table_name, $insert_arr, $connection = false, $params = false );

    // Returns an EDIT query string for table $table_name for $edit_arr data with $where_arr conditions
    public function quick_edit( $table_name, $edit_arr, $connection = false, $params = false );
}
