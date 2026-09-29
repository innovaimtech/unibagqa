<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xshipments"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xshipments"]["fullcust"] = "";

$_sesmodulename = "shipments";
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["shp_supporder_based"] = (int)$_REQUEST["shp_supporder_based"];
   $_REQUEST["supplier_id_0"]       = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]          = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]             = (int)$_REQUEST["shop_id"];
   $_REQUEST["shp_supporder_id"]    = (int)$_REQUEST["shp_supporder_id"];
   $_REQUEST["shp_delivery_date"]   = trim($_REQUEST["shp_delivery_date"]);
   $_REQUEST["shp_delivery_date"]   = explode(".", $_REQUEST["shp_delivery_date"]);
   $_REQUEST["shp_delivery_date"]   = (int)mktime(0, 0, 0, $_REQUEST["shp_delivery_date"][1], $_REQUEST["shp_delivery_date"][0], $_REQUEST["shp_delivery_date"][2]);
   $_REQUEST["shp_supplier_docnum"] = trim(addslashes($_REQUEST["shp_supplier_docnum"]));

   //----------------------------------------------------------------------------------
   $shp_stockchange = 1;
   $isremote = getShops($CON, false, false, 0, $_REQUEST["shop_id"]);
   $isremote = $isremote[0]["shop_isremote"];
   if($isremote)
      $shp_stockchange = 0;

   //----------------------------------------------------------------------------------
   if($_REQUEST["shp_supporder_based"])
   {
      $sql = " select sord_supplier_id, sord_taxes
               from supplier_order
               where
               id = {$_REQUEST["shp_supporder_id"]}";
      $suppid = $CON->select($sql);
      $_REQUEST["supplier_id_0"] = (int)$suppid[0]["sord_supplier_id"];
      $supptax = (int)$suppid[0]["sord_taxes"];
   }
   else
   {
      $supptax = getSupplierTaxes($CON, $_REQUEST["supplier_id_0"]);
      $_REQUEST["shp_supporder_id"] = 0;
   }
   $supptax = (int)$supptax;

   //----------------------------------------------------------------------------------
   $shp_num = createTransactionNumber($CON, $_REQUEST["company_id"], "shipment");
   
   $sql = " insert into shipment
            (shp_num, shp_supplier_id, shp_supplier_docnum, shp_delivery_date, shp_supporder_based,
             shp_supporder_id, shp_company_id, shp_shop_id, shp_taxes, shp_crtdat, shp_crtusr, shp_stockchange)
            VALUES
            ('{$shp_num}', {$_REQUEST["supplier_id_0"]}, '{$_REQUEST["shp_supplier_docnum"]}', {$_REQUEST["shp_delivery_date"]},
              {$_REQUEST["shp_supporder_based"]}, {$_REQUEST["shp_supporder_id"]}, {$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]},
              {$supptax}, {$currtme}, {$_SESSION["user_id"]}, {$shp_stockchange})";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($res)
   {
      $sql = " select MAX(id) 'id'
               from shipment
               where
               shp_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["id"] = (int)$thisid[0]["id"];

      //----------------------------------------------------------------------------------
      if($_REQUEST["shp_supporder_based"] && $_REQUEST["shp_supporder_id"])
      {
         $sql = " select *
                  from supplier_order_items
                  where
                  sord_id = {$_REQUEST["shp_supporder_id"]}
                  order by item_pos asc";
         $ordpos = $CON->select($sql);

         for($x = 0; $x < count($ordpos) && $ordpos != false; $x++)
         {
            $sql_item_amount = sprintf("%.2f", $ordpos[$x]["item_amount"] - $ordpos[$x]["item_amount_shipped"]);
            if($sql_item_amount < 0)
               $sql_item_amount = 0.00;

            $ordpos[$x]["item_desc"] = addslashes($ordpos[$x]["item_desc"]);
            $item_charges_act = itemHasChargeAct($CON, $ordpos[$x]["item_id"], $ordpos[$x]["item_type"]);

            $item_amt            = $ordpos[$x]["item_amount"];
            $netto_dsc           = $ordpos[$x]["item_costprice_netto_dsc"] / $ordpos[$x]["item_amount"];

            if(round($netto_dsc,2) == round($ordpos[$x]["item_costprice_netto"], 2))
               $netto_dsc  = 0;
            else
            {
               $netto_dif  = $ordpos[$x]["item_costprice_netto"] - $netto_dsc;
               $netto_dsc  = (float)$netto_dif / $ordpos[$x]["item_costprice_netto"] * 100;
            }

            /*
            $item_subitem_id     = 0;
            $item_subitem_amount = 0;
            $subitem             = getItemSubItem($CON, $ordpos[$x]["item_id"]);
            if((int)$subitem["prod_item_id"])
            {
               $item_subitem_id     = $subitem["prod_item_id"];
               $item_subitem_amount = $subitem["prod_item_amount"];
            }
            */
            
            $sql = " insert into shipment_items
                     (shipment_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_supporder_pos, item_desc,
                      item_charges_act, item_discount, item_subitem_id, item_subitem_amount)
                     VALUES
                     ({$_REQUEST["id"]}, {$ordpos[$x]["item_id"]}, {$ordpos[$x]["item_pos"]}, {$sql_item_amount},
                      '{$ordpos[$x]["item_type"]}', {$ordpos[$x]["item_costprice_brutto"]}, {$ordpos[$x]["item_costprice_taxes_perc"]},
                      {$ordpos[$x]["item_costprice_netto"]}, {$ordpos[$x]["item_costprice_taxes"]}, {$ordpos[$x]["item_pos"]},
                      '{$ordpos[$x]["item_desc"]}', {$item_charges_act}, {$netto_dsc}, {$ordpos[$x]["item_subitem_id"]}, {$ordpos[$x]["item_subitem_amount"]})";
            $CON->no_result($sql);
         }
      }

      ?>
      <script language="Javascript">
         location.href = 'index.php?mid=10072&exec=editshipment&subexec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from shipment t1
         where
         t1.id = {$_REQUEST["id"]}";
$shipmenttemp = $CON->select($sql);
$suppid  = (int)$shipmenttemp[0]["shp_supplier_id"];
$sordid  = (int)$shipmenttemp[0]["shp_supporder_id"];
$supptax = (int)$shipmenttemp[0]["shp_taxes"];
$shp_stockchange = (int)$shipmenttemp[0]["shp_stockchange"];

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "xf_search_") !== false && strpos($reqkey, "xf_search_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
    
         $_REQUEST["item_amount_{$idx}"]         = getPrice($_REQUEST["item_amount_{$idx}"],4);
         $_REQUEST["item_amount_shipped_{$idx}"] = getPrice($_REQUEST["item_amount_shipped_{$idx}"],4);
         $_REQUEST["item_supporder_pos_{$idx}"]  = (int)$_REQUEST["item_supporder_pos_{$idx}"];
         $_REQUEST["item_stid_{$idx}"]           = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_discount_{$idx}"]       = getPrice($_REQUEST["item_discount_{$idx}"],8);

         if(!(int)$shp_stockchange)
            $_REQUEST["item_stid_{$idx}"] = 0;

         //----------------------------------------------------------------------------------
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         //----------------------------------------------------------------------------------
         if($_REQUEST["item_id_{$idx}"] != "")
         {
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];
            $sql_charges_act  = (int)$itemvalues[4];

            if((int)$supptax)
            {
               $sql_costprice = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
               $item_costprice_netto_dsc = round($sql_costprice * $_REQUEST["item_amount_shipped_{$idx}"]);
            }
            else
            {
               $sql_costprice = getPrice($_REQUEST["item_costprice_netto_{$idx}"], 2);
               $item_costprice_netto_dsc = round($sql_costprice * $_REQUEST["item_amount_shipped_{$idx}"],2);
            }
            if($_REQUEST["item_discount_{$idx}"] > 0.00)
            {
               $item_costprice_netto_dsc = $sql_costprice * $_REQUEST["item_amount_shipped_{$idx}"];
               $item_costprice_netto_dsc = $item_costprice_netto_dsc - ($item_costprice_netto_dsc / 100 * $_REQUEST["item_discount_{$idx}"]);
               $item_costprice_netto_dsc = round($item_costprice_netto_dsc, 2);
            }

            //----------------------------------------------------------------------------------
            if((int)$_REQUEST["manual_pos_{$idx}"])
               $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
            else
               $_REQUEST["item_desc_{$idx}"] = "";

            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update shipment_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_amount_shipped        = {$_REQUEST["item_amount_shipped_{$idx}"]},
                        item_costprice_netto       = {$sql_costprice},
                        item_costprice_netto_dsc   = {$item_costprice_netto_dsc},
                        item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                        item_discount              = {$_REQUEST["item_discount_{$idx}"]},
                        item_pos                   = {$poscounter}
                        where
                        shipment_id                = {$_REQUEST["id"]} and
                        item_id                    = {$existing_id} and
                        item_pos                   = {$existing_pos}";
               $CON->no_result($sql);

               renameItemChargePos($CON, $_REQUEST["id"], "shipment", $existing_pos, $poscounter);
               
               updateItemChargeData($CON, $_REQUEST["id"], "shipment", $existing_id, $sql_type, $poscounter,
                                    $shipmenttemp[0]["shp_company_id"], $shipmenttemp[0]["shp_shop_id"], $_REQUEST["item_charges_data_{$idx}"]);
            }
            else
            {
               $item_subitem_id     = 0;
               $item_subitem_amount = 0;
               $subitem             = getItemSubItem($CON, $sql_id);
               if((int)$subitem["prod_item_id"])
               {
                  $item_subitem_id     = $subitem["prod_item_id"];
                  $item_subitem_amount = $subitem["prod_item_amount"];
               }
            
               $sql = " insert into shipment_items
                        (shipment_id, item_id, item_pos, item_amount, item_amount_shipped, item_type, 
                         item_costprice_netto, item_costprice_netto_dsc, item_supporder_pos, item_st_id,
                         item_desc, item_charges_act, item_discount, item_subitem_id, item_subitem_amount)
                        VALUES
                        ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]},
                         {$_REQUEST["item_amount_shipped_{$idx}"]}, '{$sql_type}', {$sql_costprice}, {$item_costprice_netto_dsc},
                         {$_REQUEST["item_supporder_pos_{$idx}"]},
                         {$_REQUEST["item_stid_{$idx}"]},  '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act},
                         {$_REQUEST["item_discount_{$idx}"]}, {$item_subitem_id}, {$item_subitem_amount})";
               $CON->no_result($sql);

               updateItemChargeData($CON, $_REQUEST["id"], "shipment", $sql_id, $sql_type, $poscounter,
                                    $shipmenttemp[0]["shp_company_id"], $shipmenttemp[0]["shp_shop_id"], $_REQUEST["item_charges_data_{$idx}"]);
            }

            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from shipment_items
                     where
                     shipment_id    = {$_REQUEST["id"]} and
                     item_id        = {$existing_id} and
                     item_pos       = {$existing_pos}";
            $CON->no_result($sql);

            clearItemChargeData($CON, $_REQUEST["id"], "shipment", $existing_pos);
         }
      }
   }
   recalcShipment($CON, $_REQUEST["id"]);

   if($_REQUEST["shp_status"] == "2")
      $ret = bookShipment($CON, $_REQUEST["id"]);

   if($shipmenttemp[0]["shp_status"] == 2 && $_REQUEST["shp_status"] == 1)
   {
      delShipment($CON, $_REQUEST["id"]);
      
      $sql = " update shipment
               set
               shp_status = 1,
               shp_remote_synced = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["shp_annotation"]      = trim(addslashes($_REQUEST["shp_annotation"]));
   $_REQUEST["shp_supplier_docnum"] = trim(addslashes($_REQUEST["shp_supplier_docnum"]));
   $_REQUEST["shp_exchange"]        = getPrice($_REQUEST["shp_exchange"], 6);
   $_REQUEST["shp_delivery_date"]   = explode(".", $_REQUEST["shp_delivery_date"]);
   $_REQUEST["shp_delivery_date"]   = (int)mktime(0, 0, 0, $_REQUEST["shp_delivery_date"][1], $_REQUEST["shp_delivery_date"][0], $_REQUEST["shp_delivery_date"][2]);
   
   $sql = " update shipment
            set
            shp_annotation       = '{$_REQUEST["shp_annotation"]}',
            shp_supplier_docnum  = '{$_REQUEST["shp_supplier_docnum"]}',
            shp_delivery_date    = {$_REQUEST["shp_delivery_date"]},
            shp_updusr           = {$_SESSION["user_id"]},
            shp_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = " select count(*) 'cc'
            from shipment t1
            INNER JOIN shipment t2 ON ( t1.shp_supplier_id = t2.shp_supplier_id and
                                        t1.shp_status > 0 and t2.shp_status > 0 and
                                        t1.shp_supplier_docnum = t2.shp_supplier_docnum)
            where
            t1.id = {$_REQUEST["id"]}";
   $shpdoccheck = $CON->select($sql);
   if((int)$shpdoccheck[0]["cc"] > 1)
   {
      $sql = " update shipment
               set
               shp_supplier_docnum = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      $_SESSION["JSEXEC"] .= ";alert('EL NUMERO DE GUIA YA EXISTE PARA EL PROVEEDOR');";
      
   }
   
   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "edit" && $_REQUEST["clearOrder"] == "1")
{
   $currtme = time();
   $sql = " select shp_supporder_id
            from shipment
            where
            id = {$_REQUEST["id"]}";
   $sordid = $CON->select($sql);
   $sordid = (int)$sordid[0]["shp_supporder_id"];

   if($sordid)
   {
      $sql = " update supplier_order
               set
               sord_order_shipped   = 1,
               sord_status          = 4,
               sord_updusr          = {$_SESSION["user_id"]},
               sord_upddat          = {$currtme}
               where
               id = {$sordid}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["cancelInvoice"] == "1")
{
   $sql = " update shipment
            set
            shp_status = 3
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["openInvoice"] == "1")
{
   $sql = " update shipment
            set
            shp_status = 2
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}


//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t3.sord_number, t3.sord_order_shipped, t4.company_short, t5.shop_name, t1.shp_supplier_id, t2.supp_notes,
                t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname'
         from shipment t1
         INNER JOIN supplier t2              ON t1.shp_supplier_id   = t2.id
         LEFT OUTER JOIN supplier_order t3   ON t1.shp_supporder_id  = t3.id
         LEFT OUTER JOIN company_data t4     ON t1.shp_company_id    = t4.id
         LEFT OUTER JOIN company_shops t5    ON t1.shp_shop_id       = t5.id
         LEFT OUTER JOIN user t6             ON t1.shp_updusr        = t6.id
         LEFT OUTER JOIN user t7             ON t1.shp_crtusr        = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$isremote = getShops($CON, false, false, 0, $headdata["shp_shop_id"]);
$isremote = (int)$isremote[0]["shop_isremote"];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.supp_provinciaid  = t5.id
         where
         t1.id = {$headdata["shp_supplier_id"]}";
$supplier = $CON->select($sql);
$supplier = $supplier[0];

//----------------------------------------------------------------------------------
$posdata = getShipmentPos($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;


if((int)$headdata["shp_status"] > 1)
{
   $rdlo       = "readonly";
   $dabl       = "disabled";
   $rowcount   = count($posdata);
}

if(!(int)$headdata["shp_taxes"])
{
   $inputw    = "50px";
   $inputw2   = "55px";
   $moneystr  = "US&nbsp;";
   $numberlim = "4";
}
else
{
   $inputw    = "70px";
   $inputw2   = "70px";
   $moneystr  = "";
   $numberlim = "2";
}

//----------------------------------------------------------------------------------
if((int)$_SESSION["user_docopen_perm"] || (int)$_REQUEST["user_docopen_perm"])
   $hasdocopeperm = true;
else
   $hasdocopeperm = false;

if($_SESSION["xshipments"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/shipments/searchstorehouses.php?shpid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;
   }

   function detectEvent (event, rowcount, shpid)
   {
      var xurl = './libs/modules/shipments/searchitem.fancy.php?rowcount=' +rowcount + '&shpid=' +shpid;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<form action="index.php" method="post" name="form_shppos"
<?php if($rdlo != "") echo "onsubmit='return false'"; else echo "onsubmit='return checkform(new Array(this.shp_delivery_date))'"?>>
<input type="hidden" name="exec" value="editshipment">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="shp_status" value="1">
<input type="hidden" name="user_docopen_perm" value="<?=$_REQUEST["user_docopen_perm"]?>">
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
   <td class="content_row"><?=$headdata["shp_num"]?></td>
   <td class="content_rowl">Numero OC</td>
   <td class="content_row"><?=$headdata["sord_number"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_num"]        = $headdata["shp_num"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["sord_number"]    = $headdata["sord_number"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["company_short"]  = $headdata["company_short"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shop_name"]      = $headdata["shop_name"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_company_id"] = $headdata["shp_company_id"];
?>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Proveedor</td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=editshipment&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xshipments"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$headdata["supp_company"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$supplier["supp_rut"]?></b>&nbsp;</td>
</tr>
   <tr <?php if($_SESSION["xshipments"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$supplier["supp_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($supplier["supp_phone"] != "") echo $supplier["supp_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xshipments"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$supplier["name"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][16]?></td>
   <td class="content_row"><?=$supplier["supp_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xshipments"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$supplier["pro_name"]?> - <?=$supplier["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$supplier["supp_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xshipments"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">País</td>
   <td class="content_row"><?=$supplier["country_name"]?>&nbsp;</td>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Cambiar datos del proveedor", "postnav", "index.php?mid=497&exec=edit&fromdocexec=editshipment&id={$supplier["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}", "", "disk-black", 200);
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
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_company"] = $supplier["supp_company"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_rut"]     = $supplier["supp_rut"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_street"]  = $supplier["supp_street"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_phone"]   = $supplier["supp_phone"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["name"]         = $supplier["name"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_fax"]     = $supplier["supp_fax"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["nombre"]       = $supplier["nombre"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_email"]   = $supplier["supp_email"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["country_name"] = $supplier["country_name"];
?>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Numero de guia</td>
   <td class="content_row" <?=$cdatastyle?>>
      <input type="text" style="width:350px" name="shp_supplier_docnum" class="text" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["shp_supplier_docnum"]?>">
   </td>
   <td class="content_rowl" <?=$cdatastyle?>>Recibido *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <input type="text" style="width:80px" id="shp_delivery_date" name="shp_delivery_date" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["shp_delivery_date"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row">
      <textarea class="text" name="shp_annotation" style="width:350px;height:45px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["shp_annotation"]?></textarea>
   </td>
   <td class="content_rowl" valign="top">Estado</td>
   <td class="content_row" valign="top">
      <?php
      $statimg = "";
      switch((int)$headdata["shp_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
         case 3: $statimg = "blue_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>">
      <?=getShipmentStatus($headdata["shp_status"], true)?>
   </td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_supplier_docnum"] = $headdata["shp_supplier_docnum"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_delivery_date"]   = date('d.m.Y', $headdata["shp_delivery_date"]);
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_annotation"]      = $headdata["shp_annotation"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shp_status"]          = $headdata["shp_status"];
?>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($headdata["shp_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["shp_upddat"])?></td>
</tr>
<?php
if(trim($headdata["supp_notes"]) != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Comentarios</td>
      <td class="content_row" colspan="3" style="color:navy"><?generateCommentToogle($headdata["supp_notes"])?></td>
   </tr>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_notes"] = $headdata["supp_notes"];
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<br>
<?php
//----------------------------------------------------------------------------------
// ADJUNTOS
//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.docto_title,
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from tran_docs t1
         LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
         LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
         where
         t1.doc_tran_id    = {$_REQUEST["id"]} and
         t1.doc_tran_type  = 'shipments'
         order by t1.id";
$docs = $CON->select($sql);
if(count($docs) && $docs != false)
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col width="85">
      <col width="100">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Documentos relacionados</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Documento</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($docs) && $docs != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row"><nobr><?=$docs[$x]["doc_name"]?>&nbsp;</nobr></td>
         <td class="content_row"><?=$docs[$x]["docto_title"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["doc_desc"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["crt_lastname"]?>&nbsp;</td>
         <td class="content_row"><?=date('d.m.Y',$docs[$x]["doc_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            $docext = strtoupper(substr($docs[$x]["doc_file"], strrpos($docs[$x]["doc_file"], ".")+1));
            if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
               printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/shipments/{$docs[$x]["doc_file"]}')", "image");
            else
               printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$docs[$x]["doc_file"]}&name={$docs[$x]["doc_name"]}&path=../../../docs.tran/shipments/'", "navigation-270-white");
            ?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
   $sql = " select shp_supporder_id
            from shipment
            where
            id = {$_REQUEST["id"]}";
   $shp_supporder_id = $CON->select($sql);
   $shp_supporder_id = (int)$shp_supporder_id[0]["shp_supporder_id"];

   $sql = " select t1.*, t2.docto_title,
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_docs t1
            LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
            LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
            where
            t1.doc_tran_id    = {$shp_supporder_id} and
            t1.doc_tran_type  = 'supplier_order'
            order by t1.id";
   $refdocs = $CON->select($sql);
   if(count($refdocs) && $refdocs != false)
   {  ?>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col width="85">
         <col width="100">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Origen</td>
         <td class="content_tbl_subheader">Documento</td>
         <td class="content_tbl_subheader">Tipo</td>
         <td class="content_tbl_subheader">Descripción</td>
         <td class="content_tbl_subheader">Creado por</td>
         <td class="content_tbl_subheader">Creado</td>
         <td class="content_tbl_subheader" align="center">Opciones</td>
      </tr>
      <?php
      for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
      {
         $sql = " select sord_number
                  from supplier_order
                  where
                  id = {$refdocs[$x]["doc_tran_id"]}";
         $sord_number = $CON->select($sql);
         $sord_number = $sord_number[0]["sord_number"];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><nobr><?=$sord_number?></nobr></td>
            <td class="content_row"><nobr><?=$refdocs[$x]["doc_name"]?>&nbsp;</nobr></td>
            <td class="content_row"><?=$refdocs[$x]["docto_title"]?>&nbsp;</td>
            <td class="content_row"><?=$refdocs[$x]["doc_desc"]?>&nbsp;</td>
            <td class="content_row"><?=$refdocs[$x]["crt_lastname"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$refdocs[$x]["doc_crtdat"])?></td>
            <td class="content_row" align="center">
               <?php
               $docext = strtoupper(substr($refdocs[$x]["doc_file"], strrpos($refdocs[$x]["doc_file"], ".")+1));
               if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                  printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$refdocs[$x]["doc_file"]}')", "image");
               else
                  printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$refdocs[$x]["doc_file"]}&name={$refdocs[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
               ?>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
   }
}
?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="1">
   <col width="28">
   <col width="1">
   <col width="1">
   <col width="1">
   <col width="1">
   <col>
   <col width="1">
   <col width="1">
   <col width="1">
</colgroup>
<tr>
   <td colspan="10" class="content_tbl_header">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader" align="center">Unidad</td>
   <td class="content_tbl_subheader" align="center">Orden</td>
   <td class="content_tbl_subheader" align="right">Cantidad</td>
   <td class="content_tbl_subheader">Bodega</td>
   <td class="content_tbl_subheader" valign="top" align="right">Precio (neto)</td>
   <td class="content_tbl_subheader" valign="top" align="right">Desc.</td>
   <td class="content_tbl_subheader" valign="top" align="right">Valor Total</td>
</tr>
<?php
$firstentry = false;

for($x = 0; $x < $rowcount; $x++)
{
   $showmanual = false;
   if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_type"] == "manual")
      $showmanual = true;
      
   $shpstyle = "";
   if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_amount_shipped"] > 0)
   {
      if($posdata[$x]["item_amount"] > 0)
      {
         if($posdata[$x]["item_amount_shipped"] == $posdata[$x]["item_amount"])
            $shpstyle = "background-color:#D1FFCC";
         else
            $shpstyle = "background-color:#FFF08C";
      }
      else
         $shpstyle = "background-color:#E4C4F5";
   }

   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_shipped_{$x}";
   if((int)$headdata["shp_stockchange"])
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" valign="top">
         <?if($showmanual) { echo "&nbsp;"; $_FIELDIGNORES["xf_search_{$x}"] = 1; } ?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$x?>" <?php if($showmanual) echo "style='display:none'" ?>>
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td class="content_row_clear">
               <?php
               if((int)$posdata[$x]["item_id"] && (int)$posdata[$x]["item_supporder_pos"] >= 0)
               {  ?>
                  <input type="hidden" name="xf_search_<?=$x?>" id="xf_search_<?=$x?>">
                  <?php
                  echo "Orden";
               }
               else
               {  ?>
                  <input type="text" class="text" style="width:70px" name="xf_search_<?=$x?>" id="xf_search_<?=$x?>"
                  onfocus="markfield(this,0)" <?=$rdlo?> autocomplete="off"
                  <?php
                  if(!(int)$posdata[$x]["item_id"])
                  {  ?>
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/shipments/searchitem.php?rowcount=<?=$x?>&shpid=<?=$headdata["id"]?>&search=' +this.value;} this.value='';"
                     onkeyup="detectEvent(event, '<?=$x?>', '<?=$_REQUEST["id"]?>')"
                     <?php
                  }
                  else
                  {  ?>
                     onblur="markfield(this,1)"
                     <?php
                  }
                  ?>>
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row" valign="top">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="hidden" name="existing_pos_<?=$x?>" value="<?=$posdata[$x]["item_pos"]?>">
            <input type="hidden" name="existing_id_<?=$x?>" value="<?=$posdata[$x]["item_id"]?>">
            <input type="hidden" name="item_amount_<?=$x?>" value="<?=printPrice($posdata[$x]["item_amount"],2)?>">
            <input type="button" class="buttonred" value="x" style="width:20px;<?php if((int)$posdata[$x]["item_supporder_pos"] >= 0 || $dabl != "") echo "display:none" ?>"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) {
                     document.form_shppos.item_id_<?=$x?>.options.length=0;
                     submitForm(document.form_shppos); }">
            <?php
            if((int)$posdata[$x]["item_supporder_pos"] >= 0 || $dabl != "")
               echo "&nbsp;";
         }
         else
         {  ?>
            <img src="/images/menu/icons/notebook--plus.png" border="0" style="cursor:pointer"
            onclick="showOrderPartPosManualEdit('<?=$x?>')">
            <?php
         }
         ?>
         <input type="hidden" name="item_supporder_pos_<?=$x?>"
         value="<?if((int)$posdata[$x]["item_id"]) echo (int)$posdata[$x]["item_supporder_pos"]; else echo "-1";?>">
      </td>
      <td class="content_row" valign="top">
         <nobr>
         <?php
         $overlibover = "";
         $ovritemselw = "360px";
         if((int)$posdata[$x]["item_id"])
         {
            if($posdata[$x]["item_invoicebuy_note"] != "")
               $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoicebuy_note"]))."</b>";

            if($overlibover != "")
            {  ?>
               <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
               onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
               onmouseout="return nd()">
               <?php
               $ovritemselw = "340px";
            }
         }
         ?>
         <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
         onchange="setItemInfosShp('<?=$x?>', this.value)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc = trim(addslashes($posdata[$x]["item_title"]));
               ?>
               <option value="<?=$posdata[$x]["item_id"]?>#<?=$posdata[$x]["item_type"]?>#0#0#<?=(int)$posdata[$x]["item_charges_act"]?>"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></option>
               <?php
            }
            ?>
         </select>
         <textarea class="text" name="item_desc_<?=$x?>" id="item_desc_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         style="width:360px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
         <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
         value="<?php if($showmanual) echo "1"; else echo "0" ?>">
         </nobr>
      </td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
            echo getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="center" valign="top">
         <?php
            if((int)$posdata[$x]["item_id"])
               echo printPrice($posdata[$x]["item_amount"],2);
            else
               echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear">
               <?php
               if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_amount"] > 0.00)
               {  ?>
                  <input type="checkbox" value="1" <?=$dabl?>
                  onclick="if(this.checked && (document.all.item_amount_shipped_<?=$x?>.value == '' ||
                              document.all.item_amount_shipped_<?=$x?>.value == '0,00' ||
                              document.all.item_amount_shipped_<?=$x?>.value == '0'))
                              document.all.item_amount_shipped_<?=$x?>.value = '<?=printPrice($posdata[$x]["item_amount"],2)?>';
                           if(!this.checked)
                              document.all.item_amount_shipped_<?=$x?>.value = '';"
                  <?php if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_amount_shipped"] > 0) echo "checked" ?>>
                  <?php
               }
               ?>
            </td>
            <td class="content_row_clear" align="right">
               <input type="text" class="text" style="width:50px;text-align:right;<?=$shpstyle?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
               name="item_amount_shipped_<?=$x?>" id="item_amount_shipped_<?=$x?>" <?=$rdlo?>
               value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount_shipped"],2)?>">
            </td>
         </tr>
         </table>
         <?php
         $ges_ordered += $posdata[$x]["item_amount"];
         $ges_shipped += $posdata[$x]["item_amount_shipped"];
         ?>
      </td>
      <td class="content_row" valign="top">
         <?php
         if(!(int)$headdata["shp_stockchange"])
            echo "<div style='display:none'>";
         ?>
         <select class="text" style="width:125px;<?if(!(int)$headdata["shp_stockchange"]) echo 'display:none'?>"
         name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="addSelStyle(this);">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               
               if($posdata[$x]["item_type"] == "item")
               {
                  $checkitemid = $posdata[$x]["item_id"];
                  if((int)$posdata[$x]["item_subitem_id"])
                     $checkitemid = $posdata[$x]["item_subitem_id"];
                     
                  $itemsts = getItemStorehouses($CON, $headdata["shp_shop_id"], $checkitemid, $posdata[$x]["item_type"]);
               }
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["shp_shop_id"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"], $itemstid, $checkitemid, $posdata[$x]["item_type"], true);
                     ?>
                     <option value="<?=$itemstid?>"
                     <?php if($posdata[$x]["item_st_id"] == $itemstid) echo "selected"?>>
                        <?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)
                     </option>
                     <?php
                  }
               }
            }
            ?>
         </select>
         <?php
         if(!(int)$headdata["shp_stockchange"])
            echo '</div>&nbsp;';
         ?>
         <div style="<?if(!(int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$x?>">
            <?php
            $btnicon = "arrow";
            $btnname = "Lotes";
            $btnclas = "postnav";
               
            if(posHasItemChargeData($CON, $_REQUEST["id"], "shipment", $x) > 0)
            {
               $btnicon = "tick-circle-frame";
               $btnclas = "postnav_save";

               $charge_amount = posItemChargeDataAmount($CON, $_REQUEST["id"], "shipment", $x);
               if($charge_amount["tran_amount"] != $posdata[$x]["item_amount_shipped"])
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
               
            printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.php?trantype=shipment&tranid={$_REQUEST["id"]}&tranpos={$x}&itemdata=' +escape($('#item_id_{$x}').val()), 'iframe', 650, 400, 'auto')", "", $btnicon)
            ?>
            <textarea id="item_charges_data_<?=$x?>" name="item_charges_data_<?=$x?>" style="display:none"></textarea>
         </div>
      </td>
      <td class="content_row" align="right" valign="top">
         <nobr>
         <?=$moneystr?>
         <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$rdlo?>
         name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </nobr>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:40px;text-align:right" <?=$rdlo?> autocomplete="off"
         name="item_discount_<?=$x?>" id="item_discount_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_discount"],8)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right" valign="top">
         <nobr>
         <?=$moneystr?>
         <input type="text" class="text" readonly
         style="width:<?=$inputw2?>;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_amount_shipped"] > 0.00) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         name="item_costprice_netto_dsc_<?=$x?>" id="item_costprice_netto_dsc_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim)?>">
         </nobr>
      </td>
   </tr>
   <?php
   if((int)$posdata[$x]["item_amount_shipped"])
   {
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val1"] = $desc;
      if($showmanual)
         $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val1"] = $posdata[$x]["item_desc"];
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val2"] = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val3"] = $posdata[$x]["item_number_prod"];
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val4"] = printPrice($posdata[$x]["item_amount_shipped"],2);
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val6"] = $moneystr." ".printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val7"] = printPrice($posdata[$x]["item_discount"], 8);
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val8"] = $moneystr." ".printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val9"] = $posdata[$x]["item_code"];
   }

   if($x == 0 && !(int)$posdata[$x]["item_id"])
      $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$x}.focus();";
}
?>
<tr>
   <td class="content_row_totals" colspan="4">TOTAL</td>
   <td class="content_row_totals" align="center"><?=printPrice($ges_ordered,2)?></td>
   <td class="content_row_totals" align="right"><?=printPrice($ges_shipped,2)?></td>
   <td class="content_row_totals">&nbsp;</td>
   <td class="content_row_totals content_rowl" colspan="2">MONTO</td>
   <td class="content_row_totals" align="right">
      <input type="text" class="text" style="width:70px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
      value="<?=$moneystr?><?=printPrice($headdata["shp_total_netto"], $numberlim)?>">
   </td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val1"] = "<b>TOTAL</b>";
$_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val4"] = "<b>".printPrice($ges_shipped,2)."</b>";
$_SESSION["STATS"][$_sesmodulename]["ITEMS"][$x]["val8"] = "<b>".$moneystr." ".printPrice($headdata["shp_total_netto"], $numberlim)."</b>";
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
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if((int)$headdata["shp_status"] == 1)
            printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=delshipment&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   if((int)$headdata["shp_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if(!$_BLOCKFIN_CHARGE)
      {  ?>
         <td align="right" width="130" style="padding-right:5px" id="idx_fin_button">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.shp_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {
      if((int)$headdata["shp_supporder_id"] && !(int)$headdata["sord_order_shipped"])
      {  ?>
         <td align="right" width="140" style="padding-right:5px">
            <?php
            printButton("Cerrar OC", "postnav_save", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=editshipment&subexec=edit&clearOrder=1&id={$_REQUEST["id"]}')", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
      $_BLOCKSYNC = false;
      if($isremote && $headdata["shp_status"] > 1 && !(int)$headdata["shp_remote_synced"])
      {  ?>
         <td width="130" style="padding-right:5px;color:red" class="content_row_clear">
            Esperando sucursal.
         </td>
         <?php
         $_BLOCKSYNC = true;
      }
      if($headdata["shp_status"] == 2)
      {  ?>
         <td align="right" width="180" style="padding-right:5px">
            <?php
            printButton("Cerrar guia para facturación", "postnav_save", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=editshipment&id={$_REQUEST["id"]}&cancelInvoice=1')", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
      if($headdata["shp_status"] == 3)
      {  ?>
         <td align="right" width="180" style="padding-right:5px">
            <?php
            printButton("Abrir guia para facturación", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=editshipment&id={$_REQUEST["id"]}&openInvoice=1')", "arrow-circle-045-left");
            ?>
         </td>
         <?php
      }
      if($headdata["shp_status"] == 2 && !$_BLOCKOPEN_CHARGE && !$_BLOCKSYNC)
      {  ?>
         <td align="right" width="140">
            <?php
            if($hasdocopeperm)
               printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';submitForm(document.form_shppos);}", "arrow-circle-045-left");
            else
               printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')", "arrow-circle-045-left");
            ?>
         </td>
         <?php
      }
      ?>
      <td align="right" width="140" style="padding-left:5px">
         <?php
         printButton("Imprimir", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=editshipment&subexec=edit&id={$_REQUEST["id"]}&printpdf=1", "", "document-pdf");
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
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createShipmentPDF($CON);

if($pdffile != "")
{
   $doctitle = "Guia-de-Despacho-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
