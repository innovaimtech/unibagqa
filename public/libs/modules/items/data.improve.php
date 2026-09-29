<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["item_ext_prod_act"]         = (int)$_REQUEST["item_ext_prod_act"];
   $_REQUEST["item_int_prod_act"]         = (int)$_REQUEST["item_int_prod_act"];
   $_REQUEST["item_ext_prod_item_id"]     = (int)$_REQUEST["item_ext_prod_item_id"];
   $_REQUEST["item_ext_prod_supp_id"]     = (int)$_REQUEST["item_ext_prod_supp_id"];
   $_REQUEST["item_ext_prod_item_id_0"]   = (int)$_REQUEST["item_ext_prod_item_id_0"];
   $_REQUEST["item_ext_prod_supp_id_0"]   = (int)$_REQUEST["item_ext_prod_supp_id_0"];

   if($_REQUEST["item_ext_prod_item_id"] == 0)
      $_REQUEST["item_ext_prod_act"] = 0;

   if($_REQUEST["item_ext_prod_act"] == 0 || $_REQUEST["item_ext_prod_item_id"] == 0)
   {
      $_REQUEST["item_ext_prod_item_id"]     = 0;
      $_REQUEST["item_ext_prod_supp_id"]     = 0;
      $_REQUEST["item_ext_prod_item_amount"] = 0;
      $_REQUEST["item_ext_prod_item_id_0"]   = 0;
      $_REQUEST["item_ext_prod_supp_id_0"]   = 0;
   }

   $sql = " update item
            set
            item_ext_prod_act          = {$_REQUEST["item_ext_prod_act"]},
            item_ext_prod_item_id      = {$_REQUEST["item_ext_prod_item_id"]},
            item_ext_prod_supp_id      = {$_REQUEST["item_ext_prod_supp_id"]},
            item_ext_prod_serv_item_id = {$_REQUEST["item_ext_prod_item_id_0"]},
            item_ext_prod_serv_supp_id = {$_REQUEST["item_ext_prod_supp_id_0"]},
            item_int_prod_act          = {$_REQUEST["item_int_prod_act"]}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from prod_item_int_pos
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   //echo "<pre>";
   //print_r($_REQUEST);
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $_REQUEST["item_amount_{$idx}"]  = getPrice($_REQUEST["item_amount_{$idx}"],10);
         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues    = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$itemvalues[0];
            $sql_type      = $itemvalues[1];

            $sql = " insert into prod_item_int_pos
                     (item_id, prod_item_id, prod_item_pos, prod_item_amount)
                     VALUES
                     ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]})";
            $CON->no_result($sql);
            $poscounter++;
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from prod_item_int_costs
            where
            ipa_item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "ipa_desc_") !== false && strpos($reqkey, "ipa_desc_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $_REQUEST["ipa_desc_{$idx}"]  = trim(addslashes($_REQUEST["ipa_desc_{$idx}"]));
         $_REQUEST["ipa_price_{$idx}"] = getPrice($_REQUEST["ipa_price_{$idx}"],2);

         if($_REQUEST["ipa_desc_{$idx}"] != "")
         {
            $sql = " insert into prod_item_int_costs
                     (ipa_item_id, ipa_item_pos, ipa_desc, ipa_price)
                     VALUES
                     ({$_REQUEST["id"]}, {$poscounter}, '{$_REQUEST["ipa_desc_{$idx}"]}', {$_REQUEST["ipa_price_{$idx}"]})";
            $CON->no_result($sql);
            $poscounter++;
         }
      }
   }

   $savemsg = getSaveMessage($res);
}

$sql = " select t1.*, t5.unit_name, t5.unit_desc, t4.item_title 'item_ext_prod_title',
         t4.item_number_prod 'item_ext_prod_item_number_prod', t6.supp_company,
         t8.unit_name 'unit_name_0', t8.unit_desc 'unit_desc_0', t7.item_title 'item_ext_prod_title_0',
         t7.item_number_prod 'item_ext_prod_item_number_prod_0', t9.supp_company 'supp_company_0'
         from item t1
         LEFT OUTER JOIN item       t4 ON t1.item_ext_prod_item_id = t4.id
         LEFT OUTER JOIN item_units t5 ON t4.item_unit = t5.id
         LEFT OUTER JOIN supplier   t6 ON t1.item_ext_prod_supp_id = t6.id
         LEFT OUTER JOIN item       t7 ON t1.item_ext_prod_serv_item_id = t7.id
         LEFT OUTER JOIN item_units t8 ON t7.item_unit = t8.id
         LEFT OUTER JOIN supplier   t9 ON t1.item_ext_prod_serv_supp_id = t9.id
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
         document.getElementById('row3').style.display = "";
      }
      else
      {
         document.getElementById('row1').style.display = "none";
         document.getElementById('row2').style.display = "none";
         document.getElementById('row3').style.display = "none";
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

   function showSupplier(itemid, idx)
   {
      if(idx != '')
         idx = "_" +idx;
         
      var dataString = "itemid="+itemid;
      $.ajax({
         type:       "POST",
         cache:      false,
         url:        "/libs/modules/items/get.suppliers.php",
         data:       dataString,
         dataType:   "html",
         success: function(res)
         {
            $("#item_ext_prod_supp_id" +idx).html(res);
         }
      });
   }
</script>
<form action="index.php" method="post" class="fokusfirst" name="js_item_form">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col width="550">
   <col>
   <col width="160">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Unidades de Compra</td>
</tr>
<tr>
   <td class="content_row_clear" colspan="5" align="left">
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
            <input type="checkbox" class="text" name="item_int_prod_act" id="item_int_prod_act" value="1" onchange="showFormInt()"
            <?if($item["item_int_prod_act"] == 1) echo "checked"?>>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr class="prodint">
   <td class="content_tbl_subheader" valign="top">Busqueda</font></td>
   <td class="content_tbl_subheader" valign="top">Act.</font></td>
   <td class="content_tbl_subheader" valign="top">Artículo</font></td>
   <td class="content_tbl_subheader" valign="top" align="center">Unidad</font></td>
   <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$posdata = getItemProdInternItems($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
for($y = 0; $y < 1; $y++)
{
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "xf_search_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_id_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_amount_{$y}";
   ?>
   <tr class="prodint" bgcolor="<?=getRowColor($y)?>">
      <td class="content_row" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$y?>">
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" name="xf_search_<?=$y?>" id="xf_search_<?=$y?>"
               onfocus="markfield(this,0)" autocomplete="off"
               <?php
               if(!(int)$posdata[$y]["prod_item_id"])
               {
                  $urlparam = "&itemsonly=1";
                  ?>
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
      <td class="content_row" valign="top">
         <?php
         if((int)$posdata[$y]["prod_item_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.js_item_form.item_amount_<?=$y?>.value='0'; submitForm(document.js_item_form); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:550px;"
         name="item_id_<?=$y?>" id="item_id_<?=$y?>"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$y]["prod_item_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$y]["prod_item_id"])
            {
               $desc = trim(addslashes($posdata[$y]["item_title"]));
               ?>
               <option value="<?=$posdata[$y]["prod_item_id"]?>#item"><?=$posdata[$y]["item_number_prod"]?> - <?=$desc?></option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$y]["prod_item_id"])
            echo getItemUnitDesc($CON, $posdata[$y]["prod_item_id"], "item");
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:150px;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
         name="item_amount_<?=$y?>" id="item_amount_<?=$y?>"
         value="<?php if((int)$posdata[$y]["prod_item_id"]) echo printPrice($posdata[$y]["prod_item_amount"],10)?>">
      </td>
   </tr>
   <?php
}
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