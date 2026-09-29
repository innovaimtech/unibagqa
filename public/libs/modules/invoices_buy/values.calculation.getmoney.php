<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

echo md5(microtime());

switch($_REQUEST["cost_money"])
{
   case "YEN": $dbfield = "exc_yenval"; break;
   case "USD": $dbfield = "exc_usdval"; break;
   case "EUR": $dbfield = "exc_eurval"; break;
}

$dend = getDateFromString($_REQUEST["xdate"], false);

$sql = " select {$dbfield} 'moneyval'
         from money_exchange
         where
         exc_tstamp <= {$dend}
         order by exc_tstamp desc
         LIMIT 0,1";
$usdval = $CON->select($sql);
$usdval = (float)$usdval[0]["moneyval"];
?>
<script language="JavaScript">
    parent.document.getElementById('cost_exchangevalue_<?=$_REQUEST["rowcount"]?>').value='<?=printPrice($usdval,4)?>';
</script>
<?php