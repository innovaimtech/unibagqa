<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if((int)$_REQUEST["revertDel"])
{
   $sql = " update stockpacks
            set
            stk_status = 1
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);   
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   $_REQUEST["cid"]              = (int)$_REQUEST["cid"];
   $_REQUEST["sid"]              = (int)$_REQUEST["sid"];
   $_REQUEST["sthid"]            = (int)$_REQUEST["sthid"];
   $_REQUEST["issue_id"]         = (int)$_REQUEST["issue_id"];

   $_REQUEST["stk_bookdate"]     = date('d.m.Y');
   $_REQUEST["stk_bookdate"]     = explode(".", $_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]     = (int)mktime(15, 0, 0, $_REQUEST["stk_bookdate"][1], $_REQUEST["stk_bookdate"][0], $_REQUEST["stk_bookdate"][2]);

   $sql = " select stkis_negative
            from stockchanges_issues
            where
            id = {$_REQUEST["issue_id"]}";
   $stkis_negative = $CON->select($sql);
   $stkis_negative = (int)$stkis_negative[0]["stkis_negative"];
   
   $sql = " insert into stockpacks
            (stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
             stk_negative, stk_crtdat, stk_crtusr, stk_fixedsthid)
            VALUES
            ({$_REQUEST["issue_id"]}, {$_REQUEST["cid"]}, {$_REQUEST["sid"]}, {$_REQUEST["stk_bookdate"]},
              {$stkis_negative}, {$currtme}, {$_SESSION["user_id"]},  {$_REQUEST["sthid"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from stockpacks
               where
               stk_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      $_REQUEST["id"] = $thisid;
      $stk_num = sprintf("%05s", $_REQUEST["id"]);

      $sql = " update stockpacks
               set
               stk_num = '{$stk_num}'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from stockpacks t1
         where
         t1.id = {$_REQUEST["id"]}";
$stktemp = $CON->select($sql);
$stktemp = $stktemp[0];

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["cid"]                 = (int)$_REQUEST["cid"];
   $_REQUEST["sid"]                 = (int)$_REQUEST["sid"];
   $_REQUEST["stk_annotation"]      = trim(addslashes($_REQUEST["stk_annotation"]));
   $_REQUEST["stk_cinumber"]        = trim(addslashes($_REQUEST["stk_cinumber"]));
   $_REQUEST["stk_bookdate"]        = trim($_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]        = explode(".", $_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]        = (int)mktime(15, 0, 0, $_REQUEST["stk_bookdate"][1], $_REQUEST["stk_bookdate"][0], $_REQUEST["stk_bookdate"][2]);

   $sql = " update stockpacks
            set
            stk_annotation       = '{$_REQUEST["stk_annotation"]}',
            stk_bookdate         = {$_REQUEST["stk_bookdate"]},
            stk_updusr           = {$_SESSION["user_id"]},
            stk_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $thisid  = $_REQUEST["id"];

   if($res && $thisid)
   {
      $poscounter = 0;
      foreach(array_keys($_REQUEST) AS $reqkey)
      {
         if(strpos($reqkey, "xf_search_") !== false && strpos($reqkey, "xf_search_") == 0)
         {
            $idx = substr($reqkey, strrpos($reqkey, "_") +1);
            
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];
            $sql_charges_act  = (int)$itemvalues[4];

            //----------------------------------------------------------------------------------
            $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
            $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];
            
            $_REQUEST["amount_{$idx}"]       = getPrice($_REQUEST["amount_{$idx}"]);
            $_REQUEST["item_stid_{$idx}"]    = (int)$_REQUEST["item_stid_{$idx}"];

            if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["amount_{$idx}"] > 0.00)
            {
               if($existing_id)
               {
                  $sql = " update stockpacks_items
                           set
                           item_amount                = {$_REQUEST["amount_{$idx}"]},
                           item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                           item_pos                   = {$poscounter}
                           where
                           stk_id                     = {$_REQUEST["id"]} and
                           item_id                    = {$existing_id} and
                           item_pos                   = {$existing_pos}";
                  $CON->no_result($sql);
               }
               else
               {
                  $sql = " insert into stockpacks_items
                           (stk_id, item_id, item_pos, item_amount, item_type, item_st_id)
                           VALUES
                           ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["amount_{$idx}"]}, '{$sql_type}',
                            {$_REQUEST["item_stid_{$idx}"]})";
                  $CON->no_result($sql);
               }
               $poscounter++;
            }
            elseif($existing_id)
            {
               $sql = " delete from stockpacks_items
                        where
                        stk_id         = {$_REQUEST["id"]} and
                        item_id        = {$existing_id} and
                        item_pos       = {$existing_pos}";
               $CON->no_result($sql);
            }
         }
      }

      if($_REQUEST["stk_status"] == "2")
      {
         bookStockpackChange($CON, $_REQUEST["id"]);
      }

      if($stktemp["stk_status"] == 2 && $_REQUEST["stk_status"] == "1")
         delStockpackChange($CON, $_REQUEST["id"]);

      $savemsg = getSaveMessage(true);

      
   }
   else
      $savemsg = getSaveMessage(false);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t4.stkis_title, t5.st_name,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from stockpacks t1
            LEFT OUTER JOIN user t2 ON t1.stk_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.stk_crtusr = t3.id
            LEFT OUTER JOIN stockchanges_issues t4 ON t1.stk_issueid = t4.id
            LEFT OUTER JOIN company_shops_storehouses t5 ON t1.stk_fixedsthid = t5.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   $_REQUEST["cid"] = $headdata["stk_companyid"];
   $_REQUEST["sid"] = $headdata["stk_shopid"];
}

