<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["item_int_pack_act"] = (int)$_REQUEST["item_int_pack_act"];

   $sql = " update item
            set
            item_int_pack_act = {$_REQUEST["item_int_pack_act"]}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from prod_item_pack_pos
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $item_amount = getPrice($_REQUEST["item_amount_{$idx}"]);

         if($_REQUEST["item_id_{$idx}"] != "" && $item_amount > 0)
         {
            $itemvalues    = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$itemvalues[0];
            $sql_type      = $itemvalues[1];

            if((int)$_REQUEST["delitempos"] != $sql_id && $sql_id != $_REQUEST["id"])
            {
               $sql = " insert into prod_item_pack_pos
                        (item_id, pack_item_id, pack_item_pos, pack_item_amount)
                        VALUES
                        ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$item_amount})";
               $CON->no_result($sql);
               $poscounter++;
            }
         }
      }
   }

   $savemsg = getSaveMessage($res);
}

$sql = " select t1.*
         from item t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function showForm()
   {
      if(document.getElementById('item_ext_prod_act').checked == true)
      {
         document.getElementById('row1').style.display = "";
         document.getElementById('row2').style.display = "";
      }
      else
      {
         document.getElementById('row1').style.display = "none";
         document.getElementById('row2').style.display = "none";
      }
   }

   function showFormInt()
   {
      if(document.getElementById('item_int_prod_act').checked == true)
      {
         $('.prodint').each(function(index, value)
         {
            $(this).show();
         });
      }
      else
      {
         $('.prodint').each(function(index, value)
         {
            $(this).hide();
         });
      }
   }

   function showSupplier(itemid)
   {

   }
</script>
<form action="index.php" method="post" class="fokusfirst" name="js_item_form">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="delitempos" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col width="25">
   <col width="300">
   <col width="80">
   <col width="80">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Contenido Pack</td>
</tr>
<tr>
   <td class="content_row_clear content_row_os" colspan="5" align="left">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="110">
      <colgroup>
         <col width="85">
         <col width="28">
      </colgroup>
      <tr>
         <td class="content_row_clear">
            Activado
         </td>
         <td class="content_row_clear">
            <input type="checkbox" class="text" name="item_int_pack_act" id="item_int_pack_act" value="1" onchange="showFormInt()"
            <?if($item["item_int_pack_act"] == 1) echo "checked"?>>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr class="prodint">
   <td class="content_tbl_subheader content_row_os" valign="top">Busqueda</font></td>
   <td class="content_tbl_subheader content_row_os" valign="top">Act.</font></td>
   <td class="content_tbl_subheader content_row_os" valign="top">Artículo</font></td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Unidad</font></td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Cantidad</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$posdata = getItemProdPackItems($CON, $_REQUEST["id"]);

$rowcount = 0;
if(count($posdata) && $posdata != false)
   $rowcount = count($posdata);
$rowcount += 8;

//----------------------------------------------------------------------------------
for($y = 0; $y < $rowcount; $y++)
{
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "xf_search_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_id_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_amount_{$y}";
   ?>
   <tr class="prodint" bgcolor="<?=getRowColor($y)?>">
      <td class="content_row_os" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$y?>">
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" name="xf_search_<?=$y?>" id="xf_search_<?=$y?>"
               onfocus="markfield(this,0)" autocomplete="off"
               <?php
               if(!(int)$posdata[$y]["item_id"])
               {  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/stats/searchitem.php?rowcount=<?=$y?><?=$urlparam?>&search=' +this.value} this.value='';"
                  onkeyup="detectEvent(event, '<?=$y?>', '<?=$_REQUEST["id"]?>')"
                  <?php
               }
               else
               {  ?>
                  onblur="markfield(this,1)"
                  <?php
                  $hasItems = true;
               }
               ?>>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row_os" valign="top">
         <?php
         if((int)$posdata[$y]["item_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.js_item_form.delitempos.value='<?=$posdata[$y]["pack_item_id"]?>'; submitForm(document.js_item_form); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row_os" valign="top">
         <select class="text" style="width:680px;"
         name="item_id_<?=$y?>" id="item_id_<?=$y?>"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$y]["item_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               $desc = trim(addslashes($posdata[$y]["item_title"]));
               ?>
               <option value="<?=$posdata[$y]["pack_item_id"]?>#item"><?=$desc?></option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row_os" valign="top" align="center">
         <?php
         if((int)$posdata[$y]["item_id"])
            echo getItemUnitDesc($CON, $posdata[$y]["pack_item_id"], "item");
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row_os" align="right" valign="top">
         <input type="text" class="text" style="width:100%;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
         name="item_amount_<?=$y?>" id="item_amount_<?=$y?>"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["pack_item_amount"])?>">
      </td>
   </tr>
   <?php
}
//----------------------------------------------------------------------------------
?>
</table>
<?=Nifty_printF(false)?>
<br>
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
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
$_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');showFormInt();";
?>