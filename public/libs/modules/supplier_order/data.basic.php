<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php');
// echo 'rut del archivo : '.realpath('functions.php');
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xsupplierorders"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xsupplierorders"]["fullcust"] = "";

//----------------------------------------------------------------------------------
if($_REQUEST["cancelshp"] == "1")
{
   $currtme = time();

   $sql = " update supplier_order
            set
            sord_order_shipped = 1,
            sord_status = 4,
            sord_updusr = {$_SESSION["user_id"]},
            sord_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["revertshp"] == "1")
{
   $currtme = time();

   $sql = " update supplier_order
            set
            sord_order_shipped = 0,
            sord_status = 3,
            sord_updusr = {$_SESSION["user_id"]},
            sord_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{

   $currtme = time();
 
   $_REQUEST["supplier_id_0"] = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];
   $_REQUEST["delivery_date"] = explode(".", $_REQUEST["delivery_date"]);
   $_REQUEST["delivery_date"] = (int)mktime(0, 0, 0, $_REQUEST["delivery_date"][1], $_REQUEST["delivery_date"][0], $_REQUEST["delivery_date"][2]);
   $_REQUEST["sord_taxes"]    = (int)$_REQUEST["sord_taxes"];
   $_REQUEST["sord_type"]     = (int)$_REQUEST["sord_type"];
   $sord_en_lang = 0;
   if(!$_REQUEST["sord_taxes"])
      $sord_en_lang = 1;

   //----------------------------------------------------------------------------------
   $sql = " select supp_paymentid, supp_dsc_finance, supp_dsc_finance_calc
            from supplier
            where
            id = {$_REQUEST["supplier_id_0"]}";
   $sord_paymentid = $CON->select($sql);

   
   $sord_supplier_dsc_finance       = (float)$sord_paymentid[0]["supp_dsc_finance"];
   $sord_supplier_dsc_finance_calc  = $sord_paymentid[0]["supp_dsc_finance_calc"];
   $sord_paymentid                  = (int)$sord_paymentid[0]["supp_paymentid"];

   //----------------------------------------------------------------------------------
   $dsc_pay                = getSupplierPaymentDiscount($CON, $_REQUEST["supplier_id_0"], $sord_paymentid);
   $sord_payment_dsc       = (float)$dsc_pay["dct_scale_discount"];
   $sord_payment_dsctype   = (int)$dsc_pay["dct_scale_type"];

   $sql = "select * from supplier where id = {$_REQUEST["supplier_id_0"]}";
   $proveedor = $CON->select($sql);
   $proveedor = $proveedor[0];
   
   $sord_num = createTransactionNumber($CON, $_REQUEST["company_id"], "order");

   //----------------------------------------------------------------------------------
   $sql = " insert into supplier_order
            (sord_number, sord_supplier_id, sord_company_id, sord_shop_id, sord_date,
             sord_title, sord_taxes, sord_paymentid, sord_payment_dsc, sord_payment_dsctype,
             sord_supplier_dsc_finance, sord_supplier_dsc_finance_calc, sord_crtdat, sord_crtusr,
             sord_type, sord_en_lang, sord_cod_moneda)
            VALUES
            ('{$sord_num}', {$_REQUEST["supplier_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$_REQUEST["delivery_date"]}, 'Orden de compra {$sord_num}',
              {$_REQUEST["sord_taxes"]}, {$sord_paymentid}, {$sord_payment_dsc}, {$sord_payment_dsctype},
              {$sord_supplier_dsc_finance}, '{$sord_supplier_dsc_finance_calc}', {$currtme},
              {$_SESSION["user_id"]}, {$_REQUEST["sord_type"]}, {$sord_en_lang},'{$proveedor["supp_cod_moneda"]}')";
   $res = $CON->no_result($sql);
   // echo($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_order
               where
               sord_crtusr = {$_SESSION["user_id"]}";
      $sorder = $CON->select($sql);
      $_REQUEST["id"]   = $sorder[0]["thisid"];

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=662&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
   
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$_AMTRANGES = Array();
if((int)$_REQUEST["id"])
{
   $sql = " select *
            from supplier_order
            where
            id = {$_REQUEST["id"]}";
   $sordtemp = $CON->select($sql);
   $sordtemp = $sordtemp[0];

   $sql = " select *
            from supplier
            where
            id = {$sordtemp["sord_supplier_id"]}";
   $suppliertemp = $CON->select($sql);
   $suppliertemp = $suppliertemp[0];

   if(!(int)$suppliertemp["supp_aprobrange_disabled"])
   {
      $sql = " select *
               from supplier_order_ranges
               where
               rng_status > 0
               order by rng_amt_init";
      $ranges = $CON->select($sql);
      foreach($ranges AS $range)
      {
         $temp["INIT"] = (int)$range["rng_amt_init"];
         $temp["END"]  = (int)$range["rng_amt_end"];

         $sql = " select t2.id, t2.user_firstname, t2.user_lastname, t2.user_mail
                  from supplier_order_ranges_aprobusers t1
                  INNER JOIN user t2 ON t1.user_id = t2.id
                  where
                  t1.rng_id = {$range["id"]} and
                  t2.user_mail like '%@%'
                  order by 1,2";
         $temp["USERS"] = $CON->select($sql);
         $_AMTRANGES[] = $temp;
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["sord_otherdiscount_id"] = (int)$_REQUEST["sord_otherdiscount_id"];

   $sql = " select *
            from supplier_order
            where
            id = {$_REQUEST["id"]}";
   $suppliertemp = $CON->select($sql);
   $suppliertaxes    = $suppliertemp[0]["sord_taxes"];
   $supplierorder    = $suppliertemp[0]["sord_supplier_id"];

   $sql = " delete from supplier_order_items_specs
            where
            sord_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];
         $item_kgs      = (int)$_REQUEST["item_kgs_{$idx}"];
         
         $_REQUEST["item_amount_{$idx}"]  = getPrice($_REQUEST["item_amount_{$idx}"],2);

         //----------------------------------------------------------------------------------
         unset($_SPECSAVEDATA);
         foreach(array_keys($_REQUEST) AS $specreqkey)
         {
            if(strpos($specreqkey, "specdata_{$idx}_") !== false && strpos($specreqkey, "specdata_{$idx}_") == 0)
            {
               $spec_id       = substr($specreqkey, strrpos($specreqkey, "_") +1);
               $spec_value    = trim(addslashes($_REQUEST[$specreqkey]));
               if($spec_value != "")
                  $_SPECSAVEDATA[$spec_id] = $spec_value;
            }
         }
         
         //----------------------------------------------------------------------------------
         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues    = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$itemvalues[0];
            $sql_type      = $itemvalues[1];

            //----------------------------------------------------------------------------------
            if((int)$suppliertaxes)
            {
               $sql_costnetto = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
               $sql_taxesperc = getPrice($_REQUEST["item_costprice_taxes_perc_{$idx}"], 2);
               $sql_taxes     = (float)sprintf("%.2f", $sql_costnetto / 100 * $sql_taxesperc);
               $sql_costprice = (float)sprintf("%.2f", $sql_costnetto + $sql_taxes);
            }
            else
            {
               $sql_costnetto = getPrice($_REQUEST["item_costprice_netto_{$idx}"], 4);
               $sql_taxesperc = 0;
               $sql_taxes     = 0;
               $sql_costprice = $sql_costnetto;
            }

            //----------------------------------------------------------------------------------
            if((int)$_REQUEST["manual_pos_{$idx}"])
               $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
            else
               $_REQUEST["item_desc_{$idx}"] = "";

            $itemimage = "";
            if($_FILES["itemimage_{$idx}"]["name"] != "" &&
               $_FILES["itemimage_{$idx}"]["tmp_name"] != "" &&
               $_FILES["itemimage_{$idx}"]["error"] == 0 &&
               $_FILES["itemimage_{$idx}"]["size"] > 0)
            {
               $doc_type = substr($_FILES["itemimage_{$idx}"]["name"], strrpos($_FILES["itemimage_{$idx}"]["name"], ".") +1);
               $doc_hash = md5(microtime());
               $doc_name = "{$_REQUEST["id"]}_{$doc_hash}.{$doc_type}";
               $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.supplierorder/";
               $imgres   = move_uploaded_file($_FILES["itemimage_{$idx}"]["tmp_name"], "{$doc_dir}{$doc_name}");
               if($imgres)
                  $itemimage = $doc_name;
            }

            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update supplier_order_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_costprice_brutto      = {$sql_costprice},
                        item_costprice_taxes_perc  = {$sql_taxesperc},
                        item_costprice_netto       = {$sql_costnetto},
                        item_costprice_taxes       = {$sql_taxes},
                        item_pos                   = {$poscounter},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                        item_kgs                   = {$item_kgs}
                        where
                        sord_id  = {$_REQUEST["id"]} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               if($itemimage != "")
               {
                  $sql = " update supplier_order_items
                           set
                           item_image = '{$itemimage}'
                           where
                           sord_id  = {$_REQUEST["id"]} and
                           item_id  = {$existing_id} and
                           item_pos = {$existing_pos}";
                  $CON->no_result($sql);
               }
               if($_REQUEST["delitemimagepos"] != "" && $_REQUEST["delitemimagepos"] == $existing_pos)
               {
                  $sql = " update supplier_order_items
                           set
                           item_image = ''
                           where
                           sord_id  = {$_REQUEST["id"]} and
                           item_id  = {$existing_id} and
                           item_pos = {$existing_pos}";
                  $CON->no_result($sql);
               }

               foreach(array_keys($_SPECSAVEDATA) AS $spec_id)
               {
                  $sql = " insert into supplier_order_items_specs
                           (sord_id, item_id, item_pos, spec_id, spec_value)
                           VALUES
                           ({$_REQUEST["id"]}, {$existing_id}, {$existing_pos}, {$spec_id}, '{$_SPECSAVEDATA[$spec_id]}')";
                  $CON->no_result($sql);
                  $CON->no_result("insert into mensajes(texto) values('{$_REQUEST["id"]} - {$existing_id} - {$spec_id} - {$_SPECSAVEDATA[$spec_id]}' )");
               }

               recalcSupplierOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, false);

               //----------------------------------------------------------------------------------
               $_REQUEST["item_pcat_dsc_act_{$idx}"]     = (int)$_REQUEST["item_pcat_dsc_act_{$idx}"];
               $_REQUEST["item_vol_act_{$idx}"]          = (int)$_REQUEST["item_vol_act_{$idx}"];

               $_REQUEST["item_discount_{$idx}"]         = getPrice($_REQUEST["item_discount_{$idx}"],2);
               $_REQUEST["item_discount_type_{$idx}"]    = (int)$_REQUEST["item_discount_type_{$idx}"];

               for($y = 1; $y <= 4; $y++)
                  $_REQUEST["item_pcat_dsc_{$idx}_{$y}"] = getPrice($_REQUEST["item_pcat_dsc_{$idx}_{$y}"],2);

               $sql = " update supplier_order_items
                        set
                        item_discount        = {$_REQUEST["item_discount_{$idx}"]},
                        item_discount_type   = {$_REQUEST["item_discount_type_{$idx}"]},
                        item_pcat_dsc_act    = {$_REQUEST["item_pcat_dsc_act_{$idx}"]},
                        item_pcat_dsc1       = {$_REQUEST["item_pcat_dsc_{$idx}_1"]},
                        item_pcat_dsc2       = {$_REQUEST["item_pcat_dsc_{$idx}_2"]},
                        item_pcat_dsc3       = {$_REQUEST["item_pcat_dsc_{$idx}_3"]},
                        item_pcat_dsc4       = {$_REQUEST["item_pcat_dsc_{$idx}_4"]},
                        item_vol_act         = {$_REQUEST["item_vol_act_{$idx}"]}
                        where
                        sord_id  = {$_REQUEST["id"]} and
                        item_pos = {$poscounter}";
               $CON->no_result($sql);

               if($suppliertemp[0]["sord_otherdiscount_id"] != $_REQUEST["sord_otherdiscount_id"])
                  recalcSupplierOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, true);
            }
            else
            {
               if($sql_type == "item")
               {
                  $sql = " select t3.pay_id
                           from item_productcats t1
                           INNER JOIN item_suppliers t2              ON t1.item_id = t2.item_id
                           INNER JOIN supplier_productcat_payment t3 ON ( t3.sup_id = t2.supplier_id and t3.cat_id = t1.cat_id )
                           where
                           t1.item_id     = {$sql_id} and
                           t2.supplier_id = {$suppliertemp[0]["sord_supplier_id"]}";
                  $overridepaymentid = $CON->select($sql);
                  $overridepaymentid = (int)$overridepaymentid[0]["pay_id"];
                  if($overridepaymentid)
                     $_REQUEST["sord_paymentid"] = $overridepaymentid;
               }

               $item_subitem_id     = 0;
               $item_subitem_amount = 0;
               $subitem             = getItemSubItem($CON, $sql_id);
               if((int)$subitem["prod_item_id"])
               {
                  $item_subitem_id     = $subitem["prod_item_id"];
                  $item_subitem_amount = $subitem["prod_item_amount"];
               }
                        
               $sql = " insert into supplier_order_items
                        (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                         item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_desc,
                         item_subitem_id, item_subitem_amount, item_kgs, item_image)
                        VALUES
                        ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                         {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes}, '{$_REQUEST["item_desc_{$idx}"]}',
                         {$item_subitem_id}, {$item_subitem_amount}, {$item_kgs}, '{$itemimage}')";
               $CON->no_result($sql);

               foreach(array_keys($_SPECSAVEDATA) AS $spec_id)
               {
                  $sql = " insert into supplier_order_items_specs
                           (sord_id, item_id, item_pos, spec_id, spec_value)
                           VALUES
                           ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$spec_id}, '{$_SPECSAVEDATA[$spec_id]}')";
                  $CON->no_result($sql);
               }

               recalcSupplierOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter);
            }

            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from supplier_order_items
                     where
                     sord_id  = {$_REQUEST["id"]} and
                     item_id  = {$existing_id} and
                     item_pos = {$existing_pos}";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["sord_title"]       = trim(addslashes($_REQUEST["sord_title"]));
   $_REQUEST["sord_desc"]        = trim(addslashes($_REQUEST["sord_desc"]));
   $_REQUEST["sord_desc_intern"] = trim(addslashes($_REQUEST["sord_desc_intern"]));
   $_REQUEST["sord_date"]        = trim(addslashes($_REQUEST["sord_date"]));
   $_REQUEST["sord_plazo_desc"]  = trim(addslashes($_REQUEST["sord_plazo_desc"]));
   $_REQUEST["sord_paymentid"]   = (int)$_REQUEST["sord_paymentid"];

   $sord_date_stamp = explode(".", $_REQUEST["sord_date"]);
   $sord_date_stamp = (int)mktime(0,0,0,$sord_date_stamp[1], $sord_date_stamp[0], $sord_date_stamp[2]);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from supplier_order
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $dsc_pay                = getSupplierPaymentDiscount($CON, $headdata["sord_supplier_id"], $_REQUEST["sord_paymentid"]);
   $sord_payment_dsc       = (float)$dsc_pay["dct_scale_discount"];
   $sord_payment_dsctype   = (int)$dsc_pay["dct_scale_type"];

   //----------------------------------------------------------------------------------
   $_REQUEST["sord_number"] = trim(addslashes($_REQUEST["sord_number"]));
   $_REQUEST["sord_crtdat"] = explode(".", $_REQUEST["sord_crtdat"]);
   $_REQUEST["sord_crtdat"] = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["sord_crtdat"][1], $_REQUEST["sord_crtdat"][0], $_REQUEST["sord_crtdat"][2]);

   $_REQUEST["sord_incoterms"]   = trim(addslashes($_REQUEST["sord_incoterms"]));
   $_REQUEST["sord_en_lang"]     = (int)$_REQUEST["sord_en_lang"];

   $sql = " update supplier_order
            set
            sord_number          = '{$_REQUEST["sord_number"]}',
            sord_en_lang         = {$_REQUEST["sord_en_lang"]},
            sord_incoterms       = '{$_REQUEST["sord_incoterms"]}',
            sord_crtdat          = {$_REQUEST["sord_crtdat"]},
            sord_status          = {$_REQUEST["sord_status"]},
            sord_title           = '{$_REQUEST["sord_title"]}',
            sord_desc            = '{$_REQUEST["sord_desc"]}',
            sord_desc_intern     = '{$_REQUEST["sord_desc_intern"]}',
            sord_paymentid       = {$_REQUEST["sord_paymentid"]},
            sord_payment_dsc     = {$sord_payment_dsc},
            sord_payment_dsctype = {$sord_payment_dsctype},
            sord_otherdiscount_id = {$_REQUEST["sord_otherdiscount_id"]},
            sord_plazo_desc      = '{$_REQUEST["sord_plazo_desc"]}',
            sord_date            = {$sord_date_stamp},
            sord_updusr          = {$_SESSION["user_id"]},
            sord_upddat          = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from supplier_order t1
            INNER JOIN supplier_order t2 ON (t1.sord_status > 0 and t2.sord_status > 0 and t1.sord_number = t2.sord_number)
            where
            t1.id = {$_REQUEST["id"]}";
   $shpdoccheck = $CON->select($sql);
   if((int)$shpdoccheck[0]["cc"] > 1)
      $_SESSION["JSEXEC"] .= ";alert('EL NUMERO DE ORDEN DE COMPRA YA EXISTE');";

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["sord_otherdiscount_id"])
   {
      $sql = " select *
               from dscbuy_supplier_others
               where
               dsc_other_id   = {$_REQUEST["sord_otherdiscount_id"]} and
               dct_supp_id    = {$headdata["sord_supplier_id"]}";
      $othdata = $CON->select($sql);
      foreach($othdata as $othrow)
         $_OTHDATA[$othrow["dct_cat_id"]] = $othrow;
            
      $posdata = getSupplierOrderPos($CON, $_REQUEST["id"]);
      foreach($posdata AS $posrow)
      {
         if($posrow["item_type"] == "item")
         {
            $sql = " select cat_id
                     from item_productcats
                     where
                     item_id = {$posrow["item_id"]}";
            $catid = $CON->select($sql);
            $catid = $catid[0]["cat_id"];
         }
         elseif($posrow["item_type"] == "itemlist")
         {
            $sql = " select cat_id
                     from item_productcats_itemlist
                     where
                     item_id = {$posrow["item_id"]}";
            $catid = $CON->select($sql);
            $catid = $catid[0]["cat_id"];
         }
         if((int)$_OTHDATA[$catid]["dsc_other_id"])
         {
            $sql = " update supplier_order_items
                     set
                     item_discount        = 0,
                     item_pcat_dsc_act    = 1,
                     item_pcat_dsc1       = {$_OTHDATA[$catid]["dct_scale_discount1"]},
                     item_pcat_dsc2       = {$_OTHDATA[$catid]["dct_scale_discount2"]},
                     item_pcat_dsc3       = {$_OTHDATA[$catid]["dct_scale_discount3"]},
                     item_pcat_dsc4       = {$_OTHDATA[$catid]["dct_scale_discount4"]},
                     item_vol_act         = 0
                     where
                     sord_id  = {$_REQUEST["id"]} and
                     item_id  = {$posrow["item_id"]} and
                     item_pos = {$posrow["item_pos"]}";
            $CON->no_result($sql);
         }
      }
   }

   recalcSupplierOrder($CON, $_REQUEST["id"]);

   $_SESSION["supporder_RELOADCALC"]++;
   $lastreloadcalc = $_SESSION["supporder_RELOADCALC"];
   if($_SESSION["supporder_RELOADCALC"] > 1)
      $_SESSION["supporder_RELOADCALC"] = 0;

   if($final)
   {
      $canfinalize = true;
      if(is_array($_AMTRANGES) && count($_AMTRANGES) > 0 && (int)$_SESSION["user_type"] != 1)
      {
         $sql = " select *
                  from supplier_order
                  where
                  id = {$_REQUEST["id"]}";
         $sodata = $CON->select($sql);
         $sodata = $sodata[0];

         $sord_total_brutto = $sodata["sord_total_brutto"];
         if(!(int)$sodata["sord_taxes"])
         {
            $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
            $sord_total_brutto = $sord_total_brutto * $usdval;
         }

         foreach($_AMTRANGES AS $_AMTRANGE)
         {
            if($sord_total_brutto >= $_AMTRANGE["INIT"] && $sord_total_brutto <= $_AMTRANGE["END"])
               $canfinalize = false;
         }

         if(!$canfinalize)
         {
            $final = false;
            $sql = " update supplier_order
                     set
                     sord_status = 1
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
            ?>
            <script language="JavaScript">
               alert('Segun monto, la OC debe ser aprobada por un usuario con privilegio.');
               location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$_REQUEST["id"]?>';
            </script>
            <?php
            exit;
         }
      }
      else
      {
         $sql = " update supplier_order
                  set
                  sord_aprobblocked = 0
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }

   

   $final = false;
   if($headdata["sord_status"] == 1 && $_REQUEST["sord_status"] == 2)
      $final = true;


   //----------------------------------------------------------------------------------
   if($final || (int)$_REQUEST["printpdf"])
   {
      // if($headdata["sord_type"] != 0)
      doc_createSupplierOrder($CON, $_REQUEST["id"]);
      // else
      //    doc_createSupplierOrderTCPDF($CON, $_REQUEST["id"]);
   }
      
}

