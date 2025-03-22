<?php
require_once "wrapper.php";
use MySQLiWrapper\ {
    Database,
    Table,
    Last
};
//include("lib.php");
$test = new Database("localhost", "xmanousek", "Ylrq2117", "xmanousek", 7070);
// $test->delete("test", ["test"], ["16"]);
// $test->insert("test", ["stra"], ["a"]);
$test->select("test", "*");
echo var_dump($test->get("result"));
echo $test->get_public_properties();
?>