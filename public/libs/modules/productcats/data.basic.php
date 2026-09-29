<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   $currtme = time();

   $_REQUEST["cat_title"]              = trim(addslashes($_REQUEST["cat_title"]));
   $_REQUEST["cat_desc"]               = trim(addslashes($_REQUEST["cat_desc"]));
   $_REQUEST["cat_prefix"]             = trim(addslashes($_REQUEST["cat_prefix"]));
   $_REQUEST["cat_calc_price_perc"]    = getPrice($_REQUEST["cat_calc_price_perc"],2);
   $_REQUEST["cat_sellprice_min"]      = getPrice($_REQUEST["cat_sellprice_min"]);
   $_REQUEST["cat_dsc_maxperc"]        = getPrice($_REQUEST["cat_dsc_maxperc"],2);
   $_REQUEST["cat_dsc_maxbuyperc"]     = getPrice($_REQUEST["cat_dsc_maxbuyperc"],2);
   $_REQUEST["cat_dsc_off"]            = (int)$_REQUEST["cat_dsc_off"];
   $_REQUEST["cat_released"]           = (int)$_REQUEST["cat_released"];
   $_REQUEST["cat_parentid"]           = (int)$_REQUEST["cat_parentid"];
   $_REQUEST["cat_id"]                 = (int)$_REQUEST["cat_id"];
   $_REQUEST["cat_salary_seperate"]    = (int)$_REQUEST["cat_salary_seperate"];
   $_REQUEST["cat_itemreg_width"]      = (int)$_REQUEST["cat_itemreg_width"];
   $_REQUEST["cat_itemreg_gsm"]        = (int)$_REQUEST["cat_itemreg_gsm"];
   $_REQUEST["cat_itemreg_length"]     = (int)$_REQUEST["cat_itemreg_length"];
   $_REQUEST["cat_itemreg_kg"]         = (int)$_REQUEST["cat_itemreg_kg"];
   $_REQUEST["cat_itemreg_machine_assign"] = (int)$_REQUEST["cat_itemreg_machine_assign"];
   $_REQUEST["cat_repuestos_act"] = (int)$_REQUEST["cat_repuestos_act"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into productcats{$_REQUEST["tbl_suffix"]}
               (id, cat_title, cat_desc, cat_released, cat_parentid, cat_calc_price_perc, cat_crtusr, cat_crtdat,
               cat_dsc_off, cat_dsc_maxperc, cat_sellprice_min, cat_dsc_maxbuyperc, cat_salary_seperate, cat_prefix,
               cat_itemreg_width, cat_itemreg_gsm, cat_itemreg_length, cat_itemreg_machine_assign, cat_itemreg_kg,
               cat_repuestos_act)
               VALUES
               ({$_REQUEST["cat_id"]}, '{$_REQUEST["cat_title"]}', '{$_REQUEST["cat_desc"]}', 1,
                {$_REQUEST["cat_parentid"]}, {$_REQUEST["cat_calc_price_perc"]},
                {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["cat_dsc_off"]},
                {$_REQUEST["cat_dsc_maxperc"]}, {$_REQUEST["cat_sellprice_min"]},
                {$_REQUEST["cat_dsc_maxbuyperc"]}, {$_REQUEST["cat_salary_seperate"]}, '{$_REQUEST["cat_prefix"]}',
                {$_REQUEST["cat_itemreg_width"]}, {$_REQUEST["cat_itemreg_gsm"]}, {$_REQUEST["cat_itemreg_length"]},
                {$_REQUEST["cat_itemreg_machine_assign"]}, {$_REQUEST["cat_itemreg_kg"]}, {$_REQUEST["cat_repuestos_act"]})";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from productcats{$_REQUEST["tbl_suffix"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " select   t1.*
               from productcats t1
               where
               t1.id = {$_REQUEST["id"]}";
      $cat = $CON->select($sql);

      //----------------------------------------------------------------------------------
      if((float)$_REQUEST["cat_calc_price_perc"] != (float)$cat[0]["cat_calc_price_perc"])
      {
         $sql = " select t1.id, t1.item_sellprice_taxes_perc, t3.item_costprice_netto, 'item_type' 'I'
                  from item t1
                  INNER JOIN item_productcats t2 ON      ( t1.id = t2.item_id and t2.cat_id = {$_REQUEST["id"]} )
                  LEFT OUTER JOIN item_suppliers t3 ON   ( t1.id = t3.item_id and t3.item_supp_act = 1 )
                  where
                  t1.item_sellable              = 1 and
                  t1.item_sellprice_calc        = 1 and
                  t1.item_status                > 0 and
                  t1.item_sellprice_calc_type   = 'CAT'
                  UNION ALL
                  select t1.id, t1.item_sellprice_taxes_perc, t3.item_costprice_netto, 'item_type' 'L'
                  from itemlist t1
                  INNER JOIN item_productcats_itemlist t2 ON   ( t1.id = t2.item_id and t2.cat_id = {$_REQUEST["id"]} )
                  LEFT OUTER JOIN itemlist_suppliers t3 ON     ( t1.id = t3.item_id and t3.item_supp_act = 1 )
                  where
                  t1.item_sellable              = 1 and
                  t1.item_sellprice_calc        = 1 and
                  t1.item_status                > 0 and
                  t1.item_sellprice_calc_type   = 'CAT'";
         $items = $CON->select($sql);

         //----------------------------------------------------------------------------------
         foreach($items AS $item)
         {
            if($item["item_type"] == "item_typeI")
               $item["item_type"] = "item";
            else
               $item["item_type"] = "itemlist";

            //----------------------------------------------------------------------------------
            $item_sellprice_taxes_perc = $item["item_sellprice_taxes_perc"];
            $item_costprice_netto      = $item["item_costprice_netto"];
            $item_sellprice_netto      = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", (float)$item["item_costprice_netto"]+ ((float)$item["item_costprice_netto"] / 100 * $_REQUEST["cat_calc_price_perc"]));

            $item_sellprice_taxes   = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_costprice_netto / 100 * $item_sellprice_taxes_perc);
            $item_sellprice_brutto  = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_costprice_netto + $item_sellprice_taxes);

            //----------------------------------------------------------------------------------
            $sql = " update {$item["item_type"]}
                     set
                     item_sellprice_netto       = {$item_sellprice_netto},
                     item_sellprice_brutto      = {$item_sellprice_brutto},
                     item_sellprice_taxes       = {$item_sellprice_taxes},
                     item_sellprice_calc_perc   = {$_REQUEST["cat_calc_price_perc"]}
                     where
                     id = {$item["id"]}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            if($item["item_type"] == "item")
               updateItemStorePrices($CON, $item["id"]);
            else
               updateItemlistStorePrices($CON, $item["id"]);

            registerSellPriceHistory($CON, $item["id"], $item["item_type"]);

            $shwupdcount = true;
            $priceupdcount++;
         }
      }
   
      $sql = " update productcats{$_REQUEST["tbl_suffix"]}
               set
               cat_title            = '{$_REQUEST["cat_title"]}',
               cat_desc             = '{$_REQUEST["cat_desc"]}',
               cat_prefix           = '{$_REQUEST["cat_prefix"]}',
               cat_released         = 1,
               cat_parentid         = {$_REQUEST["cat_parentid"]},
               cat_calc_price_perc  = {$_REQUEST["cat_calc_price_perc"]},
               cat_sellprice_min    = {$_REQUEST["cat_sellprice_min"]},
               cat_dsc_maxperc      = {$_REQUEST["cat_dsc_maxperc"]},
               cat_dsc_off          = {$_REQUEST["cat_dsc_off"]},
               cat_dsc_maxbuyperc   = {$_REQUEST["cat_dsc_maxbuyperc"]},
               cat_salary_seperate  = {$_REQUEST["cat_salary_seperate"]},
               cat_itemreg_width    = {$_REQUEST["cat_itemreg_width"]},
               cat_itemreg_gsm      = {$_REQUEST["cat_itemreg_gsm"]},
               cat_itemreg_length   = {$_REQUEST["cat_itemreg_length"]},
               cat_itemreg_kg       = {$_REQUEST["cat_itemreg_kg"]},
               cat_itemreg_machine_assign = {$_REQUEST["cat_itemreg_machine_assign"]},
               cat_repuestos_act = {$_REQUEST["cat_repuestos_act"]},
               cat_updusr           = {$_SESSION["user_id"]}, 
               cat_upddat           = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);

      if($res)
         $thisid = $_REQUEST["id"];
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   if($_REQUEST["reactivate"] == "1")
   {
      $sql = " update productcats{$_REQUEST["tbl_suffix"]}
               set
               cat_status = 1
               where
               id = {$_REQUEST["id"]}";
      $CON->select($sql);
   }
   $sql = " select   t1.*,
                     t2.user_lastname 'crt_lastname', t2.user_firstname 'crt_firstname',
                     t3.user_lastname 'upd_lastname', t3.user_firstname 'upd_firstname'
            from productcats{$_REQUEST["tbl_suffix"]} t1
            LEFT OUTER JOIN user t2 ON t1.cat_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.cat_updusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $cat = $CON->select($sql);

   $_REQUEST["cat_parentid"] = (int)$cat[0]["cat_parentid"];
}
else
{
   $sql = " select MAX(id) 'catid'
            from productcats{$_REQUEST["tbl_suffix"]}";
   $maxid = $CON->select($sql);
   $maxid = $maxid[0]["catid"] +1;
}
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function checkItemNumber(xcat)
   {
      $("#idx_data").load("./libs/modules/productcats/checkcatnumber.php?xcat=" +xcat);
   }

   function calcMaxDiscount(addon, destobj)
   {
      var base = 100.00;
      
      var c1 = $("#" +addon +"calc1").val().replace('.','').replace(',','.');
      var c2 = $("#" +addon +"calc2").val().replace('.','').replace(',','.');
      var c3 = $("#" +addon +"calc3").val().replace('.','').replace(',','.');
      var c4 = $("#" +addon +"calc4").val().replace('.','').replace(',','.');

      if(c1 > 0.00) base = base - (base / 100 * c1);
      if(c2 > 0.00) base = base - (base / 100 * c2);
      if(c3 > 0.00) base = base - (base / 100 * c3);
      if(c4 > 0.00) base = base - (base / 100 * c4);

      base = (100 - base).toFixed(2);
      
      $("#" +destobj).val(base);
      $("#" +destobj).val($("#" +destobj).val().replace('.',','));
   }
