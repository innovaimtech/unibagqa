<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from supplier_contenedor t1
         LEFT OUTER JOIN company_data t3  ON t1.sord_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.sord_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.sord_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.sord_crtusr       = t6.id
         where
         t1.id = {$_REQUEST["id"]}";
$contheaddata = $CON->select($sql);
$contheaddata = $contheaddata[0];

if($_REQUEST["ccom"] == "save")
{
   $_REQUEST["stk_annotation"]   = trim(addslashes($_REQUEST["stk_annotation"]));
   $_REQUEST["stk_bookdate"]     = date('d.m.Y');
   $_REQUEST["stk_bookdate"]     = explode(".", $_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]     = (int)mktime(0, 0, 0, $_REQUEST["stk_bookdate"][1], $_REQUEST["stk_bookdate"][0], $_REQUEST["stk_bookdate"][2]);

   $poscounter = 0;
   $_hasitems = false;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "contcfm_") !== false && strpos($reqkey, "contcfm_") == 0)
      {
         $reqkeyarr  = explode("_", $reqkey);
         $itemid     = $reqkeyarr[1];
         $refid      = $reqkeyarr[2];
         $cfmamt     = getPrice($_REQUEST[$reqkey]);
         if($cfmamt > 0.00)
            $_hasitems = true;
      }
   }

   if($_hasitems)
   {
      $stk_num    = createTransactionNumber($CON, $contheaddata["sord_company_id"], "stockchange");
      $_ISSUE_ID  = 17;
      $currtme    = time();

      $sql = " insert into stockchanges
               (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                stk_negative, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid,
                sth_supporder_contenedorid)
               VALUES
               ('{$stk_num}', '{$_REQUEST["stk_annotation"]}',
                 {$_ISSUE_ID}, {$contheaddata["sord_company_id"]}, {$contheaddata["sord_shop_id"]}, {$_REQUEST["stk_bookdate"]},
                 0, {$currtme}, {$_SESSION["user_id"]}, 0, 0, {$_REQUEST["id"]})";
      $res = $CON->no_result($sql);
      if($res)
      {
         $sthid = mysql_insert_id();
         $poscounter = 0;
         foreach(array_keys($_REQUEST) AS $reqkey)
         {
            if(strpos($reqkey, "contcfm_") !== false && strpos($reqkey, "contcfm_") == 0)
            {
               $reqkeyarr  = explode("_", $reqkey);
               $itemid     = $reqkeyarr[1];
               $refid      = $reqkeyarr[2];
               $cfmamt     = getPrice($_REQUEST[$reqkey],2);
               if($cfmamt > 0.00)
               {
                  $sql = " insert into stockchanges_items
                           (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_contenedor_refid)
                           VALUES
                           ({$sthid}, {$itemid}, {$poscounter}, {$cfmamt}, 'item', {$_REQUEST["item_stid_{$itemid}_{$refid}"]}, {$refid})";
                  $CON->no_result($sql);
                  $poscounter++;
               }
            }
         }

         bookStockChange($CON, $sthid);
         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=receive&id=<?=$_REQUEST["id"]?>';
         </script>
         <?php
      }
   }
}

