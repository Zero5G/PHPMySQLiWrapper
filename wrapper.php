<?php
declare(strict_types=1);
namespace Wrapper\MySQLi;
use mysqli, mysqli_result;
// class Table {
//     private array $names;
//     private array $values;
//     function __construct($array) {
//         $this->values = $array;
//         $this->names = array_keys($array[0]);
//     }
//     public function get_values(): array {
//         return $this->values;
//     }
//     public function get_names(): array {
//         return $this->names;
//     }
// }
/**
 * Access is wrapper around MySQLi that makes simple operations quicker.
 * 
 * The connection is closed on __destruct (on the last reference of the created object).
 * 
 * This wrapper works only for: Integers, Floats, Strings and Booleans
 */
class Access {
    /** @var array $properties List of properties available thougth the `get()` function */
    private array $properties = ["sql", "result", "assoc_array", "collumn_names"];
    private mysqli $sql;
    /** @var array $result Last returned result or false in the case of no return. */
    public mysqli_result|bool $result;
    /** @var array $assoc_array Last returned associative array. */
    private array $assoc_array; 
    /** @var array $collumn_names Last returned names of columns. */
    private array $collumn_names;
    private bool $debug_mode = true;
    /** Get the type of every varibable in an array and write the type to a string
     * 
     * Get's the type of every varibable in an array and write the type to a string for MySQLi bind_param()
     * @param array $array Array of variables
     * @return string $types String of parameter types for MySQLi
     */
    private function get_types(array $array): string {
        $types = "";
        foreach ($array as $value) {
            switch (gettype($value)) {
                case "boolean": $types .= "i";
                    break;
                case "integer": $types .= "i";
                    break;
                case "double": $types .= "d";
                    break;
                case "string": $types .= "s";
                    break;
                default:
                    ob_start();
                    var_dump($value);
                    $result = ob_get_clean();
                    throw new \Exception("Error when processing data type of $result. Available types: Integers, Floats, Strings and Booleans.");
                    break;
            }
        }
        return $types;
    }
    /** Create parameters for a MySQLi prepared statement
     * 
     * Creates parameters separeted with the selected separator for a MySQLi prepared statement
     * 
     * (adds =? behind each parameters)
     * @param array $parameters Array of selected parameters 
     * @param string $separator Separator of each parameter
     * @return string Returns a string of separated parameters
     */
    private function create_parameters(array $parameters, string $separator): string {
        return implode($separator, 
            array_map(
                fn ($c) => $c."=?",
                $parameters
            )
        );
    }
    /** Executes an SQL query
     * 
     * This function prepares an SQL statemenet using the provides query and parameters
     * @param  string $table SQL table name
     * @param  string $command This is used for debug_mode
     * @param  string $query SQL query
     * @param  array $values Values to be added to the prepared statement
     * @param  bool $return If the query has a result
     * @return mysqli_result|false Result of query or false if there is no result
     */
    private function execute(string $table, string $command, string $query, array $values, ?bool $return = false): mysqli_result|false {
        $out = false;
        if ($this->debug_mode) {
            echo "\nDEBUG: Query: ". $query;
        }
        if ($statement = $this->sql->prepare($query)) {
            if (!empty($types = $this->get_types($values))) {
                $statement->bind_param($types, ...$values);
            }

            if (!$statement->execute()) {
                throw new \Exception("Chyba při $command příkazu do $table,\nQUERY: $query,\nTYPES: $types", 1);
            }
            if ($return) {
                $out = $statement->get_result();
                $this->result = $out;
            } else {
                $this->result = $statement->get_result();
            }
            $statement->close();
        } else {
            throw new \Exception("Chyba při přopojování do $this->sql", 1);
        }
        return $out;
    }
    /** Get values of a propertity
     * @param string Name of an available property
     * @return mixed Returns value of an available property
     */
    public function get(string $var_name): mixed {
        return (in_array($var_name, $this->properties)) ? $this->$var_name : null;
    }
    /** Get a list of propertities available with get()
     * @param bool $array If the function should return an array or string
     * @return string|array Propertities available with get()
     */
    public function get_properties(bool $array = false): string|array {
        return $array ? $this->properties : implode(", ", $this->properties);
    }
    public function collumn_names(?mysqli_result $result = null): array|false {
        if (empty($result) && empty($this->result)) {
            return false;
        } else if (empty($result)) {
            $result = $this->result;
        }
        $return = array_map(
            fn($n) => $n->name,
            $result->fetch_fields()
        );
        $this->collumn_names = $return;
        return $return;
    }
    public function assoc_array(?mysqli_result $result = null): array|false {
        if (empty($result) && empty($this->result)) {
            return false;
        } else if (empty($result)) {
            $result = $this->result;
        }
        $return = $result->fetch_all(MYSQLI_ASSOC);
        $this->assoc_array = $return;
        return $return;
    }
    /** Doesn't have exceptions/doesn't return false */
    // public function get_array_table(?mysqli_result $result = null): Table {
    //     $output = array();
    //     if (empty($result)) {
    //         $output = $this->result;
    //     } else {
    //         $output = $result;
    //     }
    //     return new Table($this->assoc_array($output));
    // }
    public function insert(string $table, array $columns, array $values) {
        $placeholders = implode(",", array_fill(0, count($columns), "?"));
        $collums = implode(",", $columns);

        $query = "INSERT $table ($collums) VALUES ($placeholders)";
        $this->execute($table, "INSERT", $query, $values, false);
    }
    public function delete(string $table, array $conditions, array $values) {
        $query = "DELETE FROM $table WHERE " . $this->create_parameters($conditions, " AND ");
        $this->execute($table, "DELETE", $query, $values, false);
    }
    public function select(string $table, array|string $columns, ?array $conditions = null, ?array $values = null, ?string $sort_by = null, ?string $sort = "ASC", ?int $limit = null, ?int $offset = null): mysqli_result|false {
        $columns_str = (gettype($columns) == "array") ? implode(",", $columns) : $columns;
        $values = isset($values) ? $values : array();

        $query = "SELECT $columns_str FROM $table";

        if (isset($conditions)) {
            $conds = $this->create_parameters($conditions, " AND ");
            $query .= " WHERE $conds";
        }

        if (isset($sort_by)) {
            $query .= " ORDER BY $sort_by";
            $query .= " $sort";
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

        return $this->execute($table, "SELECT", $query, $values, true);
    }
    public function update(string $table, array|string $columns, array $values, ?array $conditions, ?array $condition_values) {
        $query = "UPDATE $table SET ";
        array_push($values, $condition_values);

        $query .= $this->create_parameters($columns, ", ");

        if (isset($conditions)) {
            $conds = $this->create_parameters($conditions, " AND ");
            $query .= " WHERE $conds";
        }
        
        $this->execute($table, "UPDATE", $query, $values);
    }
    function autocommit(bool $on) {
        if (!$this->sql->autocommit($on)) {
            throw new \Exception("Error: Can't configure auto commit.", 1);
        }
        if ($this->debug_mode) {
            if ($on) {
                echo "\nDEBUG: Enabling autocommit.";
            } else {
                echo "\nDEBUG: Disabling autocommit.";
            }
        }
    }
    function commit() {
        if (!$this->sql->commit()) {
            throw new \Exception("Error: Can't commit transactions.", 1);
        }
        if ($this->debug_mode) {
            echo "\nDEBUG: Commiting stored transactions.\n";
        }
    }
    function __construct(?string $hostname, ?string $username, ?string $password, ?string $database, ?int $port = null) {
        $this->sql = @new mysqli($hostname, $username, $password, $database, $port);
        if ($this->sql->connect_error) {
            throw new \Exception("\nDEBUG: Connection failed: ". $this->sql->connect_error ."\n");
        } else if ($this->debug_mode) {
            echo "\nDEBUG: Connection to $database successful.\n";
        }
    }
    function __destruct() {
        if ($this->debug_mode) { echo "\nDEBUG: Closing MySQLi connection the SQL database\n"; }
        $this->sql->close();
    }
}
?>
