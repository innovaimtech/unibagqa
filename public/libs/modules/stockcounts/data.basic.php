<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
/*
select count(t1.item_id) 'CC'  from stockcounts_preitem t1  LEFT OUTER JOIN item t2 ON t1 . item_id =t2 . id  where  t1 . stc_id =270   ORDER BY `t1` . `item_id` ASC

*/
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["cid"]    = (int)$_REQUEST["cid"];
   $_REQUEST["sid"]    = (int)$_REQUEST["sid"];
   $_REQUEST["stc_onlystock"] = (int)$_REQUEST["stc_onlystock"];

   if($_REQUEST["reactivateNumber"] == "")
      $stc_num = createTransactionNumber($CON, $_REQUEST["cid"], "stockcount");
   else
   {
      $stc_num = $_REQUEST["reactivateNumber"];
      $sql = " update stockcounts
               set
               stc_num = 'DELETED'
               where
               stc_num = '{$stc_num}' and
               stc_status = 0";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " insert into stockcounts
            (stc_num, stc_companyid, stc_shopid, stc_annotation, stc_bookdate,
             stc_crtdat, stc_crtusr, stc_onlystock)
            VALUES
            ('{$stc_num}', {$_REQUEST["cid"]}, {$_REQUEST["sid"]}, 'Recuento inventario ".date('d.m.Y')."',
             {$currtme}, {$currtme}, {$_SESSION["user_id"]}, {$_REQUEST["stc_onlystock"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from stockcounts
               where
               stc_crtusr = {$_SESSION["user_id"]}";
      $sorder = $CON->select($sql);
      $_REQUEST["id"]   = $sorder[0]["thisid"];

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=685&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
   
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["stc_annotation"]   = trim(addslashes($_REQUEST["stc_annotation"]));
   $_REQUEST["stc_cat_mode"]     = (int)$_REQUEST["stc_cat_mode"];
   $_REQUEST["stc_sth_mode"]     = (int)$_REQUEST["stc_sth_mode"];
   $_REQUEST["stc_tran_mode"]    = (int)$_REQUEST["stc_tran_mode"];
   $_REQUEST["stc_list_mode"]    = (int)$_REQUEST["stc_list_mode"];
   $_REQUEST["stc_order_mode"]   = (int)$_REQUEST["stc_order_mode"];
   $_REQUEST["stc_repo_mode"]    = (int)$_REQUEST["stc_repo_mode"];
   $_REQUEST["stc_selitem"]      = (int)$_REQUEST["stc_selitem"];
   $_REQUEST["stc_bookdate"]     = trim($_REQUEST["stc_bookdate"]);
   $_REQUEST["stc_bookdate"]     = explode(".", $_REQUEST["stc_bookdate"]);
   $_REQUEST["stc_bookdate"]     = (int)mktime(0, 0, 0, $_REQUEST["stc_bookdate"][1], $_REQUEST["stc_bookdate"][0], $_REQUEST["stc_bookdate"][2]);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " UPDATE stockcounts
            set
            stc_itemid           = {$_REQUEST["stc_selitem"]},
            stc_status           = {$_REQUEST["stc_status"]},
            stc_annotation       = '{$_REQUEST["stc_annotation"]}',
            stc_cat_mode         = {$_REQUEST["stc_cat_mode"]},
            stc_sth_mode         = {$_REQUEST["stc_sth_mode"]},
            stc_tran_mode        = {$_REQUEST["stc_tran_mode"]},
            stc_list_mode        = {$_REQUEST["stc_list_mode"]},
            stc_order_mode       = {$_REQUEST["stc_order_mode"]},
            stc_repo_mode        = {$_REQUEST["stc_repo_mode"]},
            stc_bookdate         = {$_REQUEST["stc_bookdate"]},
            stc_updusr           = {$_SESSION["user_id"]},
            stc_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   $sql = " delete from stockcounts_productcats
            where
            stc_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $selcatarr = explode("#", $_REQUEST["stc_selfamilies"]);
   foreach($selcatarr AS $selcatid)
   {
      $sql = " insert into stockcounts_productcats
               (stc_id, cat_id) VALUES ({$_REQUEST["id"]}, {$selcatid})";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from stockcounts_storehouses
            where
            stc_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $selstharr = explode("#", $_REQUEST["stc_selstorehouses"]);
   foreach($selstharr AS $selsthid)
   {
      $sql = " insert into stockcounts_storehouses
               (stc_id, sth_id) VALUES ({$_REQUEST["id"]}, {$selsthid})";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from stockcounts_preitem
            where
            stc_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $_REQUEST["item_id_{$idx}"] = (int)$_REQUEST["item_id_{$idx}"];

         if($_REQUEST["item_id_{$idx}"] && !(int)$_REGISTEREDITEMS[$_REQUEST["item_id_{$idx}"]])
         {
            $sql = " insert into stockcounts_preitem
                     (stc_id, item_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$_REQUEST["item_id_{$idx}"]})";
            $CON->no_result($sql);
         }
         $_REGISTEREDITEMS[$_REQUEST["item_id_{$idx}"]] = 1;
      }
   }

   if((int)$_REQUEST["stc_addubicid"] && (int)$_REQUEST["stc_tran_mode"])
   {
      $sql = " update stockcounts
               set
               stc_ubicid = {$_REQUEST["stc_addubicid"]}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      $sql = " select *
               from item t1
               INNER JOIN item_shops t2 ON t1.id = t2.item_id
               where
               t1.item_status    = 1 and
               t2.shop_id        = {$headdata["stc_shopid"]} and
               t1.item_ubicacion = {$_REQUEST["stc_addubicid"]} and
               (
                  select SUM(tx.iss_inventory) 'iss_inventory'
                  from item_shops_storehouses tx
                  INNER JOIN company_shops_storehouses ty ON tx.st_id = ty.id
                  where
                  tx.item_id     = t1.id and
                  tx.shop_id     = t2.shop_id and
                  ty.st_status   = 1
               ) != 0
               order by t1.item_title";
      $ubicitems = $CON->select($sql);
      for($x = 0; $x < count($ubicitems) && $ubicitems != false; $x++)
      {
         $sql = " select count(*) 'cc'
                  from stockcounts_preitem
                  where
                  stc_id   = {$_REQUEST["id"]} and
                  item_id  = {$ubicitems[$x]["id"]}";
         $exists = $CON->select($sql);
         $exists = (int)$exists[0]["cc"];

         if(!$exists)
         {
            $sql = " insert into stockcounts_preitem
                     (stc_id, item_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$ubicitems[$x]["id"]})";
            $CON->no_result($sql);
         }
      }          
   }
   
   stockcountSetItemContent($CON, $_REQUEST["id"]);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*,t3.company_short, t4.shop_name, t7.item_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from stockcounts t1
         LEFT OUTER JOIN company_data t3  ON t1.stc_companyid    = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.stc_shopid       = t4.id
         LEFT OUTER JOIN user t5          ON t1.stc_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.stc_crtusr       = t6.id
         LEFT OUTER JOIN item t7          ON t1.stc_itemid       = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$selcatidstr = "";
$sql = " select cat_id
         from stockcounts_productcats
         where
         stc_id = {$_REQUEST["id"]}";
$selcatids = $CON->select($sql);
foreach($selcatids AS $selcatid)
{
   $selcatidstr .= $selcatid["cat_id"]."#";
   $selcatidshw .= sprintf("%03s", $selcatid["cat_id"])." - ";
}
$selcatidstr = substr($selcatidstr, 0, -1);
$selcatidshw = substr($selcatidshw, 0, -2);

//----------------------------------------------------------------------------------
$selsthidstr = "";
$sql = " select t1.sth_id, t2.st_name
         from stockcounts_storehouses t1
         INNER JOIN company_shops_storehouses t2 ON t1.sth_id = t2.id
         where
         t1.stc_id = {$_REQUEST["id"]}";
$selsthids = $CON->select($sql);
foreach($selsthids AS $selsthid)
{
   $selsthidstr .= $selsthid["sth_id"]."#";
   $selsthidshw .= sprintf("%03s", $selsthid["st_name"])." - ";
}
$selsthidstr = substr($selsthidstr, 0, -1);
$selsthidshw = substr($selsthidshw, 0, -2);

//----------------------------------------------------------------------------------
// $posdata    = getSupplierOrderPos($CON, $_REQUEST["id"]);

$sql = " select t1.*, t2.item_number_prod, t2.item_title
         from stockcounts_preitem t1
         LEFT OUTER JOIN item t2 ON t1.item_id = t2.id
         where
         t1.stc_id = {$_REQUEST["id"]}
         order by t1.id";
$posdata = $CON->select($sql);

$rowcount = count($posdata)+10;

if($headdata["stc_status"] >= 2)
{
   $rdlo     = " readonly ";
   $dabl     = " disabled ";
   $rowcount = count($posdata);
}
if((int)$headdata["stc_ubicid"])
{
   $rowcount = count($posdata);

   $sql = " select t1.id, t1.ubi_name, t1.ubi_crtdat
            from ubicacion t1
            where
            id = {$headdata["stc_ubicid"]}";
   $ubicdesc = $CON->select($sql);
   $ubicdesc = "Ubicación: ".$ubicdesc[0]["ubi_name"];
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
   echo "onsubmit='return checkform(new Array(this.stc_bookdate))'";
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="stc_status" value="1">
<input type="hidden" name="stc_selfamilies" id="stc_selfamilies" value="<?=$selcatidstr?>">
<input type="hidden" name="stc_selstorehouses" id="stc_selstorehouses" value="<?=$selsthidstr?>">
<input type="hidden" name="stc_selitem" id="stc_selitem" value="<?=$headdata["stc_itemid"]?>">
<input type="hidden" name="stc_selitem" id="stc_selitem" value="<?=$headdata["stc_itemid"]?>">
<input type="hidden" name="stc_addubicid" id="stc_addubicid" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">
      Datos básicos
      <?php
      if((int)$headdata["stc_onlystock"])
      {  ?>
         <span style="float:right;padding:3px;padding-left:6px;padding-right:6px;background-color:#1AAAA6;color:#FFFFFF">Solo productos con stock</span>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["stc_num"]?></td>
   <td class="content_rowl">Fecha *</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="stc_bookdate" name="stc_bookdate" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["stc_bookdate"]) echo date('d.m.Y', $headdata["stc_bookdate"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Familias</td>
   <td class="content_row" valign="top">
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="radio" name="stc_cat_mode" value="1" <?=$dabl?>
            onclick="document.getElementById('idx_stc_families').style.display='none';
                     document.getElementById('idx_stc_families_shw').style.display='none'"
            <?if($headdata["stc_cat_mode"] == 1) echo "checked"?>> Todas familias

            <input type="radio" name="stc_cat_mode" value="2" <?=$dabl?>
            onclick="document.getElementById('idx_stc_families').style.display='';
                     document.getElementById('idx_stc_families_shw').style.display=''"
            <?if($headdata["stc_cat_mode"] == 2) echo "checked"?>> Familias especificas
         </td>
         <td class="content_row_clear" align="right">
            <div id="idx_stc_families" style="padding-top:3px;<?if($headdata["stc_cat_mode"] == 1) echo "display:none"?>">
               <?php
               if($headdata["stc_status"] == 1)
                  printButton("Seleccione", "postnav", "javascript: void(0)",
                  "showFancybox('/libs/modules/stockcounts/select.families.php', 'iframe', 600, 400, 'auto')", "disk-black", 80);
               ?>
            </div>
         </td>
      </tr>
      </table>
      <div id="idx_stc_families_shw" style="padding-top:3px;padding-left:5px;<?if($headdata["stc_cat_mode"] == 1) echo "display:none"?>">
      <?php
      if($selcatidshw != "")
      {  ?>
         <b class="msg_save_ok">Familias seleccionadas:</b>
         <br>
         <?=$selcatidshw?>
         <?php
      }
      ?>
      </div>
   </td>
   <td class="content_rowl" valign="top">Bodegas</td>
   <td class="content_row" valign="top">
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="radio" name="stc_sth_mode" value="1" <?=$dabl?>
            onclick="document.getElementById('idx_stc_storehouses').style.display='none';
                     document.getElementById('idx_stc_storehouses_shw').style.display='none'"
            <?if($headdata["stc_sth_mode"] == 1) echo "checked"?>> Todas bodegas
            
            <input type="radio" name="stc_sth_mode" value="2" <?=$dabl?>
            onclick="document.getElementById('idx_stc_storehouses').style.display='';
                     document.getElementById('idx_stc_storehouses_shw').style.display=''"
            <?if($headdata["stc_sth_mode"] == 2) echo "checked"?>> Bodegas especificas
         </td>
         <td class="content_row_clear">
            <div id="idx_stc_storehouses" style="padding-top:3px;<?if($headdata["stc_sth_mode"] == 1) echo "display:none"?>">
               <?php
               if($headdata["stc_status"] == 1)
                  printButton("Seleccione", "postnav", "javascript: void(0)",
                  "showFancybox('/libs/modules/stockcounts/select.storehouses.php?shopid={$headdata["stc_shopid"]}', 'iframe', 600, 400, 'auto')", "disk-black", 80);
               ?>
            </div>
         </td>
      </tr>
      </table>
      <div id="idx_stc_storehouses_shw" style="padding-top:3px;padding-left:5px;<?if($headdata["stc_sth_mode"] == 1) echo "display:none"?>">
      <?php
      if($selsthidshw != "")
      {  ?>
         <b class="msg_save_ok">Bodegas seleccionadas:</b>
         <br>
         <?=$selsthidshw?>
         <?php
      }
      ?>
      </div>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Articulos</td>
   <td class="content_row" valign="top">
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="radio" name="stc_tran_mode" value="1"
            onclick="document.getElementById('idx_stc_items').style.display='none';
                     document.getElementById('idx_stc_items_shw').style.display='none';
                     document.getElementById('idx_stc_items_div').style.display='none'"
            <?if($headdata["stc_tran_mode"] == 1) echo "checked"?> <?=$dabl?>> Todos artículos<br>
            <!--
            <input type="radio" name="stc_tran_mode" value="2"
            onclick="document.getElementById('idx_stc_items').style.display='';
                     document.getElementById('idx_stc_items_shw').style.display=''"
            <?if($headdata["stc_tran_mode"] == 2) echo "checked"?> <?=$dabl?>> Un solo artículo<br>
            -->
            <input type="radio" name="stc_tran_mode" value="3"
            onclick="document.getElementById('idx_stc_items').style.display='none';
                     document.getElementById('idx_stc_items_shw').style.display='none';
                     document.getElementById('idx_stc_items_div').style.display=''"
            <?if($headdata["stc_tran_mode"] == 3) echo "checked"?> <?=$dabl?>> Artículos especificos
         </td>
         <td class="content_row_clear" align="right" valign="top">
            <div id="idx_stc_items" style="padding-top:3px;<?if($headdata["stc_tran_mode"] != 2) echo "display:none"?>">
               <?php
               if($headdata["stc_status"] == 1)
                  printButton("Seleccione", "postnav", "javascript: void(0)",
                  "showFancybox('/libs/modules/stockcounts/select.items.php?shopid={$headdata["stc_shopid"]}', 'iframe', 800, 450, 'auto')", "disk-black", 80);
               ?>
            </div>
         </td>
      </tr>
      </table>
      <div id="idx_stc_items_shw" style="padding-top:3px;padding-left:5px;<?if($headdata["stc_tran_mode"] != 2) echo "display:none"?>">
      <?php
      if($headdata["stc_itemid"] != 0)
      {  ?>
         <b class="msg_save_ok">Articulo seleccionado:</b>
         <br>
         <?=$headdata["item_title"]?>
         <?php
      }
      ?>
      </div>
   </td>
   <td class="content_rowl" valign="top">Separación</td>
   <td class="content_row" valign="top">
      <input type="radio" name="stc_list_mode" value="1" <?if($headdata["stc_list_mode"] == 1) echo "checked"?> <?=$dabl?>> Por bodega y familia<br>
      <input type="radio" name="stc_list_mode" value="2" <?if($headdata["stc_list_mode"] == 2) echo "checked"?> <?=$dabl?>> Por bodega
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Ordenar</td>
   <td class="content_row">
      <input type="radio" name="stc_order_mode" value="1" <?if($headdata["stc_order_mode"] == 1) echo "checked"?> <?=$dabl?>> Por número de artículo<br>
      <input type="radio" name="stc_order_mode" value="2" <?if($headdata["stc_order_mode"] == 2) echo "checked"?> <?=$dabl?>> Por nombre de artículo
   </td>
   <td class="content_rowl" valign="top">Estado</td>
   <td class="content_row" valign="top">
      <?php
      $statimg = "";
      switch((int)$headdata["stc_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "orange_active.gif"; break;
         case 3: $statimg = "green_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
      <?=getStockcountStatus($headdata["stc_status"], true)?>
      <br>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Modo Reposición</td>
   <td class="content_row" colspan="3">
      <input type="checkbox" name="stc_repo_mode" value="1" <?if($headdata["stc_repo_mode"] == 1) echo "checked"?> <?=$dabl?>> Activado
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row" colspan="3">
      <textarea class="text" style="width:830px; height:45px" name="stc_annotation" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["stc_annotation"])?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($headdata["stc_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["stc_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130" style="padding-right:5px">
      <?php
      printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
      ?>
   </td>
   <?php
   if($headdata["stc_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton("Aprobar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.stc_status.value = '2';document.form_shppos.submit(); }", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   if($headdata["stc_status"] >= 2 && $headdata["stc_hash"] != "")
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&id={$_REQUEST["id"]}&hash={$headdata["sord_hash"]}.pdf&name={$headdata["sord_number"]}.pdf&path=../../../docs.supplierorder/'", "script");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<div id="idx_stc_items_div" style="display:<?if($headdata["stc_tran_mode"] != 3) echo "none"?>">
<br>
<?php
$sql = " select t1.id, t1.ubi_name, t1.ubi_crtdat
         from ubicacion t1
         where
         t1.ubi_status > 0
         order by 2";
$ubics = $CON->select($sql);


?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Artículos </td>
   <td class="content_tbl_header" align="right">
      <?=$ubicdesc?>
      <?php
      if($headdata["stc_status"] == 1 && !(int)$headdata["stc_ubicid"])
      {  ?>
         Agregar via Ubicación:&nbsp;
         <select class="text" name="addviaubicid" id="addviaubicid">
            <option value="">SELECCIONE</option>
            <?php
            foreach($ubics AS $ubic)
            {  ?>
               <option value="<?=$ubic["id"]?>"><?=$ubic["ubi_name"]?></option>
               <?php
            }
            ?>
         </select>
         <input type="button" class="button" value="Agregar Articulos"
         onclick="if($('#addviaubicid').val() != '') { $('#stc_addubicid').val($('#addviaubicid').val()); submitForm(document.form_shppos) } ">
         <?php
      }
      else
         echo "&nbsp;";
      ?>
   </td>
</tr>
<tr>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" id="xf_search_<?=$x?>" name="xf_search_<?=$x?>"
               onfocus="markfield(this,0)" <?=$rdlo?>
               <?php
               if(!(int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/stockcounts/searchitem.php?rowcount=<?=$x?>&id=<?=$_REQUEST["id"]?>&search=' +this.value;} this.value='';"
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
      <td class="content_row">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)" <?=$dabl?>
            onclick="if(askDel('')) {
                     document.form_shppos.item_id_<?=$x?>.options.length=0;
                     submitForm(document.form_shppos); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:867px" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onfocus="addSelStyle(this);"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc = trim(addslashes($posdata[$x]["item_title"]));
               ?>
               <option value="<?=$posdata[$x]["item_id"]?>"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></option>
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
<?=Nifty_printF()?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</div>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
