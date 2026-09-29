<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
if((int)$_REQUEST["setnewcatid"])
{
   $sql = " select cat_id
            from item_productcats
            where
            item_id = {$_REQUEST["id"]}";
   $currcatid = $CON->select($sql);
   $currcatid = (int)$currcatid[0]["cat_id"];

   $currtme   = time();
   if($currcatid != (int)$_REQUEST["setnewcatid"])
   {
      $sql = " delete from item_productcats
               where
               item_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      $sql = " insert into item_productcats
               (item_id, cat_id) VALUES ({$_REQUEST["id"]}, {$_REQUEST["setnewcatid"]}) ";
      $CON->no_result($sql);

      /*
      $sql = " select *
               from productcats
               where
               id = {$_REQUEST["setnewcatid"]}";
      $catdata = $CON->select($sql);
      $catdata = $catdata[0];

      $sql = " select t1.*, t2.cat_prefix
               from item t1
               INNER JOIN item_productcats t3   ON t1.id = t3.item_id
               INNER JOIN productcats t2        ON t3.cat_id = t2.id
               where
               t3.cat_id = {$_REQUEST["setnewcatid"]} and
               t1.item_number_prod like '{$catdata["cat_prefix"]}%'
               order by t1.id";
      $items = $CON->select($sql);
      $maxid = 1;
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $inumb   = $items[$x]["item_number_prod"];
         $thisid  = str_replace($items[$x]["cat_prefix"],"",$inumb);

         if($thisid +1 >= $maxid)
            $maxid = $thisid +1;

         if(!(int)$items[$x]["item_status"])
            $_ITEMIDS[$thisid] = 1;
         else
            $_ITEMUSD[$thisid] = 1;

      }
      $_ITEMIDS[$maxid] = 1;

      $_NEWPRODNUM = "";
      foreach(array_keys($_ITEMIDS) AS $newitemid)
      {
         if(!(int)$_ITEMUSD[$newitemid])
         {
            $_NEWPRODNUM = $catdata["cat_prefix"].sprintf("%04s",$newitemid);
         }
      }

      if($_NEWPRODNUM != "")
      {
         $sql = " update item
                  set
                  item_number_prod = '{$_NEWPRODNUM}',
                  item_updusr      = {$_SESSION["user_id"]},
                  item_upddat      = {$currtme}
                  where
                  id = {$_REQUEST["id"]}";
         $res = $CON->no_result($sql);
         $savemsg = getSaveMessage($res);
      }
      */

      unset($_ITEMIDS);
      unset($_ITEMUSD);
      unset($catdata);
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["item_title"]                   = trim(addslashes($_REQUEST["item_title"]));
   $_REQUEST["item_desc"]                    = trim(addslashes($_REQUEST["item_desc"]));
   $_REQUEST["item_invoice_note"]            = trim(addslashes($_REQUEST["item_invoice_note"]));
   $_REQUEST["item_invoicebuy_note"]         = trim(addslashes($_REQUEST["item_invoicebuy_note"]));
   $_REQUEST["item_number_prod"]             = trim(addslashes($_REQUEST["item_number_prod"]));
   $_REQUEST["item_sellprice_calc_type"]     = trim(addslashes($_REQUEST["item_sellprice_calc_type"]));
   $_REQUEST["item_fabricate_prefix"]        = trim(addslashes($_REQUEST["item_fabricate_prefix"]));
   $_REQUEST["item_released"]                = (int)$_REQUEST["item_released"];
   $_REQUEST["item_unit"]                    = (int)$_REQUEST["item_unit"];
   $_REQUEST["item_sellable"]                = (int)$_REQUEST["item_sellable"];
   $_REQUEST["item_purchasable"]             = (int)$_REQUEST["item_purchasable"];
   $_REQUEST["item_sellprice_calc"]          = (int)$_REQUEST["item_sellprice_calc"];
   $_REQUEST["item_sell_withotheritems"]     = (int)$_REQUEST["item_sell_withotheritems"];
   $_REQUEST["item_charges_act"]             = (int)$_REQUEST["item_charges_act"];
   $_REQUEST["item_sell_amountmin"]          = getPrice($_REQUEST["item_sell_amountmin"],2);
   $_REQUEST["item_sell_nodsc"]              = (int)$_REQUEST["item_sell_nodsc"];
   $_REQUEST["item_weight"]                  = getPrice($_REQUEST["item_weight"],2);
   $_REQUEST["item_weight_price"]            = getPrice($_REQUEST["item_weight_price"]);
   $_REQUEST["item_unit_amount"]             = getPrice($_REQUEST["item_unit_amount"],2);
   $_REQUEST["item_unitembalaje_amount"]     = getPrice($_REQUEST["item_unitembalaje_amount"],2);
   $_REQUEST["item_sellprice_calc_perc"]     = getPrice($_REQUEST["item_sellprice_calc_perc"],2);
   $_REQUEST["item_unitbuy_act"]             = (int)$_REQUEST["item_unitbuy_act"];
   $_REQUEST["item_unitbuy"]                 = (int)$_REQUEST["item_unitbuy"];
   $_REQUEST["item_unitbuy_amount"]          = getPrice($_REQUEST["item_unitbuy_amount"],2);
   $_REQUEST["item_ubicacion"]               = (int)$_REQUEST["item_ubicacion"];
   $_REQUEST["item_factorshop"]              = (int)$_REQUEST["item_factorshop"];
   $_REQUEST["item_fabricate_act"]           = (int)$_REQUEST["item_fabricate_act"];
   $_REQUEST["item_codcont"]                 = (int)$_REQUEST["item_codcont"];
   $_REQUEST["item_codcont_prod"]            = (int)$_REQUEST["item_codcont_prod"];
   $_REQUEST["item_reg_width"]               = (int)getPrice(trim($_REQUEST["item_reg_width"]));
   $_REQUEST["item_reg_gsm"]                 = (int)getPrice(trim($_REQUEST["item_reg_gsm"]));
   $_REQUEST["item_reg_length"]              = (int)getPrice(trim($_REQUEST["item_reg_length"]));
   $_REQUEST["item_reg_kg"]                  = (float)getPrice(trim($_REQUEST["item_reg_kg"]),2);
   $_REQUEST["item_fabricate_extern_act"]    = (int)$_REQUEST["item_fabricate_extern_act"];
   $_REQUEST["item_prodwrk_act"]             = (int)$_REQUEST["item_prodwrk_act"];
   $_REQUEST["item_ventaonline_act"]         = (int)$_REQUEST["item_ventaonline_act"];
   $_REQUEST["item_prodcalc_fuelle_act"]     = (int)$_REQUEST["item_prodcalc_fuelle_act"];
   $_REQUEST["item_fabricate_fabrictext"]    = trim(addslashes($_REQUEST["item_fabricate_fabrictext"]));
   $_REQUEST["item_fabricate_fuelletext"]    = trim(addslashes($_REQUEST["item_fabricate_fuelletext"]));

   $_REQUEST["item_des_personalizada"]       = (int)$_REQUEST["item_des_personalizada"];
   $_REQUEST["item_controla_stock"]          = (int)$_REQUEST["item_controla_stock"];


   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_purchasable"] || !$_REQUEST["item_sellable"])
      $_REQUEST["item_sellprice_calc"] = 0;
   
   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_sellprice_calc"])
   {
      $_REQUEST["item_sellprice_calc_perc"] = 0.00;
      $_REQUEST["item_sellprice_calc_type"]  = "";
   }
   else
   {
      if($_REQUEST["item_sellprice_calc_type"] == "CAT")
      {
         $sql = " select cat_calc_price_perc
                  from productcats
                  where
                  id = {$_REQUEST["item_catids"]}";
         $calc_price_perc = $CON->select($sql);
         $_REQUEST["item_sellprice_calc_perc"] = $calc_price_perc[0]["cat_calc_price_perc"];
      }

      $sql = " select t1.item_costprice_netto
               from item_suppliers t1
               where
               t1.item_id        = {$_REQUEST["id"]} and
               t1.item_supp_act  = 1";
      $suppinfo = $CON->select($sql);
      $suppinfo = $suppinfo[0];


      $_REQUEST["item_sellprice_netto"] = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", (float)$suppinfo["item_costprice_netto"] + ((float)$suppinfo["item_costprice_netto"] / 100 * $_REQUEST["item_sellprice_calc_perc"]));
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["item_sellprice_netto"]         = getPrice($_REQUEST["item_sellprice_netto"]);
   $_REQUEST["item_sellprice_taxes_perc"]    = getPrice($_REQUEST["item_sellprice_taxes_perc"],2);
   $_REQUEST["item_sellprice_taxes"]         = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $_REQUEST["item_sellprice_netto"] / 100 * $_REQUEST["item_sellprice_taxes_perc"]);
   $_REQUEST["item_sellprice_brutto"]        = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $_REQUEST["item_sellprice_netto"] + $_REQUEST["item_sellprice_taxes"]);

   //----------------------------------------------------------------------------------
   if(!$_REQUEST["item_sellable"])
   {
      $_REQUEST["item_sellprice_brutto"]     = 0.00;
      $_REQUEST["item_sellprice_taxes"]      = 0.00;
      $_REQUEST["item_sellprice_netto"]      = 0.00;
      $_REQUEST["item_sellprice_calc_perc"]  = 0.00;
      $_REQUEST["item_sellprice_calc_type"]  = "";
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $_REQUEST["item_number"] = createNumberSystem($CON, "ITEMNUMBER");

      $sql = " select count(*) 'cc'
               from item
               where
               item_status > 0 and
               item_number_prod = '{$_REQUEST["item_number_prod"]}'";
      $itemnumexists = $CON->select($sql);
      $itemnumexists = (int)$itemnumexists[0]["cc"];
      if((int)$itemnumexists)
      {  ?>
         <script language="JavaScript">
            alert('EL CODIGO INTERNO "<?=$_REQUEST["item_number_prod"]?>" YA EXISTE PARA OTRO PRODUCTO.\nEL PRODUCTO NO SE GUARDO!!!');
            location.href='index.php?mid=<?=$_REQUEST["mid"]?>';
         </script>
         <?php
         exit;
      }
      
      $sql = " insert into item
               (item_title, item_desc, item_released, item_number, item_number_prod, item_sellprice_brutto, 
               item_sellprice_taxes_perc, item_sellprice_taxes, item_sellprice_netto,
               item_unit, item_unit_amount, item_sellable, item_purchasable, item_sellprice_calc,
               item_sellprice_calc_perc, item_sellprice_calc_type, item_weight, item_weight_price,
               item_invoice_note, item_invoicebuy_note, item_sell_withotheritems, item_sell_amountmin, item_sell_nodsc,
               item_charges_act, item_unitbuy_act, item_unitbuy, item_unitbuy_amount, item_crtusr, item_crtdat,
               item_unitembalaje_amount, item_ubicacion, item_nameshop, item_factorshop, item_fabricate_act,
               item_fabricate_prefix, item_fabricate_extern_act, item_fabricate_fabrictext, item_codcont,
               item_fabricate_fuelletext, item_codcont_prod, item_prodwrk_act, item_ventaonline_act,
               item_prodcalc_fuelle_act,item_des_personalizada,item_controla_stock)
               VALUES
               ('{$_REQUEST["item_title"]}', '{$_REQUEST["item_desc"]}', {$_REQUEST["item_released"]},
                '{$_REQUEST["item_number"]}', '{$_REQUEST["item_number_prod"]}', {$_REQUEST["item_sellprice_brutto"]}, 
                 {$_REQUEST["item_sellprice_taxes_perc"]},
                 {$_REQUEST["item_sellprice_taxes"]}, {$_REQUEST["item_sellprice_netto"]}, {$_REQUEST["item_unit"]},
                 {$_REQUEST["item_unit_amount"]}, {$_REQUEST["item_sellable"]}, {$_REQUEST["item_purchasable"]}, 
                 {$_REQUEST["item_sellprice_calc"]}, {$_REQUEST["item_sellprice_calc_perc"]}, '{$_REQUEST["item_sellprice_calc_type"]}',
                 {$_REQUEST["item_weight"]}, {$_REQUEST["item_weight_price"]}, '{$_REQUEST["item_invoice_note"]}', '{$_REQUEST["item_invoicebuy_note"]}',
                 {$_REQUEST["item_sell_withotheritems"]}, {$_REQUEST["item_sell_amountmin"]}, {$_REQUEST["item_sell_nodsc"]}, {$_REQUEST["item_charges_act"]},
                 {$_REQUEST["item_unitbuy_act"]}, {$_REQUEST["item_unitbuy"]}, {$_REQUEST["item_unitbuy_amount"]},
                 {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["item_unitembalaje_amount"]}, {$_REQUEST["item_ubicacion"]}, '{$_REQUEST["item_nameshop"]}',
                 {$_REQUEST["item_factorshop"]}, {$_REQUEST["item_fabricate_act"]}, '{$_REQUEST["item_fabricate_prefix"]}',
                 {$_REQUEST["item_fabricate_extern_act"]}, '{$_REQUEST["item_fabricate_fabrictext"]}', {$_REQUEST["item_codcont"]},
                 '{$_REQUEST["item_fabricate_fuelletext"]}', {$_REQUEST["item_codcont_prod"]}, {$_REQUEST["item_prodwrk_act"]},
                 {$_REQUEST["item_ventaonline_act"]}, {$_REQUEST["item_prodcalc_fuelle_act"]},
                 {$_REQUEST["item_des_personalizada"]}, {$_REQUEST["item_controla_stock"]}               
                 )";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from item
                  where
                  item_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;

         $redirect = true;
      }
      
      $savemsg = getSaveMessage($res);

   }
   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update item
               set
               item_title                 = '{$_REQUEST["item_title"]}',
               item_number_prod           = '{$_REQUEST["item_number_prod"]}',
               item_desc                  = '{$_REQUEST["item_desc"]}',
               item_released              = {$_REQUEST["item_released"]},
               item_sellprice_brutto      = {$_REQUEST["item_sellprice_brutto"]},
               item_sellprice_taxes_perc  = {$_REQUEST["item_sellprice_taxes_perc"]},
               item_sellprice_taxes       = {$_REQUEST["item_sellprice_taxes"]},
               item_sellprice_netto       = {$_REQUEST["item_sellprice_netto"]},
               item_sellprice_calc        = {$_REQUEST["item_sellprice_calc"]},
               item_sellprice_calc_perc   = {$_REQUEST["item_sellprice_calc_perc"]},
               item_sellprice_calc_type   = '{$_REQUEST["item_sellprice_calc_type"]}',
               item_sell_withotheritems   = {$_REQUEST["item_sell_withotheritems"]},
               item_sell_amountmin        = {$_REQUEST["item_sell_amountmin"]},
               item_sell_nodsc            = {$_REQUEST["item_sell_nodsc"]},
               item_sellable              = {$_REQUEST["item_sellable"]},
               item_purchasable           = {$_REQUEST["item_purchasable"]},
               item_weight                = {$_REQUEST["item_weight"]},
               item_charges_act           = {$_REQUEST["item_charges_act"]},
               item_weight_price          = {$_REQUEST["item_weight_price"]},
               item_invoice_note          = '{$_REQUEST["item_invoice_note"]}',
               item_invoicebuy_note       = '{$_REQUEST["item_invoicebuy_note"]}',
               item_unit                  = {$_REQUEST["item_unit"]},
               item_unit_amount           = {$_REQUEST["item_unit_amount"]},
               item_unitbuy_act           = {$_REQUEST["item_unitbuy_act"]},
               item_unitbuy               = {$_REQUEST["item_unitbuy"]},
               item_unitbuy_amount        = {$_REQUEST["item_unitbuy_amount"]},
               item_unitembalaje_amount   = {$_REQUEST["item_unitembalaje_amount"]},
               item_ubicacion             = {$_REQUEST["item_ubicacion"]},
               item_nameshop              = '{$_REQUEST["item_nameshop"]}',
               item_factorshop            = {$_REQUEST["item_factorshop"]},
               item_reg_width             = {$_REQUEST["item_reg_width"]},
               item_reg_gsm               = {$_REQUEST["item_reg_gsm"]},
               item_reg_length            = {$_REQUEST["item_reg_length"]},
               item_reg_kg                = {$_REQUEST["item_reg_kg"]},
               item_fabricate_act         = {$_REQUEST["item_fabricate_act"]},
               item_fabricate_prefix      = '{$_REQUEST["item_fabricate_prefix"]}',
               item_fabricate_extern_act  = {$_REQUEST["item_fabricate_extern_act"]},
               item_fabricate_fabrictext  = '{$_REQUEST["item_fabricate_fabrictext"]}',
               item_fabricate_fuelletext  = '{$_REQUEST["item_fabricate_fuelletext"]}',
               item_codcont_prod          = {$_REQUEST["item_codcont_prod"]},
               item_codcont               = {$_REQUEST["item_codcont"]},
               item_prodwrk_act           = {$_REQUEST["item_prodwrk_act"]},
               item_ventaonline_act       = {$_REQUEST["item_ventaonline_act"]},
               item_prodcalc_fuelle_act   = {$_REQUEST["item_prodcalc_fuelle_act"]},
               item_updusr                = {$_SESSION["user_id"]},
               item_upddat                = {$currtme},
               item_des_personalizada     = {$_REQUEST["item_des_personalizada"]}, 
               item_controla_stock        = {$_REQUEST["item_controla_stock"]}  
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);

      if($res)
      {

         updateItemStorePrices($CON, $_REQUEST["id"]);
      
         $thisid = $_REQUEST["id"];

         $sql = " delete from item_productcats
                  where
                  item_id = {$thisid}";
         $CON->no_result($sql);
      }


   }

   //----------------------------------------------------------------------------------
   if((int)$thisid)
   {
      $catidx   = (int)$_REQUEST["item_catids"];

      $sql = " insert into item_productcats
               (item_id, cat_id) VALUES ({$thisid}, {$catidx}) ";
      $CON->no_result($sql);
   }

   $sql = " delete from item_suppliers
            where
            item_id  = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "supplier_id_") !== false && (int)$_REQUEST[$reqkey])
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);

         if(strpos($_REQUEST["supplier_id_{$idx}"], "#") !== false)
         {
            $supplier_id   = explode("#", $_REQUEST["supplier_id_{$idx}"]);
            $supplier_id   = (int)$supplier_id[0];
         }
         else
            $supplier_id   = (int)$_REQUEST["supplier_id_{$idx}"];

         $item_code     = trim(addslashes($_REQUEST["item_code_{$idx}"]));
         $item_supp_fob = (int)$_REQUEST["item_supp_fob_{$idx}"];

         $supptax = getSupplierTaxes($CON, $supplier_id);

         if(!(int)$supptax)
         {
            $item_costprice_netto      = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
            $item_costprice_brutto     = $item_costprice_netto;
            $item_costprice_taxes_perc = 0;
            $item_costprice_taxes      = 0;
            $item_costprice_usd        = getPrice($_REQUEST["item_costprice_usd_{$idx}"],4);
         }
         else
         {
            $item_costprice_netto      = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
            $item_costprice_taxes_perc = getPrice($_REQUEST["item_costprice_taxes_perc_{$idx}"],2);
            $item_costprice_taxes      = (float)sprintf("%.2f", $item_costprice_netto / 100 * $item_costprice_taxes_perc);
            $item_costprice_brutto     = (float)sprintf("%.2f", $item_costprice_netto + $item_costprice_taxes);
            $item_costprice_usd        = 0.00;
            $item_supp_fob             = 0;
         }

         
         if($idx == (int)$_REQUEST["item_supp_act"])
            $item_supp_act = 1;
         else
            $item_supp_act = 0;

         $sql = " insert into item_suppliers
                  (item_id, supplier_id, item_code, item_costprice_brutto, item_costprice_taxes_perc,
                   item_costprice_netto, item_costprice_taxes, item_costprice_usd, item_supp_act, item_supp_fob)
                  VALUES
                  ({$_REQUEST["id"]}, {$supplier_id}, '{$item_code}', {$item_costprice_brutto},
                   {$item_costprice_taxes_perc}, {$item_costprice_netto}, {$item_costprice_taxes},
                   {$item_costprice_usd}, {$item_supp_act}, {$item_supp_fob})";
         $CON->no_result($sql);
      }
   }

   $sql = " delete from item_barcodes
            where
            item_id     = {$_REQUEST["id"]} and
            item_type   = 'item'";
   $CON->no_result($sql);
   
   foreach($_REQUEST["item_barcode"] AS $item_barcode)
   {
      $item_barcode = trim(addslashes($item_barcode));
      if($item_barcode != "")
      {
         $sql = " insert into item_barcodes
                  (item_id, item_type, item_barcode)
                  VALUES
                  ({$_REQUEST["id"]}, 'item', '{$item_barcode}')";
         $CON->no_result($sql);
      }
   }

