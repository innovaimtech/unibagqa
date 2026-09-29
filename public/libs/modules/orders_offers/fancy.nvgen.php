<?php
$sql = " select *
from company_data
where
id = {$_SESSION["user_company_id"]}";
$companydata = $CON->select($sql);
$companydata = $companydata[0];
$_SESSION["_CONF"]["conf_number_decimal_places"] = $companydata["company_num_decimal"];
$_SESSION["_CONF"]["conf_currency"] = $companydata["company_simbolo_moneda"];
$_SESSION["_CONF"]["conf_taxes"] = $companydata["company_iva"];

//---------------------------------------------------------------------------------------------------------------------------------
function MailEnvioCorreoAvisoCierre($CON, $order_id)
{
  
   $sql = "select req_number
                  , req_total_netto
                  , req_total_taxes
                  , req_total_brutto
                  , req_cust_id
                  , req_crtdat
                  , req_userid_seller
                  , req_offerid 
                  , concat(ven.user_firstname,' ',ven.user_lastname) as vendedor
                  , cus.cust_company
                  , cus.cust_name
                  , o.req_paymentid
                  , o.req_despacho_desc
                  , pay.pay_title
               from orders o
                  inner join user ven on ven.id = o.req_userid_seller
                  inner join customer cus on cus.id = o.req_cust_id
                  inner join payments pay on pay.id = o.req_paymentid                  
               where o.id = {$order_id}";
   
   $cotizacion = $CON->select($sql);
   $cotizacion = $cotizacion[0];

   $sql = "select user_firstname, user_lastname, user_mail
            from user where user_informar_cierre_cotiza = 1";
   $envio_mail = $CON->select($sql);

   $fecha        = time();
   $Neto         = '$' . number_format($cotizacion["req_total_netto"], 0, ',', '.'); 
   $Iva          = '$' . number_format($cotizacion["req_total_taxes"], 0, ',', '.');
   $Total        = '$' . number_format($cotizacion["req_total_brutto"], 0, ',', '.');

   foreach($envio_mail as $mail)
   {
      $title   = "Confirmación de Cotización {$cotizacion["req_number"]} - {$cotizacion["cust_name"]} ";
      $body    = "Estimado(a) {$mail["user_firstname"]} {$mail["user_lastname"]},<br><br>
                     Con fecha ".date("d/m/Y H:i", $fecha)." se ha aceptado la cotizacion {$cotizacion["req_number"]} de :<br><br>
                     Razon Social  : {$cotizacion["cust_company"]}.<br>
                     Cliente       : {$cotizacion["cust_name"]}<br><br>

                     Forma de Pago : {$cotizacion["pay_title"]}<br>
                     Despacho      : {$cotizacion["req_despacho_desc"]}<br><br>

                     Neto          : {$Neto}<br>
                     Iva           : {$Iva}<br>
                     Total         : {$Total}<br>
                  <br></a>
                  Vendedor<br>
                  {$cotizacion["vendedor"]}<br>";
      sendExternalMail($title, $body, $mail["user_mail"], $mail["user_firstname"]." ".$mail["user_lastname"], "", "");
   }

}



//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["req_cust_street"]        = trim(addslashes($_REQUEST["req_cust_street"]));
   $_REQUEST["req_cust_countryid"]     = (int)$_REQUEST["country"];
   $_REQUEST["req_cust_regionid"]      = (int)$_REQUEST["regions"];
   $_REQUEST["req_cust_provinciaid"]   = (int)$_REQUEST["provincias"];
   $_REQUEST["req_cust_comunaid"]      = (int)$_REQUEST["comunas"];

   $currtme = time();
   $sql = " update offers
            set
            req_cust_street      = '{$_REQUEST["req_cust_street"]}',
            req_cust_countryid   = {$_REQUEST["req_cust_countryid"]},
            req_cust_regionid    = {$_REQUEST["req_cust_regionid"]},
            req_cust_provinciaid = {$_REQUEST["req_cust_provinciaid"]},
            req_cust_comunaid    = {$_REQUEST["req_cust_comunaid"]},
            req_updusr           = {$_SESSION["user_id"]},
            req_upddat           = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
}
//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.country_name, t9.name, t10.nombre, t11.pro_name
         from offers t1
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN country t8          ON t1.req_cust_countryid   = t8.id
         LEFT OUTER JOIN regions t9          ON t1.req_cust_regionid    = t9.id
         LEFT OUTER JOIN comunas t10         ON t1.req_cust_comunaid    = t10.id
         LEFT OUTER JOIN provincias t11      ON t1.req_cust_provinciaid = t11.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
