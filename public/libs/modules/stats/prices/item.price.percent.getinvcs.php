<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../classes/page.php");
require_once("../../../classes/mysql.php");
require_once("../../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../functions.php");

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

$tstamp = explode(".", $_REQUEST["xdate"]);
$dstart = mktime(0,0,0,$tstamp[1],$tstamp[0],$tstamp[2]);
$dend   = mktime(23,59,59,$tstamp[1],$tstamp[0],$tstamp[2]);
$sql = " select distinct invc_docnumber
         from invoices_sell
         where
         invc_status > 1 and
         invc_date between {$dstart} and {$dend}
         order by invc_docnumber asc";
$invcs = $CON->select($sql);
?>
<option value="">Seleccionar</option>
<?php
for($x = 0; $x < count($invcs) && $invcs != false; $x++)
{  ?>
   <option value="<?=$invcs[$x]["invc_docnumber"]?>" <?php if($_REQUEST["sql_invcsellnum"] == $invcs[$x]["invc_docnumber"]) echo "selected"?>><?=$invcs[$x]["invc_docnumber"]?></option>
   <?php
}
