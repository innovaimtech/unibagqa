<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
if($_REQUEST["loadStockDate"] != "")
{
   $_REQUEST["loadStockDate"] = trim($_REQUEST["loadStockDate"]);
   $_REQUEST["loadStockDate"] = explode(".", $_REQUEST["loadStockDate"]);
   $_REQUEST["loadStockDate"] = (int)mktime(15,0,0,$_REQUEST["loadStockDate"][1],$_REQUEST["loadStockDate"][0],$_REQUEST["loadStockDate"][2]);
   $sql_year_init    = (int)date('Y', 2011);
   $sql_month_init   = (int)date('m', 1);
   $sql_day_init     = (int)date('d', 1);
   $sql_year_end     = (int)date('Y', $_REQUEST["loadStockDate"]);
   $sql_month_end    = (int)date('m', $_REQUEST["loadStockDate"]);
   $sql_day_end      = (int)date('d', $_REQUEST["loadStockDate"]);

   //----------------------------------------------------------------------------------
   $stop = false;
   for($xmonth = $sql_month_init, $xyear = $sql_year_init; $stop == false; $xmonth++)
   {
      if($xmonth == 13)
      {
         $xmonth = 1;
         $xyear++;
      }

      $xsql_date_arr[$xyear][$xmonth]["INIT"]   = 0;
      $xsql_date_arr[$xyear][$xmonth]["END"]    = 0;
      
      if($xmonth == $sql_month_init && $xyear == $sql_year_init)
      {
         $xsql_date_arr[$xyear][$xmonth]["INIT"] = $sql_day_init;
      }
      if($xmonth == $sql_month_end && $xyear == $sql_year_end)
      {
         $xsql_date_arr[$xyear][$xmonth]["END"] = $sql_day_end;
      }
      if($xyear > $sql_year_end || ($xyear == $sql_year_end && $xmonth == $sql_month_end))
         $stop = true;
   }
   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts
            where
            id  = {$_REQUEST["id"]}";
   $stc = $CON->select($sql);
   $stc = $stc[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.item_number_prod, t2.item_title, t2.id 'itemid', t3.unit_name, t4.item_code
            from stockcounts_lists_items t1
            LEFT OUTER JOIN item         t2 ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units   t3 ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_suppliers t4 ON ( t1.item_id = t4.item_id and t4.item_supp_act = 1 )
            where
            t1.stc_id        = {$_REQUEST["id"]} and
            t1.stc_lst_posid = {$_REQUEST["lstpos"]}
            order by t1.stc_lst_posid, t1.item_pos";
   $stclistitems = $CON->select($sql);

   foreach($stclistitems AS $stclistitem)
   {
      $_sesmodulename = "stock_cards";
      $_SESSION["statsstockcards"]["sql_company"]        = $stc["stc_companyid"];
      $_SESSION["stock_cards"]["sql_company"]            = $stc["stc_companyid"];
      $_SESSION["STATS"]["statsstockcards"]["_SQLDATE"]  = $xsql_date_arr;
      $_SESSION["STATS"]["stock_cards"]["_SQLDATE"]      = $xsql_date_arr;
      $_REQUEST["showItem"]                              = $stclistitem["item_id"];
      $details = doc_createStatsItemStockCard($CON);
      $count   = count($details);
      $stockcc = (float)getPrice($details[($count -1)]["SALDO"],10);

      $max_price = 0.00;
      $max_docno = "";
      $max_docda = 0;

      if((int)$stc["stc_repo_mode"])
      {
         $searchyear = date('Y', $_REQUEST["loadStockDate"]);
         $searchinit = mktime(0, 0, 0, 1, 1, $searchyear);
         $searchend  = mktime(23, 59, 59, 12, 31, $searchyear);

         $itemsup    = getItemSuppliers($CON, $stclistitem["item_id"], "item");
         $itemsup    = $itemsup[0];
         $supptax    = getSupplierTaxes($CON, $itemsup["supplier_id"]);

         if((int)$supptax)
         {
            $sql = " select t1.invc_docnumber, t1.invc_date, (t3.item_costprice_netto_dsc2 / t3.item_amount) 'year_max_buyprice'
                     from invoices_buy t1
                     INNER JOIN invoices_buy_parts t2       ON t1.id = t2.part_invc_id
                     INNER JOIN invoices_buy_parts_items t3 ON t2.part_invc_id = t3.invc_id and t2.id = t3.part_id
                     where
                     t1.invc_date      between {$searchinit} and {$searchend} and
                     t1.invc_status    > 1 and
                     t1.invc_status    < 4 and
                     t3.item_id        = {$stclistitem["item_id"]} and
                     t3.item_type      = 'item'
                     order by 3 desc
                     LIMIT 0,1";
            $maxinvc = $CON->select($sql);
            $max_price = (float)$maxinvc[0]["year_max_buyprice"];
            $max_docno = $maxinvc[0]["invc_docnumber"];
            $max_docda = $maxinvc[0]["invc_date"];
            if(!$max_price)
            {
               $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $stclistitem["item_id"], "item", $_REQUEST["loadStockDate"]);
               $max_price     = $lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"];
               $max_docno     = $lastbuyprice["invc_docnumber"];
               $max_docda     = $lastbuyprice["invc_date"];
            }
         }
         else
         {
            $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $stclistitem["item_id"], "item", $_REQUEST["loadStockDate"]);
            $max_price     = $lastbuyprice["item_costprice_import_total"] / $lastbuyprice["item_amount"];
            $max_docno     = $lastbuyprice["invc_docnumber"];
            $max_docda     = $lastbuyprice["invc_date"];
         }
      }
      else
      {
         $itemsup    = getItemSuppliers($CON, $stclistitem["item_id"], "item");
         $itemsup    = $itemsup[0];
         $supptax    = getSupplierTaxes($CON, $itemsup["supplier_id"]);

         if((int)$supptax)
         {
            $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $stclistitem["item_id"], "item", $_REQUEST["loadStockDate"]);
            $max_price     = $lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"];
            $max_docno     = $lastbuyprice["invc_docnumber"];
            $max_docda     = $lastbuyprice["invc_date"];
         }
         else
         {
            $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $stclistitem["item_id"], "item", $_REQUEST["loadStockDate"]);
            $max_price     = $lastbuyprice["item_costprice_import_total"] / $lastbuyprice["item_amount"];
            $max_docno     = $lastbuyprice["invc_docnumber"];
            $max_docda     = $lastbuyprice["invc_date"];
         }
      }

      $item_costprice_avg_netto  = (float)round($max_price,0);
      $item_costprice_docnumber  = trim(addslashes($max_docno));
      $item_costprice_docdate    = (int)$max_docda;

      if($item_costprice_avg_netto == 0.00)
      {
         $cost = getSupplierFinalCostNetto($CON, 0, $stclistitem["item_id"]);
         $item_costprice_avg_netto = (float)$cost;
      }

      //----------------------------------------------------------------------------------
      $sql = " update stockcounts_lists_items
               set
               item_amount_stock          = {$stockcc},
               item_costprice_avg_netto   = {$item_costprice_avg_netto},
               item_costprice_docnumber   = '{$item_costprice_docnumber}', 
               item_costprice_docdate     = {$item_costprice_docdate}
               where
               item_id                    = {$stclistitem["item_id"]} and
               item_pos                   = {$stclistitem["item_pos"]} and
               stc_id                     = {$_REQUEST["id"]} and
               stc_lst_posid              = {$_REQUEST["lstpos"]}";
      $res = $CON->no_result($sql);
   }
   $_SESSION["JSEXEC"] .= ";submitForm(document.form_sthitems);";
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["revert"])
{
   //----------------------------------------------------------------------------------
   $sql = " select distinct id
            from stockchanges
            where
            stockchange_stc_id   = {$_REQUEST["id"]} and
            stockchange_lst_pos  = {$_REQUEST["lstpos"]} and
            stk_status           > 1";
   $chgids = $CON->select($sql);
   foreach($chgids AS $chgid)
   {
      delStockChange($CON, $chgid["id"]);
      $sql = " delete from stockchanges
               where
               id = {$chgid["id"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " update stockcounts_lists
            set
            lst_status = 1
            where
            stc_id  = {$_REQUEST["id"]} and
            lst_pos = {$_REQUEST["lstpos"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " update stockcounts
            set
            stc_status = 2
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   if($_REQUEST["lst_date"] != "")
   {
      $_REQUEST["lst_date"]     = explode(".", $_REQUEST["lst_date"]);
      $_REQUEST["lst_date"]     = (int)mktime(0, 0, 0, $_REQUEST["lst_date"][1], $_REQUEST["lst_date"][0], $_REQUEST["lst_date"][2]);
      $_REQUEST["lst_pppupdate"] = (int)$_REQUEST["lst_pppupdate"];
      $sql = " update stockcounts_lists
               set
               lst_date       = {$_REQUEST["lst_date"]},
               lst_pppupdate  = {$_REQUEST["lst_pppupdate"]}
               where
               stc_id   = {$_REQUEST["id"]} and
               lst_pos  = {$_REQUEST["lstpos"]}";
      $CON->no_result($sql);
   }

   $savests    = true;
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_pos_") !== false && strpos($reqkey, "item_pos_") == 0)
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         $existing_pos  = (int)$_REQUEST["item_pos_{$idx}"];
         $_REQUEST["item_comment_{$idx}"] = trim(addslashes($_REQUEST["item_comment_{$idx}"]));
         $_REQUEST["item_costprice_avg_netto_{$idx}"] = getPrice($_REQUEST["item_costprice_avg_netto_{$idx}"],8);

         //----------------------------------------------------------------------------------
         $sql = " select item_amount_stock, item_id
                  from stockcounts_lists_items
                  where
                  stc_id            = {$_REQUEST["id"]} and
                  stc_lst_posid     = {$_REQUEST["lstpos"]} and
                  item_pos          = {$existing_pos}";
         $currdata = $CON->select($sql);
         $stid = $currdata[0]["item_id"];
         $stcc = (float)$currdata[0]["item_amount_stock"];

         //----------------------------------------------------------------------------------
         if($_REQUEST["item_amount_count_{$idx}"] != "")
         {
            $_REQUEST["item_amount_count_{$idx}"] = getPrice($_REQUEST["item_amount_count_{$idx}"],10);
            $finished         = 1;
            $item_amount_book = $_REQUEST["item_amount_count_{$idx}"] - $stcc;
         }
         else
         {
            $_REQUEST["item_amount_count_{$idx}"] = "NULL";
            $finished         = 0;
            $item_amount_book = 0;
         }

         //----------------------------------------------------------------------------------
         $item_alternative_id    = 0;
         $item_alternative_type  = "";
         if($_REQUEST["item_alternative_id_{$idx}"] != "")
         {
            $altarr        = explode("#", $_REQUEST["item_alternative_id_{$idx}"]);
            $alt_item_id   = $altarr[0];
            $alt_item_type = $altarr[1];
            if($alt_item_type == "itemlist")
            {
               $item_alternative_id    = $alt_item_id;
               $item_alternative_type  = $alt_item_type;
               if($finished)
               {
                  $posamt = 0;
                  $itemlistpos = getItemListContent($CON, $item_alternative_id);
                  foreach($itemlistpos AS $itemlistrow)
                     $posamt += $itemlistrow["item_amount"];
               
                  $_REQUEST["item_amount_count_{$idx}"] = $_REQUEST["item_amount_count_{$idx}"] * $posamt;
                  $item_amount_book = $_REQUEST["item_amount_count_{$idx}"] - $stcc;
               }
            }
         }

         //----------------------------------------------------------------------------------
         $sql = " update stockcounts_lists_items
                  set
                  item_amount_count          = {$_REQUEST["item_amount_count_{$idx}"]},
                  item_finished              = {$finished},
                  item_comment               = '{$_REQUEST["item_comment_{$idx}"]}',
                  item_amount_book           = {$item_amount_book},
                  item_alternative_id        = {$item_alternative_id},
                  item_alternative_type      = '{$item_alternative_type}',
                  item_costprice_avg_netto   = {$_REQUEST["item_costprice_avg_netto_{$idx}"]}
                  where
                  item_pos                   = {$existing_pos} and
                  stc_id                     = {$_REQUEST["id"]} and
                  stc_lst_posid              = {$_REQUEST["lstpos"]}";
         $res = $CON->no_result($sql);

         if(!$res)
            $savests = false;
      }
   }
   $savemsg = getSaveMessage($savests);

   if($_REQUEST["lst_status"] == "2")
   {
      $currtme = time();
      
      $sql = " update stockcounts_lists
               set
               lst_status  = 2,
               lst_upddat  = {$currtme},
               lst_updusr  = {$_SESSION["user_id"]}
               where
               stc_id   = {$_REQUEST["id"]} and
               lst_pos  = {$_REQUEST["lstpos"]}";
      $CON->no_result($sql);

      createStockChangesFromStockcount($CON, $_REQUEST["id"], $_REQUEST["lstpos"]);

      //----------------------------------------------------------------------------------
      $finished = true;
      $sql = " select lst_status
               from stockcounts_lists
               where
               stc_id = {$_REQUEST["id"]}";
      $alllists = $CON->select($sql);

      foreach($alllists AS $alllist)
         if($alllist["lst_status"] != 2)
            $finished = false;

      if($finished)
      {
         $sql = " update stockcounts
                  set
                  stc_status = 3
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
      ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=items&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from stockcounts
         where
         id  = {$_REQUEST["id"]}";
$stc = $CON->select($sql);
$stc = $stc[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from stockcounts_lists t1
         where
         t1.stc_id  = {$_REQUEST["id"]} and
         t1.lst_pos = {$_REQUEST["lstpos"]}";
$stclists = $CON->select($sql);
$stclists = $stclists[0];

if($stclists["lst_status"] > 1)
{
   $rdlo = " readonly ";
   $dabl = " disabled ";
}

//----------------------------------------------------------------------------------
$sql = " select distinct t1.*, t2.item_number_prod, t2.item_title, t2.id 'itemid', t3.unit_name, t4.item_code
         from stockcounts_lists_items t1
         LEFT OUTER JOIN item         t2 ON t1.item_id = t2.id
         LEFT OUTER JOIN item_units   t3 ON t2.item_unit = t3.id
         LEFT OUTER JOIN item_suppliers t4 ON ( t1.item_id = t4.item_id and t4.item_supp_act = 1 )
         LEFT OUTER JOIN item_barcodes tx    ON ( t2.id = tx.item_id AND tx.item_type = 'item')
         where
         t1.stc_id        = {$_REQUEST["id"]} and
         t1.stc_lst_posid = {$_REQUEST["lstpos"]} ";
if($_REQUEST["sql_item"] != "")
{
   $_REQUEST["sql_item"] = trim(addslashes($_REQUEST["sql_item"]));

   $sql .= " and ( t2.item_title        like '%{$_REQUEST["sql_item"]}%' or
                   t2.item_number       like '%{$_REQUEST["sql_item"]}%' or
                   t2.item_number_prod  like '%{$_REQUEST["sql_item"]}%' or
                   tx.item_barcode      = '{$_REQUEST["sql_item"]}' ) ";
}
$sql .= " order by t1.stc_lst_posid, t1.item_pos";
$stclistitems = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<?php
if($rdlo == "")
{  ?>
   <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
   <input type="hidden" name="lstpos" value="<?=$_REQUEST["lstpos"]?>">
   <?=Nifty_printH("box2", "1110")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="100">
      <col>
      <col width="100">
      <col width="300">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
   </tr>
   <tr>
      <td class="content_rowl">Artículo</td>
      <td class="content_row" colspan="2">
         <input type="text" style="width:650px" id="sql_item" name="sql_item" class="text"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_item"])?>">
      </td>
      <td class="content_row" align="right">
         <?php
         printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
         $_SESSION["_SUBMITBTN"] = 1;
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_sthitems" <?php if($rdlo != "") echo "onsubmit='return false'"?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="lstpos" value="<?=$_REQUEST["lstpos"]?>">
<input type="hidden" name="lst_status" value="">
<input type="hidden" name="loadStockDate" value="">
<?=Nifty_printH("box1", "1110")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="70">
   <col>
   <col width="70">
   <col width="70">
   <col width="70">
   <col width="80">
   <col width="50">
   <col width="50">
   <col width="50">
   <col width="50">
   
   <col>
   <col>
   <col width="105">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="13">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" style="margin:0px;padding:0px">Recuento inventario</td>
         <td class="content_tbl_header" style="margin:0px;padding:0px" width="60" align="right">
            <?php
            if((int)$stclists["lst_date"] && $rdlo == "")
            {  ?>
               <input type="button" class="button" style="background-color:#BBFFB6" value="Calcular Stock segun fecha"
               onclick="if(askDel('')){document.form_sthitems.subexec.value='';document.form_sthitems.loadStockDate.value=$('#lst_date').val();submitForm(document.form_sthitems);}">
               <?php
            }
            ?>
         </td>
         <td class="content_tbl_header" style="margin:0px;padding:0px;display:none" width="200" align="right">
            <nobr><input type="checkbox" value="1" class="checkbox" name="lst_pppupdate"
            <?php if((int)$stclists["lst_pppupdate"]) echo "checked"?>> Sobreescribir PPP existente</nobr>
         </td>
         <td class="content_tbl_header" style="margin:0px;padding:0px;vertical-align:bottom" width="70" align="center">Fecha</td>
         <td class="content_tbl_header" style="margin:0px;padding:0px" width="130">
            <input type="text" style="width:80px" id="lst_date" name="lst_date" <?=$rdlo?>
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if((int)$stclists["lst_date"]) echo date('d.m.Y', $stclists["lst_date"])?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os" valign="top">Número</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Artículo</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Unidad</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Codigo Prov.</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Stock<br>actual</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Stock<br>real</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Diferencia</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Stock<br>res.</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Valorización</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Total</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Factura</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Fecha/Fact.</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Observaciones</td>
</tr>
<?php
for($x = 0; $x < count($stclistitems) && $stclistitems != false; $x++)
{
   $unitdesc         = getItemUnitDesc($CON, $stclistitems[$x]["item_id"], "item");
   $itemalternatives = getItemOrderAlternativeUnits($CON, $stclistitems[$x]["item_id"], "item", $stc["stc_shopid"], 0);

   //----------------------------------------------------------------------------------
   $posamt = 0;
   if($stclistitems[$x]["item_alternative_id"] > 0 && $stclistitems[$x]["item_alternative_type"] == "itemlist")
   {
      $itemlistpos = getItemListContent($CON, $stclistitems[$x]["item_alternative_id"]);
      foreach($itemlistpos AS $itemlistrow)
         $posamt += $itemlistrow["item_amount"];
   }

   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_count_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_comment_{$x}";

   $shareamount = getStockShared($CON, $stclistitems[$x]["item_id"], "item", $stc["stc_shopid"]);
   ?>
   <tr bgcolor="<?if($stclistitems[$x]["item_finished"] == 1) echo "#E1FFD6"; else echo getRowColor($x);?>"
   onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os">
         <input type="hidden" name="item_pos_<?=$x?>" value="<?=$stclistitems[$x]["item_pos"]?>">
         <?=$stclistitems[$x]["item_number_prod"]?>
      </td>
      <td class="content_row_os"><?=$stclistitems[$x]["item_title"]?></td>
      <td class="content_row_os">
         <?php
         if(count($itemalternatives) && $itemalternatives != false)
         {  ?>
            <select name="item_alternative_id_<?=$x?>" id="item_alternative_id_<?=$x?>" class="text" style="width:90px" <?=$dabl?>>
               <option value="<?=$stclistitems[$x]["item_id"]?>#item"><?=$unitdesc?></option>
               <?php
               for($y = 0; $y < count($itemalternatives) && $itemalternatives != false; $y++)
               {  ?>
                  <option value="<?=$itemalternatives[$y]["item_id"]?>#<?=$itemalternatives[$y]["item_type"]?>"
                  <?php if($stclistitems[$x]["item_alternative_id"] == $itemalternatives[$y]["item_id"] &&
                        $stclistitems[$x]["item_alternative_type"] == "itemlist") echo "selected"?>>
                     <?=$itemalternatives[$y]["unit_name"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
            <?php
         }
         else
            echo $unitdesc;
         ?>
      </td>
      <td class="content_row_os"><?=$stclistitems[$x]["item_code"]?>&nbsp;</td>
      <td class="content_row_os" align="center">
         <?php
         if($stclistitems[$x]["item_alternative_id"] > 0 && $stclistitems[$x]["item_alternative_type"] == "itemlist")
            echo printPrice($stclistitems[$x]["item_amount_stock"] / $posamt, 10);
         else
            echo printPrice($stclistitems[$x]["item_amount_stock"], 10);
         ?>
      </td>
      <td class="content_row_os" align="center">
         <input type="text" class="text" style="width:60px;text-align:center" name="item_amount_count_<?=$x?>" id="item_amount_count_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         value="<?php
         if($stclistitems[$x]["item_amount_count"] != "")
         {
            if($stclistitems[$x]["item_alternative_id"] > 0 && $stclistitems[$x]["item_alternative_type"] == "itemlist")
               echo printPrice($stclistitems[$x]["item_amount_count"] / $posamt, 10);
            else
               echo printPrice($stclistitems[$x]["item_amount_count"], 10);
         }
         ?>">
      </td>
      <td class="content_row_os" align="center" style="background-color:<?php
      if($stclistitems[$x]["item_amount_book"] < 0) echo "#FFD6D8"?>">
         <?php
         if($stclistitems[$x]["item_amount_book"] > 0)
            echo "+";
         if($stclistitems[$x]["item_alternative_id"] > 0 && $stclistitems[$x]["item_alternative_type"] == "itemlist")
            echo printPrice($stclistitems[$x]["item_amount_book"] / $posamt, 10);
         else
            echo printPrice($stclistitems[$x]["item_amount_book"], 10);
         ?> 
      </td>
      <td class="content_row_os" align="center"><?=printPrice($shareamount,10)?></td>
      <td class="content_row_os" align="center">
         <input type="text" class="text" style="width:60px;text-align:center" name="item_costprice_avg_netto_<?=$x?>" id="item_costprice_avg_netto_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         value="<?=printPrice($stclistitems[$x]["item_costprice_avg_netto"],8)?>">
      </td>
      <td class="content_row_os" align="center">
         <nobr><?=printPrice(($stclistitems[$x]["item_amount_stock"] + $stclistitems[$x]["item_amount_book"])*$stclistitems[$x]["item_costprice_avg_netto"],0)?></nobr>
      </td>
      <td class="content_row_os"><?=$stclistitems[$x]["item_costprice_docnumber"]?>&nbsp;</td>
      <td class="content_row_os"><?if((int)$stclistitems[$x]["item_costprice_docdate"]) echo date('d.m.Y', $stclistitems[$x]["item_costprice_docdate"])?>&nbsp;</td>
      <td class="content_row_os">
         <input type="text" class="text" style="width:100px" name="item_comment_<?=$x?>" id="item_comment_<?=$x?>" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$stclistitems[$x]["item_comment"]?>">
      </td>
   </tr>
   <?php
   $gestotal += round(($stclistitems[$x]["item_amount_stock"] + $stclistitems[$x]["item_amount_book"])*$stclistitems[$x]["item_costprice_avg_netto"],0);
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="13" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr>
      <td class="content_row_totals" colspan="9">TOTAL</td>
      <td class="content_row_totals" align="center">
         <nobr><?=printPrice($gestotal,0)?></nobr>
      </td>
      <td class="content_row_totals" align="center">&nbsp;</td>
      <td class="content_row_totals" align="center">&nbsp;</td>
      <td class="content_row_totals" align="center">&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<div style="position:fixed;top:65px;left:1120px">
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="3" width="130">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=items&id={$_REQUEST["id"]} ", "", "arrow-180");
      ?>
   </td>
</tr>
<tr>
   <td  width="130">
      <?php
      if($rdlo == "")
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_sthitems)", "disk-black");
      ?>
   </td>
</tr>
<?php
if($stclists["lst_status"] > 1)
{  ?>
   <tr>
      <td width="130">
         <?php
         printButton("Resultados", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&printStatRep=1&lstpos={$_REQUEST["lstpos"]}&lst_pos={$_REQUEST["lstpos"]}&execPDF=1", "", "document-pdf");
         ?>
      </td>
   </tr>
   <tr>
      <td width="130">
         <?php
         printButton("Resultados", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&printStatRep=1&lstpos={$_REQUEST["lstpos"]}&lst_pos={$_REQUEST["lstpos"]}&execExcel=1", "", "document-excel");
         ?>
      </td>
   </tr>
   <tr>
      <td width="130">
         <?php
         printButton("Result. con Stock", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&printStatRep=1&lstpos={$_REQUEST["lstpos"]}&lst_pos={$_REQUEST["lstpos"]}&execPDF=1&sql_stockmode=1", "", "document-pdf");
         ?>
      </td>
   </tr>
   <tr>
      <td width="130">
         <?php
         printButton("Result. con Stock", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&printStatRep=1&lstpos={$_REQUEST["lstpos"]}&lst_pos={$_REQUEST["lstpos"]}&execExcel=1&sql_stockmode=1", "", "document-excel");
         ?>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <td align="right" width="130">
      <?php
      if($rdlo == "")
         printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_sthitems.lst_status.value = '2';document.form_sthitems.submit(); }", "tick-circle-frame");
      else
      {
         $sql = " select distinct id
                  from stockchanges
                  where
                  stockchange_stc_id   = {$_REQUEST["id"]} and
                  stockchange_lst_pos  = {$_REQUEST["lstpos"]} and
                  stk_status           > 1";
         $chgids = $CON->select($sql);

         if(count($chgids) && $chgids != false)
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){location.href='index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&lstpos={$_REQUEST["lstpos"]}&revert=1'}", "arrow-circle-045-left");
      }
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</div>
<?php
if((int)$_REQUEST["printStatRep"])
{  ?>
   <div style="display:none">
   <?php
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from stockcounts t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   unset($_SESSION["STATS"]["stock_stockcounts"]);

   $_REQUEST["orderBy"] = "1";
      if($headdata["stc_order_mode"] == 2)
         $_REQUEST["orderBy"] = "2";
   $_SESSION["stock_stockcounts"]["orderSort"] = "asc";
   $_REQUEST["orderSort"]     = "asc";
   $_REQUEST["subexec"]       = "search";
   $_REQUEST["printpdf"]      = (int)$_REQUEST["execPDF"];
   $_REQUEST["printxls"]      = (int)$_REQUEST["execExcel"];
   $_REQUEST["sql_company"]   = $stc["stc_companyid"];
   $_REQUEST["sql_stcmode"]   = 1;
   $_REQUEST["sql_stcnum"]    = $stc["stc_num"];
   $_REQUEST["sql_dspmode"]   = 1;
   require_once("./libs/modules/stats/stock/item.stockcounts.php");
   ?>
   </div>
   <?php
}
?>