if(!(int)$_REQUEST["cid"])
{
   $headdata["stk_bookdate"] = time();
   $headdata["stk_status"] = 1;

   $sql = " select t1.*
            from supplier_contenedor_items t1
            INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
            where
            t1.sord_id = {$_REQUEST["id"]}
            order by t1.id asc";
   $posdata = $CON->select($sql);
}
else
{
   $sql = " select t1.*, t4.stkis_title, t5.cust_name,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from stockchanges t1
            LEFT OUTER JOIN user t2 ON t1.stk_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.stk_crtusr = t3.id
            LEFT OUTER JOIN stockchanges_issues t4 ON t1.stk_issueid = t4.id
            LEFT OUTER JOIN customer t5 ON t1.stk_custid = t5.id
            where
            t1.id = {$_REQUEST["cid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];


   $sql = " select t1.*, t3.item_amount 'cfmamt', t4.st_name
            from supplier_contenedor_items t1
            INNER JOIN supplier_order_items t2        ON t1.sord_pos_id = t2.id
            INNER JOIN stockchanges_items t3          ON t3.stk_id = {$_REQUEST["cid"]} and t3.item_id = t2.item_id and t3.item_contenedor_refid = t1.id
            INNER JOIN company_shops_storehouses t4   ON t3.item_st_id = t4.id
            where
            t1.sord_id = {$_REQUEST["id"]}
            order by t1.id asc";
   $posdata = $CON->select($sql);

   $rdlo       = "readonly";
   $dabl       = "disabled";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="form_shppos"
onsubmit="<?if((int)$_REQUEST["cid"]) echo "return false"?>;return checkform(new Array(this.stk_bookdate))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
   <col width="120">
   <col width="500">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos Básicos</td>
</tr>
<?php
if((int)$_REQUEST["cid"])
{  ?>
   <tr>
      <td class="content_rowl" width="120">ID Transacción</td>
      <td class="content_row" colspan="3"><b class="msg_save_ok"><?=$headdata["stk_num"]?></b></td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl" width="120">Fecha recepción *</td>
   <td class="content_row" colspan="3">
      <input type="text" style="width:80px" id="stk_bookdate" name="stk_bookdate" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["stk_bookdate"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row" colspan="3">
      <textarea class="text" name="stk_annotation" style="width:100%;height:60px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["stk_annotation"]?></textarea>
   </td>
</tr>
<?php
if((int)$_REQUEST["cid"])
{  ?>
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
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col width="90">
   <col>
   <col width="40">
   <col width="80">
   <col>
   <col width="80">
   <col width="90">
   <col width="130">
   <col width="130">
   <col width="210">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="11">Productos</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os">OC</td>
   <td class="content_tbl_subheader content_row_os">Fecha OC</td>
   <td class="content_tbl_subheader content_row_os">Proveedor</td>
   <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
   <td class="content_tbl_subheader content_row_os">Código</td>
   <td class="content_tbl_subheader content_row_os">Artículo</td>
   <td class="content_tbl_subheader content_row_os" align="center">Kg Total</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cant. OC</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cant. Contenedor</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cant. Recepción</td>
   <td class="content_tbl_subheader content_row_os" align="left">Bodega destino</td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from supplier_order_items
            where
            id = {$posdata[$x]["sord_pos_id"]}";
   $supporderpos = $CON->select($sql);
   $supporderpos = $supporderpos[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_short
            from supplier_order t1
            LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id  = t2.id
            where
            t1.id = {$supporderpos["sord_id"]}";
   $sorddata = $CON->select($sql);
   $sorddata = $sorddata[0];

   $fullpos = getSupplierOrderPos($CON, $supporderpos["sord_id"], "", $posdata[$x]["sord_pos_id"]);
   $fullpos = $fullpos[0];

   $itemsts = getItemStorehouses($CON, $contheaddata["sord_shop_id"], $fullpos["item_id"], $fullpos["item_type"]);
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=$sorddata["sord_number"]?></td>
      <td class="content_row_os"><?=date('d.m.Y', $sorddata["sord_crtdat"])?></td>
      <td class="content_row_os"><?=$sorddata["supp_short"]?></td>
      <td class="content_row_os" align="center"><?=($supporderpos["item_pos"]+1)?></td>
      <td class="content_row_os"><?=$fullpos["item_number_prod"]?></td>
      <td class="content_row_os"><?=$fullpos["item_title"]?></td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["sord_kgs_amount"], 2)?></td>
      <td class="content_row_os" align="center"><?=printPrice($fullpos["item_amount"], 2)?></td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["sord_amount"], 2)?></td>
      <td class="content_row_os" align="center">
         <?php
         if(!(int)$_REQUEST["cid"])
         {  ?>
            <input type="checkbox" onclick="if(this.checked) $('#contcfm_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>').val('<?=printPrice($posdata[$x]["sord_amount"], 2)?>'); else $('#contcfm_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>').val('');">
            <?php
         }
         ?>
         <input type="text" class="text" style="width:80px;text-align:center"
         name="contcfm_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>" id="contcfm_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>"
         value="<?if((float)$posdata[$x]["cfmamt"]) echo printPrice($posdata[$x]["cfmamt"],2)?>" <?=$rdlo?>>
      </td>
      <td class="content_row_os" align="left">
         <?php
         if(!(int)$_REQUEST["cid"])
         {  ?>
            <select class="text" style="width:200px;<?if((int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>"
            name="item_stid_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>" id="item_stid_<?=$fullpos["item_id"]?>_<?=$posdata[$x]["id"]?>"
            onmousedown="markfield(this,0)"
            onfocus="addSelStyle(this);"
            onblur="markfield(this,1);removeSelStyle(this);">
               <?php
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     if($itemstid == $headdata["stk_fixedsthid"] || !(int)$headdata["stk_fixedsthid"])
                     {
                        $currstock = getItemShopStorehouseCurrentStock($CON, $contheaddata["sord_shop_id"], $itemstid, $fullpos["item_id"], $fullpos["item_type"], true);
                        ?>
                        <option value="<?=$itemstid?>"><?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)</option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
            <?php
         }
         else
            echo $posdata[$x]["st_name"];
         ?>
      </td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="11" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=receive&id={$_REQUEST["id"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
   }
   if((int)$headdata["stk_status"] == 1)
   {
      if($_REQUEST["id"] == "")
      {  ?>
         <td>&nbsp;</td>
         <?php
      }
      if(count($posdata) && $posdata != false && !$_BLOCKFIN_CHARGE)
      {  ?>
         <td align="right" width="130" id="idx_fin_button">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.submit(); }", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   /*
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
   }
   */
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>