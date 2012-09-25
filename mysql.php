<?php
/**
 *  PHP-MySQL-Class (mysqli fork)
 *  Version 0.1
 *
 *  Copyright (C) 2011
 *     Ronald M. Clifford (http://github.com/roncli/PHP-MySQLi-Class)
 *  Based on PHP-MySQL-Class by
 *     Ed Rackham (http://github.com/a1phanumeric/PHP-MySQL-Class)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Simple MySQLi class written in PHP for interfacing with a MySQL database.
 */
class MySQL
{

    /**
     * @var array A list of data type values for MySQLi integers.
     */
    var $integerTypes = array(1, 2, 3, 8, 9);

    /**
     * @var array A list of data type values for MySQLi decimals.
     */
    var $decimalTypes = array(4, 5, 246);

    /**
     * @var mysqli The mysqli Database object
     */
    var $db = null;

    /**
     * @var bool|mysqli_result Holds the MySQL query result
     */
    var $result;

    /**
     * @var bool|mysqli_stmt Holds the MYSQL prepared statement
     */
    var $statement;

    /**
     * @var int Holds the total number of records returned
     */
    var $recordCount;

    /**
     * @var int Holds the total number of records affected
     */
    var $recordsAffected;

    /**
     * @var string Holds the last error
     */
    var $lastError;

    /**
     * @var string MySQL Hostname
     */
    var $hostname = MYSQL_HOSTNAME;

    /**
     * @var string MySQL Username
     */
    var $username = MYSQL_USERNAME;

    /**
     * @var string MySQL Password
     */
    var $password = MYSQL_PASSWORD;

    /**
     * @var string MySQL Database
     */
    var $database = MYSQL_DATABASE;

    /**
     * @var string The database character type.
     */
    var $charType = MYSQL_CHARTYPE;

    /**
     * Class constructor.  Connects to the database.
     */
    function MySQL()
    {
        $this->Connect();
    }

    /**
     * Connects the class to the database.
     * @param bool $persistent Determines whether to use a persistent
     * connection.
     * @return bool Determines whether the connection was successful.
     */
    function Connect($persistent = false)
    {
        // If we currently are connected to the database, close the connection.
        if ($this->db && $this->db->ping()) {
            $this->db->close();
        }

        // Create the new connection.
        if ($persistent) {
            $this->db = new mysqli("p:" . $this->hostname, $this->username,
                $this->password);
        } else {
            $this->db = new mysqli($this->hostname, $this->username,
                $this->password);
        }

        // Check to see if there was an error when connecting.
        if ($this->db->connect_error) {
            $this->lastError = "Could not connect to server: " .
                $this->db->error;
            return false;
        }

        if (!$this->db->set_charset($this->charType)) {
            $this->lastError = "Could not set character type: " .
                $this->db->error;
            return false;
        }

        // Attempt to use the database and return its success.
        return $this->UseDB();
    }

    /**
     * Select the database to user.
     * @return bool Determines whether the database selection was successful.
     */
    function UseDB()
    {
        if (!$this->db->select_db($this->database)) {
            $this->lastError = "Cannot select database: " . $this->db->error;
            return false;
        } else {
            return true;
        }
    }

    /**
     * Executes a MySQL query.
     * @param $sql string The query to execute.
     * @return bool Determines whether the query successfully ran.
     */
    function ExecuteSQL($sql)
    {
        if ($this->result = $this->db->query($sql)) {
            $this->recordCount = @$this->result->num_rows;
            $this->recordsAffected = @$this->db->affected_rows;
            return true;
        } else {
            $this->lastError = "Could not get the result: " . $this->db->error;
            return false;
        }
    }

    /**
     * Prepares a SQL statement for execution.
     * @param $sql string The parameterized SQL query.
     * @param $parameters array The optional parameters to prepare the SQL
     * statement with.
     * @return bool Determines whether the statement was successfully executed.
     */
    function PrepareSQL($sql,
                        $parameters = null)
    {
        // Prepare the statement.
        if ($this->statement = $this->db->prepare($sql)) {
            // Make sure we have the correct number of parameters.
            if ($this->statement->param_count != count($parameters)) {
                $this->lastError = "Parameters count in the SQL doesn't " .
                    "match the parameter count in the \$parameters argument.";
                return false;
            }

            if (count($parameters) > 0) {
                // Get the parameter types.
                $types = "";
                foreach ($parameters as $parameter) {
                    $types = $types . (is_double($parameter) ? "d" :
                        (is_integer($parameter) ? "i" : "s"));
                }

                // Prepend the paremeter types to the parameters array.
                array_unshift($parameters, $types);

                // Bind the parameters.
                call_user_func_array(array($this->statement, "bind_param"),
                    $this->ReferenceValues($parameters));
            }

            // Execute the statement.
            if (!$this->statement->execute()) {
                $this->lastError = "Could not execute the statement: " .
                    $this->db->error;
                return false;
            }

            // Get the result.
            if ($this->result = $this->statement->get_result()) {
                $this->recordCount = @$this->result->num_rows;
                $this->recordsAffected = @$this->db->affected_rows;
                return true;
            } else {
                $this->lastError = "Could not get the result: " .
                    $this->db->error;
                return false;
            }
        } else {
            $this->lastError = "Could not prepare the statement: " .
                $this->db->error;
            return false;
        }
    }

    /**
     * Gets a single result as an array.
     * @return array The result, or null if there are no results.
     */
    function GetResult()
    {
        $result = $this->result->fetch_assoc();
        $this->CleanRow($result);
        return $result;
    }

    /**
     * Gets multiple results as an array.
     * @return array The results.
     */
    function GetResults()
    {
        $results = array();
        while ($row = $this->result->fetch_assoc()) {
            $this->CleanRow($row);
            array_push($results, $row);
        }
        return $results;
    }

    /**
     * Gets the auto increment column affected by the last statement.
     * @return The auto increment value, or 0 if none was affected.
     */
    function GetIdentity()
    {
        return $this->db->insert_id;
    }

    /**
     * Cleans up a row in memory by casting data to its appropriate data type.
     * @param $row array The row to clean up.
     */
    function CleanRow(&$row)
    {
        if (!$row) {
            return;
        }
        foreach ($this->result->fetch_fields() as $field) {
            $type = $field->type;

            // Cast data appropriately.
            if ($row[$field->name] != null) {
                if (in_array($type, $this->integerTypes)) {
                    $row[$field->name] = intval($row[$field->name]);
                } elseif (in_array($type, $this->decimalTypes)) {
                    $row[$field->name] = doubleval($row[$field->name]);
                }
            }
        }
    }

    /**
     * Changes an array to references for PHP versions greater than 5.3.
     * @param $values array The values to change to references.
     * @return array The values changed to references.
     */
    function ReferenceValues($values)
    {
        if (defined("PHP_VERSION_ID") && PHP_VERSION_ID >= 50300) {
            $references = array();
            foreach ($values as $key => $value) {
                $references[$key] = &$values[$key];
            }
            return $references;
        }
        return $values;
    }
}

?>