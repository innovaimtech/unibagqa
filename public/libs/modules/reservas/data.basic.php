<?php
$currtme = time();

//----------------------------------------------------------------------------------
if((int)$_REQUEST["archive"] == 1)
{
   $sql = " update reservas_header
            set
            res_status  = 2,
            res_updusr  = {$_SESSION["user_id"]},
            res_upddat  = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}
//----------------------------------------------------------------------------------
if((int)$_REQUEST["archive"] == 2)
{
   $sql = " update reservas_header
            set
            res_status  = 1,
            res_updusr  = {$_SESSION["user_id"]},
            res_upddat  = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from reservas_header t1
         LEFT OUTER JOIN user t5             ON t1.res_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.res_crtusr           = t6.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

if($headdata["crt_firstname"] == "")
   $headdata["crt_firstname"] = "Interfaz Sistema";

//----------------------------------------------------------------------------------
$sql = " select *
         from reservas_pos
         where
         pos_header_id = {$_REQUEST["id"]}
         order by id asc";
$posdata = $CON->select($sql);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_reqpos" id="form_reqpos">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="req_status" value="1">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">
      <span style="float:left;line-height:16px">Datos básicos</span>
      <span style="float:right">
         <?php
         $statimg = "";
         switch((int)$headdata["res_status"])
         {
            case 1: $statimg = "red_active.gif"; break;
            case 2: $statimg = "green_active.gif"; break;
         }
         ?>
         <img class="select" src="./images/content/<?=$statimg?>">
      </span>
   </td>
</tr>
<tr>
   <td class="content_rowl">Despacho</td>
   <td class="content_row"><?=$headdata["res_id"]?>&nbsp;</td>
   <td class="content_rowl">Origen</td>
   <td class="content_row"><?=$headdata["res_type"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$headdata["res_custname"]?>&nbsp;</td>
   <td class="content_rowl">Fecha Despacho</td>
   <td class="content_row"><?=$headdata["res_date"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Referencia</td>
   <td class="content_row"><?=$headdata["res_ref"]?>&nbsp;</td>
   <td class="content_rowl">Fecha Importación</td>
   <td class="content_row"><?=date("d.m.Y H:i:s", $headdata["res_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Ciudad</td>
   <td class="content_row"><?=$headdata["res_city"]?>&nbsp;</td>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$headdata["res_street"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Email</td>
   <td class="content_row"><?=$headdata["res_mail"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?=$headdata["res_phone"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Estado Pago</td>
   <td class="content_row">
      <?php
      if((int)$headdata["res_paystate"])
         echo "<b class=msg_save_ok>Si</b>";
      else
         echo "<b class=msg_save_err>No</b>";
      ?>
   </td>
   <td class="content_rowl">Estado Despacho</td>
   <td class="content_row">
      <?php
      if((int)$headdata["res_dlvstate"])
         echo "<b class=msg_save_ok>Si</b>";
      else
         echo "<b class=msg_save_err>No</b>";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Destino/Retiro</td>
   <td class="content_row">
      <?php
      if((int)$headdata["res_retiroshopid"] == -1)
         echo "- - -";
      elseif((int)$headdata["res_retiroshopid"] == 0)
         echo "DESPACHO";
      else
      {
         $sql = " select shop_name
                  from company_shops
                  where
                  id = {$headdata["res_retiroshopid"]}";
         $shop_name = $CON->select($sql);
         $shop_name = $shop_name[0]["shop_name"];
         echo $shop_name."&nbsp;";
      }
      ?>
   </td>
   <td class="content_rowl">Pago Prestashop</td>
   <td class="content_row"><?=$headdata["res_paydesc"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($headdata["res_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["res_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="125">
   <col>
   <col width="90">
   <col width="90">
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Productos</td>
</tr>
<tr>
   <td class="content_row_os content_tbl_subheader" valign="top">Código</td>
   <td class="content_row_os content_tbl_subheader" valign="top">Producto</td>
   <td class="content_row_os content_tbl_subheader" valign="top" align="center">Cantidad</td>
   <td class="content_row_os content_tbl_subheader" valign="top" align="right">$/Unitario</nobr></td>
   <td class="content_row_os content_tbl_subheader" valign="top" align="right">$/Subtotal</nobr></td>
</tr>
<?php
$_CANGENERATE = true;
//----------------------------------------------------------------------------------
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   $trbgcolor = getRowColor($x);

   $itemrefid = 0;
   if($posdata[$x]["pos_code"] != "")
   {
      $sql = " select id
               from item
               where
               item_status > 0 and
               item_number_prod = '{$posdata[$x]["pos_code"]}'";
      $itemrefid = $CON->select($sql);
      $itemrefid = (int)$itemrefid[0]["id"];
   }
   $btx = $posdata[$x]["pos_code"];
   $bss = "color:green;font-weight:bold";
   if(!$itemrefid)
   {
      $bss = "color:red;font-weight:bold";
      $btx = "<u>{$posdata[$x]["pos_code"]}</u> no existe";
      $_CANGENERATE = false;
   }
   ?>
   <tr bgcolor="<?=$trbgcolor?>">
      <td class="content_row_os" style="<?=$bss?>"><?=$btx?>&nbsp;</td>
      <td class="content_row_os"><?=$posdata[$x]["pos_itemdesc"]?></td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["pos_itemamt"])?></td>
      <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["pos_itemprice"] / $posdata[$x]["pos_itemamt"])?></td>
      <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["pos_itemprice"])?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="55">
   <col width="100">
</colgroup>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row_os" colspan="2"><b>SUBTOTAL</b></td>
   <td class="content_row_os" align="right">
      <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#EEEEEE" readonly
      value="<?=printPrice($headdata["res_amount"] - $headdata["res_delivprice"])?>">
   </td>
</tr>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row_os" colspan="2"><b>DESPACHO</b></td>
   <td class="content_row_os" align="right">
      <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#EEEEEE" readonly
      value="<?=printPrice($headdata["res_delivprice"])?>">
   </td>
</tr>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row_totals content_row_os" colspan="2"><b>TOTAL</b></td>
   <td class="content_row_totals content_row_os" align="right">
      <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
      value="<?=printPrice($headdata["res_amount"])?>">
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180", 130);
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($headdata["res_status"] == 1)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) location.href='/index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}'", "cross-circle-frame", 130);
         ?>
      </td>
      <td align="right" width="130" style="padding-left:3px">
         <?php
         printButton("Archivar", "postnav", "javascript: deactivateFormChange()", "if(askDel('')) location.href='/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&archive=1'", "database", 130);
         ?>
      </td>
      <?php
      if($_CANGENERATE)
      {  ?>
         <td align="right" width="130" style="padding-left:3px">
            <?php
            printButton("Generar Boleta", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) location.href='/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&bolgen=1'", "gear", 130);
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("En proceso", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) location.href='/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&archive=2'", "disk-black", 130);
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
<?php
//----------------------------------------------------------------------------------
if((int)$_REQUEST["bolgen"])
{
   $invc_date = time();

   $invc_desc_intern = "RESERVA {$headdata["res_id"]} / {$headdata["res_type"]} / {$headdata["res_custname"]}";
   $invc_desc_intern = trim(addslashes($invc_desc_intern));
   
   $sql = " insert into invoices_sell_bol
            (invc_number, invc_cust_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date,
             invc_taxes, invc_type, invc_stockchange, invc_userid_seller, invc_userid_cashing,
             invc_paymentid, invc_transportid, invc_delivery_date, invc_dlv_docnum, invc_estpay_date,
             invc_docnumber, invc_crtdat, invc_crtusr, invc_isinvcbrutto, invc_desc_intern, invc_resv_id)
            VALUES
            ('', 0, 20010, 30010, {$invc_date}, {$invc_date}, 1, 0,
              1, {$_SESSION["user_id"]}, {$_SESSION["user_id"]}, 0,
              0, {$invc_date}, '', 0, '', {$invc_date}, {$_SESSION["user_id"]}, 1,
              '{$invc_desc_intern}', {$_REQUEST["id"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_sell_bol
               where
               invc_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $invcid  = $invoice[0]["thisid"];

      $sql = " insert into invoices_sell_bol_parts
               (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
               VALUES
               ({$invcid}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
      $CON->no_result($sql);
      $partid = mysql_insert_id();

      $item_stid  = getCentralStorehouse($CON, 30010);
      $poscounter = 0;
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $item_order_pos = -1;
         $item_dlv_pos   = -1;

         $sql = " select id
                  from item
                  where
                  item_status > 0 and
                  item_number_prod = '{$posdata[$x]["pos_code"]}'";
         $itemrefid = $CON->select($sql);
         $itemrefid = (int)$itemrefid[0]["id"];

         $sql_sellprice = round($posdata[$x]["pos_itemprice"] / $posdata[$x]["pos_itemamt"]);
         $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
         $sql_taxes     = round($sql_sellprice / (100 + $sql_taxesperc) * $sql_taxesperc);
         $sql_sellnetto = $sql_sellprice - $sql_taxes;

         //----------------------------------------------------------------------------------
         $sql = " insert into invoices_sell_bol_parts_items
                  (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                   item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                   item_st_id, item_desc, item_charges_act, item_order_pos, item_dlv_pos)
                  VALUES
                  ({$invcid}, {$partid}, {$itemrefid}, {$poscounter}, {$posdata[$x]["pos_itemamt"]}, 'item',
                   {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes},
                   {$item_stid}, '', 0, {$item_order_pos}, {$item_dlv_pos})";
         $CON->no_result($sql);
         
         recalcOrderItem($CON, $invcid, $itemrefid, $poscounter, true, "INVOICEBOL", $partid);
         $poscounter++;
      }

      //----------------------------------------------------------------------------------
      if((float)$headdata["res_delivprice"] > 0.00)
      {
         $itemrefid     = 77;
         $sql_sellprice = round($headdata["res_delivprice"]);
         $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
         $sql_taxes     = round($sql_sellprice / (100 + $sql_taxesperc) * $sql_taxesperc);
         $sql_sellnetto = $sql_sellprice - $sql_taxes;

         //----------------------------------------------------------------------------------
         $sql = " insert into invoices_sell_bol_parts_items
                  (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                   item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                   item_st_id, item_desc, item_charges_act, item_order_pos, item_dlv_pos)
                  VALUES
                  ({$invcid}, {$partid}, {$itemrefid}, {$poscounter}, 1, 'item',
                   {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes},
                   0, '', 0, {$item_order_pos}, {$item_dlv_pos})";
         $CON->no_result($sql);
         
         recalcOrderItem($CON, $invcid, $itemrefid, $poscounter, true, "INVOICEBOL", $partid);
      }
      
      recalcOrder($CON, $invcid, "INVOICEBOL");
      ?>
      <script language="JavaScript">
         location.href='/index.php?mid=984&exec=edit&subexec=edit&id=<?=$invcid?>';
      </script>
      <?php
   }
}