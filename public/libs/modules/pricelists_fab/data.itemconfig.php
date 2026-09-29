<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "existingid_") !== false && strpos($reqkey, "existingid_") == 0)
      {
         $idxarr              = explode("_", $reqkey);
         $fab_type            = $idxarr[1];
         $header_id           = $idxarr[2];
         $posidx              = $idxarr[3];
         $idxstr              = "{$fab_type}_{$header_id}_{$posidx}";

         $existingid             = (int)$_REQUEST["existingid_{$idxstr}"];
         $fab_med_width          = (int)$_REQUEST["fab_med_width_{$idxstr}"];
         $fab_med_height         = (int)$_REQUEST["fab_med_height_{$idxstr}"];
         $fab_med_fuelle         = (int)$_REQUEST["fab_med_fuelle_{$idxstr}"];
         $fab_printtype          = trim($_REQUEST["fab_printtype_{$idxstr}"]);
         $fab_print_colors_front = (int)$_REQUEST["fab_print_colors_front_{$idxstr}"];
         $fab_print_colors_back  = (int)$_REQUEST["fab_print_colors_back_{$idxstr}"];

         if($fab_med_width && $fab_med_height && $fab_printtype != "")
         {
            if($existingid)
            {
               $sql = " update price_lists_fab_items_predefines
                        set
                        fab_med_width           = {$fab_med_width},  
                        fab_med_height          = {$fab_med_height},
                        fab_med_fuelle          = {$fab_med_fuelle},
                        fab_printtype           = '{$fab_printtype}',
                        fab_print_colors_front  = {$fab_print_colors_front},
                        fab_print_colors_back   = {$fab_print_colors_back}
                        where
                        id = {$existingid}";
               $res = $CON->no_result($sql);
            }
            else
            {
               $sql = " insert into price_lists_fab_items_predefines
                        (header_id, fab_med_width, fab_med_height, fab_med_fuelle, fab_printtype,
                         fab_print_colors_front, fab_print_colors_back)
                        VALUES
                        ({$header_id}, {$fab_med_width}, {$fab_med_height}, {$fab_med_fuelle},
                         '{$fab_printtype}', {$fab_print_colors_front}, {$fab_print_colors_back})";
               $res = $CON->no_result($sql);
            }
         }
      }
   }

   if((int)$_REQUEST["delpositem"])
   {
      $sql = " delete from price_lists_fab_items_predefines
               where
               id = {$_REQUEST["delpositem"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from fabric_types
         where
         fabt_status > 0
         order by fabt_code";
$fabric_types = $CON->select($sql);
foreach($fabric_types AS $fabric_type)
{
   $_LISTTYPES[$fabric_type["fabt_code"]] = 1;
   $_LISTTYPESNAMES[$fabric_type["fabt_code"]] = $fabric_type["fabt_name"];
}

//---------------------------------------------------------------------------------
$sql = " select t1.*, t2.item_title, t2.item_number_prod, t3.inc_name
         from price_lists_fab_items t1
         LEFT OUTER JOIN item t2 ON t1.fab_item_id = t2.id
         LEFT OUTER JOIN price_lists_fab_increments t3 ON t1.fab_inc_id = t3.id
         where
         t1.pl_id = {$_REQUEST["id"]}
         order by t2.item_title, t1.fab_desc, t1.fab_item_id, t1.id";
$data = $CON->select($sql);
foreach($data AS $row)
{
   $idx = $row["fab_type"];
   $_RES[$idx][] = $row;
}

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_pl">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="itemconfig">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="delpositem" value="">
<?php
//---------------------------------------------------------------------------------
foreach(array_keys($_LISTTYPES) AS $ltype)
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="40">
      <col>
      <col width="140">
      <col width="140">
      <col width="60">
      <col width="60">
      <col width="60">
      <col width="100">
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="9"><?=$ltype?>: <?=$_LISTTYPESNAMES[$ltype]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader content_row_os">&nbsp;</td>
      <td class="content_tbl_subheader content_row_os">Producto</td>
      <td class="content_tbl_subheader content_row_os">Incremento</td>
      <td class="content_tbl_subheader content_row_os">Descripción</td>
      <td class="content_tbl_subheader content_row_os" align="center">Ancho</td>
      <td class="content_tbl_subheader content_row_os" align="center">Alto</td></td>
      <td class="content_tbl_subheader content_row_os" align="center">Fuelle</td>
      <td class="content_tbl_subheader content_row_os">Impresion</td>
      <td class="content_tbl_subheader content_row_os" align="center">Colores</td>
   </tr>
   <?php
   for($x = 0; $x < count($_RES[$ltype]); $x++)
   {
      $mainrow = $_RES[$ltype][$x];
      ?>
      <tr bgcolor="#00A9A6">
         <td class="content_row_os" style="color:white">&nbsp;</td>
         <td class="content_row_os" style="color:white"><b><?=$mainrow["item_number_prod"]?> - <?=$mainrow["item_title"]?></b></td>
         <td class="content_row_os" style="color:white"><b><?=$mainrow["inc_name"]?>&nbsp;</b></td>
         <td class="content_row_os" style="color:white"><b><?=$mainrow["fab_desc"]?>&nbsp;</b></td>
         <td class="content_row_os" style="color:white" align="center"><?if((int)$mainrow["id"]) echo (int)$mainrow["fab_med_width"]?>&nbsp;</td>
         <td class="content_row_os" style="color:white" align="center"><?if((int)$mainrow["id"]) echo (int)$mainrow["fab_med_height"]?>&nbsp;</td>
         <td class="content_row_os" style="color:white" align="center"><?if((int)$mainrow["fab_med_fuelle"]) echo (int)$mainrow["fab_med_fuelle"]?>&nbsp;</td>
         <td class="content_row_os">&nbsp;</td>
         <td class="content_row_os">&nbsp;</td>
      </tr>
      <?php
      $sql = " select *
               from price_lists_fab_items_predefines
               where
               header_id = {$mainrow["id"]}
               order by fab_printtype, fab_med_width, fab_med_height, fab_med_fuelle";
      $predefines = $CON->select($sql);
      $rowcount = count($predefines) +8;
      for($y = 0; $y < $rowcount; $y++)
      {
         $predefine = $predefines[$y];
         ?>
         <input type="hidden" name="existingid_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>" value="<?=$predefine["id"]?>">
         
         <tr bgcolor="<?=getRowColor($y)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center">
               <?php
               if((int)$predefine["id"])
               {  ?>
                  <input type="button" class="buttonred" value="x" style="width:20px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="if(askDel('')) { document.xform_pl.delpositem.value = '<?=$predefine["id"]?>'; submitForm(document.xform_pl); }">
                  <?php
               }
               else
               {  ?>
                  <img src="/images/menu/icons/plus.png">
                  <?php
               }
               ?>
            </td>
            <td class="content_row_os" colspan="3">
               <img src="/images/menu/icons/arrow-turn-000-left.png">
               Registrar información del producto
            </td>
            <td class="content_row_os" align="center">
               <input type="text" class="text" style="width:60px;text-align:center"
               name="fab_med_width_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>"
               value="<?if((int)$predefine["id"]) echo (int)$predefine["fab_med_width"]?>">
            </td>
            <td class="content_row_os" align="center">
               <input type="text" class="text" style="width:60px;text-align:center"
               name="fab_med_height_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>"
               value="<?if((int)$predefine["id"]) echo (int)$predefine["fab_med_height"]?>">
            </td>
            <td class="content_row_os" align="center">
               <input type="text" class="text" style="width:60px;text-align:center"
               name="fab_med_fuelle_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>"
               value="<?if((int)$predefine["fab_med_fuelle"]) echo (int)$predefine["fab_med_fuelle"]?>">
            </td>
            <td class="content_row_os">
               <select class="text" style="width:100%" name="fab_printtype_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>">
                  <option value="">Seleccione</option>
                  <option value="FLEX" <?if($predefine["fab_printtype"] == "FLEX") echo "selected"?>>Flexografía</option>
                  <option value="SERI" <?if($predefine["fab_printtype"] == "SERI") echo "selected"?>>Serigrafía</option>
               </select>
            </td>
            <td class="content_row_os" align="center">
               <input type="text" class="text" style="width:25px;text-align:center"
               name="fab_print_colors_front_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>"
               value="<?if((int)$predefine["id"]) echo (int)$predefine["fab_print_colors_front"]?>"> /
               <input type="text" class="text" style="width:25px;text-align:center"
               name="fab_print_colors_back_<?=$ltype?>_<?=$mainrow["id"]?>_<?=$y?>"
               value="<?if((int)$predefine["id"]) echo (int)$predefine["fab_print_colors_back"]?>">
            </td>
         </td>
         </tr>
         <?php
      }
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<div style="position:fixed;left:1000px;top:300px;width:120px">
   <?php
   printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pl)", "disk-black");
   ?>
</div>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pl)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';