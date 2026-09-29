<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xprodext"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xprodext"]["fullcust"] = "";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["supplier_id_0"] = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];
   $_REQUEST["req_dlv_mode"]  = (int)$_REQUEST["req_dlv_mode"];

   //----------------------------------------------------------------------------------
   $invc_date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));

   //----------------------------------------------------------------------------------
   $sql = " insert into prod_item_ext
            (req_supplier_id, req_company_id, req_shop_id, req_date, req_delivery_date, req_crtdat, req_crtusr, req_dlv_mode)
            VALUES
            ({$_REQUEST["supplier_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$invc_date}, {$invc_date}, {$currtme}, {$_SESSION["user_id"]}, {$_REQUEST["req_dlv_mode"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from prod_item_ext
               where
               req_crtusr = {$_SESSION["user_id"]}";
      $prod = $CON->select($sql);
      $_REQUEST["id"]   = $prod[0]["thisid"];

      //----------------------------------------------------------------------------------
      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=800&exec=edit&id=<?=$_REQUEST["id"]?>'
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
   $_REQUEST["req_desc"]            = trim(addslashes($_REQUEST["req_desc"]));
   $_REQUEST["req_desc_intern"]     = trim(addslashes($_REQUEST["req_desc_intern"]));
   $_REQUEST["req_date"]            = trim(addslashes($_REQUEST["req_date"]));
   $_REQUEST["req_dlv_docnum"]      = trim(addslashes($_REQUEST["req_dlv_docnum"]));
   $_REQUEST["req_delivery_date"]   = trim(addslashes($_REQUEST["req_delivery_date"]));

   //----------------------------------------------------------------------------------
   $_REQUEST["req_date"] = explode(".", $_REQUEST["req_date"]);
   $_REQUEST["req_date"] = (int)mktime(15, 0, 0, $_REQUEST["req_date"][1], $_REQUEST["req_date"][0], $_REQUEST["req_date"][2]);

   $_REQUEST["req_delivery_date"] = explode(".", $_REQUEST["req_delivery_date"]);
   $_REQUEST["req_delivery_date"] = (int)mktime(15, 0, 0, $_REQUEST["req_delivery_date"][1], $_REQUEST["req_delivery_date"][0], $_REQUEST["req_delivery_date"][2]);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from prod_item_ext
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   //----------------------------------------------------------------------------------
   $revert  = false;
   $final   = false;
   if($headdata["req_status"] == 1 && $_REQUEST["req_status"] == 2)
      $final = true;
   if($headdata["req_status"] == 2 && $_REQUEST["req_status"] == 1)
      $revert = true;
      
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         $_REQUEST["item_amount_{$idx}"] = getPrice($_REQUEST["item_amount_{$idx}"],2);

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];

            $itemvalues       = explode("#", $_REQUEST["item_iddest_{$idx}"]);
            $sql_iddest       = (int)$itemvalues[0];
            $sql_typedest     = $itemvalues[1];

            $item_stid        = (int)$_REQUEST["item_stid_{$idx}"];
            $item_amount      = getPrice($_REQUEST["item_amount_{$idx}"],2);

            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update prod_item_ext_pos
                        set
                        item_amount                = {$item_amount},
                        item_amount_dest           = {$item_amountdest},
                        item_st_id                 = {$item_stid},
                        item_st_id_dest            = {$item_stiddest},
                        item_pos                   = {$poscounter},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}'
                        where
                        req_id   = {$_REQUEST["id"]} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " insert into prod_item_ext_pos
                        (req_id, item_pos, item_id, item_id_dest, item_amount, item_type, item_type_dest,
                         item_st_id, item_desc)
                        VALUES
                        ({$_REQUEST["id"]}, {$poscounter}, {$sql_id}, {$sql_iddest}, {$item_amount}, 
                        '{$sql_type}', '{$sql_typedest}', {$item_stid}, 
                         '{$_REQUEST["item_desc_{$idx}"]}')";
               $CON->no_result($sql);
            }
            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from prod_item_ext_pos
                     where
                     req_id   = {$_REQUEST["id"]} and
                     item_id  = {$existing_id} and
                     item_pos = {$existing_pos}";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update prod_item_ext
            set
            req_status           = {$_REQUEST["req_status"]},
            req_desc             = '{$_REQUEST["req_desc"]}',
            req_desc_intern      = '{$_REQUEST["req_desc_intern"]}',
            req_date             = {$_REQUEST["req_date"]},
            req_delivery_date    = {$_REQUEST["req_delivery_date"]},
            req_dlv_docnum       = '{$_REQUEST["req_dlv_docnum"]}',
            req_updusr           = {$_SESSION["user_id"]},
            req_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   if(!(int)$headdata["req_dlv_mode"])
   {
      if($final)
         bookProdExt($CON, $_REQUEST["id"]);
      if($revert)
         revertProdExt($CON, $_REQUEST["id"]);
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t3.company_short, t4.shop_name, t2.supp_notes,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from prod_item_ext t1
         LEFT OUTER JOIN supplier t2      ON t1.req_supplier_id  = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.req_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.req_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.req_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.req_crtusr       = t6.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         where
         t1.id = {$headdata["req_supplier_id"]}";
$supplier = $CON->select($sql);
$supplier = $supplier[0];

$posdata  = getProdExtPos($CON, $_REQUEST["id"], $_REQUEST["setPosOrder"]);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;
   
//----------------------------------------------------------------------------------
if($headdata["req_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------
if($_SESSION["xprodext"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/item_prodext/searchstorehouses.php?id=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;
   }
   function updateItemStorehousesDest(idx, itemid, itemtype)
   {
      document.all.idxifrsrc2.src='./libs/modules/item_prodext/searchstorehousesdest.php?id=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;
   }

   function updateItemStorehousesArr(idx, xval)
   {
      var valarr = xval.split('#');

      document.all.idxifrsrc2.src='./libs/modules/item_prodext/searchitemdest.php?rowcount=' +idx +'&id=<?=$headdata["id"]?>&itemid=' +valarr[2] +'&itemtype=' +valarr[1];
      updateItemStorehouses(idx, valarr[0], valarr[1]);
   }

   function detectEvent (event, rowcount, id, storehousemode)
   {
      var xurl = './libs/modules/invoices_buy/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.req_date, this.req_dlv_docnum, this.req_delivery_date));"
   <?php
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="req_status" value="1">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">
      <img src="./images/menu/icons/arrow-move.png" height="14" style="cursor:pointer;vertical-align:bottom"
      onclick="var x=0;var brows = $('#ifx_tblheader > tbody');
               brows.each(function(){x++;if(x > 1)
               {if($(this).is(':hidden')) $(this).show(); else $(this).hide();}});">
      Datos básicos
   </td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=sprintf("%07s", $headdata["id"])?></td>
   <td class="content_rowl">Fecha</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="req_date" name="req_date" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y', $headdata["req_date"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Proveedor</td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xprodext"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$headdata["supp_company"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$supplier["supp_rut"]?></b>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xprodext"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$supplier["supp_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($supplier["supp_phone"] != "") echo $supplier["supp_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xprodext"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$supplier["name"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][16]?></td>
   <td class="content_row"><?=$supplier["supp_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xprodext"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$supplier["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$supplier["supp_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xprodext"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">País</td>
   <td class="content_row"><?=$supplier["country_name"]?>&nbsp;</td>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Cambiar datos del proveedor", "postnav", "index.php?mid=497&exec=edit&id={$supplier["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}", "", "disk-black", 200);
            ?>
         </td>
         <td class="content_row_clear" style="padding-left:5px">
            <?php
            printGooglemapsButton($supplier["supp_street"], $supplier["name"], $supplier["country_name"]);
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Guia de despacho *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <?php
      if((int)$headdata["req_dlv_mode"])
      {
         if($headdata["req_dlv_docnum"] == "")
            printButton("Asignar Guia", "postnav_save", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/item_prodext/assigndlv.fancy.php?id={$_REQUEST["id"]}', 'iframe', 450, 250, 'no')");
         else
         {  ?>
            Número: <?=$headdata["req_dlv_docnum"]?>, Fecha: <?=date('d.m.Y', $headdata["req_delivery_date"])?>
            <?php
         }
         ?>
         <div style="display:none">
         <input type="text" style="width:153px" name="req_dlv_docnum" class="text" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["req_dlv_docnum"]?>">
         <input type="text" style="width:80px" id="req_delivery_date" name="req_delivery_date" <?=$rdlo?>
         class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=date('d.m.Y', $headdata["req_delivery_date"])?>">
         </div>
         <?php
      }
      else
      {  ?>
         Número:
         <input type="text" style="width:153px" name="req_dlv_docnum" class="text" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["req_dlv_docnum"]?>">

         Fecha:
         <input type="text" style="width:80px" id="req_delivery_date" name="req_delivery_date" <?=$rdlo?>
         class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=date('d.m.Y', $headdata["req_delivery_date"])?>">
         <?php
      }
      ?>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>>Estado</td>
   <td class="content_row" <?=$cdatastyle?>>
      <?php
      $statimg = "";
      switch((int)$headdata["req_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "orange_active.gif"; break;
         case 3: $statimg = "green_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
      <?=getProdExtStatus($headdata["req_status"], true)?>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[cliente]</td>
   <td class="content_row" valign="top">
      <textarea class="text" style="width:350px; height:70px" name="req_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["req_desc"])?></textarea>
   </td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row" valign="top">
      <textarea class="text" style="width:350px; height:70px" name="req_desc_intern" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["req_desc_intern"])?></textarea>
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
   <td class="content_row"><?=displayDate($headdata["req_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["req_upddat"])?></td>
</tr>
<?php
if(trim($headdata["supp_notes"]) != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Comentarios</td>
      <td class="content_row" colspan="3" style="color:navy"><?generateCommentToogle($headdata["supp_notes"])?></td>
   </tr>
   <?php
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col width="28">
   <col>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader" valign="top">Busqueda</td>
   <td class="content_tbl_subheader" valign="top">Act.</td>
   <td class="content_tbl_subheader" valign="top">Artículo/Origin</td>
   <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
   <td class="content_tbl_subheader" valign="top">Cantidad</td>
   <td class="content_tbl_subheader" valign="top"><nobr>Bodega/Origin</nobr></td>
   <td class="content_tbl_subheader" valign="top"><nobr>Artículo/Modificado</nobr></td>
</tr>
<?php
$hasItems = false;
//----------------------------------------------------------------------------------
for($x = 0; $x < $rowcount; $x++)
{
   $showmanual = false;
   if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_type"] == "manual")
      $showmanual = true;

   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_iddest_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amountdest_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stiddest_{$x}";
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <?if($showmanual) { echo "&nbsp;"; $_FIELDIGNORES["xf_search_{$x}"] = 1; } ?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$x?>" <?php if($showmanual) echo "style='display:none'" ?>>
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" name="xf_search_<?=$x?>" id="xf_search_<?=$x?>"
               onfocus="markfield(this,0)" <?=$rdlo?> autocomplete="off"
               <?php
               if(!(int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/item_prodext/searchitem.php?rowcount=<?=$x?>&id=<?=$headdata["id"]?>&search=' +this.value} this.value='';"
                  onkeyup="detectEvent(event, '<?=$x?>', '<?=$_REQUEST["id"]?>')"
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
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="hidden" name="existing_id_<?=$x?>" value="<?=$posdata[$x]["item_id"]?>">
            <input type="hidden" name="existing_pos_<?=$x?>" value="<?=$posdata[$x]["item_pos"]?>">
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)" <?=$dabl?>
            onclick="if(askDel('')) { document.form_shppos.item_amount_<?=$x?>.value='0'; submitForm(document.form_shppos); }">
            <?php
         }
         else
         {  ?>
            <img src="/images/menu/icons/notebook--plus.png" border="0" style="cursor:pointer"
            onclick="showOrderPartPosManualEdit('<?=$x?>')">
            <?php
         }
         ?>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:370px;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
         onchange="updateItemStorehousesArr('<?=$x?>', this.value);">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc = trim(addslashes($posdata[$x]["item_title"]));
               ?>
               <option value="<?=$posdata[$x]["item_id"]?>#<?=$posdata[$x]["item_type"]?>"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></option>
               <?php
            }
            ?>
         </select>
         <textarea class="text" name="item_desc_<?=$x?>" id="item_desc_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         style="width:370px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
         <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
         value="<?php if($showmanual) echo "1"; else echo "0" ?>">
      </td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
            echo getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         name="item_amount_<?=$x?>" id="item_amount_<?=$x?>" <?=$rdlo?>
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:100px"
         name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="addSelStyle(this);"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if($posdata[$x]["item_type"] == "item")
                  $itemsts = getItemStorehouses($CON, $headdata["req_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["req_shop_id"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     if($rdlo == "" || ($rdlo != "" && $posdata[$x]["item_st_id"] == $itemstid))
                     {
                        $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
                        ?>
                        <option value="<?=$itemstid?>"
                        <?php if($posdata[$x]["item_st_id"] == $itemstid) echo "selected"?>>
                           <?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)
                        </option>
                        <?php
                     }
                  }
               }
            }
            ?>
         </select>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:235px;<?php if($showmanual) echo "display:none" ?>" name="item_iddest_<?=$x?>" id="item_iddest_<?=$x?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$x]["item_id_dest"]) echo "addSelStyle(this);" ?>">
            <?php
            if((int)$posdata[$x]["item_id_dest"])
            {
               $desc = trim(addslashes($posdata[$x]["item_titledest"]));
               ?>
               <option value="<?=$posdata[$x]["item_id_dest"]?>#<?=$posdata[$x]["item_type_dest"]?>"><?=$posdata[$x]["item_number_proddest"]?> - <?=$desc?></option>
               <?php
            }
            ?>
         </select>
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
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($headdata["req_status"] == 2)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.req_status.value='1';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   if($headdata["req_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if($hasItems)
      {  ?>
         <td align="right" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.req_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   ?>
</tr>
</table>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<iframe id="idxifrsrc2" height="0" width="0" frameborder="0"></iframe>