</script>
<form action="index.php" method="post" class="fokusfirst" enctype="multipart/form-data" name="xform_pcat"
 onsubmit="if(!askDel('')) return false; else return checkform(new Array(this.cat_title, this.cat_prefix <?php if($_REQUEST["id"] == "") echo ', this.cat_id' ?>))">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="subexec" value="<?=$_REQUEST["subexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="cat_parentid" value="<?=$_REQUEST["cat_parentid"]?>">
<input type="hidden" name="tbl_suffix" value="<?=$_REQUEST["tbl_suffix"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="160">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de familia</td>
</tr>
<tr>
   <td class="content_rowl">ID</td>
   <td class="content_row">
      <?php
      if($_REQUEST["id"] == "")
      {  ?>
         <input name="cat_id" id="cat_id" type="text" class="text" style="width:90px"
         value="<?=sprintf("%03s", $maxid)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         onkeyup="checkItemNumber(this.value)">
         <?php
      }
      else
         echo sprintf("%03s", $cat[0]["id"]);
      ?>
      <span id="idx_data"></span>
      <?php
      if($_REQUEST["id"] == "")
      {  ?>
         <span><a href="javascript: showFancybox('./libs/modules/productcats/checkcatnumber.disp.php?xcat=' +document.getElementById('cat_id').value, 'iframe', 450, 450, 'auto')" class="link">[Numeros disponibles]</a></span>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Prefijo *</td>
   <td class="content_row">
      <input name="cat_prefix" type="text" class="text" style="width:90px" value="<?=$cat[0]["cat_prefix"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="cat_title" type="text" class="text" style="width:834px" value="<?=$cat[0]["cat_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="cat_desc" class="text" style="width:834px; height:85px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$cat[0]["cat_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Productos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_itemreg_width"
      <?php if((int)$cat[0]["cat_itemreg_width"]) echo "checked"?>> Registrar ancho (cm)
   </td>
</tr>
<tr>
   <td class="content_rowl">Productos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_itemreg_gsm"
      <?php if((int)$cat[0]["cat_itemreg_gsm"]) echo "checked"?>> Registrar GSM (gr)
   </td>
</tr>
<tr>
   <td class="content_rowl">Productos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_itemreg_length"
      <?php if((int)$cat[0]["cat_itemreg_length"]) echo "checked"?>> Registrar longitud (m)
   </td>
</tr>
<tr>
   <td class="content_rowl">Productos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_itemreg_kg"
      <?php if((int)$cat[0]["cat_itemreg_kg"]) echo "checked"?>> Registrar Kilogramos (kg)
   </td>
</tr>
<tr>
   <td class="content_rowl">Productos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_itemreg_machine_assign"
      <?php if((int)$cat[0]["cat_itemreg_machine_assign"]) echo "checked"?>> Asociar máquinas (p.e. repuestos)
   </td>
</tr>
<tr>
   <td class="content_rowl">Entrega repuestos</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="cat_repuestos_act"
      <?php if((int)$cat[0]["cat_repuestos_act"]) echo "checked"?>> Activado
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$cat[0]["crt_firstname"]?>&nbsp;<?=$cat[0]["crt_lastname"]?></td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($cat[0]["cat_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$cat[0]["upd_firstname"]?>&nbsp;<?=$cat[0]["upd_lastname"]?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($cat[0]["cat_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<iframe style="width:1px;height:1px;display:none" id="idx_number_get" src=""></iframe>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td width="130" align="right" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&id={$_REQUEST["id"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pcat)", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br><br>
<?php
$_SESSION["JSEXEC"] .= "addFormListeners('xform_pcat');";
if($shwupdcount)
   $_SESSION["JSEXEC"] .= ";alert('PRECIOS ACTUALIZADOS: {$priceupdcount}');";
?>