<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_RSPSTHIDRET     = getUserRespSthids($CON);
$_RSPSTHIDS       = $_RSPSTHIDRET["_RSPSTHIDS"];
$_RSPSTHIDS_SQL   = $_RSPSTHIDRET["_RSPSTHIDS_SQL"];

if((int)$_REQUEST["generateDlv"])
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from storehousechanges t1
            where
            t1.id = {$_REQUEST["id"]}";
   $hdata = $CON->select($sql);
   $hdata = $hdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select shop_rel_custid, shop_rel_custdelivid
            from company_shops
            where
            id = {$hdata["strc_shop_dest_id"]}";
   $shopdest = $CON->select($sql);
   $shopdest = $shopdest[0];

   if(!(int)$shopdest["shop_rel_custid"])
   {  ?>
      <script language="JavaScript">
         alert('EL SUCURSAL DESTINO NO TIENE EL CLIENTE ASIGNADO');
      </script>
      <?php
   }
   else
   {
      //----------------------------------------------------------------------------------
      $sql = " select t2.*, t3.item_title, t3.item_number_prod
               from storehousechanges_items t2
               LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
               where
               t2.strc_id     = {$_REQUEST["id"]} and
               t2.item_type   = 'item'
               UNION ALL
               select t2.*, t3.item_title, t3.item_number_prod
               from storehousechanges_items t2
               LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
               where
               t2.strc_id     = {$_REQUEST["id"]} and
               t2.item_type   = 'itemlist'
               order by 3 asc";
      $posdata = $CON->select($sql);

      //----------------------------------------------------------------------------------
      $itemsperinvoice  = 22;
      $rowcount         = count($posdata);
      $geninvoices      = ceil($rowcount / $itemsperinvoice);
      $currtme          = time();
      $poscc            = 0;

      //----------------------------------------------------------------------------------
      for($x = 0; $x < $geninvoices; $x++)
      {
         $dlv_num = createTransactionNumber($CON, $hdata["strc_company_id"], "shipmentsell");
         $dlv_number_str .= $dlv_num.", ";
      
         $sql = " insert into orders_delivery
                  (dlv_num, dlv_cust_id, dlv_delivery_date, dlv_order_based, dlv_paymentid,
                   dlv_order_id, dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
                   dlv_transportid, dlv_mode, dlv_supplier_id, dlv_parent_guaid, dlv_weightprice_netto,
                   dlv_crtdat, dlv_crtusr, dlv_oc_number, dlv_oc_dat, dlv_annotation, dlv_annotation_intern,
                   dlv_docnum, dlv_discount_perc, dlv_discount_amt)
                  VALUES
                  ('{$dlv_num}', {$shopdest["shop_rel_custid"]}, {$currtme}, 0, 0,
                    0, {$hdata["strc_company_id"]}, {$hdata["strc_shop_id"]},
                    1, {$shopdest["shop_rel_custdelivid"]}, 0, 2, 0, 0,
                    0, {$currtme}, {$_SESSION["user_id"]}, '', 0, 'GENERADO POR TRASPASO {$hdata["strc_number"]}',
                    '', '{$dlv_num}', 0, 0)";
         $res = $CON->no_result($sql);

         $poscc = $x * $itemsperinvoice;
         $maxcc = $poscc + $itemsperinvoice;
         if($res)
         {
            $sql = " select MAX(id) 'thisid'
                     from orders_delivery
                     where
                     dlv_crtusr = {$_SESSION["user_id"]}";
            $dlvid = $CON->select($sql);
            $dlvid = $dlvid[0]["thisid"];

            $poscounter = 0;
            for($y = $poscc; $y < $maxcc; $y++)
            {
               if((int)$posdata[$y]["item_id"])
               {
                  $sellprice = getShopItemStorePrice($CON, $hdata["strc_shop_id"], $posdata[$y]["item_id"], $posdata[$y]["item_type"]);

                  $item_sellprice_brutto     = (float)$sellprice["itemshop_sellprice_brutto"];
                  $item_sellprice_taxes_perc = (float)$sellprice["itemshop_sellprice_taxes_perc"];
                  $item_sellprice_netto      = (float)$sellprice["itemshop_sellprice_netto"];
                  $item_sellprice_taxes      = (float)$sellprice["itemshop_sellprice_taxes"];
                  $item_sellprice_netto_dsc  = (float)($posdata[$y]["item_amount"] * $item_sellprice_netto);

                  $linedesc = trim(addslashes($posdata[$y]["item_number_prod"]." - ".$posdata[$y]["item_title"]));

                  $sql = " insert into orders_delivery_items
                           (dlv_id, item_id, item_pos, item_amount, item_amount_shipped, item_type, 
                            item_sellprice_brutto, item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_netto_dsc,
                            item_sellprice_taxes,  item_desc)
                           VALUES
                           ({$dlvid}, 9999999, {$poscounter}, 0, {$posdata[$y]["item_amount"]}, 'manual',
                            {$item_sellprice_brutto}, {$item_sellprice_taxes_perc}, {$item_sellprice_netto}, {$item_sellprice_netto_dsc},
                            {$item_sellprice_taxes}, '{$linedesc}')";
                  $CON->no_result($sql);

                  $poscounter++;
               }
            }
         }
      }

      $dlv_number_str = substr($dlv_number_str, 0, -2);
      if($geninvoices > 1)
      {  ?>
         <script language="JavaScript">
            alert('GUIAS GENERADAS: <?=$dlv_number_str?>\nFAVOR INGRESA A LOS DOCUMENTOS VIA EL MENU.');
         </script>
         <?php
      }
      else
      {  ?>
         <script language="JavaScript">
            alert('GUIA GENERADA: <?=$dlv_number_str?>');
            location.href='/index.php?mid=749&exec=edit&subexec=edit&id=<?=$dlvid?>';
         </script>
         <?php
      }
   
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   $_REQUEST["company_id"]      = (int)$_REQUEST["company_id"];
   $_REQUEST["company_id_dest"] = (int)$_REQUEST["company_id_dest"];
   $_REQUEST["shop_id"]         = (int)$_REQUEST["shop_id"];
   $_REQUEST["shop_id_dest"]    = (int)$_REQUEST["shop_id_dest"];
   $strcnumber = createTransactionNumber($CON, $_REQUEST["company_id"], "storehouse");
   $sql_date = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

   $_REQUEST["strc_order_num"]         = trim(addslashes($_REQUEST["strc_order_num"]));
   $_REQUEST["strc_order_num_dest"]    = trim(addslashes($_REQUEST["strc_order_num_dest"]));
   $strc_order_id = 0;
   $strc_order_id_dest = 0;
   if($_REQUEST["strc_order_num"] != "")
   {
      $sql = " select id
               from orders
               where
               req_status > 0 and
               req_number = '{$_REQUEST["strc_order_num"]}'";
      $orderdata = $CON->select($sql);
      $orderdata = $orderdata[0];
      if(!(int)$orderdata["id"])
      {  ?>
         <script language="Javascript">
            alert('Error: CC no existe');
            location.href = '/index.php?mid=680&exec=start&req_status=1&page=0';
         </script>
         <?php
         exit;
      }
      else
         $strc_order_id = $orderdata["id"];
   }
   if($_REQUEST["strc_order_num_dest"] != "")
   {
      $sql = " select id
               from orders
               where
               req_status > 0 and
               req_number = '{$_REQUEST["strc_order_num_dest"]}'";
      $orderdata = $CON->select($sql);
      $orderdata = $orderdata[0];
      if(!(int)$orderdata["id"])
      {  ?>
         <script language="Javascript">
            alert('Error: CC no existe');
            location.href = '/index.php?mid=680&exec=start&req_status=1&page=0';
         </script>
         <?php
         exit;
      }
      else
         $strc_order_id_dest = $orderdata["id"];
   }


   $sql = " insert into storehousechanges
            (strc_company_id, strc_shop_id, strc_company_dest_id, strc_shop_dest_id, strc_date,
             strc_number, strc_crtusr, strc_crtdat, strc_order_num, strc_order_id, strc_order_num_dest, strc_order_id_dest)
            VALUES
            ({$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, {$_REQUEST["company_id_dest"]}, {$_REQUEST["shop_id_dest"]},
             {$sql_date}, '{$strcnumber}', {$_SESSION["user_id"]}, {$currtme},
             '{$_REQUEST["strc_order_num"]}', {$strc_order_id}, '{$_REQUEST["strc_order_num_dest"]}', {$strc_order_id_dest})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'id'
               from storehousechanges
               where
               strc_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["id"] = (int)$thisid[0]["id"];

      ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from storehousechanges t1
         where
         t1.id = {$_REQUEST["id"]}";
$stktemp = $CON->select($sql);
$stktemp = $stktemp[0];

$_APROBMODE = false;
if((int)$stktemp["strc_status"] == 1 && (int)$stktemp["strc_poraprobar"] == 1)
   $_APROBMODE = true;

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "xf_search_") !== false && strpos($reqkey, "xf_search_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $item     = explode("#", $_REQUEST["item_id_{$idx}"]);
         $itemid   = $item[0];
         $itemtype = $item[1];
         $sql_charges_act  = (int)$item[2];

         $_REQUEST["item_amount_{$idx}"]     = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_stid_{$idx}"]       = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_stid_dest_{$idx}"]  = (int)$_REQUEST["item_stid_dest_{$idx}"];

         //----------------------------------------------------------------------------------
         /*
         $isremote = getShops($CON, false, false, 0, $stktemp["strc_shop_id"]);
         $isremote = $isremote[0]["shop_isremote"];
         if($isremote)
            $_REQUEST["item_stid_{$idx}"] = 0;

         //----------------------------------------------------------------------------------
         $isremote = getShops($CON, false, false, 0, $stktemp["strc_shop_dest_id"]);
         $isremote = $isremote[0]["shop_isremote"];
         if($isremote)
            $_REQUEST["item_stid_dest_{$idx}"] = 0;
         */

         //----------------------------------------------------------------------------------
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         if($_REQUEST["item_id_{$idx}"] != ""  && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update storehousechanges_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_st_dest_id            = {$_REQUEST["item_stid_dest_{$idx}"]},
                        item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                        item_pos                   = {$poscounter}
                        where
                        strc_id                    = {$_REQUEST["id"]} and
                        item_id                    = {$existing_id} and
                        item_pos                   = {$existing_pos}";
               $CON->no_result($sql);

               renameItemChargePosUsed($CON, $_REQUEST["id"], "sthdown", $existing_pos, $poscounter, 0, true);

               updateItemChargeDataUsed($CON, $_REQUEST["id"], "sthdown", $existing_id, $itemtype, $poscounter,
                                       $stktemp["strc_company_id"], $stktemp["strc_shop_id"], $_REQUEST["item_charges_data_{$idx}"],
                                       0, true);
            }
            else
            {
               $sql = " insert into storehousechanges_items
                        (strc_id, item_id, item_pos, item_type, item_amount, item_st_id, item_st_dest_id, item_charges_act)
                        VALUES
                        ({$_REQUEST["id"]}, {$itemid}, {$poscounter}, '{$itemtype}', {$_REQUEST["item_amount_{$idx}"]},
                        {$_REQUEST["item_stid_{$idx}"]}, {$_REQUEST["item_stid_dest_{$idx}"]}, {$sql_charges_act})";
               $CON->no_result($sql);

               updateItemChargeDataUsed($CON, $_REQUEST["id"], "sthdown", $itemid, $itemtype, $poscounter,
                                       $stktemp["strc_company_id"], $stktemp["strc_shop_id"], $_REQUEST["item_charges_data_{$idx}"],
                                       0, true);
            }
            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from storehousechanges_items
                     where
                     strc_id        = {$_REQUEST["id"]} and
                     item_id        = {$existing_id} and
                     item_pos       = {$existing_pos}";
            $CON->no_result($sql);

            clearItemChargeDataUsed($CON, $_REQUEST["id"], "sthdown", $existing_pos, 0, true);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["strc_desc"]   = trim(addslashes($_REQUEST["strc_desc"]));
   $_REQUEST["strc_status"] = (int)$_REQUEST["strc_status"];
   $_REQUEST["cust_id_0"]   = (int)$_REQUEST["cust_id_0"];
   
   $sql_date = explode(".", $_REQUEST["strc_date"]);
   $sql_date = mktime(0, 0, 0, $sql_date[1], $sql_date[0], $sql_date[2]);

   $sql = " update storehousechanges
            set
            strc_custid = {$_REQUEST["cust_id_0"]},
            strc_date   = {$sql_date},
            strc_desc   = '{$_REQUEST["strc_desc"]}',
            strc_updusr = {$_SESSION["user_id"]},
            strc_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   if($_REQUEST["strc_status"] == 2)
   {
      //----------------------------------------------------------------------------------
      $sql = " select t2.*, t3.item_title, t3.item_number_prod
               from storehousechanges_items t2
               LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
               where
               t2.strc_id     = {$_REQUEST["id"]} and
               t2.item_type   = 'item'
               UNION ALL
               select t2.*, t3.item_title, t3.item_number_prod
               from storehousechanges_items t2
               LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
               where
               t2.strc_id     = {$_REQUEST["id"]} and
               t2.item_type   = 'itemlist'
               order by 3 asc";
      $posdata = $CON->select($sql);
      $needsaprob = 0;

      foreach($posdata AS $posdatarow)
      {
         if(!(int)$_RSPSTHIDS[$posdatarow["item_st_dest_id"]])
            $needsaprob = 1;
      }

      if($_APROBMODE)
         $needsaprob = 0;

      if($needsaprob)
      {
         $sql = " update storehousechanges
                  set
                  strc_status = 1,
                  strc_poraprobar = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
         ?>
         <script language="Javascript">
            alert("Traspaso a la espera de aprobacion.");
            location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
         </script>
         <?php
      }
      else
      {
         $sql = " update storehousechanges
                  set
                  strc_poraprobar = 0
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);

         bookStorehouseChange($CON, $_REQUEST["id"]);
         xls_createStorehousechange($CON, $_REQUEST["id"]);
      }

   }
   if($stktemp["strc_status"] == 2 && $_REQUEST["strc_status"] == "1")
      delStorehouseChange($CON, $_REQUEST["id"]);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.company_short, t3.company_short 'company_dest_name', t4.shop_name, t5.shop_name 'shop_dest_name',
                t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname', t8.cust_name
         from storehousechanges t1
         LEFT OUTER JOIN company_data t2 ON t1.strc_company_id = t2.id
         LEFT OUTER JOIN company_data t3 ON t1.strc_company_dest_id = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.strc_shop_id = t4.id
         LEFT OUTER JOIN company_shops t5 ON t1.strc_shop_dest_id = t5.id
         LEFT OUTER JOIN user t6 ON t1.strc_updusr = t6.id
         LEFT OUTER JOIN user t7 ON t1.strc_crtusr = t7.id
         LEFT OUTER JOIN customer t8 ON t1.strc_custid = t8.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$isremote  = 0;
