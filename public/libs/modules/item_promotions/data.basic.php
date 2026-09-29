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

   $_REQUEST["prom_name"]        = trim(addslashes($_REQUEST["prom_name"]));
   $_REQUEST["prom_desc"]        = trim(addslashes($_REQUEST["prom_desc"]));
   $_REQUEST["prom_dsc_type"]    = trim(addslashes($_REQUEST["prom_dsc_type"]));
   $_REQUEST["prom_released"]    = (int)$_REQUEST["prom_released"];
   $_REQUEST["prom_item_amount"] = getPrice($_REQUEST["prom_item_amount"],2);
   $_REQUEST["prom_dsc"]         = getPrice($_REQUEST["prom_dsc"]);

   $_REQUEST["prom_datefrom"] = explode(".", $_REQUEST["prom_datefrom"]);
   $_REQUEST["prom_datefrom"] = (int)mktime(0, 0, 0, $_REQUEST["prom_datefrom"][1], $_REQUEST["prom_datefrom"][0], $_REQUEST["prom_datefrom"][2]);
   $_REQUEST["prom_dateto"]   = explode(".", $_REQUEST["prom_dateto"]);
   $_REQUEST["prom_dateto"]   = (int)mktime(23, 59, 59, $_REQUEST["prom_dateto"][1], $_REQUEST["prom_dateto"][0], $_REQUEST["prom_dateto"][2]);

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into item_promotions
               (prom_name, prom_desc, prom_datefrom, prom_dateto, prom_released, prom_dsc,
                prom_item_amount, prom_crtdat, prom_crtusr, prom_dsc_type)
               VALUES
               ('{$_REQUEST["prom_name"]}', '{$_REQUEST["prom_desc"]}', {$_REQUEST["prom_datefrom"]}, {$_REQUEST["prom_dateto"]},
                 {$_REQUEST["prom_released"]}, {$_REQUEST["prom_dsc"]},  {$_REQUEST["prom_item_amount"]}, {$currtme}, {$_SESSION["user_id"]},
                 '{$_REQUEST["prom_dsc_type"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from item_promotions
                  where
                  prom_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;

         $redirect = true;
      }
   }
   else
   {
      $sql = " update item_promotions
               set
               prom_name         = '{$_REQUEST["prom_name"]}',
               prom_desc         = '{$_REQUEST["prom_desc"]}',
               prom_datefrom     = {$_REQUEST["prom_datefrom"]},
               prom_dateto       = {$_REQUEST["prom_dateto"]},
               prom_released     = {$_REQUEST["prom_released"]},
               prom_item_amount  = {$_REQUEST["prom_item_amount"]},
               prom_dsc          = {$_REQUEST["prom_dsc"]},
               prom_dsc_type     = '{$_REQUEST["prom_dsc_type"]}',
               prom_upddat       = {$currtme},
               prom_updusr       = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from item_promotions_shops
            where
            prom_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach($_REQUEST["selshops"] AS $selshopid)
   {
      $sql = " insert into item_promotions_shops
               (prom_id, shop_id)
               VALUES
               ({$_REQUEST["id"]}, {$selshopid})";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from item_promotions_items
            where
            prom_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $itemvalues    = explode("#", $_REQUEST[$reqkey]);
         $sql_id        = (int)$itemvalues[0];
         $sql_type      = $itemvalues[1];
         if((int)$sql_id)
         {
            $sql = " insert into item_promotions_items
                     (prom_id, item_id, item_type, pos_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$sql_id}, '{$sql_type}', {$poscounter})";
            $CON->no_result($sql);

            $poscounter++;
         }
      }
   }
   
   //----------------------------------------------------------------------------------
   if($redirect)
   {  ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from item_promotions t1
            LEFT OUTER JOIN user t2 ON t1.prom_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.prom_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $prom = $CON->select($sql);
   $prom = $prom[0];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from item_promotions_shops
            where
            prom_id = {$_REQUEST["id"]}";
   $tmpshops = $CON->select($sql);
   foreach($tmpshops AS $tmpshop)
   {
      $selshops[$tmpshop["shop_id"]] = 1;
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.item_number_prod, t2.item_title
            from item_promotions_items t1
            INNER JOIN item t2 ON (t1.item_id = t2.id and t1.item_type = 'item')
            where
            t1.prom_id = {$_REQUEST["id"]}
            UNION ALL
            select t1.*, t2.item_number_prod, t2.item_title
            from item_promotions_items t1
            INNER JOIN itemlist t2 ON (t1.item_id = t2.id and t1.item_type = 'itemlist')
            where
            t1.prom_id = {$_REQUEST["id"]}
            order by 4";
   $posdata = $CON->select($sql);
}

//----------------------------------------------------------------------------------
$rowcount = 0;
if(count($posdata))
   $rowcount = count($posdata);

$fullrowcount = 11;
$addrowcount  = 3;

if($rowcount <= 8)
   $rowcount = $fullrowcount;
else
   $rowcount += $addrowcount;
   
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" class="fokusfirst" name="js_item_form"
 onsubmit="return checkform(new Array(this.prom_name, this.prom_dsc, this.prom_datefrom, this.prom_dateto))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de promoción</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="prom_name" type="text" class="text" style="width:100%" value="<?=$prom["prom_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="prom_desc" class="text" style="width:100%; height:90px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$prom["prom_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Descuento *</td>
   <td class="content_row">
      <input name="prom_dsc" type="text" class="text" style="width:80px;text-align:right"
      value="<?php if($prom["prom_dsc"] > 0.00) echo printPrice($prom["prom_dsc"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      <select class="text" name="prom_dsc_type">
         <option value="perc" <?if($prom["prom_dsc_type"] == "perc") echo "selected"?>>%</option>
         <option value="amt"  <?if($prom["prom_dsc_type"] == "amt") echo "selected"?>>$</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Valido *</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="prom_datefrom" name="prom_datefrom" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($prom["prom_dateto"] > 0) echo date('d.m.Y', $prom["prom_datefrom"])?>">
      -
      <input type="text" style="width:80px" id="prom_dateto" name="prom_dateto" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($prom["prom_dateto"] > 0) echo date('d.m.Y', $prom["prom_dateto"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Activado</td>
   <td class="content_row">
      <select class="text" name="prom_released" style="width:80px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="1" style="color:green" <?php if((int)$prom["prom_released"] || $_REQUEST["id"] == "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][0]?></option>
         <option value="0" style="color:red"   <?php if(!(int)$prom["prom_released"] && $_REQUEST["id"] != "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][1]?></option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$prom["crt_firstname"]?> <?=$prom["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($prom["prom_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$prom["upd_firstname"]?> <?=$prom["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($prom["prom_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col width="90">
   <col width="20">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Productos</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">Artículo #<?=($x +1)?></td>
      <td class="content_row" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="1" style="padding-right:3px"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:150px"
               name="xf_search_<?=$x?>" id="xf_search_<?=$x?>"
               onfocus="markfield(this,0)" autocomplete="off"
               <?=$rdlo?>
               <?php
               if(!(int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/stats/searchitem.php?rowcount=<?=$x?>&search=' +this.value} this.value='';"
                  <?php
               }
               else
               {  ?>
                  onblur="markfield(this,1)"
                  <?php
               }
               ?>>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)" <?=$dabl?>
            onclick="if(askDel('')) { document.js_item_form.item_id_<?=$x?>.options.length=0; submitForm(document.js_item_form); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <select class="text" style="width:690px" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc = trim(addslashes($posdata[$x]["item_title"]));
               $udsc = trim(addslashes(getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"])));
               ?>
               <option value="<?=$posdata[$x]["item_id"]?>#<?=$posdata[$x]["item_type"]?>"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?> (<?=$udsc?>)</option>
               <?php
            }
            else
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);
?>
<?=Nifty_printH("box3", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="9">Valido en sucursales</td>
</tr>
<tr>
   <td class="content_tbl_subheader" colspan="2">Sucursal</td>
</tr>
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

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row">
            <input type="checkbox" name="selshops[]" value="<?=$shops[$x]["id"]?>"
            <?php if((int)$selshops[$shops[$x]["id"]]) echo "checked"?>>
         </td>
         <td class="content_row"><?=$company["company_short"]?>: <?=$shops[$x]["shop_name"]?>&nbsp;</td>
      </tr>
      <?php
   }
}
?>
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
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton("Agregar familia", "postnav", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/item_promotions/addcat.fancy.php?id={$_REQUEST["id"]}', 'iframe', 450, 160, 'no')", "gear");
         ?>
      </td>
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
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');" ?>