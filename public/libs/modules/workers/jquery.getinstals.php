<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../classes/page.php");
require_once("../../classes/mysql.php");
require_once("../../config.php");


//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../lang/es.php");
require_once("../../functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

$sql = " select distinct t1.id, t1.cust_name, t2.cust_name 'cliente', t1.cust_street, t1.cust_code
         from customer_instalaciones t1
         INNER JOIN customer t2 ON t1.cust_custid = t2.id
         where
         t1.cust_status = 1 and
         t1.cust_custid = {$_REQUEST["custid"]}
         order by t1.cust_name";
$instals = $CON->select($sql);
?>
<option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
<?php
foreach($instals AS $instal)
{  ?>
   <option value="<?=$instal["id"]?>"><?=$instal["cust_name"]?></option>
   <?php
}
