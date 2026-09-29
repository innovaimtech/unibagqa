<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["item_number_prod1"]         = trim(addslashes($_REQUEST["item_number_prod1"]));
   $_REQUEST["item_number_prod2"]         = trim(addslashes($_REQUEST["item_number_prod2"]));
   $_REQUEST["item_sellprice_calc_type"]  = trim(addslashes($_REQUEST["item_sellprice_calc_type"]));
   $_REQUEST["item_invoice_note"]         = trim(addslashes($_REQUEST["item_invoice_note"]));
   $_REQUEST["item_invoicebuy_note"]      = trim(addslashes($_REQUEST["item_invoicebuy_note"]));
   $_REQUEST["item_number_prod"]          = "{$_REQUEST["item_number_prod1"]}{$_REQUEST["item_number_prod2"]}";

   $_REQUEST["item_released"]       = (int)$_REQUEST["item_released"];
   $_REQUEST["item_sellable"]       = (int)$_REQUEST["item_sellable"];
   $_REQUEST["item_purchasable"]    = (int)$_REQUEST["item_purchasable"];
   $_REQUEST["item_unit"]           = (int)$_REQUEST["item_unit"];
   $_REQUEST["item_unit_amount"]    = getPrice($_REQUEST["item_unit_amount"],2);
   $_REQUEST["item_sellprice_calc"] = (int)$_REQUEST["item_sellprice_calc"];
   $_REQUEST["item_sell_withotheritems"]     = (int)$_REQUEST["item_sell_withotheritems"];
   $_REQUEST["item_sell_amountmin"]          = getPrice($_REQUEST["item_sell_amountmin"],2);
   $_REQUEST["item_sell_nodsc"]              = (int)$_REQUEST["item_sell_nodsc"];
   $_REQUEST["item_weight"]         = getPrice($_REQUEST["item_weight"],2);
   $_REQUEST["item_weight_price"]   = getPrice($_REQUEST["item_weight_price"]);
   
   $_REQUEST["item_sellprice_calc_perc"]     = getPrice($_REQUEST["item_sellprice_calc_perc"],2);
   $_REQUEST["component_item_amount"]        = getPrice($_REQUEST["component_item_amount"],2);
   $_REQUEST["component_item_id"]            = (int)$_REQUEST["component_item_id"];

   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_purchasable"] || !$_REQUEST["item_sellable"])
      $_REQUEST["item_sellprice_calc"] = 0;

   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_sellprice_calc"])
   {
      $_REQUEST["item_sellprice_calc_perc"]  = 0.00;
      $_REQUEST["item_sellprice_calc_type"]  = "";
   }
   else
   {
      if($_REQUEST["item_sellprice_calc_type"] == "CAT")
      {
         $sql = " select cat_calc_price_perc
                  from productcats
                  where
                  id = {$_REQUEST["item_catids"]}";
         $calc_price_perc = $CON->select($sql);
         $_REQUEST["item_sellprice_calc_perc"] = $calc_price_perc[0]["cat_calc_price_perc"];
      }
      
      $sql = " select t1.item_costprice_netto
               from itemlist_suppliers t1
               where
               t1.item_id        = {$_REQUEST["id"]} and
               t1.item_supp_act  = 1";
      $suppinfo = $CON->select($sql);
      $suppinfo = $suppinfo[0];

      $_REQUEST["item_sellprice_netto"] = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", (float)$suppinfo["item_costprice_netto"] + ((float)$suppinfo["item_costprice_netto"] / 100 * $_REQUEST["item_sellprice_calc_perc"]));
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["item_sellprice_netto"]         = getPrice($_REQUEST["item_sellprice_netto"]);
   $_REQUEST["item_sellprice_taxes_perc"]    = getPrice($_REQUEST["item_sellprice_taxes_perc"],2);
   $_REQUEST["item_sellprice_taxes"]         = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $_REQUEST["item_sellprice_netto"] / 100 * $_REQUEST["item_sellprice_taxes_perc"]);
   $_REQUEST["item_sellprice_brutto"]        = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $_REQUEST["item_sellprice_netto"] + $_REQUEST["item_sellprice_taxes"]);


   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_sellable"])
   {
      $_REQUEST["item_sellprice_brutto"]     = 0.00;
      $_REQUEST["item_sellprice_taxes"]      = 0.00;
      $_REQUEST["item_sellprice_netto"]      = 0.00;
      $_REQUEST["item_sellprice_calc_perc"]  = 0.00;
      $_REQUEST["item_sellprice_calc_type"]  = "";
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $_REQUEST["item_number"] = createNumberSystem($CON, "ITEMLISTNUMBER");

      $sql = " select item_title
               from item
               where
               id = {$_REQUEST["component_item_id"]}";
      $itemtitle = $CON->select($sql);
      $itemtitle = trim(addslashes($itemtitle[0]["item_title"]));
      
      $sql = " insert into itemlist
               (item_title, item_released, item_number, item_number_prod, item_sellprice_brutto,
               item_sellprice_taxes_perc, item_sellprice_taxes, item_sellprice_netto,
               item_unit, item_unit_amount, item_sellable, item_purchasable, item_sellprice_calc,
               item_sellprice_calc_perc, item_sellprice_calc_type, item_weight, item_weight_price, item_invoice_note,
               item_invoicebuy_note, item_sell_withotheritems, item_sell_amountmin, item_sell_nodsc, item_crtusr, item_crtdat)
               VALUES
               ('{$itemtitle}', {$_REQUEST["item_released"]},
                '{$_REQUEST["item_number"]}', '{$_REQUEST["item_number_prod"]}', {$_REQUEST["item_sellprice_brutto"]}, 
                 {$_REQUEST["item_sellprice_taxes_perc"]},
                 {$_REQUEST["item_sellprice_taxes"]}, {$_REQUEST["item_sellprice_netto"]}, {$_REQUEST["item_unit"]},
                 {$_REQUEST["item_unit_amount"]}, {$_REQUEST["item_sellable"]}, {$_REQUEST["item_purchasable"]},
                 {$_REQUEST["item_sellprice_calc"]}, {$_REQUEST["item_sellprice_calc_perc"]}, '{$_REQUEST["item_sellprice_calc_type"]}',
                 {$_REQUEST["item_weight"]}, {$_REQUEST["item_weight_price"]}, '{$_REQUEST["item_invoice_note"]}', '{$_REQUEST["item_invoicebuy_note"]}',
                 {$_REQUEST["item_sell_withotheritems"]}, {$_REQUEST["item_sell_amountmin"]}, {$_REQUEST["item_sell_nodsc"]},
                 {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from itemlist
                  where
                  item_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;

         $sql = " insert into itemlist_pos
                  (itemlist_id, item_id, item_pos, item_amount)
                  VALUES
                  ({$thisid}, {$_REQUEST["component_item_id"]}, 0, {$_REQUEST["component_item_amount"]})";
         $CON->no_result($sql);

         $redirect = true;
      }
      
      $savemsg = getSaveMessage($res);
   }
   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update itemlist
               set
               item_released              = {$_REQUEST["item_released"]},
               item_sellprice_brutto      = {$_REQUEST["item_sellprice_brutto"]},
               item_sellprice_taxes_perc  = {$_REQUEST["item_sellprice_taxes_perc"]},
               item_sellprice_taxes       = {$_REQUEST["item_sellprice_taxes"]},
               item_sellprice_netto       = {$_REQUEST["item_sellprice_netto"]},
               item_sellprice_calc        = {$_REQUEST["item_sellprice_calc"]},
               item_sellprice_calc_perc   = {$_REQUEST["item_sellprice_calc_perc"]},
               item_sellprice_calc_type   = '{$_REQUEST["item_sellprice_calc_type"]}',
               item_sell_withotheritems   = {$_REQUEST["item_sell_withotheritems"]},
               item_sell_amountmin        = {$_REQUEST["item_sell_amountmin"]},
               item_sell_nodsc            = {$_REQUEST["item_sell_nodsc"]},
               item_sellable              = {$_REQUEST["item_sellable"]},
               item_purchasable           = {$_REQUEST["item_purchasable"]},
               item_weight                = {$_REQUEST["item_weight"]},
               item_weight_price          = {$_REQUEST["item_weight_price"]},
               item_invoice_note          = '{$_REQUEST["item_invoice_note"]}',
               item_invoicebuy_note       = '{$_REQUEST["item_invoicebuy_note"]}',
               item_unit                  = {$_REQUEST["item_unit"]},
               item_unit_amount           = {$_REQUEST["item_unit_amount"]},
               item_updusr                = {$_SESSION["user_id"]},
               item_upddat                = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);

      //----------------------------------------------------------------------------------
      if($res)
      {
         updateItemlistStorePrices($CON, $_REQUEST["id"]);
      
         $thisid = $_REQUEST["id"];

         $sql = " delete from item_productcats_itemlist
                  where
                  item_id = {$thisid}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   if((int)$thisid)
   {
      $catidx   = (int)$_REQUEST["item_catids"];

      $sql = " insert into item_productcats_itemlist
               (item_id, cat_id) VALUES ({$thisid}, {$catidx}) ";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from itemlist_suppliers
            where
            item_id  = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "supplier_id_") !== false && (int)$_REQUEST[$reqkey])
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         
         if(strpos($_REQUEST["supplier_id_{$idx}"], "#") !== false)
         {
            $supplier_id   = explode("#", $_REQUEST["supplier_id_{$idx}"]);
            $supplier_id   = (int)$supplier_id[0];
         }
         else
            $supplier_id   = (int)$_REQUEST["supplier_id_{$idx}"];
            
         $item_code     = trim(addslashes($_REQUEST["item_code_{$idx}"]));
         $item_supp_fob = (int)$_REQUEST["item_supp_fob_{$idx}"];

         $supptax = getSupplierTaxes($CON, $supplier_id);

         if(!(int)$supptax)
         {
            $item_costprice_netto      = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
            $item_costprice_brutto     = $item_costprice_netto;
            $item_costprice_taxes_perc = 0;
            $item_costprice_taxes      = 0;
            $item_costprice_usd        = getPrice($_REQUEST["item_costprice_usd_{$idx}"],4);
         }
         else
         {
            $item_costprice_netto      = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
            $item_costprice_taxes_perc = getPrice($_REQUEST["item_costprice_taxes_perc_{$idx}"],2);
            $item_costprice_taxes      = (float)sprintf("%.2f", $item_costprice_netto / 100 * $item_costprice_taxes_perc);
            $item_costprice_brutto     = (float)sprintf("%.2f", $item_costprice_netto + $item_costprice_taxes);
            $item_costprice_usd        = 0.00;
            $item_supp_fob             = 0;
         }

         if($idx == (int)$_REQUEST["item_supp_act"])
            $item_supp_act = 1;
         else
            $item_supp_act = 0;

         $sql = " insert into itemlist_suppliers
                  (item_id, supplier_id, item_code, item_costprice_brutto, item_costprice_taxes_perc,
                   item_costprice_netto, item_costprice_taxes, item_costprice_usd, item_supp_act, item_supp_fob)
                  VALUES
                  ({$_REQUEST["id"]}, {$supplier_id}, '{$item_code}', {$item_costprice_brutto},
                   {$item_costprice_taxes_perc}, {$item_costprice_netto}, {$item_costprice_taxes},
                   {$item_costprice_usd}, {$item_supp_act}, {$item_supp_fob})";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   registerCostPriceHistory($CON, $_REQUEST["id"], "itemlist");
   recalcAutomatedSellPrices($CON, $_REQUEST["id"], "itemlist");
   updateItemlistStorePrices($CON, $_REQUEST["id"]);
   registerSellPriceHistory($CON, $_REQUEST["id"], "itemlist");
   
   //----------------------------------------------------------------------------------
   if($redirect)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from company_data
               where
               company_status = 1
               order by company_name";
      $companies = $CON->select($sql);

      foreach($companies AS $company)
      {
         $sql = " select t1.*
                  from company_shops t1
                  where
                  t1.shop_status = 1 and
                  t1.shop_company_id = {$company["id"]}
                  order by t1.shop_name";
         $shops = $CON->select($sql);

         foreach($shops AS $shop)
         {
            $sql = " insert into itemlist_shops
                    (item_id, shop_id)
                    VALUES
                    ({$_REQUEST["id"]}, {$shop["id"]})";
            $res = $CON->no_result($sql);
         }
      }
      updateItemlistStorePrices($CON, $_REQUEST["id"]);
      registerSellPriceHistory($CON, $_REQUEST["id"], "itemlist");
      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=640&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t4.unit_name, t4.unit_desc,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from itemlist t1
            LEFT OUTER JOIN user t2 ON t1.item_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.item_crtusr = t3.id
            LEFT OUTER JOIN item_units t4 ON t1.item_unit = t4.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $item = $CON->select($sql);
   $item = $item[0];

   //----------------------------------------------------------------------------------
   $sql = " select t2.id
            from item_productcats_itemlist t1, productcats t2
            where
            t1.item_id     = {$_REQUEST["id"]} and
            t1.cat_id      = t2.id and
            t2.cat_status  = 1";
   $selitemcatid = $CON->select($sql);
   $selitemcatidstr = $selitemcatid[0]["id"];

   //----------------------------------------------------------------------------------
   $sql = " select t1.item_costprice_brutto, t1.item_costprice_netto, t2.id, t2.supp_company
            from itemlist_suppliers t1
            LEFT OUTER JOIN supplier t2 ON t1.supplier_id = t2.id
            where
            t1.item_id        = {$_REQUEST["id"]} and
            t1.item_supp_act  = 1";
   $suppinfo = $CON->select($sql);
   $suppinfo = $suppinfo[0];
}

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         order by cat_title{$_SESSION["_CONF"]["conf_lang"]}";
$cats = $CON->select($sql);