// doc_createSupplierOrder($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
$sql = " select *
            from supplier_order
            where
            id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
//----------------------------------------------------------------------------------------

$tipo_moneda  = $CON->select("select * from parametros where tabla = 'MONEDA' and codigo = '{$headdata["sord_cod_moneda"]}'");
$tipo_moneda  = $tipo_moneda[0];


$simbolo = $CON->select("select * from parametros where tabla = 'SIM' and codigo = '{$headdata["sord_cod_moneda"]}'");
$iva     = printPrice($simbolo[0]["valor1"],2);
$simbolo = $simbolo[0]["descripcion"];
$headdata["sord_taxes"] = 0;

// echo("simbolo".$simbolo["descripcion"]." otro ".$headdata["sord_cod_moneda"]." sord ".$headdata["sord_taxes"]." iva : (".$iva.")" );

function doc_SupplierOrder($CON, $id)
{

// require_once('../../libs/tcpdf/tcpdf.php');


// require_once('../tcpdf/tcpdf.php');


// Clase personalizada para encabezado y pie
/*
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, utf8_encode('Factura Electrónica'), 0, false, 'C');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

// Crear PDF
$pdf = new MYPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// ?? Cabecera
$pdf->Cell(0, 6, 'RUT: 12.345.678-9', 0, 1);
$pdf->Cell(0, 6, 'Nombre: Juan Pérez', 0, 1);
$pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y'), 0, 1);
$pdf->Ln(5);

// ?? Detalle (encabezado de tabla)
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(30, 6, 'Imagen', 1, 0, 'C', true);
$pdf->Cell(20, 6, 'Código', 1, 0, 'C', true);
$pdf->Cell(60, 6, 'Descripción', 1, 0, 'C', true);
$pdf->Cell(20, 6, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Precio', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Total', 1, 1, 'C', true);


$imagen = 'saquito.jpg'; // Ruta de imagen
$anchoCelda = 30;
$altoCelda = 30;
$anchoImagen = 20;
$altoImagen = 20;

// Calcular posición centrada dentro de la celda
$posX = $pdf->GetX() + ($anchoCelda - $anchoImagen) / 2;
$posY = $pdf->GetY() + ($altoCelda - $altoImagen) / 2;

// Dibujar contorno de la celda (opcional para alineación visual)
$pdf->Cell($anchoCelda, $altoCelda, '', 1, 0); // Celda vacía con borde

// Insertar imagen centrada
$pdf->Image($imagen, $posX, $posY, $anchoImagen, $altoImagen);

// Continuar con las demás celdas
$pdf->Cell(20, $altoCelda, 'P001', 1);
$pdf->Cell(60, $altoCelda, 'Producto de ejemplo', 1);
$pdf->Cell(20, $altoCelda, '2', 1);
$pdf->Cell(30, $altoCelda, '$5.000', 1);
$pdf->Cell(30, $altoCelda, '$10.000', 1);
$pdf->Ln();


// ?? Pie de totales
$pdf->Ln(5);
$pdf->Cell(160, 6, 'Neto', 1);
$pdf->Cell(30, 6, '$10.000', 1, 1);
$pdf->Cell(160, 6, 'IVA (19%)', 1);
$pdf->Cell(30, 6, '$1.900', 1, 1);
$pdf->Cell(160, 6, 'Total', 1);
$pdf->Cell(30, 6, '$11.900', 1, 1);

// Salida del PDF
$pdf->Output('factura.pdf', 'I');
*/


}