$posdata  = getOfferPos($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
foreach($posdata AS $posdatarow)
{
   $idx1 = $posdatarow["item_id"];
   $idx2 = $posdatarow["item_pos"];
   $_POSDATA[$idx1][$idx2] = $posdatarow;
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{  
   $currtme = time();
   if(count($_REQUEST["positemadd"]) > 0)
   {
      $sql_rut = str_replace(".", "", $headdata["req_cust_rut"]);
      $sql = " select id
               from customer
               where
               cust_status > 0 and
               REPLACE(cust_rut,'.','') like '{$sql_rut}'";
      $custid = $CON->select($sql);
      $custid = $custid[0]["id"];

      $cust_rut            = trim(addslashes($headdata["req_cust_rut"]));
      $cust_company        = trim(addslashes($headdata["req_cust_company"]));
      $cust_street         = trim(addslashes($headdata["req_cust_street"]));
      $cust_phone          = trim(addslashes($headdata["req_cust_phone"]));
      $cust_email          = trim(addslashes($headdata["req_cust_email"]));
      $req_cust_fax        = trim(addslashes($headdata["req_cust_fax"]));
      $cust_countryid      = (int)$headdata["req_cust_countryid"];
      $cust_regionid       = (int)$headdata["req_cust_regionid"];
      $cust_provinciaid    = (int)$headdata["req_cust_provinciaid"];
      $cust_comunaid       = (int)$headdata["req_cust_comunaid"];
      $cust_paymentid      = (int)$headdata["req_paymentid"];
      $cust_catid          = (int)$headdata["req_cust_catid"];

      //'{$cust_phone}', '{$cust_email}'
      if(!(int)$custid)
      {
         $sql = " insert into customer
                  (cust_company, cust_street, cust_rut, cust_name,
                   cust_countryid, cust_regionid, cust_comunaid,
                   cust_paymentid, cust_crtusr, cust_crtdat, cust_provinciaid, cust_catid)
                  VALUES
                  ('{$cust_company}', '{$cust_street}', '{$cust_rut}', '{$cust_company}',
                    {$cust_countryid}, {$cust_regionid}, {$cust_comunaid},
                    {$cust_paymentid}, {$_SESSION["user_id"]}, {$currtme}, {$cust_provinciaid}, {$cust_catid})";
         $res = $CON->no_result($sql);
         if($res)
            $custid = mysql_insert_id();
      }
      else
      {
         $sql = " update customer
                  set
                  cust_company      = '{$cust_company}',
                  cust_street       = '{$cust_street}',
                  cust_name         = '{$cust_company}',
                  cust_countryid    = {$cust_countryid},  
                  cust_regionid     = {$cust_regionid},
                  cust_comunaid     = {$cust_comunaid},
                  cust_paymentid    = {$cust_paymentid},
                  cust_provinciaid  = {$cust_provinciaid}, 
                  cust_catid        = {$cust_catid}
                  where
                  id = {$custid}";
         $CON->no_result($sql);
      }

      if((int)$custid)
      {
         $sql = " select *
                  from customer_contacts
                  where
                  add_cust_id = {$custid} and
                  add_email   = '{$cust_email}'";
         $contactexists = $CON->select($sql);
         if((int)$contactexists[0]["id"])
         {
            $sql = " update customer_contacts
                     set
                     add_firstname  = '{$req_cust_fax}',
                     add_phone      = '{$cust_phone}'
                     where
                     id = {$contactexists[0]["id"]}";
            $CON->no_result($sql);
         }
         else
         {
            $sql = " insert into customer_contacts
                     (add_cust_id, add_firstname, add_phone, add_email)
                     VALUES
                     ({$custid}, '{$req_cust_fax}', '{$cust_phone}', '$cust_email')";
            $CON->no_result($sql);
         }

         if((int)$_REQUEST["invcgen"])
         {
            $invc_number         = createTransactionNumber($CON, $headdata["req_company_id"], "invoicesell");
            $invc_date           = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            $invc_delivery_date  = $invc_date;
            $invc_dlv_docnum     = $invc_number;
            $invc_stockchange    = 1;

            //----------------------------------------------------------------------------------
            $sql = " select t1.cust_paymentid, t1.cust_sellerid, t1.cust_transportid, t2.pay_days
                     from customer t1
                     LEFT OUTER JOIN payments t2 ON t1.cust_paymentid = t2.id
                     where
                     t1.id = {$custid}";
            $custdata = $CON->select($sql);
            $custdata = $custdata[0];

            $invc_userid_seller  = (int)$custdata["cust_sellerid"];
            $invc_userid_cashing = (int)$custdata["cust_sellerid"];
            $invc_paymentid      = (int)$custdata["cust_paymentid"];
            $invc_paymentdays    = (int)$custdata["pay_days"];
            $invc_transportid    = (int)$custdata["cust_transportid"];
            $invc_estpay_date    = 0;

            if($invc_paymentid)
            {
               $tmp_estpay       = time() + ($invc_paymentdays * 86400);
               $invc_estpay_date = mktime(15, 0, 0, date('m', $tmp_estpay), date('d', $tmp_estpay), date('Y', $tmp_estpay));
            }
   
            //----------------------------------------------------------------------------------
            $currtme = time();
            $sql = " insert into invoices_sell
                     (invc_number, invc_cust_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date,
                      invc_taxes, invc_type, invc_stockchange, invc_userid_seller, invc_userid_cashing,
                      invc_paymentid, invc_transportid, invc_delivery_date, invc_dlv_docnum, invc_estpay_date,
                      invc_docnumber, invc_crtdat, invc_crtusr, invc_desc)
                     VALUES
                     ('{$invc_number}', {$custid}, {$headdata["req_company_id"]},
                       {$headdata["req_shop_id"]}, {$invc_date}, {$invc_date}, 1, 3,
                       {$invc_stockchange}, {$invc_userid_seller}, {$invc_userid_cashing}, {$invc_paymentid},
                       {$invc_transportid}, {$invc_delivery_date}, '{$invc_dlv_docnum}', {$invc_estpay_date},
                       '{$invc_number}', {$currtme}, {$_SESSION["user_id"]},
                       'COTIZACION {$headdata["req_number"]}')";
            $ires = $CON->no_result($sql);
            if($ires)
            {
               $new_invc_id = mysql_insert_id();

               $sql = " insert into invoices_sell_parts
                        (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
                        VALUES
                        ({$new_invc_id}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
               $pres = $CON->no_result($sql);
               if($pres)
               {
                  $new_part_id   = mysql_insert_id();
                  $poscounter    = 0;
                  foreach($_REQUEST["positemadd"] AS $positemaddrow)
                  {
                     $idxarr     = explode("-", $positemaddrow);
                     $item_id    = (int)$idxarr[0];
                     $item_pos   = (int)$idxarr[1];
                     $item_amt   = (float)round($idxarr[2],2);
                     $item_netto = (float)round($idxarr[3],2);
                     $_POSROW    = $_POSDATA[$item_id][$item_pos];

                     $item_sellprice_taxes = $item_netto / 100 * $_POSROW["item_sellprice_taxes_perc"];
                     $sql_sellprice        = $item_netto + $item_sellprice_taxes;

                     $xitem_desc       = trim(addslashes($_POSROW["item_desc"]));
                     $xitem_compdesc   = trim(addslashes($_POSROW["item_compdesc"]));
                     $item_adddesc     = $xitem_compdesc;
                     if($_POSROW["item_type"] == 'manual')
                        $item_adddesc = '';

                     //----------------------------------------------------------------------------------
                     $sql = " insert into invoices_sell_parts_items
                              (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                               item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                               item_st_id, item_desc, item_adddesc)
                              VALUES
                              ({$new_invc_id}, {$new_part_id}, {$item_id}, {$poscounter}, {$item_amt}, '{$_POSROW["item_type"]}',
                               {$sql_sellprice}, {$_POSROW["item_sellprice_taxes_perc"]}, {$item_netto}, {$item_sellprice_taxes},
                               0, '{$xitem_desc} {$xitem_compdesc}', '{$item_adddesc}')";
                     $CON->no_result($sql);

                     recalcOrderItem($CON, $new_invc_id, $item_id, $poscounter, true, "INVOICE", $new_part_id);

                     $poscounter++;
                  }
                  recalcOrder($CON, $new_invc_id, "INVOICE");

                  ?>
                  <script language="JavaScript">
                     parent.location.href = '/index.php?mid=751&exec=edit&subcatexec=basic&id=<?=$new_invc_id?>';
                  </script>
                  <?php
                  exit;
               }
            }
         }
         else
         {        
            $poscounter = 0;
            $nvsuffix   = 1;
            $req_num    = createInternalAltaNumberOrder($CON, $nvsuffix);
            $first      = true;
            foreach($_REQUEST["positemadd"] AS $positemaddrow)
            {
               //----------------------------------------------------------------------------------
               if($nvsuffix > 1)
               {
                  $xreq_num   = explode("-", $req_num);
                  $req_num    = $xreq_num[0]."-".$xreq_num[1]."-".$nvsuffix;
               }
               
               $nvsuffix++;

               //----------------------------------------------------------------------------------
               $req_despacho_desc = trim(addslashes($headdata["req_plazo_entrega"]));

               if((int)$_REQUEST["itemmode"] || (!(int)$_REQUEST["itemmode"] && $first))
               {
                  $sql = " insert into orders
                           (req_number, req_company_id, req_shop_id, req_cust_id, req_paymentid,
                            req_userid_seller, req_userid_cashing, req_taxes, req_transportid,
                            req_crtdat, req_crtusr, req_isinvcbrutto, req_isreserva, req_crtdat_first,
                            req_offerid, req_despacho_desc)
                           VALUES
                           ('{$req_num}', {$headdata["req_company_id"]}, {$headdata["req_shop_id"]}, {$custid},
                             {$cust_paymentid}, {$headdata["req_crtusr"]}, {$headdata["req_crtusr"]}, 1,
                             0, {$currtme}, {$_SESSION["user_id"]}, 0, 0, {$currtme}, {$_REQUEST["id"]},
                             '{$req_despacho_desc}')";
                  $res = $CON->no_result($sql);
               }
               if($res)
               {
                  if((int)$_REQUEST["itemmode"] || (!(int)$_REQUEST["itemmode"] && $first))
                     $order_id = mysql_insert_id();

                  $first = false;

                  if((int)$headdata["req_prices_cnt"] == 1)
                  {
                     $sql = " update orders
                              set
                              req_discount_perc = {$headdata["req_discount_perc"]}
                              where
                              id = {$order_id}";
                     $CON->no_result($sql);
                  }

                  
                  $idxarr     = explode("-", $positemaddrow);
                  $item_id    = (int)$idxarr[0];
                  $item_pos   = (int)$idxarr[1];
                  $item_amt   = (float)round($idxarr[2],2);
                  $item_netto = (float)round($idxarr[3],2);
                  $_POSROW    = $_POSDATA[$item_id][$item_pos];

                  $item_sellprice_taxes = $item_netto / 100 * $_POSROW["item_sellprice_taxes_perc"];
                  $sql_sellprice        = $item_netto + $item_sellprice_taxes;


                  $xitem_desc       = trim(addslashes($_POSROW["item_desc"]));
                  $xitem_compdesc   = trim(addslashes($_POSROW["item_compdesc"]));

                  $sql = " insert into orders_items
                           (req_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                            item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes, item_desc,
                            item_compdesc,
                            fab_type, fab_printtype, fab_med_width, fab_med_height, fab_med_fuelle, fab_print_width,
                            fab_print_height, fab_manilla_length, fab_mat_fabric_color, fab_mat_manilla_color, fab_print_colors_front_1,
                            fab_print_colors_back_1, fab_print_colors_front_2, fab_print_colors_back_2, fab_print_colors_front_3,
                            fab_print_colors_back_3, fab_print_colors_front_4, fab_print_colors_back_4, fab_print_colors_front_5,
                            fab_print_colors_back_5)
                           VALUES
                           ({$order_id}, {$item_id}, {$poscounter}, {$item_amt}, '{$_POSROW["item_type"]}',
                            {$sql_sellprice}, {$_POSROW["item_sellprice_taxes_perc"]}, {$item_netto},
                            {$item_sellprice_taxes}, '{$xitem_desc}', '{$xitem_compdesc}',
                            '{$_POSROW["fab_type"]}', '{$_POSROW["fab_printtype"]}',
                            {$_POSROW["fab_med_width"]}, {$_POSROW["fab_med_height"]}, {$_POSROW["fab_med_fuelle"]},
                            {$_POSROW["fab_print_width"]}, {$_POSROW["fab_print_height"]}, {$_POSROW["fab_manilla_length"]},
                            {$_POSROW["fab_mat_fabric_color"]}, {$_POSROW["fab_mat_manilla_color"]},
                            {$_POSROW["fab_print_colors_front_1"]}, {$_POSROW["fab_print_colors_back_1"]},
                            {$_POSROW["fab_print_colors_front_2"]}, {$_POSROW["fab_print_colors_back_2"]},
                            {$_POSROW["fab_print_colors_front_3"]}, {$_POSROW["fab_print_colors_back_3"]},
                            {$_POSROW["fab_print_colors_front_4"]}, {$_POSROW["fab_print_colors_back_4"]},
                            {$_POSROW["fab_print_colors_front_5"]}, {$_POSROW["fab_print_colors_back_5"]})";
                  $CON->no_result($sql);

                  if((int)$_POSROW["item_fabricate_act"])
                  {
                     $sql = " update orders
                              set
                              req_isfabricate = 1
                              where
                              id = {$order_id}";
                     $CON->no_result($sql);
                  }

                  if((int)$_POSROW["item_fabricate_extern_act"])
                  {
                     $sql = " update orders
                              set
                              req_isfabricate_extern = 1
                              where
                              id = {$order_id}";
                     $CON->no_result($sql);
                  }

                  recalcOrderItem($CON, $order_id, $item_id, $poscounter, true, "");

                  if(!(int)$_REQUEST["itemmode"])
                     $poscounter++;

                  recalcOrder($CON, $order_id);
               }
            }

            if((int)$order_id)
            {  
               MailEnvioCorreoAvisoCierre($CON, $order_id);
               ?>
               <script language="JavaScript">
                  parent.location.href = '/index.php?mid=689&setstatus=1,2,3';
               </script>
               <?php
               exit;
            }
         }
      }
   }
}

$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
<?php
generateCountryJS($countries, $regions, $comunas, $provincias);
?>
</script>
<div id="idx_comunaout" style="display:none"></div>
<div style="height:3px"></div>
<form action="iframe.fancy.php" method="post" name="form_reqpos" class="fokusfirst"
onsubmit="return checkform(new Array(this.req_cust_street, this.comunas, this.provincias, this.regions, this.country))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="invcgen" value="">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_rowl">Confirmar dirección</td>
   <td class="content_row">
      <input name="req_cust_street" type="text" class="text" style="width:100%" value="<?=$headdata["req_cust_street"]?>" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Comuna</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="jqUnibagSetComuna(this.value, 'offer')">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         $allcomunas = getAllComunas($CON);
         foreach($allcomunas AS $comuna)
         {  ?>
            <option value="<?=$comuna["id"]?>"
            <?php if($comuna["id"] == $headdata["req_cust_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Provincia</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setComunas(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$headdata["req_cust_regionid"])
         {
            foreach($provincias as $provincia)
            {
               if($provincia["region_id"] == $headdata["req_cust_regionid"])
               {  ?>
                  <option value="<?=$provincia["id"]?>"
                  <?php if($provincia["id"] == $headdata["req_cust_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Región</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="regions" id="regions"
      onchange="setProvincias(this.value);"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$headdata["req_cust_countryid"])
         {
            foreach($regions as $region)
            {
               if($region["id_pais"] == $headdata["req_cust_countryid"])
               {  ?>
                  <option value="<?=$region["id"]?>"
                  <?php if($region["id"] == $headdata["req_cust_regionid"]) echo "selected"?>><?=$region["name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">País</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="country" id="country"
      onchange="setRegions(this.value)"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($countries as $country)
         {  ?>
            <option value="<?=$country["id"]?>"
            <?php if($country["id"] == $headdata["req_cust_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25">
   <col>
   <col width="80">
   <col width="100">
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header">
      <input type="checkbox" name="xdummy" value="1"
      onclick="$('.clsmarkchks').attr('checked', this.checked)">
   </td>
   <td class="content_tbl_header">Artículo</td>
   <td class="content_tbl_header" align="center">Cantidad</td>
   <td class="content_tbl_header" align="right">Precio/Unit.</td>
   <td class="content_tbl_header" align="right">Subtotal</td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   $_XMODES[(int)$posdata[$x]["item_fabricate_act"]] = 1;
   
if((int)$_XMODES[0] && (int)$_XMODES[1])
{
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      if(!(int)$posdata[$x]["item_fabricate_act"])
         $_XMODESDISABLED[$posdata[$x]["item_id"]][$posdata[$x]["item_pos"]] = 1;
   }
}

$_ITEMMODE = 0;
if((int)$_XMODES[1])
   $_ITEMMODE = 1;
?>
<input type="hidden" name="itemmode" value="<?=$_ITEMMODE?>">
<?php
$y = 0;
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   if((int)$headdata["req_prices_cnt"] > 1)
   {
      for($xx = 1; $xx <= $headdata["req_prices_cnt"]; $xx++)
      {  ?>
         <tr bgcolor="<?=getRowColor($y)?>">
            <td class="content_row_os" align="center">
               <input type="checkbox" name="positemadd[]" class="clsmarkchks" <?if((int)$_XMODESDISABLED[$posdata[$x]["item_id"]][$posdata[$x]["item_pos"]]) echo "disabled"?>
               value="<?=$posdata[$x]["item_id"]?>-<?=$posdata[$x]["item_pos"]?>-<?=(float)$headdata["req_prices_ccval{$xx}"]?>-<?=(float)$posdata[$x]["item_sellprice_netto_ccval{$xx}"]?>">
            </td>
            <td class="content_row_os">
               <?=$posdata[$x]["item_number_prod"]?> - <?=$posdata[$x]["item_title"]?>
               <?php
               if((int)$posdata[$x]["item_fabricate_act"])
               {
                  $_HASFABITEMS = true;
                  ?>
                  <br>
                  <?php
                  if($posdata[$x]["item_img_hash"] != "")
                  {  ?>
                     <img src="/docs.offer/<?=$posdata[$x]["item_img_hash"]?>" height="50" width="50"
                     style="border:1px solid #CCCCCC;float:left;margin-right:10px">
                     <?php
                  }
                  ?>
                  <font style="color:navy;font-size:10px"><?=$posdata[$x]["item_compdesc"]?></font>
                  <?php
               }
               ?>
            </td>
            <td class="content_row_os" align="center"><?=printPrice($headdata["req_prices_ccval{$xx}"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["item_sellprice_netto_ccval{$xx}"],2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($headdata["req_prices_ccval{$xx}"] * $posdata[$x]["item_sellprice_netto_ccval{$xx}"],2)?></td>
         </tr>
         <?php
         $y++;
      }
   }
   else
   {  ?>
      <tr bgcolor="<?=getRowColor($y)?>">
         <td class="content_row_os" align="center">
            <input type="checkbox" name="positemadd[]" class="clsmarkchks" <?if((int)$_XMODESDISABLED[$posdata[$x]["item_id"]][$posdata[$x]["item_pos"]]) echo "disabled"?>
            value="<?=$posdata[$x]["item_id"]?>-<?=$posdata[$x]["item_pos"]?>-<?=(float)$posdata[$x]["item_amount"]?>-<?=(float)$posdata[$x]["item_sellprice_netto"]?>">
         </td>
         <td class="content_row_os">
            <?=$posdata[$x]["item_number_prod"]?> - <?=$posdata[$x]["item_title"]?>
            <?php
            if((int)$posdata[$x]["item_fabricate_act"])
            {
               $_HASFABITEMS = true;
               ?>
               <br>
               <?php
               if($posdata[$x]["item_img_hash"] != "")
               {  ?>
                  <img src="/docs.offer/<?=$posdata[$x]["item_img_hash"]?>" height="50" width="50"
                  style="border:1px solid #CCCCCC;float:left;margin-right:10px">
                  <?php
               }
               ?>
               <font style="color:navy;font-size:10px"><?=$posdata[$x]["item_compdesc"]?></font>
               <?php
            }
            ?>
         </td>
         <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["item_amount"],2)?></td>
         <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["item_sellprice_netto"],2)?></td>
         <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["item_sellprice_netto_dsc"],4)?></td>
      </tr>
      <?php
      $y++;
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <style>
         #mensajeEnvio {
            display: none;
            font-weight: bold;
            color: green;
            animation: parpadeo 1s infinite;
         }

         @keyframes parpadeo {
            0% { opacity: 1; }
            50% { opacity: 0; }
            100% { opacity: 1; }
         }
   </style>
   <div id="mensajeEnvio" style="display: none; font-weight: bold; color: green;">
        Espere un momento mientras se genere Confirmación de Compras ...
   </div>
   <td id="bloqueCorreo" align="center">
      <?php
      printButton("Generar Confirmación de compra para los productos seleccionados", "postnav_save", "javascript: deactivateFormChange()", "iniciarProceso(this);enviarCorreo();submitForm(document.form_reqpos)", "tick-circle-frame");
      ?>
   </td>
   <script>
      function iniciarProceso(btn) 
      {
         document.body.classList.add('loading');
      }

      function enviarCorreo()
      {
         const mensaje = document.getElementById('mensajeEnvio');

         mensaje.style.display = 'block';

         // document.getElementById('mensajeEnvio').style.display = 'block';
         document.getElementById('bloqueCorreo').style.display = 'none';
         // Simula procesamiento (reemplaza con tu lógica real)
         /*
         setTimeout(() => {
            document.getElementById('mensajeEnvio').style.display = 'none';
            alert("Correo enviado correctamente"); // Aquí podrías manejar una respuesta real
         }, 3000);
            */
      }
   </script>

</tr>
<?php
if(!$_HASFABITEMS)
{  ?>
   <tr>
      <td align="center">
         <br>
         <?php
         printButton("Generar Factura para los productos seleccionados", "postnav_save", "javascript: deactivateFormChange()", "document.form_reqpos.invcgen.value='1';submitForm(document.form_reqpos)", "document");
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>