/* Actualiza Datos DEFONTANA */
$token = ObtieneToken($CON, $_SESSION["user_company_id"]);
if (!$token["success"]) {
    $sql = "insert into log_erp(proceso, estatus, detalle)
            values('token',0,'No se pudo obtener token en ingreso de ITEM {$_REQUEST["id"]}')";
    $CON->no_result($sql);
} else {
    $codigo = $_REQUEST['item_number_prod'];
    $name = preg_replace('/[^A-Za-z0-9 ]/', '', $_REQUEST["item_title"]);
    $name = substr($name, 0, 100);
    $brutto = (int)$_REQUEST["item_sellprice_netto"];

    /* Valida si el producto existe en DEFONTANA */
    $parametros = array(
        'code'         => $codigo,
        'status'       => 0,
        'itemsPerPage' => 1,
        'pageNumber'   => 1,
    );

    $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'Getproducts'";
    $url_base = $CON->select($sql);
    $url_base = $url_base[0]['url'];
    $url_completa = $url_base . '?' . http_build_query($parametros);

    $ch = curl_init($url_completa);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: '.$token["token_type"].' '.$token["access_token"]
    ));

    $respuesta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch);
    curl_close($ch);

    $datos = json_decode($respuesta, true);

    /* Validación */
    if (isset($datos['totalItems']) && $datos['totalItems'] == 0) {
        /* Crea Registro en DEFONTANA */
        $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'SaveProduct'";
        $url = $CON->select($sql);
        $url = $url[0]['url'];

        $data = array(
            'code'        => $codigo,
            'name'        => $name,
            'unit'        => "UN",
            'price'       => $brutto,
            'description' => $name,
            'categoryID'  => 2,
            'isService'   => false,
            'usaLotes'    => false
        );
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=UTF-8',
            'Authorization: '.$token["token_type"].' '.$token["access_token"]
        ));

        $respuestaJson = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($ch);
        curl_close($ch);

        $respuestaArray = json_decode($respuestaJson, true);
        $success = isset($respuestaArray['success']) ? $respuestaArray['success'] : false;
        $message = isset($respuestaArray['message']) ? $respuestaArray['message'] : 'Sin mensaje';

        if ($http_code < 200 || $http_code >= 300) {
            $sql = "insert into log_erp(proceso, estatus, detalle)
                    values('SaveProduct',0,'Error HTTP en Item {$_REQUEST["id"]} - Código: {$http_code} : {$message}')";
            $CON->no_result($sql);
        } elseif ($curl_error) {
            $sql = "insert into log_erp(proceso, estatus, detalle)
                    values('SaveProduct',0,'Error cURL en Item {$_REQUEST["id"]} - {$curl_error}')";
            $CON->no_result($sql);
        } else {
            if ($success) {
                $sql = "insert into log_erp(proceso, estatus, detalle)
                        values('SaveProduct',1,'Se creó correctamente Item {$_REQUEST["id"]}')";
                $CON->no_result($sql);
            } else {
                $sql = "insert into log_erp(proceso, estatus, detalle)
                        values('SaveProduct',0,'Error API en Item {$_REQUEST["id"]} - ({$codigo}) : {$message}')";
                $CON->no_result($sql);
            }
        }
    } else 
    {

        $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'UpdateProduct'";
        $url = $CON->select($sql);
        $url = $url[0]['url'];
        
        $data = array(
            'code'         => $codigo,
            'externalCode' => $codigo,
            'internalCode' => $codigo,
            'name'         => $name,
            'unit'         => "UN",
            'price'        => $brutto,
            'description'  => $name,
            'categoryID'   => 2,
            'isService'    => false,
            'configs'      => []
        );
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=UTF-8',
            'Authorization: '.$token["token_type"].' '.$token["access_token"]
        ));

        $respuestaJson = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($ch);
        curl_close($ch);

        $respuestaArray = json_decode($respuestaJson, true);
        $success = isset($respuestaArray['success']) ? $respuestaArray['success'] : false;
        $message = isset($respuestaArray['message']) ? $respuestaArray['message'] : 'Sin mensaje';

        if ($http_code < 200 || $http_code >= 300) {
            $sql = "insert into log_erp(proceso, estatus, detalle)
                    values('UpdateProduct',0,'Error HTTP {$http_code} en Item {$_REQUEST["id"]} - Código: {$http_code} : {$message}')";
            $CON->no_result($sql);
        } elseif ($curl_error) {
            $sql = "insert into log_erp(proceso, estatus, detalle)
                    values('UpdateProduct',0,'Error cURL en Item {$_REQUEST["id"]} - {$curl_error}')";
            $CON->no_result($sql);
        } else {
            if ($success) {
                $sql = "insert into log_erp(proceso, estatus, detalle)
                        values('UpdateProduct',1,'Se actualizó correctamente Item {$_REQUEST["id"]}')";
                $CON->no_result($sql);
            } else {
                $sql = "insert into log_erp(proceso, estatus, detalle)
                        values('UpdateProduct',0,'Error API en Item {$_REQUEST["id"]} - ({$codigo}) : {$message}')";
                $CON->no_result($sql);
            }
        }
    }
}
/* FIN */

   $savemsg = getSaveMessage(true);

   registerCostPriceHistory($CON, $_REQUEST["id"], "item");
   recalcAutomatedSellPrices($CON, $_REQUEST["id"], "item");
   updateItemStorePrices($CON, $_REQUEST["id"]);
   registerSellPriceHistory($CON, $_REQUEST["id"], "item");

   //----------------------------------------------------------------------------------
   $sql = " delete from tran_comments_item_vals
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "comvals_") !== false && strpos($reqkey, "comvals_") == 0 && strpos($reqkey, "_eng") !== true)
      {
         $idxarr        = explode("_", $reqkey);
         $com_id        = $idxarr[1];
         $val_id        = $_REQUEST[$reqkey];

         if((int)$val_id && (int)$com_id)
         {
            $sql = " insert into tran_comments_item_vals
                     (item_id, com_id, val_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$com_id}, {$val_id})";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from item_equipos_rel
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   foreach($_REQUEST["equipids"] AS $equipid)
   {
      $sql = " insert into item_equipos_rel
               (item_id, equipo_id)
               VALUES
               ({$_REQUEST["id"]}, {$equipid})";
      $CON->no_result($sql);
   }

      
   //----------------------------------------------------------------------------------
   if($redirect)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from company_data
               where
               company_status = 1
               order by company_name";
      $companies = $CON->select($sql);

      foreach($companies AS $company)
      {
         $sql = " select t1.*
                  from company_shops t1
                  where
                  t1.shop_status = 1 and
                  t1.shop_company_id = {$company["id"]}
                  order by t1.shop_name";
         $shops = $CON->select($sql);

         foreach($shops AS $shop)
         {
            $sql = " insert into item_shops
                    (item_id, shop_id)
                    VALUES
                    ({$_REQUEST["id"]}, {$shop["id"]})";
            $res = $CON->no_result($sql);
         }
      }
      updateItemStorePrices($CON, $_REQUEST["id"]);
      registerSellPriceHistory($CON, $_REQUEST["id"], "item");

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=638&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t4.unit_name, t4.unit_desc,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from item t1
            LEFT OUTER JOIN user t2 ON t1.item_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.item_crtusr = t3.id
            LEFT OUTER JOIN item_units t4 ON t1.item_unit = t4.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $item = $CON->select($sql);
   $item = $item[0];

   $sql = " select count(*) 'cc'
            from item
            where
            item_status > 0 and
            id != {$_REQUEST["id"]} and
            item_number_prod = '{$item["item_number_prod"]}'";
   $itemnumexists = $CON->select($sql);
   $itemnumexists = (int)$itemnumexists[0]["cc"];
   if((int)$itemnumexists)
   {  ?>
      <script language="JavaScript">
         alert('ADVERTENCIA: EL CODIGO INTERNO "<?=$item["item_number_prod"]?>" YA EXISTE PARA OTRO PRODUCTO');
      </script>
      <?php
   }

   //----------------------------------------------------------------------------------
   $sql = " select t2.id, t2.cat_title
            from item_productcats t1, productcats t2
            where
            t1.cat_id      = t2.id and 
            t1.item_id     = {$_REQUEST["id"]} and
            t2.cat_status  = 1";
   $selitemcatid = $CON->select($sql);
   $selitemcatidstr = $selitemcatid[0]["id"];
   $selitemcatname  = $selitemcatid[0]["cat_title"];

   //----------------------------------------------------------------------------------
   $sql = " select t1.item_costprice_brutto, t1.item_costprice_netto, t2.id, t2.supp_company
            from item_suppliers t1
            LEFT OUTER JOIN supplier t2 ON t1.supplier_id = t2.id
            where
            t1.item_id        = {$_REQUEST["id"]} and
            t1.item_supp_act  = 1";
   $suppinfo = $CON->select($sql);
   $suppinfo = $suppinfo[0];

   //----------------------------------------------------------------------------------
   $sql = " select item_barcode
            from item_barcodes
            where
            item_id     = {$_REQUEST["id"]} and
            item_type   = 'item'
            order by item_barcode";
   $barcodes = $CON->select($sql);

   foreach($barcodes AS $barcode)
   {
      $sql = " select count(*) 'cc'
               from item t1
               INNER JOIN item_barcodes t2 ON ( t1.id = t2.item_id and t2.item_type = 'item' )
               where
               t1.item_status > 0 and
               t1.id != {$_REQUEST["id"]} and
               t2.item_barcode = '{$barcode["item_barcode"]}'";
      $itemnumexists = $CON->select($sql);
      $itemnumexists = (int)$itemnumexists[0]["cc"];
      if((int)$itemnumexists)
      {  ?>
         <script language="JavaScript">
            alert('ADVERTENCIA: EL CODIGO DE BARRA "<?=$barcode["item_barcode"]?>" YA EXISTE PARA OTRO PRODUCTO');
         </script>
         <?php
      }
   }

   $sql = " select *
            from tran_comments_item_vals
            where
            item_id = {$_REQUEST["id"]}";
   $comvals = $CON->select($sql);
   foreach($comvals AS $comval)
      $_COMVALS[$comval["com_id"]] = $comval["val_id"];

   $sql = " select *
            from item_equipos_rel
            where
            item_id = {$_REQUEST["id"]}";
   $equvals = $CON->select($sql);
   foreach($equvals AS $equval)
      $_EQUVALS[$equval["equipo_id"]] = 1;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         cat_status = 1
         order by cat_prefix, id";
$cats = $CON->select($sql);

//----------------------------------------------------------------------------------
$itemunits = getItemUnits($CON);
$ubics     = getUbicaciones($CON);

if((int)$_REQUEST["addSync"])
{
   $shops   = getShops($CON, true);
   $shwmsg  = true;
   foreach($shops AS $shop)
   {
      addSyncTransaction($CON, "items", $_REQUEST["id"], $shop["shop_company_id"], $shop["id"], $shwmsg, $_REQUEST["addSyncTime"]);
      $shwmsg = false;
   }
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function setUnitBuy()
   {
      if(document.getElementById('unitbuy').style.display == 'none')
         document.getElementById('unitbuy').style.display = '';
      else
         document.getElementById('unitbuy').style.display = 'none';
   }
</script>
<form action="index.php" method="post" class="fokusfirst" name="js_item_form"
 onsubmit="return checkform(new Array(this.item_title, this.item_number_prod, this.item_catids))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="frommid" value="<?=$_REQUEST["frommid"]?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del articulo I</td>
      </tr>
      <?php
      if($_REQUEST["id"] == "")
      {  ?>
         <tr>
            <td class="content_rowl">Familia *</td>
            <td class="content_row">
               <select name="item_catids" id="item_catids" type="text" class="text" style="width:360px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="document.getElementById('idx_number_get').src='./libs/modules/items/get.productcat.php?catid=' +this.value;">>
                  <option value="">SELECCIONE</option>
                  <?php
                  foreach($cats AS $cat)
                  {
                     $selected = "";
                     if($selitemcatid[0]["id"] == $cat["id"])
                     {
                        $selected = "selected";
                     }
                     ?>
                     <option <?=$selected?> value="<?=$cat["id"]?>"><?=$cat["cat_prefix"]?> - <?=$cat["cat_title"]?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
      }
      else
      {  ?>
         <tr>
            <td class="content_rowl">Familia</td>
            <td class="content_row">
               <select name="item_catids" id="item_catids" type="text" class="text" style="width:290px">
                  <option value="<?=$selitemcatid[0]["id"]?>"><?=$selitemcatname?></option>
               </select>
               <input type="button" class="button" value="Modificar" style="width:68px"
               onclick="$('#idx_div_resv_tipoconf_id').fadeIn(300);">
               <div id="idx_div_resv_tipoconf_id" style="font-family: Arial;font-size: 12px;display: none;position:fixed;top:0px;left:0px;width:100%;height:100%;background-color:rgba(0,0,0,0.5);">
                  <center>
                  <div style="width:300px;background-color:white;height:180px;margin-top:150px;font-family:Arial;font-size:12px">
                     <br><br>
                     Asignar nueva familia:<br>
                     <div style="height:10px"></div>
                     <select class="text" style="width:90%" id="idx_newcatid" name="idx_newcatid">
                        <?php
                        foreach($cats AS $cat)
                        {  ?>
                           <option value="<?=$cat["id"]?>" <?if($selitemcatid[0]["id"] == $cat["id"]) echo "selected"?>>
                              <?=$cat["cat_title"]?>
                           </option>
                           <?php
                        }
                        ?>
                     </select>
                     <div style="height:10px"></div>
                     <?php
                     printButton("CONFIRMAR", "postnav_save", "javascript: deactivateFormChange()", "deactivateFormChange(); location.href = '/index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}&setnewcatid=' +$('#idx_newcatid').val();", "fa-check", "90%");
                     ?>
                     <div style="height:10px"></div>
                     <?php
                     printButton("VOLVER", "postnav", "javascript: deactivateFormChange()", "$('#idx_div_resv_tipoconf_id').fadeOut(300);", "fa-chevron-left", "90%");
                     ?>
                  </div>
                  </center>
               </div>
            </td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="content_rowl">Codigo Interno *</td>
         <td class="content_row">
            <input name="item_number_prod" id="item_number_prod" type="text" class="text" style="width:360px;background-color:#EEEEEE" readonly
            value="<?=$item["item_number_prod"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)" tabindex="-1">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="item_title" type="text" class="text" style="width:360px" value="<?=$item["item_title"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre Inglés</td>
         <td class="content_row">
            <input name="item_nameshop" type="text" class="text" style="width:360px" value="<?=$item["item_nameshop"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top">Descripción</td>
         <td class="content_row">
            <textarea name="item_desc" class="text" style="width:360px; height:148px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$item["item_desc"]?></textarea>
         </td>
      </tr>

      <tr>
         <td class="content_rowl">Utilizar Descripción</td>
         <td class="content_row">
            <input name="item_des_personalizada" type="checkbox" value="1"
            <?php if((int)$item["item_des_personalizada"]) echo "checked"?>> Desea que tome la descripcion en cotizaciones
         </td>
      </tr>
      
      <tr>
      <td class="content_rowl">Producto Stock</td>
      <td class="content_row">
         <input name="item_controla_stock" type="checkbox" value="1"
            <?php if (!isset($item['item_controla_stock']) || (int)$item['item_controla_stock'] === 1) echo 'checked'; ?>>
         Desea que Producto controle Stock.
      </td>
      </tr>
  
      <tr>
         <td class="content_rowl">Unidad</td>
         <td class="content_row">
            <input name="item_unit_amount" type="text" class="text" style="width:70px"
            value="<?php if($_REQUEST["id"] == "") echo "1,00"; else echo printPrice($item["item_unit_amount"],2)?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <select class="text" name="item_unit" style="width:285px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               foreach($itemunits AS $itemunit)
               {  ?>
                  <option value="<?=$itemunit["id"]?>" <?php if(($_REQUEST["id"] == "" && $itemunit["unit_name"] == "C/U") || $item["item_unit"] == $itemunit["id"]) echo "selected"?>><?=$itemunit["unit_name"]?> - <?=$itemunit["unit_desc"]?></option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Embalaje</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" width="80">
                  <input name="item_unitembalaje_amount" type="text" class="text" style="width:70px"
                  value="<?if($item["item_unitembalaje_amount"] > 0) echo printPrice($item["item_unitembalaje_amount"], 2); else echo "1";?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </td>
               <td class="content_row_clear" width="140"><nobr>Factor Compra Sucursal</nobr></td>
               <td class="content_row_clear">
                  <input name="item_factorshop" type="text" class="text" style="width:70px"
                  value="<?if(!(int)$_REQUEST["id"]) echo 1; else echo (int)$item["item_factorshop"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Activado</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" width="80">
                  <select class="text" name="item_released" style="width:70px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <option value="1" style="color:green" <?php if((int)$item["item_released"] || $_REQUEST["id"] == "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][0]?></option>
                     <option value="0" style="color:red"   <?php if(!(int)$item["item_released"] && $_REQUEST["id"] != "") echo "selected"?>><?=$_LANG["FORM"]["RADIO"][1]?></option>
                  </select>
               </td>
               <td class="content_row_clear" align="right" style="display:none">
                  <input type="checkbox" value="1" class="checkbox" name="item_charges_act" id="item_charges_act"
                  <?php if((int)$item["item_charges_act"]) echo "checked" ?>>
               </td>
               <td class="content_row_clear" align="right" width="115"  style="padding-right:10px;display:none"><nobr>Inventario con Lotes</nobr></td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$item["crt_firstname"]?> <?=$item["crt_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=displayDate($item["item_crtdat"])?></td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del articulo II</td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <table cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="112">
                  <input name="item_sellable" type="checkbox" value="1" id="item_sellable" onclick="setItemSellable(this)"
                  <?php if((int)$item["item_sellable"] == 1 || !(int)$_REQUEST["id"]) echo "checked"?>>
                  Ventas
               </td>
               <td class="content_row_clear">
                  <input name="item_purchasable" type="checkbox" value="1" id="item_purchasable"
                  <?php if((int)$item["item_purchasable"] == 1 || !(int)$_REQUEST["id"]) echo "checked"?>>
                  Compras
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_tr_venta1" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">Precio venta (neto) *</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="112">
                  <input name="item_sellprice_netto" id="item_sellprice_netto" type="text" class="text"
                  style="width:80px;text-align:right<?if((int)$item["item_sellprice_calc"]) echo ";background-color:#E1FFD6"?>"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_netto"])?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)" <?if((int)$item["item_sellprice_calc"]) echo "readonly"?>>
                  <?=$_SESSION["_CONF"]["conf_currency"]?>
               </td>
               <td class="content_row_clear" style="display:none">
                  <input type="checkbox" value="1" name="item_sellprice_calc" id="item_sellprice_calc" onclick="setItemSellCalc(this)"
                  <?php if((int)$item["item_sellprice_calc"]) echo "checked" ?>>
                  Precio calculado
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_tr_venta1a" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"]) echo "style='display:none'"?>>
         <td class="content_rowl">Margen basado en</td>
         <td class="content_row">
            <input type="radio" value="CAT" name="item_sellprice_calc_type" id="item_sellprice_calc_typef"
            onclick="document.getElementById('idx_tr_venta1b').style.display='none';
                     document.getElementById('idx_tr_venta1d').style.display=''"
            <?php if($item["item_sellprice_calc_type"] == "CAT" || $item["item_sellprice_calc_type"] == "") echo "checked" ?>>
            Familia
            
            <input type="radio" value="ITEM" name="item_sellprice_calc_type" id="item_sellprice_calc_typei"
            onclick="document.getElementById('idx_tr_venta1b').style.display='';
                     document.getElementById('idx_tr_venta1d').style.display='none'"
            <?php if($item["item_sellprice_calc_type"] == "ITEM") echo "checked" ?>>
            Particular
         </td>
      </tr>
      <tr id="idx_tr_venta1b" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"] || $item["item_sellprice_calc_type"] != "ITEM") echo "style='display:none'"?>>
         <td class="content_rowl">Utilidad</td>
         <td class="content_row">
            <input name="item_sellprice_calc_perc" type="text" class="text" style="width:80px;text-align:right"
            value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_calc_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            %
         </td>
      </tr>
      <tr id="idx_tr_venta1d" <?php if(!(int)$item["item_sellable"] || !(int)$item["item_sellprice_calc"] || $item["item_sellprice_calc_type"] != "CAT") echo "style='display:none'"?>>
         <td class="content_rowl" height="25">Utilidad</td>
         <td class="content_row">
            <?php
            if(!(int)$_REQUEST["id"])
               echo "- - -";
            else
               echo printPrice($item["item_sellprice_calc_perc"], 2);
            ?>
             %
          </td>
      </tr>
      <tr id="idx_tr_venta2" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">IVA *</td>
         <td class="content_row">
            <input name="item_sellprice_taxes_perc" type="text" class="text" style="width:80px;text-align:right"
            value="<?php if((int)$item["id"]) echo printPrice($item["item_sellprice_taxes_perc"],2); else echo printPrice($_SESSION["_CONF"]["conf_taxes"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
         </td>
      </tr>
      <tr id="idx_tr_venta3" <?php if(!(int)$item["item_sellable"]) echo "style='display:none'"?>>
         <td class="content_rowl">Precio venta (bruto)</td>
         <td class="content_row">
            <b class="msg_save_ok">
            <?=$_SESSION["_CONF"]["conf_currency"]?>
            <?php
            if((int)$item["id"])
               echo printPrice($item["item_sellprice_brutto"]);
            else
               echo "0,00";
            ?>
            </b>
         </td>
      </tr>
      <?php
      if($_REQUEST["id"] != "" && (int)$item["item_purchasable"])
      {  ?>
         <tr>
            <td class="content_rowl">Precio compra</td>
            <td class="content_row">
               <?php
               if((int)$suppinfo["id"])
               {  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                     <td class="content_row_clear" width="120"><b>Neto:</b> <?=$_SESSION["_CONF"]["conf_currency"]." ".printPrice($suppinfo["item_costprice_netto"])?></td>
                     <td class="content_row_clear" align="left"><b>Bruto:</b> <?=$_SESSION["_CONF"]["conf_currency"]." ".printPrice($suppinfo["item_costprice_brutto"])?></td>
                  </tr>
                  </table>
                  <?php
               }
               else
                  echo "- - -";
               ?>
            </td>
         </tr>
         <?php
      }
      ?>
      <tr id="idx_tr_venta1c" style='display:none'>
         <td class="content_rowl">Peso / Conducciï¿½n</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="120">
                  <input name="item_weight" type="text" class="text" style="width:80px;text-align:right"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_weight"],2)?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  onkeyup="calcWeightPrice(this, document.getElementById('item_weight_price'))"> Kg
               </td>
               <td class="content_row_clear">
                  <input name="item_weight_price" id="item_weight_price" type="text" class="text" style="width:80px;text-align:right"
                  value="<?php if((int)$item["id"]) echo printPrice($item["item_weight_price"])?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"> <?=$_SESSION["_CONF"]["conf_currency"]?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Nota int.: Venta</td>
         <td class="content_row">
            <input name="item_invoice_note" type="text" class="text" style="width:100%" value="<?=$item["item_invoice_note"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Nota int.: Compra</td>
         <td class="content_row">
            <input name="item_invoicebuy_note" type="text" class="text" style="width:100%" value="<?=$item["item_invoicebuy_note"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Ubicacion</td>
         <td class="content_row">
            <select class="text" name="item_ubicacion" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($ubics AS $ubi)
               {  ?>
                  <option value="<?=$ubi["id"]?>" <?php if($ubi["id"] == $item["item_ubicacion"]) echo "selected"?>>
                     <?=$ubi["ubi_name"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr id="idx_tr_venta1x" style='display:none'>
         <td class="content_rowl">Opciones adicionales</td>
         <td class="content_row">
            <table border="0" cellpadding="2" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear">
                  &nbsp;<input type="checkbox" value="1" name="item_sell_nodsc" id="item_sell_nodsc"
                  <?php if((int)$item["item_sell_nodsc"]) echo "checked" ?>>
                  &nbsp;&nbsp;No lleva descuentos
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
      if($_REQUEST["id"] != "" && (int)$item["item_purchasable"] && (float)$suppinfo["item_costprice_netto"] > 0.00 && (float)$item["item_sellprice_netto"] > 0.00)
      {
         $buyval = getSupplierFinalCostNetto($CON, $suppinfo["id"], $_REQUEST["id"]);
         $selval = getFinalSellNetto($CON, $_REQUEST["id"]);
         ?>
         <tr>
            <td class="content_row" colspan="2">
               <img src="./libs/modules/items/spanne.php?buyval=<?=$buyval?>&sellval=<?=$selval?>&title=<?=$title?>">
            </td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="content_rowl">Fabricación</td>
         <td class="content_row">
            <input name="item_fabricate_act" type="checkbox" value="1"
            <?php if((int)$item["item_fabricate_act"]) echo "checked"?>> Activar (no lleva stock directo)
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fabricación / Fuelle</td>
         <td class="content_row">
            <input name="item_prodcalc_fuelle_act" type="checkbox" value="1"
            <?php if((int)$item["item_prodcalc_fuelle_act"]) echo "checked"?>> Calculo ancho + fuelle (en modulo de producción)
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fabricación externa</td>
         <td class="content_row">
            <input name="item_fabricate_extern_act" type="checkbox" value="1"
            <?php if((int)$item["item_fabricate_extern_act"]) echo "checked"?>> Activar fabricación externa
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Insumo/Material</td>
         <td class="content_row">
            <input name="item_prodwrk_act" type="checkbox" value="1"
            <?php if((int)$item["item_prodwrk_act"]) echo "checked"?>> Activar (repuestos, materiales de producción)
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Venta online</td>
         <td class="content_row">
            <input name="item_ventaonline_act" type="checkbox" value="1"
            <?php if((int)$item["item_ventaonline_act"]) echo "checked"?>> Activar
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cotización/Producto</td>
         <td class="content_row">
            <input name="item_fabricate_prefix" class="text" type="text" value="<?=$item["item_fabricate_prefix"]?>"
            style="width:100%" placeholder="p.e. Bolsa reutilizable, Saco reutilizable, etc.">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cotización/Tela</td>
         <td class="content_row">
            <input name="item_fabricate_fabrictext" class="text" type="text" value="<?=$item["item_fabricate_fabrictext"]?>"
            style="width:100%" placeholder="p.e. confeccionada en algodon">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Texto adic. fuelle</td>
         <td class="content_row">
            <input name="item_fabricate_fuelletext" class="text" type="text" value="<?=$item["item_fabricate_fuelletext"]?>"
            style="width:100%" placeholder="p.e. lateral y en base">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Código contable</td>
         <td class="content_row">
            <select name="item_codcont" id="item_codcont" type="text" class="text" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               $sql = " select t1.*
                        from codigo_contable t1
                        where
                        t1.cc_status = 1
                        order by t1.cc_title";
               $cccodes = $CON->select($sql);
               foreach($cccodes AS $cccode)
               {  ?>
                  <option value="<?=$cccode["id"]?>" <?if($item["item_codcont"] == $cccode["id"]) echo "selected"?>><?=$cccode["cc_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Código producción</td>
         <td class="content_row">
            <select name="item_codcont_prod" id="item_codcont_prod" type="text" class="text" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               $sql = " select t1.* 
                        from prod_codigo_contable t1
                        where
                        t1.cc_status = 1
                        order by t1.cc_title";
               $cccodes = $CON->select($sql);
               foreach($cccodes AS $cccode)
               {  ?>
                  <option value="<?=$cccode["id"]?>" <?if($item["item_codcont_prod"] == $cccode["id"]) echo "selected"?>><?=$cccode["cc_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      
      <tr>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$item["upd_firstname"]?> <?=$item["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=displayDate($item["item_upddat"])?></td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
</table>
<br>
<?php
if($_REQUEST["id"] != "")
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="left" width="130">
         <?php
         if($_REQUEST["frommid"] != "")
            $mid = $_REQUEST["frommid"];
         else
            $mid = $_REQUEST["mid"];
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$mid}{$extlink}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if($_SESSION["user_type"] == 1)
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         else
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/items/auth.fancybox.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}','iframe', 450, 160, 'no')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.js_item_form)", "disk-black");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}

if($_REQUEST["id"] != "")
{
   $sql = " select *
            from productcats
            where
            id = {$selitemcatid[0]["id"]}";
   $pcatdata = $CON->select($sql);
   $pcatdata = $pcatdata[0];



   $sql = " select t1.com_name, t3.*
            from tran_comments t1
            INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
            INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
            where
            t1.com_status  > 0 and
            t2.cat_id      = {$pcatdata["id"]} and
            t3.add_status  > 0
            order by t1.com_name, t3.add_name";
   
   $trancoms = $CON->select($sql);
   
   foreach($trancoms AS $trancom)
   {
      $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
      $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
   }

   if((int)$pcatdata["cat_itemreg_width"] || (int)$pcatdata["cat_itemreg_gsm"] ||
      (int)$pcatdata["cat_itemreg_length"] || (int)$pcatdata["cat_itemreg_machine_assign"] ||
      (count($trancoms) && $trancoms != false))
   {  ?>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="200">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2" style="background-color:#9CC5FF;text-shadow:none">Registrar características</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Característica</td>
         <td class="content_tbl_subheader">Valor</td>
      </tr>
      <?php
      if((int)$pcatdata["cat_itemreg_width"])
      {  ?>
         <tr>
            <td class="content_rowl">Ancho</td>
            <td class="content_row">
               <input name="item_reg_width" id="item_reg_width" type="text" class="text" style="width:120px;"
               value="<?if((float)$item["item_reg_width"] > 0.00) echo printPrice($item["item_reg_width"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               cm
            </td>
         </tr>
         <?php
      }
      if((int)$pcatdata["cat_itemreg_length"])
      {  ?>
         <tr>
            <td class="content_rowl">Longitud</td>
            <td class="content_row">
               <input name="item_reg_length" id="item_reg_length" type="text" class="text" style="width:120px;"
               value="<?if((float)$item["item_reg_length"] > 0.00) echo printPrice($item["item_reg_length"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               m
            </td>
         </tr>
         <?php
      }
      if((int)$pcatdata["cat_itemreg_gsm"])
      {  ?>
         <tr>
            <td class="content_rowl">GSM</td>
            <td class="content_row">
               <input name="item_reg_gsm" id="item_reg_gsm" type="text" class="text" style="width:120px;"
               value="<?if((float)$item["item_reg_gsm"] > 0.00) echo printPrice($item["item_reg_gsm"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               gr
            </td>
         </tr>
         <?php
      }
      if((int)$pcatdata["cat_itemreg_kg"])
      {  ?>
         <tr>
            <td class="content_rowl">Kilogramos</td>
            <td class="content_row">
               <input name="item_reg_kg" id="item_reg_kg" type="text" class="text" style="width:120px;"
               value="<?if((float)$item["item_reg_kg"] > 0.00) echo printPrice($item["item_reg_kg"],2)?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               kg
            </td>
         </tr>
         <?php
      }
      if((int)$pcatdata["cat_itemreg_machine_assign"])
      {
         $sql = " select t1.id, t1.equipo_name, t2.type_ant_title
                  from equipo t1
                  LEFT OUTER JOIN equipo_type t2 ON t1.equipo_type_id = t2.id
                  where
                  t1.equipo_status > 0
                  order by t2.type_ant_title, t1.equipo_name";
         $machines = $CON->select($sql);
         ?>
         <tr>
            <td class="content_row" valign="top">Asignar máquinas</td>
            <td class="content_row" style="padding:0px;border:0px">
               <table border="0" cellpadding="3" cellspacing="0" width="100%">
               <tr>
                  <td class="content_rowl" width="25" align="center">Activar</td>
                  <td class="content_rowl" width="250">Tipo mï¿½quina</td>
                  <td class="content_rowl">Nombre mï¿½quina</td>
               </tr>
               <?php
               foreach($machines AS $machine)
               {  ?>
                  <tr>
                     <td class="content_row">
                        <input type="checkbox" name="equipids[]" value="<?=$machine["id"]?>"
                        <?php if((int)$_EQUVALS[$machine["id"]]) echo "checked"?>>
                     </td>
                     <td class="content_row"><?=$machine["type_ant_title"]?></td>
                     <td class="content_row"><?=$machine["equipo_name"]?></td>
                  </tr>
                  <?php
               }
               ?>
               </table>
            </td>
         </tr>
         <?php
      }
      //----------------------------------------------------------------------------------
      foreach(array_keys($_TRANSCOM) AS $trancomid)
      {  ?>
         <tr>
            <td class="content_rowl"><?=$_TRANSCOM[$trancomid]["NAME"]?>
            </td>
            <td class="content_row">
               <select class="text" style="width:400px;" name="comvals_<?=$trancomid?>" id="comvals_<?=$trancomid?>">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach(array_keys($_TRANSCOM[$trancomid]["OPTS"]) AS $trancomvalid)
                  {  ?>
                     <option value="<?=$trancomvalid?>" <?php if((int)$_COMVALS[$trancomid] == $trancomvalid) echo "selected"?>>
                        <?=$_TRANSCOM[$trancomid]["OPTS"][$trancomvalid]?>
                     </option>
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
   }
}
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col width="28">
   <col width="240">
   <col>
   <col>
   <col>
   <col>
   <col>
   <col width="20">
   <col width="50">
   <col>
   <col>
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="13">Proveedores</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Busqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader">Codigo</td>
   <td class="content_tbl_subheader" align="right"><nobr>Neto/Basico</nobr></td>
   <td class="content_tbl_subheader" align="right"><nobr>Precio/F</nobr></td>
   <td class="content_tbl_subheader" align="right"><nobr>Precio/USD</nobr></td>
   <td class="content_tbl_subheader" align="right"><nobr>Alza</nobr></td>
   <td class="content_tbl_subheader" align="right">FOB</td>
   <td class="content_tbl_subheader" align="right">IVA %</td>
   <td class="content_tbl_subheader" align="right"><nobr>P/Bruto</nobr></td>
   <td class="content_tbl_subheader" align="center">Primario</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.country_money_type
         from item_suppliers t1
         LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
         LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
         where
         item_id = {$_REQUEST["id"]}
         order by item_supp_act desc, item_costprice_brutto asc";
$posdata = $CON->select($sql);

$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

$rowcount += 2;
for($x = 0; $x < $rowcount; $x++)
{
   $supptax = getSupplierTaxes($CON, $posdata[$x]["supplier_id"]);

   //----------------------------------------------------------------------------------
   $sql = " select t1.prc_item_id, t1.prc_costprice_netto
            from pricehist_buy t1
            where
            t1.prc_item_id    = {$_REQUEST["id"]} and
            t1.prc_item_type  = 'item' and
            t1.prc_supplier_id = {$posdata[$x]["supplier_id"]} 
            order by t1.prc_crtdat desc
            LIMIT 0,2";
   $buyhist = $CON->select($sql);
   $buyhist = $buyhist[1];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td>
               <input type="text" class="text" style="width:60px" onfocus="markfield(this,0)"
               <?php
               if((int)$posdata[$x]["item_id"])
               {  ?>
                  onblur="markfield(this,1);"
                  <?php
               }
               else
               {  ?>
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?mode=money&rowcount=<?=$x?>' +'&search=' +this.value"
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
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.all.supplier_id_<?=$x?>.options.length=0;submitForm(document.js_item_form) }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <select class="text" style="width:270px" name="supplier_id_<?=$x?>"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="var valarr = this.value.split('#');document.getElementById('country_money_type_<?=$x?>').value=valarr[1]">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $sql = " select t1.id, t1.supp_company
                        from supplier t1
                        where
                        t1.id = {$posdata[$x]["supplier_id"]}";
               $selitem = $CON->select($sql);

               $desc = trim(addslashes($selitem[0]["supp_company"]));
               ?>
               <option value="<?=$selitem[0]["id"]?>"><?=$desc?></option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row">
         <input type="hidden" id="country_money_type_<?=$x?>" value="<?=$posdata[$x]["country_money_type"]?>">
         <input class="text" name="item_code_<?=$x?>" id="item_code_<?=$x?>" style="width:90px"
         value="<?php if((int)$posdata[$x]["item_id"]) echo $posdata[$x]["item_code"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right">
         <input class="text" name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" style="width:65px;text-align:right">
      </td>
      <td class="content_row" align="right">
         <?=printPrice(getSupplierFinalCostNetto($CON, $posdata[$x]["supplier_id"], $_REQUEST["id"], "item", 0.00, 1))?>
      </td>
      <td class="content_row" align="right">
         <nobr>
         <input type="button" class="button" value="&lt;"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="if(document.getElementById('item_costprice_usd_<?=$x?>').value != '')
                  document.all.idxifrsrc.src='./libs/modules/items/get.usdclp.php?mode=' +document.getElementById('country_money_type_<?=$x?>').value +'&rowcount=<?=$x?>' +'&value=' +document.getElementById('item_costprice_usd_<?=$x?>').value"
         style="width:25px;<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>">
         
         <input class="text" name="item_costprice_usd_<?=$x?>" id="item_costprice_usd_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_usd"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         style="width:50px;text-align:right;<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>">
         <?if((int)$posdata[$x]["item_id"] && $supptax) echo "&nbsp;"?>
         </nobr>
      </td>
      <td class="content_row" align="right">
         <?php
         if((int)$buyhist["prc_item_id"])
         {
            $pbase = $buyhist["prc_costprice_netto"];
            $pnew  = $posdata[$x]["item_costprice_netto"];
               
            $diff = ($pnew - $pbase) / $pbase * 100;
            if($diff > 0.00)
               echo "+";
            echo printPrice(round($diff,0))."%";
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right">
         <input type="checkbox" name="item_supp_fob_<?=$x?>" value="1"
         style="<?if((int)$posdata[$x]["item_id"] && $supptax) echo "display:none"?>"
         <?php if((int)$posdata[$x]["item_supp_fob"]) echo "checked" ?>>
         <?if((int)$posdata[$x]["item_id"] && $supptax) echo "&nbsp;"?>
      </td>
      <td class="content_row" align="right">
         <input class="text" name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_taxes_perc"],2); else echo printPrice($_SESSION["_CONF"]["conf_taxes"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" style="width:40px;text-align:right">
      </td>
      <td class="content_row" align="right">
         <?php
         if((int)$posdata[$x]["item_id"])
            echo printPrice($posdata[$x]["item_costprice_brutto"],2);
         else
            echo "- - -";
         ?>
      </td>
      <td class="content_row" align="center">
         <input type="radio" name="item_supp_act" value="<?=$x?>"
         <?php if($x == 0) echo "checked" ?>>
      </td>
      <td class="content_row" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
            printButton("Cond.", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=supplierdiscounts&id={$_REQUEST["id"]}&supplierid={$posdata[$x]["supplier_id"]}&itemtype=item", "", "calculator");
         else
            echo "&nbsp;";
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Codigos de barra</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Codigos #1</td>
   <td class="content_tbl_subheader">Codigos #2</td>
   <td class="content_tbl_subheader">Codigos #3</td>
</tr>
<?php
$poscounter = 0;
for($rows = 0, $y = 0; $rows < 2; $rows++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <input class="text" name="item_barcode[]" style="width:310px;"
         value="<?=$barcodes[$y]["item_barcode"]?><?$y++?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" >
      </td>
      <td class="content_row">
         <input class="text" name="item_barcode[]" style="width:310px;"
         value="<?=$barcodes[$y]["item_barcode"]?><?$y++?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" >
      </td>
      <td class="content_row">
         <input class="text" name="item_barcode[]" style="width:310px;"
         value="<?=$barcodes[$y]["item_barcode"]?><?$y++?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" >
      </td>
   </tr>
   <?php
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
         if($_REQUEST["frommid"] != "")
            $mid = $_REQUEST["frommid"];
         else
            $mid = $_REQUEST["mid"];
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$mid}{$extlink}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="display:none;padding-right:5px">
         <?php
         printButton("Sincronizar", "postnav", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/syncing/fancybox.php?mid={$_REQUEST["mid"]}&subcatexec=basic&exec=edit&id={$_REQUEST["id"]}','iframe', 500, 320, 'no')", "arrow-retweet");
         ?>
      </td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if($_SESSION["user_type"] == 1)
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         else
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/items/auth.fancybox.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}','iframe', 450, 160, 'no')", "cross-circle-frame");
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
<br>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<iframe style="width:1px;height:1px;display:none" id="idx_number_get" src=""></iframe>
<?php
$_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');";
if($_REQUEST["reExecSave"] == "1")
   $_SESSION["JSEXEC"] .= "submitForm(document.js_item_form);";
if(!(int)$_REQUEST["id"])
   $_SESSION["JSEXEC"] .= "setItemSellable(document.getElementById('item_sellable'));";
?>