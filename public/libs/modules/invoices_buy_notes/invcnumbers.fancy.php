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
$sql = " select *
         from invoices_notes_buy
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_buy
            set
            note_multiinvc_desc = 0
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach($_REQUEST["invcnum"] AS $sqlinvcnum)
   {
      $sqlinvcnum = trim(addslashes($sqlinvcnum));
      if($sqlinvcnum != "")
      {
         $invcrelid = 0;

         $sql = " select id, invc_total_netto_dsc
                  from invoices_buy
                  where
                  invc_company_id   = {$headdata["note_company_id"]} and
                  invc_shop_id      = {$headdata["note_shop_id"]} and
                  invc_supplier_id  = {$headdata["note_supplier_id"]} and
                  invc_docnumber    = '{$sqlinvcnum}' and
                  invc_status       > 1 and
                  invc_status       < 4";
         $invcrelid = $CON->select($sql);
         $invcrelat = (float)$invcrelid[0]["invc_total_netto_dsc"];
         $invcrelid = (int)$invcrelid[0]["id"];

         if(!$invcrelid)
         {  ?>
            <script language="JavaScript">
               alert('ERROR: EL DOCUMENTO <?=$sqlinvcnum?> NO EXISTE!');
            </script>
            <?php
            $noClose = true;
         }
         else
         {
            $_RELS[$invcrelid] = $sqlinvcnum;
            $_RELV[$invcrelid] = $invcrelat;

            $sql = " select note_total_netto, note_type
                     from invoices_notes_buy
                     where
                     note_status > 1 and
                     note_parent_invcid = {$invcrelid} and
                     id != {$_REQUEST["id"]}";
            $asnotes = $CON->select($sql);
            foreach($asnotes AS $asnote)
            {
               if($asnote["note_type"] == 1)
                  $_RELV[$invcrelid] -= $asnote["note_total_netto"];
               elseif($asnote["note_type"] == 2)
                  $_RELV[$invcrelid] += $asnote["note_total_netto"];
            }
         }
      }
   }
   if(!$noClose)
   {
      $sql = " delete from invoices_notes_buy_otherdscinvc
               where
               note_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      foreach(array_keys($_RELS) AS $relid)
      {
         $descval = round($_RELV[$relid] / 100 * $headdata["note_discount_perc"],0);
         $_TOTALNOTEDESC += $descval;
         $sql = " insert into  invoices_notes_buy_otherdscinvc
                  (note_id, note_parent_invcid, note_parent_invcnum, note_parent_invcamt, note_parent_invcdsc)
                  VALUE
                  ({$_REQUEST["id"]}, {$relid}, '{$_RELS[$relid]}', {$_RELV[$relid]}, {$descval})";
         $CON->no_result($sql);
      }

      $_TOTALNOTEDESC = round($_TOTALNOTEDESC / 100 * (100 + $_SESSION["_CONF"]["conf_taxes"]),0);

      //----------------------------------------------------------------------------------
      $sql = " update invoices_notes_buy
               set
               note_multiinvc_desc = {$_TOTALNOTEDESC}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from invoices_notes_buy
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$sql = " select *
         from invoices_notes_buy_otherdscinvc
         where
         note_id = {$_REQUEST["id"]}
         order by 3 asc";
$note_invcnumbers = $CON->select($sql);

$sql = " select invc_total_netto_dsc
         from invoices_buy
         where
         id = {$headdata["note_parent_invcid"]}";
$maininvcnetto = $CON->select($sql);
$maininvcnetto = (float)$maininvcnetto[0]["invc_total_netto_dsc"];
$maindescval   = round($maininvcnetto / 100 * $headdata["note_discount_perc"],0);
$_TOTALDESC += $maindescval;
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
   <style type="text/css"><!-- @import url(/libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="/libs/jscripts/datepicker/datepicker.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="autofocus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?php
if($headdata["note_status"] == 1)
{  ?>
   <form action="invcnumbers.fancy.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst">
   <?php
}
else
{  ?>
   <form action="invcnumbers.fancy.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst"
   onsubmit="return false">
   <?php
}
?>
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="note_type_contype" value="<?=$_REQUEST["note_type_contype"]?>">
<?=Nifty_printH("box1", "99%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Números: <?=getInvoiceSellNoteConType($_REQUEST["note_type_contype"])?></td>
</tr>
<tr>
   <td class="content_row_os content_tbl_subheader">Número</td>
   <td class="content_row_os content_tbl_subheader">Monto/Factura</td>
   <td class="content_row_os content_tbl_subheader"><?=printPrice($headdata["note_discount_perc"],2)?>% / Nota de Credito</td>
</tr>
<tr>
   <td class="content_row_os"><?=$headdata["note_invcnumber"]?></td>
   <td class="content_row_os">$ <?=printPrice($maininvcnetto)?></td>
   <td class="content_row_os">$ <?=printPrice($maindescval)?></td>
</tr>
<?php
$xfcnt = 0;
for($x = 0; $x < 12; $x++)
{
   ?>
   <tr>
      <td class="content_row_os">
         <input type="text" class="text" name="invcnum[]" style="width:110px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=$note_invcnumbers[$x]["note_parent_invcnum"]?>">
      </td>
      <td class="content_row_os">$ <?=printPrice($note_invcnumbers[$x]["note_parent_invcamt"])?></td>
      <td class="content_row_os">$ <?=printPrice($note_invcnumbers[$x]["note_parent_invcdsc"])?></td>
   </tr>
   <?php
   $_TOTALDESC += $note_invcnumbers[$x]["note_parent_invcdsc"];
   $xfcnt++;
}
?>
<tr>
   <td class="content_row_os content_row_totals">TOTAL NC</td>
   <td class="content_row_os content_row_totals">&nbsp;</td>
   <td class="content_row_os content_row_totals">$ <?=printPrice($_TOTALDESC)?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="right" width="130" style="padding-right:5px">
      <?php
      if($headdata["note_status"] == 1)
         printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "tick-circle-frame");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</body>
</html>