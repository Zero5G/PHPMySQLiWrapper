<?php
class ArrayTable {
    private array $columns;
    private array $values;
    function __construct($array) {
        $this->values = $array;
        $this->columns = array_keys($array[0]);
    }
    public function get_values(): array {
        return $this->values;
    }
    public function get_columns(): array {
        return $this->columns;
    }
}
class Database {
    private mysqli $sql;
    public mysqli_result|bool $last_result;
    public array $last_assoc_array;
    public array $last_collumn_names;
    private bool $debug_mode = true;
    function __construct(?string $hostname, ?string $username, ?string $password, ?string $database, ?int $port = null) {
        $this->sql = @new mysqli($hostname, $username, $password, $database, $port);
        if ($this->sql->connect_error) {
            throw new Exception("Connection failed: " . $this->sql->connect_error);
        } else if ($this->debug_mode) {
            echo "Connection to $database successful.";
        }
    }
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
                    throw new Exception("Error when processing data type of $result. Available types: Integers, Floats, Strings and Booleans.");
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
                throw new Exception("Chyba při $command příkazu do $table,\nQUERY: $query,\nTYPES: $types", 1);
            }
            if ($return) {
                $out = $statement->get_result();
                $this->last_result = $out;
            } else {
                $this->last_result = $statement->get_result();
            }
            $statement->close();
        } else {
            throw new Exception("Chyba při přopojování do $this->sql", 1);
            $statement->close();
        }
        return $out;
    }
    public function get_sql() {
        return $this->sql;
    }
    public function collumn_names(?mysqli_result $result = null) {
        if (empty($result)) {
            $result = $this->last_result;
        }
        $return = array_keys($result->fetch_assoc(MYSQLI_ASSOC));
        $this->last_collumn_names = $return;
        return $return;
    }
    public function assoc_array(?mysqli_result $result = null) {
        $return = array();
        if (empty($result)) {
            $result = $this->last_result;
        }
        $return = $result->fetch_all(MYSQLI_ASSOC);
        $this->last_assoc_array = $return;
        return $return;
    }
    public function get_array_table(?mysqli_result $result = null) {
        $output = array();
        if (empty($result)) {
            $output = $this->last_result;
        } else {
            $output = $result;
        }
        return new ArrayTable($this->assoc_array($output));
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
}
?>