//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "send")
{
   $currtme = time();
   
   $sql = " update supplier_order
            set
            sord_updusr = {$_SESSION["user_id"]},
            sord_upddat = {$currtme},
            sord_status = 3
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = " select *
            from supplier_order
            where
            id = {$_REQUEST["id"]}";
   $attachfile = $CON->select($sql);
   
   $filedir = "./docs.supplierorder/";
   $filename   = "{$attachfile[0]["id"]}.{$attachfile[0]["sord_hash"]}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";
   
   $temp["NAME"]  = "{$attachfile[0]["sord_number"]}{$fileext}";
   $temp["FILE"]  = $pdffile;

   
   $attachfiles[0] = $temp;

   $text    = '<html>
               <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
               <body style="margin:10px" class="page">'.$_REQUEST["msg_body"].'</body></html>';

   $sentmails = sendExternalMail($_REQUEST["msg_header"],
                                 $text,
                                 $_REQUEST["msg_toaddr"],
                                 $_REQUEST["msg_toname"],
                                 "",
                                 "",
                                 $attachfiles);
   if($sentmails <= 0)
      $savemsg = getSaveMessage(false);
   else
      $savemsg = getSaveMessage(true);
}

//DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY
// generateSupplierOrderAprobMail($CON, $_REQUEST["id"]);
//DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY

//----------------------------------------------------------------------------------
if((int)$_REQUEST["aprobsend"] && $_SESSION["supporder_RELOADCALC"] == 0)
{
   generateSupplierOrderAprobMail($CON, $_REQUEST["id"]);

   $sql = " update supplier_order
            set
            sord_aprobblocked = 1
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t2.supp_email, t3.company_short, t4.shop_name, t1.sord_supplier_id, t1.sord_taxes,
                t2.supp_notes, t7.pay_title, t8.oth_name,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from supplier_order t1
         LEFT OUTER JOIN supplier t2      ON t1.sord_supplier_id  = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.sord_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.sord_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.sord_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.sord_crtusr       = t6.id
         LEFT OUTER JOIN payments t7      ON t1.sord_paymentid    = t7.id
         LEFT OUTER JOIN dscbuy_supplier_others_head t8 ON t1.sord_otherdiscount_id = t8.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

if((int)$headdata["sord_aprobblocked"] && (int)$_SESSION["user_type"] != 1)
{  ?>
   <script language="JavaScript">
      alert("La orden de compra se encuentra a la espera de aprobacion.");
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.supp_provinciaid  = t5.id
         where
         t1.id = {$headdata["sord_supplier_id"]}";
$supplier = $CON->select($sql);
$supplier = $supplier[0];

//----------------------------------------------------------------------------------
$posdata    = getSupplierOrderPos($CON, $_REQUEST["id"], $_REQUEST["setPosOrder"]);
$payments   = getPayments($CON);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount < 15)
   $rowcount = 15;
else
   $rowcount = $rowcount + 5;

if($headdata["sord_type"] == 3)
{
   $rowcount = count($posdata) + 1;
}

if($headdata["sord_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}
//----------------------------------------------------------------------------------
if($headdata["sord_status"] == 1 && ($posdata === false || count($posdata) == 0))
{
   $_INITMODE = true;
   $_FILTERCATSQL = "";
   if($headdata["sord_type"] == 0) //NORMAL
      $_FILTERCATSQL = " and t6.cat_id NOT IN (2,3,6) ";
   elseif($headdata["sord_type"] == 1) //TELAS
      $_FILTERCATSQL = " and t6.cat_id IN (6) ";
   elseif($headdata["sord_type"] == 2) //TINTAS
      $_FILTERCATSQL = " and t6.cat_id IN (2) ";
   elseif($headdata["sord_type"] == 3) //BOLSAS
      $_FILTERCATSQL = " and t6.cat_id IN (3) ";
   
   $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                   t2.item_costprice_usd, 'item_type' 'I', t2.item_code
            from item t1
            INNER JOIN item_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["sord_supplier_id"]} )
            INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["sord_shop_id"]} )
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            LEFT OUTER JOIN item_productcats t6 ON t1.id = t6.item_id
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t1.item_purchasable  = 1 {$_FILTERCATSQL}
            order by 2
            LIMIT 0, 200";
   $defaultitems = $CON->select($sql);
   $rowcount = count($defaultitems)+1;
}

//----------------------------------------------------------------------------------
$custselarr = Array();
   
$custname = str_replace(",","", str_replace("'","", str_replace('"',"", trim($headdata["supp_company"]))));
$custmail = str_replace(",","", str_replace("'","", str_replace('"',"", trim($headdata["supp_email"]))));
if($custmail != "")
{
   $temp["NAME"] = $custname;
   $temp["MAIL"] = $custmail;

   array_push($custselarr, $temp);
}

//----------------------------------------------------------------------------------
$sql = " select add_email, add_firstname, add_lastname
         from supplier_contacts
         where
         add_supplier_id = {$headdata["sord_supplier_id"]}
         order by add_pos asc";
