<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2015 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/classes/invc.npgspf.php");
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

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_sell")
{
   $_tablename = "invoices_sell";
   $_prefix    = "invc";
   $_docnum    = "invc_docnumber";
   $_fancymode = "invoicesell";
   $_btnname   = "Factura";
   $_mid       = 751;
}
//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_sell_bol")
{
   $_tablename = "invoices_sell_bol";
   $_prefix    = "invc";
   $_docnum    = "invc_docnumber";
   $_fancymode = "invoicesellbol";
   $_btnname   = "Boleta";
   $_mid       = 984;
}
//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_sell_notes")
{
   $_tablename = "invoices_notes_sell";
   $_prefix    = "note";
   $_docnum    = "note_docnumber";
   $_fancymode = "invoicesellnote";
   $_btnname   = "Nota";
   $_mid       = 755;
}
//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "orders_delivery")
{
   $_tablename = "orders_delivery";
   $_prefix    = "dlv";
   $_docnum    = "dlv_docnum";
   $_fancymode = "ordersdelivery";
   $_btnname   = "Guia";
   $_mid       = 748;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from {$_tablename}
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
?>
<div style="display:none"><?=md5(microtime())?></div>
<?php
if(!(int)$headdata["{$_prefix}_sgntr_end"] || $headdata["{$_prefix}_sgntr_doc1"] == "")
{  ?>
   <script language="JavaScript">
      setTimeout(reloadSiiState, 3000);
   </script>
   <?php
}
if($headdata["{$_prefix}_itf_hash"] == "")
{  ?>
   <img src="/images/content/loading.gif" width="18" style="vertical-align:bottom">&nbsp;Esperando Comunicación...
   <?php
}
elseif($headdata["{$_prefix}_itf_hash"] != "" && !(int)$headdata["{$_prefix}_sgntr_end"])
{  ?>
   <img src="/images/content/loading.gif" width="18" style="vertical-align:bottom">&nbsp;Procesando Documento...
   <?php
}
elseif((int)$headdata["{$_prefix}_sgntr_end"] && (int)$headdata["{$_prefix}_itf_trndat"])
{
   if($headdata["{$_prefix}_sgntr_doc1"] != "")
   {  ?>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear"><?=$headdata[$_docnum]?></td>
         <td class="content_row_clear" width="1" style="padding-right:5px">
            <?php
            if($headdata["{$_prefix}_sgntr_doc1"] != "")
               printButton($_btnname, "postnav", "index.php?mid={$_mid}&exec=edit&subexec=edit&id={$headdata["id"]}&printsiidoc1=1\" style=\"padding-bottom:3px;padding-top:3px", "", "document-pdf", 80);
            ?>
         </td>

         <td class="content_row_clear" width="1" style="padding-right:5px">
            <?php
            if($headdata["{$_prefix}_sgntr_doc2"] != "" && $_REQUEST["mode"] != "invoices_sell_bol" && date('Ymd', $headdata["invc_date"]) > '20250531' ) 
               printButton("Cedible", "postnav", "index.php?mid={$_mid}&exec=edit&subexec=edit&id={$headdata["id"]}&printsiidoc2=1\" style=\"padding-bottom:3px;padding-top:3px", "", "document-pdf", 80);
            ?>
         </td>
         <!--
         <td class="content_row_clear" width="1">
            <?php
            printButton("", "postnav", "index.php?mid={$_mid}&exec=edit&subexec=edit&id={$headdata["id"]}&reinitDocs=1\" style=\"padding-bottom:3px;padding-top:3px", "", "arrow-circle-315", 1);
            ?>
         </td>
         -->
      </tr>
      </table>
      <?php
   }
   elseif($headdata["{$_prefix}_itf_hash"] != "")
   {
      if((int)$headdata["{$_prefix}_sgntr_check"] >= 5)
      {
         $url = "javascript:showFancybox('/libs/modules/invoices_sell/docelectr.traninfo.fancy.php?id={$headdata["id"]}&mode={$_fancymode}&autoclearDownload=1', 'iframe', 500, 300, 'no')";
         ?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear"><?=$headdata[$_docnum]?></td>
            <td class="content_row_clear" align="right">
               <a href="<?=$url?>" class='link'><b class='msg_save_err'><blink>[Error: PDF no disponible]</blink></b></a><br>
            </td>
         </tr>
         </table>
         <?php
      }
      else
      {  
         ?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear"><?=$headdata[$_docnum]?></td>
            <td class="content_row_clear" align="right">
               <img src="/images/content/loading.gif" width="18" style="vertical-align:bottom">&nbsp;
               Confirmado, Esperando PDF's&nbsp;
            </td>
            <td class="content_row_clear" align="right">
               <?php
                  printButton("", "postnav", "index.php?mid={$_mid}&exec=edit&subexec=edit&id={$headdata["id"]}&reinitDocs=1\" style=\"padding-bottom:3px;padding-top:3px", "", "arrow-circle-315", 1);
               ?>
            </td>
         </tr>
         </table>
         <?php
      }
   }
   else
   {  ?>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear"><?=$headdata[$_docnum]?></td>
      </tr>
      </table>
      <?php
   }
}
elseif((int)$headdata["{$_prefix}_sgntr_end"] && !(int)$headdata["{$_prefix}_itf_trndat"])
{
   $url = "javascript:showFancybox('/libs/modules/invoices_sell/docelectr.traninfo.fancy.php?id={$headdata["id"]}&mode={$_fancymode}', 'iframe', 500, 300, 'no')";
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td class="content_row_clear">
         <a href="<?=$url?>" class='link'><b class='msg_save_err'><blink>[Error: Documento electronico]</blink></b></a><br>
         <?=date('d-m-Y H:i:s', $headdata["{$_prefix}_itf_crtdat"])?>:<br>
         <?=$headdata["{$_prefix}_sgntr_message"]?>
      </td>
   </tr>
   </table>
   <?php
}
?>