$itemunits = getItemUnits($CON);

//----------------------------------------------------------------------------------
$sql = " select *
         from itemlist_pos
         where
         itemlist_id = {$_REQUEST["id"]}";
$items = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.item_sellprice_brutto, (t2.item_sellprice_netto * t1.item_amount) AS 'gessum',
                      t4.unit_name, t4.unit_desc
         from itemlist_pos t1
         INNER JOIN item t2 ON t1.item_id = t2.id
         LEFT OUTER JOIN item_suppliers t3 ON ( t2.id = t3.item_id and t3.item_supp_act = 1 )
         LEFT OUTER JOIN item_units t4 ON t2.item_unit = t4.id
         where
         t1.itemlist_id = {$_REQUEST["id"]} 
         order by t1.item_pos asc";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
$ges = 0;
foreach($posdata AS $pos)
   $ges = $ges + $pos["gessum"];

$sql = " select *
         from productcats
         where
         cat_status = 1
         order by id";
$cats = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="js_item_form"
<?php if($_REQUEST["id"] == "") echo 'onsubmit="return checkform(new Array(this.item_number_prod1, this.item_number_prod2, this.component_item_amount))"'?>>
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="item_catids" value="<?=$selitemcatidstr?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del pack I</td>
      </tr>
      <tr>
         <td class="content_rowl">Articulo relacionado *</td>
         <td class="content_row">
            <?php
            if($_REQUEST["id"] != "")
               echo $item["item_number_prod"];
            else
            {  ?>
               <select name="item_number_prod1" id="item_number_prod1" class="text" style="width:45px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="document.getElementById('idx_number_get').src='./libs/modules/itemlist/get.productcat.php?catid=' +this.value">
                  <option value="">- - -</option>
                  <?php
                  foreach($cats AS $cat)
                  {  ?>
                     <option value="<?=sprintf("%03s", $cat["id"])?>"><?=sprintf("%03s", $cat["id"])?>  <?=$cat["cat_title"]?></option>
                     <?php
                  }
                  ?>
               </select>
               &nbsp;
               <select name="item_number_prod2" id="item_number_prod2" class="text" style="width:80px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="document.getElementById('idx_number_get').src='./libs/modules/itemlist/get.itemdata.php?catid=' +document.getElementById('item_number_prod1').value +'&itemnumber=' +this.value">
                     <option value="">- - -</option>
               </select>
               <input type="hidden" name="component_item_id" id="component_item_id" value="">
               <?php
            }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre</td>
         <td class="content_row"><div id="idx_item_name"><?php if($_REQUEST["id"] != "") echo $item["item_title"]; else echo "- - -"?></div></td>
      </tr>
      <tr>
         <td class="content_rowl">Unidad</td>
         <td class="content_row"><div id="idx_item_unit"><?php if($_REQUEST["id"] != "") echo $posdata[0]["unit_name"]." - ".$posdata[0]["unit_desc"]; else echo "- - -"?></div></td>
      </tr>
      <tr>
         <td class="content_rowl">Contenido del pack *</td>
         <td class="content_row">
            <?php
            if($_REQUEST["id"] != "")
               echo printPrice($posdata[0]["item_amount"],2);
            else
            {  ?>
               <input name="component_item_amount" type="text" class="text" style="width:70px" value=""
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Unidad del pack</td>
         <td class="content_row">
            <input name="item_unit_amount" type="text" class="text" style="width:60px"
            value="<?php if($_REQUEST["id"] == "") echo "1,00"; else echo printPrice($item["item_unit_amount"],2)?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <select class="text" name="item_unit" style="width:265px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               foreach($itemunits AS $itemunit)
               {  ?>
                  <option value="<?=$itemunit["id"]?>" <?php if(($_REQUEST["id"] == "" && $itemunit["unit_name"] == "CAJ") || $item["item_unit"] == $itemunit["id"]) echo "selected"?>><?=$itemunit["unit_name"]?> - <?=$itemunit["unit_desc"]?></option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nota int.: Venta</td>
         <td class="content_row">
            <input name="item_invoice_note" type="text" class="text" style="width:330px" value="<?=$item["item_invoice_note"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nota int.: Compra</td>
         <td class="content_row">
            <input name="item_invoicebuy_note" type="text" class="text" style="width:330px" value="<?=$item["item_invoicebuy_note"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Activado</td>
         <td class="content_row">
            <select class="text" name="item_released" style="width:70px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="1" style="color:green" <?php if((int)$item["item_released"] || $_REQUEST["id"] == "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][0]?></option>
               <option value="0" style="color:red"   <?php if(!(int)$item["item_released"] && $_REQUEST["id"] != "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][1]?></option>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$item["crt_firstname"]?> <?=$item["crt_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=displayDate($item["item_crtdat"])?></td>
      </tr>
      <tr>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$item["upd_firstname"]?> <?=$item["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=displayDate($item["item_upddat"])?></td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del pack II</td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <table cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="112">
                  <input name="item_sellable" type="checkbox" value="1" id="item_sellable" onclick="setItemSellable(this)"
                  <?php if((int)$item["item_sellable"] == 1) echo "checked"?>>
                  Ventas
               </td>
               <td class="content_row_clear">
                  <input name="item_purchasable" type="checkbox" value="1" id="item_purchasable"
                  <?php if((int)$item["item_purchasable"] == 1) echo "checked"?>>
                  Compras
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_tr_venta1" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">Precio venta (neto) *</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="112">
                  <input name="item_sellprice_netto" id="item_sellprice_netto" type="text" class="text"
                  style="width:80px;text-align:right;<?if((int)$item["item_sellprice_calc"]) echo ";background-color:#E1FFD6"?>"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_netto"])?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)" <?if((int)$item["item_sellprice_calc"]) echo "readonly"?>>
                  <?=$_SESSION["_CONF"]["conf_currency"]?>
               </td>
               <td class="content_row_clear">
                  <input type="checkbox" value="1" name="item_sellprice_calc" id="item_sellprice_calc" onclick="setItemSellCalc(this)"
                  <?php if((int)$item["item_sellprice_calc"]) echo "checked" ?>>
                  Precio calculado
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_tr_venta1a" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"]) echo "style='display:none'"?>>
         <td class="content_rowl">Margen basado en</td>
         <td class="content_row">
            <input type="radio" value="CAT" name="item_sellprice_calc_type" id="item_sellprice_calc_typef"
            onclick="document.getElementById('idx_tr_venta1b').style.display='none';
                     document.getElementById('idx_tr_venta1d').style.display=''"
            <?php if($item["item_sellprice_calc_type"] == "CAT" || $item["item_sellprice_calc_type"] == "") echo "checked" ?>>
            Familia
            
            <input type="radio" value="ITEM" name="item_sellprice_calc_type" id="item_sellprice_calc_typei"
            onclick="document.getElementById('idx_tr_venta1b').style.display='';
                     document.getElementById('idx_tr_venta1d').style.display='none'"
            <?php if($item["item_sellprice_calc_type"] == "ITEM") echo "checked" ?>>
            Particular
         </td>
      </tr>
      <tr id="idx_tr_venta1b" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"] || $item["item_sellprice_calc_type"] != "ITEM") echo "style='display:none'"?>>
         <td class="content_rowl">Utilidad</td>
         <td class="content_row">
            <input name="item_sellprice_calc_perc" type="text" class="text" style="width:80px;text-align:right"
            value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_calc_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            %
         </td>
      </tr>
      <tr id="idx_tr_venta1d" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"] || $item["item_sellprice_calc_type"] != "CAT") echo "style='display:none'"?>>
         <td class="content_rowl" height="25">Utilidad</td>
         <td class="content_row">
            <?php
            if(!(int)$_REQUEST["id"])
               echo "- - -";
            else
               echo printPrice($item["item_sellprice_calc_perc"], 2);
            ?>
             %
          </td>
      </tr>
      <tr id="idx_tr_venta2" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">IVA *</td>
         <td class="content_row">
            <input name="item_sellprice_taxes_perc" type="text" class="text" style="width:80px;text-align:right"
            value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_taxes_perc"],2); else echo printPrice($_SESSION["_CONF"]["conf_taxes"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
         </td>
      </tr>
      <tr id="idx_tr_venta3" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">Precio venta (bruto)</td>
         <td class="content_row">
            <b class="msg_save_ok">
            <?=$_SESSION["_CONF"]["conf_currency"]?>
            <?php
            if((int)$item["id"])
               echo printPrice($item["item_sellprice_brutto"]);
            else
               echo "0,00";
            ?>
            </b>
         </td>
      </tr>
      <?php
      if($_REQUEST["id"] != "" && (int)$item["item_purchasable"])
      {  ?>
         <tr>
            <td class="content_rowl">Precio compra</td>
            <td class="content_row">
               <?php
               if((int)$suppinfo["id"])
               {  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                     <td class="content_row_clear" width="120"><b>Neto:</b> <?=$_SESSION["_CONF"]["conf_currency"]." ".printPrice($suppinfo["item_costprice_netto"])?></td>
                     <td class="content_row_clear" align="left"><b>Bruto:</b> <?=$_SESSION["_CONF"]["conf_currency"]." ".printPrice($suppinfo["item_costprice_brutto"])?></td>
                  </tr>
                  </table>
                  <?php
               }
               else
                  echo "- - -";
               ?>
            </td>
         </tr>
         <?php
      }
      ?>
      <tr id="idx_tr_venta1c" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">Peso / Conducción</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="120">
                  <input name="item_weight" type="text" class="text" style="width:80px;text-align:right"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_weight"],2)?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  onkeyup="calcWeightPrice(this, document.getElementById('item_weight_price'))"> Kg
               </td>
               <td class="content_row_clear">
                  <input name="item_weight_price" id="item_weight_price" type="text" class="text" style="width:80px;text-align:right"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_weight_price"])?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"> <?=$_SESSION["_CONF"]["conf_currency"]?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_tr_venta1x" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl" valign="top">Opciones adicionales</td>
         <td class="content_row" valign="top">
            <table border="0" cellpadding="2" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear">
                  <input name="item_sell_amountmin" id="item_sell_amountmin" type="text" class="text" style="width:30px;text-align:center"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_sell_amountmin"])?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  Cantidad minima de venta
               </td>
            </tr>
            <tr>
               <td class="content_row_clear">
                  &nbsp;<input type="checkbox" value="1" name="item_sell_withotheritems" id="item_sell_withotheritems"
                  <?php if((int)$item["item_sell_withotheritems"]) echo "checked" ?>>
                  &nbsp;&nbsp;Se vende con otros productos
               </td>
            </tr>
            <tr>
               <td class="content_row_clear">
                  &nbsp;<input type="checkbox" value="1" name="item_sell_nodsc" id="item_sell_nodsc"
                  <?php if((int)$item["item_sell_nodsc"]) echo "checked" ?>>
                  &nbsp;&nbsp;No lleva descuentos
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
      if($_REQUEST["id"] != "" && (int)$item["item_purchasable"] && (float)$suppinfo["item_costprice_netto"] > 0.00 && (float)$item["item_sellprice_netto"] > 0.00)
      {
         $sql = " select * 
                  from productcats
                  where
                  id = {$selitemcatidstr}";
         $pcat = $CON->select($sql);
         $pcat = $pcat[0];
         if(($pcat["cat_dsc_maxperc"] > 0.00 || $pcat["cat_dsc_maxbuyperc"] > 0.00) && !(int)$item["item_sellprice_calc"])
         {
            $buyval = round($suppinfo["item_costprice_netto"] - ($suppinfo["item_costprice_netto"] / 100 * $pcat["cat_dsc_maxbuyperc"]), 0);
            $selval = round($item["item_sellprice_netto"] - ($item["item_sellprice_netto"] / 100 * $pcat["cat_dsc_maxperc"]), 0);
            $title  = "Margen calculado por familia";
         }
         else
         {
            $buyval = (float)$suppinfo["item_costprice_netto"];
            $selval = (float)$item["item_sellprice_netto"];
            $title  = "Margen neto";
         }
         ?>
         <tr>
            <td class="content_row" colspan="2">
               <img src="./libs/modules/items/spanne.php?buyval=<?=$buyval?>&sellval=<?=$selval?>&title=<?=$title?>">
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col width="240">
   <col>
   <col>
   <col>
   <col>
   <col width="20">
   <col width="50">
   <col>
   <col>
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="12">Proveedores</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Busqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader">Codigo</td>
   <td class="content_tbl_subheader" align="right"><nobr>Neto/Basico</nobr></td>
   <td class="content_tbl_subheader" align="right"><nobr>Precio/USD</nobr></td>
   <td class="content_tbl_subheader" align="right"><nobr>Alza</nobr></td>
   <td class="content_tbl_subheader" align="right">FOB</td>
   <td class="content_tbl_subheader" align="right">IVA %</td>
   <td class="content_tbl_subheader" align="right"><nobr>P/Bruto</nobr></td>
   <td class="content_tbl_subheader" align="center">Primario</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.country_money_type
         from itemlist_suppliers t1
         LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
         LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
         where
         item_id = {$_REQUEST["id"]}
         order by item_supp_act desc, item_costprice_brutto asc";
$posdata = $CON->select($sql);

$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

$rowcount += 2;

for($x = 0; $x < $rowcount; $x++)
{
   $supptax = getSupplierTaxes($CON, $posdata[$x]["supplier_id"]);

   //----------------------------------------------------------------------------------
   $sql = " select t1.prc_item_id, t1.prc_costprice_netto
            from pricehist_buy t1
            where
            t1.prc_item_id    = {$_REQUEST["id"]} and
            t1.prc_item_type  = 'itemlist' and
            t1.prc_supplier_id = {$posdata[$x]["supplier_id"]} 
            order by t1.prc_crtdat desc
            LIMIT 0,2";
   $buyhist = $CON->select($sql);
   $buyhist = $buyhist[1];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" onfocus="markfield(this,0)"
               <?php
               if((int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);"
                  <?php
               }
               else
               {  ?>
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?mode=money&rowcount=<?=$x?>' +'&search=' +this.value"
                  <?php
               }
               ?>>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.all.supplier_id_<?=$x?>.options.length=0;submitForm(document.js_item_form) }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <select class="text" style="width:270px" name="supplier_id_<?=$x?>"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $sql = " select t1.id, t1.supp_company
                        from supplier t1
                        where
                        t1.id = {$posdata[$x]["supplier_id"]}";
               $selitem = $CON->select($sql);

               $desc = trim(addslashes($selitem[0]["supp_company"]));
               ?>
               <option value="<?=$selitem[0]["id"]?>"><?=$desc?></option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row">
         <input type="hidden" id="country_money_type_<?=$x?>" value="<?=$posdata[$x]["country_money_type"]?>">
         <input class="text" name="item_code_<?=$x?>" id="item_code_<?=$x?>" style="width:90px"
         value="<?php if((int)$posdata[$x]["item_id"]) echo $posdata[$x]["item_code"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right">
         <input class="text" name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" style="width:65px;text-align:right">
      </td>
      <td class="content_row" align="right">
         <nobr>
         <input type="button" class="button" value="&lt;&lt;"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="if(document.getElementById('item_costprice_usd_<?=$x?>').value != '')
                  document.all.idxifrsrc.src='./libs/modules/items/get.usdclp.php?mode=' +document.getElementById('country_money_type_<?=$x?>').value +'&rowcount=<?=$x?>' +'&value=' +document.getElementById('item_costprice_usd_<?=$x?>').value"
         style="width:25px;<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>">
         
         <input class="text" name="item_costprice_usd_<?=$x?>" id="item_costprice_usd_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_usd"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         style="width:50px;text-align:right;<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>">
         <?if((int)$posdata[$x]["item_id"] && $supptax) echo "&nbsp;"?>
         </nobr>
      </td>
      <td class="content_row" align="right">
         <?php
         if((int)$buyhist["prc_item_id"])
         {
            $pbase = $buyhist["prc_costprice_netto"];
            $pnew  = $posdata[$x]["item_costprice_netto"];
               
            $diff = ($pnew - $pbase) / $pbase * 100;
            if($diff > 0.00)
               echo "+";
            echo printPrice(round($diff,0))."%";
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right">
         <input type="checkbox" name="item_supp_fob_<?=$x?>" value="1"
         style="<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>"
         <?php if((int)$posdata[$x]["item_supp_fob"]) echo "checked" ?>>
         <?if((int)$posdata[$x]["item_id"] && $supptax) echo "&nbsp;"?>
      </td>
      <td class="content_row" align="right">
         <input class="text" name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_taxes_perc"],2); else echo printPrice($_SESSION["_CONF"]["conf_taxes"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" style="width:40px;text-align:right">
      </td>
      <td class="content_row" align="right">
         <?php
         if((int)$posdata[$x]["item_id"])
            echo printPrice($posdata[$x]["item_costprice_brutto"],2);
         else
            echo "- - -";
         ?>
      </td>
      <td class="content_row">
         <input type="radio" name="item_supp_act" value="<?=$x?>"
         <?php if($x == 0) echo "checked" ?>>
      </td>
      <td class="content_row" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
            printButton("Condiciones", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=supplierdiscounts&id={$_REQUEST["id"]}&supplierid={$posdata[$x]["supplier_id"]}&itemtype=itemlist", "", "calculator");
         else
            echo "&nbsp;";
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<iframe style="width:1px;height:1px;display:none" id="idx_number_get" src=""></iframe>
<br>
<?=Nifty_printH("box3", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header">Familia</td>
</tr>
<tr>
   <td class="content_row_clear" style="padding-top:3px">
      <iframe id="cat_iframe" src="./libs/modules/productcats/list.php?catids=<?=$selitemcatidstr?>" width="100%" height="0" frameborder="0"></iframe>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.js_item_form)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');" ?>