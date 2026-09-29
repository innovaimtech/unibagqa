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

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

echo md5(microtime());


$amount  = sprintf("%.2f", str_replace(",",".", $_REQUEST["value"]));
$data    = file_get_contents("http://si3.bcentral.cl/indicadoresvalores/secure/indicadoresvalores.aspx");
if($_REQUEST["mode"] == "USD")
{
   $data    = substr($data, strpos($data, "lar Observado</span>"));
   $data    = substr($data, strpos($data, "</span>") +7);
   $data    = trim(substr($data, 0, strpos($data, "</span>")));
   $data    = substr($data, strrpos($data, ">") +1);
   $data    = sprintf("%.2f", str_replace(",",".",$data));
   $result  = round($amount * $data,0);
}
elseif($_REQUEST["mode"] == "EUR")
{
   $data    = substr($data, strpos($data, ">Euro</span>"));
   $data    = substr($data, strpos($data, "</span>") +7);
   $data    = trim(substr($data, 0, strpos($data, "</span>")));
   $data    = substr($data, strrpos($data, ">") +1);
   $data    = sprintf("%.2f", str_replace(",",".",$data));
   $result  = round($amount * $data,0);
}

if($amount > 0.00 && $data > 0.00 && $result > 0.00)
{  ?>
   <script language="JavaScript">
       parent.document.getElementById('item_costprice_netto_<?=$_REQUEST["rowcount"]?>').value='<?=printPrice($result)?>';
   </script>
   <?php
}