<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from supplier_order
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme       = time();
   $suppliertaxes = $headdata["sord_taxes"];

   $sql = " select MAX(item_pos) 'item_pos'
            from supplier_order_items
            where
            sord_id  = {$_REQUEST["id"]}";
   $poscounter = $CON->select($sql);
   $poscounter = $poscounter[0]["item_pos"];

   if($poscounter != "")
      $poscounter = (int)$poscounter +1;
   else
      $poscounter = 0;

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $_REQUEST["item_amount_{$idx}"]  = getPrice($_REQUEST["item_amount_{$idx}"],2);

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $sql_arr       = explode("#",$_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$sql_arr[0];
            $sql_type      = $sql_arr[1];
            
            //----------------------------------------------------------------------------------
            $sql = " select item_costprice_netto, item_costprice_usd, item_costprice_taxes_perc
                     from {$sql_type}_suppliers
                     where
                     item_id = {$sql_id} and
                     supplier_id = {$headdata["sord_supplier_id"]}";
            $costdata = $CON->select($sql);

            //----------------------------------------------------------------------------------
            if($suppliertaxes)
               $sql_costnetto = (float)$costdata[0]["item_costprice_netto"];
            else
               $sql_costnetto = (float)$costdata[0]["item_costprice_usd"];

            //----------------------------------------------------------------------------------
            $sql_taxesperc = (float)$costdata[0]["item_costprice_taxes_perc"];
            $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto / 100 * $sql_taxesperc);
            $sql_costprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto + $sql_taxes);
            
            $sql = " insert into supplier_order_items
                     (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                     VALUES
                     ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                      {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes} )";
            $CON->no_result($sql);

            recalcSupplierOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter);
            
            $poscounter++;
         }
      }
   }

   $sql = " update supplier_order
            set
            sord_updusr = {$_SESSION["user_id"]},
            sord_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   recalcSupplierOrder($CON, $_REQUEST["id"]);

   ?>
   <script language="Javascript">
      location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   $savemsg = getSaveMessage($res);

}

//----------------------------------------------------------------------------------
$sql = " select t2.item_id, t2.iss_inventory, t2.iss_inventory_min, t2.iss_order_amount, 'item_type' 'I',
                t3.item_title, t1.st_name, t1.id 'st_id', 
                t4.item_costprice_netto, t4.item_costprice_taxes_perc, t3.item_number_prod
         from company_shops_storehouses t1
         INNER JOIN item_shops_storehouses t2   ON t1.id = t2.st_id
         INNER JOIN item t3                     ON t2.item_id = t3.id
         INNER JOIN item_suppliers t4           ON t2.item_id = t4.item_id
         where
         t1.st_shop_id        = {$headdata["sord_shop_id"]} and
         t4.supplier_id       = {$headdata["sord_supplier_id"]} and
         t3.item_status       = 1 and
         t3.item_released     = 1 and
         t3.item_purchasable  = 1 and
         (
            t2.iss_inventory     < t2.iss_inventory_min or
            t2.iss_inventory     < 0
         )
         order by t3.item_number_prod, t3.item_title";
$crtitems = $CON->select($sql);

?>
<form action="index.php" method="post" name="form_shppos">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subcatexec" value="criticalitems">
<input type="hidden" name="subsubexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="crtitemsmode" value="<?=$_REQUEST["crtitemsmode"]?>">
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="100">
   <col>
   <col width="65">
   <col width="120">
   <col width="60">
   <col width="60">
   <col width="60">
   <col width="60">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="9">Artículos bajo stock critico</td>
</tr>
<tr>
   <td class="content_tbl_subheader" valign="top">Artículo</td>
   <td class="content_tbl_subheader" valign="top">Unidad</td>
   <td class="content_tbl_subheader" valign="top">Bodega</td>
   <td class="content_tbl_subheader" valign="top" align="left">Cantidad</td>
   <td class="content_tbl_subheader" valign="top" align="left">Unidad de compra</td>
   <td class="content_tbl_subheader" valign="top" align="center">Stock<br>actual</td>
   <td class="content_tbl_subheader" valign="top" align="center">Stock<br>transito</td>
   <td class="content_tbl_subheader" valign="top" align="center">Stock<br>critico</td>
   <td class="content_tbl_subheader" valign="top" align="center">Stock<br>requerido</td>
</tr>
<?php
for($x = 0; $x < count($crtitems) && $crtitems != false; $x++)
{
   if($crtitems[$x]["item_type"] == "item_typeI")
      $crtitems[$x]["item_type"] = "item";
   else
      $crtitems[$x]["item_type"] = "itemlist";

   $unitdesc         = getItemUnitDesc($CON, $crtitems[$x]["item_id"], $crtitems[$x]["item_type"]);
   $transtock        = getItemShopTransStock($CON, $headdata["sord_shop_id"], $crtitems[$x]["item_id"], $crtitems[$x]["item_type"], true);
   $itemalternatives = getItemOrderAlternativeUnits($CON, $crtitems[$x]["item_id"], $crtitems[$x]["item_type"], $headdata["sord_shop_id"], $headdata["sord_supplier_id"]);
   $reqstock         = $crtitems[$x]["iss_inventory_min"] - ($crtitems[$x]["iss_inventory"] + $transtock);
   
   $cssstyle = "";
   if($reqstock > 0.00)
      $cssstyle = "style='background-color:#FFD6D8'";
   ?>
   <tr <?=$cssstyle?>>
      <td class="content_row"><?=$crtitems[$x]["item_number_prod"]?> - <?=$crtitems[$x]["item_title"]?></td>
      <td class="content_row"><nobr><?=$unitdesc?></nobr></td>
      <td class="content_row"><?=$crtitems[$x]["st_name"]?></td>
      <td class="content_row" align="left">
         <input type="text" class="text" style="width:50px;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         name="item_amount_<?=$x?>" id="item_amount_<?=$x?>"
         value="">
      </td>
      <td class="content_row" align="left">
         <?php
         if(count($itemalternatives) && $itemalternatives != false)
         {  ?>
            <select name="item_id_<?=$x?>" id="item_id_<?=$x?>" class="text" style="width:115px">
               <option value="<?=$crtitems[$x]["item_id"]?>#<?=$crtitems[$x]["item_type"]?>"><?=$unitdesc?></option>
               <?php
               for($y = 0; $y < count($itemalternatives) && $itemalternatives != false; $y++)
               {  ?>
                  <option value="<?=$itemalternatives[$y]["item_id"]?>#<?=$itemalternatives[$y]["item_type"]?>">
                     <?=$itemalternatives[$y]["unit_name"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
            <?php
         }
         else
         {  ?>
            <input type="hidden" name="item_id_<?=$x?>" value="<?=$crtitems[$x]["item_id"]?>#<?=$crtitems[$x]["item_type"]?>">
            <?=$unitdesc?>
            <?php
         }
         ?>
      </td>
      <td class="content_row" align="center"><?=printPrice($crtitems[$x]["iss_inventory"],2)?></td>
      <td class="content_row" align="center"><?=printPrice($transtock,2)?></td>
      <td class="content_row" align="center"><?=printPrice($crtitems[$x]["iss_inventory_min"],2)?></td>
      <td class="content_row" align="center"><?=printPrice($reqstock,2)?></td>
   </tr>
   <?php
   $x++;
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="9" align="center" valign="middle" height="30">
         <b class="msg_save_err">No hay datos disponibles.</b>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$_REQUEST["id"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($x)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("Agregar artículos", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "plus", 150);
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php