/*
$isremote1 = getShops($CON, false, false, 0, $headdata["strc_shop_id"]);
$isremote1 = $isremote1[0]["shop_isremote"];
$isremote2 = getShops($CON, false, false, 0, $headdata["strc_shop_dest_id"]);
$isremote2 = $isremote2[0]["shop_isremote"];
if((int)$isremote1 || $isremote2)
   $isremote = 1;
*/
   
//----------------------------------------------------------------------------------
$sql = " select t2.*, t3.item_title, t3.item_number_prod
         from storehousechanges_items t2
         LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
         where
         t2.strc_id     = {$_REQUEST["id"]} and
         t2.item_type   = 'item'
         UNION ALL
         select t2.*, t3.item_title, t3.item_number_prod
         from storehousechanges_items t2
         LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
         where
         t2.strc_id     = {$_REQUEST["id"]} and
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


if((int)$headdata["strc_status"] > 1)
{
   $rdlo       = "readonly";
   $dabl       = "disabled";
   $rowcount   = count($posdata);
}

$_APROBMODE = false;
if((int)$headdata["strc_status"] == 1 && (int)$headdata["strc_poraprobar"] == 1)
   $_APROBMODE = true;

if($_APROBMODE)
{
   $rowcount = count($posdata);
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/storehousechanges/searchstorehouses.php?strcid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

      var valarr  = $('#item_id_' +idx).val().split('#');
      valarr[4] = valarr[2];
      switchShpChargeMode(idx, valarr);
   }

   function detectEvent (event)
   {
      var xurl = './libs/modules/orders/searchcust.fancy.php'
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<table border="0" cellpadding="0" cellspacing="0" width="1180">
<tr>
   <td height="30"><b class="content_header">Cambiar traspaso</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="form_strc"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.strc_date))"
   <?php
}
?>>
<script language="JavaScript">
   function checkOpenInvcNotes(val)
   {
   }
