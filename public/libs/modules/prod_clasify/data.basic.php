<script type="text/javascript" src="https://me.kis.v2.scr.kaspersky-labs.com/FD126C42-EBFA-4E12-B309-BB3FDD723AC1/main.js?attr=ZuO_62NIvKfggp_s9HB4So3foDJ4jLRn7YV8NTJnserImNhlEtSjkQQOTFVYTwDWmRI551-7hUx-SjaypEJQhG_WTctp60KhFm3aTIGQG6SGte1L3WdJGGZZMi03aDy3BBTpFSteXYsBj97Gk81M7HeEIJzA8FlRIGZ-Z1eL_SdPtQuNbnykcI0NMZtldCU2Vh02kb9QRIhoRMDKFWcnBaPbKRykzrXvgn3up7JqoD9kQdk7QluaNIW0fKritXiw" charset="UTF-8"></script><?php
if($_REQUEST["subexec"] == "save")
{
   $sql = " select t1.* from orders t1 where t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $_REQUEST["req_id_cc_m1"] = 0;
   $_REQUEST["req_id_cc_m2"] = 0;
   $_REQUEST["req_id_cc_m3"] = 0;

   if($_REQUEST["req_cc_m1"] == $_REQUEST["req_cc_m2"])
      $_REQUEST["req_cc_m2"] = "";

   if($_REQUEST["req_cc_m1"] == $_REQUEST["req_cc_m3"])
     $_REQUEST["req_cc_m3"] = "";

   if($_REQUEST["req_cc_m2"] == $_REQUEST["req_cc_m3"])
      $_REQUEST["req_cc_m3"] = "";

   $sw = 0;
   if( $_REQUEST["req_cc_m1"] != "")
   {
      $sql = " select * from orders where req_number = '{$_REQUEST["req_cc_m1"]}' and {$headdata["req_cust_id"]} = req_cust_id ";
      $cc1 = $CON->select($sql);
      $cc1 = $cc1[0];
      if(count($cc1) == 0)
         $sw = 1;
      else
         $_REQUEST["req_id_cc_m1"] = (int)trim($cc1["id"]);   
   }

   if($_REQUEST["req_cc_m2"] != "")
   {
      $sql = " select * from orders where req_number = '{$_REQUEST["req_cc_m2"]}' and {$headdata["req_cust_id"]} = req_cust_id ";
      $cc2 = $CON->select($sql);
      $cc2 = $cc2[0];
      if(count($cc2) == 0)
         $sw = 1;
      else
         $_REQUEST["req_id_cc_m2"] = (int)trim($cc2["id"]);
   }
   if($_REQUEST["req_cc_m3"] != "")
   {
      $sql = " select * from orders where req_number = '{$_REQUEST["req_cc_m3"]}' and {$headdata["req_cust_id"]} = req_cust_id ";
      $cc3 = $CON->select($sql);
      $cc3 = $cc3[0];
      if(count($cc3) == 0)
         $sw = 1;
      else
         $_REQUEST["req_id_cc_m3"] = (int)trim($cc3["id"]);
   }
   if($sw == 1)
   {
      $savemsg =  $savemsg = "<b class='msg_save_err'>Error, CC NO pertenece al Cliente</b>"; 
      ?>
         <script>alert("Error no pudo actualizar Clasificación, revise CC de asignación Mixta");</script>
      <?php
   }
}
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save" && $sw == 0)
{
   $_REQUEST["prd_plantaid"]  = (int)$_REQUEST["prd_plantaid"];
   $_REQUEST["prd_desc"]      = trim(addslashes($_REQUEST["prd_desc"]));

   
   $sql = " select id
            from prod_header
            where
            prd_reqid   = {$_REQUEST["id"]} and
            prd_status  > 0";
   $prodid = $CON->select($sql);
   $prodid = $prodid[0]["id"];

   $currtme = time();
   if(!(int)$prodid)
   {
      $sql = " insert into prod_header
               (prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid)
               VALUES
               ({$currtme}, {$_SESSION["user_id"]}, 1, '{$_REQUEST["prd_desc"]}', {$_REQUEST["prd_plantaid"]},
                {$_REQUEST["id"]})";
      $res = $CON->no_result($sql);
      if($res)
         $prodid = mysql_insert_id();
   }
   else
   {
      $sql = " update prod_header
               set
               prd_desc       = '{$_REQUEST["prd_desc"]}', 
               prd_plantaid   = {$_REQUEST["prd_plantaid"]},
               prd_upddat     = {$currtme}, 
               prd_updusr     = {$_SESSION["user_id"]}
               where
               id = {$prodid}";
       $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if((int)$prodid)
   {
      $sql = " delete from prod_compat_equipos
               where
               prd_id = {$prodid}";
      $CON->no_result($sql);
      foreach($_REQUEST["equiposact"] AS $equiposactid)
      {
         $sql = " insert into prod_compat_equipos
                  (prd_id, equ_id)
                  VALUES
                  ({$prodid}, {$equiposactid})";
         $CON->no_result($sql);
      }
      
      foreach(array_keys($_REQUEST) AS $reqkey)
      {
         if(strpos($reqkey, "prodplan_existingid_") !== false && strpos($reqkey, "prodplan_existingid_") == 0)
         {
            $idx = substr($reqkey, strrpos($reqkey, "_") +1);

            $existing_id   = (int)$_REQUEST["prodplan_existingid_{$idx}"];
            $prodplan_amt  = getPrice($_REQUEST["prodplan_amt_{$idx}"]);
            $prodplan_date = trim($_REQUEST["prodplan_date_{$idx}"]);

            if($existing_id)
            {
               if($prodplan_amt > 0 && $prodplan_date != "")
               {
                  $prodplan_date = explode(".", $prodplan_date);
                  $prodplan_date = mktime(15, 0, 0, $prodplan_date[1], $prodplan_date[0], $prodplan_date[2]);
                  $sql = " update prod_amtplan
                           set
                           prodplan_amt   = {$prodplan_amt},
                           prodplan_date  = {$prodplan_date}
                           where
                           prodplan_prdid = {$prodid} and
                           id = {$existing_id}";
                  $CON->no_result($sql);
               }
               else
               {
                  $sql = " delete from prod_amtplan
                           where
                           prodplan_prdid = {$prodid} and
                           id = {$existing_id}";
                  $CON->no_result($sql);
               }
            }
            else
            {
               if($prodplan_amt > 0 && $prodplan_date != "")
               {
                  $prodplan_date = explode(".", $prodplan_date);
                  $prodplan_date = mktime(15, 0, 0, $prodplan_date[1], $prodplan_date[0], $prodplan_date[2]);
                  $sql = " insert into prod_amtplan
                           (prodplan_amt, prodplan_date, prodplan_prdid)
                           VALUES
                           ({$prodplan_amt}, {$prodplan_date}, {$prodid})";
                  $CON->no_result($sql);
               }
            }
         }
      }

      if((int)$_REQUEST["activate"])
      {
         $currtme = time();
         $prd_number = createNumberSystem($CON, "PRODOT");
         $sql = " update prod_header set prd_status  = 2,
                                         prd_number  = '{$prd_number}',
                                         prd_fecha_number = {$currtme}
                  where id = {$prodid}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["req_embalaje_medidas_caja"]                = trim(addslashes($_REQUEST["req_embalaje_medidas_caja"]));
   $_REQUEST["req_embalaje_bolsas_por_caja_amt"]         = (int)trim($_REQUEST["req_embalaje_bolsas_por_caja_amt"]);
   $_REQUEST["req_embalaje_cajas_por_pallet_amt"]        = (int)trim($_REQUEST["req_embalaje_cajas_por_pallet_amt"]);
   $_REQUEST["req_embalaje_pallets_completos_amt"]       = (int)trim($_REQUEST["req_embalaje_pallets_completos_amt"]);
   $_REQUEST["req_embalaje_palletcajas_incompletos_amt"] = (int)trim($_REQUEST["req_embalaje_palletcajas_incompletos_amt"]);
   $_REQUEST["req_embalaje_cajas_completas_amt"]         = (int)trim($_REQUEST["req_embalaje_cajas_completas_amt"]);
   $_REQUEST["req_embalaje_caja_final"]                  = (int)trim($_REQUEST["req_embalaje_caja_final"]);
   $_REQUEST["req_operador_tela_width"]                  = (int)trim($_REQUEST["req_operador_tela_width"]);
   $_REQUEST["req_operador_mermaperc"]                   = (float)getPrice(trim($_REQUEST["req_operador_mermaperc"]),2);
   $_REQUEST["req_operador_bastidoramt"]                 = (int)trim($_REQUEST["req_operador_bastidoramt"]);

   $_REQUEST["req_rebo_type"]                            = trim(addslashes($_REQUEST["req_rebo_type"]));
   $_REQUEST["req_rebo_state"]                           = trim(addslashes($_REQUEST["req_rebo_state"]));
   $_REQUEST["req_rebo_rolloscc"]                        = (float)getPrice(trim($_REQUEST["req_rebo_rolloscc"]));
   $_REQUEST["req_rebo_cortescc"]                        = (float)getPrice(trim($_REQUEST["req_rebo_cortescc"]));
   $_REQUEST["req_rebo_rolloscc_opt"]                    = trim(addslashes($_REQUEST["req_rebo_rolloscc_opt"]));
   $_REQUEST["req_rebo_rolloscc_opttype"]                = trim(addslashes($_REQUEST["req_rebo_rolloscc_opttype"]));
   $_REQUEST["fab_mat_gramms"]                           = (int)$_REQUEST["fab_mat_gramms"];

   $_REQUEST["req_id_cc_m1"]                             = (int)trim($_REQUEST["req_id_cc_m1"]);
   $_REQUEST["req_id_cc_m2"]                             = (int)trim($_REQUEST["req_id_cc_m2"]);
   $_REQUEST["req_id_cc_m3"]                             = (int)trim($_REQUEST["req_id_cc_m3"]);

   $_REQUEST["req_infoaddprd_dado_manillas"]             = trim(addslashes($_REQUEST["req_infoaddprd_dado_manillas"]));
   $_REQUEST["req_infoaddprd_cabezal_act"]               = trim(addslashes($_REQUEST["req_infoaddprd_cabezal_act"]));
   $_REQUEST["req_infoaddprd_procedencia"]               = trim(addslashes($_REQUEST["req_infoaddprd_procedencia"]));
   $_REQUEST["req_infoaddprd_reversa_act"]               = trim(addslashes($_REQUEST["req_infoaddprd_reversa_act"]));
   $_REQUEST["req_infoaddprd_cliche_ubicacion"]          = trim(addslashes($_REQUEST["req_infoaddprd_cliche_ubicacion"]));
   $_REQUEST["req_infoaddprd_cliche_codigo"]             = trim(addslashes($_REQUEST["req_infoaddprd_cliche_codigo"]));

   $sql = " update orders
            set
            req_embalaje_medidas_caja                 = '{$_REQUEST["req_embalaje_medidas_caja"]}',
            req_embalaje_bolsas_por_caja_amt          = {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]},
            req_embalaje_cajas_por_pallet_amt         = {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
            req_embalaje_pallets_completos_amt        = {$_REQUEST["req_embalaje_pallets_completos_amt"]},
            req_embalaje_palletcajas_incompletos_amt  = {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
            req_embalaje_cajas_completas_amt          = {$_REQUEST["req_embalaje_cajas_completas_amt"]},
            req_embalaje_caja_final                   = {$_REQUEST["req_embalaje_caja_final"]},
            req_operador_tela_width                   = {$_REQUEST["req_operador_tela_width"]},
            req_operador_mermaperc                    = {$_REQUEST["req_operador_mermaperc"]},
            req_operador_bastidoramt                  = {$_REQUEST["req_operador_bastidoramt"]},
            req_rebo_type                             = '{$_REQUEST["req_rebo_type"]}',
            req_rebo_state                            = '{$_REQUEST["req_rebo_state"]}',
            req_rebo_rolloscc                         = {$_REQUEST["req_rebo_rolloscc"]},
            req_rebo_cortescc                         = {$_REQUEST["req_rebo_cortescc"]},
            req_rebo_rolloscc_opt                     = '{$_REQUEST["req_rebo_rolloscc_opt"]}',
            req_rebo_rolloscc_opttype                 = '{$_REQUEST["req_rebo_rolloscc_opttype"]}',
            req_id_cc_m1                              = {$_REQUEST["req_id_cc_m1"]},
            req_id_cc_m2                              = {$_REQUEST["req_id_cc_m2"]},
            req_id_cc_m3                              = {$_REQUEST["req_id_cc_m3"]},
            req_infoaddprd_dado_manillas              = '{$_REQUEST["req_infoaddprd_dado_manillas"]}',
            req_infoaddprd_cabezal_act                = '{$_REQUEST["req_infoaddprd_cabezal_act"]}',
            req_infoaddprd_procedencia                = '{$_REQUEST["req_infoaddprd_procedencia"]}',
            req_infoaddprd_reversa_act                = '{$_REQUEST["req_infoaddprd_reversa_act"]}',
            req_infoaddprd_cliche_ubicacion           = '{$_REQUEST["req_infoaddprd_cliche_ubicacion"]}',
            req_infoaddprd_cliche_codigo              = '{$_REQUEST["req_infoaddprd_cliche_codigo"]}'
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
   
   $sql = "update orders_items set fab_mat_gramms  = {$_REQUEST["fab_mat_gramms"]} where req_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   if((int)$_REQUEST["req_id_cc_m1"])
   {
      $sql = "update orders set req_id_cc_m1 = {$_REQUEST["id"]}, 
                                req_id_cc_m2 = {$_REQUEST["req_id_cc_m2"]},
                                req_id_cc_m3 = {$_REQUEST["req_id_cc_m3"]}
      where id = {$_REQUEST["req_id_cc_m1"]} ";
      $CON->no_result($sql);
   }
   
   if((int)$_REQUEST["req_id_cc_m2"])
   {
      $sql = "update orders set req_id_cc_m1 = {$_REQUEST["id"]},
                                req_id_cc_m2 = {$_REQUEST["req_id_cc_m1"]},
                                req_id_cc_m3 = {$_REQUEST["req_id_cc_m3"]}
              where id = {$_REQUEST["req_id_cc_m2"]} ";
      $CON->no_result($sql);
   }
   
   if((int)$_REQUEST["req_id_cc_m3"])
   {
      $sql = "update orders set req_id_cc_m1 = {$_REQUEST["id"]},
                                req_id_cc_m2 = {$_REQUEST["req_id_cc_m1"]},
                                req_id_cc_m3 = {$_REQUEST["req_id_cc_m2"]}
              where id = {$_REQUEST["req_id_cc_m3"]} ";
      $CON->no_result($sql);
   }
   
   $sql = " delete from orders_classify_subproducts
            where
            req_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
  
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "subprod_destino_") !== false && strpos($reqkey, "subprod_destino_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $sub_pos          = $poscounter;
         $subprod_ancho_cm = (float)getPrice(trim($_REQUEST["subprod_ancho_{$idx}"]));
         $subprod_amount   = (float)getPrice(trim($_REQUEST["subprod_amount_{$idx}"]));
         $subprod_destino  = trim(addslashes($_REQUEST["subprod_destino_{$idx}"]));

         if($subprod_ancho_cm != 0 && $subprod_amount != 0)
         {
            $sql = " insert into orders_classify_subproducts
                     (req_id, sub_pos, subprod_ancho_cm, subprod_amount, subprod_destino)
                     VALUES
                     ({$_REQUEST["id"]}, {$sub_pos}, {$subprod_ancho_cm}, {$subprod_amount}, '{$subprod_destino}')";
            $CON->no_result($sql);
            $poscounter++;
         }
      }
   }


   $sql = " delete from orders_classify_rebo_values
            where
            req_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "rebov2_input_rolloamt_") !== false && strpos($reqkey, "rebov2_input_rolloamt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_rolloamt_{$idx}"]);
         $rollo_dims = (float)getPrice($_REQUEST["rebov2_input_rollodims_{$idx}"],2);
         if($rollo_amt > 0 && $rollo_dims > 0)
         {
            $sql = " insert into orders_classify_rebo_values
                     (req_id, req_rebo_type, rollo_amt, rollo_dims)
                     VALUES
                     ({$_REQUEST["id"]}, 'rollo', {$rollo_amt}, {$rollo_dims})";
            $CON->no_result($sql);
         }
      }

      if(strpos($reqkey, "rebov2_input_rollos_amt_") !== false && strpos($reqkey, "rebov2_input_rollos_amt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_rollos_amt_{$idx}"]);
         if($rollo_amt > 0)
         {
            $sql = " insert into orders_classify_rebo_values
                     (req_id, req_rebo_type, rollo_amt)
                     VALUES
                     ({$_REQUEST["id"]}, 'rollo_amt', {$rollo_amt})";
            $CON->no_result($sql);
         }
      }

      if(strpos($reqkey, "rebov2_input_metroamt_") !== false && strpos($reqkey, "rebov2_input_metroamt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_metroamt_{$idx}"]);
         $rollo_dims = (float)getPrice($_REQUEST["rebov2_input_metrodims_{$idx}"],2);
         if($rollo_amt > 0 && $rollo_dims > 0)
         {
            $sql = " insert into orders_classify_rebo_values
                     (req_id, req_rebo_type, rollo_amt, rollo_dims)
                     VALUES
                     ({$_REQUEST["id"]}, 'metro', {$rollo_amt}, {$rollo_dims})";
            $CON->no_result($sql);
         }
      }

      if(strpos($reqkey, "rebov2_input_metros_amt_") !== false && strpos($reqkey, "rebov2_input_metros_amt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_metros_amt_{$idx}"]);
         if($rollo_amt > 0)
         {
            $sql = " insert into orders_classify_rebo_values
                     (req_id, req_rebo_type, rollo_amt)
                     VALUES
                     ({$_REQUEST["id"]}, 'metro_amt', {$rollo_amt})";
            $CON->no_result($sql);
         }
      }
   }

   
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t2.cust_email, t3.company_short, t4.shop_name, t1.req_cust_id, t2.cust_notes, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.user_firstname 'seller_firstname', t8.user_lastname 'seller_lastname',
                t9.user_firstname 'cashing_firstname', t9.user_lastname 'cashing_lastname',
                t10.trans_name
         from orders t1
         LEFT OUTER JOIN customer t2         ON t1.req_cust_id          = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN user t8             ON t1.req_userid_seller    = t8.id
         LEFT OUTER JOIN user t9             ON t1.req_userid_cashing   = t9.id
         LEFT OUTER JOIN transports t10      ON t1.req_transportid      = t10.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

