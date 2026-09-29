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
      $_REQUEST["item_ext_prod_item_id_0"]   = 0;
      $_REQUEST["item_ext_prod_supp_id_0"]   = 0;
   }

   $sql = " update itemlist
            set
            item_ext_prod_act         = {$_REQUEST["item_ext_prod_act"]},
            item_ext_prod_item_id     = {$_REQUEST["item_ext_prod_item_id"]},
            item_ext_prod_supp_id     = {$_REQUEST["item_ext_prod_supp_id"]},
            item_ext_prod_serv_item_id = {$_REQUEST["item_ext_prod_item_id_0"]},
            item_ext_prod_serv_supp_id = {$_REQUEST["item_ext_prod_supp_id_0"]}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

$sql = " select t1.*, t4.item_title 'item_ext_prod_title',
         t4.item_number_prod 'item_ext_prod_item_number_prod', t6.supp_company,
         t7.item_title 'item_ext_prod_title_0',
         t7.item_number_prod 'item_ext_prod_item_number_prod_0', t9.supp_company 'supp_company_0'
         from itemlist t1
         LEFT OUTER JOIN itemlist t4 ON t1.item_ext_prod_item_id = t4.id
         LEFT OUTER JOIN supplier t6 ON t1.item_ext_prod_supp_id = t6.id
         LEFT OUTER JOIN item       t7 ON t1.item_ext_prod_serv_item_id = t7.id
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

   function showSupplier(itemid, idx)
   {
      if(idx != '')
         idx = "_" +idx;
         
      var dataString = "itemid="+itemid;
      $.ajax({
         type:       "POST",
         cache:      false,
         url:        "/libs/modules/itemlist/get.suppliers.php",
         data:       dataString,
         dataType:   "html",
         success: function(res)
         {
            $("#item_ext_prod_supp_id" +idx).html(res);
         }
      });
   }
   function showSupplier2(itemid, idx)
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
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col>
   <col width="390">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Mejora</td>
</tr>
<tr>
   <td class="content_row_clear" colspan="6" align="left">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="85">
         <col>
      </colgroup>
      <tr>
         <td class="content_row_clear">
            Con mejora
         </td>
         <td class="content_row_clear">
            <input type="checkbox" class="text" name="item_ext_prod_act" id="item_ext_prod_act" value="1" onchange="showForm()"
            <?if($item["item_ext_prod_act"] == 1) echo "checked"?>>
            ( 1. Linea: Producto final, 2. Linea: Servicio externo )
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr id="row1" style="display:<?if($item["item_ext_prod_act"] == 0) echo "none"?>">
   <td class="content_row" valign="top"><font color="#804818">Busqueda</font></td>
   <td class="content_row" valign="top"><font color="#804818">Act.</font></td>
   <td class="content_row" valign="top"><font color="#804818">Artículo</font></td>
   <td class="content_row" valign="top"><font color="#804818">Proveedor</font></td>
