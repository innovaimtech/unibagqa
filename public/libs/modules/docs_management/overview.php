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

//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "delTranDoc")
{
   $sql = " select doc_file
            from tran_docs
            where
            id = {$_REQUEST["doc_id"]}";
   $orgfile = $CON->select($sql);
   
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/{$_REQUEST["mode"]}/";

   unlink("{$doc_dir}{$orgfile[0]["doc_file"]}");

   $sql = " delete from tran_docs
            where
            id = {$_REQUEST["doc_id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);

   if($_REQUEST["mode"] == "orders_offers")
   {  ?>
      <script language="JavaScript">
         parent.reloadSendAnex();
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.docto_title,
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from tran_docs t1
         LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
         LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
         where
         t1.doc_tran_id    = {$_REQUEST["id"]} and
         t1.doc_tran_type  = '{$_REQUEST["mode"]}'
         order by t1.id";
$docs = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
   <script language="Javascript">
      <?php
      require_once("../../jscripts/sourcen.php");
      ?>
   </script>
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("boxopt_t", "830")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%" style="padding-top:3px">
<tr>
   <td width="100%" style="padding-right:0px">
      <?php
      if($_REQUEST["subcatexec"] == "file")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Subir archivo", $btype, "overview.php?subcatexec=file&subexec=edit&id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}", "", "arrow-stop-090");
      ?>
   </td>
   <!--
   <td width="50%">
      <?php
      if($_REQUEST["subcatexec"] == "scanning")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Escanear archivo", $btype, "overview.php?subcatexec=scanning&subexec=edit&id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}", "", "scanner");
      ?>
   </td>
   -->
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "edit")
{
   require_once("data.document.php");
}
else
{  ?>
   <?=Nifty_printH("box1", "830")?>
   <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col width="85">
      <col width="90">
      <col width="100">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Documentos relacionados</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Documento</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center" colspan="2">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($docs) && $docs != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row"><nobr><?=$docs[$x]["doc_name"]?>&nbsp;</nobr></td>
         <td class="content_row"><?=$docs[$x]["docto_title"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["doc_desc"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["crt_lastname"]?>&nbsp;</td>
         <td class="content_row"><?=date('d.m.Y',$docs[$x]["doc_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton("Editar", "postnav", "overview.php?subcatexec=file&subexec=edit&id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}&doc_id={$docs[$x]["id"]}", "", "pencil");
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            $docext = strtoupper(substr($docs[$x]["doc_file"], strrpos($docs[$x]["doc_file"], ".")+1));
            if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
               printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/{$_REQUEST["mode"]}/{$docs[$x]["doc_file"]}')", "image");
            else
               printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$docs[$x]["doc_file"]}&name={$docs[$x]["doc_name"]}&path=../../../docs.tran/{$_REQUEST["mode"]}/'", "navigation-270-white");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="7" align="center" valign="middle" height="30">
            <br>
            <b class="msg_save_err">No hay datos disponibles.</b>
            <br><br>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "shipments")
{
   $sql = " select shp_supporder_id
            from shipment
            where
            id = {$_REQUEST["id"]}";
   $shp_supporder_id = $CON->select($sql);
   $shp_supporder_id = (int)$shp_supporder_id[0]["shp_supporder_id"];

   $sql = " select t1.*, t2.docto_title,
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_docs t1
            LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
            LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
            where
            t1.doc_tran_id    = {$shp_supporder_id} and
            t1.doc_tran_type  = 'supplier_order'
            order by t1.id";
   $refdocs = $CON->select($sql);
   if(count($refdocs) && $refdocs != false)
   {  ?>
      <br>
      <?=Nifty_printH("box1", "830")?>
      <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col width="85">
         <col width="190">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Origen</td>
         <td class="content_tbl_subheader">Documento</td>
         <td class="content_tbl_subheader">Tipo</td>
         <td class="content_tbl_subheader">Descripción</td>
         <td class="content_tbl_subheader">Creado por</td>
         <td class="content_tbl_subheader">Creado</td>
         <td class="content_tbl_subheader" align="center">Opciones</td>
      </tr>
      <?php
      for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
      {
         $sql = " select sord_number
                  from supplier_order
                  where
                  id = {$refdocs[$x]["doc_tran_id"]}";
         $sord_number = $CON->select($sql);
         $sord_number = $sord_number[0]["sord_number"];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><nobr><?=$sord_number?></nobr></td>
            <td class="content_row"><nobr><?=$refdocs[$x]["doc_name"]?>&nbsp;</nobr></td>
            <td class="content_row"><?=$refdocs[$x]["docto_title"]?>&nbsp;</td>
            <td class="content_row"><?=$refdocs[$x]["doc_desc"]?>&nbsp;</td>
            <td class="content_row"><?=$refdocs[$x]["crt_lastname"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$refdocs[$x]["doc_crtdat"])?></td>
            <td class="content_row" align="center">
               <?php
               $docext = strtoupper(substr($refdocs[$x]["doc_file"], strrpos($refdocs[$x]["doc_file"], ".")+1));
               if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                  printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$refdocs[$x]["doc_file"]}')", "image");
               else
                  printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$refdocs[$x]["doc_file"]}&name={$refdocs[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
               ?>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_buy")
{

   $sql = " select invc_type
            from invoices_buy
            where
            id = {$_REQUEST["id"]}";
   $invc_type = $CON->select($sql);
   $invc_type = (int)$invc_type[0]["invc_type"];

   //DLV
   $_REFDOCS = Array();
   if($invc_type == 1)
   {
      $sql = " select part_shp_id
               from invoices_buy_parts
               where
               part_invc_id = {$_REQUEST["id"]} and
               part_shp_id > 0
               order by id";
      $spart_shp_ids = $CON->select($sql);
      foreach($spart_shp_ids AS $spart_shp_idrow)
      {
         $sql = " select t1.*, t2.docto_title,
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                  from tran_docs t1
                  LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                  LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                  where
                  t1.doc_tran_id    = {$spart_shp_idrow["part_shp_id"]} and
                  t1.doc_tran_type  = 'shipments'
                  order by t1.id";
         $refdocs = $CON->select($sql);
         for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
         {
            $_REFDOCS[] = $refdocs[$x];
         }
      }

      if(count($_REFDOCS) && $_REFDOCS != false)
      {  ?>
         <br>
         <?=Nifty_printH("box1", "830")?>
         <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
            <col width="190">
         </colgroup>
         <tr>
         </tr>
            <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
         <tr>
            <td class="content_tbl_subheader">Origen</td>
            <td class="content_tbl_subheader">Documento</td>
            <td class="content_tbl_subheader">Tipo</td>
            <td class="content_tbl_subheader">Descripción</td>
            <td class="content_tbl_subheader">Creado por</td>
            <td class="content_tbl_subheader">Creado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($_REFDOCS) && $_REFDOCS != false; $x++)
         {
            $sql = " select shp_supplier_docnum
                     from shipment
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $shp_supplier_docnum = $CON->select($sql);
            $shp_supplier_docnum = $shp_supplier_docnum[0]["shp_supplier_docnum"];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>">
               <td class="content_row"><nobr>Guia: <?=$shp_supplier_docnum?></nobr></td>
               <td class="content_row"><nobr><?=$_REFDOCS[$x]["doc_name"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=$_REFDOCS[$x]["docto_title"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["doc_desc"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["crt_lastname"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$_REFDOCS[$x]["doc_crtdat"])?></td>
               <td class="content_row" align="center">
                  <?php
                  $docext = strtoupper(substr($_REFDOCS[$x]["doc_file"], strrpos($_REFDOCS[$x]["doc_file"], ".")+1));
                  if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                     printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/shipments/{$_REFDOCS[$x]["doc_file"]}')", "image");
                  else
                     printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$_REFDOCS[$x]["doc_file"]}&name={$_REFDOCS[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                  ?>
               </td>
            </tr>
            <?php
            $sql = " select shp_supporder_id
                     from shipment
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $shp_supporder_id = $CON->select($sql);
            $shp_supporder_id = (int)$shp_supporder_id[0]["shp_supporder_id"];

            $sql = " select t1.*, t2.docto_title,
                     t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                     from tran_docs t1
                     LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                     LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                     where
                     t1.doc_tran_id    = {$shp_supporder_id} and
                     t1.doc_tran_type  = 'supplier_order'
                     order by t1.id";
            $subrefdocs = $CON->select($sql);
            for($z = 0; $z < count($subrefdocs) && $subrefdocs != false; $z++)
            {
               $sql = " select sord_number
                        from supplier_order
                        where
                        id = {$subrefdocs[$z]["doc_tran_id"]}";
               $sord_number = $CON->select($sql);
               $sord_number = $sord_number[0]["sord_number"];
               ?>
               <tr bgcolor="<?=getRowColor($x)?>">
                  <td class="content_row"><nobr><?=$sord_number?></nobr></td>
                  <td class="content_row"><nobr><?=$subrefdocs[$z]["doc_name"]?>&nbsp;</nobr></td>
                  <td class="content_row"><?=$subrefdocs[$z]["docto_title"]?>&nbsp;</td>
                  <td class="content_row"><?=$subrefdocs[$z]["doc_desc"]?>&nbsp;</td>
                  <td class="content_row"><?=$subrefdocs[$z]["crt_lastname"]?>&nbsp;</td>
                  <td class="content_row"><?=date('d.m.Y',$subrefdocs[$z]["doc_crtdat"])?></td>
                  <td class="content_row" align="center">
                     <?php
                     $docext = strtoupper(substr($subrefdocs[$x]["doc_file"], strrpos($subrefdocs[$x]["doc_file"], ".")+1));
                     if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                        printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$subrefdocs[$x]["doc_file"]}')", "image");
                     else
                        printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$subrefdocs[$x]["doc_file"]}&name={$subrefdocs[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                     ?>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <?php

      }
   }
   elseif($invc_type == 2)
   {
      $sql = " select part_sord_id
               from invoices_buy_parts
               where
               part_invc_id = {$_REQUEST["id"]} and
               part_sord_id > 0
               order by id";
      $part_sord_ids = $CON->select($sql);
      foreach($part_sord_ids AS $part_sord_idrow)
      {
         $sql = " select t1.*, t2.docto_title,
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                  from tran_docs t1
                  LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                  LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                  where
                  t1.doc_tran_id    = {$part_sord_idrow["part_sord_id"]} and
                  t1.doc_tran_type  = 'supplier_order'
                  order by t1.id";
         $refdocs = $CON->select($sql);
         for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
         {
            $_REFDOCS[] = $refdocs[$x];
         }
      }

      if(count($_REFDOCS) && $_REFDOCS != false)
      {  ?>
         <br>
         <?=Nifty_printH("box1", "830")?>
         <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
            <col width="190">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">Origen</td>
            <td class="content_tbl_subheader">Documento</td>
            <td class="content_tbl_subheader">Tipo</td>
            <td class="content_tbl_subheader">Descripción</td>
            <td class="content_tbl_subheader">Creado por</td>
            <td class="content_tbl_subheader">Creado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($_REFDOCS) && $_REFDOCS != false; $x++)
         {
            $sql = " select sord_number
                     from supplier_order
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $sord_number = $CON->select($sql);
            $sord_number = $sord_number[0]["sord_number"];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>">
               <td class="content_row"><nobr><?=$sord_number?></nobr></td>
               <td class="content_row"><nobr><?=$_REFDOCS[$x]["doc_name"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=$_REFDOCS[$x]["docto_title"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["doc_desc"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["crt_lastname"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$_REFDOCS[$x]["doc_crtdat"])?></td>
               <td class="content_row" align="center">
                  <?php
                  $docext = strtoupper(substr($_REFDOCS[$x]["doc_file"], strrpos($_REFDOCS[$x]["doc_file"], ".")+1));
                  if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                     printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$_REFDOCS[$x]["doc_file"]}')", "image");
                  else
                     printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$_REFDOCS[$x]["doc_file"]}&name={$_REFDOCS[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                  ?>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <?php
      }
   }
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>
