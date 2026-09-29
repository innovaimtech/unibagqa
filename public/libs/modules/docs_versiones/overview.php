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
            from docs_versiones
            where
            id = {$_REQUEST["doc_id"]}";
   $orgfile = $CON->select($sql);
   
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/{$_REQUEST["mode"]}/";

   unlink("{$doc_dir}{$orgfile[0]["doc_file"]}");

   $sql = " delete from docs_versiones
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
         from docs_versiones t1
         LEFT OUTER JOIN docs_versiones_types t2 ON t1.doc_typeid = t2.id
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
      <td class="content_tbl_subheader">Descripci�n</td>
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
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>