$suppcontacts = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($suppcontacts) && $suppcontacts != false; $x++)
{
   $custname = str_replace(",","", str_replace("'","", str_replace('"',"", trim($suppcontacts[$x]["add_firstname"]." ".$suppcontacts[$x]["add_lastname"]))));
   $custmail = str_replace(",","", str_replace("'","", str_replace('"',"", trim($suppcontacts[$x]["add_email"]))));

   if($custmail != "")
   {
      $temp["NAME"] = $custname;
      $temp["MAIL"] = $custmail;

      array_push($custselarr, $temp);
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.oth_name
         from dscbuy_supplier_others_head t1
         INNER JOIN dscbuy_supplier_others t2 ON t1.id = t2.dsc_other_id
         where
         t1.oth_status  > 0 and
         t2.dct_supp_id = {$headdata["sord_supplier_id"]}
         order by t1.oth_name";
$otherdiscs = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_SESSION["user_pricebuy_perm"] || (int)$_REQUEST["user_pricebuy_perm"])
   $hasprcbuyperm = true;
else
   $hasprcbuyperm = false;

//----------------------------------------------------------------------------------
if($_SESSION["xsupplierorders"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function detectEvent (event, rowcount, sordid)
   {
      var xurl = './libs/modules/supplier_order/searchitem.fancy.php?sord_type=<?=$headdata["sord_type"]?>&rowcount=' +rowcount + '&sordid=' +sordid;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="xform_itemprices" enctype="multipart/form-data"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
   echo "onsubmit='return checkform(new Array(this.sord_paymentid,this.sord_date))'";
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="sord_status" value="1">
<input type="hidden" name="aprobsend" value="<?if((int)$_SESSION["supporder_RELOADCALC"] == 1 && (int)$_REQUEST["aprobsend"]) echo "1"?>">
<input type="hidden" name="setPosOrder" value="">
<input type="hidden" name="printpdf" id="printpdf" value="">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricebuy_perm" value="<?=$_REQUEST["user_pricebuy_perm"]?>">
<input type="hidden" name="delitemimagepos" value="">
<?=Nifty_printH("box1", "1180",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="460">
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
   <td class="content_row">
      <input type="text" class="text" style="width:150px" name="sord_number" id="sord_number" <?=$rdlo?>
      value="<?=$headdata["sord_number"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_rowl">Otro Descuento</td>
   <td class="content_row">
      <?php
      if(count($otherdiscs) && $otherdiscs != false)
      {  ?>
         <select class="text" style="width:450px" name="sord_otherdiscount_id" id="sord_otherdiscount_id"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if($dabl == "")
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($otherdiscs as $otherdisc)
               {  ?>
                  <option value="<?=$otherdisc["id"]?>"
                  <?php if($otherdisc["id"] == $headdata["sord_otherdiscount_id"]) echo "selected"?>><?=$otherdisc["oth_name"]?></option>
                  <?php
               }
            }
            else
            {  ?>
               <option value="<?=$headdata["sord_otherdiscount_id"]?>"><?=$headdata["oth_name"]?></option>
               <?php
            }
            ?>
         </select>
         <?php
      }
      else
         echo "- - -";
      ?>
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
      if($_SESSION["xsupplierorders"]["fullcust"] == "") echo "1"; else echo "0"?>'">
       <b><?=$headdata["supp_company"]?></b></a>
    </td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$supplier["supp_rut"]?></b>&nbsp;</td>
</tr>
   <tr <?php if($_SESSION["xsupplierorders"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$supplier["supp_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($supplier["supp_phone"] != "") echo $supplier["supp_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xsupplierorders"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$supplier["name"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][16]?></td>
   <td class="content_row"><?=$supplier["supp_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xsupplierorders"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$supplier["pro_name"]?> - <?=$supplier["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$supplier["supp_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xsupplierorders"]["fullcust"] == "") echo "style='display:none'"?>>
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
   <td class="content_rowl" <?=$cdatastyle?>>Forma de pago</td>
   <td class="content_row" <?=$cdatastyle?>>
      <select class="text" style="width:450px" name="sord_paymentid" id="sord_paymentid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($payments as $payment)
            {  ?>
               <option value="<?=$payment["id"]?>"
               <?php if($payment["id"] == $headdata["sord_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["sord_paymentid"]?>"><?=$headdata["pay_title"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>>Fecha entrega</td>
   <td class="content_row" <?=$cdatastyle?>>
      <input type="text" style="width:80px" id="sord_date" name="sord_date" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["sord_date"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Asunto</td>
   <td class="content_row">
      <input type="text" class="text" style="width:450px" maxlength="254" name="sord_title" value="<?=stripslashes($headdata["sord_title"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <?php
      $statimg = "";
      switch((int)$headdata["sord_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
         case 3: $statimg = "purple_active.gif"; break;
         case 4: $statimg = "blue_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
      <?=getSupplierOrderStatus($headdata["sord_status"], true)?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Idioma</td>
   <td class="content_row">
      <select class="text" style="width:450px" name="sord_en_lang">
         <option value="0" <?if((int)$headdata["sord_en_lang"] == 0) echo "selected"?>>Español</option>
         <option value="1" <?if((int)$headdata["sord_en_lang"] == 1) echo "selected"?>>Inglés</option>
      </select>
   </td>
   <td class="content_rowl">Incoterms</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="sord_incoterms">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <option value="EXW" <?if($headdata["sord_incoterms"] == "EXW") echo "selected"?>>EXW</option>
         <option value="FAS" <?if($headdata["sord_incoterms"] == "FAS") echo "selected"?>>FAS</option>
         <option value="FOB" <?if($headdata["sord_incoterms"] == "FOB") echo "selected"?>>FOB</option>
         <option value="FCA" <?if($headdata["sord_incoterms"] == "FCA") echo "selected"?>>FCA</option>
         <option value="CFR" <?if($headdata["sord_incoterms"] == "CFR") echo "selected"?>>CFR</option>
         <option value="CIF" <?if($headdata["sord_incoterms"] == "CIF") echo "selected"?>>CIF</option>
         <option value="CPT" <?if($headdata["sord_incoterms"] == "CPT") echo "selected"?>>CPT</option>
         <option value="CIP" <?if($headdata["sord_incoterms"] == "CIP") echo "selected"?>>CIP</option>
         <option value="DPU" <?if($headdata["sord_incoterms"] == "DPU") echo "selected"?>>DPU</option>
         <option value="DAP" <?if($headdata["sord_incoterms"] == "DAP") echo "selected"?>>DAP</option>
         <option value="DDP" <?if($headdata["sord_incoterms"] == "DDP") echo "selected"?>>DDP</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Plazo entrega</td>
   <td class="content_row">
      <input type="text" class="text" style="width:450px" maxlength="254" name="sord_plazo_desc" value="<?=$headdata["sord_plazo_desc"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
   <td class="content_rowl">Moneda</td>
   <td>
      <table>
         <td class="content_row"><?=$tipo_moneda["descripcion"]?>
         <td style="text-align:center">
            <?php
               printButton("", "postnav", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/supplier_order/carga_moneda.fancy.php?frmname=form_shppos&fecha_hoy={$headdata["sord_date"]}', 'iframe', 450, 160, 'no')", "money--plus");
            ?>
         </td>
      </table>         
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[proveedor]</td>
   <td class="content_row">
      <textarea class="text" style="width:450px; height:45px" name="sord_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["sord_desc"])?></textarea>
   </td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row">
      <textarea class="text" style="width:450px; height:45px" name="sord_desc_intern" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["sord_desc_intern"])?></textarea>
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
   <td class="content_row">
      <input type="text" style="width:80px" id="sord_crtdat" name="sord_crtdat" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y', $headdata["sord_crtdat"])?>">
   </td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["sord_upddat"])?></td>
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
         t1.doc_tran_type  = 'supplier_order'
         order by t1.id";
$docs = $CON->select($sql);
if(count($docs) && $docs != false)
{  ?>
   <?=Nifty_printH("box1", "1180")?>
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
               printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$docs[$x]["doc_file"]}')", "image");
            else
               printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$docs[$x]["doc_file"]}&name={$docs[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
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
?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?php
if($headdata["sord_type"] == 0)
{  ?>
   <?=Nifty_printH("box2", "1180",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="90">
      <col width="28">
      <col width="380">
      <col>
      <col>
      <col width="50">
      <col width="75">
      <col width="45">
      <?php
      if($_REQUEST["showDiscounts"] == "1")
      {  ?>
         <col>
         <col>
         <col>
         <col>
         <?php
      }
      else
      {  ?>
         <col>
         <col>
         <?php
      }
      ?>
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="14">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td class="content_tbl_header" style="padding:0px">
               Artículos:
               <?php
               if($headdata["sord_type"] == 1)
                  echo "Telas (conversion a Kg via ancho, longitud, gramaje)";
               elseif($headdata["sord_type"] == 2)
                  echo "Tintas (conversion a Kg via cantidad)";
               elseif($headdata["sord_type"] == 3)
                  echo "Bolsas (registro de datos adicionales)";
               ?>
            </td>
            <td class="content_tbl_header" style="padding:0px" width="150">
               <?php
               if($headdata["sord_status"] == 1 && count($posdata) && $posdata != false)
               {  ?>
                  <nobr>
                  <img src="./images/menu/icons/arrow-270-medium.png" style="vertical-align:bottom">
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="document.form_shppos.setPosOrder.value='prodnumber'; submitForm(document.form_shppos);">Ordenar por codigo</a>
                  </nobr>
                  <?php
               }
               ?>
            </td>
            <?php
            if(!(int)$_SESSION["user_pricebuy_perm"] && !(int)$_REQUEST["user_pricebuy_perm"] && $headdata["sord_status"] == 1)
            {  ?>
               <td class="content_tbl_header" style="padding:0px" width="190">
                  <img src="./images/menu/icons/currency.png" style="vertical-align:bottom">
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="showFancybox('/libs/modules/supplier_order/auth.pricechange.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')">Activar cambio de precios</a>
               </td>
               <?php
            }
            ?>
            <td class="content_tbl_header" style="padding:0px" width="140">
               <nobr>
               <img src="./images/menu/icons/calculator.png" style="vertical-align:bottom">
               <?php
               if((int)$_REQUEST["showDiscounts"])
               {
                  $newshowDiscountsTxt = "Ocultar";
                  $newshowDiscounts    = "";
               }
               else
               {
                  $newshowDiscountsTxt = "Mostrar";
                  $newshowDiscounts    = 1;
               }
               if($headdata["sord_status"] == 1)
               {  ?>
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="document.form_shppos.showDiscounts.value='<?=$newshowDiscounts?>'; submitForm(document.form_shppos);"><?=$newshowDiscountsTxt?> condiciones</a>
                  <?php
               }
               else
               {  ?>
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>&showDiscounts=<?=$newshowDiscounts?>';"><?=$newshowDiscountsTxt?> condiciones</a>
                  <?php
               }
               ?>
               </nobr>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" valign="top">Busqueda</td>
      <td class="content_tbl_subheader" valign="top">Act.</td>
      <td class="content_tbl_subheader" valign="top">Artículo</td>
      <td class="content_tbl_subheader" valign="top" align="center">Codigo/Prov</td>
      <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
      <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
      <td class="content_tbl_subheader" valign="top" align="right"><nobr>Precio (neto)</nobr></td>
      <td class="content_tbl_subheader" valign="top" align="right"><nobr>IVA 19%</nobr></td> <!-- IVA 19% -->
      <?php
      if($_REQUEST["showDiscounts"] == "1")
      {  ?>
         <td class="content_tbl_subheader" align="right"><nobr>Subtotal</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Descuentos-Familia</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Desc-Vol.</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Desc-Manual</nobr></td>
         <?php
      }
      else
      {  ?>
         <td class="content_tbl_subheader" valign="top" align="center"><nobr>S/Act</nobr></td>
         <td class="content_tbl_subheader" valign="top" align="center"><nobr>S/Tran</nobr></td>
         <?php
      }
      ?>
      <td class="content_tbl_subheader" valign="top" align="right"><nobr>Precio Total</nobr></td>
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
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_netto_{$x}";
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_taxes_perc_{$x}";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row" valign="top">
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/supplier_order/searchitem.php?sord_type=<?=$headdata["sord_type"]?>&rowcount=<?=$x?>&sordid=<?=$headdata["id"]?>&search=' +this.value} this.value='';"
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
            <?php
            $overlibover = "";
            $ovritemselw = "565px";
            if((int)$posdata[$x]["item_id"])
            {
               $alternateitems = getItemSupplierAlternatives($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"],
                                                             $posdata[$x]["item_pos"], $headdata["sord_supplier_id"], $posdata[$x]["item_costprice_netto_dsc"]);
               if($posdata[$x]["item_invoicebuy_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoicebuy_note"]))."</b>";

               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  $ovritemselw = "355px";
               }
               if(count($alternateitems))
               {
                  $overlibover = "";
                  foreach($alternateitems AS $alternateitems)
                     $overlibover .= "<b class=msg_save_ok>Alternativa: ".str_replace("'","",str_replace('"',"",$alternateitems["supp_short"])).": $".printPrice($alternateitems["supp_price"],2)."</b><br>";
                  ?>
                  <img src="/images/menu/icons/currency.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 450, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#039700', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  if($overlibover != "")
                     $ovritemselw = "335px";
                  else
                     $ovritemselw = "355px";
               }
            }
            ?>
            <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
            onmousedown="markfield(this,0)"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
            onchange="setItemInfos('<?=$x?>', this.value)">
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
            style="width:565px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">

            <?php
            if((int)$posdata[$x]["item_id"] && !$showmanual && $posdata[$x]["item_img"] != "")
            {
               $sql = " select count(*) 'cc'
                        from item_productcats
                        where
                        item_id = {$posdata[$x]["item_id"]} and
                        cat_id  = 7 ";
               $isrepuesto = $CON->select($sql);
               $isrepuesto = (int)$isrepuesto[0]["cc"];

               if($isrepuesto)
               {  ?>
                  <div style="height:5px"></div>
                  <img src="/images/items/<?=$posdata[$x]["item_img"]?>" height="150" style="cursor:pointer"
                  onclick="window.open('/images/items/<?=$posdata[$x]["item_img"]?>');">
                  <div style="height:5px"></div>
                  <?php
               }
            }
            ?>
         </td>
         <td class="content_row" valign="top" align="center" id="altdata_<?=$x?>"><?=$posdata[$x]["item_code"]?>&nbsp;</td>
         <td class="content_row" valign="top" align="center">
            <?php
            if((int)$posdata[$x]["item_id"])
               echo getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
            echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" align="right" valign="top">
            <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);"
            name="item_amount_<?=$x?>" id="item_amount_<?=$x?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">
         </td>
         <td class="content_row" align="right" valign="top">
            <?php
            if(!(int)$headdata["sord_taxes"])
            {
               $inputw    = "65px";
               $moneystr  = "US&nbsp;";
               $numberlim = "4";
            }
            else
            {
               $inputw    = "85px";
               $moneystr  = "";
               $numberlim = "2";
            }

            $inputw    = "65px";
            $moneystr  = $simbolo."&nbsp;";
            $numberlim = "4";
           
            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$dscrdlo?>
            name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>" autocomplete="off"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            </nobr>
         </td>
         <td class="content_row" align="right" valign="top">
            <?php
            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;

            
            if(!(int)$posdata[$x]["item_id"] && !(float)$posdata[$x]["item_costprice_taxes_perc"] && (int)$headdata["sord_taxes"])
               $posdata[$x]["item_costprice_taxes_perc"] = $_SESSION["_CONF"]["conf_taxes"]; 
                        
            ?>
            
            <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?>
            name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>" autocomplete="off"
            value="<?=printPrice($posdata[$x]["item_costprice_taxes_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
         
         </td>
         <td class="content_row" valign="top" align="right" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $ges_line = $posdata[$x]["item_costprice_netto"] * $posdata[$x]["item_amount"];
               ?>
               <input type="text" class="text" readonly
               style="width:75px;text-align:right;"
               value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($ges_line, $numberlim)?>">
               <?php
            }
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if(!$hasprcbuyperm)
                  $dscrdlo = " readonly ";
               else
                  $dscrdlo = $rdlo;
               ?>
               <nobr>
               <input type="checkbox" class="checkbox" name="item_pcat_dsc_act_<?=$x?>" value="1"
               <?php if(!$hasprcbuyperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$x]["item_pcat_dsc_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               <?php
               $isfirst = false;
               for($y = 1; $y <= 4; $y++)
               {  ?>
                  <input type="text" class="text" name="item_pcat_dsc_<?=$x?>_<?=$y?>" style="width:25px;text-align:center;<?php
                  if((int)$posdata[$x]["item_pcat_dsc_act"] && $posdata[$x]["item_pcat_dsc{$y}"] > 0.00)
                     echo "background-color:#E1FFD6";
                  else
                     echo "background-color:#FFD6D8";?>"
                  value="<?=printPrice($posdata[$x]["item_pcat_dsc{$y}"],2)?>" <?=$dscrdlo?>>
                  <?php
               }
               ?>
               </nobr>
               <?php
            }
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"])
            {  ?>
               <nobr>
               <input type="checkbox" class="checkbox" name="item_vol_act_<?=$x?>" value="1"
               <?php if(!$hasprcbuyperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$x]["item_vol_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               
               <input name="item_vol_dsc_<?=$x?>" type="text" class="text" style="width:30px;text-align:center;<?php
               if((int)$posdata[$x]["item_vol_act"] && $posdata[$x]["item_vol_dsc"] > 0.00)
                  echo "background-color:#E1FFD6";
               else
                  echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$x]["item_vol_dsc"] > 0.00) echo printPrice($posdata[$x]["item_vol_dsc"],2);?>" readonly>
               
               <?php
               if((int)$posdata[$x]["item_vol_dsctype"])
                  echo $_SESSION["_CONF"]["conf_currency"];
               else
                  echo "%";
               ?>
               </nobr>
               <?php
            }
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if(!$hasprcbuyperm)
               {
                  $dscrdlo = " readonly ";
                  $dscdabl = " disabled ";
               }
               else
               {
                  $dscrdlo = $rdlo;
                  $dscdabl = $dabl;
               }
               ?>
               <nobr>
               <input name="item_discount_<?=$x?>" type="text" class="text" style="width:30px;text-align:center;<?php
               if($posdata[$x]["item_discount"] > 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$x]["item_discount"] > 0.00) echo printPrice($posdata[$x]["item_discount"],2);?>" <?=$dscrdlo?>>
               
               <select class="text" style="width:40px" name="item_discount_type_<?=$x?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dscdabl?>>
                  <option value="0" <?php if(!(int)$posdata[$x]["item_discount_type"]) echo "selected" ?>>%</option>
                  <option value="1" <?php if( (int)$posdata[$x]["item_discount_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
               </select>
               </nobr>
               <?php
            }
            ?>
         </td>
         <td class="content_row" align="center" valign="top" <?php if($_REQUEST["showDiscounts"] == "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"] && !$showmanual)
               printFancyBoxStock($CON, $headdata["sord_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"], "storehousestock");
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" align="center" valign="top" <?php if($_REQUEST["showDiscounts"] == "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$x]["item_id"] && !$showmanual)
               printFancyBoxStock($CON, $headdata["sord_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"], "transstock");
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly
            style="width:65px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim)?>">
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
   if($hasItems && $supplier["supp_order_minval"] > 0.00 && $headdata["sord_total_netto"] < $supplier["supp_order_minval"])
   {  ?>
      <table border="0" cellpadding="3" cellspacing="0" width="1180">
      <tr>
         <td style="border:3px double red;font-family:Arial;font-size:12px;color:red">
            <b class="msg_save_err"><u>ATENCIÓN!</u> VALOR DE ORDEN DE COMPRA BAJO MONTO MINIMO DEL PROVEEDOR:</b>
            MONTO MINIMO: <u><?=$moneystr?> <?=printPrice($supplier["supp_order_minval"], $numberlim)?></u>,
            VALOR ORDEN:  <u><?=$moneystr?> <?=printPrice($headdata["sord_total_netto"], $numberlim)?></u>
         </td>
      </tr>
      </table>
      <br>
      <?php
   }
}
if($headdata["sord_type"] == 1)
{  ?>
   <?=Nifty_printH("box2", "99%", 0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="80">
      <col width="25">
      <col width="375">
      <col>
      <col>
      <col>
      <col>
      <col>
      <col width="60">
      <col width="70">
      <col width="110">
      <col width="75">
   </colgroup>
   <tr>
      <td class="content_tbl_header" valign="top">Busqueda</td>
      <td class="content_tbl_header" valign="top">Act.</td>
      <td class="content_tbl_header" valign="top">Artículo</td>
      <td class="content_tbl_header" valign="top">Color</td>
      <td class="content_tbl_header" valign="top">Material</td>
      <td class="content_tbl_header" valign="top">Gr/m2</td>
      <td class="content_tbl_header" valign="top">Ancho</td>
      <td class="content_tbl_header" valign="top">Longitud</td>
      <td class="content_tbl_header" valign="top" align="right">Cantidad</td>
      <td class="content_tbl_header" valign="top" align="right">Kg</td>
      <td class="content_tbl_header" valign="top" align="right">$/Unit.</td>
      <td class="content_tbl_header" valign="top" align="right">$/Subtotal</td>
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
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_netto_{$x}";
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_taxes_perc_{$x}";

      //----------------------------------------------------------------------------------
      $item_reg_width   = 0;
      $item_reg_gsm     = 0;
      $item_reg_length  = 0;
      $item_kgs         = 0;
      $item_colorname   = "";
      $item_matname     = "";

      if((int)$posdata[$x]["item_id"])
      {
         $sql = " select *
                  from item
                  where
                  id = {$posdata[$x]["item_id"]}";
         $allitemdata = $CON->select($sql);
         $allitemdata = $allitemdata[0];
         
         $item_reg_width   = (float)$allitemdata["item_reg_width"];
         $item_reg_gsm     = (float)$allitemdata["item_reg_gsm"];
         $item_reg_length  = (float)$allitemdata["item_reg_length"];
         $item_kgs         = round(($item_reg_gsm / 1000) * ($item_reg_width / 100) * $item_reg_length * $posdata[$x]["item_amount"]);

         $sql = " select *
                  from item_productcats
                  where
                  item_id   = {$posdata[$x]["item_id"]}";
         $pcatdata = $CON->select($sql);
         $pcatdata = $pcatdata[0];

         $sql = " select *
                  from tran_comments_item_vals
                  where
                  item_id = {$posdata[$x]["item_id"]}";
         $comvals = $CON->select($sql);
         unset($_COMVALS);
         foreach($comvals AS $comval)
            $_COMVALS[$comval["com_id"]] = $comval["val_id"];

         $sql = " select t1.com_name, t3.*
                  from tran_comments t1
                  INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                  INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                  where
                  t1.com_status  > 0 and
                  t2.cat_id      = {$pcatdata["cat_id"]} and
                  t3.add_status  > 0
                  order by t1.com_name, t3.add_name";
         $trancoms = $CON->select($sql);
         foreach($trancoms AS $trancom)
         {
            if(strpos(strtoupper($trancom["com_name"]), "MATERIAL") !== false &&
               $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
               $item_matname = $trancom["add_name"];
               
            if(strpos(strtoupper($trancom["com_name"]), "COLOR") !== false &&
               $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
               $item_colorname = $trancom["add_name"];
         }
      }

      //----------------------------------------------------------------------------------
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row_os">
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/supplier_order/searchitem.php?sord_type=<?=$headdata["sord_type"]?>&allowdoubles=1&rowcount=<?=$x?>&sordid=<?=$headdata["id"]?>&search=' +this.value} this.value='';"
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
         <td class="content_row_os" valign="top">
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
         <td class="content_row_os" valign="top">
            <?php
            $overlibover = "";
            $ovritemselw = "565px";
            if((int)$posdata[$x]["item_id"])
            {
               $alternateitems = getItemSupplierAlternatives($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"],
                                                             $posdata[$x]["item_pos"], $headdata["sord_supplier_id"], $posdata[$x]["item_costprice_netto_dsc"]);
               if($posdata[$x]["item_invoicebuy_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoicebuy_note"]))."</b>";

               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  $ovritemselw = "355px";
               }
               if(count($alternateitems))
               {
                  $overlibover = "";
                  foreach($alternateitems AS $alternateitems)
                     $overlibover .= "<b class=msg_save_ok>Alternativa: ".str_replace("'","",str_replace('"',"",$alternateitems["supp_short"])).": $".printPrice($alternateitems["supp_price"],2)."</b><br>";
                  ?>
                  <img src="/images/menu/icons/currency.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 450, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#039700', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  if($overlibover != "")
                     $ovritemselw = "335px";
                  else
                     $ovritemselw = "355px";
               }
            }
            ?>
            <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
            onmousedown="markfield(this,0)"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
            onchange="setItemInfos('<?=$x?>', this.value)">
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
            style="width:375px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">
         </td>
         <td class="content_row_os" align="left"><?=$item_colorname?>&nbsp;</td>
         <td class="content_row_os" align="left"><?=$item_matname?>&nbsp;</td>
         <td class="content_row_os" align="left"><?php if($item_reg_gsm > 0) { echo printPrice($item_reg_gsm)?> gr <?php } ?>&nbsp;</td>
         <td class="content_row_os" align="left"><?php if($item_reg_width > 0) { echo printPrice($item_reg_width)?> cm <?php } ?>&nbsp;</td>
         <td class="content_row_os" align="left"><?php if($item_reg_length > 0) { echo printPrice($item_reg_length)?> m <?php } ?>&nbsp;</td>
         <td class="content_row_os" align="right" valign="top">
            <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);"
            name="item_amount_<?=$x?>" id="item_amount_<?=$x?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">
         </td>
         <td class="content_row_os" align="right">
            <?=printPrice($item_kgs)?>
            <input type="hidden" name="item_kgs_<?=$x?>" id="item_kgs_<?=$x?>" value="<?=$item_kgs?>">
         </td>
         <td class="content_row_os" align="right" valign="top">
            <?php
            if(!(int)$headdata["sord_taxes"])
            {
               $inputw    = "65px";
               $moneystr  = "US&nbsp;";
               $numberlim = "4";
            }
            else
            {
               $inputw    = "85px";
               $moneystr  = "";
               $numberlim = "2";
            }
 
            $inputw    = "65px";
            $moneystr  = $simbolo."&nbsp;";
            $numberlim = "4";


            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$dscrdlo?>
            name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>" autocomplete="off"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            </nobr>
         </td>
         <td class="content_row_os" align="right" valign="top" style="display:none">
            <?php
            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;

            if(!(float)$posdata[$x]["item_costprice_taxes_perc"] && (int)$headdata["sord_taxes"])
               $posdata[$x]["item_costprice_taxes_perc"] = $_SESSION["_CONF"]["conf_taxes"];
          
            ?>
            
            <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?>
            name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>" autocomplete="off"
            value="<?=printPrice($posdata[$x]["item_costprice_taxes_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
         
         </td>
         <td class="content_row_os" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly
            style="width:65px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim)?>">
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
}
if($headdata["sord_type"] == 2)
{  ?>
   <?=Nifty_printH("box2", "1180", 0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="80">
      <col width="25">
      <col width="375">
      <col>
      <col width="60">
      <col width="70">
      <col width="110">
      <col width="75">
   </colgroup>
   <tr>
      <td class="content_tbl_header" valign="top">Busqueda</td>
      <td class="content_tbl_header" valign="top">Act.</td>
      <td class="content_tbl_header" valign="top">Artículo</td>
      <td class="content_tbl_header" valign="top">Color</td>
      <td class="content_tbl_header" valign="top" align="right">Cantidad</td>
      <td class="content_tbl_header" valign="top" align="right">Kg</td>
      <td class="content_tbl_header" valign="top" align="right">$/Unit.</td>
      <td class="content_tbl_header" valign="top" align="right">$/Subtotal</td>
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
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_netto_{$x}";
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_taxes_perc_{$x}";

      //----------------------------------------------------------------------------------
      $item_kgs         = 0;
      $item_colorname   = "";

      if((int)$posdata[$x]["item_id"])
      {
         $sql = " select *
                  from item
                  where
                  id = {$posdata[$x]["item_id"]}";
         $allitemdata = $CON->select($sql);
         $allitemdata = $allitemdata[0];
         
         $item_kgs = (float)$allitemdata["item_reg_kg"] * $posdata[$x]["item_amount"];

         $sql = " select cat_id
                  from item_productcats
                  where
                  item_id = {$posdata[$x]["item_id"]}";
         $pcatdata = $CON->select($sql);
         $pcatdata = $pcatdata[0];

         $sql = " select *
                  from tran_comments_item_vals
                  where
                  item_id = {$posdata[$x]["item_id"]}";
         $comvals = $CON->select($sql);
         unset($_COMVALS);
         foreach($comvals AS $comval)
            $_COMVALS[$comval["com_id"]] = $comval["val_id"];

         $sql = " select t1.com_name, t3.*
                  from tran_comments t1
                  INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                  INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                  where
                  t1.com_status  > 0 and
                  t2.cat_id      = {$pcatdata["cat_id"]} and
                  t3.add_status  > 0
                  order by t1.com_name, t3.add_name";
         $trancoms = $CON->select($sql);
         foreach($trancoms AS $trancom)
         {
            if(strpos(strtoupper($trancom["com_name"]), "COLOR") !== false &&
               $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
               $item_colorname = $trancom["add_name"];
         }
      }

      //----------------------------------------------------------------------------------
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row_os">
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/supplier_order/searchitem.php?sord_type=<?=$headdata["sord_type"]?>&rowcount=<?=$x?>&sordid=<?=$headdata["id"]?>&search=' +this.value} this.value='';"
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
         <td class="content_row_os" valign="top">
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
         <td class="content_row_os" valign="top">
            <?php
            $overlibover = "";
            $ovritemselw = "565px";
            if((int)$posdata[$x]["item_id"])
            {
               $alternateitems = getItemSupplierAlternatives($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"],
                                                             $posdata[$x]["item_pos"], $headdata["sord_supplier_id"], $posdata[$x]["item_costprice_netto_dsc"]);
               if($posdata[$x]["item_invoicebuy_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoicebuy_note"]))."</b>";

               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  $ovritemselw = "355px";
               }
               if(count($alternateitems))
               {
                  $overlibover = "";
                  foreach($alternateitems AS $alternateitems)
                     $overlibover .= "<b class=msg_save_ok>Alternativa: ".str_replace("'","",str_replace('"',"",$alternateitems["supp_short"])).": $".printPrice($alternateitems["supp_price"],2)."</b><br>";
                  ?>
                  <img src="/images/menu/icons/currency.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 450, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#039700', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  if($overlibover != "")
                     $ovritemselw = "335px";
                  else
                     $ovritemselw = "355px";
               }
            }
            ?>
            <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
            onmousedown="markfield(this,0)"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
            onchange="setItemInfos('<?=$x?>', this.value)">
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
            style="width:375px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">
         </td>
         <td class="content_row_os" align="left"><?=$item_colorname?>&nbsp;</td>
         <td class="content_row_os" align="right" valign="top">
            <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);"
            name="item_amount_<?=$x?>" id="item_amount_<?=$x?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">
         </td>
         <td class="content_row_os" align="right">
            <?=printPrice($item_kgs)?>
            <input type="hidden" name="item_kgs_<?=$x?>" id="item_kgs_<?=$x?>" value="<?=$item_kgs?>">
         </td>
         <td class="content_row_os" align="right" valign="top">
            <?php
            if(!(int)$headdata["sord_taxes"])
            {
               $inputw    = "65px";
               $moneystr  = "US&nbsp;";
               $numberlim = "4";
            }
            else
            {
               $inputw    = "85px";
               $moneystr  = "";
               $numberlim = "2";
            }

            $inputw    = "65px";
            $moneystr  = $simbolo."&nbsp;";
            $numberlim = "4";


            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$dscrdlo?>
            name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>" autocomplete="off"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            </nobr>
         </td>
         <td class="content_row_os" align="right" valign="top" style="display:none">
            <?php
            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;

            
            if(!(float)$posdata[$x]["item_costprice_taxes_perc"] && (int)$headdata["sord_taxes"])
               $posdata[$x]["item_costprice_taxes_perc"] = $_SESSION["_CONF"]["conf_taxes"];
         
               
            ?>
            
            <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?>
            name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>" autocomplete="off"
            value="<?=printPrice($posdata[$x]["item_costprice_taxes_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            
         </td>
         <td class="content_row_os" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly
            style="width:65px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim)?>">
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
}
if($headdata["sord_type"] == 3)
{
   $_specopts = getSuppOrderCharacts();
   ?>
   <?=Nifty_printH("box2", "99%", 0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="80">
      <col width="25">
      <col width="375">
      <col>
      <col width="60">
      <col width="110">
      <col width="75">
   </colgroup>
   <tr>
      <td class="content_tbl_header" valign="top">Busqueda</td>
      <td class="content_tbl_header" valign="top">Act.</td>
      <td class="content_tbl_header" valign="top">Artículo</td>
      <td class="content_tbl_header" valign="top">Caracteristicas</td>
      <td class="content_tbl_header" valign="top" align="right">Cantidad</td>
      <td class="content_tbl_header" valign="top" align="right">$/Unit.</td>
      <td class="content_tbl_header" valign="top" align="right">$/Subtotal</td>
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
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_netto_{$x}";
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_costprice_taxes_perc_{$x}";

      unset($_ITEMSPECS);
      if((int)$posdata[$x]["item_id"]) 
      {
         $sql = " select *
                  from supplier_order_items_specs
                  where
                  sord_id     = {$posdata[$x]["sord_id"]} and
                  item_id     = {$posdata[$x]["item_id"]} and
                  item_pos    = {$posdata[$x]["item_pos"]}";
         $itemspecs = $CON->select($sql);
         foreach($itemspecs AS $itemspec)
            $_ITEMSPECS[$itemspec["spec_id"]] = $itemspec["spec_value"];
      }

      //----------------------------------------------------------------------------------
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row_os" valign="top">
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/supplier_order/searchitem.php?sord_type=<?=$headdata["sord_type"]?>&allowdoubles=1&rowcount=<?=$x?>&sordid=<?=$headdata["id"]?>&search=' +this.value} this.value='';"
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
         <td class="content_row_os" valign="top">
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
         <td class="content_row_os" valign="top">
            <?php
            $overlibover = "";
            $ovritemselw = "565px";
            if((int)$posdata[$x]["item_id"])
            {
               $alternateitems = getItemSupplierAlternatives($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"],
                                                             $posdata[$x]["item_pos"], $headdata["sord_supplier_id"], $posdata[$x]["item_costprice_netto_dsc"]);
               if($posdata[$x]["item_invoicebuy_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoicebuy_note"]))."</b>";

               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  $ovritemselw = "355px";
               }
               if(count($alternateitems))
               {
                  $overlibover = "";
                  foreach($alternateitems AS $alternateitems)
                     $overlibover .= "<b class=msg_save_ok>Alternativa: ".str_replace("'","",str_replace('"',"",$alternateitems["supp_short"])).": $".printPrice($alternateitems["supp_price"],2)."</b><br>";
                  ?>
                  <img src="/images/menu/icons/currency.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 450, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#039700', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  if($overlibover != "")
                     $ovritemselw = "335px";
                  else
                     $ovritemselw = "355px";
               }
            }
            ?>
            <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>" name="item_id_<?=$x?>" id="item_id_<?=$x?>"
            onmousedown="markfield(this,0)"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
            onchange="setItemInfos('<?=$x?>', this.value)">
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
            style="width:375px;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">
            <br>
            <?php
            if($posdata[$x]["item_image"] != "")
            {  ?>
               <center>
               <img src="/docs.supplierorder/<?=$posdata[$x]["item_image"]?>" width="90%" style="margin-top:10px;border:0px;max-height:240px">
               <?php
               if($rdlo == "")
               {  ?>
                  <input type="button" class="button" value="Eliminar imagen" style="margin-top:3px"
                  onclick="if(askDel('')) { document.form_shppos.delitemimagepos.value = '<?=$x?>'; document.form_shppos.submit();  } ">
                  <?php
               }
               ?>
               </center>
               <?php
            }
            elseif(!$_INITMODE)
            {  ?>
               <table border="0" cellspacing="0" cellpadding="3" width="100%" style="margin-top:15px">
               <tr>
                  <td class="content_row_clear" width="60"><nobr>Imagen</nobr></td>
                  <td class="content_row_clear">
                     <input type="file" name="itemimage_<?=$x?>" style="width:90%">
                  </td>
               </tr>
               </table>
               <?php
            }
            ?>
         </td>
         <td class="content_row_os" align="left">
            <?php
            if(!$_INITMODE)
            {  ?>
               <table border="0" cellspacing="0" cellpadding="3" width="100%">
               <?php
               foreach($_specopts AS $_specopt)
               {  ?>
                  <tr>
                     <td class="content_row_os content_rowl" width="1" valign="top"><nobr><?=$_specopt["name"]?></nobr></td>
                     <td class="content_row_os">
                        <?php
                        if($_specopt["small"])
                        {  ?>
                           <input type="text" class="text" style="width:100%" name="specdata_<?=$x?>_<?=$_specopt["id"]?>"
                           value="<?=$_ITEMSPECS[$_specopt["id"]]?>" <?=$rdlo?>>
                           <?php
                        }
                        else
                        {  ?>
                           <textarea class="text" style="width:100%;height:40px" <?=$rdlo?>
                           name="specdata_<?=$x?>_<?=$_specopt["id"]?>"><?=stripslashes($_ITEMSPECS[$_specopt["id"]])?></textarea>
                           <?php
                        }
                        ?>
                     </td>
                  </tr>
                  <?php
               }
               ?>
               </table>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row_os" align="right" valign="top">
            <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);"
            name="item_amount_<?=$x?>" id="item_amount_<?=$x?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount"],2)?>">
         </td>
         <td class="content_row_os" align="right" valign="top">
            <?php
            if(!(int)$headdata["sord_taxes"])
            {
               $inputw    = "65px";
               $moneystr  = "US&nbsp;";
               $numberlim = "4";
            }
            else
            {
               $inputw    = "85px";
               $moneystr  = "";
               $numberlim = "2";
            }

            $inputw    = "65px";
            $moneystr  = $simbolo."&nbsp;";
            $numberlim = "4";


            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$dscrdlo?>
            name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>" autocomplete="off"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            </nobr>
         </td>
         <td class="content_row_os" align="right" valign="top" style="display:none">
            <?php
            if(!$hasprcbuyperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;

            if(!(float)$posdata[$x]["item_costprice_taxes_perc"] && (int)$headdata["sord_taxes"])
               $posdata[$x]["item_costprice_taxes_perc"] = $_SESSION["_CONF"]["conf_taxes"];

            ?>
            
            <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?>
            name="item_costprice_taxes_perc_<?=$x?>" id="item_costprice_taxes_perc_<?=$x?>" autocomplete="off"
            value="<?=printPrice($posdata[$x]["item_costprice_taxes_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);">
            
         </td>
         <td class="content_row_os" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly
            style="width:65px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim)?>">
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
}
?>
<table border="0" cellpadding="0" cellspacing="0">
<tr>
   <td valign="top">
      <?php
      if($_REQUEST["showDiscounts"] == "1")
         $ftablewidth = 645;
      else
         $ftablewidth = 1180;
      ?>
      <?=Nifty_printH("box1", $ftablewidth, 0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="55">
         <col width="100">
      </colgroup>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="2">SUBTOTAL</td>
         <td class="content_row" align="right">
            <input type="text" class="text" style="width:120px;text-align:right" readonly
            value="<?=$moneystr?><?=printPrice($headdata["sord_item_netto_total"], $numberlim)?>">
         </td>
      </tr>
      <?php
      if($headdata["sord_value_dsc_netto_total"] > 0.00)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" style="color:red">DESCUENTO POR MONTO</td>
            <td class="content_row" style="color:red" align="right"><?=printPrice($headdata["sord_value_dsc_netto_total"] / $headdata["sord_item_netto_total"] * 100,2)?> %</td>
            <td class="content_row" style="color:red" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;background-color:#FFD6D8" readonly
               value="- <?=$moneystr?><?=printPrice($headdata["sord_value_dsc_netto_total"], $numberlim)?>">
            </td>
         </tr>
         <?php
      }
      if($headdata["sord_payment_dsc_netto_total"] > 0.00)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" style="font-weight:normal;color:red">DESCUENTO POR COND. DE PAGO</td>
            <td class="content_row" style="font-weight:normal;color:red" align="right">
               <?=printPrice($headdata["sord_payment_dsc_netto_total"] / ($headdata["sord_item_netto_total"] - $headdata["sord_value_dsc_netto_total"]) * 100, 2)?>
               %
             </td>
            <td class="content_row" style="color:red" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;background-color:#FFD6D8" readonly
               value="- <?=$moneystr?><?=printPrice($headdata["sord_payment_dsc_netto_total"], $numberlim)?>"></td>
         </tr>
         <?php
      }
      if($headdata["sord_supplier_dsc_finance_total"] > 0.00)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" style="font-weight:normal;color:red">DESCUENTO FINANCIERO</td>
            <td class="content_row" style="font-weight:normal;color:red" align="right">
               <?=printPrice($headdata["sord_supplier_dsc_finance_total"] / ($headdata["sord_item_netto_total"] - $headdata["sord_value_dsc_netto_total"] - $headdata["sord_payment_dsc_netto_total"]) * 100, 2)?>
               %
             </td>
            <td class="content_row" style="color:red" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;background-color:#FFD6D8" readonly
               value="- <?=$moneystr?><?=printPrice($headdata["sord_supplier_dsc_finance_total"], $numberlim)?>">
            </td>
         </tr>
         <?php
      }
      if($headdata["sord_total_taxes"] > 0.00)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2">NETO</td>
            <td class="content_row_totals content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
               value="<?=$moneystr?><?=printPrice($headdata["sord_total_netto"], $numberlim)?>">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row content_rowl" colspan="2">IVA ( <?=$iva?> % ) </td>
            <td class="content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
               value="<?=$moneystr?><?=printPrice($headdata["sord_total_taxes"], $numberlim)?>">
            </td>
         </tr>
         <?php
      }
      ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_totals content_rowl" colspan="2">TOTAL</td>
         <td class="content_row_totals content_row" align="right">
            <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
            value="<?=$moneystr?><?=printPrice($headdata["sord_total_brutto"], $numberlim)?>">
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td width="15" class="content_row_clear">&nbsp;</td>
   <td valign="top">
      <?php
      if($_REQUEST["showDiscounts"] == "1" && $headdata["sord_status"] < 2 && $hasprcbuyperm)
      {  ?>
         <script language="JavaScript">
            function setCalcDiscountsGLB()
            {
               var val = document.getElementById('glb_item_discount').value;
               var mod = document.getElementById('glb_item_discount_type').value;

               var $inputs = $('#xform_itemprices :input');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_discount_') > -1)
                     $(this).val(val);
               });

               var $inputs = $('#xform_itemprices select');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_discount_type_') > -1)
                     $(this).val(mod);
               });
            }

            function setCalcDiscountsCAT()
            {
               var val1 = document.getElementById('glb_item_pcat_dsc_1').value;
               var val2 = document.getElementById('glb_item_pcat_dsc_2').value;
               var val3 = document.getElementById('glb_item_pcat_dsc_3').value;
               var val4 = document.getElementById('glb_item_pcat_dsc_4').value;

               var $inputs = $('#xform_itemprices :input');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_1') == objname.length - 2)
                     $(this).val(val1);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_2') == objname.length - 2)
                     $(this).val(val2);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_3') == objname.length - 2)
                     $(this).val(val3);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_4') == objname.length - 2)
                     $(this).val(val4);
               });
            }

            function setCalcDiscountsACT(mode)
            {
               var sfield = '';
               var ofield = '';
               if(mode == 'CAT') { ofield = 'glb_item_pcat_act';  sfield = 'item_pcat_dsc_act_'; }
               if(mode == 'VOL') { ofield = 'glb_item_vol_act';   sfield = 'item_vol_act_'; }
               if(mode == 'VAL') { ofield = 'glb_item_val_act';   sfield = 'item_value_act_'; }
                  
               var act  = document.getElementById(ofield).checked;
               var $inputs = $('#xform_itemprices :checkbox');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf(sfield) > -1)
                     $(this).attr('checked', act);
               });
            }
         </script>
         <?=Nifty_printH("box1", "510", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
         <colgroup>
            <col>
            <col width="180">
            <col width="75">
         </colgroup>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" style="border-top:0px">DESCUENTOS GLOBALES</td>
            <td class="content_row" style="border-top:0px" align="right">
               <input id="glb_item_discount" type="text" class="text" style="width:75px;text-align:center;">

               <select class="text" style="width:35px" id="glb_item_discount_type"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="0">%</option>
                  <option value="1"><?=$_SESSION["_CONF"]["conf_currency"]?></option>
               </select>
            </td>
            <td class="content_row" style="border-top:0px" align="right">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsGLB()">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl">DESCUENTOS POR FAMILIA / COND.</td>
            <td class="content_row" align="right">
               <?php
               for($y = 1; $y <= 4; $y++)
               {  ?>
                  <input type="text" class="text" id="glb_item_pcat_dsc_<?=$y?>" style="width:26px;text-align:center;" value="">
                  <?php
               }
               ?>
            </td>
            <td class="content_row" align="right">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsCAT()">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
            <td class="content_row" align="right">
               POR FAMILIA / CONDICIÓN
               <input type="checkbox" class="checkbox" id="glb_item_pcat_act" value="1" checked>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsACT('CAT')">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
            <td class="content_row" align="right">
               POR VOLUMEN
               <input type="checkbox" class="checkbox" id="glb_item_vol_act" value="1" checked>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="button" class="button" value="&gt;&gt;" style="width:65px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               onclick="setCalcDiscountsACT('VOL')">
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<br>
<?php
if($_REQUEST["showDiscounts"] == "1")
   $ftablewidth = 1170;
else
   $ftablewidth = 1180;
?>
<?=Nifty_printH("boxopt_b", $ftablewidth, 0)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   $sql = " select count(*) 'cc'
            from shipment
            where
            shp_supporder_id = {$headdata["id"]} and
            shp_status > 1";
   $hasshipments = $CON->select($sql);
   $hasshipments = (int)$hasshipments[0]["cc"];
   if($headdata["sord_status"] >= 2 && !(int)$headdata["sord_order_shipped"] && !$hasshipments)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.sord_status.value='1';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   if(($headdata["sord_status"] == 2 || $headdata["sord_status"] == 3) &&
      (!(int)$headdata["sord_order_shipped"]))
   {  ?>
      <td align="right" width="180" style="padding-right:5px">
         <?php
         printButton("Cerrar OC para entradas", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&cancelshp=1')", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   if($headdata["sord_status"] == 1)
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
      {
         $canfinalize = true;
         if(is_array($_AMTRANGES) && count($_AMTRANGES) > 0 && (int)$_SESSION["user_type"] != 1)
         {
            $sql = " select *
                     from supplier_order
                     where
                     id = {$_REQUEST["id"]}";
            $sodata = $CON->select($sql);
            $sodata = $sodata[0];

            $sord_total_brutto = $sodata["sord_total_brutto"];
            if(!(int)$sodata["sord_taxes"])
            {
               $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
               echo("valor moneda".$usdval);
               $sord_total_brutto = $sord_total_brutto * $usdval;
            }

            foreach($_AMTRANGES AS $_AMTRANGE)
            {
               if($sord_total_brutto >= $_AMTRANGE["INIT"] && $sord_total_brutto <= $_AMTRANGE["END"])
                  $canfinalize = false;
            }
         }

         if($canfinalize)
         {  ?>
            <td align="right" width="130" style="padding-right:5px" id="idx_finalbtn1">
               <?php
               printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.sord_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
               ?>
            </td>
            <?php
         }
         else
         {  ?>
            <td align="right" width="130" style="padding-right:5px" id="idx_finalbtn2">
               <?php
               printButton("Pedir aprobación", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.aprobsend.value='1';submitForm(document.form_shppos);}", "tick-circle-frame");
               ?>
            </td>
            <?php
         }
      }
   }
   if($headdata["sord_status"] == 4)
   {  ?>
      <td align="right" width="180" style="padding-right:5px">
         <?php
         printButton("Abrir OC para entradas", "postnav_save", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&revertshp=1')", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   if($headdata["sord_status"] >= 2 && $headdata["sord_hash"] != "")
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&id={$_REQUEST["id"]}&hash={$headdata["sord_hash"]}.pdf&name={$headdata["sord_number"]}.pdf&path=../../../docs.supplierorder/'", "script");
         ?>
      </td>
      <?php
      if($headdata["sord_status"] == 2 && count($custselarr))
      {  ?>
         <td width="130">
            <?php
            printButton("Enviar", "postnav_save", "javascript:document.all.idx_mail.style.display='';void(0)", "", "mail");
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
<?php
if($headdata["sord_status"] == 2 && $headdata["sord_hash"] != "")
{
   if(count($custselarr))
   {  ?>
      <div id="idx_mail" style="display:none">
      <script type="text/javascript" src="./libs/jscripts/tinymce_3_2_2_3/jscripts/tiny_mce/tiny_mce.js"></script>
      <script type="text/javascript">
         tinyMCE.init({
            mode : "specific_textareas",
            editor_selector : "mceEditor",
            theme : "advanced",
            plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
            theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,outdent,indent,blockquote,|,forecolor,backcolor,tablecontrols",
            theme_advanced_buttons2 : "", theme_advanced_buttons3 : "", theme_advanced_buttons4 : "",
            theme_advanced_toolbar_location : "top", theme_advanced_toolbar_align : "left",
            content_css : "css/content.css", template_external_list_url : "lists/template_list.js", external_link_list_url : "lists/link_list.js", external_image_list_url : "lists/image_list.js", media_external_list_url : "lists/media_list.js",
            width: "810px", height: "150px", force_br_newlines: true, forced_root_block: ''
         });
      </script>
      <form action="index.php" method="post" name="xform_docsend"
       onsubmit="return checkform(new Array(this.msg_header, this.msg_body))">
       <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subexec" value="send">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <?=Nifty_printH("box2", "1180",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="150">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Enviar correo</td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top" rowspan="2"><?=$_LANG["MODULE"]["MSG"][19]?></td>
         <td class="content_row">
            <?php
            for($yy = 0; $yy < count($custselarr); $yy++)
            {  ?>
               <input type="radio" name="xrecpt" <?php if($yy == 0) echo "checked"?>
               onclick="document.xform_docsend.msg_toname.value='<?=$custselarr[$yy]["NAME"]?>';
                        document.xform_docsend.msg_toaddr.value='<?=$custselarr[$yy]["MAIL"]?>'">
               <?=$custselarr[$yy]["NAME"]?> &lt;<?=$custselarr[$yy]["MAIL"]?>&gt;
               <br>
               <?php
            }
            $msg_header = "{$headdata["sord_title"]}";

            $sql = " select user_mail_signature_html
                     from user
                     where
                     id = {$_SESSION["user_id"]}";
            $mailsig = $CON->select($sql);
            $mailsig = $mailsig[0]["user_mail_signature_html"];
            if(trim($mailsig) != "")
               $msg_body = "<br><br>".$mailsig;
            ?>
         </td>
      </tr>
         <td class="content_row">
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="60">
               <col>
            </colgroup>
            <tr>
               <td class="content_row_clear">Nombre *</td>
               <td class="content_row_clear">
                  <input type="text" name="msg_toname" class="text" style="width:280px" value="<?=$custselarr[0]["NAME"]?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </td>
            </tr>
            <tr>
               <td class="content_row_clear">E-Mail *</td>
               <td class="content_row_clear">
                  <input type="text" name="msg_toaddr" class="text" style="width:280px" value="<?=$custselarr[0]["MAIL"]?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["MSG"][29]?> *</td>
         <td class="content_row">
            <input type="text" class="text" style="width:810px" maxlength="254" name="msg_header" value="<?=$msg_header?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["MSG"][30]?> *</td>
         <td class="content_row">
            <textarea class="text mceEditor" style="width:810px; height:150px" name="msg_body"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($msg_body)?></textarea>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <table border="0" cellspacing="0" cellpadding="0" width="1180">
      <tr>
         <td>&nbsp;</td>
         <td width="130" style="padding-right:5px">
            <?php
            printButton("Enviar", "postnav_save", "javascript: deactivateFormChange()", "tinyMCE.triggerSave();submitForm(document.xform_docsend)", "mail");
            ?>
         </td>
      </tr>
      </form>
      </table>
      <br><br><br>
      </div>
      <?php
   }
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if($headdata["sord_status"] == 1 && !$hasItems)
{
   if((int)$headdata["sord_taxes"])
   {
      $prcfield = "item_costprice_netto";
      $decimals = 2;
   }
   else
   {
      $prcfield = "item_costprice_usd";
      $decimals = 2;
   }

   ?>
   <script language="JavaScript">
   <?php
   for($x = 0; $x < count($defaultitems) && $defaultitems != false; $x++)
   {
      if($defaultitems[$x]["item_type"] == "item_typeI")
         $defaultitems[$x]["item_type"] = "item";
      else
         $defaultitems[$x]["item_type"] = "itemlist";

      $unitdesc      = getItemUnitDesc($CON, $defaultitems[$x]["id"], $defaultitems[$x]["item_type"]);
      $desc          = trim(addslashes($defaultitems[$x]["item_title"]));

      $addcode = "";
      if($defaultitems[$x]["item_code"] != "")
         $addcode = " - ".$defaultitems[$x]["item_code"];
      ?>
      var obj = document.getElementById('item_id_<?=$x?>');
      obj.options.length = 0;
      
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$defaultitems[$x]["item_number_prod"]?> <?=$addcode?> - <?=$desc?> (<?=$unitdesc?>)');
      newOpt.value = '<?=$defaultitems[$x]["id"]?>#<?=$defaultitems[$x]["item_type"]?>#<?=printPrice($defaultitems[$x][$prcfield], $decimals)?>#<?=printPrice($defaultitems[$x]["item_costprice_taxes_perc"],2)?>#0';
      obj.options[newIndex] = newOpt;

      obj.selectedIndex  = 0;
      setItemInfos(<?=$x?>, newOpt.value);
      <?php
   }
   ?>
   </script>
   <?php
}

if($_REQUEST["setPosOrder"] == "prodnumber")
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
}
if($_SESSION["supporder_RELOADCALC"] == 1)
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
   $_SESSION["supporder_RELOADCALC"] = 2;
}
if((int)$_REQUEST["printpdf"])
{
   $sql = " select t1.*
            from supplier_order t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $doctitle = $headdata["sord_number"].".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&id={$_REQUEST["id"]}&hash={$headdata["sord_hash"]}.pdf&name={$doctitle}&path=../../../docs.supplierorder/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>