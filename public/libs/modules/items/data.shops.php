<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$sql = " select t1.*
         from item t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];

if($_REQUEST["subexec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "shop_act_") !== false && (int)$_REQUEST[$reqkey])
      {
         $shopidx                      = substr($reqkey, strrpos($reqkey, "_") +1);
         $itemshop_price_man           = (int)$_REQUEST["itemshop_price_man_{$shopidx}"];

         if($itemshop_price_man)
         {
            $itemshop_sellprice_brutto       = getPrice($_REQUEST["itemshop_sellprice_brutto_{$shopidx}"]);
            $itemshop_sellprice_taxes_perc   = getPrice($_REQUEST["itemshop_sellprice_taxes_perc_{$shopidx}"],2);
            $itemshop_sellprice_taxes        = round($itemshop_sellprice_brutto / (100 + $itemshop_sellprice_taxes_perc) * $itemshop_sellprice_taxes_perc);
            $itemshop_sellprice_netto        = round($itemshop_sellprice_brutto - $itemshop_sellprice_taxes);
         }
         else
         {
            $itemshop_sellprice_brutto       = $item["item_sellprice_brutto"];
            $itemshop_sellprice_taxes_perc   = $item["item_sellprice_taxes_perc"];
            $itemshop_sellprice_taxes        = $item["item_sellprice_taxes"];
            $itemshop_sellprice_netto        = $item["item_sellprice_netto"];
         }

         //----------------------------------------------------------------------------------
         $sql = " select count(*) 'cc'
                  from item_shops
                  where
                  item_id = {$_REQUEST["id"]} and
                  shop_id = {$shopidx}";
         $chk = $CON->select($sql);

         if((int)$chk[0]["cc"])
         {
            $sql = " update item_shops
                     set
                     itemshop_price_man            = {$itemshop_price_man},
                     itemshop_sellprice_brutto     = {$itemshop_sellprice_brutto},
                     itemshop_sellprice_netto      = {$itemshop_sellprice_netto},
                     itemshop_sellprice_taxes_perc = {$itemshop_sellprice_taxes_perc},
                     itemshop_sellprice_taxes      = {$itemshop_sellprice_taxes}
                     where
                     item_id = {$_REQUEST["id"]} and
                     shop_id = {$shopidx}";
            $res = $CON->no_result($sql);
         }
         else
         {
            $sql = " insert into item_shops
                     (item_id, shop_id, itemshop_price_man, itemshop_sellprice_brutto, itemshop_sellprice_netto,
                      itemshop_sellprice_taxes_perc, itemshop_sellprice_taxes)
                     VALUES
                     ({$_REQUEST["id"]}, {$shopidx}, {$itemshop_price_man}, {$itemshop_sellprice_brutto},
                      {$itemshop_sellprice_netto}, {$itemshop_sellprice_taxes_perc}, {$itemshop_sellprice_taxes})";
             $res = $CON->no_result($sql);
         }

         $savemsg = getSaveMessage($res);
      }

      //----------------------------------------------------------------------------------
      if(strpos($reqkey, "shop_existing_") !== false)
      {
         $shopidx = substr($reqkey, strrpos($reqkey, "_") +1);

         if(!(int)$_REQUEST["shop_act_{$shopidx}"])
         {
            $sql = " delete from item_shops
                     where
                     item_id = {$_REQUEST["id"]} and
                     shop_id = {$shopidx}";
            $res = $CON->no_result($sql);

            $sql = " delete from item_shops_storehouses
                     where
                     item_id = {$_REQUEST["id"]} and
                     shop_id = {$shopidx}";
            $res = $CON->no_result($sql);
   
            $savemsg = getSaveMessage($res);
         }
      }
   }
   registerSellPriceHistory($CON, $_REQUEST["id"], "item");
}

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_name";
$companies = $CON->select($sql);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="js_item_form">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?php
foreach($companies AS $company)
{
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status = 1 and
            t1.shop_company_id = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col width="50">
      <col width="150">
      <col>
      <?php
      if($item["item_sellable"])
      {  ?>
         <col width="70">
         <col width="90">
         <col width="90">
         <col width="130">
         <?php
      }
      ?>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8"><?=$company["company_name"]?>, <?=$company["company_rut"]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center">Act.</td>
      <td class="content_tbl_subheader">Numero</td>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Direccion</td>
      <?php
      if($item["item_sellable"])
      {  ?>
         <td class="content_tbl_subheader" align="center">Manual</td>
         <td class="content_tbl_subheader" align="right">Precio bruto</td>
         <td class="content_tbl_subheader" align="right">IVA %</td>
         <td class="content_tbl_subheader" align="right">Precio neto</td>
         <?php
      }
      ?>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {
      $shopid = $shops[$x]["id"];

      $sql = " select *
               from item_shops
               where
               item_id = {$_REQUEST["id"]} and
               shop_id = {$shopid}";
      $seldata = $CON->select($sql);
      $seldata = $seldata[0];

      if(!(int)$seldata["shop_id"] || !(int)$seldata["itemshop_price_man"])
         $hidefields = true;
      else
         $hidefields = false;

      if((int)$seldata["shop_id"])
         $rowstyle = "style='color:#000000'";
      else
         $rowstyle = "style='color:#888888'";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="hidden" name="shop_existing_<?=$shopid?>" value="1">
            <input type="checkbox" name="shop_act_<?=$shopid?>" value="1"
            <?php if((int)$seldata["shop_id"]) echo "checked" ?>>
         </td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["id"]?>&nbsp;</td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_street"]?>&nbsp;</td>
         <?php
         if($item["item_sellable"])
         {  ?>
            <td class="content_row" align="center">
               <input type="checkbox" name="itemshop_price_man_<?=$shopid?>" value="1"
               <?php if(((int)$seldata["shop_id"] && (int)$seldata["itemshop_price_man"])) echo "checked" ?>
               onclick="if(!this.checked)
                        {
                           document.all.idxsp_<?=$shopid?>.style.display = 'none';
                           document.all.idxst_<?=$shopid?>.style.display = 'none';
                           document.all.sp_idxsp_<?=$shopid?>.style.display = '';
                           document.all.sp_idxst_<?=$shopid?>.style.display = '';
                        }
                        else
                        {
                           document.all.idxsp_<?=$shopid?>.style.display = '';
                           document.all.idxst_<?=$shopid?>.style.display = '';
                           document.all.sp_idxsp_<?=$shopid?>.style.display = 'none';
                           document.all.sp_idxst_<?=$shopid?>.style.display = 'none';
                        }">
            </td>
            <td class="content_row" align="right" <?=$rowstyle?>>
               <input class="text" name="itemshop_sellprice_brutto_<?=$shopid?>" id="idxsp_<?=$shopid?>"
               style="width:70px;text-align:right;<?php if($hidefields) echo "display:none"?>"
               value="<?php if((int)$seldata["shop_id"]) echo printPrice($seldata["itemshop_sellprice_brutto"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <span id="sp_idxsp_<?=$shopid?>" style="<?php if(!$hidefields) echo "display:none"?>"><?php if((int)$seldata["shop_id"]) echo $_SESSION["_CONF"]["conf_currency"]." ".printPrice($seldata["itemshop_sellprice_brutto"])?></span>
            </td>
            <td class="content_row" align="right" <?=$rowstyle?>>
               <input class="text" name="itemshop_sellprice_taxes_perc_<?=$shopid?>" id="idxst_<?=$shopid?>"
               style="width:50px;text-align:right;<?php if($hidefields) echo "display:none"?>"
               value="<?php if((int)$seldata["shop_id"]) echo printPrice($seldata["itemshop_sellprice_taxes_perc"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <span id="sp_idxst_<?=$shopid?>" style="<?php if(!$hidefields) echo "display:none"?>"><?php if((int)$seldata["shop_id"]) echo printPrice($seldata["itemshop_sellprice_taxes_perc"],2)?></span>
            </td>
            <td class="content_row" align="right" <?=$rowstyle?>><?php if((int)$seldata["shop_id"]) echo $_SESSION["_CONF"]["conf_currency"]." ".printPrice($seldata["itemshop_sellprice_netto"])?>&nbsp;</td>
            <?php
         }
         ?>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
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
<?php $_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');" ?>
<br><br>