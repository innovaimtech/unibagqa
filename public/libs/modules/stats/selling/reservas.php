<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_orders_reservas";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Pedido" => "2", "Cliente" => "7", "Fecha Pedido" => "5,6", "Monto" => "6", "Creado" => "3", "Estado" => "4");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{

   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
   $_SESSION[$_sesmodulename]["sql_mode"]      = (int)$_REQUEST["sql_mode"];
   $_SESSION[$_sesmodulename]["sql_xstate_pay"] = (int)$_REQUEST["sql_xstate_pay"];
   $_SESSION[$_sesmodulename]["sql_xstate_dlv"] = (int)$_REQUEST["sql_xstate_dlv"];
   $_SESSION[$_sesmodulename]["sql_xstate_resv"] = (int)$_REQUEST["sql_xstate_resv"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_xstate_trans"] = (int)$_REQUEST["sql_xstate_trans"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

//----------------------------------------------------------------------------------
$sql = " select t2.item_id, SUM(t2.item_amount - t2.item_amount_shipped) 'transstock'
         from supplier_order t1
         INNER JOIN supplier_order_items  t2 ON t1.id       = t2.sord_id
         where
         t1.sord_order_shipped   = 0 and
         t1.sord_status          IN (2,3) and
         t2.item_type            = 'item' and
         t2.item_amount          > t2.item_amount_shipped
         group by 1";
$transititems = $CON->select($sql);
foreach($transititems AS $transititem)
{
   $idx = $transititem["item_id"];
   $_TRANSIT[$idx] += $transititem["transstock"];
}

//----------------------------------------------------------------------------------
$seasql = "";
$joisql = "";

//----------------------------------------------------------------------------------
$sql = " select id, item_title, item_number_prod
         from item
         where
         item_status       > 0 and
         item_number_prod  != ''
         order by item_number_prod desc";
$allitems = $CON->select($sql);
foreach($allitems AS $allitem)
{
   $idx = strtoupper($allitem["item_number_prod"]);
   $_ITEMS[$idx] = $allitem;
}
$_ITEMS["9999999999"]["item_number_prod"] = "N/A";
$_ITEMS["9999999999"]["item_title"]       = "DESCONOCIDO";
$_ITEMS["9999999999"]["id"]               = "9999999999";
         
//----------------------------------------------------------------------------------
$datsql = " select t2.pos_code, t2.pos_itemamt, t2.pos_itemprice
            from reservas_header t1
            INNER JOIN reservas_pos t2 ON t1.id = t2.pos_header_id
            where
            t1.res_status > 0 and
            t1.res_crtdat between {$sql_datefrom} and {$sql_dateto}";
if((int)$_SESSION[$_sesmodulename]["sql_xstate_pay"] == 1)
   $seasql .= " and t1.res_paystate = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xstate_pay"] == 2)
   $seasql .= " and t1.res_paystate = 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 1)
   $seasql .= " and t1.res_dlvstate = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 2)
   $seasql .= " and t1.res_dlvstate = 0 ";
if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 1)
   $seasql .= " and t1.res_paydesc like '%transferencia%' and t1.res_paydesc like '%verificad%' ";
elseif($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 2)
   $seasql .= " and t1.res_paydesc NOT like '%transferencia%' ";
if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 1)
   $seasql .= " and t1.res_paydesc like '%reservado%' ";
elseif($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 2)
   $seasql .= " and t1.res_paydesc NOT like '%reservado%' ";

$datsql .= $seasql;
$datsql .= " order by t2.pos_code desc ";
$orders = $CON->select($datsql);

