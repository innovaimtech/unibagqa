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
require_once("../../../libs/classes/invc.npgspf.php");
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

if($_REQUEST["savemodetext"] == "1")
{
   $_REQUEST["dlv_modetext"]  = addslashes($_REQUEST["dlv_modetext"]);
   $_REQUEST["dlv_modenum"]   = trim(addslashes($_REQUEST["dlv_modenum"]));
   $_REQUEST["dlv_modedate"]  = trim(addslashes($_REQUEST["dlv_modedate"]));
   $_REQUEST["dlv_modedate"]  = explode(".", $_REQUEST["dlv_modedate"]);
   $_REQUEST["dlv_modedate"]  = (int)mktime(15,0,0,$_REQUEST["dlv_modedate"][1],$_REQUEST["dlv_modedate"][0],$_REQUEST["dlv_modedate"][2]);

   if(!$_REQUEST["dlv_modedate"])
      $_REQUEST["dlv_modedate"] = time();

   $sql = " select *
            from orders_delivery
            where
            id = {$_REQUEST["id"]}";
   $odata = $CON->select($sql);
   $odata = $odata[0];

   if($odata["dlv_modenum"] != "")
   {
      $sql = " insert into orders_delivery_redocs
               (dlv_id, dlv_modetext, dlv_modenum, dlv_modedate)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["dlv_modetext"]}',
                '{$_REQUEST["dlv_modenum"]}', {$_REQUEST["dlv_modedate"]})";
      $CON->no_result($sql);

      $sql = " select MAX(id) 'id'
               from orders_delivery_redocs
               where
               dlv_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["id"];

      $xurl = "printraw.data.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}&printMode={$_REQUEST["printMode"]}&showDiff={$_REQUEST["showDiff"]}&useSubdetailid={$thisid}";
      ?>
      <script language="JavaScript">
         location.href='<?=$xurl?>';
      </script>
      <?php
   }
   else
   {
      $sql = " update orders_delivery
               set
               dlv_modetext   = '{$_REQUEST["dlv_modetext"]}',
               dlv_modenum    = '{$_REQUEST["dlv_modenum"]}',
               dlv_modedate   = {$_REQUEST["dlv_modedate"]}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      $xurl = "printraw.data.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}&printMode={$_REQUEST["printMode"]}&showDiff={$_REQUEST["showDiff"]}&useSubdetailid=0";
      ?>
      <script language="JavaScript">
         location.href='<?=$xurl?>';
      </script>
      <?php
   }
}

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
      <td class="content_row_clear"><pre><font style="font-size:10px"><?php
}

$sql = " select t1.*, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t9.pay_title
         from orders_delivery t1
         LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
         LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
         LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
         LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
         LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$sql = " select t1.invc_docnumber
         from invoices_sell t1
         INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
         where
         t1.invc_status > 0 and
         t2.part_dlv_id = {$headdata["id"]}";
$refinvcdoc = $CON->select($sql);
$refinvcdoc = $refinvcdoc[0]["invc_docnumber"];
      
$numpos = Array();
if($headdata["dlv_modenum"] != "")
{
   $temp["id"]                   = 0;
   $temp["dlv_modenum"]          = $headdata["dlv_modenum"];
   $temp["dlv_modedate"]         = $headdata["dlv_modedate"];
   $temp["dlv_modetext"]         = $headdata["dlv_modetext"];
   $temp["dlv_modedel"]          = $headdata["dlv_modedel"];

   $numpos[] = $temp;
   $hasData = true;
}

if($_REQUEST["showDiff"] == "1")
   printDeliveryDiff($CON, $_REQUEST["id"], $_REQUEST["diffMode"]);
else
{
   if((int)$_REQUEST["execITF"] && (int)$_REQUEST["printMode"])
   {
      $_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["dlv_company_id"]);
      if((int)$_INVCCFG["company_invc_mode"])
      {
         $dlv_docnum = (int)createTransactionNumber($CON, $headdata["dlv_company_id"], "itf_dlv");
         if((int)$_REQUEST["useSubdetailid"] == 0)
         {
            $sql = " update orders_delivery
                     set dlv_modenum = '{$dlv_docnum}'
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
            $numpos[0]["dlv_modenum"] = $dlv_docnum;
         }
         else
         {
            $sql = " update orders_delivery_redocs
                     set dlv_modenum = '{$dlv_docnum}'
                     where
                     dlv_id = {$_REQUEST["id"]} and
                     id = {$_REQUEST["useSubdetailid"]}";
            $CON->no_result($sql);
            for($x = 0; $x < count($numpos) && $numpos != false; $x++)
            {
               if($numpos[$x]["id"] == $_REQUEST["useSubdetailid"])
                  $numpos[$x]["dlv_modenum"] = $dlv_docnum;
            }
         }
         if($_INVCCFG["company_invc_itf"] == "BCNCONS")
         {
            $_EDOC = new NPGSPF($CON, $_INVCCFG);
            $_EDOC->createOrdersDelivery($_REQUEST["id"], $_REQUEST["printMode"], $_REQUEST["useSubdetailid"], $numpos);
            unset($_EDOC);

            $link = "printraw.data.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}&printMode={$_REQUEST["printMode"]}&showDiff={$_REQUEST["showDiff"]}&useSubdetailid={$_REQUEST["useSubdetailid"]}&ruid=".md5(microtime());
            ?>
            <script language="JavaScript">
               location.href='<?=$link?>';
            </script>
            <?php
            exit;
         }
      }
      printOrdersDelivery($CON, $_REQUEST["id"], $_REQUEST["mode"], $_REQUEST["printMode"], $_REQUEST["useSubdetailid"], $numpos);
   }
   else
      printOrdersDelivery($CON, $_REQUEST["id"], $_REQUEST["mode"], $_REQUEST["printMode"], $_REQUEST["useSubdetailid"], $numpos);
}