//----------------------------------------------------------------------------------
$sql = " select *
         from company_shops
         where
         id = {$_REQUEST["sid"]}";
$shop = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         id = {$_REQUEST["cid"]}";
$company = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t2.*, t3.item_title, t3.item_number_prod
         from stockpacks_items t2
         LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
         where
         t2.stk_id      = {$_REQUEST["id"]} and
         t2.item_type   = 'item'
         order by 3 asc";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;

//----------------------------------------------------------------------------------
if((int)$headdata["stk_status"] > 1)
{
   $rdlo       = "readonly";
   $dabl       = "disabled";
   $rowcount   = count($posdata);
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/stockpacks/searchstorehouses.php?stkid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;
      var valarr  = $('#item_id_' +idx).val().split('#');
      switchShpChargeMode(idx, valarr);
   }

   function checkStockchange(xform)
   {
      return checkform(new Array(this.stk_bookdate));
   }
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Armar/Desarmar Packs</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkStockchange(this);"
   <?php
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="sid" value="<?=$_REQUEST["sid"]?>">
<input type="hidden" name="stk_status" value="1">
<input type="hidden" name="printpdf" value="">
<input type="hidden" name="revertDel" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col width="350">
   <col width="100">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos de merma</td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["stk_num"]?>&nbsp;</td>
   <td class="content_rowl">Motivo</td>
   <td class="content_row"><?=$headdata["stkis_title"]?></option></td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$company[0]["company_short"]?>&nbsp;</td>
   <td class="content_rowl">Fecha *</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="stk_bookdate" name="stk_bookdate" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["stk_bookdate"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Surcusal / Bodega</td>
   <td class="content_row"><?=$shop[0]["shop_name"]?>&nbsp;/ <?=$headdata["st_name"]?></td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <?php
      $statimg = "";
      switch((int)$headdata["stk_status"])
      {
         case 0: $statimg = "gray_active.gif"; break;
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>">
      <?=getShipmentStatus($headdata["stk_status"], true)?>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row" colspan="3">
      <textarea class="text" name="stk_annotation" style="width:830px;height:45px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["stk_annotation"]?></textarea>
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
   <td class="content_row"><?=displayDate($headdata["stk_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["stk_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
$itemselw = "750px";
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col>
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader" align="left">Cantidad</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "amount_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";
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
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/stockpacks/searchitem.php?rowcount=<?=$x?>&id=<?=$_REQUEST["id"]?>&search=' +this.value;} this.value='';"
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
            <input type="hidden" name="existing_pos_<?=$x?>" value="<?=$posdata[$x]["item_pos"]?>">
            <input type="hidden" name="existing_id_<?=$x?>" value="<?=$posdata[$x]["item_id"]?>">
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
         <select class="text" style="width:<?=$itemselw?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onfocus="addSelStyle(this);"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onchange="setItemInfosStk('<?=$x?>', this.value)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc       = trim(addslashes($posdata[$x]["item_title"]));
               $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
               ?>
               <option value="<?=$posdata[$x]["item_id"]?>#<?=$posdata[$x]["item_type"]?>"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)</option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row" align="left">
         <input type="text" class="text" style="width:100px;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         name="amount_<?=$x?>" id="amount_<?=$x?>" <?=$rdlo?>
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">

         <input type="hidden" name="item_stid_<?=$x?>" id="item_stid_<?=$x?>" value="<?=$headdata["stk_fixedsthid"]?>">
      </td>
   </tr>
   <?php
   if($x == 0 && !(int)$posdata[$x]["item_id"])
      $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$x}.focus();";
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
      <td width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if((int)$headdata["stk_status"] == 1 && !$_BLOCKOPEN_CHARGE)
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   if((int)$headdata["stk_status"] == 1)
   {
      if($_REQUEST["id"] == "")
      {  ?>
         <td>&nbsp;</td>
         <?php
      }
      ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if(count($posdata) && $posdata != false && !$_BLOCKFIN_CHARGE)
      {  ?>
         <td align="right" width="130" id="idx_fin_button">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.stk_status.value = '2';document.form_shppos.submit(); }", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   elseif((int)$headdata["stk_status"] != 0)
   {
      if(!$_BLOCKOPEN_CHARGE)
      {  ?>
         <td align="right" width="140">
            <?php
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';submitForm(document.form_shppos);}", "arrow-circle-045-left");
            ?>
         </td>
         <?php
      }
      ?>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/stockpacks/data.stockpacks.pdf.php?id={$_REQUEST["id"]}'", "document-pdf");
         ?>
      </td>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&hash={$headdata["stk_num"]}.stockpacks.xls&name=Ajuste-{$headdata["stk_num"]}.xls&path=../../../docs.print/'", "document-excel");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<form action="index.php" method="post" name="form_updateci">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="updateCI">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="sid" value="<?=$_REQUEST["sid"]?>">
<input type="hidden" name="new_stk_cinumber" id="new_stk_cinumber" value="">
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');" ?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
