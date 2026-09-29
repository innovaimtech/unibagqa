<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);
// error_reporting(E_ALL);

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();


require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

$_REQUEST["xval"] = trim(addslashes($_REQUEST["xval"]));

if($_REQUEST["xval"] != "")
{
   if($_REQUEST["xmode"] == "name")
   {
      $sql = " select id, cust_rut, cust_company, cust_name
               from customer
               where
               cust_status > 0 and
               (
                  cust_name                  like '%{$_REQUEST["xval"]}%' or
                  cust_company               like '%{$_REQUEST["xval"]}%'
               )
               order by cust_name
               LIMIT 0,20";
   }
   else
   {
      $sql_rut = str_replace(".", "", $_REQUEST["xval"]);
      $sql = " select id, cust_rut, cust_company, cust_name
               from customer
               where
               cust_status > 0 and
               (
                  REPLACE(cust_rut,'.','')   like '{$sql_rut}%'
               )
               order by cust_name
               LIMIT 0,20";
   }
   $custs = $CON->select($sql);
}
if(count($custs) && $custs != false)
{  ?>
   <table border="0" cellpadding="2" cellspacing="0" width="100%" style="border:1px solid #333333">
   <tr>
      <td class="content_tbl_header">RUT</td>
      <td class="content_tbl_header">
         Nombre
         <span style="float:right">
            <img src="/images/menu/icons/cross-circle-frame.png" style="cursor:pointer"
            onclick="$('#idx_jqcustdata0').html('');$('#idx_jqcustdata1').html('');">
         </span>
      </td>
   </tr>
   <?php
   $x = 0;
   foreach($custs AS $cust)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)" style="cursor:pointer"
      onclick="v2setCustid('<?=$cust["id"]?>')">
         <td class="content_row_os" width="90"><?=$cust["cust_rut"]?></td>
         <td class="content_row_os"><?=$cust["cust_name"]?></td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?php
}
?>