if($_REQUEST["mode"] == "preview")
{
   ?></font></pre></td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "860")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <?php
   if($_REQUEST["showDiff"] != "1" && $_REQUEST["printMode"] == "1")
   {
       

      if($headdata["dlv_modetext"] == "")
         $headdata["dlv_modetext"] = "GUIA SIN VALOR COMERCIAL\nEMITIDA PARA RESPALDO FACTURA {$refinvcdoc}";
      ?>
      <tr>
         <td colspan="4" class="content_row_clear" valign="top" height="65">
            <style type="text/css"><!-- @import url(/libs/jscripts/datepicker/datepicker.css); //--></style>
            <script language="JavaScript" src="/libs/jscripts/datepicker/datepicker.js"></script>
            <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
            <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.mousewheel-3.0.4.pack.js"></script>
            <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.pack.js"></script>
            <link rel="stylesheet" type="text/css" href="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.css" media="screen" />
            <script language="Javascript">
               <?php
               require_once("../../jscripts/sourcen.php");
               ?>
            </script>
            <input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
            <input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
            <input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
            <input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
            <form action="printraw.data.php" method="post" name="xform_shp"
            onsubmit="return checkform(new Array(this.dlv_modetext, this.dlv_modedate, this.dlv_modenum))">
            <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
            <input type="hidden" name="showDiff" value="<?=$_REQUEST["showDiff"]?>">
            <input type="hidden" name="printMode" value="<?=$_REQUEST["printMode"]?>">
            <input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
            <input type="hidden" name="savemodetext" value="1">
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" valign="top" width="50" rowspan="2"><b>Glosa</b></td>
               <td class="content_row_clear" width="400" rowspan="2">
                  <textarea name="dlv_modetext" class="text" style="width:390px;height:40px"><?=stripslashes($headdata["dlv_modetext"])?></textarea>
               </td>
               <td class="content_row_clear" valign="top" width="50"><b>Número</b></td>
               <td class="content_row_clear" width="100" valign="top">
                  <input type="text" name="dlv_modenum" class="text" style="width:90px;" value="PENDIENTE" readonly>
               </td>
               <td class="content_row_clear" valign="top" rowspan="2">
                  <?php
                  printButton("Agregar Guia", "postnav_save", "javascript: void(0)", "submitForm(document.xform_shp)", "disk-black", 120);
                  ?>
               </td>
            </tr>
            <tr>
               <td class="content_row_clear" valign="top" width="50"><b>Fecha</b></td>
               <td class="content_row_clear" width="100" valign="top">
                  <input type="text" name="dlv_modedate" style="width:70px;"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  value="<?=date('d.m.Y')?>">
               </td>
            </tr>
            </table>
            </form>
         </td>
      </tr>
      <?php
   }
   ?>
   <tr>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "javascript: void(0);window.close()", "", "arrow-180", 230);
         ?>
      </td>
      <td class="content_row_clear" align="center">
         <?php
         if($_REQUEST["showDiff"] == "1" || $_SESSION["user_printer_name"] == "")
            echo "&nbsp;";
         else
         {  ?>
            <nobr>
            <input type="hidden" id="idx_print_res" value="<?=(int)$_REQUEST["printMode"]?>">
            <input type="radio" name="idx_print_res_sel" onclick="document.getElementById('idx_print_res').value='0';location.href='/libs/modules/orders_delivery/printraw.data.php?id=<?=$_REQUEST["id"]?>&mode=preview&printMode=0';" <?php if(!(int)$_REQUEST["printMode"]) echo "checked"?>> Impresion normal
            <input type="radio" name="idx_print_res_sel" onclick="document.getElementById('idx_print_res').value='1';location.href='/libs/modules/orders_delivery/printraw.data.php?id=<?=$_REQUEST["id"]?>&mode=preview&printMode=1';" <?php if((int)$_REQUEST["printMode"]) echo "checked"?>> Guia de respaldo
            </nobr>
            <?php
         }
         ?>
      </td>
      <?php
      if($_REQUEST["showDiff"] == "1")
      {  ?>
         <td width="130" align="right" style="padding-right:3px">
         <?php
         printButton("Modificar pendientes", "postnav", "/libs/modules/invoices_sell/printraw.shipped.modify.php?id={$_REQUEST["id"]}&diffMode={$_REQUEST["diffMode"]}", "", "document", 230);
         ?>
         </td>
         <?php
      }
      ?>
      <td align="right">
         <?php
         if($_SESSION["user_printer_name"] != "")
            printButton("Ejecutar impresion punto matriz", "postnav_save", "javascript: void(0)", "opener.document.getElementById('idxifrsrc').src = './libs/modules/orders_delivery/printraw.exec.php?id={$_REQUEST["id"]}&showDiff={$_REQUEST["showDiff"]}&diffMode={$_REQUEST["diffMode"]}&printMode=' +document.getElementById('idx_print_res').value +'&useSubdetailid={$_REQUEST["useSubdetailid"]}';window.close()", "script", 230);
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