$_ORDERS = Array();
for($x = 0; $x < count($orders) && $orders != fase; $x++)
{
   $idx = strtoupper($orders[$x]["pos_code"]);
   if((int)$_ITEMS[$idx]["id"])
   {
      $_ORDERS[$idx]["CODE"]     = $_ITEMS[$idx]["item_number_prod"];
      $_ORDERS[$idx]["TITLE"]    = $_ITEMS[$idx]["item_title"];
      $_ORDERS[$idx]["ID"]       = $_ITEMS[$idx]["id"];
      $_ORDERS[$idx]["AMT"]     += $orders[$x]["pos_itemamt"];
      $_ORDERS[$idx]["PRCTOT"]  += $orders[$x]["pos_itemprice"];
      $_ORDERS[$idx]["PRCUNIT"]  = round($_ORDERS[$idx]["PRCTOT"] / $_ORDERS[$idx]["AMT"]);
   }
   else
   {
      $idx = "9999999999";
      $_ORDERS[$idx]["CODE"]     = $_ITEMS[$idx]["item_number_prod"];
      $_ORDERS[$idx]["TITLE"]    = $_ITEMS[$idx]["item_title"];
      $_ORDERS[$idx]["ID"]       = $_ITEMS[$idx]["id"];
      $_ORDERS[$idx]["AMT"]     += $orders[$x]["pos_itemamt"];
      $_ORDERS[$idx]["PRCTOT"]  += $orders[$x]["pos_itemprice"];
      $_ORDERS[$idx]["PRCUNIT"]  = round($_ORDERS[$idx]["PRCTOT"] / $_ORDERS[$idx]["AMT"]);
   }
}

