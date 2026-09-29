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
         from invoices_notes_sell
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "invcnum_") !== false && strpos($reqkey, "invcnum_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $sqlinvcnum = str_replace(",","",trim($_REQUEST["invcnum_{$idx}"]));
         $sqlinvcdat = trim($_REQUEST["invcdat_{$idx}"]);

         if($sqlinvcnum != "" && $sqlinvcdat != "")
         {
            $invcrelid = 0;

            if($_REQUEST["note_type_contype"] == 0 || $_REQUEST["note_type_contype"] == 4)
            {
               $sql = " select id
                        from invoices_sell
                        where
                        invc_company_id   = {$headdata["note_company_id"]} and
                        invc_shop_id      = {$headdata["note_shop_id"]} and
                        invc_cust_id      = {$headdata["note_cust_id"]} and
                        invc_docnumber    = '{$sqlinvcnum}' and
                        invc_status       > 1 and
                        invc_status       < 4";
               $invcrelid = $CON->select($sql);
               $invcrelid = (int)$invcrelid[0]["id"];
            }
            elseif($_REQUEST["note_type_contype"] == 1 || $_REQUEST["note_type_contype"] == 5)
            {
               $sql = " select id
                        from invoices_notes_sell
                        where
                        note_company_id   = {$headdata["note_company_id"]} and
                        note_shop_id      = {$headdata["note_shop_id"]} and
                        note_cust_id      = {$headdata["note_cust_id"]} and
                        note_docnumber    = '{$sqlinvcnum}' and
                        note_type         = 1 and
                        note_status       > 1 and
                        note_status       < 4";
               $invcrelid = $CON->select($sql);
               $invcrelid = (int)$invcrelid[0]["id"];
            }
            elseif($_REQUEST["note_type_contype"] == 2 || $_REQUEST["note_type_contype"] == 6)
            {
               $sql = " select id
                        from invoices_notes_sell
                        where
                        note_company_id   = {$headdata["note_company_id"]} and
                        note_shop_id      = {$headdata["note_shop_id"]} and
                        note_cust_id      = {$headdata["note_cust_id"]} and
                        note_docnumber    = '{$sqlinvcnum}' and
                        note_type         = 2 and
                        note_status       > 1 and
                        note_status       < 4";
               $invcrelid = $CON->select($sql);
               $invcrelid = (int)$invcrelid[0]["id"];
            }

            $sqlinvcnumstr .= $sqlinvcnum."-".$sqlinvcdat.",";
         }
         
      }
   }
   $sqlinvcnumstr = substr($sqlinvcnumstr, 0, -1);
   
   $sql = " update invoices_notes_sell
            set
            note_invcnumber = '{$sqlinvcnumstr}'
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   if(!$noClose)
   {  ?>
      <script language="JavaScript">
         parent.$.fancybox.close();
         parent.document.form_shppos.submit();
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from invoices_notes_sell
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$note_invcnumber  = $headdata["note_invcnumber"];
$temp = explode(",", $note_invcnumber);

$idx = 0;
for($x = 0; $x < count($temp); $x++)
{
   if($temp[$x] != "")
   {
      $arr = explode("-", $temp[$x]);
      $note_invcnumbers[$idx] = $arr[0];
      $note_invcdates[$idx] = $arr[1];
      $idx++;
   }
}
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
<form action="invcnumbers.fancy.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="note_type_contype" value="<?=$_REQUEST["note_type_contype"]?>">
<?=Nifty_printH("box1", "99%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Números: <?=getInvoiceSellNoteConType($_REQUEST["note_type_contype"])?></td>
</tr>
<tr>
   <td class="content_row_os content_tbl_subheader">Número</td>
   <td class="content_row_os content_tbl_subheader">Emissión</td>
   <td class="content_row_os content_tbl_subheader">Número</td>
   <td class="content_row_os content_tbl_subheader">Emissión</td>
</tr>
<?php
$xfcnt = 0;
for($x = 0; $x < 6; $x++)
{  ?>
   <tr>
      <td class="content_row_os">
         <input type="text" class="text" name="invcnum_<?=$xfcnt?>" style="width:90px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=$note_invcnumbers[$xfcnt]?>">
      </td>
      <td class="content_row_os">
         <input type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
         name="invcdat_<?=$xfcnt?>" id="invcdat_<?=$xfcnt?>" style="width:90px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=$note_invcdates[$xfcnt]?>">
      </td>
      <?php $xfcnt++ ?>
      <td class="content_row_os">
         <input type="text" class="text" name="invcnum_<?=$xfcnt?>" style="width:90px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=$note_invcnumbers[$xfcnt]?>">
      </td>
      <td class="content_row_os">
         <input type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
         name="invcdat_<?=$xfcnt?>" id="invcdat_<?=$xfcnt?>" style="width:90px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=$note_invcdates[$xfcnt]?>">
      </td>
      <?php $xfcnt++ ?>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="right" width="130">
      <?php
      printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "tick-circle-frame");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</body>
</html>