<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from company_shops t1
         where
         t1.shop_status = 1 and
         t1.shop_company_id = {$_REQUEST["id"]}
         order by t1.shop_name";
$shops = $CON->select($sql);
   
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Sucursales asignadas</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Dirección</td>
   <td class="content_tbl_subheader">Bodegas</td>
</tr>
<?php

//----------------------------------------------------------------------------------
for($x = 0; $x < count($shops) && $shops != false; $x++)
{
   $sql = " select *
            from company_shops_storehouses
            where
            st_shop_id = {$shops[$x]["id"]} and
            st_status  = 1
            order by st_name";
   $storehouses = $CON->select($sql);
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$shops[$x]["id"]?>&nbsp;</td>
      <td class="content_row"><?=$shops[$x]["shop_name"]?>&nbsp;</td>
      <td class="content_row"><?=$shops[$x]["shop_street"]?>&nbsp;</td>
      <td class="content_row">
         <?php
         foreach($storehouses AS $storehouse)
         {  ?>
            <li type="square"> <?=$storehouse["st_name"]?><br>
            <?php
         }
         if(count($storehouses) == 0 || $storehouses == false)
            echo "&nbsp;";
         ?>
      </td>
   </tr><?php
}
?>
</table>
<?=Nifty_printF()?>