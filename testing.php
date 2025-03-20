<?php
include("lib.php");

$test = new Database("localhost", "xmanousek", "Ylrq2117", "xmanousek", 7070);
// $test->delete("test", ["test"], ["16"]);
// $test->insert("test", ["stra"], ["a"]);
$test->select("test", "*");
echo var_dump($test->get_array_table()->get_values());
?>