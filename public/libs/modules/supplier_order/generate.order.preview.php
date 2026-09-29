<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$poscounter = 0;
foreach(array_keys($_REQUEST) AS $reqkey)
{
   if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
   {
      $idx = substr($reqkey, strrpos($reqkey, "_") +1);
      $item_id       = (int)$_REQUEST["item_id_{$idx}"];
      $item_type     = $_REQUEST["item_type_{$idx}"];
      $item_supplier = (int)$_REQUEST["item_supplier_{$idx}"];
      $item_shopid   = (int)$_REQUEST["item_shopid_{$idx}"];
      $item_amount   = getPrice($_REQUEST["item_amount_{$idx}"],2);

      if($item_amount > 0.00)
         $_RES[$item_supplier][$item_id][$item_type] = $item_amount;
   }
}
$_SESSION[$_sesmodulename]["GENDATA"] = $_RES;
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Generar ordenes de compra: Paso 2</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<script language="JavaScript">
   function getShopSuppOrders(shopid, suppid)
   {
      $("#sql_sordid_" +suppid).load('/libs/modules/supplier_order/generate.orders.getsupporders.php?shopid=' +shopid +'&suppid=' +suppid);
   }
</script>
<?php
$shops = getShops($CON);
if(is_array($_RES))
{
   foreach(array_keys($_RES) AS $item_supplier)
   {
      $sql = " select supp_short
               from supplier
               where
               id = {$item_supplier}";
      $suppname = $CON->select($sql);
      $suppname = $suppname[0]["supp_short"];
      ?>
      <?=Nifty_printH("box2", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%" id="idx_tblgen_<?=$item_supplier?>">
      <colgroup>
         <col width="120">
         <col>
         <col width="120">
         <col width="120">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Orden de compra a proveedor: <?=$suppname?></td>
      </tr>
      <tr>
         <td class="content_rowl content_row_os">Sucursal destino</td>
         <td class="content_row_os">
            <select class="text" name="sql_shopid_<?=$item_supplier?>" id="sql_shopid_<?=$item_supplier?>" style="width:300px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="getShopSuppOrders(this.value, '<?=$item_supplier?>');">
               <?php
               foreach($shops AS $shop)
               {
                  if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
                  {
                     ?>
                     <option value="<?=$shop["id"]?>"
                     <?php if($shop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) { echo "selected"; $defshopid = $shop["id"]; }?>><?=$shop["shop_name"]?></option>
                     <?php
                  }
               }
               ?>
            </select>
            <?php
            $_SESSION["JSEXEC"] .= "getShopSuppOrders('{$defshopid}', '{$item_supplier}');";
            ?>
         </td>
         <td class="content_row_os" rowspan="2" colspan="2" align="center">
            <?php
            printButton("Generar OC", "postnav_save", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/supplier_order/generate.order.exec.fancy.php?suppid={$item_supplier}&shopid=' +document.getElementById('sql_shopid_{$item_supplier}').value +'&sordid=' +document.getElementById('sql_sordid_{$item_supplier}').value, 'iframe', 400, 200, 'no');", "tick-circle-frame", 150);
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl content_row_os">Orden de compra</td>
         <td class="content_row_os">
            <select class="text" name="sql_sordid_<?=$item_supplier?>" id="sql_sordid_<?=$item_supplier?>" style="width:300px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top">Numero</td>
         <td class="content_tbl_subheader content_row_os" valign="top">Artículo</td>
         <td class="content_tbl_subheader content_row_os" valign="top">Unidad</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">Cantidad</td>
      </tr>
      <?php
      $x = 0;
      foreach(array_keys($_RES[$item_supplier]) AS $item_id)
      {
         foreach(array_keys($_RES[$item_supplier][$item_id]) AS $item_type)
         {
            if($item_type == "itemlist")
            {
               $sql = " select t1.*, t7.unit_name
                        from itemlist t1
                        LEFT OUTER JOIN item_units t7 ON t1.item_unit = t7.id
                        where
                        t1.id = {$item_id}";
               $itemdata = $CON->select($sql);
               $itemdata = $itemdata[0];
            }
            else
            {
               $sql = " select t1.*, t7.unit_name
                        from item t1
                        LEFT OUTER JOIN item_units t7 ON t1.item_unit = t7.id
                        where
                        t1.id = {$item_id}";
               $itemdata = $CON->select($sql);
               $itemdata = $itemdata[0];
            }
            $item_amount = $_RES[$item_supplier][$item_id][$item_type];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$itemdata["item_number_prod"]?></td>
               <td class="content_row_os"><?=$itemdata["item_title"]?></td>
               <td class="content_row_os"><?=$itemdata["unit_name"]?></td>
               <td class="content_row_os" align="right"><?=printPrice($item_amount,2)?>
            </tr>
            <?php
            $x++;
         }
      }
      ?>
      </table>
      <span id="idx_spangen_<?=$item_supplier?>" style="display:none">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_tbl_header">Orden de compra a proveedor: <?=$suppname?></td>
         </tr>
         <tr>
            <td class="content_row_clear" id="idx_spangendtl_<?=$item_supplier?>"></td>
         </tr>
         </table>
      </span>
      <?=Nifty_printF(false)?>
      
      <br>
      <?php
   }
}
else
{  ?>
   <script language="JavaScript">
      alert('No hay datos disponibles');
      location.href='index.php?mid=<?=$_REQUEST["mid"]?>';
   </script>
   <?php
}