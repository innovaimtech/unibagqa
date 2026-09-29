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
         from prod_item_ext
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   $_REQUEST["dlvnum"] = trim(addslashes($_REQUEST["dlvnum"]));
   if($_REQUEST["dlvnum"] != "")
   {
      $sql = " select *
               from orders_delivery
               where
               dlv_docnum        = '{$_REQUEST["dlvnum"]}' and
               dlv_supplier_id   = {$headdata["req_supplier_id"]} and
               dlv_status        > 0";
      $dlvdata = $CON->select($sql);
      $dlvdata = $dlvdata[0];
      if((int)$dlvdata["id"])
      {
         $poscounter = 0;
         $posdata = getOrderDeliveryPos($CON, $dlvdata["id"]);
         foreach($posdata AS $posrow)
         {
            $sql = " select *
                     from item
                     where
                     id = {$posrow["item_id"]} and
                     item_ext_prod_act = 1 and
                     item_status > 0";
            $itemvalid = $CON->select($sql);
            $itemvalid = $itemvalid[0];
            if((int)$itemvalid["id"] && (int)$itemvalid["item_ext_prod_item_id"])
            {
               $posrow["item_st_id"] = (int)$posrow["item_st_id"];
               $posrow["item_amount_shipped"] = (float)$posrow["item_amount_shipped"];
               $sql = " insert into prod_item_ext_pos
                        (req_id, item_pos, item_id, item_id_dest, item_amount, item_type, item_type_dest,
                         item_st_id, item_desc)
                        VALUES
                        ({$_REQUEST["id"]}, {$poscounter}, {$itemvalid["id"]}, {$itemvalid["item_ext_prod_item_id"]}, {$posrow["item_amount_shipped"]},
                        'item', 'item', {$posrow["item_st_id"]}, '')";
               $CON->no_result($sql);
               $poscounter++;
            }
         }  
      }
      else
         echo "<b class=msg_save_err>Guia no valida</b>";
   }
   if(!(int)$poscounter)
      echo "<b class=msg_save_err>No se encontraron productos validos en la guia.</b>";
   else
   {
      $sql = " update prod_item_ext
               set
               req_dlv_docnum    = '{$dlvdata["dlv_docnum"]}',
               req_delivery_date = {$dlvdata["dlv_delivery_date"]}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         parent.location.href = '/index.php?mid=800&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}
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
<form action="assigndlv.fancy.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst"
onsubmit="return checkform(new Array(this.dlvnum))">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "99%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Ingresar Número de Guia</td>
</tr>
<tr>
   <td class="content_row_os">Número</td>
   <td class="content_row_os">
      <?php
      $sql = " select *
               from orders_delivery
               where
               dlv_supplier_id   = {$headdata["req_supplier_id"]} and
               dlv_status        > 0 and
               dlv_docnum        NOT IN
               (
                  select req_dlv_docnum
                  from prod_item_ext
                  where
                  req_supplier_id = dlv_supplier_id and
                  req_status      > 1
               )
               order by dlv_delivery_date";
      $dlvsels = $CON->select($sql);
      ?>
      <select class="text" name="dlvnum" style="width:350px">
         <option value="">&lt; FAVOR SELECCIONE &gt;</option>
         <?php
         foreach($dlvsels AS $dlvsel)
         {  ?>
            <option value="<?=$dlvsel["dlv_docnum"]?>"><?=$dlvsel["dlv_docnum"]?> (<?=date('d.m.Y', $dlvsel["dlv_delivery_date"])?>)</option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="right" width="130" style="padding-right:5px">
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