</tr>
<tr id="row2" style="display:<?if($item["item_ext_prod_act"] == 0) echo "none"?>">
   <td class="content_row_clear" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
         <td>
            <input type="text" class="text" style="width:60px"
            name="xf_search" id="xf_search"
            onfocus="markfield(this,0)" autocomplete="off"
            onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/itemlist/searchitemlist.php?id=<?=$_REQUEST["id"]?>&search=' +this.value} this.value='';">
         </td>
      </tr>
      </table>
   </td>
   <td class="content_row_clear" valign="top">
      <?php
      if((int)$item["item_ext_prod_item_id"])
      {  ?>
         <input type="button" class="buttonred" value="x" style="width:20px"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="if(askDel('')) { document.js_item_form.item_ext_prod_item_id.options.length=0; submitForm(document.js_item_form); }">
         <?php
      }
      else
      {
         if(!$_FIELDREGS[0]) $_FIELDREGS[0] = Array(); $_FIELDREGS[0][] = "xf_search";
         if(!$_FIELDREGS[0]) $_FIELDREGS[0] = Array(); $_FIELDREGS[0][] = "item_ext_prod_item_id";
         if(!$_FIELDREGS[0]) $_FIELDREGS[0] = Array(); $_FIELDREGS[0][] = "item_ext_prod_supp_id";
         echo "&nbsp;";
      }
      ?>
   </td>
   <td class="content_row_clear" valign="top">
      <select class="text" style="width:450px"
      name="item_ext_prod_item_id" id="item_ext_prod_item_id"
      onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this);<?php if(!(int)$item["item_ext_prod_item_id"]) echo "showSupplier(this.value, '');"?>"
      onfocus="<?php if(!(int)$item["item_ext_prod_item_id"]) echo "showSupplier(this.value, '');addSelStyle(this);" ?>"
      onmousedown="markfield(this,0)"
      onchange="showSupplier(this.value, '')">
         <?php
         if((int)$item["item_ext_prod_item_id"])
         {
            $desc = trim(addslashes($item["item_ext_prod_title"]));
            ?>
            <option value="<?=$item["item_ext_prod_item_id"]?>"><?=$item["item_ext_prod_item_number_prod"]?> - <?=$desc?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_row_clear" valign="top">
      <select class="text" style="width:380px"
      name="item_ext_prod_supp_id" id="item_ext_prod_supp_id"
      onblur="markfield(this,1);"
      onmousedown="markfield(this,0)">
         <?php
         if((int)$item["item_ext_prod_item_id"])
         {  ?>
            <option value="<?=$item["item_ext_prod_supp_id"]?>"><?=$item["supp_company"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr id="row3" style="display:<?if($item["item_ext_prod_act"] == 0) echo "none"?>">
   <td class="content_row_clear" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
         <td>
            <input type="text" class="text" style="width:60px"
            name="xf_search_0" id="xf_search_0"
            onfocus="markfield(this,0)" autocomplete="off"
            onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/items/searchitem.php?id=<?=$_REQUEST["id"]?>&rowcount=0&search=' +this.value} this.value='';">
            <?php
            if(!$_FIELDREGS[1]) $_FIELDREGS[1] = Array(); $_FIELDREGS[1][] = "xf_search_0";
            if(!$_FIELDREGS[1]) $_FIELDREGS[1] = Array(); $_FIELDREGS[1][] = "item_ext_prod_item_id_0";
            if(!$_FIELDREGS[1]) $_FIELDREGS[1] = Array(); $_FIELDREGS[1][] = "item_ext_prod_supp_id_0";
            ?>
         </td>
      </tr>
      </table>
   </td>
   <td class="content_row_clear" valign="top">
      <?php
      if((int)$item["item_ext_prod_serv_item_id"])
      {  ?>
         <input type="button" class="buttonred" value="x" style="width:20px"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="if(askDel('')) { document.js_item_form.item_ext_prod_item_id_0.options.length=0; submitForm(document.js_item_form); }">
         <?php
      }
      else
         echo "&nbsp;";
      ?>
   </td>
   <td class="content_row_clear" valign="top">
      <select class="text" style="width:450px"
      name="item_ext_prod_item_id_0" id="item_ext_prod_item_id_0"
      onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this);showSupplier2(this.value, '0')"
      onfocus="<?php if(!(int)$item["item_ext_prod_serv_item_id"]) echo "addSelStyle(this);" ?>"
      onmousedown="markfield(this,0)"
      onchange="showSupplier2(this.value, '0')">
         <?php
         if((int)$item["item_ext_prod_serv_item_id"])
         {
            $desc = trim(addslashes($item["item_ext_prod_title_0"]));
            ?>
            <option value="<?=$item["item_ext_prod_serv_item_id"]?>"><?=$item["item_ext_prod_item_number_prod_0"]?> - <?=$desc?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_row_clear" valign="top">
      <select class="text" style="width:380px"
      name="item_ext_prod_supp_id_0" id="item_ext_prod_supp_id_0"
      onblur="markfield(this,1);"
      onmousedown="markfield(this,0)">
         <?php
         if((int)$item["item_ext_prod_serv_item_id"])
         {  ?>
            <option value="<?=$item["item_ext_prod_serv_supp_id"]?>"><?=$item["supp_company_0"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
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
