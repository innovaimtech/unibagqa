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

session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

$sql = " select lang_filename
         from config_lang
         where
         id = {$conf["conf_lang"]}";
$lang = $CON->select($sql);
$_SESSION["_CONF"]["conf_lang_filename"] = $lang[0]["lang_filename"];

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

if($_REQUEST["mode"] == "preview")
{  ?>
   <html>
   <head>
      <title>Prevista impresión</title>
      <style type="text/css">
      html { height: 100%; }
      <?php $_SESSION["_PAGE"]->printStyle() ?>
      </style>
   </head>
   <body class="page_content" style="background-color:#EEEEEE">
   <center>
   <br>
   <?=Nifty_printH("box1", "860")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header">Prevista impresión</td>
   </tr>
   <tr>
      <td class="content_row_clear"><pre><font style="font-size:10px"><br><?php
}

if($_REQUEST["showDiff"] == "1")
   printDeliveryDiff($CON, $_REQUEST["id"], $_REQUEST["diffMode"], $_REQUEST["preCalc"]);
else
   printInvoiceSell($CON, $_REQUEST["id"], $_REQUEST["mode"]);

if($_REQUEST["mode"] == "preview")
{
   ?></font></pre></td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "860")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "javascript: void(0);window.close()", "", "arrow-180", 230);
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if($_REQUEST["showDiff"] == "1" && !(int)$_REQUEST["preCalc"])
      {  ?>
         <td width="130" align="right" style="padding-right:3px">
         <?php
         printButton("Modificar pendientes", "postnav", "/libs/modules/invoices_sell/printraw.shipped.modify.php?id={$_REQUEST["id"]}&diffMode={$_REQUEST["diffMode"]}", "", "document", 230);
         ?>
         </td>
         <?php
      }
      ?>
      <td align="right" width="130">
         <?php
         if($_SESSION["user_printer_name"] != "")
            printButton("Ejecutar impresion punto matriz", "postnav_save", "javascript: void(0)", "opener.document.getElementById('idxifrsrc').src = './libs/modules/orders_delivery/printraw.exec.php?id={$_REQUEST["id"]}&module=invoices_sell&showDiff={$_REQUEST["showDiff"]}&diffMode={$_REQUEST["diffMode"]}&preCalc={$_REQUEST["preCalc"]}';window.close()", "script", 230);
         else
            echo "<b class=msg_save_err>Para imprimir: Ingresa nombre de la impresora en el perfil del usuario</b>";
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   </center>
   <br>
   </body>
   </html>
   <?php
}
?>