if( (int)$headdata["req_id_cc_m1"] )
{
   $sql = " select req_number as req_cc_m1, orders.*  from orders where id = {$headdata["req_id_cc_m1"]} ";
   $datos_cc_m1 = $CON->select($sql);
   $datos_cc_m1 = $datos_cc_m1[0];
}   

if( (int)$headdata["req_id_cc_m2"] )
{
   $sql = " select req_number as req_cc_m2, orders.*  from orders where id = {$headdata["req_id_cc_m2"]} ";
   $datos_cc_m2 = $CON->select($sql);
   $datos_cc_m2 = $datos_cc_m2[0];
}   

if( (int)$headdata["req_id_cc_m3"] )
{
   $sql = " select req_number as req_cc_m3, orders.*  from orders where id = {$headdata["req_id_cc_m3"]} ";
   $datos_cc_m3 = $CON->select($sql);
   $datos_cc_m3 = $datos_cc_m3[0];
}   


//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
         where
         t1.id = {$headdata["req_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre
         from customer_deliveryaddr t1
         LEFT OUTER JOIN country t2 ON t1.delivery_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.delivery_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.delivery_comunaid  = t4.id
         where
         t1.id = {$headdata["req_cust_delivid"]}
         order by t1.id asc";
$deliveryaddr = $CON->select($sql);
$deliveryaddr = $deliveryaddr[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from plantas t1
         where
         t1.planta_status > 0
         order by t1.planta_name";
$plantas = $CON->select($sql);

//----------------------------------------------------------------------------------
$posdata    = getOrderPos($CON, $_REQUEST["id"]);
$thispos    = $posdata[0];
// ---------------------------------------------------------------------------------
$sql = " select distinct t1.item_reg_gsm
              from item t1
                  INNER JOIN item_productcats t2         ON t1.id = t2.item_id
                  INNER JOIN tran_comments_item_vals t3  ON t1.id = t3.item_id
                  INNER JOIN tran_comments t4            ON t3.com_id = t4.id
                  INNER JOIN tran_comments_vals t5       ON t3.val_id = t5.id
            where t1.item_status > 0 and
               t2.cat_id      = {$_CONFIG["TELA_CATID"]} and
               t5.add_name    = '{$thispos["fab_type"]}'
         order by t1.item_reg_gsm asc";
$optsgrams = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_header
         where
         prd_reqid   = {$_REQUEST["id"]} and
         prd_status  > 0";
$proddata = $CON->select($sql);
$proddata = $proddata[0];
//----------------------------------------------------------------------------------
$sql = " select *
         from prod_amtplan
         where
         prodplan_prdid = {$proddata["id"]}
         order by prodplan_date asc";
$prodplans = $CON->select($sql);

$sql = " select *
         from prod_compat_equipos
         where
         prd_id = {$proddata["id"]}";
$prdequs = $CON->select($sql);
foreach($prdequs AS $prdequ)
   $_EQUIPO_ACT[$prdequ["equ_id"]] = 1;

if((int)$proddata["prd_status"] >= 2)
{
   $rdlo = " readonly ";
   $dabl = " disabled ";
}

$sql = " select *
         from orders_classify_subproducts
         where
         req_id = {$_REQUEST["id"]}
         order by sub_pos";
$subproducts = $CON->select($sql);
foreach($subproducts AS $subproduct)
   $_SUBPRODUCTS[$subproduct["sub_pos"]] = $subproduct;

$sql = " select *
         from orders_classify_rebo_values
         where
         req_id = {$_REQUEST["id"]}
         order by id";
$rebovals = $CON->select($sql);
foreach($rebovals AS $reboval)
{
   $_REBOVALS[$reboval["req_rebo_type"]][$reboval["id"]] = $reboval;
}
// print_r($_REBOVALS);
?>

<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>

<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="idx_xform"
onsubmit="return checkform(new Array(this.prd_plantaid))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="activate" value="">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">

<table border="0" cellpadding="0" cellspacing="0" width="1080">
<tr>
   <td class="content_row_clear" valign="top">
      <?php
      if($rdlo != "")
      {  ?>
         <div style="font-family:Arial;font-size:14px;font-weight:bold;background-color:#72C074;color:white;text-shadow:none;padding:10px;width:997px">
            OT GENERADA: <u><?=$proddata["prd_number"]?></u>
         </div>
         <br>
         <?php
      }
      ?>
      <?=Nifty_printH("box1", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
      <colgroup>
         <col width="130">
         <col width="360">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Datos básicos</td>
      </tr>
      <tr>
         <td class="content_rowl">Número</td>
         <td class="content_row"><?=$headdata["req_number"]?></td>
         <td class="content_rowl">Activación producción</td>
         <td class="content_row"><?=$headdata["req_number"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?=$headdata["company_short"]?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?=$headdata["shop_name"]?></td>
      </tr>
      <tbody>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><b><?=$customer["cust_name"]?></b></td>
         <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][13]?></td>
         <td class="content_row"><b><?=$customer["cust_rut"]?></b>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección</td>
         <td class="content_row"><?=$customer["cust_street"]?>&nbsp;</td>
         <td class="content_rowl">Teléfono</td>
         <td class="content_row"><?if($customer["cust_phone"] != "") echo $customer["cust_phone"];?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Región</td>
         <td class="content_row"><?=$customer["name"]?>&nbsp;</td>
         <td class="content_rowl">Whatsapp</td>
         <td class="content_row"><?=$customer["cust_fax"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Comuna</td>
         <td class="content_row"><?=$customer["pro_name"]?> - <?=$customer["nombre"]?>&nbsp;</td>
         <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
         <td class="content_row"><?=$customer["cust_email"]?>&nbsp;</td>
      </tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row"><?=$headdata["seller_firstname"]?> <?=$headdata["seller_lastname"]?>&nbsp;</td>
         <td class="content_rowl">Pie Imprenta</td>
         <td class="content_row"><?=$headdata["req_pie_imprenta"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Transportista</td>
         <td class="content_row"><?=$headdata["trans_name"]?>&nbsp;</td>
         <td class="content_rowl">Despachar a</td>
         <td class="content_row">
            <?php
            if(!(int)$headdata["req_cust_delivid"])
            {  ?>
               DIRECCIÓN PRINCIPAL
               <?php
            }
            else
            {  ?>
               <?=$deliveryaddr["delivery_street"]?>, <?=$deliveryaddr["nombre"]?>, <?=$deliveryaddr["name"]?>
               <?php
            }
            ?>&nbsp;
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Plazo de despacho</td>
         <td class="content_row"><?=$headdata["req_despacho_desc"]?>&nbsp;</td>
         <td class="content_rowl">Embalaje</td>
         <td class="content_row"><?=$headdata["req_embalaje_desc"]?>&nbsp;</td>
      </tr>
      <?php
      if($headdata["req_desc"] != "" || $headdata["req_desc_intern"] != "")
      {  ?>
         <tr>
            <td class="content_rowl" valign="top">Obs. Cliente</td>
            <td class="content_row"><?=$headdata["req_desc"]?></td>
            <td class="content_rowl" valign="top">Obs. Interno</td>
            <td class="content_row"><?=$headdata["req_desc_intern"]?></td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=date('d.m.Y', $headdata["req_crtdat"])?></td>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=displayDate($headdata["req_upddat"])?></td>
      </tr>
      <?php
      if(trim($headdata["cust_notes"]) != "")
      {  ?>
         <tr>
            <td class="content_rowl" valign="top">Comentarios</td>
            <td class="content_row" colspan="3" style="color:navy"><?=$headdata["cust_notes"]?></td>
         </tr>
         <?php
      }
      ?>
      </tbody>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
<tr>
   <td>
      <?php
      //----------------------------------------------------------------------------------
      $sql = " select add_name
               from tran_comments_vals
               where
               id = {$thispos["fab_mat_fabric_color"]}";
      $fabric_color = $CON->select($sql);
      $fabric_color = $fabric_color[0]["add_name"];

      //----------------------------------------------------------------------------------
      $sql = " select add_name
               from tran_comments_vals
               where
               id = {$thispos["fab_mat_manilla_color"]}";
      $manilla_color = $CON->select($sql);
      $manilla_color = $manilla_color[0]["add_name"];

      //----------------------------------------------------------------------------------
      $_ROWSPANLINES = 2;
      for($x = 1; $x <= 5; $x++)
      {
         if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
         {
            $_ROWSPANLINES++;
         }
      }
      ?>
      <br>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col width="360">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Especificaciones del producto</td>
      </tr>
      <tr>
         <td class="content_rowl">Material</td>
         <td class="content_row"><?=$thispos["fab_type"]?></td>
         <td class="content_rowl">Producto</td>
         <td class="content_row"><?=$thispos["item_title"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Medidas</td>
         <td class="content_row">
            <?=(int)$thispos["fab_med_width"]?>x<?=(int)$thispos["fab_med_height"]?> cm
            <?php
            if((int)$thispos["fab_med_fuelle"])
               echo ", Fuelle ".(int)$thispos["fab_med_fuelle"]." cm";
            ?>
         </td>
         <td class="content_rowl">Area impresión</td>
         <td class="content_row"><?=(int)$thispos["fab_print_width"]?> x <?=(int)$thispos["fab_print_height"]?> cm
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo impresión</td>
         <td class="content_row">
            <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Flexografia";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Serigrafia";
            ?>
         </td>
         <td class="content_rowl">Manillas</td>
         <td class="content_row"><?=(int)$thispos["fab_manilla_length"]?> cm</td>
      </tr>
      <tr>
         <td class="content_rowl">Color tela</td>
         <td class="content_row"><?=$fabric_color?>&nbsp;</td>
         <td class="content_rowl" valign="top" rowspan="<?=$_ROWSPANLINES?>">Descripción</td>
         <td class="content_row" valign="top" rowspan="<?=$_ROWSPANLINES?>"><?=$thispos["item_compdesc"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Color manillas</td>
         <td class="content_row"><?=$manilla_color?>&nbsp;</td>
      </tr>
      <?php
      for($x = 1; $x <= 5; $x++)
      {
         if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
         {  ?>      
            <tr>
               <td class="content_rowl" height="1">Color #<?=$x?></td>
               <td class="content_row">
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                  <colgroup>
                     <col width="100">
                     <col>
                  </colgroup>
                  <tr>
                     <td class="content_row_clear" width="100">
                        <?php
                        if((int)$thispos["fab_print_colors_front_{$x}"] && !(int)$thispos["fab_print_colors_back_{$x}"])
                           echo "Frente";
                        elseif(!(int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                           echo "Dorso";
                        elseif((int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                           echo "Frente/Dorso";
                        ?>
                     </td>
                     <td class="content_row_clear"><?=$thispos["fab_print_colordesc_{$x}"]?>&nbsp;</td>
                  </tr>
                  </table>
               </td>
            </tr>
            <?php
         }
      }
      ?>
      <tr>
         <td class="content_rowl">Cantidad</td>
         <td class="content_row"><?=printPrice($thispos["item_amount"],2)?></td>
         <td class="content_rowl">Código de barra</td>
         <td class="content_row"><?=$thispos["item_sellprice_barcodenumber"]?>&nbsp;</td>
      </tr>
      <tr>
         <!-- <td class="content_row"><?=printPrice($thispos["fab_mat_gramms"])?> gr</td> -->
         <td class="content_rowl">Gramaje</td>
         <td class="content_row">
               <select class="text" style="width:50%;" name="fab_mat_gramms">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($optsgrams AS $optsgram)
                  {  ?>
                     <option value="<?=(int)$optsgram["item_reg_gsm"]?>" <?if((int)$optsgram["item_reg_gsm"] == $thispos["fab_mat_gramms"]) echo "selected"?>>
                        <?=(int)$optsgram["item_reg_gsm"]?>
                     </option>
                     <?php
                  }
                  ?>
            </select>
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>

      <tr>
         <td class="content_rowl" valign="top">Imagen diseño</td>
         <td class="content_row" valign="top">
            <?php
            if($thispos["fab_design_imagehash"] != "")
            {  ?>
               <a href="./docs.order/<?=$thispos["fab_design_imagehash"]?>" target="_blank"><img border="0" src="./docs.order/<?=$thispos["fab_design_imagehash"]?>" width="50" height="50"  style="float:left"></a>
               <?php
            }
            ?>
         </td>
         <td class="content_rowl" valign="top">Comentarios diseño</td>
         <td class="content_row" valign="top"><?=$thispos["fab_design_desc"]?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col width="360">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Adjuntos y fechas cliché o pélicula</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Descripción</td>
         <td class="content_tbl_subheader">Valor</td>
         <td class="content_tbl_subheader">Descripción</td>
         <td class="content_tbl_subheader">Valor</td>
      </tr>
      <tr>
         <td class="content_rowl">
            Solicitud
            <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "película";
            ?>
         </td>
         <td class="content_row">
            <?php
            if($headdata["req_cliche_peli_solic_dat"] > 0) 
               echo date('d.m.Y', $headdata["req_cliche_peli_solic_dat"]);
            else
               echo "<b class=msg_save_err>N/A</b>";

            if((int)$headdata["req_cliche_peli_solic_repeat_act"])
               echo " - Trabajo repetido"
            ?>
         </td>
         <td class="content_rowl">
            Recepción
            <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "película";
            ?>
         </td>
         <td class="content_row">
            <?php 
            if($headdata["req_cliche_peli_recep_dat"] > 0) 
               echo date('d.m.Y', $headdata["req_cliche_peli_recep_dat"]);
            else
               echo "<b class=msg_save_err>N/A</b>";
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row"><?=$headdata["req_solic_supp_gesttype"]?></td>
         <?php
         if($thispos["fab_printtype"] == "SERI" && $headdata["req_solic_supp_seudonimo"] != "")
         {  ?>
            <td class="content_rowl">Seudónimo</td>
            <td class="content_row"><?=$headdata["req_solic_supp_seudonimo"]?></td>
            <?php
         }
         else
         {  ?>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
            <?php
         }
         ?>
      </tr>
      <?php
      if($headdata["req_solic_supp_gesttype"] == "Maqueta")
      {  ?>
         <tr>
            <td class="content_rowl">Color pantone</td>
            <td class="content_row"><?=$headdata["req_solic_supp_maqueta_pantone"]?>&nbsp;</td>
            <td class="content_rowl">Área impresión</td>
            <td class="content_row"><?=$headdata["req_solic_supp_maqueta_areaimpresion"]?>&nbsp;</td>
         </tr>
         <tr>
            <td class="content_rowl">Cantidad</td>
            <td class="content_row"><?=(int)$headdata["req_solic_supp_maqueta_cantidad"]?>&nbsp;</td>
            <td class="content_rowl">Medidas</td>
            <td class="content_row"><?=$headdata["req_solic_supp_maqueta_medidas"]?>&nbsp;</td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="content_row" colspan="4" style="padding:0px">
            <table border="0" cellspacing="0" cellpadding="3" width="100%">
            <colgroup>
               <col width="20%">
               <col width="20%">
               <col width="20%">
               <col width="20%">
               <col width="20%">
            </colgroup>
            <tr>
               <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Cliché</td>
               <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Cliché</td>
               <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Película</td>
               <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Película</td>
               <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Montaje</td>
            </tr>
            <tr>
               <?php
               for($x = 0; $x < 5; $x++)
               {  ?>
                  <td align="center" class="content_rowl content_row_os" style="cursor:pointer"
                  <?php
                  if($headdata["req_prod_adjfile_{$x}"] != "")
                  {
                     $dlname  = $headdata["req_prod_adjfile_{$x}"];
                     if($headdata["req_prod_adjname_{$x}"] != "")
                        $dlname = $headdata["req_prod_adjname_{$x}"];

                     $xkey = md5($headdata["req_prod_adjfile_{$x}"]."_5gfffd".$dlname);
                     $pdflink = "/getprodfile.php?xkey={$xkey}&hash={$headdata["req_prod_adjfile_{$x}"]}&name={$dlname}&type=req_prod_adjfile";
                     ?>
                     onclick="window.open('<?=$pdflink?>')"
                     <?php
                     $_HAS_FILES = true;
                  } ?>>
                  <?php
                  if($headdata["req_prod_adjfile_{$x}"] != "")
                  {
                     $ftype = strtoupper(substr($headdata["req_prod_adjfile_{$x}"], strrpos($headdata["req_prod_adjfile_{$x}"], ".")+1));
                     if($ftype == "PNG" || $ftype == "JPG" || $ftype == "JPEG" || $ftype == "BMP" ||$ftype == "GIF")
                     {  ?>
                        <img src="/docs.prod/<?=$headdata["req_prod_adjfile_{$x}"]?>" width="100%">
                        <?php
                     }
                     else
                     {  ?>
                        IMAGEN<br>NO DISPONIBLE<br>(SOLO DESCARGAR)
                        <?php
                     }
                  }
                  else
                  {  ?>
                     <font style="color:red">SIN INFORMACIÓN</font>
                     <?php
                  }
                  ?>
                  </td>
                  <?php
               }
               ?>
            </tr>
            <tr>
            <?php
            for($x = 0; $x < 5; $x++)
            {  ?>
               <td class="content_rowl content_row_os"><?=$headdata["req_prod_adjcomments_{$x}"]?>&nbsp;</td>
               <?php
            }
            ?>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
   </td>
</tr>
<tr style="display:none">
   <td>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="16%">
         <col width="21%">
         <col width="21%">
         <col width="21%">
         <col width="21%">
      </colgroup>
      <tr>
         <td class="content_tbl_header" align="center" style="color:<?=$_COL1_COLOR?>"><b>Checklist Tela</b></td>
         <td class="content_tbl_header" align="center" style="color:<?=$_COL2_COLOR?>"><b>Checklist Diseño en CC</b></td>
         <td class="content_tbl_header" align="center" style="color:<?=$_COL3_COLOR?>"><b>Checklist Tipo de Bolsa</b></td>
         <td class="content_tbl_header" align="center" style="color:<?=$_COL4_COLOR?>"><b>Checklist Tipo de Tela</b></td>
         <td class="content_tbl_header" align="center" style="color:<?=$_COL5_COLOR?>"><b>Checklist <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película";
            ?></b>
         </td>
      </tr>
      <tr>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            Desarrollo<br>
            <input type="text" readonly class="text" style="width:80%;text-align:center" name="req_dsgnchk_desarrollo" value="<?=$headdata["req_dsgnchk_desarrollo"]?>"><br><br>
            Rollo<br>
            <input type="text" readonly class="text" style="width:80%;text-align:center" name="req_dsgnchk_rollo" value="<?=$headdata["req_dsgnchk_rollo"]?>"><br><br>
            Z<br>
            <input type="text"  readonly class="text" style="width:80%;text-align:center" name="req_dsgnchk_z" value="<?=$headdata["req_dsgnchk_z"]?>">
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="checkbox" onclick="return false" name="req_dsgnchk_valid_cc" value="1"  <?if((int)$headdata["req_dsgnchk_valid_cc"] == 1) echo "checked"?>> Validación con CC<br>
            <input type="checkbox" onclick="return false" name="req_dsgnchk_texto_ok" value="1"  <?if((int)$headdata["req_dsgnchk_texto_ok"] == 1) echo "checked"?>> Textos <b>OK</b><br>
            <hr>
            <b>Código</b> de barra<br>
            <input type="radio" onclick="return false" name="req_dsgnchk_barcode_act" value="1" <?if((int)$headdata["req_dsgnchk_barcode_act"] == 1) echo "checked"?>> Si
            <input type="radio" onclick="return false" name="req_dsgnchk_barcode_act" value="0" <?if((int)$headdata["req_dsgnchk_barcode_act"] == 0) echo "checked"?>> No
            <div id="idx_inp_barcode" style="margin-top: 3px;<?if((int)$headdata["req_dsgnchk_barcode_act"] != 1) echo "display: none"?>">
               <input name="req_dsgnchk_barcode_text" name="req_dsgnchk_barcode_text" type="text" class="text" readonly
               style="width:100%" placeholder="Código de barra" value="<?=$headdata["req_dsgnchk_barcode_text"]?>">
               <select class="text" style="width:100%;margin-top:3px" name="req_dsgnchk_barcode_type" disabled>
                  <option value="">No especificado</option>
                  <option value="1D" <?if($headdata["req_dsgnchk_barcode_type"] == "1D") echo "selected"?>>Código 1D</option>
                  <option value="QR" <?if($headdata["req_dsgnchk_barcode_type"] == "QR") echo "selected"?>>Código QR</option>
               </select>
            </div>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Boutique"          <?if($headdata["req_dsgnchk_tipobolsa"] == "Boutique") echo "checked"?>> Bolsa <b>Boutique</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Promocional"       <?if($headdata["req_dsgnchk_tipobolsa"] == "Promocional") echo "checked"?>> Bolsa <b>Promocional</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Basurin"           <?if($headdata["req_dsgnchk_tipobolsa"] == "Basurin") echo "checked"?>> Bolsa <b>Basurin</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Saco"              <?if($headdata["req_dsgnchk_tipobolsa"] == "Saco") echo "checked"?>> Bolsa <b>Saco</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Troquel"           <?if($headdata["req_dsgnchk_tipobolsa"] == "Troquel") echo "checked"?>> Bolsa <b>Troquel</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Sobre"             <?if($headdata["req_dsgnchk_tipobolsa"] == "Sobre") echo "checked"?>> Bolsa <b>Sobre</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Sobre Ecommerce"   <?if($headdata["req_dsgnchk_tipobolsa"] == "Sobre Ecommerce") echo "checked"?>> Bolsa <b>Sobre Ecommerce</b><br>
            <input type="radio" onclick="return false" name="req_dsgnchk_tipobolsa" value="Otros"             <?if(strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") !== false) echo "checked"?>> Otros</b>
            <textarea class="text" style="width:100%;height:40px;<?if(strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") === false) echo "display: none"?>"
            name="req_dsgnchk_tipobolsa_otrosdesc" readonly><?=stripslashes($headdata["req_dsgnchk_tipobolsa_otrosdesc"])?></textarea>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            Pie imprenta<br>
            <input type="radio" onclick="return false" name="req_dsgnchk_pieimprenta_act" value="1" <?if((int)$headdata["req_dsgnchk_pieimprenta_act"] == 1) echo "checked"?>> Si
            <input type="radio" onclick="return false" name="req_dsgnchk_pieimprenta_act" value="0" <?if((int)$headdata["req_dsgnchk_pieimprenta_act"] == 0) echo "checked"?>> No
            <hr>
            <input type="checkbox" onclick="return false" name="req_dsgnchk_prg_recliclaje_pr" value="1" <?if((int)$headdata["req_dsgnchk_prg_recliclaje_pr"] == 1) echo "checked"?>> Programa de reciclaje PR<br>
            <input type="checkbox" onclick="return false" name="req_dsgnchk_tele_vege" value="1"     <?if((int)$headdata["req_dsgnchk_tele_vege"] == 1) echo "checked"?>> Tela <b>Vegetal</b><br>
            <input type="checkbox" onclick="return false" name="req_dsgnchk_tela_tnt_pp" value="1"   <?if((int)$headdata["req_dsgnchk_tela_tnt_pp"] == 1) echo "checked"?>> Tela <b>TNT PP</b><br>
            <input type="checkbox" onclick="return false" name="req_dsgnchk_ok_material" value="1"   <?if((int)$headdata["req_dsgnchk_ok_material"] == 1) echo "checked"?>> OK <b>Materialidad</b><br>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="radio" name="req_dsgnchk_clipeli_tipo" value="Nuevo" onclick="return false"
            <?if($headdata["req_dsgnchk_clipeli_tipo"] == "Nuevo") echo "checked"?>> <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché <b>Nuevo</b>";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película <b>Nuevo</b>";
            ?>
            <br>
            <input type="radio" name="req_dsgnchk_clipeli_tipo" value="Repetido" onclick="return false"
            <?if($headdata["req_dsgnchk_clipeli_tipo"] == "Repetido") echo "checked"?>> <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché <b>Repetido</b>";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película <b>Repetido</b>";
            ?>
            <br><br>


            <input type="checkbox" onclick="return false" name="req_dsgnchk_valid_pantone" value="1" <?if((int)$headdata["req_dsgnchk_valid_pantone"] == 1) echo "checked"?>> Validación <b>Pantone</b><br>

            <input type="checkbox" name="req_dsgnchk_valid_medidas" value="1" onclick="return false"
            <?if((int)$headdata["req_dsgnchk_valid_medidas"] == 1) echo "checked"?>>
            Validación <b>Medidas</b><br>

            <input type="checkbox" onclick="return false" name="req_dsgnchk_rev_tacas" value="1" <?if((int)$headdata["req_dsgnchk_rev_tacas"] == 1) echo "checked"?>> Revisión de <b>Tacas</b><br>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Registrar fechas de entrega y observaciones</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">Cantidad</td>
         <td class="content_tbl_subheader content_row_os">Fecha entrega</td>
         <td class="content_tbl_subheader content_row_os">Planta y observaciones</td>
      </tr>
      <?php
      $rowcount = count($prodplans) +3;
      for($x = 0; $x < $rowcount; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row">
               <input type="hidden" name="prodplan_existingid_<?=$x?>" value="<?=$prodplans[$x]["id"]?>">
               <input type="text" class="text" style="width:100%" onkeyup="$('#idx_finalbtnx').hide(0);"
               name="prodplan_amt_<?=$x?>" id="prodplan_amt_<?=$x?>"
               value="<?if((int)$prodplans[$x]["id"]) echo printPrice($prodplans[$x]["prodplan_amt"])?>">
            </td>
            <td class="content_row">
               <input type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               name="prodplan_date_<?=$x?>" id="prodplan_date_<?=$x?>" style="width:80px"
               value="<?if((int)$prodplans[$x]["id"]) echo date("d.m.Y", $prodplans[$x]["prodplan_date"])?>">
            </td>
            <?php
            if(!$x)
            {  ?>
               <td class="content_row" valign="top" rowspan="<?=$rowcount?>">
                  <select class="text" style="width:100%" name="prd_plantaid"
                  onchange="submitForm(document.idx_xform)">
                     <option value="">Seleccione una planta</option>
                     <?php
                     foreach($plantas AS $planta)
                     {  ?>
                        <option value="<?=$planta["id"]?>" <?if($planta["id"] == $proddata["prd_plantaid"]) echo "selected"?>><?=$planta["planta_name"]?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <div style="height:3px"></div>
                  <textarea <?=$rdlo?> name="prd_desc" class="text" style="width:100%;height:<?=(($rowcount * 28)-28)?>px;min-height:60px"><?=stripslashes($proddata["prd_desc"])?></textarea>
               </td>
               <?php
            }  
            ?>
         </tr>
         <?php
         $_PLAN_AMT += $prodplans[$x]["prodplan_amt"];
      }
      ?>
      </table>
      <?=Nifty_printF(false)?>
      <br>
   </td>
</tr>
<?php
$rebobinadorahide = "display:none";
if((int)$proddata["prd_plantaid"])
{  ?>
   <tr>
      <td>
         <?=Nifty_printH("box2", "1020",0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="260">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="2">Registrar proceso / máquinas de fabricacción compatibles</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Categoria</td>
            <td class="content_tbl_subheader content_row_os">Máquinas</td>
         </tr>
         <?php
         $sqlfield = "";
         if($thispos["fab_printtype"] == "FLEX")
            $sqlfield = "type_ant_flexo_act";
         elseif($thispos["fab_printtype"] == "SERI")
            $sqlfield = "type_ant_seri_act";
            
         $sql = " select *
                  from equipo_type
                  where
                  type_ant_status > 0 and
                  type_ant_prod_dabl = 0 ";
         if($sqlfield != "")
            $sql .= " and {$sqlfield} = 1 ";
         $sql .= " order by type_ant_title";
         $equipotypes = $CON->select($sql);

         $px = 0;
         foreach($equipotypes AS $equipotype)
         {
            $sql = " select *
                     from equipo
                     where
                     equipo_status     > 0 and
                     equipo_type_id    = {$equipotype["id"]} and
                     equipo_planta_id  = {$proddata["prd_plantaid"]} and
                     equipo_prod_dabl  = 0
                     order by equipo_name";
            $equipos = $CON->select($sql);
            if(count($equipos) && $equipos != false)
            {
               $showline = true;

               if($showline)
               {  ?>
                  <tr bgcolor="<?=getRowColor($px)?>">
                     <td class="content_row_os"><?=$equipotype["type_ant_title"]?></td>
                     <td class="content_row_os">
                        <?php
                        foreach($equipos AS $equipo)
                        {
                           $eventstr = "";
                           $eventcls = "";
                           if(strpos(strtoupper($equipotype["type_ant_title"]), "REBOBIN") !== false)
                           {
                              $eventcls = "clsrebob";
                              $eventstr = "showhideReboOpts()";
                              if((int)$_EQUIPO_ACT[$equipo["id"]])
                                 $rebobinadorahide = "";
                           }
                           ?>
                           <nobr>
                              <input type="checkbox" name="equiposact[]" value="<?=$equipo["id"]?>" class="<?=$eventcls?>"
                              onclick="$('#idx_finalbtnx').hide(0);<?=$eventstr?>"
                              <?if((int)$_EQUIPO_ACT[$equipo["id"]]) echo "checked"?>> <?=$equipo["equipo_name"]?>
                           </nobr>
                           <?php
                        }
                        ?>
                     </td>
                  </tr>
                  <?php
                  $px++;
               }
            }
         }
         ?>
         </table>
         <?=Nifty_printF(false)?>
      </td>
   </tr>
   <?php
}
?>
</table>
<br>

<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="190">
   <col width="130">
   <col width="215">
   <col width="130">
   <col width="205">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Medidas / Cantidades embalaje</td>
</tr>
<tr>
   <td class="content_rowl">Medida de caja</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_medidas_caja" style="width:100%"
      value="<?=$headdata["req_embalaje_medidas_caja"]?>">
   </td>
   <td class="content_rowl">Cantidad de bolsas por caja</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_bolsas_por_caja_amt" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_bolsas_por_caja_amt"]) echo (int)$headdata["req_embalaje_bolsas_por_caja_amt"]?>">
   </td>
   <td class="content_rowl">Cantidad de cajas por pallets</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_cajas_por_pallet_amt" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_cajas_por_pallet_amt"]) echo (int)$headdata["req_embalaje_cajas_por_pallet_amt"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Cantidad de pallets completos</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_pallets_completos_amt" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_pallets_completos_amt"]) echo (int)$headdata["req_embalaje_pallets_completos_amt"]?>">
   </td>
   <td class="content_rowl">Numero de cajas en pallet incompleto</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_palletcajas_incompletos_amt" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_palletcajas_incompletos_amt"]) echo (int)$headdata["req_embalaje_palletcajas_incompletos_amt"]?>">
   </td>
   <td class="content_rowl">Cantidad total de cajas completas</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_cajas_completas_amt" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_cajas_completas_amt"]) echo (int)$headdata["req_embalaje_cajas_completas_amt"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Caja final (completa pedido)</td>
   <td class="content_row">
      <input type="text" class="text" name="req_embalaje_caja_final" style="width:100%"
      value="<?if((int)$headdata["req_embalaje_caja_final"]) echo (int)$headdata["req_embalaje_caja_final"]?>">
   </td>
   <td class="content_rowl">Ancho de bobina (cm)</td>
   <td class="content_row">
      <?php
      $sql = " select distinct add_name
               from tran_comments_vals
               where
               add_com_id = 32 and
               add_status > 0
               order by add_name";
      $dbopts = $CON->select($sql);
      foreach($dbopts AS $dbopt)
      {
         $optval = (int)$dbopt["add_name"];
         if($optval > 0)
            $_OPTS[$optval] = 1;
      }
      if((int)$headdata["req_operador_tela_width"] > 0)
      {
         $optval = (int)$headdata["req_operador_tela_width"];
         $_OPTS[$optval] = 1;
      }
      ksort($_OPTS);
      $opts = array_keys($_OPTS);
      ?>
      <select class="text" style="width:100%;" name="req_operador_tela_width" id="req_operador_tela_width">
         <option value="">Seleccione</option>
         <?php
         foreach($opts AS $opt)
         {  ?>
            <option value="<?=$opt?>" <?if($headdata["req_operador_tela_width"] == $opt) echo "selected"?>><?=$opt?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">% de merma</td>
   <td class="content_row">
      <input type="text" class="text" name="req_operador_mermaperc" id="req_operador_mermaperc"
      style="width:100%;"
      value="<?if((float)$headdata["req_operador_mermaperc"]) echo printPrice($headdata["req_operador_mermaperc"],2)?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Cant. imágenes impresas bastidor</td>
   <td class="content_row">
      <select class="text" style="width:100%"
      name="req_operador_bastidoramt" id="req_operador_bastidoramt">
         <option value="">Seleccione</option>
         <?php
         for($zzz = 1; $zzz <= 5; $zzz++)
         {  ?>
            <option value="<?=$zzz?>" <?if($headdata["req_operador_bastidoramt"] == $zzz) echo "selected"?>><?=$zzz?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Cantidad desarrollo</td>
   <td class="content_row"><?=(int)$headdata["req_solic_devprints_cc"]?></td>
   <td class="content_rowl">Cantidad a imprimir</td>
   <td class="content_row">
      <?php
      $cc_print = ceil(($thispos["item_amount"] / 100 * $headdata["req_operador_mermaperc"]) / $headdata["req_solic_devprints_cc"]);
      echo printPrice($cc_print);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="190">
   <col width="130">
   <col width="215">
   <col width="130">
   <col width="205">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Informaciones adicionales de producción</td>
</tr>
<tr>
   <td class="content_rowl">Dado de Manillas</td>
   <td class="content_row">
      <input type="text" class="text" name="req_infoaddprd_dado_manillas" style="width:100%"
      value="<?=$headdata["req_infoaddprd_dado_manillas"]?>">
   </td>
   <td class="content_rowl">Cabezal</td>
   <td class="content_row">
      <input type="radio" name="req_infoaddprd_cabezal_act" value="No" <?if($headdata["req_infoaddprd_cabezal_act"] == "No") echo "checked"?>> No
      <input type="radio" name="req_infoaddprd_cabezal_act" value="Si" <?if($headdata["req_infoaddprd_cabezal_act"] == "Si") echo "checked"?>> Si
   </td>
   <td class="content_rowl">Procedencia</td>
   <td class="content_row">
      <input type="radio" name="req_infoaddprd_procedencia" value="Fábrica" <?if($headdata["req_infoaddprd_procedencia"] == "Fábrica") echo "checked"?>> Fábrica
      <input type="radio" name="req_infoaddprd_procedencia" value="Bodega" <?if($headdata["req_infoaddprd_procedencia"] == "Bodega") echo "checked"?>> Bodega
   </td>
</tr>
<tr>
   <td class="content_rowl">Reversa</td>
   <td class="content_row">
      <input type="radio" name="req_infoaddprd_reversa_act" value="No" <?if($headdata["req_infoaddprd_reversa_act"] == "No") echo "checked"?>> No
      <input type="radio" name="req_infoaddprd_reversa_act" value="Si" <?if($headdata["req_infoaddprd_reversa_act"] == "Si") echo "checked"?>> Si
   </td>
   <td class="content_rowl">Ubicación Cliché</td>
   <td class="content_row">
      <input type="text" class="text" name="req_infoaddprd_cliche_ubicacion" style="width:100%"
      value="<?=$headdata["req_infoaddprd_cliche_ubicacion"]?>">
   </td>
   <td class="content_rowl">Codigo Cliché</td>
   <td class="content_row">
      <input type="text" class="text" name="req_infoaddprd_cliche_codigo" style="width:100%"
      value="<?=$headdata["req_infoaddprd_cliche_codigo"]?>">
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Realizar Entrega Mixtas</td>
   </tr>
   <tr>
      <td class="content_rowl">Numero de CC 1</td>
      <td class="content_row">
         <input type="hidden" name="req_id_cc_m1" value=<?$datos_cc_m1["id"]?>>
         <input type="text" class="text" name="req_cc_m1" 
           value="<?if($datos_cc_m1["req_cc_m1"]) echo $datos_cc_m1["req_cc_m1"]?>">
      </td>
      <td class="content_rowl">Numero de CC 2</td>
      <td class="content_row">
         <input type="hidden" name="req_id_cc_m2" value=<?$datos_cc_m2["id"]?>>
         <input type="text" class="text" name="req_cc_m2" 
         value="<?if($datos_cc_m2["req_cc_m2"]) echo $datos_cc_m2["req_cc_m2"]?>">
      </td>
      <td class="content_rowl">Numero de CC 3</td>
      <td class="content_row">
         <input type="hidden" name="req_id_cc_m3" value=<?$datos_cc_m3["id"]?>>
         <input type="text" class="text" name="req_cc_m3" 
         value="<?if($datos_cc_m3["req_cc_m3"]) echo $datos_cc_m3["req_cc_m3"]?>">
      </td>
   </tr>
</table>
<?=Nifty_printF(false)?>
<br>

<script language="JavaScript">
function showhideReboOpts()
{
   $('#idx_rebob_idx').hide(0);
   var ischecked = false;
   $('.clsrebob').each(function()
   {
      if($(this).attr('checked'))
         ischecked = true;
   });
   if(ischecked)
      $('#idx_rebob_idx').show(0);
}


</script>
<div id="idx_rebob_idx" style="<?=$rebobinadorahide?>">
<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="190">
   <col width="350">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Configuración rebobinadora</td>
</tr>
<tr>
   <td class="content_rowl">Tarea</td>
   <td class="content_row">
      <input type="radio" name="req_rebo_type" value="Corte"
      onchange="showreq_rebo_rolloscc_opt(this.value)"
      <?if($headdata["req_rebo_type"] == "Corte") echo "checked"?>> Corte
      <input type="radio" name="req_rebo_type" value="Rebobinado"
      onchange="showreq_rebo_rolloscc_opt(this.value)"
      <?if($headdata["req_rebo_type"] == "Rebobinado") echo "checked"?>> Rebobinado
      <input type="radio" name="req_rebo_type" value="Empalme"
      onchange="showreq_rebo_rolloscc_opt(this.value)"
      <?if($headdata["req_rebo_type"] == "Empalme") echo "checked"?>> Empalme
   </td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="req_rebo_state">
         <option value="">Seleccione</option>
         <option value="Nuevo" <?if($headdata["req_rebo_state"] == "Nuevo") echo "selected"?>>Nuevo</option>
         <option value="Usado" <?if($headdata["req_rebo_state"] == "Usado") echo "selected"?>>Usado</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <span style="display:none" id="req_rebo_rolloscc_opttype_rollo">
         <input type="radio" name="req_rebo_rolloscc_opttype" id="id_req_rebo_rolloscc_opttype_rollo" value="Por rollo"
         onchange="set_req_rebo_rolloscc_opttype(this.value)"
         <?if($headdata["req_rebo_rolloscc_opttype"] == "Por rollo") echo "checked"?>> Por rollo
      </span>
      <span style="display:none" id="req_rebo_rolloscc_opttype_metro">
         <input type="radio" name="req_rebo_rolloscc_opttype" id="id_req_rebo_rolloscc_opttype_metro" value="Por metro"
         onchange="set_req_rebo_rolloscc_opttype(this.value)"
         <?if($headdata["req_rebo_rolloscc_opttype"] == "Por metro") echo "checked"?>> Por metro
      </span>
   </td>
   <td class="content_rowl">Cantidad de cortes</td>
   <td class="content_row">
      <input type="text" class="text" name="req_rebo_cortescc" id="req_rebo_cortescc" style="width:100%;"
      value="<?if((float)$headdata["req_rebo_cortescc"]) echo printPrice($headdata["req_rebo_cortescc"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Rollos</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="req_rebo_rolloscc_opt">
         <option value="">Seleccione</option>
         <option value="100cm_manillas"       <?if($headdata["req_rebo_rolloscc_opt"] == "100cm_manillas") echo "selected"?>>Rollos de 100cm para manillas</option>
         <option value="100cm_algunos_metros" <?if($headdata["req_rebo_rolloscc_opt"] == "100cm_algunos_metros") echo "selected"?>>Rollos de 100cm (algunos metros) a rollo de 76cm más manillas y restante debe mantener código</option>
         <option value="solo_rebobinar"       <?if($headdata["req_rebo_rolloscc_opt"] == "solo_rebobinar") echo "selected"?>>Rollos solo rebobinar</option>
      </select>
      <input type="text" class="text" name="req_rebo_rolloscc" id="req_rebo_rolloscc" style="width:100%;display:none"
      value="<?if((float)$headdata["req_rebo_rolloscc"]) echo printPrice($headdata["req_rebo_rolloscc"])?>">
   </td>
   <td class="content_rowl"></td>
   <td class="content_row"></td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<div id="iddiv_req_rebo_rolloscc_opttype_rollo"
style="<?if($headdata["req_rebo_type"] == "" || $headdata["req_rebo_rolloscc_opttype"] != "Por rollo") echo "display:none"?>">
<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="400" valign="top">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="170">
         <col>
         <col width="60">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Distribución solicitado</td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader content_rowl">Cantidad</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Medida</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Subtotal</td>
      </tr>
      <?php
      $rebovals = array_values($_REBOVALS["rollo"]);
      $xtotal   = 0;
      for($x = 0; $x <= count($rebovals)+1; $x++)
      {
         $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
         ?>
         <tr>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_rolloamt_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
            </td>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_rollodims_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?>">
            </td>
            <td class="content_row_os">
               <?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?>
            </td>
         </tr>
         <?php
         $xtotal += $subtotal;
      }
      ?>

      </table>
   </td>
   <td valign="top">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="170">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Rollos</td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader content_rowl">Cantidad de rollos</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Materialidad</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Color</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Gramaje</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Ancho</td>
      </tr>
      <?php
      $rebovals = array_values($_REBOVALS["rollo_amt"]);
      $xtotal2   = 0;
      for($x = 0; $x <= count($rebovals)+1; $x++)
      {  ?>
         <tr>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_rollos_amt_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
            </td>
            <td class="content_row_os"><?if($x == 0) echo $thispos["fab_type"]?></td>
            <td class="content_row_os"><?if($x == 0) echo $fabric_color?></td>
            <td class="content_row_os"><?if($x == 0) echo printPrice($thispos["fab_mat_gramms"])." gr"?></td>
            <td class="content_row_os"><?if($x == 0) echo $headdata["req_operador_tela_width"]?></td>
         </tr>
         <?php
         $xtotal2 += $rebovals[$x]["rollo_amt"];
      }
      ?>

      </table>
   </td>
</tr>
<tr>
   <td class="content_row_clear" colspan="2">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="340">
         <col width="60">
         <col>
      </colgroup>
      <tr>
         <td class="content_row_os content_row_totals">Total</td>
         <td class="content_row_os content_row_totals"><?=printPrice($xtotal, 2, true)?></td>
         <td class="content_row_os content_row_totals"><?=printPrice($xtotal2, 0, true)?></td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
</div>
<div id="iddiv_req_rebo_rolloscc_opttype_metro"
style="<?if($headdata["req_rebo_type"] == "" || $headdata["req_rebo_rolloscc_opttype"] != "Por metro") echo "display:none"?>">
<?=Nifty_printH("box2", "1020",0)?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="400" valign="top">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="170">
         <col>
         <col width="60">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Distribución solicitado</td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader content_rowl">Cantidad</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Medida</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Subtotal</td>
      </tr>
      <?php
      $rebovals = array_values($_REBOVALS["metro"]);
      $xtotal   = 0;
      for($x = 0; $x <= count($rebovals)+1; $x++)
      {
         $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
         ?>
         <tr>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_metroamt_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
            </td>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_metrodims_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?>">
            </td>
            <td class="content_row_os">
               <?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?>
            </td>
         </tr>
         <?php
         $xtotal += $subtotal;
      }
      ?>

      </table>
   </td>
   <td valign="top">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col width="170">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Largos</td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader content_rowl">Materialidad</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Color</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Gramaje</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Ancho</td>
         <td class="content_row_os content_tbl_subheader content_rowl">Largo</td>
      </tr>
      <?php
      $rebovals = array_values($_REBOVALS["metro_amt"]);
      $xtotal2   = 0;
      for($x = 0; $x <= count($rebovals)+1; $x++)
      {  ?>
         <tr>
            <td class="content_row_os"><?if($x == 0) echo $thispos["fab_type"]?></td>
            <td class="content_row_os"><?if($x == 0) echo $fabric_color?></td>
            <td class="content_row_os"><?if($x == 0) echo printPrice($thispos["fab_mat_gramms"])." gr"?></td>
            <td class="content_row_os"><?if($x == 0) echo $headdata["req_operador_tela_width"]?></td>
            <td class="content_row_os">
               <input type="text" class="text" name="rebov2_input_metros_amt_<?=$x?>" style="width:100%"
               value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
            </td>
         </tr>
         <?php
         $xtotal2 += $rebovals[$x]["rollo_amt"];
      }
      ?>

      </table>
   </td>
</tr>
<tr>
   <td class="content_row_clear" colspan="2">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="340">
         <col>
         <col width="170">
      </colgroup>
      <tr>
         <td class="content_row_os content_row_totals">Total</td>
         <td class="content_row_os content_row_totals"><?=printPrice($xtotal, 2, true)?></td>
         <td class="content_row_os content_row_totals"><?=printPrice($xtotal2, 0, true)?></td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
</div>

<script language="JavaScript">
   function set_req_rebo_rolloscc_opttype(xval)
   {
      $('#iddiv_req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#iddiv_req_rebo_rolloscc_opttype_metro').hide(0);
      if(xval == 'Por rollo')
      {
         $('#iddiv_req_rebo_rolloscc_opttype_rollo').show(0);
      }
      else if(xval == 'Por metro')
      {
         $('#iddiv_req_rebo_rolloscc_opttype_metro').show(0);
      }
   }
   function showreq_rebo_rolloscc_opt(xval)
   {
      $('#iddiv_req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#iddiv_req_rebo_rolloscc_opttype_metro').hide(0);
      $('#id_req_rebo_rolloscc_opttype_rollo').attr('checked', false);
      $('#id_req_rebo_rolloscc_opttype_metro').attr('checked', false);

      $('#req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#req_rebo_rolloscc_opttype_metro').hide(0);

      if(xval != '')
         $('#req_rebo_rolloscc_opttype_rollo').show(0);

      if(xval == "Corte")
         $('#req_rebo_rolloscc_opttype_metro').show(0);
      if(xval == "Rebobinado")
         $('#req_rebo_rolloscc_opttype_metro').show(0);

      if(xval == "Empalme")
      {
         $('#id_req_rebo_rolloscc_opttype_rollo').attr('checked', true);
         set_req_rebo_rolloscc_opttype('Por rollo');
      }
   };
</script>
<?php
if($headdata["req_rebo_type"] != "")
{  ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         $('#req_rebo_rolloscc_opttype_rollo').show(0);
         <?php
         if($headdata["req_rebo_type"] == "Corte")
         {  ?>
            $('#req_rebo_rolloscc_opttype_metro').show(0);
            <?php
         }
         if($headdata["req_rebo_type"] == "Rebobinado")
         {  ?>
            $('#req_rebo_rolloscc_opttype_metro').show(0);
            <?php

         }
         ?>
      });
   </script>
   <?php
}

if((float)$headdata["req_rebo_cortescc"])
{
   /*
   ?>
   <?=Nifty_printH("box2", "1020",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="190">
      <col width="350">
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Material saliente rebobinadora</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader content_row_os">Sub-Productos</td>
      <td class="content_tbl_subheader content_row_os">Ancho rollo (cm)</td>
      <td class="content_tbl_subheader content_row_os">Cantidad</td>
      <td class="content_tbl_subheader content_row_os">Destino</td>
   </tr>
   <?php
   $hassubs = false;
   for($x = 0; $x < (int)$headdata["req_rebo_cortescc"]; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os">Corte #<?=($x + 1)?></td>
         <td class="content_row_os">
            <input type="text" class="text" name="subprod_ancho_<?=$x?>" style="width:100%;"
            value="<?if((int)$_SUBPRODUCTS[$x]["subprod_ancho_cm"]) echo printPrice($_SUBPRODUCTS[$x]["subprod_ancho_cm"])?>">
         </td>
         <td class="content_row_os">
            <input type="text" class="text" name="subprod_amount_<?=$x?>" style="width:100%;"
            value="<?if((int)$_SUBPRODUCTS[$x]["subprod_amount"]) echo printPrice($_SUBPRODUCTS[$x]["subprod_amount"])?>">
         </td>
         <td class="content_row_os">
            <input type="text" class="text" name="subprod_destino_<?=$x?>" style="width:100%;"
            value="<?=$_SUBPRODUCTS[$x]["subprod_destino"]?>">
         </td>
      </tr>
      <?php
      $_SUB_TOTAL_ANCHOS += $_SUBPRODUCTS[$x]["subprod_ancho_cm"];
      $_SUB_TOTAL_AMTS   += $_SUBPRODUCTS[$x]["subprod_amount"];
      $_SUB_TOTAL_SUM    += ($_SUBPRODUCTS[$x]["subprod_ancho_cm"] * $_SUBPRODUCTS[$x]["subprod_amount"]);

      if((int)$_SUBPRODUCTS[$x]["req_id"])
         $hassubs = true;
   }
   if($hassubs)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_totals content_row_os">Total</td>
         <td class="content_row_totals content_row_os"><?=printPrice($_SUB_TOTAL_ANCHOS)?></td>
         <td class="content_row_totals content_row_os"><?=printPrice($_SUB_TOTAL_AMTS)?></td>
         <td class="content_row_totals content_row_os">
            = <?=printPrice($_SUB_TOTAL_SUM)?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
   */
}
?>
</div>

<?=Nifty_printH("boxopt_b", "1020",0)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if((int)$proddata["id"])
   {
      if($rdlo == "")
      {  ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$proddata["id"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   if($rdlo == "")
   {  ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.idx_xform)", "disk-black");
         ?>
      </td>
      <?php
      if((int)$proddata["id"] &&
         $_PLAN_AMT == $thispos["item_amount"] &&
         is_array($_EQUIPO_ACT) && count($_EQUIPO_ACT) > 0 &&
         (
            (
               $_HAS_FILES ||
               $headdata["req_solic_supp_gesttype"] == "Maqueta"
            )
         ) &&
         (
            ((int)$headdata["req_cliche_peli_solic_dat"] && (int)$headdata["req_cliche_peli_recep_dat"]) ||
            $headdata["req_solic_supp_gesttype"] == "Maqueta" ||
            ($headdata["req_solic_supp_gesttype"] != "Maqueta" && (int)$headdata["req_cliche_peli_solic_repeat_act"])
         )
      )
      {  ?>
         <td align="right" width="130" style="padding-left:5px" id="idx_finalbtnx">
            <?php
            printButton("Activar para planificación", "postnav_save", "javascript: deactivateFormChange()", "document.idx_xform.activate.value='1';submitForm(document.idx_xform);", "tick-circle-frame", 180);
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.idx_xform)", "disk-black");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>