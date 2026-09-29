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
if($_REQUEST["mode"] == "ordersdelivery")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from orders_delivery t1
            LEFT OUTER JOIN user t2 ON t1.dlv_itf_crtusr = t2.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
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

   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "recreate")
   {
      $sql = " update orders_delivery
               set
               dlv_sgntr_end       = 0,
               dlv_sgntr_check     = 0,
               dlv_sgntr_message   = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["autoclearDownload"])
   {
      $sql = " update orders_delivery
               set
               dlv_sgntr_check = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
      exit;
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from orders_delivery t1
            LEFT OUTER JOIN user t2 ON t1.dlv_itf_crtusr = t2.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$_REQUEST["printMode"])
   {
      if((int)$_REQUEST["useSubdetailid"] == 0)
      {
         $headdata["dlv_docnum"]          = $headdata["dlv_modenum"];
         $headdata["dlv_delivery_date"]   = $headdata["dlv_modedate"];
         $headdata["user_firstname"]      = $headdata["mode_user_firstname"];
         $headdata["user_lastname"]       = $headdata["mode_user_lastname"];
      }
      else
      {
         $sql = " select t1.*,
                         t3.user_firstname 'mode_user_firstname', t3.user_lastname 'mode_user_lastname'
                  from orders_delivery_redocs t1
                  LEFT OUTER JOIN user t3 ON t1.dlv_itf_crtusr = t3.id
                  where
                  t1.dlv_id   = {$_REQUEST["id"]} and
                  t1.id       = {$_REQUEST["useSubdetailid"]}";
         $subdoc = $CON->select($sql);
         $subdoc = $subdoc[0];

         $headdata["dlv_docnum"]          = $subdoc["dlv_modenum"];
         $headdata["dlv_itf_hash"]        = $subdoc["dlv_itf_hash"];
         $headdata["dlv_itf_file"]        = $subdoc["dlv_itf_file"];
         $headdata["dlv_delivery_date"]   = $subdoc["dlv_modedate"];
         $headdata["dlv_itf_trndat"]      = $subdoc["dlv_itf_trndat"];
         $headdata["dlv_itf_crtdat"]      = $subdoc["dlv_itf_crtdat"];
         $headdata["user_firstname"]      = $subdoc["mode_user_firstname"];
         $headdata["user_lastname"]       = $subdoc["mode_user_lastname"];
      }
   }

   $tranfolio  = $headdata["dlv_docnum"];
   $trannum    = $headdata["dlv_itf_hash"];
   $tranfile   = $headdata["dlv_itf_file"];
   $trandir    = "ordersdelivery/".date('Y-m', $headdata["dlv_delivery_date"])."/";
   $trancrtdat = date('d.m.Y H:i:s', $headdata["dlv_itf_crtdat"]);
   $trancrtusr = $headdata["user_firstname"]."&nbsp;".$headdata["user_lastname"];
   $trantrndat = $headdata["dlv_itf_trndat"];
   if($trantrndat > 0)
      $trantrndat = "<b class='msg_save_ok'>".date('d.m.Y H:i:s', $trantrndat)."</b>";
   else
   {
      $createBtn  = true;
      $trantrndat = "<b class='msg_save_err'>Error de transferencia</b>";
   }
   
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoicesell")
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "recreate")
   {
      $sql = " update invoices_sell
               set
               invc_sgntr_end       = 0,
               invc_sgntr_check     = 0,
               invc_sgntr_message   = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["autoclearDownload"])
   {
      $sql = " update invoices_sell
               set
               invc_sgntr_check = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
      exit;
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from invoices_sell t1
            LEFT OUTER JOIN user t2 ON t1.invc_itf_crtusr = t2.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tranfolio  = $headdata["invc_docnumber"];
   $trannum    = $headdata["invc_itf_hash"];
   $tranfile   = $headdata["invc_itf_file"];
   $trandir    = "invoicesell/".date('Y-m', $headdata["invc_date"])."/";
   $trancrtdat = date('d.m.Y H:i:s', $headdata["invc_itf_crtdat"]);
   $trancrtusr = $headdata["user_firstname"]."&nbsp;".$headdata["user_lastname"];
   $trantrndat = $headdata["invc_itf_trndat"];
   if($trantrndat > 0)
      $trantrndat = "<b class='msg_save_ok'>".date('d.m.Y H:i:s', $trantrndat)."</b>";
   else
   {
      $createBtn  = true;
      $trantrndat = "<b class='msg_save_err'>Error de transferencia</b>";
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoicesellbol")
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "recreate")
   {
      $sql = " update invoices_sell_bol
               set
               invc_sgntr_end       = 0,
               invc_sgntr_check     = 0,
               invc_sgntr_message   = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["autoclearDownload"])
   {
      $sql = " update invoices_sell_bol
               set
               invc_sgntr_check = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
      exit;
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from invoices_sell_bol t1
            LEFT OUTER JOIN user t2 ON t1.invc_itf_crtusr = t2.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tranfolio  = $headdata["invc_docnumber"];
   $trannum    = $headdata["invc_itf_hash"];
   $tranfile   = $headdata["invc_itf_file"];
   $trandir    = "invoicesell/".date('Y-m', $headdata["invc_date"])."/";
   $trancrtdat = date('d.m.Y H:i:s', $headdata["invc_itf_crtdat"]);
   $trancrtusr = $headdata["user_firstname"]."&nbsp;".$headdata["user_lastname"];
   $trantrndat = $headdata["invc_itf_trndat"];
   if($trantrndat > 0)
      $trantrndat = "<b class='msg_save_ok'>".date('d.m.Y H:i:s', $trantrndat)."</b>";
   else
   {
      $createBtn  = true;
      $trantrndat = "<b class='msg_save_err'>Error de transferencia</b>";
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoicesellnote")
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "recreate")
   {
      $sql = " update invoices_notes_sell
               set
               note_sgntr_end       = 0,
               note_sgntr_check     = 0,
               note_sgntr_message   = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["autoclearDownload"])
   {
      $sql = " update invoices_notes_sell
               set
               note_sgntr_check = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.reloadSiiState();
         parent.$.fancybox.close();
      </script>
      <?php
      exit;
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from invoices_notes_sell t1
            LEFT OUTER JOIN user t2 ON t1.note_itf_crtusr = t2.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tranfolio  = $headdata["note_docnumber"];
   $trannum    = $headdata["note_itf_hash"];
   $tranfile   = $headdata["note_itf_file"];
   $trandir    = "invoicesellnote/".date('Y-m', $headdata["note_date"])."/";
   $trancrtdat = date('d.m.Y H:i:s', $headdata["note_itf_crtdat"]);
   $trancrtusr = $headdata["user_firstname"]."&nbsp;".$headdata["user_lastname"];
   $trantrndat = $headdata["note_itf_trndat"];
   if($trantrndat > 0)
      $trantrndat = "<b class='msg_save_ok'>".date('d.m.Y H:i:s', $trantrndat)."</b>";
   else
   {
      $createBtn  = true;
      $trantrndat = "<b class='msg_save_err'>Error de transferencia</b>";
   }
}

$trandir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electr/{$trandir}";
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("box1", "490")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos: Documento electronico</td>
</tr>
<tr>
   <td class="content_rowl">Folio</td>
   <td class="content_row"><?=$tranfolio?></td>
</tr>
<tr>
   <td class="content_rowl">Transacción</td>
   <td class="content_row"><?=$trannum?></td>
</tr>
<tr>
   <td class="content_rowl">Archivo</td>
   <td class="content_row"><?=$tranfile?></td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=$trancrtdat?></td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$trancrtusr?></td>
</tr>
<tr>
   <td class="content_rowl">Transferido</td>
   <td class="content_row"><?=$trantrndat?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<table border="0" cellpadding="3" cellspacing="0" width="490">
<tr>
   <td class="content_row_clear">
      <?php
      printButton("Descargar archivo", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$tranfile}&name={$tranfile}&path={$trandir}'", "document", 160);
      ?>
   </td>
   <td class="content_row_clear" align="right">
      <?php
      printButton("Transferir denuevo", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) location.href='docelectr.traninfo.fancy.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}&printMode={$_REQUEST["printMode"]}&useSubdetailid={$_REQUEST["useSubdetailid"]}&exec=recreate'", "tick-circle-frame", 160);
      ?>
   </td>
</tr>
</table>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>