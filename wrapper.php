<?php
/**
 * Wrapper around MySQLi
 * 
 * PHP version - 8.3
 * 
 * @category Databases
 * @package  Wrapper
 * @author   Zero5G <0fiveg.personal@gmail.com>
 *                  (https://github.com/Zero5G/)
 * @license  MIT - https://mit-license.org/
 * @link     https://github.com/Zero5G/PHPMySQLiWrapper
 */
declare(strict_types=1);
namespace Wrapper\MySQLi;
use mysqli, mysqli_result;

/**
 * Access is wrapper around MySQLi that makes simple operations quicker.
 * 
 * The connection is closed on __destruct 
 * (on the last reference of the created object).
 * 
 * This wrapper works best for integers, floats, strings and booleans.
 * (There is some BLOB support)
 * 
 * @category Database
 * @package  MySQLiWrapper
 * @author   Zero5G <0fiveg.personal@gmail.com>
 *                  (https://github.com/Zero5G/)
 * @license  MIT - https://mit-license.org/
 * @link     https://github.com/Zero5G/PHPMySQLiWrapper
 */
class Access
{
    /**
     * List of properties
     * 
     * @var array $_properties List of properties 
     * available thougth the `get()` function 
     */
    private array $_properties = ["sql", "result", "assoc_array", "collumn_names"];
    /**
     * MySQLi object
     * 
     * @var mysqli $_sql SQL connection using MySQLi (mysqli) (Accesible with get())
     */
    private mysqli $_sql;
    /**
     * Last returned mysqli_result
     * 
     * @var mysqli_result|bool $_result Last returned mysqli_result or 
     * false in the case of no return. (Accesible with get()) 
     */
    private mysqli_result|bool $_result;
    /**
     * Last returned associative array
     * 
     * @var array $_assoc_array Last returned associative 
     * array. (Accesible with get())
     */
    private array $_assoc_array; 
    /**
     * Last returned column names
     * 
     * @var array $_collumn_names Last returned 
     * names of columns. (Accesible with get()) 
     */
    private array $_collumn_names;
    private bool $_debug_mode = true;
    /**
     * Get values of a propertity
     * 
     * Available properties: "sql", "result", "assoc_array", "collumn_names"
     *
     * @param string $var_name Name of an available property
     * 
     * @return mixed Returns value of an available property
     */
    public function get(string $var_name): mixed
    {
        $var = "_" . $var_name;
        return (in_array($var, $this->_properties)) ? $this->{$var} : null;
    }
    /**
     * Get a list of propertities available with get()
     *
     * @param bool $array If the function should return an array or string
     * 
     * @return string|array Propertities available with get()
     */
    public function getProperties(bool $array = false): string|array
    {
        return $array ? $this->_properties : implode(", ", $this->_properties);
    }
    /**
     * Function that gets a SQL type from PHP type
     * 
     * @param mixed $variable Your variable
     * 
     * @return string SQL type of your variable
     */
    private function _getType(mixed $variable): string
    {
        $type = "";
        switch (gettype($variable)) {
        case "boolean": $type = "i";
            break;
        case "integer": $type = "i";
            break;
        case "double": $type = "d";
            break;
        case "string": $type = "s";
            break;
        default:
            ob_start();
            var_dump($variable);
            $result = ob_get_clean();
            throw new \Exception(
                "Error when processing data type of $result. 
                Available types: Integers, Floats, Strings and Booleans."
            );
                break;
        }
        return $type;
    }
    /**
     * Get the type of every varibable in an array and write the type to a string
     * 
     * Get's the type of every varibable in an array and
     * write the type to a string for MySQLi bind_param()
     *
     * @param array $array          Array of variables
     * @param array $blob_overrides An array of variable positions.
     *                              This is used to override string types 
     *                              (in types for bind_param) 
     *                              to the BLOB type as PHP has 
     *                              no solid way of detecting it.
     *                              There are no safety mechanism here, 
     *                              you can override an int or float (double).
     * 
     * @return string $types String of parameter types for MySQLi
     */
    private function _getTypes(
        array|string $array, 
        ?array $blob_overrides = null
    ): string {
        $types = "";
        foreach ($array as $value) {
            $types .= $this->_getType($value);
        }
        if (isset($blob_overrides)) {
            foreach ($blob_overrides as $pos) {
                $types = substr_replace($types, "b", $pos, 1);
            }
        }
        return $types;
    }
    /**
     * Create parameters for a MySQLi prepared statement
     * 
     * Creates parameters separeted with the 
     * selected separator for a MySQLi prepared statement
     * 
     * (adds =? behind each parameters)
     *
     * @param array  $parameters Array of selected parameters 
     * @param string $separator  Separator of each parameter
     * 
     * @return string Returns a string of separated parameters
     */
    private function _createParameters(
        array|string $parameters, 
        string $separator
    ): string {
        if (!is_array($parameters)) {
            return $parameters . "=?";
        }
        return implode(
            $separator, 
            array_map(
                fn ($p) => str_contains($p, " ") ? $p : $p . "=?",
                $parameters
            )
        );
    }
    /**
     * Executes an SQL query
     * 
     * This function prepares an SQL statemenet 
     * using the provides query and parameters
     *
     * @param string $table          SQL table name
     * @param string $command        This is used for debug_mode
     * @param string $query          SQL query
     * @param array  $values         Values to be added to the prepared statement
     * @param bool   $return         If the query has a result
     * @param array  $blob_overrides Used for $blob_overrides in _getTypes()
     *                               DO NOT USE IF YOU DON'T KNOW WHAT YOU
     *                               ARE DOING
     * 
     * @return mysqli_result|false Result of query or false if there is no result
     */
    private function _execute(
        string $table, 
        string $command, 
        string $query, 
        array|string|null $values, 
        ?bool $return = false, 
        ?array $blob_overrides = null
    ): mysqli_result|false {
        
        $out = false;
        if ($this->_debug_mode) {
            echo "\nDEBUG: Query: ". $query;
        }
        if ($statement = $this->_sql->prepare($query)) {
            if (isset($values)) {
                if (is_array($values)) {
                    $types = $this->_getTypes($values, $blob_overrides);
                    $statement->bind_param($types, ...$values);

                } else {
                    $types = $this->_getType($values);
                    $statement->bind_param($types, $values);
                }
            }

            if (!$statement->execute()) {
                throw new \Exception(
                    "Chyba při $command příkazu do $table,
                    \nQUERY: $query,\nTYPES: $types", 1
                );
            }
            if ($return) {
                $out = $statement->get_result();
                $this->_result = $out;
            } else {
                $this->_result = $statement->get_result();
            }
            $statement->close();
        } else {
            throw new \Exception("Chyba při přopojování do $this->_sql", 1);
        }
        return $out;
    }
    /**
     * Turn a MySQLi result (mysqli_result) to an array of columns names
     * 
     * @param mysqli_result $result If left empty last result is used
     * 
     * @return array|false Returns an array or false if there is no result
     */
    public function collumnNames(?mysqli_result $result = null): array|false
    {
        if (empty($result) && empty($this->_result)) {
            return false;
        } else if (empty($result)) {
            $result = $this->_result;
        }
        $return = array_map(
            fn($n) => $n->name,
            $result->fetch_fields()
        );
        $this->_collumn_names = $return;
        return $return;
    }
    /**
     * Turn a MySQLi result (mysqli_result) to an associative array
     * 
     * @param mysqli_result $result If left empty last result is used
     * 
     * @return array|false Returns an array or false if there is no result
     */
    public function assocArray(?mysqli_result $result = null): array|false
    {
        if (empty($result) && empty($this->_result)) {
            return false;
        } else if (empty($result)) {
            $result = $this->_result;
        }
        $return = $result->fetch_all(MYSQLI_ASSOC);
        $this->_assoc_array = $return;
        return $return;
    }
    /**
     * Generates an SQL INSERT query and executes it
     * 
     * @param string $table          Name of a table in the current database
     * @param array  $columns        Array of column names in the table
     * @param array  $values         Array of values names to be inserted
     * @param array  $blob_overrides Override string types to BLOB types
     *                               **DO NOT USE IF YOU DON'T KNOW WHAT
     *                               YOU ARE DOING**
     * 
     * @return void
     */
    public function insert(
        string $table,
        array|string $columns,
        array|string $values,
        ?array $blob_overrides = null
    ) {
        $count = 1;
        if (is_array($columns)) {
            $count = count($columns);
            $columns = implode(",", $columns);
        }
        $placeholders = implode(",", array_fill(0, $count, "?"));

        $query = "INSERT $table ($columns) VALUES ($placeholders)";
        $this->_execute($table, "INSERT", $query, $values, false, $blob_overrides);
    }
    /**
     * Generates an SQL DELETE query and executes it
     * 
     * @param string $table            Name of a table in the current database
     * @param array  $conditions       Array of column names in 
     *                                 the tables used for WHERE
     * @param array  $condition_values Array of values in the table used for WHERE
     * 
     * @return void
     */
    public function delete(
        string $table, 
        array|string $conditions, 
        array|string $condition_values
    ): void {
        $query = "DELETE FROM $table WHERE " 
        . $this->_createParameters($conditions, " AND ");
        $values = is_array($condition_values) ? 
            $condition_values : array($condition_values) ;
        $this->_execute($table, "DELETE", $query, $values, false);
    }
    /**
     * Generates an SQL SELECT query and executes it
     * 
     * @param string            $table            Name of a table in
     *                                            the current database
     * @param array|string      $columns          Array of column names in the table
     * @param array|string|null $conditions       Column name/names in 
     *                                            the tables used for WHERE
     * @param ?array            $condition_values Array of values in the
     *                                            table used for WHERE
     * @param ?string           $sort_by          Name of a column that 
     *                                            is used for ORDER BY
     * @param ?string           $sort             Can be either ASC or DESC else
     *                                            will do nothing 
     *                                            (it is ASC by default)
     * @param ?int              $limit            Limits the number of
     *                                            rows in the result
     * @param ?int              $offset           Offsets the number of 
     *                                            rows in the result 
     *                                            (can only be used with limit)
     * 
     * @return mysqli_result|false Returns either false or 
     *                             a MySQLi result (mysqli_result)
     */
    public function select(
        string $table,
        array|string $columns,
        array|string|null $conditions = null,
        array|string|null $condition_values = null, 
        ?string $sort_by = null,
        ?string $sort = "ASC",
        ?int $limit = null,
        ?int $offset = null
    ): mysqli_result|false {

        $columns_str = is_array($columns) ? implode(",", $columns) : $columns;
        $values = isset($condition_values) ? $condition_values : array();

        $query = "SELECT $columns_str FROM $table";

        if (isset($conditions)) {
            $conds = $this->_createParameters($conditions, " AND ");
            $query .= " WHERE $conds";
        }

        if (isset($sort_by)) {
            $query .= " ORDER BY $sort_by";

            // Accept only DESC or ASC
            if ($sort == "DESC" || $sort == "ASC") {
                $query .= " $sort";
            }
        }

        if (isset($limit)) {
            array_push($values, $limit);
            $query .= " LIMIT ?";

            // Offset requires limit
            if (isset($offset)) {
                array_push($values, $offset);
                $query .= " OFFSET ?";
            }
        }

        return $this->_execute($table, "SELECT", $query, $values, true);
    }
    /**
     * Generates an SQL UPDATE query and executes it
     * 
     * @param string       $table            Name of a table in the current database
     * @param array|string $columns          Array of column names in 
     *                                       the table to be changed
     * @param array        $values           Array of new values
     * @param array        $conditions       Array of column names in 
     *                                       the tables used for WHERE
     * @param array        $condition_values Array of values in the
     *                                       table used for WHERE
     * 
     * @return void
     */
    public function update(
        string $table,
        array|string $columns,
        array|string $values,
        array|string|null $conditions = null,
        array|string|null $condition_values = null
    ): void {
        $query = "UPDATE $table SET ";
        $_values = array();
        array_push($_values, $values);
        array_push($_values, $condition_values);

        $query .= $this->_createParameters($columns, ", ");

        if (isset($conditions)) {
            $conds = $this->_createParameters($conditions, " AND ");
            $query .= " WHERE $conds";
        }
        
        $this->_execute($table, "UPDATE", $query, $_values);
    }
    /**
     * Enables/disables autocommit
     * 
     * If disabled queries (transactions) won't be 
     * executed until the commit() function is ran
     * 
     * @param bool $on If autocommit should be switched on/off
     * 
     * @return void
     */
    function autocommit(bool $on): void
    {
        if (!$this->_sql->autocommit($on)) {
            throw new \Exception("Can't configure auto commit.", 1);
        }
        if ($this->_debug_mode) {
            if ($on) {
                echo "\nDEBUG: Enabling autocommit.";
            } else {
                echo "\nDEBUG: Disabling autocommit.";
            }
        }
    }
    /**
     * This function commits queries (transactions) made when autocommit was off 
     * 
     * @return void
     */
    function commit(): void
    {
        if (!$this->_sql->commit()) {
            throw new \Exception("Can't commit transactions.", 1);
        }
        if ($this->_debug_mode) {
            echo "\nDEBUG: Commiting stored transactions.\n";
        }
    }
    /**
     * Magic function construct, connects to a SQL databse using MySQLi
     *
     * @param string $hostname     Can be either a host name or an IP address.
     * @param string $username     The MySQL username.
     * @param string $password     User password.
     * @param string $database     Defaults to "".
     * @param int    $port         Specifies the port number for the MySQL server.
     * @param bool   $enable_debug If debug mode should be enabled.
     */
    function __construct(
        ?string $hostname = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?bool $enable_debug = false
    ) {
        $this->_debug_mode = $enable_debug;
        $this->_sql = @new mysqli($hostname, $username, $password, $database, $port);
        if ($this->_sql->connect_error) {
            throw new \Exception("Connection failed: ". $this->_sql->connect_error);
        } else if ($this->_debug_mode) {
            echo "\nDEBUG: Connection to $database successful.\n";
        }
    }
    /**
     * Magic function destruct, closes the MySQLi connection 
     */
    function __destruct()
    {
        if ($this->_debug_mode) { 
            echo "\nDEBUG: Closing MySQLi connection the SQL database\n"; 
        }
        $this->_sql->close();
    }
}
?>
