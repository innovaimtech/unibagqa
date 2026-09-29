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
   $sql = " update stockchanges
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
   $_REQUEST["stk_annotation"]   = trim(addslashes($_REQUEST["stk_annotation"]));
   $_REQUEST["issue_id"]         = (int)$_REQUEST["issue_id"];
   $_REQUEST["stk_isventainterna"] = (int)$_REQUEST["stk_isventainterna"];

   $_REQUEST["stk_bookdate"]     = date('d.m.Y');
   $_REQUEST["stk_bookdate"]     = explode(".", $_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]     = (int)mktime(0, 0, 0, $_REQUEST["stk_bookdate"][1], $_REQUEST["stk_bookdate"][0], $_REQUEST["stk_bookdate"][2]);

   $_REQUEST["sth_planta_id"]       = (int)$_REQUEST["sth_planta_id"];
   $_REQUEST["sth_assign_equipoid"] = (int)$_REQUEST["sth_assign_equipoid"];
   $_REQUEST["sth_assign_wrkid"]    = (int)$_REQUEST["sth_assign_wrkid"];

   $sql = " select stkis_negative
            from stockchanges_issues 
            where
            id = {$_REQUEST["issue_id"]}";
   $stkis_negative = $CON->select($sql);
   $stkis_negative = (int)$stkis_negative[0]["stkis_negative"];
   
   $stk_num = createTransactionNumber($CON, $_REQUEST["cid"], "stockchange");

   $sql = " insert into stockchanges
            (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
             stk_negative, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid,
             sth_planta_id, sth_assign_equipoid, sth_assign_wrkid, sth_is_repuesto)
            VALUES
            ('{$stk_num}', '{$_REQUEST["stk_annotation"]}',
              {$_REQUEST["issue_id"]}, {$_REQUEST["cid"]}, {$_REQUEST["sid"]}, {$_REQUEST["stk_bookdate"]},
              {$stkis_negative}, {$currtme}, {$_SESSION["user_id"]}, {$_REQUEST["stk_isventainterna"]},
              {$_REQUEST["sthid"]}, {$_REQUEST["sth_planta_id"]}, {$_REQUEST["sth_assign_equipoid"]}, 
              {$_REQUEST["sth_assign_wrkid"]}, 1)";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from stockchanges
               where
               stk_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      $_REQUEST["id"] = $thisid;
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from stockchanges t1
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
   $_REQUEST["stk_isventainterna"]  = (int)$_REQUEST["stk_isventainterna"];
   $_REQUEST["stk_userid"]          = (int)$_REQUEST["stk_userid"];
   $_REQUEST["stk_custid"]          = (int)$_REQUEST["stk_custid"];
   $_REQUEST["stk_ispayed"]         = (int)$_REQUEST["stk_ispayed"];
   $_REQUEST["stk_annotation"]      = trim(addslashes($_REQUEST["stk_annotation"]));
   $_REQUEST["stk_cinumber"]        = trim(addslashes($_REQUEST["stk_cinumber"]));
   $_REQUEST["stk_bookdate"]        = trim($_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]        = explode(".", $_REQUEST["stk_bookdate"]);
   $_REQUEST["stk_bookdate"]        = (int)mktime(0, 0, 0, $_REQUEST["stk_bookdate"][1], $_REQUEST["stk_bookdate"][0], $_REQUEST["stk_bookdate"][2]);
   $_REQUEST["stk_discount_perc"]   = getPrice($_REQUEST["stk_discount_perc"],2);
   $_REQUEST["stk_discount_amt"]    = getPrice($_REQUEST["stk_discount_amt"]);

   if(!(int)$_REQUEST["stk_isventainterna"])
   {
      $_REQUEST["stk_userid"]    = 0;
      $_REQUEST["stk_custid"]    = 0;
      $_REQUEST["stk_ispayed"]   = 0;
      $_REQUEST["stk_cinumber"]  = "";
   }
   
   $sql = " update stockchanges
            set
            stk_annotation       = '{$_REQUEST["stk_annotation"]}',
            stk_cinumber         = '{$_REQUEST["stk_cinumber"]}',
            stk_isventainterna   = {$_REQUEST["stk_isventainterna"]},
            stk_bookdate         = {$_REQUEST["stk_bookdate"]},
            stk_discount_perc    = {$_REQUEST["stk_discount_perc"]},
            stk_discount_amt     = {$_REQUEST["stk_discount_amt"]},
            stk_userid           = {$_REQUEST["stk_userid"]},
            stk_custid           = {$_REQUEST["stk_custid"]},
            stk_ispayed          = {$_REQUEST["stk_ispayed"]},
            stk_updusr           = {$_SESSION["user_id"]},
            stk_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   if((int)$_REQUEST["stk_isventainterna"] && trim($stktemp["stk_vintnumber"]) == "" && (int)$stktemp["stk_issueid"] == 5)
   {
      $stk_vintnumber = createNumberSystem($CON, "VENTAINTERNA");
      $sql = " update stockchanges
               set
               stk_vintnumber = '{$stk_vintnumber}'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   $thisid  = $_REQUEST["id"];

   $_TOTAL_VALUE = 0.00; 
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
            
            $_REQUEST["amount_{$idx}"]       = getPrice($_REQUEST["amount_{$idx}"],10);
            $_REQUEST["item_stid_{$idx}"]    = (int)$_REQUEST["item_stid_{$idx}"];

            if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["amount_{$idx}"] > 0.00)
            {
               $sql_costprice    = getPrice($_REQUEST["item_costprice_netto_{$idx}"]);
               $sql_taxesperc    = getPrice($_REQUEST["item_costprice_taxes_perc_{$idx}"],2);
               $sql_sellprice    = getPrice($_REQUEST["item_sellprice_brutto_{$idx}"]);
               $sql_taxes        = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costprice / 100  * $sql_taxesperc);
               $sql_costbrutto   = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costprice + $sql_taxes);

               $_TOTAL_VALUE += ($_REQUEST["amount_{$idx}"] * $sql_sellprice);
               
               //----------------------------------------------------------------------------------
               if($existing_id)
               {
                  $sql = " update stockchanges_items
                           set
                           item_amount                = {$_REQUEST["amount_{$idx}"]},
                           item_costprice_brutto      = {$sql_costbrutto},
                           item_costprice_taxes_perc  = {$sql_taxesperc},
                           item_costprice_netto       = {$sql_costprice},
                           item_costprice_taxes       = {$sql_taxes},
                           item_sellprice_brutto      = {$sql_sellprice},
                           item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                           item_pos                   = {$poscounter}
                           where
                           stk_id                     = {$_REQUEST["id"]} and
                           item_id                    = {$existing_id} and
                           item_pos                   = {$existing_pos}";
                  $CON->no_result($sql);

                  if(!(int)$stktemp["stk_negative"])
                  {
                     renameItemChargePos($CON, $_REQUEST["id"], "stockchangeup", $existing_pos, $poscounter);
                  
                     updateItemChargeData($CON, $_REQUEST["id"], "stockchangeup", $existing_id, $sql_type, $poscounter,
                                          $stktemp["stk_companyid"], $stktemp["stk_shopid"], $_REQUEST["item_charges_data_{$idx}"]);
                  }
                  else
                  {
                     renameItemChargePosUsed($CON, $_REQUEST["id"], "stockchangedown", $existing_pos, $poscounter);

                     updateItemChargeDataUsed($CON, $_REQUEST["id"], "stockchangedown", $existing_id, $sql_type, $poscounter,
                                             $stktemp["stk_companyid"], $stktemp["stk_shopid"], $_REQUEST["item_charges_data_{$idx}"]);
                  }
               }
               else
               {
                  $sql = " insert into stockchanges_items
                           (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                            item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                            item_sellprice_brutto)
                           VALUES
                           ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["amount_{$idx}"]}, '{$sql_type}',
                            {$_REQUEST["item_stid_{$idx}"]}, {$sql_costbrutto}, {$sql_taxesperc}, {$sql_costprice},
                            {$sql_taxes}, {$sql_charges_act}, {$sql_sellprice})";
                  $CON->no_result($sql);

                  if(!(int)$stktemp["stk_negative"])
                  {
                     updateItemChargeData($CON, $_REQUEST["id"], "stockchangeup", $sql_id, $sql_type, $poscounter,
                                          $stktemp["stk_companyid"], $stktemp["stk_shopid"], $_REQUEST["item_charges_data_{$idx}"]);
                  }
                  else
                  {
                     updateItemChargeDataUsed($CON, $_REQUEST["id"], "stockchangedown", $sql_id, $sql_type, $poscounter,
                                             $stktemp["stk_companyid"], $stktemp["stk_shopid"], $_REQUEST["item_charges_data_{$idx}"]);
                  }
               }
               $poscounter++;
            }
            elseif($existing_id)
            {
               $sql = " delete from stockchanges_items
                        where
                        stk_id         = {$_REQUEST["id"]} and
                        item_id        = {$existing_id} and
                        item_pos       = {$existing_pos}";
               $CON->no_result($sql);

               if(!(int)$stktemp["stk_negative"])
                  clearItemChargeData($CON, $_REQUEST["id"], "stockchangeup", $existing_pos);
               else
                  clearItemChargeDataUsed($CON, $_REQUEST["id"], "stockchangedown", $existing_pos);
            }
         }
      }

      $stk_total_netto = (float)$_TOTAL_VALUE;

      //----------------------------------------------------------------------------------
      if($_REQUEST["stk_discount_perc"] > 0.00)
         $stk_total_netto = round($stk_total_netto - $stk_total_netto / 100 * $_REQUEST["stk_discount_perc"],0);
      if($_REQUEST["stk_discount_amt"] > 0.00)
         $stk_total_netto = round($stk_total_netto - $_REQUEST["stk_discount_amt"],0);

      $stk_discount_amount_netto = (float)($_TOTAL_VALUE - $stk_total_netto);

      $sql = " update stockchanges
               set
               stk_total_netto            = {$stk_total_netto},
               stk_discount_amount_netto  = {$stk_discount_amount_netto}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      if($_REQUEST["stk_status"] == "2")
      {
         bookStockChange($CON, $_REQUEST["id"]);
         xls_createStockchanges($CON, $_REQUEST["id"]);
      }

      if($stktemp["stk_status"] == 2 && $_REQUEST["stk_status"] == "1")
         delStockChange($CON, $_REQUEST["id"]);

      $savemsg = getSaveMessage(true);

      
   }
   else
      $savemsg = getSaveMessage(false);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t4.stkis_title, t5.cust_name,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname',
            t6.planta_name, t7.wrk_lastname, t7.wrk_firstname, t8.equipo_name
            from stockchanges t1
            LEFT OUTER JOIN user t2 ON t1.stk_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.stk_crtusr = t3.id
            LEFT OUTER JOIN stockchanges_issues t4 ON t1.stk_issueid = t4.id
            LEFT OUTER JOIN customer t5 ON t1.stk_custid = t5.id
            LEFT OUTER JOIN plantas t6 ON t1.sth_planta_id = t6.id
            LEFT OUTER JOIN workers t7 ON t1.sth_assign_wrkid = t7.id
            LEFT OUTER JOIN equipo t8  ON t1.sth_assign_equipoid = t8.id
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
         from stockchanges_items t2
         LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
         where
         t2.stk_id      = {$_REQUEST["id"]} and
         t2.item_type   = 'item'
         UNION ALL
         select t2.*, t3.item_title, t3.item_number_prod
         from stockchanges_items t2
         LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
         where
         t2.stk_id      = {$_REQUEST["id"]} and
         t2.item_type   = 'itemlist'
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
      document.all.idxifrsrc.src='./libs/modules/prod_stockchanges_repuestos/searchstorehouses.php?stkid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;
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
   <td height="30"><b class="content_header">Cambiar ajuste de stock</b></td>
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
   <td class="content_rowl">Empresa/Sucursal</td>
   <td class="content_row"><?=$company[0]["company_short"]?> | <?=$shop[0]["shop_name"]?></td>
   <td class="content_rowl">Fecha *</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="stk_bookdate" name="stk_bookdate" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["stk_bookdate"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Planta</td>
   <td class="content_row"><?=$headdata["planta_name"]?>&nbsp;</td>
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
   <td class="content_rowl">Máquina asociada</td>
   <td class="content_row"><?=$headdata["equipo_name"]?>&nbsp;</td>
   <td class="content_rowl">Entregado a</td>
   <td class="content_row"><?=$headdata["wrk_lastname"]?>, <?=$headdata["wrk_firstname"]?></td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Venta interna</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="stk_isventainterna" id="stk_isventainterna"
      onclick="if(this.checked) { $('.idxcls_vint').show();$('.idxcls_novint').hide(); } else { $('.idxcls_vint').hide();$('.idxcls_novint').show(); } "
      <?php if((int)$headdata["stk_isventainterna"]) echo "checked"?>> Activado
   </td>
   <td class="content_rowl idxcls_vint" <?php if(!(int)$headdata["stk_isventainterna"]) echo "style='display:none'"?>>Pago</td>
   <td class="content_row idxcls_vint" <?php if(!(int)$headdata["stk_isventainterna"]) echo "style='display:none'"?>>
      <input type="radio" name="stk_ispayed" value="0" <?php if(!(int)$headdata["stk_ispayed"]) echo "checked"?>
      <?php
      if((int)$headdata["stk_status"] > 1)
      {  ?>
         onclick="location.href='/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$_REQUEST["id"]?>&setpayed=0'"
         <?php
      }
      ?>> No Pagado
      <input type="radio" name="stk_ispayed" value="1" <?php if((int)$headdata["stk_ispayed"]) echo "checked"?>
      <?php
      if((int)$headdata["stk_status"] > 1)
      {  ?>
         onclick="location.href='/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$_REQUEST["id"]?>&setpayed=1'"
         <?php
      }
      ?>> Pagado
   </td>
   <td class="content_rowl idxcls_novint" <?php if((int)$headdata["stk_isventainterna"]) echo "style='display:none'"?>>&nbsp;</td>
   <td class="content_row idxcls_novint" <?php if((int)$headdata["stk_isventainterna"]) echo "style='display:none'"?>>&nbsp;</td>
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
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85">
   <col width="28">
   <col>
   <col width="50">
   <col width="50">
   <?php
   $itemselw = "590px";
   if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
   {  ?>
      <col width="100">
      <col width="100">
      <?php
      $itemselw = "405px";
   }
   ?>
   <col style="display:none">
   <col width="45" style="display:none">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader" align="left">Cantidad</td>
   <td class="content_tbl_subheader">Bodega</td>
   <?php
   if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
   {  ?>
      <td class="content_tbl_subheader" align="right">Precio/Neto</td>
      <td class="content_tbl_subheader" align="right">Precio/Total</td>
      <?php
   }
   ?>
   <td class="content_tbl_subheader" align="right" style="display:none">Costo (neto)</td>
   <td class="content_tbl_subheader" valign="top" align="right" style="display:none">IVA %</td>
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
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/prod_stockchanges_repuestos/searchitem.php?rowcount=<?=$x?>&id=<?=$_REQUEST["id"]?>&search=' +this.value;} this.value='';"
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
         <input type="text" class="text" style="width:50px;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         name="amount_<?=$x?>" id="amount_<?=$x?>" <?=$rdlo?>
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],10)?>">
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:200px;<?if((int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>"
         name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
         onmousedown="markfield(this,0)"
         onfocus="addSelStyle(this);"
         onblur="markfield(this,1);removeSelStyle(this);">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if($posdata[$x]["item_type"] == "item")
                  $itemsts = getItemStorehouses($CON, $headdata["stk_shopid"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["stk_shopid"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     if($itemstid == $headdata["stk_fixedsthid"] || !(int)$headdata["stk_fixedsthid"])
                     {
                        $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
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
         <?php
         if(!(int)$headdata["stk_negative"])
         {  ?>
            <div style="<?if(!(int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$x?>">
               <?php
               $btnicon = "arrow";
               $btnname = "Lotes";
               $btnclas = "postnav";
                  
               if(posHasItemChargeData($CON, $_REQUEST["id"], "stockchangeup", $x) > 0)
               {
                  $btnicon = "tick-circle-frame";
                  $btnclas = "postnav_save";

                  $charge_amount = posItemChargeDataAmount($CON, $_REQUEST["id"], "stockchangeup", $x);
                  if($charge_amount["tran_amount"] != $posdata[$x]["item_amount"])
                  {
                     $btnicon = "cross-circle-frame";
                     $btnclas = "postnav_del";
                     $_BLOCKFIN_CHARGE = true;
                  }
                  if($charge_amount["tran_amount_used"] > 0.00)
                     $_BLOCKOPEN_CHARGE = true;
               }
               elseif((int)$posdata[$x]["item_charges_act"])
                  $_BLOCKFIN_CHARGE = true;
                  
               printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.php?trantype=stockchangeup&tranid={$_REQUEST["id"]}&tranpos={$x}&itemdata=' +escape($('#item_id_{$x}').val()), 'iframe', 650, 400, 'auto')", "", $btnicon, 200)
               ?>
               <textarea id="item_charges_data_<?=$x?>" name="item_charges_data_<?=$x?>" style="display:none"></textarea>
            </div>
            <?php
         }
         else
         {  ?>
            <div style="<?if(!(int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$x?>">
            <?php
            $btnicon = "arrow";
            $btnname = "Lotes";
            $btnclas = "postnav";

            if(posHasItemChargeDataUsed($CON, $_REQUEST["id"], "stockchangedown", $x) > 0)
            {
               $btnicon = "tick-circle-frame";
               $btnclas = "postnav_save";

               $charge_amount = posItemChargeDataAmountUsed($CON, $_REQUEST["id"], "stockchangedown", $x);
               if($charge_amount["tran_amount"] != $posdata[$x]["item_amount"])
               {
                  $btnicon = "cross-circle-frame";
                  $btnclas = "postnav_del";
                  $_BLOCKFIN_CHARGE = true;
               }
            }
            elseif((int)$posdata[$x]["item_charges_act"])
               $_BLOCKFIN_CHARGE = true;
   
            printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.select.php?trantype=stockchangedown&needamount=' +$('#amount_{$x}').val() +'&tranid={$_REQUEST["id"]}&tranpos={$x}&itemdata=' +escape($('#item_id_{$x}').val()), 'iframe', 750, 400, 'auto')", "", $btnicon, 200)
            ?>
            <textarea id="item_charges_data_<?=$x?>" name="item_charges_data_<?=$x?>" style="display:none"></textarea>
            </div>
            <?php
         }
         ?>
      </td>
      <td class="content_row" align="right" style="display:none">
         <input type="text" class="text" style="width:75px;text-align:right" <?=$rdlo?>
         name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right" valign="top" style="display:none">
         <input type="text" class="text" style="width:40px;text-align:right" <?=$rdlo?>
         name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_taxes_perc"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right" style="<?if(!(int)$headdata["stk_isventainterna"] && $headdata["stk_issueid"] != 3 && $headdata["stk_issueid"] != 4) echo "display:none"?>">
         <input type="text" class="text" style="width:90px;text-align:right" <?=$rdlo?>
         name="item_sellprice_brutto_<?=$x?>" id="item_sellprice_brutto_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_brutto"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right"  style="<?if(!(int)$headdata["stk_isventainterna"] && $headdata["stk_issueid"] != 3 && $headdata["stk_issueid"] != 4) echo "display:none"?>">
         <nobr>
         <input type="text" class="text" readonly tabindex="-1"
         style="width:90px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_brutto"] * $posdata[$x]["item_amount"])?>">
         </nobr>
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
<?php
if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
   <colgroup>
      <col>
      <col width="55">
      <col width="90">
   </colgroup>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_clear" colspan="2">SUBTOTAL</td>
      <td class="content_row_clear" align="right">
         <input type="text" class="text" style="width:90px;text-align:right" readonly
         value="<?=printPrice($headdata["stk_total_netto"] + $headdata["stk_discount_amount_netto"])?>">
      </td>
   </tr>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_clear" colspan="2">
         <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear">DESCUENTOS</td>
            <td class="content_row_clear" width="140">
               <nobr>
               <input type="text" class="text" style="text-align:center;width:90px;"
               id="req_discount_perc" name="stk_discount_perc"
               value="<?=printPrice($headdata["stk_discount_perc"],2)?>" <?=$rdlo?>> %
               </nobr>
            </td>
            <td class="content_row_clear" align="right" width="1">
               <nobr>
               <input type="text" class="text" style="text-align:center;width:100px;"
               id="req_discount_amt" name="stk_discount_amt"
               value="<?=printPrice($headdata["stk_discount_amt"],2)?>" <?=$rdlo?>> $
               </nobr>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row_clear" align="right">
         <input type="text" class="text" style="width:90px;text-align:right" readonly
         value="<?=printPrice($headdata["stk_discount_amount_netto"])?>">
      </td>
   </tr>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_totals content_rowl" colspan="2">TOTAL</td>
      <td class="content_row_totals content_row" align="right">
         <input type="text" class="text" style="width:90px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
         value="<?=printPrice($headdata["stk_total_netto"])?>">
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
?>
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
         printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/prod_stockchanges_repuestos/data.stockchanges.pdf.php?id={$_REQUEST["id"]}'", "document-pdf");
         ?>
      </td>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&hash={$headdata["stk_num"]}.stockchanges.xls&name=Ajuste-{$headdata["stk_num"]}.xls&path=../../../docs.print/'", "document-excel");
         ?>
      </td>
      <?php
   }
   elseif((int)$headdata["stk_status"] == 0)
   {  ?>
      <td align="right" width="140">
         <?php
         printButton("Recuperar", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.revertDel.value='1';document.form_shppos.onsubmit='';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');" ?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
