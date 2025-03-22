<?php
declare(strict_types=1);
namespace MySQLiWrapper;
use mysqli, mysqli_result;
class Table {
    private array $names;
    private array $values;
    function __construct($array) {
        $this->values = $array;
        $this->names = array_keys($array[0]);
    }
    public function get_values(): array {
        return $this->values;
    }
    public function get_names(): array {
        return $this->names;
    }
}
class Database {
    private array $properties = ["sql", "result", "assoc_array", "collumn_names"];
    private mysqli $sql;
    /** @var array $result Last returned result or false in the case of no return. */
    public mysqli_result|bool $result;
    /** @var array $assoc_array Last returned associative array. */
    private array $assoc_array; 
    /** @var array $collumn_names Last returned names of columns. */
    private array $collumn_names;
    private bool $debug_mode = true;
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
    private function create_parameters(array $parameters, string $separator): string {
        return implode($separator, 
            array_map(
                fn ($c) => $c."=?",
                $parameters
            )
        );
    }
    private function execute(string $table, string $command, string $query, array $values, ?bool $return = false): mysqli_result|false {
        $out = false;
        if ($this->debug_mode) {
            echo $query;
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
            $statement->close();
        }
        return $out;
    }
    public function get(string $var_name): mixed {
        return (in_array($var_name, $this->properties)) ? $this->$var_name : null;
    }
    public function get_public_properties(): string {
        return "\n Accesible properties: ".implode(", ", $this->properties);
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
    public function assoc_array(?mysqli_result $result = null) {
        if (empty($result) && empty($this->result)) {
            return false;
        } else if (empty($result)) {
            $result = $this->result;
        }
        $return = $result->fetch_all(MYSQLI_ASSOC);
        $this->assoc_array = $return;
        return $return;
    }
    public function get_array_table(?mysqli_result $result = null) {
        $output = array();
        if (empty($result)) {
            $output = $this->result;
        } else {
            $output = $result;
        }
        return new Table($this->assoc_array($output));
    }
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
    public function select(string $table, array|string $columns, ?array $conditions = null, ?array $values = null, ?string $sort_by = null, ?string $sort = "ASC", ?int $limit = null): mysqli_result|false {
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
    function __construct(?string $hostname, ?string $username, ?string $password, ?string $database, ?int $port = null) {
        $this->sql = @new mysqli($hostname, $username, $password, $database, $port);
        if ($this->sql->connect_error) {
            throw new \Exception("<br>Connection failed: " . $this->sql->connect_error . "<br>");
        } else if ($this->debug_mode) {
            echo "<br>Connection to $database successful.<br>";
        }
    }
    function __destruct() {
        $this->sql->close();
    }
}
?>