//----------------------------------------------------------------------------------
foreach(array_keys($_ORDERS) AS $idx)
{
   if($idx != "9999999999")
   {
      $currstock = getItemShopCurrentStock($CON, 30010, $_ORDERS[$idx]["ID"], "item");
      $_ORDERS[$idx]["STOCK"]    = $currstock;
      $_ORDERS[$idx]["TRANSIT"]  = $_TRANSIT[$_ORDERS[$idx]["ID"]];
   }
}

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td height="30"><b class="content_header">Informe de despachos</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="420">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row" colspan="3">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
         <tr>
            <td class="content_row_clear" width="70">
               <input type="radio" name="sql_selmode" value="2"
               onclick="document.getElementById('idx_selmode1').style.display='none';
                        document.getElementById('idx_selmode2').style.display='';
                        document.getElementById('idx_selmode3').style.display='none';"
               <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
            </td>
            <td class="content_row_clear" width="195" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
               <nobr>
               <select class="text" name="sql_month1" id="sql_month1"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  for($x = 1; $x <= 12; $x++)
                  {
                     $dsp_month = $x;
                     if($dsp_month < 10)
                        $dsp_month = "0{$dsp_month}";
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                     <?php
                  }
                  ?>
               </select>
               <select class="text" name="sql_year1" id="sql_year1"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  $startyear  = date('Y') -10;
                  $endyear    = date('Y');

                  for($x = $startyear; $x <= $endyear; $x++)
                  {
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                     <?php
                  }
                  ?>
               </select>
               -
               <select class="text" name="sql_month2" id="sql_month2"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  for($x = 1; $x <= 12; $x++)
                  {
                     $dsp_month = $x;
                     if($dsp_month < 10)
                        $dsp_month = "0{$dsp_month}";
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                     <?php
                  }
                  ?>
               </select>
               <select class="text" name="sql_year2" id="sql_year2"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  $startyear  = date('Y') -3;
                  $endyear    = date('Y');

                  for($x = $startyear; $x <= $endyear; $x++)
                  {
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                     <?php
                  }
                  ?>
               </select>
               </nobr>
            </td>
            <td class="content_row_clear" width="50">
               <input type="radio" name="sql_selmode" value="1"
               onclick="document.getElementById('idx_selmode1').style.display='';
                        document.getElementById('idx_selmode2').style.display='none';
                        document.getElementById('idx_selmode3').style.display='none';"
               <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
            </td>
            <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
               <nobr>
               <input type="text" style="width:80px" id="sql_date" name="sql_date"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
               </nobr>
            </td>
            <td class="content_row_clear" width="75">
               <input type="radio" name="sql_selmode" value="3"
               onclick="document.getElementById('idx_selmode1').style.display='none';
                        document.getElementById('idx_selmode2').style.display='none';
                        document.getElementById('idx_selmode3').style.display='';"
               <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
            </td>
            <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
               <nobr>
               <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
               -
               <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
               </nobr>
            </td>
         </tr>
         </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Pago</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 0) echo "checked"?>> Todos
            <input type="radio" value="1" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 1) echo "checked"?>> Con pago
            <input type="radio" value="2" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 2) echo "checked"?>> Sin pago
         </td>
         <td class="content_rowl">Despacho</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 0) echo "checked"?>> Todos
            <input type="radio" value="1" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 1) echo "checked"?>> Con despacho
            <input type="radio" value="2" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 2) echo "checked"?>> Sin despacho
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Reservado</td>
         <td class="content_row" colspan="2">
            <input type="radio" value="0" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 0) echo "checked"?>> Todos
            <input type="radio" value="1" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 1) echo "checked"?>> Pendiente por despachar
            <input type="radio" value="2" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 2) echo "checked"?>> No Pendiente por despachar
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Transferencia</td>
         <td class="content_row" colspan="2">
            <input type="radio" value="0" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 0) echo "checked"?>> Todos
            <input type="radio" value="1" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 1) echo "checked"?>> Transferencia verificada
            <input type="radio" value="2" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 2) echo "checked"?>> Sin Transferencia verificada
         </td>
         <td class="content_row" align="right" colspan="1">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right">
                  <?php
                  printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="90">
         <col width="90">
         <col width="100">
         <col width="95">
         <col width="90">
         <col width="90">
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os">Código</td>
         <td class="content_tbl_header content_row_os">Descripción</td>
         <td class="content_tbl_header content_row_os" style="border-left:3px double black" align="right">Precio/Unitario</td>
         <td class="content_tbl_header content_row_os" align="right">Precio/Total</td>
         <td class="content_tbl_header content_row_os" align="right">Despacho/Reserva</td>
         <td class="content_tbl_header content_row_os" style="border-left:3px double black" align="right">Stock central</td>
         <td class="content_tbl_header content_row_os" align="right">En transito</td>
         <td class="content_tbl_header content_row_os" style="border-left:3px double black" align="right">Disponible</td>
      </tr>
      <?php
      $x = 0;
      foreach(array_keys($_ORDERS) AS $idx)
      {
         $stkdispo = $_ORDERS[$idx]["STOCK"] + $_ORDERS[$idx]["TRANSIT"] - $_ORDERS[$idx]["AMT"];
         if($idx == "9999999999")
            $stkdispo = 0;
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$_ORDERS[$idx]["CODE"]?></td>
            <td class="content_row_os"><?=$_ORDERS[$idx]["TITLE"]?></td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($_ORDERS[$idx]["PRCUNIT"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_ORDERS[$idx]["PRCTOT"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_ORDERS[$idx]["AMT"])?></td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($_ORDERS[$idx]["STOCK"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_ORDERS[$idx]["TRANSIT"])?></td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($stkdispo)?></td>
         </tr>
         <?php

         $_TOT_PRC += $_ORDERS[$idx]["PRCTOT"];
         $_TOT_AMT += $_ORDERS[$idx]["AMT"];
         $_TOT_STK += $_ORDERS[$idx]["STOCK"];
         $_TOT_TRA += $_ORDERS[$idx]["TRANSIT"];
         $_TOT_DIS += $stkdispo;
         $x++;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_os" colspan="8" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      else
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row_os content_row_totals">TOTAL</td>
            <td class="content_row_os content_row_totals">&nbsp;</td>
            <td class="content_row_os content_row_totals" style="border-left:3px double black">&nbsp;</td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOT_PRC)?></td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOT_AMT)?></td>
            <td class="content_row_os content_row_totals" align="right" style="border-left:3px double black"><?=printPrice($_TOT_STK)?></td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOT_TRA)?></td>
            <td class="content_row_os content_row_totals" align="right" style="border-left:3px double black"><?=printPrice($_TOT_DIS)?></td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
