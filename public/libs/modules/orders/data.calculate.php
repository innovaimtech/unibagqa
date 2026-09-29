<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
/*
$_TABLENAME    = "orders_items";
$_TABLENAMEHD  = "orders";
$_COLPREFIX    = "req";
$_AMOUNTFIELD  = "item_amount";

//----------------------------------------------------------------------------------
if($_REQUEST["_CALCMODE"] == "DELIVERY")
{
   $_TABLENAME    = "orders_delivery_items";
   $_TABLENAMEHD  = "orders_delivery";
   $_COLPREFIX    = "dlv";
   $_AMOUNTFIELD  = "item_amount_shipped";
}
//----------------------------------------------------------------------------------
else
*/
if($_REQUEST["_CALCMODE"] == "INVOICE")
{
   $_TABLENAME    = "invoices_sell_parts_items";
   $_TABLENAMEHD  = "invoices_sell";
   $_COLPREFIX    = "invc";
   $_AMOUNTFIELD  = "item_amount";
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_discount_type_") !== false && strpos($reqkey, "item_discount_type_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $pdx = $_REQUEST["item_part_id_{$idx}"];
         
         $_REQUEST["item_pcat_dsc_act_{$idx}"]     = (int)$_REQUEST["item_pcat_dsc_act_{$idx}"];
         $_REQUEST["item_vol_act_{$idx}"]          = (int)$_REQUEST["item_vol_act_{$idx}"];
         $_REQUEST["item_value_act_{$idx}"]        = (int)$_REQUEST["item_value_act_{$idx}"];

         $_REQUEST["item_discount_{$idx}"]         = getPrice($_REQUEST["item_discount_{$idx}"],2);
         $_REQUEST["item_discount_type_{$idx}"]    = (int)$_REQUEST["item_discount_type_{$idx}"];

         for($y = 1; $y <= 4; $y++)
            $_REQUEST["item_pcat_dsc_{$idx}_{$y}"] = getPrice($_REQUEST["item_pcat_dsc_{$idx}_{$y}"],2);

         $sql = " update {$_TABLENAME}
                  set
                  item_discount        = {$_REQUEST["item_discount_{$idx}"]},
                  item_discount_type   = {$_REQUEST["item_discount_type_{$idx}"]},
                  item_pcat_dsc_act    = {$_REQUEST["item_pcat_dsc_act_{$idx}"]},
                  item_pcat_dsc1       = {$_REQUEST["item_pcat_dsc_{$idx}_1"]},
                  item_pcat_dsc2       = {$_REQUEST["item_pcat_dsc_{$idx}_2"]},
                  item_pcat_dsc3       = {$_REQUEST["item_pcat_dsc_{$idx}_3"]},
                  item_pcat_dsc4       = {$_REQUEST["item_pcat_dsc_{$idx}_4"]},
                  item_vol_act         = {$_REQUEST["item_vol_act_{$idx}"]},
                  item_value_act       = {$_REQUEST["item_value_act_{$idx}"]}
                  where
                  {$_COLPREFIX}_id     = {$_REQUEST["id"]} and
                  part_id              = {$pdx} and
                  item_pos             = {$idx}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_weightprice_netto"] = getPrice($_REQUEST["dlv_weightprice_netto"]);

   $sql = " update {$_TABLENAMEHD}
            set
            {$_COLPREFIX}_weightprice_netto = {$_REQUEST["dlv_weightprice_netto"]}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   recalcOrder($CON, $_REQUEST["id"], $_REQUEST["_CALCMODE"]);
   

   $savemsg = getSaveMessage(true);

   if($_REQUEST["returnToDiscountSpec"] != "")
   {  ?>
      <script language="JavaScript">
         location.href='index.php?mid=752&exec=edit&subcatexec=discountspec&id=<?=$_REQUEST["returnToDiscountSpec"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from {$_TABLENAMEHD} t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($headdata["{$_COLPREFIX}_status"] >= 2)
{
   $rdlo = " readonly ";
   $dabl = " disabled ";
}

//----------------------------------------------------------------------------------
//if($_REQUEST["_CALCMODE"] == "DELIVERY")
   //$posdata = getOrderDeliveryPos($CON, $_REQUEST["id"]);
if($_REQUEST["_CALCMODE"] == "INVOICE")
{
   $posdata    = Array();
   $invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
   for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
   {
      $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
      for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
         $posdata[] = $partposdata[$y];
   }
}
//else
//   $posdata = getOrderPos($CON, $_REQUEST["id"]);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="xform_itemprices" id="xform_itemprices" <?php if($dabl != "") echo "onsubmit='return false'"?>>
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="calculate">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="returnToDiscountSpec" value="<?=$_REQUEST["returnToDiscountSpec"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25">
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <?php
   $colcount = 11;
   if($_REQUEST["_CALCMODE"] == "DELIVERY" || $_REQUEST["_CALCMODE"] == "INVOICE")
   {
      echo "<col>";
      $colcount = 12;
   }
   ?>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="<?=$colcount?>">Condiciones de venta</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
   <td class="content_tbl_subheader content_row_os">Artículo</td>
   <td class="content_tbl_subheader content_row_os">Unidad</td>
   <?php
   if($_REQUEST["_CALCMODE"] == "DELIVERY" || $_REQUEST["_CALCMODE"] == "INVOICE")
   {  ?>
      <td class="content_tbl_subheader content_row_os" align="right">Peso</td>
      <?php
   }
   ?>
   <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
   <td class="content_tbl_subheader content_row_os" align="right">Precio</td>
   <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio Total</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="center"><nobr>Desc-Global</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="center"><nobr>Desc-Fam/Cond.Pago</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="center"><nobr>Desc-Vol.</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="center"><nobr>Desc-Monto</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="right">Subtotal</td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
   $ges_line   = $posdata[$x]["item_sellprice_netto"] * $posdata[$x][$_AMOUNTFIELD];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row_os" align="center"><?=($x +1)?></td>
      <td class="content_row_os"><?=$posdata[$x]["item_title"]?></td>
      <td class="content_row_os"><?=$unitdesc?></td>
      <?php
      if($_REQUEST["_CALCMODE"] == "DELIVERY" || $_REQUEST["_CALCMODE"] == "INVOICE")
      {
         $item_weight       = $posdata[$x][$_AMOUNTFIELD] * $posdata[$x]["item_weight"];
         $ges_weight       += $item_weight;
         $ges_weight_price += ($posdata[$x][$_AMOUNTFIELD] * (float)$posdata[$x]["item_weight_price"]);
         ?>
         <td class="content_row_os" align="right"><nobr><?=printPrice($item_weight,2)?></nobr></td>
         <?php
      }
      ?>
      <td class="content_row_os" align="right"><?=printPrice($posdata[$x][$_AMOUNTFIELD],2)?></td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($posdata[$x]["item_sellprice_netto"])?></nobr></td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($ges_line)?></nobr></td>
      <td class="content_row_os" align="center">
         <nobr>
         <input name="item_discount_<?=$x?>" type="text" class="text" style="width:26px;text-align:center;<?php
         if($posdata[$x]["item_discount"] > 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
         value="<?php if($posdata[$x]["item_discount"] > 0.00) echo printPrice($posdata[$x]["item_discount"],2);?>" <?=$rdlo?>>
         
         <select class="text" style="width:35px" name="item_discount_type_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
            <option value="0" <?php if(!(int)$posdata[$x]["item_discount_type"]) echo "selected" ?>>%</option>
            <option value="1" <?php if( (int)$posdata[$x]["item_discount_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
         </nobr>
         <input type="hidden" name="item_part_id_<?=$x?>" value="<?=$posdata[$x]["part_id"]?>">
      </td>
      <td class="content_row_os" align="center">
         <nobr>
         <input type="checkbox" class="checkbox" name="item_pcat_dsc_act_<?=$x?>" value="1"
         <?php if((int)$posdata[$x]["item_pcat_dsc_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
         <?php
         $isfirst = false;
         for($y = 1; $y <= 4; $y++)
         {  ?>
            <input type="text" class="text" name="item_pcat_dsc_<?=$x?>_<?=$y?>" style="width:26px;text-align:center;<?php
            if((int)$posdata[$x]["item_pcat_dsc_act"] && $posdata[$x]["item_pcat_dsc{$y}"] > 0.00)
               echo "background-color:#E1FFD6";
            else
               echo "background-color:#FFD6D8";?>"
            value="<?=printPrice($posdata[$x]["item_pcat_dsc{$y}"],2)?>" <?=$rdlo?>>
            <?php
         }
         ?>
         </nobr>
      </td>
      <td class="content_row_os" align="center">
         <nobr>
         <input type="checkbox" class="checkbox" name="item_vol_act_<?=$x?>" value="1"
         <?php if((int)$posdata[$x]["item_vol_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
         
         <input name="item_vol_dsc_<?=$x?>" type="text" class="text" style="width:26px;text-align:center;<?php
         if((int)$posdata[$x]["item_vol_act"] && $posdata[$x]["item_vol_dsc"] > 0.00)
            echo "background-color:#E1FFD6";
         else
            echo "background-color:#FFD6D8";?>"
         value="<?php if($posdata[$x]["item_vol_dsc"] > 0.00) echo printPrice($posdata[$x]["item_vol_dsc"],2);?>" readonly>

         <?php
         if((int)$posdata[$x]["item_vol_dsctype"])
            echo $_SESSION["_CONF"]["conf_currency"];
         else
            echo "%";
         ?>
         </nobr>
      </td>
      <td class="content_row_os" align="center">
         <nobr>
         <input type="checkbox" class="checkbox" name="item_value_act_<?=$x?>" value="1"
         <?php if((int)$posdata[$x]["item_value_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>

         <input name="item_value_dsc_<?=$x?>" type="text" class="text" style="width:26px;text-align:center;<?php
         if((int)$posdata[$x]["item_value_act"] && $posdata[$x]["item_value_dsc"] > 0.00)
            echo "background-color:#E1FFD6";
         else
            echo "background-color:#FFD6D8";?>"
         value="<?php if($posdata[$x]["item_value_dsc"] > 0.00) echo printPrice($posdata[$x]["item_value_dsc"],2);?>" readonly>
         <?php
         if((int)$posdata[$x]["item_value_dsctype"])
            echo $_SESSION["_CONF"]["conf_currency"];
         else
            echo "%";
         ?>
         </nobr>
      </td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($posdata[$x]["item_sellprice_netto_dsc"])?></nobr></td>
   </tr>
   <?php
   $ges_netto  += $ges_line;
   $ges_amount += $posdata[$x][$_AMOUNTFIELD];
}

//----------------------------------------------------------------------------------
if($x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_totals">&nbsp;</td>
      <td class="content_row_totals" colspan="2">TOTAL</td>
      <?php
      if($_REQUEST["_CALCMODE"] == "DELIVERY" || $_REQUEST["_CALCMODE"] == "INVOICE")
      {  ?>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_weight,2)?></nobr></td>
         <?php
      }
      ?>
      <td class="content_row_totals" align="right"><?=printPrice($ges_amount, 2)?></td>
      <td class="content_row_totals" align="right" colspan="2"><?=printPrice($ges_netto)?></td>
      <td class="content_row_totals" align="right" colspan="5"><?=printPrice($headdata["{$_COLPREFIX}_total_netto"] - $headdata["{$_COLPREFIX}_weightprice_netto"])?></td>
   </tr>
   <?php
}
else
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="12" align="center" valign="middle" height="30">
         <b class="msg_save_err">No hay datos disponibles.</b>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td width="540" style="padding-right:10px" valign="top">
      <script language="JavaScript">
         function setCalcDiscountsGLB()
         {
            var val = document.getElementById('glb_item_discount').value;
            var mod = document.getElementById('glb_item_discount_type').value;

            var $inputs = $('#xform_itemprices :input');
            $inputs.each(function()
            {
               var objname = $(this).attr('name');
               if(objname.indexOf('item_discount_') > -1)
                  $(this).val(val);
            });

            var $inputs = $('#xform_itemprices select');
            $inputs.each(function()
            {
               var objname = $(this).attr('name');
               if(objname.indexOf('item_discount_type_') > -1)
                  $(this).val(mod);
            });  
         }

         function setCalcDiscountsCAT()
         {
            var val1 = document.getElementById('glb_item_pcat_dsc_1').value;
            var val2 = document.getElementById('glb_item_pcat_dsc_2').value;
            var val3 = document.getElementById('glb_item_pcat_dsc_3').value;
            var val4 = document.getElementById('glb_item_pcat_dsc_4').value;

            var $inputs = $('#xform_itemprices :input');
            $inputs.each(function()
            {
               var objname = $(this).attr('name');
               if(objname.indexOf('item_pcat_dsc_') > -1 && objname.lastIndexOf('_1') == objname.length - 2)
                  $(this).val(val1);
               if(objname.indexOf('item_pcat_dsc_') > -1 && objname.lastIndexOf('_2') == objname.length - 2)
                  $(this).val(val2);
               if(objname.indexOf('item_pcat_dsc_') > -1 && objname.lastIndexOf('_3') == objname.length - 2)
                  $(this).val(val3);
               if(objname.indexOf('item_pcat_dsc_') > -1 && objname.lastIndexOf('_4') == objname.length - 2)
                  $(this).val(val4);
            });
         }

         function setCalcDiscountsACT(mode)
         {
            var sfield = '';
            var ofield = '';
            if(mode == 'CAT') { ofield = 'glb_item_pcat_act';  sfield = 'item_pcat_dsc_act_'; }
            if(mode == 'VOL') { ofield = 'glb_item_vol_act';   sfield = 'item_vol_act_'; }
            if(mode == 'VAL') { ofield = 'glb_item_val_act';   sfield = 'item_value_act_'; }
               
            var act  = document.getElementById(ofield).checked;
            var $inputs = $('#xform_itemprices :checkbox');
            $inputs.each(function()
            {
               var objname = $(this).attr('name');
               if(objname.indexOf(sfield) > -1)
                  $(this).attr('checked', act);
            });
         }
      </script>
      <?php
      if($headdata["{$_COLPREFIX}_status"] < 2)
      {  ?>
         <?=Nifty_printH("box1", "100%")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
         <colgroup>
            <col>
            <col width="180">
            <col width="75">
         </colgroup>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" style="border-top:0px">DESCUENTOS GLOBALES</td>
            <td class="content_row" style="border-top:0px" align="right">
               <input id="glb_item_discount" type="text" class="text" style="width:75px;text-align:center;">
               
               <select class="text" style="width:35px" id="glb_item_discount_type"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="0">%</option>
                  <option value="1"><?=$_SESSION["_CONF"]["conf_currency"]?></option>
               </select>
            </td>
            <td class="content_row" style="border-top:0px" align="right">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsGLB()">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl">DESCUENTOS POR FAMILIA / COND.</td>
            <td class="content_row" align="right">
               <?php
               for($y = 1; $y <= 4; $y++)
               {  ?>
                  <input type="text" class="text" id="glb_item_pcat_dsc_<?=$y?>" style="width:26px;text-align:center;" value="">
                  <?php
               }
               ?>
            </td>
            <td class="content_row" align="right">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsCAT()">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
            <td class="content_row" align="right">
               POR FAMILIA / CONDICIÓN
               <input type="checkbox" class="checkbox" id="glb_item_pcat_act" value="1" checked>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsACT('CAT')">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
            <td class="content_row" align="right">
               POR VOLUMEN
               <input type="checkbox" class="checkbox" id="glb_item_vol_act" value="1" checked>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsACT('VOL')">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
            <td class="content_row" align="right">
               POR MONTO
               <input type="checkbox" class="checkbox" id="glb_item_val_act" value="1" checked>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsACT('VAL')">
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         <?php
      }
      else
         echo "&nbsp;";
      ?>
   </td>
   <td valign="top">
      <?php
      $tdheight = 26;
      ?>
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
      <colgroup>
         <col>
         <col width="55">
         <col width="100">
      </colgroup>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_clear" colspan="2">SUBTOTAL</td>
         <td class="content_row_clear" align="right"><?=printPrice($headdata["{$_COLPREFIX}_total_netto"] - $headdata["{$_COLPREFIX}_weightprice_netto"])?></td>
      </tr>
      <?php
      if($_REQUEST["_CALCMODE"] == "DELIVERY" || $_REQUEST["_CALCMODE"] == "INVOICE")
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="2" height="<?=$tdheight?>">
               CONDUCCIÓN | SUGERENCIA: <b class="msg_save_ok"><?=printPrice($ges_weight, 2)?> Kg = $ <?=printPrice($ges_weight_price)?></b>
            </td>
            <td class="content_row" align="right">
               <nobr>
               <?php
               if($headdata["{$_COLPREFIX}_status"] < 2)
               {  ?>
                  <input type="button" class="button" value="&gt;&gt;" style="width:25px"
                  onclick="document.getElementById('dlv_weightprice_netto').value='<?=printPrice($ges_weight_price)?>'">
                  <?php
               }
               ?>
               <input type="text" class="text" style="width:65px;text-align:right" <?=$rdlo?>
               name="dlv_weightprice_netto" id="dlv_weightprice_netto"
               value="<?=printPrice($headdata["{$_COLPREFIX}_weightprice_netto"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </nobr>
            </td>
         </tr>
         <?php
      }
      ?>
      <?php
      if($headdata["{$_COLPREFIX}_total_taxes"] > 0.00)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2" height="<?=$tdheight?>">NETO</td>
            <td class="content_row_totals content_row" align="right"><b><?=printPrice($headdata["{$_COLPREFIX}_total_netto"])?></b></td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row content_rowl" colspan="2" height="<?=$tdheight?>">IVA</td>
            <td class="content_row" align="right"><b><?=printPrice($headdata["{$_COLPREFIX}_total_taxes"])?></b></td>
         </tr>
         <?php
      }
      ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_totals content_rowl" colspan="2" height="<?=$tdheight?>">TOTAL</td>
         <td class="content_row_totals content_row" align="right"><b><?=printPrice($headdata["{$_COLPREFIX}_total_brutto"])?></b></td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <?php
   if($headdata["{$_COLPREFIX}_status"] <= 1)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>