<?php
unset($_RESGLBIDS);
global $_RESGLBIDS;
getOrdersRelations($CON, $_REQUEST["id"]);

foreach(array_keys($_RESGLBIDS) AS $relidx)
{
   $sql = " select *
            from orders
            where
            id = {$relidx}";
   $xhead = $CON->select($sql);
   $xhead = $xhead[0];
   ?>
   <?=Nifty_printH("box1", "1018")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="120">
      <col>
      <col width="90">
      <col width="100">
      <col width="100">
      <col width="140">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="10">Nota de Venta: <?=$xhead["req_number"]?></td>
   </tr>
   <tr>
      <td class="content_row_os content_tbl_subheader">Artículo</td>
      <td class="content_row_os content_tbl_subheader">Nombre</td>
      <td class="content_row_os content_tbl_subheader">Cantidad</td>
      <td class="content_row_os content_tbl_subheader">Unidad</td>
      <td class="content_row_os content_tbl_subheader">Precio/U/Neto</td>
      <td class="content_row_os content_tbl_subheader">Precio/Total/Neto</td>
   </tr>
   <?php
   $x       = 0;
   $posdata = getOrderPos($CON, $relidx);
   foreach($posdata AS $row)
   {  ?>
      <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os"><?=$row["item_number_prod"]?>&nbsp;</td>
         <td class="content_row_os"><?=$row["item_title"]?>&nbsp;</td>
         <td class="content_row_os"><?=printPrice($row["item_amount"],2)?>&nbsp;</td>
         <td class="content_row_os"><?=getItemUnitDesc($CON, $row["item_id"], $row["item_type"])?></td>
         <td class="content_row_os"><?=printPrice(round($row["item_sellprice_netto_dsc"] / $row["item_amount"]))?>&nbsp;</td>
         <td class="content_row_os"><?=printPrice($row["item_sellprice_netto_dsc"])?>&nbsp;</td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}