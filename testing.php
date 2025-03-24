<?php
require_once "wrapper.php";
use Wrapper\MySQLi\ {
    Access, Table
};
//include("lib.php");
$test = new Access("localhost", "xmanousek", "Ylrq2117", "xmanousek", 7070);
// $test->delete("test", ["test"], ["16"]);
// $test->insert("test", ["stra"], ["a"]);
$test->select("test", "*");
$a = new Table(array());
echo var_dump($test->get("result"));
echo $test->get_public_properties();
?>