</script>
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="strc_status" value="1">
<input type="hidden" name="printpdf" value="0">
<?=Nifty_printH("box1", "1180")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="400">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos del traspaso</td>
</tr>
<?php
if((int)$headdata["strc_order_id"])
{
   $sql = " select t4.cust_name
            from orders t1
            INNER JOIN customer t4 ON t1.req_cust_id = t4.id
            where
            t1.id = {$headdata["strc_order_id"]}";
   $cust_name = $CON->select($sql);
   $cust_name = $cust_name[0]["cust_name"];
   ?>
   <tr>
      <td class="content_rowl" style="background-color:#7BFFFD">N° CC origen</td>
      <td class="content_row" style="background-color:#7BFFFD"><?=$headdata["strc_order_num"]?>&nbsp;</td>
      <td class="content_rowl" style="background-color:#7BFFFD">Cliente origen</td>
      <td class="content_row" style="background-color:#7BFFFD"><?=$cust_name?></td>
   </tr>
   <?php
}
if((int)$headdata["strc_order_id_dest"])
{
   $sql = " select t4.cust_name
            from orders t1
            INNER JOIN customer t4 ON t1.req_cust_id = t4.id
            where
            t1.id = {$headdata["strc_order_id_dest"]}";
   $cust_name = $CON->select($sql);
   $cust_name = $cust_name[0]["cust_name"];
   ?>
   <tr>
      <td class="content_rowl" style="background-color:#7BFFFD">N° CC destino</td>
      <td class="content_row" style="background-color:#7BFFFD"><?=$headdata["strc_order_num_dest"]?>&nbsp;</td>
      <td class="content_rowl" style="background-color:#7BFFFD">Cliente destino</td>
      <td class="content_row" style="background-color:#7BFFFD"><?=$cust_name?></td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row" colspan="3"><?=$headdata["strc_number"]?></td>
</tr>
<tr>
   <td class="content_rowl">Empresa origen</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Empresa destino</td>
   <td class="content_row"><?=$headdata["company_dest_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Sucursal origen</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
   <td class="content_rowl">Sucursal destino</td>
   <td class="content_row"><?=$headdata["shop_dest_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Fecha</td>
   <td class="content_row">
      <input type="text" style="width:70px" id="strc_date" name="strc_date" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?if((int)$headdata["strc_date"]) echo date('d.m.Y',$headdata["strc_date"]);?>">
   </td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <?php
      $statimg = "";
      switch((int)$headdata["strc_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>">
      <?=getShipmentStatus($headdata["strc_status"], true)?>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Cliente</td>
   <td class="content_row" colspan="3">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="160">
            <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value=""
            onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?fromdlv=1&rowcount=0' +'&search=' +this.value;"
            <?if($rdlo == ""){?> onkeyup="detectEvent(event)" <?}?> <?=$rdlo?>>
         </td>
         <td>
            <select class="text" name="cust_id_0" id="cust_id_0" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               if((int)$headdata["strc_custid"])
               {  ?>
                  <option value="<?=$headdata["strc_custid"]?>"><?=$headdata["cust_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row" colspan="3">
      <textarea class="text" name="strc_desc" style="width:830px;height:45px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["strc_desc"]?></textarea>
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
   <td class="content_row"><?=displayDate($headdata["strc_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["strc_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
$_CANREOPEN    = true;
$_CANAPPROVE   = true;
$_CANREOPENAFTERAPPROVE = true;
?>
<?=Nifty_printH("box2", "1180")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col width="28">
   <col>
   <col width="60">
   <col width="300">
   <col width="300">
</colgroup>
<tr>
   <td colspan="6" class="content_tbl_header">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader">Cantidad</td>
   <td class="content_tbl_subheader">Bodega origen</td>
   <td class="content_tbl_subheader">Bodega destino</td>
</tr>
<?php
$firstentry = false;

for($x = 0; $x < $rowcount; $x++)
{
   //----------------------------------------------------------------------------------
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_dest_{$x}";
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" valign="top">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td class="content_row_clear">
               <input type="text" class="text" style="width:70px" id="xf_search_<?=$x?>" name="xf_search_<?=$x?>"
               onfocus="markfield(this,0)" <?=$rdlo?> <?if($_APROBMODE) echo "readonly"?>
               <?php
               if(!(int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/storehousechanges/searchitem.php?rowcount=<?=$x?>&strcid=<?=$headdata["id"]?>&search=' +this.value;} this.value='';"
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
      <td class="content_row" valign="top">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="hidden" name="existing_pos_<?=$x?>" value="<?=$posdata[$x]["item_pos"]?>">
            <input type="hidden" name="existing_id_<?=$x?>" value="<?=$posdata[$x]["item_id"]?>">
            <input type="button" class="buttonred" value="x" style="width:20px;<?php if($_APROBMODE || $dabl != "") echo "display:none" ?>"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) {
                     document.form_strc.item_id_<?=$x?>.options.length=0;
                     submitForm(document.form_strc); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:370px" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onfocus="addSelStyle(this);"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onchange="setItemInfosSth('<?=$x?>', this.value)">
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
      <td class="content_row">
         <input type="text" style="width:60px;text-align:right" name="item_amount_<?=$x?>" id="item_amount_<?=$x?>"
         <?=$rdlo?> class="text" <?if($_APROBMODE) echo "readonly"?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?if($posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"], 2)?>">
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:300px;<?if((int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>"
         name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
         onfocus="addSelStyle(this);" onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if($posdata[$x]["item_type"] == "item")
                  $itemsts = getItemStorehouses($CON, $headdata["strc_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_id"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     if((int)$headdata["strc_status"] > 1 || $_APROBMODE || ((int)$headdata["strc_status"] == 1 && (int)$_RSPSTHIDS[$itemstid]))
                     {
                        $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_id"], $itemstid,
                                                                       (int)$posdata[$x]["item_id"], $posdata[$x]["item_type"], true,
                                                                       (int)$headdata["strc_order_id"]);

                        if(!$_APROBMODE || ($_APROBMODE && $itemstid == $posdata[$x]["item_st_id"]))
                        {  ?>
                           <option value="<?=$itemstid?>"
                           <?php if($itemstid == $posdata[$x]["item_st_id"]) echo "selected"?>><?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)</option>
                           <?php
                        }

                        if($posdata[$x]["item_st_id"] == $itemstid && !$_RSPSTHIDS[$itemstid])
                           $_CANREOPEN = false;
                     }
                  }
               }
            }
            ?>
         </select>
         <div style="<?if(!(int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$x?>">
            <?php
            $btnicon = "arrow";
            $btnname = "Lotes";
            $btnclas = "postnav";

            if(posHasItemChargeDataUsed($CON, $_REQUEST["id"], "sthdown", $x) > 0)
            {
               $btnicon = "tick-circle-frame";
               $btnclas = "postnav_save";

               $charge_amount = posItemChargeDataAmountUsed($CON, $_REQUEST["id"], "sthdown", $x);
               if($charge_amount["tran_amount"] != $posdata[$x]["item_amount"])
               {
                  $btnicon = "cross-circle-frame";
                  $btnclas = "postnav_del";
                  $_BLOCKFIN_CHARGE = true;
               }

               $dest_charge_amount = posItemChargeDataAmount($CON, $_REQUEST["id"], "sthup", $x);
               if($dest_charge_amount["tran_amount_used"] > 0.00)
                  $_BLOCKOPEN_CHARGE = true;
            }
            elseif((int)$posdata[$x]["item_charges_act"])
               $_BLOCKFIN_CHARGE = true;
   
            printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.select.php?trantype=sthdown&needamount=' +$('#item_amount_{$x}').val() +'&tranid={$_REQUEST["id"]}&tranpos={$x}&itemdata=' +escape($('#item_id_{$x}').val()), 'iframe', 750, 400, 'auto')", "", $btnicon, 170)
            ?>
            <textarea id="item_charges_data_<?=$x?>" name="item_charges_data_<?=$x?>" style="display:none"></textarea>
         </div>
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:300px" name="item_stid_dest_<?=$x?>" id="item_stid_dest_<?=$x?>"
         onfocus="addSelStyle(this);"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if($posdata[$x]["item_type"] == "item")
                  $itemsts = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"], $itemstid,
                                                                   (int)$posdata[$x]["item_id"], $posdata[$x]["item_type"], true,
                                                                    (int)$headdata["strc_order_id_dest"]);

                     if(!$_APROBMODE || ($_APROBMODE && $itemstid == $posdata[$x]["item_st_dest_id"]))
                     {  ?>
                        <option value="<?=$itemstid?>"
                        <?php if($itemstid == $posdata[$x]["item_st_dest_id"]) echo "selected"?>><?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)</option>
                        <?php
                     }

                     if($_APROBMODE)
                     {
                        if($posdata[$x]["item_st_dest_id"] == $itemstid && !$_RSPSTHIDS[$itemstid])
                           $_CANAPPROVE = false;
                     }

                     if($posdata[$x]["item_st_dest_id"] == $itemstid && !$_RSPSTHIDS[$itemstid])
                        $_CANREOPEN = false;
                  }
               }
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
   if($x == 0 && !(int)$posdata[$x]["item_id"])
      $_SESSION["JSEXEC"] .= "document.form_strc.xf_search_{$x}.focus();";
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "1180")?>
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
         if((int)$headdata["strc_status"] == 1)
         {
            if(!$_APROBMODE || ($_APROBMODE && $_CANAPPROVE))
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         }
         ?>
      </td>
      <?php
   }
   if((int)$headdata["strc_status"] == 1)
   {
      if(!$_APROBMODE || ($_APROBMODE && $_CANAPPROVE))
      {  ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_strc)", "disk-black");
            ?>
         </td>
         <?php
      }
      if(!$_APROBMODE || ($_APROBMODE && $_CANAPPROVE))
      {
         if(!$_BLOCKFIN_CHARGE)
         {  ?>
            <td align="right" width="130" style="padding-right:5px" id="idx_fin_button">
               <?php
               printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_strc.strc_status.value = '2';document.form_strc.submit();}", "tick-circle-frame");
               ?>
            </td>
            <?php
         }
      }
   }
   else
   {
      $_BLOCKSYNC = false;
      if($isremote && $headdata["strc_status"] > 1 && !(int)$headdata["strc_remote_synced"])
      {  ?>
         <td width="130" style="padding-right:5px;color:red" class="content_row_clear">
            Esperando sucursal.
         </td>
         <?php
         $_BLOCKSYNC = true;
      }
      if(!$_BLOCKOPEN_CHARGE && !$_BLOCKSYNC && $_CANREOPEN)
      {  ?>
         <td align="right" width="140">
            <?php
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_strc.onsubmit='';submitForm(document.form_strc);}", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
      ?>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/storehousechanges/data.storehouse.pdf.php?id={$_REQUEST["id"]}'", "document-pdf");
         ?>
      </td>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&hash={$headdata["strc_number"]}.storehousechanges.xls&name=Traspaso-{$headdata["strc_number"]}.xls&path=../../../docs.print/'", "document-excel");
         ?>
      </td>
      <td align="left" width="130" style="padding-left:5px">
         <?php
         printButton("Generar Guia", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { location.href='index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&generateDlv=1' } ", "gear");
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
<?php $_SESSION["JSEXEC"] .= "addFormListeners('form_strc');" ?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>