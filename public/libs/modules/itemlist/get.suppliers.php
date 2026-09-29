<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

$sql = " select t1.*, t2.supp_company
         from itemlist_suppliers t1
         LEFT OUTER JOIN supplier t2 ON t1.supplier_id = t2.id
         where
         t1.item_id     = {$_REQUEST["itemid"]} and
         t2.supp_status = 1
         order by item_supp_act desc";
$suppliers = $CON->select($sql);

foreach($suppliers AS $supplier)
{  ?>
   <option value="<?=$supplier["supplier_id"]?>"><?=$supplier["supp_company"]?></option>
   <?php
}
?>