<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

function createEmbalajeProdStock($CON, $prodid)
{
   $sql = " select *
            from prod_worker_ot
            where
            id = {$prodid}";
   $prod_worker_ot = $CON->select($sql);
   $prod_worker_ot = $prod_worker_ot[0];

   $sql = " select *
            from prod_worker_init
            where
            id = {$prod_worker_ot["wok_init_id"]}";
   $prod_worker_init = $CON->select($sql);
   $prod_worker_init = $prod_worker_init[0];

   $sql = " select t1.*, t2.type_createstock_act
            from equipo t1
            INNER JOIN equipo_type t2 ON t1.equipo_type_id = t2.id
            where
            t1.id = {$prod_worker_init["win_equipoid"]}";
   $equipo = $CON->select($sql);
   $equipo = $equipo[0];

   $sql = " select *
            from prod_agenda
            where
            id = {$prod_worker_ot["wok_ag_id"]}";
   $prod_agenda = $CON->select($sql);
   $prod_agenda = $prod_agenda[0];

   $sql = " select t1.*, t4.cust_name, t2.*
            from orders t1
            INNER JOIN orders_items t2 ON t1.id = t2.req_id
            INNER JOIN customer t4     ON t1.req_cust_id = t4.id
            where
            t1.id = {$prod_agenda["ag_reqid"]}";
   $orderinfo = $CON->select($sql);
   $orderinfo = $orderinfo[0];

   $sql = " select *
            from prod_worker_ot_events
            where
            evt_prod_worker_otid = {$prod_worker_ot["id"]} and
            evt_type             = 'prod' and
            evt_amount           != 0.00
            order by id asc";
   $ot_events = $CON->select($sql);



   $_TOTAL_AMOUNT = 0;
   foreach($ot_events AS $ot_event)
      $_TOTAL_AMOUNT += $ot_event["evt_amount"];

   if((int)$equipo["type_createstock_act"] && $_TOTAL_AMOUNT != 0.00)
   {
      $_ADJTYPEID = 20;
      $stkis_negative = 0;
      if($_TOTAL_AMOUNT < 0.00)
      {
         $_ADJTYPEID = 19;
         $stkis_negative = 1;
      }

      $_DEST_COMPID = 0;
      $_DEST_SHOPID = 0;
      $_DEST_STHID  = 0;

      //CHILE
      if((int)$equipo["equipo_planta_id"] == 1)
      {
         $sql      = "select * from prod_worker_embalajes where evt_reqid = {$prod_agenda["ag_reqid"]} and evt_prod_worker_otid = {$prod_worker_ot["id"]}  ";
         $embalaje = $CON->select($sql);
         $embalaje = $embalaje[0];

         $_DEST_COMPID = $orderinfo["req_company_id"]; /* 20010; */
         $_DEST_SHOPID = $orderinfo["req_shop_id"];    /* 30010; */
         if( (int)$embalaje["req_id_bodega"] )
            $_DEST_STHID  = $embalaje["req_id_bodega"]; 
         else
            $_DEST_STHID  = 8; 
      }

      if($_DEST_COMPID != 0 && $_DEST_SHOPID != 0 && $_DEST_STHID != 0)
      {
         $stk_num = createTransactionNumber($CON, $_DEST_COMPID, "stockchange");
         $stk_bookdate = time();
         $currtme = time();
         $sth_order_id =

         $sql = " insert into stockchanges
                  (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                   stk_negative, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid,
                   sth_order_num, sth_order_id)
                  VALUES
                  ('{$stk_num}', 'Registrado por sistema de producciï¿½n',
                    {$_ADJTYPEID}, {$_DEST_COMPID}, {$_DEST_SHOPID}, {$stk_bookdate},
                    {$stkis_negative}, {$currtme}, 0, 0,
                    {$_DEST_STHID}, '{$orderinfo["req_number"]}', {$prod_agenda["ag_reqid"]})";
         $res = $CON->no_result($sql);
         if($res)
         {
            $stk_id = mysql_insert_id();
            $sql = " insert into stockchanges_items
                     (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                      item_sellprice_brutto)
                     VALUES
                     ({$stk_id}, {$orderinfo["item_id"]}, 0, {$_TOTAL_AMOUNT}, 'item',
                      {$_DEST_STHID}, 0.00, 0.00, 0.00, 0.00, 0, 0.00)";
            $CON->no_result($sql);

            bookStockChange($CON, $stk_id);
            xls_createStockchanges($CON, $stk_id);
         }
      }
   }
}

function generateDesignFabricationAvisoMail($CON, $headdata, $posdata)
{
   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id
            where
            t1.user_status > 0 and
            t2.group_id    = 31 and
            t1.user_mail   like '%@%'";
   $users = $CON->select($sql);

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {
         $title   = "Fabricaciï¿½n / Informaciï¿½n tï¿½cnica: {$headdata["req_number"]}";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     la informaciï¿½n tï¿½cnica fue completada por el diseï¿½ador.";
         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "");
      }      
   }
}

//----------------------------------------------------------------------------------
function generateDesignFabricationMail($CON, $headdata, $posdata)
{

   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id
            where
            t1.user_status > 0 and
            t2.group_id    = 20 and
            t1.user_mail   like '%@%'";
   $users = $CON->select($sql);

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {
         $uhash   = md5($headdata["id"].$user["id"]);
         $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}produpload.php?id={$headdata["id"]}&uid={$user["id"]}&k={$uhash}";
         $title   = "Solicitud preparación para fabricación: {$headdata["req_number"]}";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     hay una nueva solictud de fabricación para adjuntar detalles técnicos.";
         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "");
      }      
   }
}

//----------------------------------------------------------------------------------
function getProdStats($CON, $prdid, $agid, $otid, $plantaid, $reqid, $equipotypeid)
{
   $events = getProdEvents($CON, $prdid, $agid, $otid, $plantaid, $reqid, $equipotypeid);
   for($x = 0; $x < count($events) && $events != false; $x++)
   {
      if(!(int)$events[$x]["evt_enddat"])
         $events[$x]["evt_enddat"] = time();
   }

   $_RET["_PROD_AMOUNT"]            = 0;
   $_RET["_PROD_TIME_MINS"]         = 0;
   $_RET["_PROD_TIME_STR"]          = "";
   $_RET["_APERTURA_AMOUNT"]        = 0;
   $_RET["_APERTURA_TIME_MINS"]     = 0;
   $_RET["_APERTURA_TIME_STR"]      = "";
   $_RET["_MANTENCION_AMOUNT"]      = 0;
   $_RET["_MANTENCION_TIME_MINS"]   = 0;
   $_RET["_MANTENCION_TIME_STR"]    = "";

   for($x = 0; $x < count($events) && $events != false; $x++)
   {
      $row = $events[$x];

      $time_diff  = $events[$x]["evt_enddat"] - $events[$x]["evt_crtdat"];
      $time_diffx = $time_diff / 60;
      $hours_diff = (int)($time_diffx / 60);
      $min_diff   = (int)($time_diffx - ($hours_diff * 60));

      if($row["evt_type"] == "prod")
      {
         $_RET["_PROD_AMOUNT"]      += $row["evt_amount"];
         $_RET["_PROD_TIME_MINS"]   += $time_diffx;
      }
      elseif($row["evt_type"] == "apertura")
      {
         $_RET["_APERTURA_AMOUNT"]++;
         $_RET["_APERTURA_TIME_MINS"] += $time_diffx;
      }
      elseif($row["evt_type"] == "mantencion")
      {
         $_RET["_MANTENCION_AMOUNT"]++;
         $_RET["_MANTENCION_TIME_MINS"] += $time_diffx;
      }
      elseif($row["evt_type"] == "pause")
      {
         $_RET["_PAUSE_AMOUNT"]++;
         $_RET["_PAUSE_TIME_MINS"] += $time_diffx;
      }
   }

   $time_diffx = $_RET["_PROD_TIME_MINS"];
   $hours_diff = (int)($time_diffx / 60);
   $min_diff   = (int)($time_diffx - ($hours_diff * 60));
   $_RET["_PROD_TIME_STR"] = "{$hours_diff}h {$min_diff}m";

   $time_diffx = $_RET["_APERTURA_TIME_MINS"];
   $hours_diff = (int)($time_diffx / 60);
   $min_diff   = (int)($time_diffx - ($hours_diff * 60));
   $_RET["_APERTURA_TIME_STR"] = "{$hours_diff}h {$min_diff}m";

   $time_diffx = $_RET["_MANTENCION_TIME_MINS"];
   $hours_diff = (int)($time_diffx / 60);
   $min_diff   = (int)($time_diffx - ($hours_diff * 60));
   $_RET["_MANTENCION_TIME_STR"] = "{$hours_diff}h {$min_diff}m";

   $time_diffx = $_RET["_PAUSE_TIME_MINS"];
   $hours_diff = (int)($time_diffx / 60);
   $min_diff   = (int)($time_diffx - ($hours_diff * 60));
   $_RET["_PAUSE_TIME_STR"] = "{$hours_diff}h {$min_diff}m";

   $time_diffx = $_RET["_APERTURA_TIME_MINS"] + $_RET["_MANTENCION_TIME_MINS"] + $_RET["_PAUSE_TIME_MINS"];
   $hours_diff = (int)($time_diffx / 60);
   $min_diff   = (int)($time_diffx - ($hours_diff * 60));
   $_RET["_NOPROD_TIME_STR"] = "{$hours_diff}h {$min_diff}m";

   return $_RET;
}

//----------------------------------------------------------------------------------
function getProdEvents($CON, $prdid, $agid, $otid, $plantaid, $reqid, $equipotypeid, $equipoid = 0)
{
   $sql = " select t4.*, t5.mant_title, t5x.pause_name, t5x.pause_code, t5.mant_code,
                   t6.ubim_title, t2.ag_equipo_id, t8.wrk_lastname, t2.ag_amount,
                   t9.equipo_prod_isprinter_seri, t9.equipo_prod_isprinter_flexo
            from prod_header t1
            INNER JOIN prod_agenda t2              ON t1.id = t2.ag_prdid
            INNER JOIN prod_worker_ot t3           ON t3.wok_ag_id = t2.id
            INNER JOIN prod_worker_ot_events t4    ON t4.evt_prod_worker_otid = t3.id
            LEFT OUTER JOIN equipo_manttype t5     ON t4.evt_equipo_mantid = t5.id
            LEFT OUTER JOIN prod_pause_types t5x   ON t4.evt_pause_id = t5x.id
            LEFT OUTER JOIN equipos_ubimants t6    ON t4.evt_ubim_id = t6.id
            LEFT OUTER JOIN prod_worker_init t7    ON t3.wok_init_id = t7.id
            LEFT OUTER JOIN workers t8             ON t7.win_wrkid = t8.id
            LEFT OUTER JOIN equipo t9              ON t2.ag_equipo_id = t9.id
            where
            t1.prd_status  = 2 and
            t2.ag_status   > 0 and
            t3.wok_status  > 0 and
            t4.evt_status  > 0 ";
   if((int)$prdid)
      $sql .= " and t1.id = {$prdid} ";
   if((int)$agid)
      $sql .= " and t2.id = {$agid} ";
   if((int)$otid)
      $sql .= " and t3.id = {$otid} ";
   if((int)$plantaid)
      $sql .= " and t1.prd_plantaid = {$plantaid} ";
   if((int)$reqid)
      $sql .= " and t1.prd_reqid = {$reqid} ";
   if((int)$equipotypeid)
      $sql .= " and t2.ag_equipotype_id = {$equipotypeid} ";
   if((int)$equipoid)
      $sql .= " and t2.ag_equipo_id = {$equipoid} ";
   $sql .= " order by t4.evt_crtdat desc";
   // echo $sql."<hr>";
   $events = $CON->select($sql);
   return $events;
}

//----------------------------------------------------------------------------------
function getProdOpenWorkerOT($CON, $wrkid, $plantaid)
{
   $hasopeninit = getProdOpenWorkerInit($CON, $wrkid, $plantaid);
   if((int)$hasopeninit["id"])
   {
      $sql = " select t1.*, t4.prd_number, t4.prd_reqid
               from prod_worker_ot t1
               INNER JOIN prod_worker_init t2   ON t1.wok_init_id = t2.id
               INNER JOIN prod_agenda t3        ON t1.wok_ag_id = t3.id
               INNER JOIN prod_header t4        ON t3.ag_prdid = t4.id
               where
               t1.wok_init_id = {$hasopeninit["id"]} and
               t1.wok_status  = 1";
      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];

      return $hasopenot;
   }
   return false;
}

//----------------------------------------------------------------------------------
function getProdOpenWorkerInit($CON, $wrkid, $plantaid)
{
   $sql = " select t1.*, t7.equipo_name, t8.type_ant_title, t7.equipo_type_id, t7.equipo_prod_serimulticolors_act,
                   t8.type_createstock_act, t8.type_ant_inpmedidas_act, t7.equipo_prod_isprinter_seri,
                   t7.equipo_prod_isprinter_flexo, t7.equipo_prod_divisor_perc, t7.equipo_prod_printer_metrotype
            from prod_worker_init t1
            LEFT OUTER JOIN equipo t7       ON t1.win_equipoid = t7.id
            LEFT OUTER JOIN equipo_type t8  ON t7.equipo_type_id = t8.id
            where
            t1.win_wrkid      = {$wrkid} and
            t1.win_status     = 1 and
            t1.win_plantaid   = {$plantaid}";
   $hasopeninit = $CON->select($sql);
   $hasopeninit = $hasopeninit[0];

   return $hasopeninit;
}

//----------------------------------------------------------------------------------
function getWorkerAssistsState($row, $_TOTALARR)
{
   if(!(int)$row["confirm_act"] && !(int)$row["confirm_falta_act"])
   {
      $_TOTALARR[0]++;
      $_CHECKEDARR[0] = "checked";
   }
   elseif((int)$row["confirm_act"] && !(int)$row["confirm_atraso_act"] &&  !(int)$row["confirm_falta_act"])
   {
      $_TOTALARR[1]++;
      $_CHECKEDARR[1] = "checked";
   }
   elseif((int)$row["confirm_act"] && (int)$row["confirm_falta_act"])
   {
      $_TOTALARR[2]++;
      $_CHECKEDARR[2] = "checked";
   }
   elseif((int)$row["confirm_act"] && (int)$row["confirm_atraso_act"])
   {
      $_TOTALARR[3]++;
      $_CHECKEDARR[3] = "checked";
   }
   $_RET["_TOTALARR"]   = $_TOTALARR;
   $_RET["_CHECKEDARR"] = $_CHECKEDARR;

   return $_RET;
}

//----------------------------------------------------------------------------------
function getAllTtypes($CON, $ordlibre = false)
{
   $sql = " select *
            from turnos_types
            where
            type_status > 0 ";
   if($ordlibre)
      $sql .=" order by type_libre_act desc, type_name";
   else
      $sql .=" order by type_name";
   $ttypes = $CON->select($sql);
   foreach($ttypes AS $ttype)
      $_TTYPES[$ttype["id"]] = $ttype;

   return $_TTYPES;
}

//----------------------------------------------------------------------------------
function getAllTurnos($CON, $sql_datefrom, $sql_dateto)
{
   $sql = " select *
            from turnos_config
            where
            cfg_datestamp between {$sql_datefrom} and {$sql_dateto}
            order by cfg_datestamp asc";
   $allturnocfg = $CON->select($sql);
   foreach($allturnocfg AS $cfg)
      $_ALLTURNOS[$cfg["cfg_datestr"]][$cfg["cfg_turno_id"]] = $cfg["cfg_turno_type_id"];
   return $_ALLTURNOS;
}

//----------------------------------------------------------------------------------
function getJornadas($CON)
{
   $sql = " select t1.*, (IFNULL(MAX(t2.turn_order),0) +1) 'counter'
            from turnos_jornadas t1
            LEFT OUTER JOIN turnos t2 ON ( t1.id = t2.turn_jornada_id and t2.turn_status > 0 )
            where
            t1.jorn_status > 0
            group by t1.id
            order by t1.jorn_order, t1.jorn_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getWorkerStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 0: return "<b style='color:#FF6600'>Sin asignar</b>"; break;
         case 1: return "<b style='color:#51994C'>Activado</b>"; break;
         case 2: return "<b style='color:#FF374D'>Terminado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 0: return "Sin asignar"; break;
         case 1: return "Activado"; break;
         case 2: return "Terminado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getCargos($CON)
{
   $sql = " select t1.id, t1.type_name, t1.type_crtdat
            from workers_types t1
            where
            t1.type_status > 0
            order by t1.type_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getPlantas($CON, $plantaid = 0)
{
   $sql = " select t1.*
            from plantas t1
            where
            t1.planta_status > 0 ";
   if((int)$plantaid)
      $sql .= " and id = {$plantaid} ";
   $sql .= " order by t1.planta_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function generateSupplierOrderAprobResponseMail($CON, $sordid, $aprobstate)
{
   $sql = " select t1.*, t5.user_firstname, t5.user_lastname, t5.user_mail
            from supplier_order t1
            LEFT OUTER JOIN user t5          ON t1.sord_updusr = t5.id
            where
            t1.id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if($headdata["user_mail"] != "")
   {
      if($aprobstate == 0)
         $aprobstate = "rechazada";
      else
         $aprobstate = "aprobada";
         
      $title   = "Solicitud OC {$aprobstate}: {$headdata["sord_number"]}";
      $body    = "Estimado(a) {$headdata["user_firstname"]} {$headdata["user_lastname"]},<br><br>
                  la solicitud de la orden de compra {$headdata["sord_number"]} fue {$aprobstate}.";
                     
      sendExternalMail($title, $body, $headdata["user_mail"], $headdata["user_firstname"]." ".$headdata["user_lastname"],
                       $_SESSION["_CONF"]["conf_mail_accountname"], $_SESSION["_CONF"]["conf_mail_sendername"]);
   }
}

//----------------------------------------------------------------------------------
function generateSupplierOrderAprobMail($CON, $sordid)
{
   //----------------------------------------------------------------------------------
   $_AMTRANGES = Array();

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
            t1.id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " select *
            from supplier_order
            where
            id = {$sordid}";
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

   $_FOUNDRANGE = Array();
   if(is_array($_AMTRANGES) && count($_AMTRANGES) > 0 && (int)$_SESSION["user_type"] != 1)
   {
      $sql = " select *
               from supplier_order
               where
               id = {$sordid}";
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
            $_FOUNDRANGE = $_AMTRANGE;
      }
   }


   if($_FOUNDRANGE["USERS"][0]["user_firstname"] != "")
   {
      foreach($_FOUNDRANGE["USERS"] AS $user)
      {
         $uhash   = md5($headdata["id"].$user["id"]);
         $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}aprobsupporder.php?id={$headdata["id"]}&uid={$user["id"]}&k={$uhash}";
         
         $title   = "Solicitud aprobación de OC: {$headdata["sord_number"]}";

         //----------------------------------------------------------------------------------
         $_MAILROWCSS1 = "color:#333333;background-color:#EEEEEE;border-top:1px solid #CCCCCC;border-left:1px solid #CCCCCC;font-family:Arial;font-size:13px";
         $_MAILROWCSS2 = "color:#666666;border-top:1px solid #CCCCCC;border-left:1px solid #CCCCCC;font-family:Arial;font-size:13px";

         $MAILBODY  = '<html><body style="font-family:Arial;font-size:13px;color:#333333"><center>';
         $MAILBODY .= "<table width=650 cellpadding=0 cellspacing=0 style='background-color:white'>";
         $MAILBODY .= "<tr><td align='center'><br><img src='{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/layout/logov2.png' width=250><br><br></td></tr>";
         $MAILBODY .= "</table><br><br>";
         $MAILBODY .= '<b>Estimado(a) '.$user["user_firstname"].' '.$user["user_lastname"].',<br><br></b>';

         $MAILBODY .= 'se solicita aprobación de orden de compra <b>'.$headdata["sord_number"].'</b>.<br>';
         $MAILBODY .= 'Favor pinchar el siguiente enlace para aprobar o rechazar:<br><br>';
         $MAILBODY .= '<a href=\''.$urllink.'\' target=\'_blank\'>'.$urllink.'</a><br><br>';
         $MAILBODY .= "<table border=0 cellpadding=3 cellspacing=0 width=650 style='border-bottom:1px solid #CCCCCC;border-right:1px solid #CCCCCC'>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};width:130px;text-align:left;' width=130><b>Número OC</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'><b>".$headdata["sord_number"]."</td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:left;'><b>Total neto</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'><b>\$ ".printPrice($headdata["sord_total_netto"], 4)."</b></td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:left;'><b>Total bruto</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'><b>\$ ".printPrice($headdata["sord_total_brutto"], 4)."</b></td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:left;'><b>Proveedor</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'>{$headdata["supp_company"]}</td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:left;'><b>Usuario</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'>".$headdata["upd_firstname"]." ".$headdata["upd_lastname"]."</td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:left;'><b>Fecha</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:left;'>".date("d.m.Y H:i")."</td>";
         $MAILBODY .= "</tr>";
         $MAILBODY .= "</table><br><br>";

         $posdata = getSupplierOrderPos($CON, $headdata["id"]);

         $MAILBODY .= "<table border=0 cellpadding=3 cellspacing=0 width=90% style='border-bottom:1px solid #CCCCCC;border-right:1px solid #CCCCCC'>";
         $MAILBODY .= "<colgroup><col width=100><col><col width=100><col width=100><col width=100></colgroup>";
         $MAILBODY .= "<tr>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1}' width='100'><b>Código</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1}'><b>Producto</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1};text-align:center !important' width='100'><b>Cantidad</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1}' width='100' align=right><b>\$ Neto</b></td>";
         $MAILBODY .= "<td style='{$_MAILROWCSS1}' width='100' align=right><b>\$ Subtotal</b></td>";
         $MAILBODY .= "</tr>";

         foreach($posdata AS $posdatarow)
         {
            $MAILBODY .= "<td style='{$_MAILROWCSS2}'>{$posdatarow["item_number_prod"]}</td>";
            $MAILBODY .= "<td style='{$_MAILROWCSS2}'>{$posdatarow["item_title"]}</td>";
            $MAILBODY .= "<td style='{$_MAILROWCSS2};text-align:center !important'>".printPrice($posdatarow["item_amount"],4)."</td>";
            $MAILBODY .= "<td style='{$_MAILROWCSS2}' align=right>".printPrice($posdatarow["item_costprice_netto"],4)."</td>";
            $MAILBODY .= "<td style='{$_MAILROWCSS2}' align=right>".printPrice($posdatarow["item_costprice_netto_dsc"],4)."</td>";
            $MAILBODY .= "</tr>";
         }

         $MAILBODY .= "</table><br><br>";
         $MAILBODY .= "<br><br>";
         $MAILBODY .= "</center></body></html>";

         doc_createSupplierOrder($CON, $headdata["id"]);

         $sql = " select sord_hash
                  from supplier_order
                  where
                  id = {$headdata["id"]}";
         $sordhash = $CON->select($sql);
         $sordhash = $sordhash[0]["sord_hash"];


         $attachfiles = Array();

         $sql = " select t1.*, t2.docto_title,
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                  from tran_docs t1
                  LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                  LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                  where
                  t1.doc_tran_id    = {$headdata["id"]} and
                  t1.doc_tran_type  = 'supplier_order'
                  order by t1.id";
         $docs = $CON->select($sql);

         if($sordhash != "")
         {
            $filedir    = "./docs.supplierorder/";
            $filename   = "{$headdata["id"]}.{$sordhash}";
            $fileext    = ".pdf";
            $pdffile    = "{$filedir}{$filename}{$fileext}";

            $sql = " update supplier_order
                     set
                     sord_hash   = ''
                     where
                     id          = {$headdata["id"]}";
            $CON->no_result($sql);

            unset($temp);
            $temp["NAME"]  = "{$headdata["sord_number"]}{$fileext}";
            $temp["FILE"]  = $pdffile;
            $attachfiles[] = $temp;

            foreach($docs AS $doc)
            {
               unset($temp);
               $temp["NAME"]  = "{$doc["doc_name"]}";
               $temp["FILE"]  = "./docs.tran/supplier_order/{$doc["doc_file"]}";
               $attachfiles[] = $temp;

            }
            sendExternalMail($title, $MAILBODY, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachfiles);
            @unlink($pdffile);
         }
         else
         {
            foreach($docs AS $doc)
            {
               unset($temp);
               $temp["NAME"]  = "{$doc["doc_name"]}";
               $temp["FILE"]  = "./docs.tran/supplier_order/{$doc["doc_file"]}";
               $attachfiles[] = $temp;

            }

            sendExternalMail($title, $MAILBODY, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachfiles);
         }
      }
   }
}

//----------------------------------------------------------------------------------
function generateDesignMailResponse($CON, $headdata, $posdata)
{
   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            where
            t1.id = {$headdata["req_updusr"]}";
   $users = $CON->select($sql);

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {
         $title   = "Solicitud diseño: {$headdata["req_number"]} / completado";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     la solicitud {$headdata["req_number"]} fue completado por el area de diseï¿½o.";

         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"],
                          $_SESSION["_CONF"]["conf_mail_accountname"], $_SESSION["_CONF"]["conf_mail_sendername"]);
                       
      }      
   }
}

//----------------------------------------------------------------------------------
function generateDesignMail($CON, $headdata, $posdata)
{
   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id
            where
            t1.user_status > 0 and
            t2.group_id    = 20 and
            t1.user_mail   like '%@%'";
   $users = $CON->select($sql);

   $sql = " select *
            from tran_docs
            where
            doc_tran_id = {$headdata["id"]} and
            doc_tran_type = 'orders'";
   $anexos = $CON->select($sql);

   $attachments = Array();
   foreach($anexos AS $anexo)
   {
      $attachment["FILE"] = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/orders/{$anexo["doc_file"]}";
      $attachment["NAME"] = $anexo["doc_name"];
      $attachments[] = $attachment;
   }

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {
         $uhash   = md5($headdata["id"].$user["id"]);
         $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}designupload.php?id={$headdata["id"]}&uid={$user["id"]}&k={$uhash}";
         $title   = "Solicitud diseño: {$headdata["req_number"]}";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     favor utilizar el siguiente link para visualizar los detalles de la solictud y subir el diseño:<br><br>
                     <a href='{$urllink}' target='_blank'>{$urllink}</a>";

         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachments);
      }      
   }
}

//----------------------------------------------------------------------------------
function createInternalAltaNumber($CON)
{
   global $_LANG;
   
   $num_month = (int)date('m');
   $num_year  = (int)date('Y');

   $sql = " select *
            from number_system_month_simi
            where
            num_year = {$num_year}";
   $currdata = $CON->select($sql);
   $currdata = $currdata[0];

   if((int)$currdata["id"])
   {
      $counter = $currdata["num_counter"] +1;
      $sql = " update number_system_month_simi
               set
               num_counter = {$counter}
               where
               id = {$currdata["id"]}";
      $CON->no_result($sql);
   }
   else
   {
      $counter = 1;
      $sql = " insert into number_system_month_simi
               (num_year, num_counter)
               VALUES
               ({$num_year}, {$counter})";
      $CON->no_result($sql);
   }

   $mname      = strtoupper(substr($_LANG["MON"][$num_month], 0, 3));
   $retnumber  = "{$num_year}-".sprintf("%05s", $counter)."-".$_SESSION["user_code"];
   
   return $retnumber;
}

//----------------------------------------------------------------------------------
function createInternalAltaNumberOrder($CON, $suffix = "1")
{
   global $_LANG;
   
   $num_month = (int)date('m');
   $num_year  = (int)date('Y');

   $sql = " select *
            from number_system_month_simi_orders
            where
            num_year = {$num_year}";
   $currdata = $CON->select($sql);
   $currdata = $currdata[0];

   if((int)$currdata["id"])
   {
      $counter = $currdata["num_counter"] +1;
      $sql = " update number_system_month_simi_orders
               set
               num_counter = {$counter}
               where
               id = {$currdata["id"]}";
      $CON->no_result($sql);
   }
   else
   {
      $counter = 1;
      $sql = " insert into number_system_month_simi_orders
               (num_year, num_counter)
               VALUES
               ({$num_year}, {$counter})";
      $CON->no_result($sql);
   }

   $num_year   = substr($num_year, -2);
   $retnumber  = "{$num_year}-".sprintf("%05s", $counter)."-".$suffix;
   
   return $retnumber;
}

//----------------------------------------------------------------------------------
function getSupplierContenedorOCStr($CON, $contid)
{
   $ocstr = "";
   $sql = " select distinct t3.sord_number
            from supplier_contenedor_items t1
            INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
            INNER JOIN supplier_order t3 ON t2.sord_id = t3.id
            where
            t1.sord_id = {$contid}
            order by t1.id asc";
   $posdata = $CON->select($sql);
   foreach($posdata AS $posdatarow)
      $ocstr .= $posdatarow["sord_number"].", ";
   $ocstr = substr($ocstr, 0, -2);

   $sql = " update supplier_contenedor
            set
            sord_ocs = '{$ocstr}'
            where
            id = {$contid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function getSupplierContenedorItemState($CON, $sord_id, $sord_pos_id, $contid)
{
   $sql = " select SUM(t2.sord_amount) 'cont_amount'
            from supplier_contenedor t1
            INNER JOIN supplier_contenedor_items t2 ON t1.id = t2.sord_id
            where
            t1.sord_status > 0 and
            t2.sord_amount > 0 and
            t2.sord_pos_id = {$sord_pos_id} and
            t2.sord_id     != {$contid} ";
   $cont_amount = $CON->select($sql);
   $cont_amount = (int)$cont_amount[0]["cont_amount"];

   return $cont_amount;
}

//----------------------------------------------------------------------------------
function printPcatFiltersWrk($CON, $catid)
{
   global $_CONFIG;
   $_RET = false;
   
   $sql = " select *
            from productcats
            where
            id = {$catid}";
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

   $selhide = "";
   if($catid == $_CONFIG["TELA_CATID"])
      $selhide = "display:none;";

   //----------------------------------------------------------------------------------
   if(count($trancoms) && $trancoms != false)
   {
      foreach(array_keys($_TRANSCOM) AS $trancomid)
      {  ?>
         <select class="inptxt" style="background-color:#FFFFFF;width:400px;margin-right:10px;<?=$selhide?>" name="sql_comvals_<?=$trancomid?>"
         onchange="document.xform_inp.submit();">
            <option value="" style="background-color:#00A9A6;color:white"><?=$_TRANSCOM[$trancomid]["NAME"]?></option>
            <?php
            foreach(array_keys($_TRANSCOM[$trancomid]["OPTS"]) AS $trancomvalid)
            {
               $issel = "";
               if((int)$_REQUEST["sql_comvals_{$trancomid}"] == $trancomvalid)
                  $issel = "selected";
               ?>
               <option value="<?=$trancomvalid?>" <?=$issel?>><?=$_TRANSCOM[$trancomid]["OPTS"][$trancomvalid]?></option>
               <?php
            }
            ?>
         </select>
         <?php
         $_RET = true;
      }
   }

   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
   $(document).ready(function()
   {
      <?php
      if($_RET)
      {  ?>
         $('#idx_charact_opts').show(0);
         <?php
      }
      else
      {  ?>
         $('#idx_charact_jqres').html('');
         $('#idx_charact_opts').hide(0);
         <?php
      }
      ?>
   });
   </script>
   <?php
   
   return $_RET;
}

//----------------------------------------------------------------------------------
function printPcatFilters($CON, $catid, $_sesmodulename)
{
   $_RET = false;
   
   $sql = " select *
            from productcats
            where
            id = {$catid}";
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

   //----------------------------------------------------------------------------------
   if(count($trancoms) && $trancoms != false)
   {
      foreach(array_keys($_TRANSCOM) AS $trancomid)
      {  ?>
         <select class="text" style="width:375px;margin-right:10px" name="sql_comvals_<?=$trancomid?>[]" multiple size="5">
            <option value="" style="background-color:#00A9A6;color:white"><?=$_TRANSCOM[$trancomid]["NAME"]?></option>
            <?php
            foreach(array_keys($_TRANSCOM[$trancomid]["OPTS"]) AS $trancomvalid)
            {
               $issel = "";
               if(is_array($_SESSION[$_sesmodulename]["sql_comvals"]) && (int)$_SESSION[$_sesmodulename]["sql_comvals"][$trancomid][$trancomvalid])
                  $issel = "selected";
               ?>
               <option value="<?=$trancomvalid?>" <?=$issel?>><?=$_TRANSCOM[$trancomid]["OPTS"][$trancomvalid]?></option>
               <?php
            }
            ?>
         </select>
         <?php
         $_RET = true;
      }
   }

   //----------------------------------------------------------------------------------
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
      <select class="text" style="width:375px;" name="sql_equvals[]" id="sql_equvals" multiple size="5">
         <option value="" style="background-color:#00A9A6;color:white">Mï¿½QUINAS</option>
         <?php
         foreach($machines AS $machine)
         {
            $issel = "";
            if(is_array($_SESSION[$_sesmodulename]["sql_equvals"]) &&
               array_search($machine["id"], $_SESSION[$_sesmodulename]["sql_equvals"]) !== false)
               $issel = "selected";
            ?>
            <option value="<?=$machine["id"]?>" <?=$issel?>>
               <?=$machine["type_ant_title"]?> | <?=$machine["equipo_name"]?>
            </option>
            <?php
         }
         ?>
      </select>
      <?php
      $_RET = true;
   }

   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
   $(document).ready(function()
   {
      <?php
      if($_RET)
      {  ?>
         $('#idx_charact_opts').show(0);
         <?php
      }
      else
      {  ?>
         $('#idx_charact_jqres').html('');
         $('#idx_charact_opts').hide(0);
         <?php
      }
      ?>
   });
   </script>
   <?php
   
   return $_RET;
}

//----------------------------------------------------------------------------------
function getShopPromotions($CON, $shopid)
{
   $currtme = time();
   $sql = " select t1.prom_dsc, t7.item_id, t1.prom_dsc_type
            from item_promotions t1
            INNER JOIN item_promotions_items t7 ON t1.id = t7.prom_id
            INNER JOIN item_promotions_shops t8 ON t1.id = t8.prom_id
            where
            t1.prom_status    = 1 and
            {$currtme} between t1.prom_datefrom and t1.prom_dateto and
            t1.prom_released  > 0 and
            t1.prom_dsc       != 0.00 and
            t8.shop_id        = {$shopid}";
   $allproms = $CON->select($sql);
   foreach($allproms AS $allprom)
   {
      $_RET["_DSC"][$allprom["item_id"]] = $allprom["prom_dsc"];
      $_RET["_TYP"][$allprom["item_id"]] = $allprom["prom_dsc_type"];
   }
   return $_RET;
}

//----------------------------------------------------------------------------------
function getCentralStorehouse($CON, $shopid)
{
   $sql = " select id
            from company_shops_storehouses
            where
            st_shop_id  = {$shopid} and
            st_status   > 0
            order by id asc
            LIMIT 0,1";
   $data = $CON->select($sql);
   return (int)$data[0]["id"];
}

//----------------------------------------------------------------------------------
function getShopOrdersStatus($stat, $strc_shopsent, $strc_shopreceived, $formated = false)
{
   if($formated)
   {
      if((int)$stat == 1)
      {
         return "<b style='color:red;font-weight:bold !important'>Por despachar</b>";
      }
      elseif((int)$stat == 2)
      {
         if(!(int)$strc_shopreceived)
            return "<b style='color:navy;font-weight:bold !important'>Despachado</b>";
         else
            return "<b class='msg_save_ok' style='font-weight:bold !important'>Recibido</b>";
      }
   }
   else
   {
      if((int)$stat == 1)
      {
         return "Por despachar";
      }
      elseif((int)$stat == 2)
      {
         if(!(int)$strc_shopreceived)
            return "Despachado";
         else
            return "Recibido";
      }
   }
}

//----------------------------------------------------------------------------------
function bookStockpackChange($CON, $stockchangeid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from stockpacks t1
            where
            t1.id = {$stockchangeid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["stk_status"] == 1)
   {
      $sql = " update stockpacks
               set
               stk_status     = 2,
               stk_updusr     = {$_SESSION["user_id"]},
               stk_upddat     = {$currtme}
               where
               id = {$stockchangeid}";
      $CON->no_result($sql);

      $stk_num = createTransactionNumber($CON, $headdata["stk_companyid"], "stockchange");
      $sql = " insert into stockchanges
               (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                stk_negative, stk_crtdat, stk_crtusr, stk_fixedsthid, stk_stockpackid)
               VALUES
               ('{$stk_num}', 'Generado por sistema',
                 {$headdata["stk_issueid"]}, {$headdata["stk_companyid"]}, {$headdata["stk_shopid"]}, {$headdata["stk_bookdate"]},
                 {$headdata["stk_negative"]}, {$currtme}, {$_SESSION["user_id"]}, {$headdata["stk_fixedsthid"]}, {$stockchangeid})";
      $res = $CON->no_result($sql);
      if($res)
      {
         $stcid = mysql_insert_id();
         $sql = " select t2.*
                  from stockpacks_items t2
                  where
                  t2.stk_id = {$stockchangeid}
                  order by 3 asc";
         $posdata = $CON->select($sql);
         foreach($posdata AS $posrow)
         {
            $sql = " insert into stockchanges_items
                     (stk_id, item_id, item_pos, item_amount, item_type, item_st_id)
                     VALUES
                     ({$stcid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$posrow["item_amount"]},
                     '{$posrow["item_type"]}', {$posrow["item_st_id"]})";
            $CON->no_result($sql);
         }
         bookStockChange($CON, $stcid);

         //----------------------------------------------------------------------------------
         $otherissueid = 15;
         $otherisnegat = 1;
         if($headdata["stk_negative"])
         {
            $otherissueid = 16;
            $otherisnegat = 0;
         }

         $stk_num = createTransactionNumber($CON, $headdata["stk_companyid"], "stockchange");
         $sql = " insert into stockchanges
                  (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                   stk_negative, stk_crtdat, stk_crtusr, stk_fixedsthid, stk_stockpackid)
                  VALUES
                  ('{$stk_num}', 'Generado por sistema',
                    {$otherissueid}, {$headdata["stk_companyid"]}, {$headdata["stk_shopid"]}, {$headdata["stk_bookdate"]},
                    {$otherisnegat}, {$currtme}, {$_SESSION["user_id"]}, {$headdata["stk_fixedsthid"]}, {$stockchangeid})";
         $res = $CON->no_result($sql);
         if($res)
         {
            $stcid2 = mysql_insert_id();
            $item_pos = 0;
            foreach($posdata AS $posrow)
            {
               $sql = " select *
                        from prod_item_pack_pos
                        where
                        item_id = {$posrow["item_id"]}
                        order by pack_item_pos";
               $packitems = $CON->select($sql);
               foreach($packitems AS $packitem)
               {
                  $item_amount = round($packitem["pack_item_amount"] * $posrow["item_amount"],2);
                  $sql = " insert into stockchanges_items
                           (stk_id, item_id, item_pos, item_amount, item_type, item_st_id)
                           VALUES
                           ({$stcid2}, {$packitem["pack_item_id"]}, {$item_pos}, {$item_amount},
                           'item', {$posrow["item_st_id"]})";
                  $CON->no_result($sql);
                  $item_pos++;
               }
            }

            bookStockChange($CON, $stcid2);
         }
      }
   }
}

//----------------------------------------------------------------------------------
function delStockpackChange($CON, $stockchangeid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from stockpacks t1
            where
            t1.id = {$stockchangeid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["stk_status"] == 2)
   {
      $sql = " select id
               from stockchanges
               where
               stk_stockpackid = {$stockchangeid}";
      $relsths = $CON->select($sql);
      foreach($relsths AS $relsth)
      {
         delStockChange($CON, $relsth["id"]);

         $sql = " update stockchanges
                  set
                  stk_status     = 0,
                  stk_updusr     = {$_SESSION["user_id"]},
                  stk_upddat     = {$currtme}
                  where
                  id = {$relsth["id"]}";
         $CON->no_result($sql);
      }
      $sql = " update stockpacks
               set
               stk_status     = 1,
               stk_updusr     = {$_SESSION["user_id"]},
               stk_upddat     = {$currtme}
               where
               id = {$stockchangeid}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function getItemProdPackItems($CON, $itemid)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod
            from prod_item_pack_pos t1
            INNER JOIN item t2 ON t1.pack_item_id = t2.id
            where
            t1.item_id = {$itemid}
            order by 3";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function cleanupInvoiceGens($CON, $invcid)
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell_parts
            where
            part_invc_id = {$invcid}";
   $parts = $CON->select($sql);
   foreach($parts AS $part)
   {
      $sql = " select count(*) 'cc'
               from invoices_sell_parts_items
               where
               invc_id = {$invcid} and
               part_id = {$part["id"]}";
      $itemcount = $CON->select($sql);
      $itemcount = (int)$itemcount[0]["cc"];
      if(!$itemcount)
      {
         $sql = " delete from invoices_sell_parts
                  where
                  id = {$part["id"]}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell_parts
            where
            part_invc_id = {$invcid}";
   $parts = $CON->select($sql);
   foreach($parts AS $part)
   {
      $sql = " select invc_id, part_id, item_id, item_pos
               from invoices_sell_parts_items
               where
               invc_id = {$invcid} and
               part_id = {$part["id"]}
               order by item_pos asc";
      $items = $CON->select($sql);
      $px = 0;
      foreach($items AS $item)
      {
         $sql = " update invoices_sell_parts_items
                  set
                  item_pos = {$px}
                  where
                  invc_id  = {$item["invc_id"]} and
                  part_id  = {$item["part_id"]} and
                  item_id  = {$item["item_id"]} and
                  item_pos = {$item["item_pos"]}";
         $xres = $CON->no_result($sql);
         $px++;
      }
   }
}

//----------------------------------------------------------------------------------
function getOrdersRelations($CON, $orderid, $fullscan = true)
{
   global $_RESGLBIDS;
   
   $_RESGLBIDS[$orderid] = 1;
   
   $sql = " select t2.id
            from orders_rels t1
            INNER JOIN orders t2 ON t1.req_id_2 = t2.id
            where
            t1.req_id_1 = {$orderid} and
            t2.req_status > 0
            UNION ALL
            select t2x.id
            from orders_rels t1x
            INNER JOIN orders t2x ON t1x.req_id_1 = t2x.id
            where
            t1x.req_id_2 = {$orderid} and
            t2x.req_status > 0";
   $xres = $CON->select($sql);

   for($x = 0; $x < count($xres) && $xres != false; $x++)
   {
      if(!(int)$_RESGLBIDS[$xres[$x]["id"]] && $fullscan)
         getOrdersRelations($CON, $xres[$x]["id"]);

      $_RESGLBIDS[$xres[$x]["id"]] = 1;
   }
   ksort($_RESGLBIDS);
}

//----------------------------------------------------------------------------------
function getOrdersDirectRelation($CON, $orderid)
{
   global $_RESGLBIDS;
   
   $sql = " select t2.*
            from orders_rels t1
            INNER JOIN orders t2 ON t1.req_id_2 = t2.id
            where
            t1.req_id_1 = {$orderid} and
            t2.req_status > 0";
   $xres = $CON->select($sql);
   for($x = 0; $x < count($xres) && $xres != false; $x++)
      $_RESGLBIDS[$xres[$x]["id"]] = $xres[$x];
   $sql = " select t2.*
            from orders_rels t1
            INNER JOIN orders t2 ON t1.req_id_1 = t2.id
            where
            t1.req_id_2 = {$orderid} and
            t2.req_status > 0";
   $xres = $CON->select($sql);
   for($x = 0; $x < count($xres) && $xres != false; $x++)
      $_RESGLBIDS[$xres[$x]["id"]] = $xres[$x];
   ksort($_RESGLBIDS);
}

//----------------------------------------------------------------------------------
function getItemStockComp($CON, $shopid, $itemid, $itemtype)
{

   $sql = " SELECT SUM(t2.item_amount) 'comp_amount'
            from orders t1
            INNER JOIN orders_items t2 ON t1.id = t2.req_id
            where
            t1.req_status     > 1 and
            t1.req_status     < 4 and
            t1.req_shop_id    = {$shopid} and
            t1.req_isreserva  = 0         and
            t2.item_id        = {$itemid} and
            t2.item_type      = '{$itemtype}' ";
   $samount = $CON->select($sql);
   $samount = $samount[0]["comp_amount"];

   return $samount;

}

//----------------------------------------------------------------------------------
function getItemCurrentStock($CON, $shopid, $itemid, $itemtype, $breakdown = false, $reserva = 0)
{
   $_SHOP = " ";
   if((int)$shopid)
      $_SHOP = " t1.shop_id = {$shopid} and ";

   if($itemtype == "item")
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from item_shops_storehouses t1
               LEFT OUTER JOIN company_shops_storehouses t2 ON t1.st_id = t2.id
               where
               t1.item_id = {$itemid} and
               {$_SHOP}
               t2.st_status = 1 ";
      if($reserva == 1)
         $sql .= " and t2.st_reserva = 1 ";
      if($reserva == 2)
         $sql .= " and t2.st_reserva = 0 ";
   }
   else
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from itemlist_shops_storehouses t1
               LEFT OUTER JOIN company_shops_storehouses t2 ON t1.st_id = t2.id
               where
               t1.item_id = {$itemid} and
               {$_SHOP}
               t2.st_status = 1 ";
      if($reserva == 1)
         $sql .= " and t2.st_reserva = 1 ";
      if($reserva == 2)
         $sql .= " and t2.st_reserva = 0 ";
   }

   if($breakdown == true && $itemtype == "itemlist")
   {
      $posamt = 0;
      $itemlistpos   = getItemListContent($CON, $itemid);
      foreach($itemlistpos AS $itemlistrow)
         $posamt += $itemlistrow["item_amount"];

      $itemsts = getItemStorehouses($CON, $shopid, $itemlistpos[0]["item_id"],"item");
      foreach(array_keys($itemsts) AS $stid)
      {
         $currstock = getItemShopStorehouseCurrentStock($CON, $shopid, $stid, $itemlistpos[0]["item_id"], "item");
         $currstock = $currstock / $posamt;
         $shopstock += $currstock;
      }
      return $shopstock;
   }

   $stockcount = $CON->select($sql);
   $stockcount = $stockcount[0]["iss_inventory"];

   return $stockcount;
}
//----------------------------------------------------------------------------------
function invoiceSellConvertShpToDesc($CON, $invcid, $dlvid)
{
   
   $sql = " select *
            from invoices_sell
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   $sql = " select *
            from invoices_sell_parts
            where
            part_invc_id   = {$invcid} and
            part_dlv_id    = {$dlvid}";
   $parts = $CON->select($sql);
   foreach($parts AS $part)
   {
      $sql = " delete from invoices_sell_parts_items
               where
               invc_id  = {$invcid} and
               part_id  = {$part["id"]}";
      $CON->no_result($sql);

      $sql = " select *
               from orders_delivery
               where
               id = {$dlvid}";
      $dlvdata = $CON->select($sql);
      $dlvdata = $dlvdata[0];

      $dlvdate   = date("d.m.Y", $dlvdata["dlv_delivery_date"]);
      $item_desc = "GUIA {$dlvdata["dlv_docnum"]}, FECHA {$dlvdate}";

      if((int)$headdata["invc_taxes"])
         $item_sellprice_taxes_perc = $_SESSION["_CONF"]["conf_taxes"];
      else
         $item_sellprice_taxes_perc = 0.00;
         
      $item_sellprice_brutto        = (float)$dlvdata["dlv_total_brutto"];
      $item_sellprice_netto         = (float)$dlvdata["dlv_total_netto"];
      $item_sellprice_netto_dsc     = (float)$dlvdata["dlv_total_netto"];
      $item_sellprice_brutto_dsc    = (float)$dlvdata["dlv_total_brutto"];
      $item_sellprice_taxes         = (float)$dlvdata["dlv_total_taxes"];

      $sql = " insert into invoices_sell_parts_items
               (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_desc,
                item_sellprice_brutto, item_sellprice_taxes_perc, item_sellprice_netto,
                item_sellprice_netto_dsc, item_sellprice_brutto_dsc, item_sellprice_taxes)
               VALUES
               ({$invcid}, {$part["id"]}, 9999999, 0, 1, 'manual', '{$item_desc}',
               {$item_sellprice_brutto}, {$item_sellprice_taxes_perc}, {$item_sellprice_netto},
               {$item_sellprice_netto_dsc}, {$item_sellprice_brutto_dsc}, {$item_sellprice_taxes})";
      $CON->no_result($sql);

      $sql = " update invoices_sell
               set
               invc_discount_perc = 0.00,
               invc_discount_amt = 0.00,
               invc_discount_amount_netto = 0.00
               where
               id = {$invcid}";
      $CON->no_result($sql);

      recalcOrder($CON, $invcid, "INVOICE");
   }
}

//----------------------------------------------------------------------------------
function createInternalBuyNumber($CON, $num_company_id, $num_shop_id, $num_trantype, $useDate = 0)
{
   if(!$useDate)
   {
      $num_month = (int)date('m');
      $num_year  = (int)date('Y');
   }
   else
   {
      $num_month = (int)date('m', $useDate);
      $num_year  = (int)date('Y', $useDate);
   }

   $sql = " select *
            from number_system_month
            where
            num_company_id = {$num_company_id} and
            num_shop_id    = {$num_shop_id} and
            num_month      = {$num_month} and
            num_year       = {$num_year} and
            num_trantype   = '{$num_trantype}'";
   $currdata = $CON->select($sql);
   $currdata = $currdata[0];

   if((int)$currdata["num_company_id"])
   {
      $counter = $currdata["num_counter"] +1;
      $sql = " update number_system_month
               set
               num_counter = {$counter}
               where
               id = {$currdata["id"]}";
      $CON->no_result($sql);
   }
   else
   {
      $counter = 1;
      $sql = " insert into number_system_month
               (num_company_id, num_shop_id, num_month, num_year, num_trantype, num_counter)
               VALUES
               ({$num_company_id}, {$num_shop_id}, {$num_month}, {$num_year}, '{$num_trantype}', {$counter})";
      $CON->no_result($sql);
   }
   return $counter;
}

//----------------------------------------------------------------------------------
function calcNormalizeDiscountArray($_INVCNETTOS, $orderdiscountamt)
{
   $_TOTALDSC = getTotalDiscountFromArray($_INVCNETTOS);
   if($_TOTALDSC == $orderdiscountamt)
      return $_INVCNETTOS;
      
   foreach(array_keys($_INVCNETTOS) AS $invcid)
   {
      $_TOTALDSC = getTotalDiscountFromArray($_INVCNETTOS);
      if($_TOTALDSC > $orderdiscountamt)
      {
         $_INVCNETTOS[$invcid]["DISCOUNT"]--;
         return $_INVCNETTOS;
         calcNormalizeDiscountArray($_INVCNETTOS, $orderdiscountamt);
      }
      elseif($_TOTALDSC < $orderdiscountamt)
      {
         $_INVCNETTOS[$invcid]["DISCOUNT"]++;
         calcNormalizeDiscountArray($_INVCNETTOS, $orderdiscountamt);
      }
   }
   return $_INVCNETTOS;
}

function getTotalDiscountFromArray($_INVCNETTOS)
{
   $_TOTALDSC = 0.00;
   foreach(array_keys($_INVCNETTOS) AS $invcid)
      $_TOTALDSC += $_INVCNETTOS[$invcid]["DISCOUNT"];
   return $_TOTALDSC;
}

//----------------------------------------------------------------------------------
function getUbicaciones($CON, $compid = 0, $shopid = 0)
{
   $sql = " select *
            from ubicacion
            where
            ubi_status > 0 ";
   if($compid)
      $sql .= " and ubi_companyid = {$compid} ";
   if($shopid)
      $sql .= " and ubi_shopid = {$shopid} ";
   $sql .= " order by ubi_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getStockShared($CON, $itemid, $itemtype, $shopid)
{
   $sql = " select SUM(t2.item_amount_shipped - t2.item_amount) 'sharedamount'
            from orders t1
            INNER JOIN orders_items t2 ON t1.id = t2.req_id
            where
            t1.req_status     > 1 and
            t1.req_status     < 4 and
            t1.req_shop_id    = {$shopid} and
            t1.req_isreserva  = 1 and
            t2.item_id        = {$itemid} and
            t2.item_type      = '{$itemtype}' and
            t2.item_amount_shipped < t2.item_amount ";
   $samount = $CON->select($sql);

   if($samount[0]["sharedamount"] < 0)
      $samount[0]["sharedamount"] = $samount[0]["sharedamount"]*-1;
   return $samount[0]["sharedamount"];

}

//----------------------------------------------------------------------------------
function getInvoiceSellNotesIssues($CON)
{
   $sql = " select *
            from invoices_notes_sell_issues
            where
            iss_status > 0
            order by iss_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getCompanyInvoiceConfig($CON, $companyid)
{
   $sql = " select company_invc_mode, company_invc_ftp_port, company_invc_itf,
                   company_invc_ftp_host, company_invc_ftp_dir, company_invc_ftp_user,
                   company_invc_ftp_pass
            from company_data
            where
            id = {$companyid}";
   $invcdata = $CON->select($sql);
   return $invcdata[0];
}

//----------------------------------------------------------------------------------
function calcAverageResultados($_DATES, $stopdidx)
{
   global $_VENTAS;
   global $_COSTOS;
   global $_NOTASC;
   global $_NOTASD;
   global $_HABIL;
   
   $stop = false;
   foreach(array_keys($_DATES) AS $didx)
   {
      if($didx == $stopdidx)
         $stop = true;
      if(!$stop)
      {
         if((int)$_HABIL[$didx])
         {
            $_TLT_VENTAS += $_VENTAS[$didx];
            $_TLT_COSTOS += $_COSTOS[$didx];
            $_TLT_NOTASC += $_NOTASC[$didx];
            $_TLT_NOTASD += $_NOTASD[$didx];
            $diffcc++;
         }
      }
   }
   if((int)$_HABIL[$stopdidx])
   {
      $_VENTAS[$stopdidx] = round($_TLT_VENTAS / $diffcc,0);
      $_COSTOS[$stopdidx] = round($_TLT_COSTOS / $diffcc,0);
      $_NOTASC[$stopdidx] = round($_TLT_NOTASC / $diffcc,0);
      $_NOTASD[$stopdidx] = round($_TLT_NOTASD / $diffcc,0);
   }
}

//----------------------------------------------------------------------------------
function dateIsHigherToday($datestr)
{
   $datearr = explode(".", $datestr);
   $today   = mktime(15, 0, 0, date('m'), date('d'), date('Y'));

   //$today   = mktime(15, 0, 0, 8, 12, 2014);
   $compdat = mktime(15, 0, 0, $datearr[1], $datearr[0], $datearr[2]);

   if($compdat >= $today)
      return true;
   return false;
}

//----------------------------------------------------------------------------------
function getMonthDaysHabil($month, $year)
{
   $sql_date_start = mktime(0, 0, 0, $month, 1, $year);
   $sql_date_end   = mktime(23, 59, 59, $month, date('t', $sql_date_start), $year);

   for($x = $sql_date_start; $x <= $sql_date_end; $x += 46400)
   {
      $idx = date("d.m.Y", $x);

      if((int)date('N', $x) != 6 && (int)date('N', $x) != 7)
         $_CC[$idx] = 1;
   }
   return count($_CC);
}

//----------------------------------------------------------------------------------
function getAverageCostFromHistoryLog($CON, $itemid, $companyid, $tstamp, $tran_type = "", $tran_id = 0, $commentonly = false, $docpriceonly = false)
{ 
   $sql = " select item_id, item_costprice_avg_netto, tran_comment, item_doc_price
            from tran_average_costprices_log
            where
            item_id      = {$itemid} and
            company_id   = {$companyid} and
            log_crtdat  <= {$tstamp} ";
   if($tran_type == "invoicesbuy" && (int)$tran_id)
      $sql .= " and tran_id = {$tran_id}";
   $sql .= " order by log_crtdat desc
             LIMIT 0,1";
   $hist = $CON->select($sql);
   $hist = $hist[0];
   if((int)$hist["item_id"])
   {
      if($commentonly && $tran_type == "invoicesbuy" && (int)$tran_id)
         return $hist["tran_comment"];
      elseif($docpriceonly && $tran_type == "invoicesbuy" && (int)$tran_id)
         return (float)$hist["item_doc_price"];
      elseif($commentonly)
         return "";
      elseif($docpriceonly)
         return 0.00;
      return $hist["item_costprice_avg_netto"];
   }
   return false;
}

//----------------------------------------------------------------------------------
function getContainerCosts($CON, $contid)
{
   $sql = " select SUM(t1.sord_kgs_amount) 'cont_total_kg'
            from supplier_contenedor_items t1
            where
            t1.sord_id = {$contid}";
   $cont_total_kg = $CON->select($sql);
   $cont_total_kg = $cont_total_kg[0]["cont_total_kg"];
   $_RET["TOTAL_KG"] = $cont_total_kg;

   //----------------------------------------------------------------------------------
   $sql = " select t2.*
            from invoices_buy t2
            LEFT OUTER JOIN invoices_buy_contenedores t1 ON t1.invc_id = t2.id
            LEFT OUTER JOIN supplier t4   ON t2.invc_supplier_id = t4.id
            where
            t1.cont_id     = {$contid} and
            t2.invc_status > 1
            order by t2.invc_date, t2.id";
   $invoices = $CON->select($sql);

   $_RET["TOTAL_COSTS"] = 0.00;
   for($x = 0; $x < count($invoices) && $invoices != false; $x++)
   {
      $row = $invoices[$x];
      if(!(int)$row["invc_importation"])
         $invc_total_value = $row["invc_total_netto_dsc"];
      else
         $invc_total_value = $row["invc_import_total"];

      $sql = " select distinct t2.id 'cont_id', SUM(t3.sord_kgs_amount) 'cont_total_kg'
               from invoices_buy_contenedores t1
               INNER JOIN supplier_contenedor t2         ON t1.cont_id = t2.id
               INNER JOIN supplier_contenedor_items t3   ON t3.sord_id = t2.id
               where
               t1.invc_id     = {$row["id"]} and
               t2.sord_status > 1
               group by 1";
      $allinvcconainters = $CON->select($sql);

      $_ALLINVCCONTTOTALKG = 0.00;
      for($y = 0; $y < count($allinvcconainters) && $allinvcconainters != false; $y++)
      {
         $_ALLINVCCONTTOTALKG += $allinvcconainters[$y]["cont_total_kg"];
      }

      for($y = 0; $y < count($allinvcconainters) && $allinvcconainters != false; $y++)
      {
         $invc_perc = ($allinvcconainters[$y]["cont_total_kg"] / $_ALLINVCCONTTOTALKG * 100);
         $allinvcconainters[$y]["cont_invc_costval"] = $invc_total_value / 100 * $invc_perc;

         if($allinvcconainters[$y]["cont_id"] == $contid)
            $_RET["TOTAL_COSTS"] += $allinvcconainters[$y]["cont_invc_costval"];
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " select t2.item_id 'supporder_itemid', t2.sord_id 'supporder_id',
                   t2.item_pos 'supporder_itempos', t2.item_amount 'supporder_item_amount',
                   t1.sord_kgs_amount 'item_cont_kgs'
            from supplier_contenedor_items t1
            INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
            where
            t1.sord_id = {$contid}";
   $contitems = $CON->select($sql);
   for($x = 0; $x < count($contitems) && $contitems != false; $x++)
   {
      $row = $contitems[$x];
      $lineperc  = $row["item_cont_kgs"] / $_RET["TOTAL_KG"] * 100;
      $linecosts = $_RET["TOTAL_COSTS"] / 100 * $lineperc;

      $contitems[$x]["line_costs_perc"]      = $lineperc;
      $contitems[$x]["line_costs_value"]     = $linecosts;
      $contitems[$x]["line_itemunit_costs"]  = $contitems[$x]["line_costs_value"] / $contitems[$x]["supporder_item_amount"];
   }

   $_RET["_ITEMS"] = $contitems;

   return $_RET;
}

//----------------------------------------------------------------------------------
function getItemFiFoCostV2Unibag($CON, $companyid, $itemid, $totalamount)
{
   $retprice      = 0.00;
   $_STOCKHISTS   = Array();
   $_TOTALSTOCK   = $totalamount;
   
   $sql = " select distinct t1.id, t1.invc_supplier_id, t1.invc_date, t2.item_amount,
                   t2.item_costprice_netto_dsc2 'pricetotal', t1.invc_importation, t1.invc_taxes,
                   t2.item_costprice_import_total, t1x.part_sord_id, t2.item_supporder_pos
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t1x      ON t1.id = t1x.part_invc_id
            INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
            INNER JOIN supplier t3                 ON t1.invc_supplier_id = t3.id
            where
            t1.invc_company_id   = {$companyid} and
            t1.invc_status       > 0 and
            t1.invc_status       < 4 and
            t2.item_id           = {$itemid} and
            t2.item_type         = 'item'
            order by t1.invc_date desc, t1.id";
   $trans = $CON->select($sql);
   for($x = 0; $x < count($trans) && $trans != false; $x++)
   {
      if($_TOTALSTOCK > 0.00)
      {
         $row = $trans[$x];
         
         if(!(int)$row["invc_importation"])
         {
            $itemprice = $row["pricetotal"] / $row["item_amount"];
         }
         else
         {
            $itemprice = $row["item_costprice_import_total"] / $row["item_amount"];

            if($row["part_sord_id"] > 0)
            {
               $sql = " select t1.id
                        from supplier_contenedor t1
                        INNER JOIN supplier_contenedor_items t2   ON t2.sord_id = t1.id
                        INNER JOIN supplier_order_items t3        ON t2.sord_pos_id = t3.id
                        where
                        t1.sord_status > 1 and
                        t3.sord_id     = {$row["part_sord_id"]}
                        group by t1.id
                        order by t1.sord_eta_puertounibag";
               $conts = $CON->select($sql);

               for($y = 0; $y < count($conts) && $conts != false; $y++)
               {
                  $controw    = $conts[$y];
                  $contcosts  = getContainerCosts($CON, $controw["id"]);

                  for($z = 0; $z < count($contcosts["_ITEMS"]) && $contcosts["_ITEMS"] != false; $z++)
                  {
                     if((int)$contcosts["_ITEMS"][$z]["supporder_id"] == $row["part_sord_id"] &&
                        (int)$contcosts["_ITEMS"][$z]["supporder_itemid"] == $itemid &&
                        (int)$contcosts["_ITEMS"][$z]["supporder_itempos"] == $row["item_supporder_pos"])
                     {
                        $itemprice += $contcosts["_ITEMS"][$z]["line_itemunit_costs"];
                     }
                  }
               }
            }
         }

         if($itemprice > 0.00)
         {
            $reststock = $_TOTALSTOCK - $row["item_amount"];
            if($reststock < 0)
            {
               $move["AMT"] = $_TOTALSTOCK;
               $move["VAL"] = $itemprice;
               $_STOCKHISTS[] = $move;
            }
            else
            {
               $move["AMT"] = $row["item_amount"];
               $move["VAL"] = $itemprice;
               $_STOCKHISTS[] = $move;
            }

            $_TOTALSTOCK   = $_TOTALSTOCK - $row["item_amount"];
         }
      }
   }

   //----------------------------------------------------------------------------------
   $_TOTAL_AMT = 0;
   $_TOTAL_VAL = 0;
   foreach($_STOCKHISTS AS $_STOCKHIST)
   {
      $_TOTAL_AMT += $_STOCKHIST["AMT"];
      $_TOTAL_VAL += ($_STOCKHIST["AMT"] * $_STOCKHIST["VAL"]);
   }
   $retprice = round($_TOTAL_VAL / $_TOTAL_AMT,4);

   return $retprice;
}

//----------------------------------------------------------------------------------
function getItemFiFoCost($CON, $companyid, $itemid, $mode = "finalprice")
{
   global $_CONFIGTRANTYPES;

   //----------------------------------------------------------------------------------
   $shops      = getShops($CON);
   $currstock  = 0.00;
   foreach($shops AS $selshop)
   {
      if($selshop["shop_company_id"] == $companyid)
         $currstock += getItemShopCurrentStock($CON, $selshop["id"], $itemid, "item");
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select distinct t1.id, t1.invc_supplier_id, t3.supp_dsc_finance, t3.supp_dsc_finance_calc,
                   t1.invc_date, t2.item_amount, t2.item_costprice_netto_dsc2 'pricetotal'
            from invoices_buy t1
            INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
            INNER JOIN supplier t3                 ON t1.invc_supplier_id = t3.id
            where
            t1.invc_company_id   = {$companyid} and
            t1.invc_status       > 0 and
            t1.invc_status       < 4 and
            t2.item_id           = {$itemid} and
            t2.item_type         = 'item'";
   $trans = $CON->select($sql);
   foreach($trans AS $row)
   {
      if($row["supp_dsc_finance_calc"] == "NC" && $row["supp_dsc_finance"] > 0.00)
         $row["pricetotal"] = round($row["pricetotal"] - ($row["pricetotal"] / 100 * $row["supp_dsc_finance"]),0);
      
      $BUYS[$row["id"]]["AMT"] += $row["item_amount"];
      $BUYS[$row["id"]]["TOT"] += $row["pricetotal"];
      $BUYS[$row["id"]]["PRC"]  = $BUYS[$row["id"]]["TOT"] / $BUYS[$row["id"]]["AMT"];
      $BUYS[$row["id"]]["DAT"]  = date('d.m.Y', $row["invc_date"]);
   }

   //----------------------------------------------------------------------------------
   $sql = " select tran_id, item_id, tran_day, tran_month, tran_year, tran_amount, tran_type, tran_number
            from tran_data t1
            where
            t1.tran_st_id      > 0 and
            t1.item_id         = {$itemid} and
            t1.tran_company_id = {$companyid}
            order by t1.tran_year desc, t1.tran_month desc, t1.tran_day desc, t1.tran_crtdat desc";
   $itemtrans = $CON->select($sql);

   //----------------------------------------------------------------------------------
   // Calculate Saldos
   //----------------------------------------------------------------------------------
   $x = 0;
   foreach($itemtrans AS $itemtran)
   {
      if($_CONFIGTRANTYPES[$itemtran["tran_type"]] === true || $_CONFIGTRANTYPES[$itemtran["tran_type"]] === false)
         $addamount = $_CONFIGTRANTYPES[$itemtran["tran_type"]];
         
      $itemtrans[$x]["newstock"]    = $currstock;
      $itemtrans[$x]["addamount"]   = $addamount;

      if($addamount)
         $currstock -= $itemtran["tran_amount"];
      else
         $currstock += $itemtran["tran_amount"];

      $itemtrans[$x]["currstock"]   = $currstock;

      $x++;
   }

   $itemtrans = array_reverse($itemtrans);

   //----------------------------------------------------------------------------------
   // Get first buy + fix history (transactions without buying before)
   // Set available amounts
   //----------------------------------------------------------------------------------
   $x = 0;
   $FIRSTBUY = false;
   $FIRSTADD = false;
   foreach($itemtrans AS $itemtran)
   {
      // Set available amounts for positive entries
      if($itemtran["addamount"])
      {
         if(!$FIRSTADD)
         {
            $itemtrans[$x]["available"] = $itemtran["newstock"];
            $FIRSTADD = true;
         }
         else
            $itemtrans[$x]["available"] = $itemtran["tran_amount"];
      }
      
      $stamp = mktime(15, 0, 0, $itemtran["tran_month"], $itemtran["tran_day"], $itemtran["tran_year"]);

      // set values for buying invoice
      if($itemtrans[$x]["tran_type"] == "invoicesbuy")
      {
         $itemtrans[$x]["stkval"] = $BUYS[$itemtran["tran_id"]]["PRC"];
         //$itemtrans[$x]["stkval"] = $BDAT[date('d.m.Y',$stamp)]["PRC"];
      }

      // fix history without prices
      if($itemtrans[$x]["tran_type"] == "invoicesbuy" && !$FIRSTBUY)
      {
         $itemtrans[$x]["calcinit"] = true;
         $FIRSTBUY = true;
         for($y = $x -1; $y >= 0; $y--)
         {
            $itemtrans[$y]["stkval"] = $itemtrans[$x]["stkval"];
         }
      }
      $x++;
   }

   //----------------------------------------------------------------------------------
   // Set prices for positive entries other than invoices
   //----------------------------------------------------------------------------------
   $x = 0;
   foreach($itemtrans AS $itemtran)
   {
      if($itemtran["addamount"] && $itemtran["tran_type"] != "invoicesbuy" && !(float)$itemtran["stkval"])
      {
         $usehistory = true;

         //----------------------------------------------------------------------------------
         // Get prices from shipment > invoice
         //----------------------------------------------------------------------------------
         if($itemtran["tran_type"] == "shipment")
         {
            $usehistory = false;
            $sql = " select distinct t1.invc_supplier_id, t3.supp_dsc_finance, t3.supp_dsc_finance_calc,
                            AVG(t2.item_costprice_netto_dsc2 / t2.item_amount)  'pricetotal'
                     from invoices_buy t1
                     INNER JOIN invoices_buy_parts       t4 ON ( t1.id = t4.part_invc_id )
                     INNER JOIN invoices_buy_parts_items t2 ON ( t1.id = t2.invc_id and t4.id = t2.part_id)
                     INNER JOIN supplier t3                 ON ( t1.invc_supplier_id = t3.id )
                     where
                     t1.invc_company_id   = {$companyid} and
                     t1.invc_status       > 0 and
                     t1.invc_status       < 4 and
                     t2.item_id           = {$itemid} and
                     t2.item_type         = 'item' and
                     t4.part_shp_id       = {$itemtran["tran_id"]}
                     group by 1,2,3";
            $contran = $CON->select($sql);
            if(!count($contran) || $contran == false)
               $usehistory = true;
            else
            {
               $contran = $contran[0];
               if($contran["supp_dsc_finance_calc"] == "NC" && $contran["supp_dsc_finance"] > 0.00)
                  $contran["pricetotal"] = round($contran["pricetotal"] - ($contran["pricetotal"] / 100 * $contran["supp_dsc_finance"]),0);
               $itemtrans[$x]["stkval"] = $contran["pricetotal"];
            }
         }

         //----------------------------------------------------------------------------------
         // get prices from history transactions
         //----------------------------------------------------------------------------------
         if($usehistory)
         {
            //Adjusts / get average from history
            $gesstock = 0.00;
            $gesprice = 0.00;
            for($y = 0; $y < $x; $y++)
            {
               $gesstock += $itemtrans[$y]["available"];
               $gesprice += ($itemtrans[$y]["available"] * $itemtrans[$y]["stkval"]);
            }
            $itemtrans[$x]["stkval"] = $gesprice/$gesstock;
         }

         // Not found take the last
         if(!(float)$itemtrans[$x]["stkval"])
         {
            for($y = $x -1; $y >= 0; $y--)
            {
               if($itemtrans[$y]["stkval"] > 0.00)
               {
                  $itemtrans[$x]["stkval"] = $itemtrans[$y]["stkval"];
                  $y = -1;
               }
            }
         }
      }
      $x++;
   }

   //----------------------------------------------------------------------------------
   // return only final value
   //----------------------------------------------------------------------------------
   if($mode == "finalprice")
   {
      //----------------------------------------------------------------------------------
      // negative transaction => discount available amounts from history
      //----------------------------------------------------------------------------------
      $x = 0;
      foreach($itemtrans AS $itemtran)
      {
         if(!$itemtran["addamount"])
         {
            $descamount = $itemtran["tran_amount"];
            for($y = 0; $y < count($itemtrans) && $descamount > 0.00; $y++)
            {
               if($itemtrans[$y]["available"] > 0.00)
               {
                  $itemtrans[$y]["available"] -= $descamount;
                  if($itemtrans[$y]["available"] >= 0.00)
                     $descamount = 0.00;
                  else
                  {
                     $descamount = $itemtrans[$y]["available"] * -1;
                     $itemtrans[$y]["available"] = 0.00;
                  }
               }
            }
         }
         $x++;
      }
   
      $gesstock = 0.00;
      $gesprice = 0.00;
      foreach($itemtrans AS $itemtran)
      {
         $gesstock += $itemtran["available"];
         $gesprice += ($itemtran["available"] * $itemtran["stkval"]);
      }
      if((float)$gesprice/$gesstock == 0.00)
      {
         foreach($itemtrans AS $itemtran)
            if($itemtran["stkval"] > 0.00)
               $retvalue = $itemtran["stkval"];
         return $retvalue;
      }
      return (float)$gesprice/$gesstock;
   }
   else
   {
      //----------------------------------------------------------------------------------
      // calculate average / discount for all lines
      //----------------------------------------------------------------------------------
      $transcopy = $itemtrans;
      $x = 0;
      foreach($itemtrans AS $itemtran)
      {
         $transclones = $transcopy;
         for($z = 0; $z <= $x; $z++)
         {
            if(!$transclones[$z]["addamount"])
            {
               $descamount = $transclones[$z]["tran_amount"];
               for($y = 0; $y < $z && $descamount > 0.00; $y++)
               {
                  if($transclones[$y]["available"] > 0.00)
                  {
                     $transclones[$y]["available"] -= $descamount;
                     if($transclones[$y]["available"] >= 0.00)
                        $descamount = 0.00;
                     else
                     {
                        $descamount = $transclones[$y]["available"] * -1;
                        $transclones[$y]["available"] = 0.00;
                     }
                  }
               }
            }
         }
         $gesstock = 0.00;
         $gesprice = 0.00;
         for($z = 0; $z <= $x; $z++)
         {
            $gesstock += $transclones[$z]["available"];
            $gesprice += ($transclones[$z]["available"] * $transclones[$z]["stkval"]);
         }
         $itemtrans[$x]["avgcost"] = $gesprice/$gesstock;
         $x++;
      }
      //echo "<pre>";
      //print_r($itemtrans);
      $itemtrans = array_reverse($itemtrans);
      return $itemtrans;
   }

   //----------------------------------------------------------------------------------
   // Calculate all average prices
   //----------------------------------------------------------------------------------
   /*
   $gesstock = 0.00;
   $gesprice = 0.00;
   foreach($itemtrans AS $itemtran)
   {
      $gesstock += $itemtran["available"];
      $gesprice += ($itemtran["available"] * $itemtran["stkval"]);
   }
   echo "STOCK: ".$gesstock."<br>";
   echo "VALUE: ".$gesprice."<br>";
   echo "C/U: ".$gesprice/$gesstock."<br>";
   


   echo "<pre>";
   print_r($itemtrans);
   //print_r($BDAT);

   $itemtrans = array_reverse($itemtrans);
   return $itemtrans;
   */
}

//----------------------------------------------------------------------------------
function printFancyBoxLastBuying($CON, $item_code, $shop_id, $item_id, $item_type)
{
   $urlparams = "shopid={$shop_id}&itemid={$item_id}&itemtype={$item_type}";
   if($item_code == "")
      $item_code = "---";
   ?>
   <div style="cursor:pointer" onclick="showFancybox('/libs/modules/orders/show.lastbuying.php?<?=$urlparams?>', 'iframe', 600, 400, 'auto')">
      <?=$item_code?>
   </div>
   <?php
}

//----------------------------------------------------------------------------------
function getFinalSellNetto($CON, $itemid, $itemtype = "item")
{
   //----------------------------------------------------------------------------------
   if($itemtype == "item")
   {
      $sql = " select t2.id
               from item_productcats t1, productcats t2
               where
               t1.item_id     = {$itemid} and
               t1.cat_id      = t2.id and
               t2.cat_status  = 1";
      $selitemcatid = $CON->select($sql);
      $selitemcatid = $selitemcatid[0]["id"];
   }
   else
   {
      $sql = " select t2.id
               from item_productcats_itemlist t1, productcats t2
               where
               t1.item_id     = {$itemid} and
               t1.cat_id      = t2.id and
               t2.cat_status  = 1";
      $selitemcatid = $CON->select($sql);
      $selitemcatid = $selitemcatid[0]["id"];
   }

   //----------------------------------------------------------------------------------
   $sql = " select cat_dsc_maxperc
            from productcats
            where
            id = {$selitemcatid}";
   $cat_dsc_maxperc = $CON->select($sql);
   $cat_dsc_maxperc = (float)$cat_dsc_maxperc[0]["cat_dsc_maxperc"];

   //----------------------------------------------------------------------------------
   if($itemtype == "item")
   {
      $sql = " select *
               from item
               where
               id = {$itemid}";
      $itemdata = $CON->select($sql);
      $itemdata = $itemdata[0];
   }
   else
   {
      $sql = " select *
               from itemlist
               where
               id = {$itemid}";
      $itemdata = $CON->select($sql);
      $itemdata = $itemdata[0];
   }

   $item_sellprice_netto   = $itemdata["item_sellprice_netto"];
   $item_sellprice_netto   = round($item_sellprice_netto - ($item_sellprice_netto / 100 * $cat_dsc_maxperc),0);
   return $item_sellprice_netto;
}

//----------------------------------------------------------------------------------
function getSupplierFinalCostNetto($CON, $supid = 0, $itemid, $itemtype = 'item', $useprice = 0.00, $nofinancedsc = 0)
{
   //----------------------------------------------------------------------------------
   /*
   if($itemtype == "item")
   {
      $sql = " select t2.id
               from item_productcats t1, productcats t2
               where
               t1.item_id     = {$itemid} and
               t1.cat_id      = t2.id and
               t2.cat_status  = 1";
      $selitemcatid = $CON->select($sql);
      $selitemcatid = $selitemcatid[0]["id"];
   }
   else
   {
      $sql = " select t2.id
               from item_productcats_itemlist t1, productcats t2
               where
               t1.item_id     = {$itemid} and
               t1.cat_id      = t2.id and
               t2.cat_status  = 1";
      $selitemcatid = $CON->select($sql);
      $selitemcatid = $selitemcatid[0]["id"];
   }

   //----------------------------------------------------------------------------------
   $sql = " select cat_dsc_maxbuyperc
            from productcats
            where
            id = {$selitemcatid}";
   $cat_dsc_maxbuyperc = $CON->select($sql);
   $cat_dsc_maxbuyperc = (float)$cat_dsc_maxbuyperc[0]["cat_dsc_maxbuyperc"];

   //----------------------------------------------------------------------------------
   if($supid > 0)
   {
      $sql = " select t1.*, t2.supp_dsc_finance_calc, t2.supp_dsc_finance
               from {$itemtype}_suppliers t1
               LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
               LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
               where
               item_id = {$itemid} and
               t1.supplier_id = {$supid}";
      $suppdata = $CON->select($sql);
      $suppdata = $suppdata[0];
   }
   else
   {
      $sql = " select t1.*, t2.supp_dsc_finance_calc, t2.supp_dsc_finance
               from {$itemtype}_suppliers t1
               LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
               LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
               where
               item_id = {$itemid} and
               t1.item_supp_act = 1";
      $suppdata = $CON->select($sql);
      $suppdata = $suppdata[0];
   }

   //----------------------------------------------------------------------------------
   $sql = " select dct_dsc_off
            from dscbuy_supplier_item
            where
            dct_item_id       = {$itemid} and
            dct_item_type     = '{$itemtype}' and
            dct_supplier_id   = {$suppdata["supplier_id"]}";
   $item_dsc_off = $CON->select($sql);
   $item_dsc_off = (int)$item_dsc_off[0]["dct_dsc_off"];

   $item_costprice_netto   = $suppdata["item_costprice_netto"];
   
   if($useprice > 0.00)
      $item_costprice_netto = $useprice;

   if($item_dsc_off)
      return $item_costprice_netto;
   
   $supp_dsc_finance_calc  = $suppdata["supp_dsc_finance_calc"];
   $supp_dsc_finance       = $suppdata["supp_dsc_finance"];

   $item_costprice_netto   = round($item_costprice_netto - ($item_costprice_netto / 100 * $cat_dsc_maxbuyperc),0);

   if(!(int)$nofinancedsc)
   {
      if($supp_dsc_finance_calc == "NC" && $supp_dsc_finance > 0.00)
         $item_costprice_netto = round($item_costprice_netto - ($item_costprice_netto / 100 * $supp_dsc_finance),0);
   }
   */

   //----------------------------------------------------------------------------------
   $itemtype = "item";
   if($supid > 0)
   {
      $sql = " select t1.*, t2.supp_dsc_finance_calc, t2.supp_dsc_finance
               from {$itemtype}_suppliers t1
               LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
               LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
               where
               item_id = {$itemid} and
               t1.supplier_id = {$supid}";
      $suppdata = $CON->select($sql);
      $suppdata = $suppdata[0];
   }
   else
   {
      $sql = " select t1.*, t2.supp_dsc_finance_calc, t2.supp_dsc_finance
               from {$itemtype}_suppliers t1
               LEFT OUTER JOIN supplier t2   ON t1.supplier_id = t2.id
               LEFT OUTER JOIN country t3    ON t2.supp_countryid = t3.id
               where
               item_id = {$itemid} and
               t1.item_supp_act = 1";
      $suppdata = $CON->select($sql);
      $suppdata = $suppdata[0];
   }

   $item_costprice_netto   = $suppdata["item_costprice_netto"];
   
   if($useprice > 0.00)
      $item_costprice_netto = $useprice;

   return $item_costprice_netto;
}

//----------------------------------------------------------------------------------
function orderByProductNumberCallback($a, $b)
{
   return ((int)$a["item_number_prod"] > (int)$b["item_number_prod"]);
}

//----------------------------------------------------------------------------------
function getCustomerDepositFavorSaldo($CON, $custid)
{
   $sql = " select SUM(adm_saldo_fav) 'adm_saldo_fav'
            from adm_deposit
            where
            adm_cust_id = {$custid} and
            adm_status  = 2 and
            adm_deposit_status != 3";
   $adm_saldo_fav = $CON->select($sql);
   return $adm_saldo_fav[0]["adm_saldo_fav"]; 
}

//----------------------------------------------------------------------------------
function autoclosePayDocs($CON, $depid)
{
}

//----------------------------------------------------------------------------------
function getAdminDepositEnvoicesPrint($id = 0)
{
   global $CON;
   $sql = " select group_concat(adm_invoice) as 'invoices'
            from adm_deposit_invoice
            where
            adm_deposit_id = {$id}
            group by adm_deposit_id";
   $res = $CON->select($sql);
   return str_replace(",", ", ",$res[0]["invoices"]);
}

//----------------------------------------------------------------------------------
function getAdminDepositDebitPrint($id = 0)
{
   global $CON;
   $sql = " select group_concat(adm_note_id) as 'debits'
            from adm_deposit_notes_sell
            where
            adm_deposit_id = {$id} and
            adm_note_type  = 2
            group by adm_deposit_id";
   $res = $CON->select($sql);
   return str_replace(",", ", ",$res[0]["debits"]);
}

//----------------------------------------------------------------------------------
function getAdminDepositCreditPrint($id = 0)
{
   global $CON;
   $sql = " select group_concat(adm_note_id) as 'credits'
            from adm_deposit_notes_sell
            where
            adm_deposit_id = {$id} and
            adm_note_type  = 1
            group by adm_deposit_id";
   $res = $CON->select($sql);
   return str_replace(",", ", ",$res[0]["credits"]);
}

//----------------------------------------------------------------------------------
function getDepositType($id = 0)
{
  global $_LANG;

   if($id == 1)
      return $_LANG["MODULE"]["DEPOSIT"][1];
   elseif($id == 2)
      return $_LANG["MODULE"]["DEPOSIT"][2];
   else
      return "&nbsp";
}

//----------------------------------------------------------------------------------
function getBanks($CON)
{
   $sql = " select *
            from bank
            where
            bank_status > 0
            order by bank_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function checkCustomerCreditLimit($CON, $custid, $thisinvcid, $companyid)
{
   //----------------------------------------------------------------------------------
   $_RET["AMOUNT"]   = 0.00;
   $_RET["LIMIT"]    = 0.00;
   $_RET["DIFF"]     = 0.00;
   $_RET["BLOCKED"]  = false;

   //----------------------------------------------------------------------------------
   $usedamount = 0.00;
   
   $sql = " select cust_pricetolerance
            from customer
            where
            id = {$custid}";
   $cust_pricetolerance = $CON->select($sql);
   $cust_pricetolerance = $cust_pricetolerance[0]["cust_pricetolerance"];

   if($cust_pricetolerance > 0.00)
   {
      $_REQUEST["sql_selmode"]      = 3;
      $_REQUEST["sql_date_pfrom"]   = "01.01.2000";
      $_REQUEST["sql_date_pto"]     = date('d.m.Y');
      $_REQUEST["sql_customer"]     = $custid;
      $_REQUEST["sql_paystate"]     = 2;
      $_REQUEST["subexec"]          = "search";
      ob_start();
      include("./libs/modules/stats/selling/referencias.php");
      $output = ob_get_contents();
      ob_end_clean();
      $usedamount = ($_TOTAL_DEBE - $_TOTAL_HABER);

      //----------------------------------------------------------------------------------
      $_RET["AMOUNT"]   = $usedamount;
      $_RET["LIMIT"]    = $cust_pricetolerance;
      $_RET["DIFF"]     = ($cust_pricetolerance - $usedamount) * -1;
   
      if($usedamount > $cust_pricetolerance)
         $_RET["BLOCKED"] = true;
   }
   return $_RET;
}

//----------------------------------------------------------------------------------
function getSellerRunningWeekDays($CON, $sql_startdate, $sql_endate)
{
   //----------------------------------------------------------------------------------
   //$sql_startdate = mktime(0, 0, 0, $month, 1, $year);
   //$sql_endate    = mktime(23, 59, 59, $month, (int)date('t', $sql_startdate), $year);

   for ($x = $sql_startdate; $x <= $sql_endate; $x += 15000)
   {
      $idx = date('d.m.Y', $x);

      if(date('N', $x) == 7)
         $state = 0;
      elseif(date('N', $x) == 6)
         $state = 1;
      else
         $state = 2;
      $_DAYS[$idx] = $state;
   }

   foreach(array_keys($_DAYS) AS $day)
   {
      if((int)$_DAYS[$day] == 2)
         $_RES["WORKDAYS"]++;
      elseif((int)$_DAYS[$day] == 0)
         $_RES["SUNDAYS"]++;
   }

   $sql = " select *
            from holidays
            where
            holiday_date between {$sql_startdate} and {$sql_endate}";
   $freedays = $CON->select($sql);
   foreach($freedays AS $freeday)
   {
      $idx = date('d.m.Y', $freeday["holiday_date"]);
      if(date('N', $freeday["holiday_date"]) != 7)
      {
         $_RES["FREEDAYS"]++;

         if(date('N', $freeday["holiday_date"]) != 6)
            $_RES["WORKDAYS"]--;
      }
   }
   
   return $_RES;
}

//----------------------------------------------------------------------------------
function createNotaAdjuntaForInvoice($CON, $invcid, $invc_paymentid)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t7.pay_title, t2.cust_discount_spec
            from invoices_sell t1
            LEFT OUTER JOIN payments t7 ON t1.invc_paymentid = t7.id
            LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t7.pay_title
            from invoices_sell t1
            LEFT OUTER JOIN payments t7 ON t1.invc_paymentid = t7.id
            where
            t1.invc_parentid = {$invcid} and
            t1.invc_status   = -10";
   $hasnote = $CON->select($sql);
   $hasnote = $hasnote[0];

   if((int)$hasnote["id"])
   {
      $sql = " delete from invoices_sell where id = {$hasnote["id"]}";
      $CON->no_result($sql);
      $sql = " delete from invoices_sell_parts where part_invc_id = {$hasnote["id"]}";
      $CON->no_result($sql);
      $sql = " delete from invoices_sell_parts_items where invc_id = {$hasnote["id"]}";
      $CON->no_result($sql);

      $sql = " update invoices_sell
               set
               invc_childid = 0
               where
               id = {$invcid}";
      $CON->no_result($sql);
   }

   if((int)$headdata["cust_discount_spec"])
   {
      //----------------------------------------------------------------------------------
      $tablesuffix   = $_SESSION["user_id"];
      $temptable     = "temp_invoices_sell_{$tablesuffix}";
      $tempparttable = "temp_invoices_sell_parts_{$tablesuffix}";
      $tempitemtable = "temp_invoices_sell_parts_items_{$tablesuffix}";

      //----------------------------------------------------------------------------------
      $sql = "drop table {$temptable}";
      $CON->no_result($sql);
      $sql = "drop table {$tempparttable}";
      $CON->no_result($sql);
      $sql = "drop table {$tempitemtable}";
      $CON->no_result($sql);
      $sql = " create table {$temptable} select * from invoices_sell where id = {$invcid}";
      $CON->no_result($sql);
      $sql = "update {$temptable} set id = NULL";
      $CON->no_result($sql);
      $sql = "insert into invoices_sell select * from {$temptable}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select MAX(id) 'id'
               from invoices_sell
               where
               invc_number = '{$headdata["invc_number"]}'";
      $cloneid = $CON->select($sql);
      $cloneid = (int)$cloneid[0]["id"];

      //----------------------------------------------------------------------------------
      if($cloneid)
      {
         $sql = " update invoices_sell
                  set
                  invc_status    = -10,
                  invc_parentid  = {$invcid},
                  invc_paymentid = {$invc_paymentid}
                  where
                  id = {$cloneid}";
         $CON->no_result($sql);

         //----------------------------------------------------------------------------------
         $sql = " select *
                  from invoices_sell_parts
                  where
                  part_invc_id = {$invcid}";
         $cloneparts = $CON->select($sql);

         foreach($cloneparts AS $clonepart)
         {
            $sql = "drop table {$tempparttable}";
            $CON->no_result($sql);
            $sql = " create table {$tempparttable} select * from invoices_sell_parts where id = {$clonepart["id"]} and part_invc_id = {$invcid}";
            $CON->no_result($sql);
            $sql = "update {$tempparttable} set id = NULL, part_invc_id = {$cloneid}";
            $CON->no_result($sql);
            $sql = "insert into invoices_sell_parts select * from {$tempparttable}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $sql = " select MAX(id) 'id'
                     from invoices_sell_parts
                     where
                     part_invc_id = {$cloneid}";
            $partcloneid = $CON->select($sql);
            $partcloneid = (int)$partcloneid[0]["id"];

            //----------------------------------------------------------------------------------
            $sql = "drop table {$tempitemtable}";
            $CON->no_result($sql);
            $sql = " create table {$tempitemtable} select * from invoices_sell_parts_items where invc_id = {$invcid} and part_id = {$clonepart["id"]}";
            $CON->no_result($sql);
            $sql = "update {$tempitemtable} set invc_id = {$cloneid}, part_id = {$partcloneid}";
            $CON->no_result($sql);
            $sql = "insert into invoices_sell_parts_items select * from {$tempitemtable}";
            $CON->no_result($sql);
         }

         $invcparts = getInvoiceSellParts($CON, $cloneid);

         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            $posdata = getInvoiceSellPartsItems($CON, $cloneid, $invcparts[$x]["id"]);
            for($y = 0; $y < count($posdata) && $posdata != false; $y++)
            {
               recalcOrderItem($CON, $cloneid, $posdata[$y]["item_id"], $posdata[$y]["item_pos"], true, "INVOICE", $invcparts[$x]["id"], true);
            }
         }
         recalcOrder($CON, $cloneid, "INVOICE");

         //----------------------------------------------------------------------------------
         $sql = " update invoices_sell
                  set
                  invc_childid = {$cloneid}
                  where
                  id = {$invcid}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = "drop table {$temptable}";
      $CON->no_result($sql);
      $sql = "drop table {$tempparttable}";
      $CON->no_result($sql);
      $sql = "drop table {$tempitemtable}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function getItemSupplierAlternatives($CON, $sordid, $itemid, $itemtype, $itempos, $supplierid, $thiscostprice)
{
   $_RET = Array();
   
   $sql = " select t2.*, t3.supp_short
            from {$itemtype}_suppliers t1
            INNER JOIN {$itemtype}_suppliers t2 ON (t1.item_id = t2.item_id and t1.supplier_id != t2.supplier_id)
            INNER JOIN supplier t3 ON t3.id = t2.supplier_id
            where
            t1.item_id     = {$itemid} and
            t1.supplier_id = {$supplierid}
            order by t2.item_costprice_netto asc";
   $results = $CON->select($sql);

   foreach($results AS $result)
   {
      $itemdscstruct = recalcSupplierOrderItem($CON, $sordid, $itemid, $itempos, true, true, $result["supplier_id"]);
      $itemdscstruct["ROW"]["item_costprice_netto"] = $result["item_costprice_netto"];
      $itemdscfinal = recalcSupplierOrder($CON, $sordid, true, $itemdscstruct, $result["supplier_id"]);

      if($itemdscfinal < $thiscostprice && $itemdscfinal > 0.00)
      {
         $temp["supp_short"] = $result["supp_short"];
         $temp["supp_price"] = $itemdscfinal;
         $_RET[] = $temp;
      }
   }

   return $_RET;
}

//----------------------------------------------------------------------------------
function getUserMetas($CON, $val)
{
   $sql = " select *
            from user_production_bonus_config
            order by pro_from";
   $ranges = $CON->select($sql);

   $res = Array();
   foreach($ranges AS $range)
   {
      if(($val >= $range["pro_from"] || ($val / $range["pro_from"] * 100) >= 96.000 ) && $val <= $range["pro_to"])
      {
         $temp["RANGE"]  = printPrice($range["pro_from"])."-".printPrice($range["pro_to"]);
         $temp["AMOUNT"] = $range["pro_amount"];

         if($val < $range["pro_from"])
            $temp["PERC"] = ($val / $range["pro_from"] * 100);
         else
            $temp["PERC"] = 100.00;
            
         $res[] = $temp;
      }
   }
   return $res;
}

//----------------------------------------------------------------------------------
function getUserComissions($CON, $uid, $catid, $company_id, $extmode = "")
{
   $sql = " select *
            from user_comission_cat_user{$extmode}
            where
            cat_id      = {$catid} and
            user_id     = {$uid} and
            company_id  = {$company_id}";
   $usercom = $CON->select($sql);
   $usercom = $usercom[0];

   if((int)$usercom["cat_id"])
      return $usercom;

   $sql = " select *
            from user_comission_cat{$extmode}
            where
            cat_id      = {$catid} and
            company_id  = {$company_id}";
   $usercom = $CON->select($sql);
   $usercom = $usercom[0];
   return $usercom;
}

//----------------------------------------------------------------------------------
function recalcShipment($CON, $shpid)
{
   $posdata = getShipmentPos($CON, $shpid);
   $item_netto_total = 0;
   foreach($posdata AS $posrow)
      $item_netto_total += $posrow["item_costprice_netto_dsc"];
   //----------------------------------------------------------------------------------
   $sql = " update shipment
            set
            shp_total_netto = {$item_netto_total}
            where
            id = {$shpid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function getAvailableTranCharges($CON, $tran_company_id, $tran_shop_id, $tran_item_id, $tran_item_type)
{
   if($tran_item_type == "itemlist")
   {
      $itemlistpos      = getItemListContent($CON, $tran_item_id);
      $tran_item_id     = $itemlistpos[0]["item_id"];
      $tran_packamt     = $itemlistpos[0]["item_amount"];
   }
   
   $sql = " select t1.*, t2.st_name
            from tran_charges t1
            LEFT OUTER JOIN company_shops_storehouses t2 ON t1.tran_st_id = t2.id
            where
            t1.tran_company_id   = {$tran_company_id} and
            t1.tran_shop_id      = {$tran_shop_id} and
            t1.tran_item_id      = {$tran_item_id} and
            t1.tran_booked       = 1 and
            t1.tran_amount_avail > 0.00
            order by t1.tran_prod_date asc, t1.tran_crtdat asc, t1.id asc";
   if($tran_item_type == "itemlist")
   {
      $rets = $CON->select($sql);
      for($x = 0; $x < count($rets) && $rets != false; $x++)
         $rets[$x]["tran_amount_avail"] = $rets[$x]["tran_amount_avail"] / $tran_packamt;
   }
   else
      $rets = $CON->select($sql);

   return $rets;
}

//----------------------------------------------------------------------------------
function posHasItemChargeData($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select count(*) 'cc'
            from tran_charges
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";
            
   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $res = $CON->select($sql);
   return (int)$res[0]["cc"];
}

//----------------------------------------------------------------------------------
function itemHasChargeAct($CON, $itemid, $itemtype)
{
   $item_charges_act = 0;
   if($itemtype == "item")
   {
      $sql = " select item_charges_act
               from item
               where
               id = {$itemid}";
      $item_charges_act = $CON->select($sql);
      $item_charges_act = (int)$item_charges_act[0]["item_charges_act"];
   }
   elseif($itemtype == "itemlist")
   {
      $itemlistpos   = getItemListContent($CON, $itemid);
      $itemid        = $itemlistpos[0]["item_id"];
      
      $sql = " select item_charges_act
               from item
               where
               id = {$itemid}";
      $item_charges_act = $CON->select($sql);
      $item_charges_act = (int)$item_charges_act[0]["item_charges_act"];
   }

   return $item_charges_act;
}

//----------------------------------------------------------------------------------
function posHasItemChargeDataUsed($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select count(*) 'cc'
            from tran_charges_used
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";
            
   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $res = $CON->select($sql);
   return (int)$res[0]["cc"];
}

//----------------------------------------------------------------------------------
function posItemChargeDataAmount($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select SUM(tran_amount) 'tran_amount', SUM(tran_amount_used) 'tran_amount_used'
            from tran_charges
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";

   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $res = $CON->select($sql);
   return $res[0];
}

//----------------------------------------------------------------------------------
function posItemChargeDataAmountUsed($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select SUM(tran_amount) 'tran_amount'
            from tran_charges_used
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";

   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $res = $CON->select($sql);
   return $res[0];
}

//----------------------------------------------------------------------------------
function clearItemChargeData($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " delete from tran_charges
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";

   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function clearItemChargeDataUsed($CON, $tranid, $trantype, $tranpos, $tranpartid = 0, $cloneToSthChange = false)
{
   $sql = " delete from tran_charges_used
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";
   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
   $CON->no_result($sql);

   if($cloneToSthChange)
   {
      $sql = " delete from tran_charges
               where
               tran_id     = {$tranid} and
               tran_pos    = {$tranpos} and
               tran_type   = 'sthup'";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function renameItemChargePos($CON, $tranid, $trantype, $tranpos, $newtranpos, $tranpartid = 0)
{
   $sql = " update tran_charges
            set
            tran_pos    = {$newtranpos}
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";

   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function renameItemChargePosUsed($CON, $tranid, $trantype, $tranpos, $newtranpos, $tranpartid = 0, $cloneToSthChange = false)
{
   $sql = " update tran_charges_used
            set
            tran_pos    = {$newtranpos}
            where
            tran_id     = {$tranid} and
            tran_pos    = {$tranpos} and
            tran_type   = '{$trantype}'";
   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
   $CON->no_result($sql);
   
   if($cloneToSthChange)
   {
      $sql = " update tran_charges
               set
               tran_pos    = {$newtranpos}
               where
               tran_id     = {$tranid} and
               tran_pos    = {$tranpos} and
               tran_type   = 'sthup'";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function updateItemChargeData($CON, $tranid, $trantype, $item_id, $item_type, $tranpos, $tran_company_id, $tran_shop_id, $chargedata, $tranpartid = 0)
{
   //----------------------------------------------------------------------------------
   $params = array();
   parse_str($chargedata, $params);

   //----------------------------------------------------------------------------------
   if($chargedata != "")
      clearItemChargeData($CON, $tranid, $trantype, $tranpos, $tranpartid);

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   $currtme    = time();
   foreach(array_keys($params) AS $reqkey)
   {
      if(strpos($reqkey, "tran_charge_number_") !== false && strpos($reqkey, "tran_charge_number_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         
         $tran_charge_number  = trim(addslashes($params["tran_charge_number_{$idx}"]));
         $tran_amount         = getPrice($params["tran_amount_{$idx}"],10);

         $tran_st_id          = (int)$params["tran_st_id_{$idx}"];
         $tran_prod_date      = trim(addslashes($params["tran_prod_date_{$idx}"]));
         $tran_expire_date    = trim(addslashes($params["tran_expire_date_{$idx}"]));
         $tran_charge_number  = trim(addslashes($params["tran_charge_number_{$idx}"]));

         $tran_prod_date      = explode(".", $tran_prod_date);
         $tran_prod_date      = (int)mktime(15, 0, 0, $tran_prod_date[1], $tran_prod_date[0], $tran_prod_date[2]);
         $tran_expire_date    = explode(".", $tran_expire_date);
         $tran_expire_date    = (int)mktime(15, 0, 0, $tran_expire_date[1], $tran_expire_date[0], $tran_expire_date[2]);

         if($tran_charge_number != "" && $tran_amount > 0.00 && $tran_st_id > 0)
         {
            $tran_item_id        = $item_id;
            $tran_amount_avail   = $tran_amount;
            
            if($item_type == "itemlist")
            {
               $itemlistrow         = getItemListContent($CON, $item_id);
               $itemlistrow         = $itemlistrow[0];
               $tran_item_id        = $itemlistrow["item_id"];
               $tran_amount_avail   = $itemlistrow["item_amount"] * $tran_amount;
            }
            
            $sql = " insert into tran_charges
                     (tran_id, tran_pos, tran_type, tran_charge_number, tran_company_id, tran_shop_id, tran_st_id,
                      item_id, item_type, tran_amount, tran_item_id, tran_amount_avail, tran_prod_date, tran_expire_date, tran_booked,
                      tran_partid, tran_crtdat, tran_crtusr)
                     VALUES
                     ({$tranid}, {$tranpos}, '{$trantype}', '{$tran_charge_number}',
                      {$tran_company_id}, {$tran_shop_id}, {$tran_st_id}, {$item_id}, '{$item_type}',
                      {$tran_amount}, {$tran_item_id}, {$tran_amount_avail}, {$tran_prod_date},
                      {$tran_expire_date}, 0, {$tranpartid}, {$currtme}, {$_SESSION["user_id"]})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
function updateItemChargeDataUsed($CON, $tranid, $trantype, $item_id, $item_type, $tranpos, $tran_company_id, $tran_shop_id, $chargedata, $tranpartid = 0, $cloneToSthChange = false)
{
   //----------------------------------------------------------------------------------
   $params = array();
   parse_str($chargedata, $params);

   //----------------------------------------------------------------------------------
   if($chargedata != "")
      clearItemChargeDataUsed($CON, $tranid, $trantype, $tranpos, $tranpartid, $cloneToSthChange);
   elseif($cloneToSthChange)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from storehousechanges_items
               where
               strc_id  = {$tranid} and
               item_pos = {$tranpos}";
      $sthpos = $CON->select($sql);
      $sthpos = $sthpos[0];
      
      $sql = " update tran_charges
               set
               tran_st_id  = {$sthpos["item_st_dest_id"]}
               where
               tran_id     = {$tranid} and
               tran_pos    = {$tranpos} and
               tran_type   = 'sthup'";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   $currtme    = time();
   foreach(array_keys($params) AS $reqkey)
   {
      if(strpos($reqkey, "tran_refid_") !== false && strpos($reqkey, "tran_refid_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         
         $tran_amount      = getPrice($params["tran_amount_{$idx}"],10);
         $tran_amount_used = $tran_amount;
         $tran_refid       = $params["tran_refid_{$idx}"];

         if($tran_amount != "")
         {
            if($item_type == "itemlist")
            {
               $itemlistrow         = getItemListContent($CON, $item_id);
               $itemlistrow         = $itemlistrow[0];
               $tran_item_id        = $itemlistrow["item_id"];
               $tran_amount_used    = $itemlistrow["item_amount"] * $tran_amount;
            }
            
            $sql = " insert into tran_charges_used
                     (tran_id, tran_pos, tran_type, tran_company_id, tran_shop_id,
                      tran_amount, tran_amount_used, tran_booked, tran_partid, tran_refid, tran_crtdat, tran_crtusr)
                     VALUES
                     ({$tranid}, {$tranpos}, '{$trantype}', {$tran_company_id}, {$tran_shop_id},
                      {$tran_amount}, {$tran_amount_used}, 0, {$tranpartid}, {$tran_refid}, {$currtme}, {$_SESSION["user_id"]})";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            if($cloneToSthChange)
            {
               $sql = " select *
                        from tran_charges
                        where
                        id = {$tran_refid}";
               $refcharge = $CON->select($sql);
               $refcharge = $refcharge[0];

               //----------------------------------------------------------------------------------
               $sql = " select *
                        from storehousechanges
                        where
                        id  = {$tranid}";
               $sthheader = $CON->select($sql);
               $sthheader = $sthheader[0];

               //----------------------------------------------------------------------------------
               $sql = " select *
                        from storehousechanges_items
                        where
                        strc_id  = {$tranid} and
                        item_pos = {$tranpos}";
               $sthpos = $CON->select($sql);
               $sthpos = $sthpos[0];

               //----------------------------------------------------------------------------------
               $sql = " insert into tran_charges
                        (tran_id, tran_pos, tran_type, tran_charge_number, tran_company_id, tran_shop_id, tran_st_id,
                         item_id, item_type, tran_amount, tran_item_id, tran_amount_avail, tran_prod_date, tran_expire_date, tran_booked,
                         tran_partid, tran_crtdat, tran_crtusr)
                        VALUES
                        ({$tranid}, {$tranpos}, 'sthup', '{$refcharge["tran_charge_number"]}',
                         {$sthheader["strc_company_dest_id"]}, {$sthheader["strc_shop_dest_id"]}, {$sthpos["item_st_dest_id"]},
                         {$sthpos["item_id"]}, '{$sthpos["item_type"]}',
                         {$tran_amount}, {$refcharge["tran_item_id"]}, {$tran_amount_used}, {$refcharge["tran_prod_date"]},
                         {$refcharge["tran_expire_date"]}, 0, 0, {$currtme}, {$_SESSION["user_id"]})";
               $CON->no_result($sql);
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
function getItemChargeTrans($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select t1.*, t2.st_name
            from tran_charges t1
            LEFT OUTER JOIN company_shops_storehouses t2 ON t1.tran_st_id = t2.id
            where
            t1.tran_id     = {$tranid} and
            t1.tran_type   = '{$trantype}' and
            t1.tran_pos    = {$tranpos} ";

   if($tranpartid > 0)
      $sql .= " and tran_partid = {$tranpartid} ";
      
   $sql .= " order by t1.id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getItemChargeTransUsed($CON, $tranid, $trantype, $tranpos, $tranpartid = 0)
{
   $sql = " select t1.id, t1.tran_amount, t2.tran_charge_number, t2.tran_crtdat, t2.tran_type,
                   t2.tran_prod_date, t2.tran_expire_date, t2.tran_st_id,
                   t2.tran_item_id, t1.tran_amount_used, t1.tran_refid, t3.st_name
            from tran_charges_used t1
            INNER JOIN tran_charges t2                   ON t1.tran_refid = t2.id
            LEFT OUTER JOIN company_shops_storehouses t3 ON t2.tran_st_id = t3.id
            where
            t1.tran_id     = {$tranid} and
            t1.tran_type   = '{$trantype}' and
            t1.tran_pos    = {$tranpos} ";

   if($tranpartid > 0)
      $sql .= " and t1.tran_partid = {$tranpartid} ";
      
   $sql .= " order by t1.id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getItemProdInternItems($CON, $itemid)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod
            from prod_item_int_pos t1
            INNER JOIN item t2 ON t1.prod_item_id = t2.id
            where
            t1.item_id   = {$itemid} 
            order by 3";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getItemProdInternCosts($CON, $itemid)
{
   $sql = " select t1.*
            from prod_item_int_costs t1
            where
            t1.ipa_item_id  = {$itemid}
            order by 2";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getMoneyTypes()
{
   return Array("CLP", "EUR", "USD", "YEN");
}

//----------------------------------------------------------------------------------
function loanSetStatus($CON, $id)
{
   $sql = " select *
            from loans
            where
            id = {$id}";
   $loan = $CON->select($sql);
   $loan = $loan[0];

   $sql = " select COUNT(*) 'cc', SUM(rate_payed) 'sum'
            from loans_rates
            where
            rate_loan_id = {$id}";
   $counts = $CON->select($sql);
   $counts = $counts[0];

   if($counts["cc"] == $counts["sum"])
      $status = 3;
   else
      $status = 2;

   if($loan["loan_status"] == 1)
      $status = 1;

   $sql = " update loans
            set
            loan_status = {$status}
            where
            id = {$id}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function getCustomerNoteDiscounts($CON, $custid)
{
   $sql = " select *
            from dscsell_customer_productcats_notes
            where
            dct_cust_id = {$custid}
            order by dct_cat_id";
   $custnotedscs = $CON->select($sql);

   foreach($custnotedscs AS $row)
      $_RES[$row["dct_cat_id"]] = $row["dct_scale_discount"];

   return $_RES;
}

//----------------------------------------------------------------------------------
function getActivePromotions($CON, $shopid)
{
   $currtme = time();
   $day     = (int)date('N');

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from item_promotions t1
            INNER JOIN item_promotions_shops t2 ON t1.id = t2.prom_id
            where
            t1.prom_status    > 0 and
            t1.prom_released  = 1 and
            t1.prom_datefrom  <= {$currtme} and
            t1.prom_dateto    >= {$currtme} and
            t2.shop_id        = {$shopid}
            order by prom_name";
   $proms = $CON->select($sql);

   return $proms;
}

//----------------------------------------------------------------------------------
function checkItemPromotions($CON, $posdata, $shopid)
{
   $currtme = time();
   $day     = (int)date('N');

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from item_promotions t1
            INNER JOIN item_promotions_shops t2 ON t1.id = t2.prom_id
            where
            t1.prom_status    > 0 and
            t1.prom_released  = 1 and
            t1.prom_datefrom  <= {$currtme} and
            t1.prom_dateto    >= {$currtme} and
            t2.shop_id        = {$shopid}
            order by prom_name";
   $proms = $CON->select($sql);
   $_RES = Array();

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($proms) && $proms != false; $x++)
   {
      $row        = $proms[$x];
      $invoiceadd = true;
      $gesamount  = 0.00;

      //----------------------------------------------------------------------------------
      $sql = " select *
               from item_promotions_items t1
               where
               prom_id = {$row["id"]}";
      $promservs = $CON->select($sql);

      foreach($promservs AS $promserv)
      {
         $thisHasItems = false;
         foreach($posdata AS $invoicerow)
         {
            if(($invoicerow["item_type"] == "item" || $invoicerow["item_type"] == "itemlist") &&
                $invoicerow["item_id"] == $promserv["item_id"] && $invoicerow["item_type"] == $promserv["item_type"])
            {
               $thisHasItems = true;
               $gesamount += $invoicerow["item_amount"];
            }
         }
         if(!$thisHasItems)
            $invoiceadd = false;
      }
      if($gesamount < $proms[$x]["prom_item_amount"])
         $invoiceadd = false;

      if($invoiceadd)
         $_RES[] = $proms[$x];
   }
   return $_RES;
}

//----------------------------------------------------------------------------------
function copyRelItemsToObject($CON, $tran_id, $tran_type, $tran_id_dest, $tran_type_dest)
{
   $sql = " delete from tran_rel_items
            where
            tran_id     = {$tran_id_dest} and
            tran_type   = '{$tran_type_dest}'";
   $CON->no_result($sql);
   
   $sql = " select *
            from tran_rel_items
            where
            tran_id     = {$tran_id} and
            tran_type   = '{$tran_type}'";
   $relconfigs = $CON->select($sql);

   for($x = 0; $x < count($relconfigs) && $relconfigs != false; $x++)
   {
      $sql = " insert into tran_rel_items
               (tran_id, tran_item_id, tran_type, tran_amount, tran_st_id)
               VALUES
               ({$tran_id_dest}, {$relconfigs[$x]["tran_item_id"]}, '{$tran_type_dest}',
                {$relconfigs[$x]["tran_amount"]}, {$relconfigs[$x]["tran_st_id"]})";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function getObjectItemRelItems($CON, $tran_id, $tran_type)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod
            from tran_rel_items t1
            INNER JOIN item t2 ON t1.tran_item_id = t2.id
            where
            t1.tran_id     = {$tran_id} and
            t1.tran_type   = '{$tran_type}'
            order by tran_item_pos";
   $relitems = $CON->select($sql);

   return $relitems;
}

//----------------------------------------------------------------------------------
function clearRelItems($CON, $tran_id, $tran_type)
{
   $sql = " delete from tran_rel_items
            where
            tran_id     = {$tran_id} and
            tran_type   = '{$tran_type}'";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function addRelItem($CON, $tran_id, $tran_type, $item_id, $item_amount, $st_id, $pos)
{
   $item_amount = getPrice($item_amount);
   $sql = " insert into tran_rel_items
            (tran_id, tran_item_id, tran_type, tran_amount, tran_st_id, tran_item_pos)
            VALUES
            ({$tran_id}, {$item_id}, '{$tran_type}',
             {$item_amount}, {$st_id}, {$pos})";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function printRelItems($CON, $tran_id, $tran_type, $shopid, $status)
{
   //----------------------------------------------------------------------------------
   $posdata = getObjectItemRelItems($CON, $tran_id, $tran_type);
   
   $rowcount = 0;
   if($posdata != false && count($posdata))
      $rowcount = count($posdata);
   $rowcount += 3;

   if((int)$status > 1 && $posdata != false && count($posdata))
      $rowcount = count($posdata);

   if((int)$status > 1)
   {
      $rdlo       = "readonly";
      $dabl       = "disabled";
   }
   if($tran_type == "ordersdelivery")
      $link = "orders_delivery";
   else
      $link = "invoices_sell";
   //----------------------------------------------------------------------------------
   if($rowcount > 0)
   {  ?>
      <?=Nifty_printH("box1", "980",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="85">
         <col width="28">
         <col width="375">
         <col width="80">
         <col>
      </colgroup>
      <tr>
         <td colspan="6" class="content_tbl_header" style="background-color:#6E3333">Salida de artï¿½culos relacionados</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Nï¿½mero</td>
         <td class="content_tbl_subheader">Act.</td>
         <td class="content_tbl_subheader">Artï¿½culo</td>
         <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
         <td class="content_tbl_subheader" align="right">Cantidad</td>
         <td class="content_tbl_subheader">Bodega</td>
      </tr>
      <?php
      $xcounter = 9000;
      global $_FIELDREGS;
      for($x = 0; $x < $rowcount; $x++)
      {
         if(!$_FIELDREGS[$xcounter]) $_FIELDREGS[$xcounter] = Array(); $_FIELDREGS[$xcounter][] = "xf_search_{$xcounter}";
         if(!$_FIELDREGS[$xcounter]) $_FIELDREGS[$xcounter] = Array(); $_FIELDREGS[$xcounter][] = "item_id_{$xcounter}";
         if(!$_FIELDREGS[$xcounter]) $_FIELDREGS[$xcounter] = Array(); $_FIELDREGS[$xcounter][] = "tran_amount_{$xcounter}";
         if(!$_FIELDREGS[$xcounter]) $_FIELDREGS[$xcounter] = Array(); $_FIELDREGS[$xcounter][] = "item_stid_{$xcounter}";
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row" valign="top">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
                  <td class="content_row_clear">
                     <input type="text" class="text" style="width:60px" name="xf_search_<?=$xcounter?>" id="xf_search_<?=$xcounter?>"
                     onfocus="markfield(this,0)" <?=$rdlo?> autocomplete="off"
                     <?php

                     if(!(int)$posdata[$x]["tran_item_id"])
                     {  ?>
                        onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/<?=$link?>/searchitem.php?storehousemode=1&rowcount=<?=$xcounter?>&dlvid=<?=$tran_id?>&id=<?=$tran_id?>&search=' +this.value;} this.value='';"
                        onkeyup="detectEvent(event, '<?=$x?>', '<?=$tran_id?>')"
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
               if((int)$posdata[$x]["tran_item_id"])
               {  ?>
                  <input type="button" class="buttonred" value="x" style="width:20px;<?php if($dabl != "") echo "display:none" ?>"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="if(askDel('')) { document.form_shppos.tran_amount_<?=$xcounter?>.value='0'; submitForm(document.form_shppos); }">
                  <?php
                  if($dabl != "")
                     echo "&nbsp;";
               }
               else
               {  ?>
                  &nbsp;
                  <?php
               }
               ?>
            </td>
            <td class="content_row" valign="top">
               <select class="text" style="width:375px;"
               name="item_id_<?=$xcounter?>" id="item_id_<?=$xcounter?>"
               onmousedown="markfield(this,0)"
               onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
               onfocus="<?php if(!(int)$posdata[$x]["tran_item_id"]) echo "addSelStyle(this);" ?>"
               onchange="setItemInfosOrderDelivery('<?=$xcounter?>', this.value)">
                  <?php
                  if((int)$posdata[$x]["tran_item_id"])
                  {
                     $desc = trim(addslashes($posdata[$x]["item_title"]));
                     ?>
                     <option value="<?=$posdata[$x]["tran_item_id"]?>#item"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_row" valign="top" align="center">
               <?php
               if((int)$posdata[$x]["tran_item_id"])
                  echo getItemUnitDesc($CON, $posdata[$x]["tran_item_id"], "item");
               echo "&nbsp;";
               ?>
            </td>
            <td class="content_row" align="right" valign="top">
               <input type="text" class="text" style="width:50px;text-align:right;"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
               name="tran_amount_<?=$xcounter?>" id="tran_amount_<?=$xcounter?>" <?=$rdlo?>
               value="<?php if((int)$posdata[$x]["tran_item_id"]) echo printPrice($posdata[$x]["tran_amount"],2)?>">
               <input type="hidden" name="item_sellprice_netto_<?=$xcounter?>" id="item_sellprice_netto_<?=$xcounter?>">
               <input type="hidden" name="item_sellprice_taxes_perc_<?=$xcounter?>" id="item_sellprice_taxes_perc_<?=$xcounter?>">
            </td>
            <td class="content_row" valign="top">
               <select class="text" style="width:120px" name="item_stid_<?=$xcounter?>" id="item_stid_<?=$xcounter?>"
               onblur="markfield(this,1);removeSelStyle(this);"
               onfocus="addSelStyle(this);"
               onmousedown="markfield(this,0)">
                  <?php
                  if((int)$posdata[$x]["tran_item_id"])
                  {
                     $itemsts = getItemStorehouses($CON, $shopid, $posdata[$x]["tran_item_id"], "item");
                     if(count($itemsts))
                     {
                        foreach(array_keys($itemsts) AS $itemstid)
                        {
                           if($rdlo == "" || ($rdlo != "" && $posdata[$x]["tran_st_id"] == $itemstid))
                           {
                              $currstock = getItemShopStorehouseCurrentStock($CON, $shopid, $itemstid, $posdata[$x]["tran_item_id"], "item", true);
                              ?>
                              <option value="<?=$itemstid?>"
                              <?php if($posdata[$x]["tran_st_id"] == $itemstid) echo "selected"?>>
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

         </tr>
         <?php
         $xcounter++;
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
   }
}

//----------------------------------------------------------------------------------
function getGuaranteeItems($CON, $thisid)
{
   $sql = " select t2.*, t3.item_title, t3.item_number_prod, t4.guat_name, t4.guat_note_act, t4.guat_dlv_act
            from guarantee_items t2
            LEFT OUTER JOIN item            t3 ON t2.item_id     = t3.id
            LEFT OUTER JOIN guarantee_types t4 ON t2.item_guatid = t4.id
            where
            t2.gua_id      = {$thisid} and
            t2.item_type   = 'item'
            UNION ALL
            select t2.*, t3.item_title, t3.item_number_prod, t4.guat_name, t4.guat_note_act, t4.guat_dlv_act
            from guarantee_items t2
            LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
            LEFT OUTER JOIN guarantee_types t4 ON t2.item_guatid = t4.id
            where
            t2.gua_id      = {$thisid} and
            t2.item_type   = 'itemlist'
            UNION ALL
            select t2.*, t2.item_desc 'item_title', '', t4.guat_name, t4.guat_note_act, t4.guat_dlv_act
            from guarantee_items t2
            LEFT OUTER JOIN guarantee_types t4 ON t2.item_guatid = t4.id
            where
            t2.gua_id      = {$thisid} and
            t2.item_type   = 'manual'
            order by 3";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getGuaranteeTypes($CON)
{
   $sql = " select *
            from guarantee_types
            where
            guat_status = 1
            order by guat_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function reformatProdNumber($val)
{
   //if(trim($val) != "")
   //   return substr($val, 0, 3)."-".substr($val, 3);
   return trim($val);
}

//----------------------------------------------------------------------------------
function getTranPayments($CON, $tranid, $trantype, $payedonly = false)
{
   $sql = " select t1.*, t1.pay_payed 'adm_deposit_status', t1.pay_date 'adm_deposit_status_date'
            from tran_payments t1
            LEFT OUTER JOIN adm_deposit t2 ON t1.pay_depid = t2.id
            where
            t1.pay_tran_type  = '{$trantype}' and
            t1.pay_tran_id    = {$tranid}
            order by t2.adm_paydate asc, t1.id asc";
   $ret = $CON->select($sql);

   if(!$payedonly)
      return $ret;
   else
   {
      $gespayed = 0.00;
      foreach($ret AS $row)
      {
         if((int)$row["pay_payed"])
            $gespayed += $row["pay_brutto"];
      }
      return $gespayed;
   }
}

//----------------------------------------------------------------------------------
function getCustomerPLPrice($CON, $custid, $itemid, $itemtype, $useplid = 0, $req_plptype = 0, $forceplid = false)
{
   if((int)$useplid)
   {
      if((int)$req_plptype)
      {
         $sql = " select t3.item_sellprice_netto2 'item_sellprice_netto',
                         t3.item_sellprice_taxes2 'item_sellprice_taxes',
                         t3.item_sellprice_taxes_perc2 'item_sellprice_taxes_perc',
                         t3.item_sellprice_brutto2 'item_sellprice_brutto',
                         t3.item_id
                  from price_lists t2
                  INNER JOIN price_lists_items t3  ON t2.id = t3.pl_id
                  where
                  t2.id          = {$useplid} and
                  t3.item_id     = {$itemid} and
                  t3.item_type   = '{$itemtype}'";
         $custprice = $CON->select($sql);
      }
      else
      {
         $sql = " select t3.item_sellprice_netto, t3.item_sellprice_taxes, t3.item_sellprice_taxes_perc,
                         t3.item_sellprice_brutto, t3.item_id
                  from price_lists t2
                  INNER JOIN price_lists_items t3  ON t2.id = t3.pl_id
                  where
                  t2.id          = {$useplid} and
                  t3.item_id     = {$itemid} and
                  t3.item_type   = '{$itemtype}'";
         $custprice = $CON->select($sql);
      }
   }
   else
   {
      if(!$forceplid)
      {
         $sql = " select t3.item_sellprice_netto, t3.item_sellprice_taxes, t3.item_sellprice_taxes_perc,
                         t3.item_sellprice_brutto, t3.item_id
                  from customer t1
                  INNER JOIN price_lists t2        ON t1.cust_plid = t2.id
                  INNER JOIN price_lists_items t3  ON t2.id = t3.pl_id
                  where
                  t1.id          = {$custid} and
                  t2.pl_status   = 1 and
                  t3.item_id     = {$itemid} and
                  t3.item_type   = '{$itemtype}'";
         $custprice = $CON->select($sql);
      }
   }
   return $custprice[0];
}

//----------------------------------------------------------------------------------
//NEW FUNCTION AGREGA RELACION DE RECUENTA A AJUSTE DE STOCK
//----------------------------------------------------------------------------------
function createStockChangesFromStockcount($CON, $stcid, $stclistpos)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts
            where
            id  = {$stcid}";
   $stc = $CON->select($sql);
   $stc = $stc[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from stockcounts_lists t1
            where
            t1.stc_id  = {$stcid} and
            t1.lst_pos = {$stclistpos}";
   $xstclists = $CON->select($sql);
   $xstclists = $xstclists[0];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts_lists_items
            where
            stc_id            = {$stcid} and
            stc_lst_posid     = {$stclistpos}";
   $items = $CON->select($sql);

   //----------------------------------------------------------------------------------
   foreach($items AS $item)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_average_costprices
               where
               item_id     = {$item["item_id"]} and
               company_id  = {$stc["stc_companyid"]}";
      $currentavgcost = $CON->select($sql);
      $currentavgcost = $currentavgcost[0];
      $item_costprice_netto = (float)$item["item_costprice_avg_netto"];

      //----------------------------------------------------------------------------------
      if($item_costprice_netto > 0.00)
      {
         if((int)$currentavgcost["item_id"])
         {
            if((int)$xstclists["lst_pppupdate"])
            {
               /*
               $sql = " update tran_average_costprices
                        set
                        item_costprice_avg_netto = {$item_costprice_netto}
                        where
                        item_id     = {$item["item_id"]} and
                        company_id  = {$stc["stc_companyid"]}";
               $CON->no_result($sql);

               if((int)$xstclists["lst_date"])
                  $logdate = $xstclists["lst_date"];
               else
                  $logdate = $stc["stc_bookdate"];

               $sql = " insert into tran_average_costprices_log
                        (item_id, company_id, log_crtdat, tran_type, tran_id, item_costprice_avg_netto)
                        VALUES
                        ({$item["item_id"]}, {$stc["stc_companyid"]}, {$logdate}, 'stockcount', {$stc["id"]}, {$item_costprice_netto})";
               $CON->no_result($sql);
               */
            }
         }
         else
         {
            /*
            $sql = " insert into tran_average_costprices
                     (item_id, company_id, item_costprice_avg_netto)
                     VALUES
                     ({$item["item_id"]}, {$stc["stc_companyid"]}, {$item_costprice_netto})";
            $CON->no_result($sql);

            if((int)$xstclists["lst_date"])
               $logdate = $xstclists["lst_date"];
            else
               $logdate = $stc["stc_bookdate"];

            $sql = " insert into tran_average_costprices_log
                     (item_id, company_id, log_crtdat, tran_type, tran_id, item_costprice_avg_netto)
                     VALUES
                     ({$item["item_id"]}, {$stc["stc_companyid"]}, {$logdate}, 'stockcount', {$stc["id"]}, {$item_costprice_netto})";
            $CON->no_result($sql);
            */
         }
      }
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts_lists_items
            where
            stc_id            = {$stcid} and
            stc_lst_posid     = {$stclistpos} and
            item_amount_book != 0";
   $items = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   $negcounter = 0;
   foreach($items AS $item)
   {
      if($item["item_amount_book"] > 0.00)
      {
         $positemarr[$poscounter] = $item;
         $poscounter++;
      }
      else
      {
         $item["item_amount_book"]  = $item["item_amount_book"] * -1;
         $negitemarr[$negcounter]   = $item;
         $negcounter++;
      }
   }

   //----------------------------------------------------------------------------------
   for($x = 0; $x <= 1; $x++)
   {
      //----------------------------------------------------------------------------------
      if($x == 0)
      {
         $itemarr = $positemarr;
         $issueid = 14;
         $stk_negative = 0;
      }
      else
      {
         $itemarr = $negitemarr;
         $issueid = 13;
         $stk_negative = 1;
      }
         
      //----------------------------------------------------------------------------------
      if(is_array($itemarr) && count($itemarr))
      {
         //----------------------------------------------------------------------------------
         $stk_annotation   = "Recuento inventario: {$stc["stc_num"]}";
         $stk_bookdate     = (int)$stc["stc_bookdate"];
         $stk_num          = createTransactionNumber($CON, $stc["stc_companyid"], "stockchange");

         if((int)$xstclists["lst_date"])
            $stk_bookdate = $xstclists["lst_date"];

         $stockchange_stc_id  = (int)$stcid;
         $stockchange_lst_pos = (int)$stclistpos;

         //----------------------------------------------------------------------------------
         $sql = " insert into stockchanges
                  (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                   stk_negative, stockchange_stc_id, stockchange_lst_pos, stk_crtdat, stk_crtusr)
                  VALUES
                  ('{$stk_num}', '{$stk_annotation}', {$issueid}, {$stc["stc_companyid"]}, {$stc["stc_shopid"]},
                    {$stk_bookdate}, {$stk_negative}, {$stockchange_stc_id}, {$stockchange_lst_pos},
                    {$currtme}, {$_SESSION["user_id"]})";
         $res = $CON->no_result($sql);
         //----------------------------------------------------------------------------------
         $sql = " select MAX(id) 'thisid'
                  from stockchanges
                  where
                  stk_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["thisid"];

         $insertcounter = 0;
         foreach($itemarr AS $positem)
         {
            $costdata                  = getSupplierItemCosts($CON, 0, $positem["item_id"], "item");
            $item_costprice_brutto     = 0.00;
            $item_costprice_taxes_perc = 0.00;
            $item_costprice_netto      = (float)$positem["item_costprice_avg_netto"];
            $item_costprice_taxes      = 0.00;
            
            $sql = " insert into stockchanges_items
                     (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                     VALUES
                     ({$thisid}, {$positem["item_id"]}, {$insertcounter}, {$positem["item_amount_book"]}, 'item',
                      {$positem["item_stid"]}, {$item_costprice_brutto}, {$item_costprice_taxes_perc},
                      {$item_costprice_netto}, {$item_costprice_taxes})";
            $CON->no_result($sql);

            $insertcounter++;
         }

         bookStockChange($CON, $thisid);
      }
   }

   //----------------------------------------------------------------------------------
   /*
   $sql = " update stockcounts_lists
            set lst_status = 1
            where
            stc_id   = {$stcid} and
            lst_pos  = {$stclistpos}";
   $CON->no_result($sql);
   */
}

//----------------------------------------------------------------------------------
function getMoneyExchangeRate($CON, $datestr)
{
   $dstart  = getDateFromString($datestr);
   $dend    = getDateFromString($datestr, false);

   $sql = " select exc_usdval
            from money_exchange
            where
            exc_tstamp <= {$dend}
            order by exc_tstamp desc
            LIMIT 0,1";
   $usdval = $CON->select($sql);
   $usdval = $usdval[0]["exc_usdval"];

   return $usdval;
}

//----------------------------------------------------------------------------------
function getInvoiceBuyNoteItems($CON, $noteid)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note
            from invoices_notes_buy_items t1
            INNER JOIN item t2 ON t1.item_id = t2.id
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note
            from invoices_notes_buy_items t1
            INNER JOIN itemlist t2 ON t1.item_id = t2.id
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' ''
            from invoices_notes_buy_items t1
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'manual'
            order by 3";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getInvoiceSellNoteItems($CON, $noteid, $setPosOrder = "")
{
   $orderBy = "3";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";

   $sql = " select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id,
                   t5.item_costprice_netto, t2.item_weight, t2.item_weight_price, t5.item_code, t2.item_sell_nodsc,
                   t2.item_sell_amountmin, t9.cat_dsc_off, t9.cat_dsc_maxperc, t9.cat_sellprice_min
            from invoices_notes_sell_items t1
            INNER JOIN item t2 ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id,
                   t5.item_costprice_netto, t2.item_weight, t2.item_weight_price, t5.item_code, t2.item_sell_nodsc,
                   t2.item_sell_amountmin, t9.cat_dsc_off, t9.cat_dsc_maxperc, t9.cat_sellprice_min
            from invoices_notes_sell_items t1
            INNER JOIN itemlist t2 ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', '' '', '' '', '' '', '' '', '' '', '' '', 0, 0, 0, 0, 0
            from invoices_notes_sell_items t1
            where
            t1.note_id  = {$noteid} and
            t1.item_type   = 'manual'
            order by {$orderBy} ";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"])."-";
      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"])."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"])."-";
      if((int)$row["item_value_act"] && (float)$row["item_value_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_value_dsc"])."-";

         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }

   return $posdata;
}

//----------------------------------------------------------------------------------
function getInvoiceBuyPartsItems($CON, $invcid, $partid)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note, t4.item_code
            from invoices_buy_parts_items t1
            INNER JOIN item t2                  ON t1.item_id = t2.id
            LEFT OUTER JOIN invoices_buy t3     ON t1.invc_id = t3.id
            LEFT OUTER JOIN item_suppliers t4   ON ( t1.item_id =  t4.item_id and t4.supplier_id = t3.invc_supplier_id )
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note, t4.item_code
            from invoices_buy_parts_items t1
            INNER JOIN itemlist t2                 ON t1.item_id = t2.id
            LEFT OUTER JOIN invoices_buy t3        ON t1.invc_id = t3.id
            LEFT OUTER JOIN itemlist_suppliers t4  ON ( t1.item_id =  t4.item_id and t4.supplier_id = t3.invc_supplier_id )
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', '' ''
            from invoices_buy_parts_items t1
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'manual'
            order by 4";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getInvoiceSellPartsItems($CON, $invcid, $partid, $setPosOrder = "", $tblprefix = "")
{
   $orderBy = "4";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";
      
   $sql = " select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id, t5.item_costprice_netto,
                   t2.item_weight, t2.item_weight_price, t7.item_amount 'order_amount', t7.item_amount_shipped 'order_amount_shipped',
                   t5.item_code, t2.item_sell_withotheritems, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t7.item_sellprice_netto 'item_sellprice_netto_orig',
                   t7.item_amount_shipped_stop, t7.item_amount_shipped_comment, t2.item_factorshop
            from invoices_sell{$tblprefix}_parts_items t1
            INNER JOIN item t2                           ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN invoices_sell{$tblprefix}_parts t6       ON ( t1.invc_id = t6.part_invc_id and t1.part_id = t6.id )
            LEFT OUTER JOIN orders_items t7              ON ( t6.part_req_id = t7.req_id and t7.item_pos = t1.item_order_pos and
                                                              t7.item_id = t1.item_id and t7.item_type = t1.item_type)
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id, t5.item_costprice_netto,
                   t2.item_weight, t2.item_weight_price, t7.item_amount 'order_amount', t7.item_amount_shipped 'order_amount_shipped',
                   t5.item_code, t2.item_sell_withotheritems, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t7.item_sellprice_netto 'item_sellprice_netto_orig',
                   t7.item_amount_shipped_stop, t7.item_amount_shipped_comment, '' ''
            from invoices_sell{$tblprefix}_parts_items t1
            INNER JOIN itemlist t2                       ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN invoices_sell{$tblprefix}_parts t6       ON ( t1.invc_id = t6.part_invc_id and t1.part_id = t6.id )
            LEFT OUTER JOIN orders_items t7              ON ( t6.part_req_id = t7.req_id and t7.item_pos = t1.item_order_pos and
                                                              t7.item_id = t1.item_id and t7.item_type = t1.item_type)
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', '' '', '' '', '' '', '' '', '' '',
                   t7.item_amount 'order_amount', t7.item_amount_shipped 'order_amount_shipped', '' '', 0,
                   0, 0, 0, 0, 0, 0, 0, '' '', '' ''
            from invoices_sell{$tblprefix}_parts_items t1
            LEFT OUTER JOIN invoices_sell{$tblprefix}_parts t6       ON ( t1.invc_id = t6.part_invc_id and t1.part_id = t6.id )
            LEFT OUTER JOIN orders_items t7              ON ( t6.part_req_id = t7.req_id and t7.item_pos = t1.item_order_pos and
                                                              t7.item_id = t1.item_id and t7.item_type = t1.item_type)
            where
            t1.invc_id = {$invcid} and
            t1.part_id = {$partid} and
            t1.item_type   = 'manual'
            order by {$orderBy} ";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"])."-";
      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"])."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"])."-";
      if((int)$row["item_value_act"] && (float)$row["item_value_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_value_dsc"])."-";

         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }

   return $posdata;
}

//----------------------------------------------------------------------------------
function getInvoiceBuyParts($CON, $invcid)
{
   $sql = " select t1.*, t2.shp_num, t2.shp_supplier_docnum, t3.sord_number
            from invoices_buy_parts t1
            LEFT OUTER JOIN shipment t2         ON t1.part_shp_id = t2.id
            LEFT OUTER JOIN supplier_order t3   ON t1.part_sord_id = t3.id
            where
            t1.part_invc_id = {$invcid}
            order by t1.id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getInvoiceSellParts($CON, $invcid, $tblprefix = "")
{
   $sql = " select t1.*, t2.dlv_num, t2.dlv_docnum, t3.req_number, t2.dlv_delivery_date
            from invoices_sell{$tblprefix}_parts t1
            LEFT OUTER JOIN orders_delivery t2  ON t1.part_dlv_id = t2.id
            LEFT OUTER JOIN orders t3           ON t1.part_req_id = t3.id
            where
            t1.part_invc_id = {$invcid}
            order by t1.id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function invoiceBuyAddShipment($CON, $invcid, $shpid)
{
   $currtme = time();
   //$sql = " delete from invoices_buy_parts where part_invc_id = {$invcid} and part_shp_id = {$shpid}"; $CON->no_result($sql);
   
   $sql = " select count(*) 'cc'
            from invoices_buy_parts
            where
            part_invc_id   = {$invcid} and
            part_shp_id     = {$shpid}";
   $check = $CON->select($sql);

   if(!(int)$check[0]["cc"])
   {
      $sql = " select *
               from shipment
               where
               id = {$shpid}";
      $shipment = $CON->select($sql);
      $shipment = $shipment[0];

      if((int)$shipment["shp_supporder_id"])
         invoiceBuyAddSupplierOrder($CON, $invcid, $shipment["shp_supporder_id"], $shpid);
      else
      {
         //----------------------------------------------------------------------------------
         $sql = " insert into invoices_buy_parts
                  (part_invc_id, part_crtdat, part_crtusr, part_shp_id, part_sord_id)
                  VALUES
                  ({$invcid}, {$currtme}, {$_SESSION["user_id"]}, {$shpid}, 0)";
         $CON->no_result($sql);

         //----------------------------------------------------------------------------------
         $sql = " select MAX(id) 'id'
                  from invoices_buy_parts
                  where
                  part_invc_id   = {$invcid} and
                  part_crtusr    = {$_SESSION["user_id"]}";
         $partid = $CON->select($sql);
         $partid = $partid[0]["id"];

         //----------------------------------------------------------------------------------
         $posdata = getShipmentPos($CON, $shpid);
         foreach($posdata AS $posrow)
         {
            $costdata = getSupplierItemCosts($CON, $shipment["shp_supplier_id"], $posrow["item_id"], $posrow["item_type"]);
            $posrow["item_costprice_brutto"]       = (float)$costdata["item_costprice_brutto"];
            $posrow["item_costprice_taxes_perc"]   = (float)$costdata["item_costprice_taxes_perc"];
            $posrow["item_costprice_netto"]        = (float)$costdata["item_costprice_netto"];
            $posrow["item_costprice_taxes"]        = (float)$costdata["item_costprice_taxes"];
            $posrow["item_desc"]                   = addslashes($posrow["item_desc"]);
            $posrow["item_costprice_netto_dsc"]    = (float)$posrow["item_amount_shipped"] * $posrow["item_costprice_netto"];

            $posrow["item_subitem_id"]             = (int)$posrow["item_subitem_id"];
            $posrow["item_subitem_amount"]         = (float)$posrow["item_subitem_amount"];

            $item_charges_act = itemHasChargeAct($CON, $posrow["item_id"], $posrow["item_type"]);
            
            $sql = " insert into invoices_buy_parts_items
                     (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_discount,
                      item_costprice_netto_dsc, item_desc, item_charges_act, item_subitem_id, item_subitem_amount)
                     VALUES
                     ({$invcid}, {$partid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$posrow["item_amount_shipped"]},
                      '{$posrow["item_type"]}', {$posrow["item_costprice_brutto"]}, {$posrow["item_costprice_taxes_perc"]},
                      {$posrow["item_costprice_netto"]}, {$posrow["item_costprice_taxes"]}, 0.00,
                      {$posrow["item_costprice_netto_dsc"]}, '{$posrow["item_desc"]}', {$item_charges_act},
                      {$posrow["item_subitem_id"]}, {$posrow["item_subitem_amount"]})";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   recalcInvoiceBuy($CON, $invcid);
}

//----------------------------------------------------------------------------------
function invoiceSellAddDelivery($CON, $invcid, $dlvid)
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell
            where
            id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from invoices_sell_parts
            where
            part_invc_id   = {$invcid} and
            part_dlv_id    = {$dlvid}";
   $check = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if(!(int)$check[0]["cc"])
   {
      $sql = " select t1.*, t2.req_userid_seller, t2.req_userid_cashing
               from orders_delivery t1
               LEFT OUTER JOIN orders t2 ON t1.dlv_order_id = t2.id
               where
               t1.id = {$dlvid}";
      $shipment = $CON->select($sql);
      $shipment = $shipment[0];

      //----------------------------------------------------------------------------------
      $shipment["req_userid_seller"]   = (int)$shipment["req_userid_seller"];
      $shipment["req_userid_cashing"]  = (int)$shipment["req_userid_cashing"];

      if($shipment["req_userid_seller"] == 0 || $shipment["req_userid_cashing"] == 0)
      {
         $sql = " select t1.cust_sellerid
                  from customer t1
                  where
                  t1.id = {$shipment["dlv_cust_id"]}";
         $custdata = $CON->select($sql);
         $custdata = $custdata[0];
         
         $shipment["req_userid_seller"]   = (int)$custdata["cust_sellerid"];
         $shipment["req_userid_cashing"]  = (int)$custdata["cust_sellerid"];
      }

      $shipment["dlv_oc_number"]          = trim(addslashes($shipment["dlv_oc_number"]));
      $shipment["dlv_annotation_intern"]  = trim(addslashes($shipment["dlv_annotation_intern"]));
      $shipment["dlv_annotation"]         = trim(addslashes($shipment["dlv_annotation"]));
      $shipment["dlv_oc_dat"]             = (int)$shipment["dlv_oc_dat"];
      $shipment["dlv_cust_delivid"]       = (int)$shipment["dlv_cust_delivid"];
      $shipment["dlv_bultos"]             = (int)$shipment["dlv_bultos"];
      $invc_discount_perc                 = (float)$shipment["dlv_discount_perc"];
      $invc_discount_amt                  = (float)$shipment["dlv_discount_amt"];
      
      $sql = " update invoices_sell
               set
               invc_paymentid          = {$shipment["dlv_paymentid"]},
               invc_transportid        = {$shipment["dlv_transportid"]},
               invc_cust_delivid       = {$shipment["dlv_cust_delivid"]},
               invc_userid_seller      = {$shipment["req_userid_seller"]},
               invc_userid_cashing     = {$shipment["req_userid_cashing"]},
               invc_oc_number          = '{$shipment["dlv_oc_number"]}',
               invc_oc_dat             = {$shipment["dlv_oc_dat"]},
               invc_desc_intern        = '{$shipment["dlv_annotation_intern"]}',
               invc_desc               = '{$shipment["dlv_annotation"]}',
               invc_bultos             = {$shipment["dlv_bultos"]},
               invc_weightprice_netto  = invc_weightprice_netto + {$shipment["dlv_weightprice_netto"]},
               invc_discount_perc      = {$invc_discount_perc},
               invc_discount_amt       = invc_discount_amt + {$invc_discount_amt}
               where
               id = {$invcid}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      if((int)$shipment["dlv_copydateinvc"])
      {
         $sql = " update invoices_sell
                  set
                  invc_date = {$shipment["dlv_delivery_date"]}
                  where
                  id = {$invcid}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      if($headdata["invc_paymentid"] != $shipment["dlv_paymentid"])
      {
         $sql = " select pay_days
                  from payments
                  where
                  id = {$shipment["dlv_paymentid"]}";
         $paydays = $CON->select($sql);
         $paydays = (int)$paydays[0]["pay_days"];

         if($paydays == 0)
            $invc_estpay_date = $headdata["invc_receipt_date"];
         else
            $invc_estpay_date = $headdata["invc_receipt_date"] + (86400 * $paydays);

         //----------------------------------------------------------------------------------
         $sql = " update invoices_sell
                  set
                  invc_estpay_date = {$invc_estpay_date}
                  where
                  id = {$invcid}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " insert into invoices_sell_parts
               (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
               VALUES
               ({$invcid}, {$currtme}, {$_SESSION["user_id"]}, {$dlvid}, 0)";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select MAX(id) 'id'
               from invoices_sell_parts
               where
               part_invc_id   = {$invcid} and
               part_crtusr    = {$_SESSION["user_id"]}";
      $partid = $CON->select($sql);
      $partid = $partid[0]["id"];

      //----------------------------------------------------------------------------------
      $posdata = getOrderDeliveryPos($CON, $dlvid);

      foreach($posdata AS $posrow)
      {
         $posrow["item_desc"] = addslashes($posrow["item_desc"]);
         $item_charges_act = itemHasChargeAct($CON, $posrow["item_id"], $posrow["item_type"]);
         
         $sql = " insert into invoices_sell_parts_items
                  (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                  item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_discount, item_discount_type,
                  item_pcat_dsc_act, item_pcat_dsc1, item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3,
                  item_pcat_dsctype3, item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype,
                  item_value_act, item_value_dsc, item_value_dsctype, item_order_pos, item_dlv_pos, item_desc, item_promid, item_promdsc,
                  item_charges_act)
                  VALUES
                  ({$invcid}, {$partid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$posrow["item_amount_shipped"]},
                   '{$posrow["item_type"]}', {$posrow["item_sellprice_brutto"]}, {$posrow["item_sellprice_taxes_perc"]},
                   {$posrow["item_sellprice_netto"]}, {$posrow["item_sellprice_netto_dsc"]}, {$posrow["item_sellprice_taxes"]},
                   {$posrow["item_discount"]}, {$posrow["item_discount_type"]}, {$posrow["item_pcat_dsc_act"]},
                   {$posrow["item_pcat_dsc1"]}, {$posrow["item_pcat_dsctype1"]}, {$posrow["item_pcat_dsc2"]}, {$posrow["item_pcat_dsctype2"]},
                   {$posrow["item_pcat_dsc3"]}, {$posrow["item_pcat_dsctype3"]}, {$posrow["item_pcat_dsc4"]}, {$posrow["item_pcat_dsctype4"]},
                   {$posrow["item_vol_act"]}, {$posrow["item_vol_dsc"]}, {$posrow["item_vol_dsctype"]}, {$posrow["item_value_act"]},
                   {$posrow["item_value_dsc"]}, {$posrow["item_value_dsctype"]}, {$posrow["item_order_pos"]}, {$posrow["item_pos"]},
                   '{$posrow["item_desc"]}', {$posrow["item_promid"]}, {$posrow["item_promdsc"]}, {$item_charges_act})";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   recalcOrder($CON, $invcid, "INVOICE");
}

//----------------------------------------------------------------------------------
function invoiceSellAddOrder($CON, $invcid, $reqid, $noloaditems = 0, $useglobalcount = false)
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell
            where
            id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from invoices_sell_parts
            where
            part_invc_id   = {$invcid} and
            part_req_id    = {$reqid}";
   $check = $CON->select($sql);

   if(!(int)$check[0]["cc"])
   {
      $sql = " select *
               from orders 
               where
               id = {$reqid}";
      $order = $CON->select($sql);
      $order = $order[0];

      $sql = " select t1.*
               from orders_anticipos t1
               where
               t1.ant_req_id      = {$reqid} and
               t1.ant_status  = 1
               order by t1.ant_recepdate";
      $ants = $CON->select($sql);
      foreach($ants AS $ant)
      {
         $sql = " insert into tran_payments
                  (pay_tran_id, pay_tran_type, pay_paymentid, pay_brutto, pay_date, pay_payed, pay_comment)
                  VALUES
                  ({$invcid}, 'invoices_sell', {$order["req_paymentid"]}, {$ant["ant_amount"]}, {$ant["ant_recepdate"]},
                   1, 'Generado por abono NV {$order["req_number"]}')";
         $CON->no_result($sql);
      }

      $order["req_oc_number"]          = trim(addslashes($order["req_oc_number"]));
      $order["req_desc_intern"]        = trim(addslashes($order["req_desc_intern"]));
      $order["req_desc"]               = trim(addslashes($order["req_desc"]));
      $order["req_oc_dat"]             = (int)$order["req_oc_dat"];
      $order["req_cust_delivid"]       = (int)$order["req_cust_delivid"];
      $invc_discount_perc              = (float)$order["req_discount_perc"];
      $invc_discount_amt               = (float)$order["req_discount_amt"];

      //----------------------------------------------------------------------------------
      $sql = " update invoices_sell
               set
               invc_paymentid          = {$order["req_paymentid"]},
               invc_transportid        = {$order["req_transportid"]},
               invc_cust_delivid       = {$order["req_cust_delivid"]},
               invc_userid_seller      = {$order["req_userid_seller"]},
               invc_userid_cashing     = {$order["req_userid_cashing"]},
               invc_oc_number          = '{$order["req_oc_number"]}',
               invc_oc_dat             = {$order["req_oc_dat"]},
               invc_desc_intern        = '{$order["req_desc_intern"]}',
               invc_desc               = '{$order["req_desc"]}',
               invc_discount_perc      = {$invc_discount_perc},
               invc_discount_amt       = invc_discount_amt + {$invc_discount_amt}
               where
               id = {$invcid}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      if($headdata["invc_paymentid"] != $order["req_paymentid"])
      {
         $sql = " select pay_days
                  from payments
                  where
                  id = {$order["req_paymentid"]}";
         $paydays = $CON->select($sql);
         $paydays = (int)$paydays[0]["pay_days"];

         if($paydays == 0)
            $invc_estpay_date = $headdata["invc_receipt_date"];
         else
            $invc_estpay_date = $headdata["invc_receipt_date"] + (86400 * $paydays);

         //----------------------------------------------------------------------------------
         $sql = " update invoices_sell
                  set
                  invc_estpay_date = {$invc_estpay_date},
                  invc_paymentid   = {$order["req_paymentid"]}
                  where
                  id = {$invcid}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " insert into invoices_sell_parts
               (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
               VALUES
               ({$invcid}, {$currtme}, {$_SESSION["user_id"]}, 0, {$reqid})";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select MAX(id) 'id'
               from invoices_sell_parts
               where
               part_invc_id   = {$invcid} and
               part_crtusr    = {$_SESSION["user_id"]}";
      $partid = $CON->select($sql);
      $partid = $partid[0]["id"];

      //----------------------------------------------------------------------------------
      if(!(int)$noloaditems)
      {
         $posdata = getOrderPos($CON, $reqid, "prodnumber");

         foreach($posdata AS $posrow)
         {
            $posrow["item_desc"] = addslashes($posrow["item_desc"]);

            $item_charges_act = itemHasChargeAct($CON, $posrow["item_id"], $posrow["item_type"]);

            $posrow["item_order_pos"] = $posrow["item_pos"];
            if($useglobalcount)
            {
               $sql = " select IFNULL(MAX(item_pos), -1) 'item_pos'
                        from invoices_sell_parts_items
                        where
                        invc_id = {$invcid}";
               $currpos = $CON->select($sql);
               $posrow["item_pos"] = (int)$currpos[0]["item_pos"] +1;
            }

            if((float)$posrow["item_sellprice_trazado"] > 0.00 || (float)$posrow["item_sellprice_barcode"] > 0.00)
            {
               $posrow["item_discount"]            = 0;
               $posrow["item_discount_type"]       = 0;
               $posrow["item_sellprice_netto"]     += ($posrow["item_sellprice_trazado"] + $posrow["item_sellprice_barcode"]);
               $posrow["item_sellprice_taxes"]     = round($posrow["item_sellprice_netto"] / 100 * $posrow["item_sellprice_taxes_perc"]);
               $posrow["item_sellprice_brutto"]    = round($posrow["item_sellprice_netto"] + $posrow["item_sellprice_taxes"]);
               $posrow["item_sellprice_netto_dsc"] = round($posrow["item_sellprice_netto"] * $posrow["item_amount"]);
            }

            $sql = " insert into invoices_sell_parts_items
                     (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                     item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_discount, item_discount_type,
                     item_pcat_dsc_act, item_pcat_dsc1, item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3,
                     item_pcat_dsctype3, item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype,
                     item_value_act, item_value_dsc, item_value_dsctype, item_order_pos, item_desc, item_promid, item_promdsc,
                     item_charges_act)
                     VALUES
                     ({$invcid}, {$partid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$posrow["item_amount"]},
                      '{$posrow["item_type"]}', {$posrow["item_sellprice_brutto"]}, {$posrow["item_sellprice_taxes_perc"]},
                      {$posrow["item_sellprice_netto"]}, {$posrow["item_sellprice_netto_dsc"]}, {$posrow["item_sellprice_taxes"]},
                      {$posrow["item_discount"]}, {$posrow["item_discount_type"]}, {$posrow["item_pcat_dsc_act"]},
                      {$posrow["item_pcat_dsc1"]}, {$posrow["item_pcat_dsctype1"]}, {$posrow["item_pcat_dsc2"]}, {$posrow["item_pcat_dsctype2"]},
                      {$posrow["item_pcat_dsc3"]}, {$posrow["item_pcat_dsctype3"]}, {$posrow["item_pcat_dsc4"]}, {$posrow["item_pcat_dsctype4"]},
                      {$posrow["item_vol_act"]}, {$posrow["item_vol_dsc"]}, {$posrow["item_vol_dsctype"]}, {$posrow["item_value_act"]},
                      {$posrow["item_value_dsc"]}, {$posrow["item_value_dsctype"]}, {$posrow["item_order_pos"]}, '{$posrow["item_desc"]}',
                      {$posrow["item_promid"]}, {$posrow["item_promdsc"]}, {$item_charges_act})";
            $CON->no_result($sql);
            $hasitems = true;
         }

         if($hasitems && $order["req_weightprice_netto"] != 0.00)
         {
            $posrow["item_pos"]++;
            $item_sellprice_netto      = $order["req_weightprice_netto"];
            $item_sellprice_taxes      = round($item_sellprice_netto / 100 * $order["item_sellprice_taxes_perc"]);
            $item_sellprice_brutto     = $item_sellprice_netto + $item_sellprice_taxes;
            $item_sellprice_netto_dsc  = $item_sellprice_netto;
            
            $sql = " insert into invoices_sell_parts_items
                     (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                     item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_order_pos, item_desc)
                     VALUES
                     ({$invcid}, {$partid}, 9999999, {$posrow["item_pos"]}, 1,
                      'manual', {$item_sellprice_brutto}, {$posrow["item_sellprice_taxes_perc"]},
                      {$item_sellprice_netto}, {$item_sellprice_netto_dsc}, {$item_sellprice_taxes},
                      -1, 'DESPACHO')";
            $CON->no_result($sql);
         }
         
         if($hasitems && $order["req_trazlogoprice_netto"] != 0.00)
         {
            $posrow["item_pos"]++;
            $item_sellprice_netto      = $order["req_trazlogoprice_netto"];
            $item_sellprice_taxes      = round($item_sellprice_netto / 100 * $order["item_sellprice_taxes_perc"]);
            $item_sellprice_brutto     = $item_sellprice_netto + $item_sellprice_taxes;
            $item_sellprice_netto_dsc  = $item_sellprice_netto;
            
            $sql = " insert into invoices_sell_parts_items
                     (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                     item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_order_pos, item_desc)
                     VALUES
                     ({$invcid}, {$partid}, 9999999, {$posrow["item_pos"]}, 1,
                      'manual', {$item_sellprice_brutto}, {$posrow["item_sellprice_taxes_perc"]},
                      {$item_sellprice_netto}, {$item_sellprice_netto_dsc}, {$item_sellprice_taxes},
                      -1, 'TRAZADO LOGO')";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   recalcOrder($CON, $invcid, "INVOICE");
}

//----------------------------------------------------------------------------------
function invoiceBuyAddSupplierOrder($CON, $invcid, $sordid, $shpid = 0)
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_buy t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from supplier_order t1
            where
            t1.id = {$sordid}";
   $suppheaddata = $CON->select($sql);
   $suppheaddata = $suppheaddata[0];

   //----------------------------------------------------------------------------------
   $sql = " select pay_days
            from payments
            where
            id = {$headdata["invc_paymentid"]}";
   $paydays = $CON->select($sql);
   $paydays = (int)$paydays[0]["pay_days"];

   if($paydays == 0)
      $_REQUEST["invc_estpay_date"] = 0;
   else
      $_REQUEST["invc_estpay_date"] = date('d.m.Y', $headdata["invc_receipt_date"] + (86400 * $paydays));

   if($suppheaddata["sord_supplier_dsc_finance_calc"] == "OC")
      $invc_discount3 = $suppheaddata["sord_supplier_dsc_finance"];
   else
      $invc_discount3 = 0.00;

   //----------------------------------------------------------------------------------
   $sql = " update invoices_buy
            set
            invc_paymentid       = {$suppheaddata["sord_paymentid"]},
            invc_discount1       = {$suppheaddata["sord_value_dsc"]},
            invc_discount_type1  = {$suppheaddata["sord_value_dsctype"]},
            invc_discount2       = {$suppheaddata["sord_payment_dsc"]},
            invc_discount_type2  = {$suppheaddata["sord_payment_dsctype"]},
            invc_discount3       = {$invc_discount3},
            invc_discount_type3  = 0,
            invc_estpay_date     = '{$_REQUEST["invc_estpay_date"]}'
            where
            id = {$invcid}";
   $CON->no_result($sql);
   
   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_buy_parts
            (part_invc_id, part_crtdat, part_crtusr, part_shp_id, part_sord_id)
            VALUES
            ({$invcid}, {$currtme}, {$_SESSION["user_id"]}, {$shpid}, {$sordid})";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " select MAX(id) 'id'
            from invoices_buy_parts
            where
            part_invc_id   = {$invcid} and
            part_crtusr    = {$_SESSION["user_id"]}";
   $partid = $CON->select($sql);
   $partid = $partid[0]["id"];
     
   //----------------------------------------------------------------------------------
   $posdata = getSupplierOrderPos($CON, $sordid);
   foreach($posdata AS $posrow)
   {
      $addItem             = false;
      $item_supporder_pos  = -1;
      
      //----------------------------------------------------------------------------------
      if($shpid > 0)
      {
         $sql = " select *
                  from shipment_items
                  where
                  shipment_id          = {$shpid} and
                  item_supporder_pos   = {$posrow["item_pos"]} and
                  item_id              = {$posrow["item_id"]} and
                  item_type            = '{$posrow["item_type"]}'";
         $shppos = $CON->select($sql);
         $shppos = $shppos[0];

         if((int)$shppos["item_id"] && $shppos["item_amount_shipped"] > 0.00)
         {
            $item_amt   = $shppos["item_amount_shipped"];
            $netto_dsc  = $posrow["item_costprice_netto_dsc"] / $posrow["item_amount"];

            if(round($netto_dsc,2) == round($posrow["item_costprice_netto"], 2))
            {
               $netto_dsc  = 0;
               $itemprctot = $posrow["item_costprice_netto_dsc"] / $posrow["item_amount"] * $shppos["item_amount_shipped"];
            }
            else
            {
               $itemprctot = $netto_dsc * $item_amt;
               $netto_dif  = $posrow["item_costprice_netto"] - $netto_dsc;
               $netto_dsc  = (float)$netto_dif / $posrow["item_costprice_netto"] * 100;
            }
            $addItem    = true;
         }
                  
      }
      else
      {
         $item_supporder_pos  = (int)$posrow["item_pos"];
         $item_amt            = $posrow["item_amount"];
         $netto_dsc           = $posrow["item_costprice_netto_dsc"] / $posrow["item_amount"];

         if(round($netto_dsc,2) == round($posrow["item_costprice_netto"], 2))
         {
            $netto_dsc  = 0;
            $itemprctot = $posrow["item_costprice_netto_dsc"];
         }
         else
         {
            $netto_dif  = $posrow["item_costprice_netto"] - $netto_dsc;
            $netto_dsc  = (float)$netto_dif / $posrow["item_costprice_netto"] * 100;
            $itemprctot = $posrow["item_costprice_netto_dsc"];
         }
         $addItem    = true;
      }

      //----------------------------------------------------------------------------------
      if($addItem)
      {
         $posrow["item_desc"]             = addslashes($posrow["item_desc"]);
         $posrow["item_subitem_id"]       = (int)$posrow["item_subitem_id"];
         $posrow["item_subitem_amount"]   = (float)$posrow["item_subitem_amount"];
         
         $sql = " insert into invoices_buy_parts_items
                  (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                   item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_discount,
                   item_costprice_netto_dsc, item_supporder_pos, item_desc, item_subitem_id, item_subitem_amount)
                  VALUES
                  ({$invcid}, {$partid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$item_amt},
                   '{$posrow["item_type"]}', {$posrow["item_costprice_brutto"]}, {$posrow["item_costprice_taxes_perc"]},
                   {$posrow["item_costprice_netto"]}, {$posrow["item_costprice_taxes"]}, {$netto_dsc},
                   {$itemprctot}, {$item_supporder_pos}, '{$posrow["item_desc"]}',
                   {$posrow["item_subitem_id"]}, {$posrow["item_subitem_amount"]})";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   recalcInvoiceBuy($CON, $invcid);
}

//----------------------------------------------------------------------------------
function stockcountSetItemContent($CON, $stcid)
{
   //----------------------------------------------------------------------------------
   $sql = " delete from stockcounts_lists
            where
            stc_id = {$stcid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from stockcounts_lists_items
            where
            stc_id = {$stcid}";
   $CON->no_result($sql);
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts
            where
            id = {$stcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   if($headdata["stc_cat_mode"] == 1)
   {
      $sql = " select t1.id, t1.cat_title
               from productcats t1
               where
               t1.cat_status = 1
               order by t1.id asc";
      $pcats = $CON->select($sql);
   }
   else
   {
      $sql = " select t2.id, t2.cat_title
               from stockcounts_productcats t1
               INNER JOIN productcats t2 ON t1.cat_id = t2.id
               where
               t1.stc_id = {$stcid}
               order by t2.id asc";
      $pcats = $CON->select($sql);
      foreach($pcats AS $pcat)
         $pcatstr .= $pcat["id"].",";
      $pcatstr = substr($pcatstr, 0, -1);
   }

   //----------------------------------------------------------------------------------
   if($headdata["stc_sth_mode"] == 1)
   {
      $sql = " select id, st_name
               from company_shops_storehouses
               where
               st_shop_id = {$headdata["stc_shopid"]} and
               st_status  = 1
               order by st_name";
      $stids = $CON->select($sql);
   }
   else
   {
      $sql = " select t2.id, t2.st_name
               from stockcounts_storehouses t1
               INNER JOIN company_shops_storehouses t2 ON t1.sth_id = t2.id
               where
               t1.stc_id = {$stcid}
               order by t2.st_name asc";
      $stids = $CON->select($sql);
      foreach($stids AS $stid)
         $sthstr .= $stid["id"].",";
      $sthstr = substr($sthstr, 0, -1);
   }

   //----------------------------------------------------------------------------------
   if($headdata["stc_list_mode"] == 1)
   {
      $listposcounter = 0;
      
      for($x = 0; $x < count($stids) && $stids != false; $x++)
      {
         for($y = 0; $y < count($pcats) && $pcats != false; $y++)
         {
            $sql_lst_sthid = (int)$stids[$x]["id"];
            $sql_lst_catid = (int)$pcats[$y]["id"];

            $sql_lst_sthname = trim(addslashes($stids[$x]["st_name"]));
            $sql_lst_catname = sprintf("%03s", $sql_lst_catid)." - ".trim(addslashes($pcats[$y]["cat_title"]));
            
            $sql = " insert into stockcounts_lists
                     (stc_id, lst_pos, lst_name, lst_sthid, lst_catid)
                     VALUES
                     ({$stcid}, {$listposcounter}, 'Inventario: {$sql_lst_sthname} | {$sql_lst_catname}', {$sql_lst_sthid}, {$sql_lst_catid})";
            $CON->no_result($sql);
            
            $listposcounter++;
         }
      }
   }

   //----------------------------------------------------------------------------------
   elseif($headdata["stc_list_mode"] == 2)
   {
      $listposcounter = 0;
      for($x = 0; $x < count($stids) && $stids != false; $x++)
      {
         $sql_lst_sthid    = (int)$stids[$x]["id"];
         $sql_lst_sthname  = trim(addslashes($stids[$x]["st_name"]));

         $sql = " insert into stockcounts_lists
                  (stc_id, lst_pos, lst_name, lst_sthid, lst_catid)
                  VALUES
                  ({$stcid}, {$listposcounter}, 'Inventario: {$sql_lst_sthname}', {$sql_lst_sthid}, 0)";
         $CON->no_result($sql);

         $listposcounter++;
      }
   }

   if($headdata["stc_tran_mode"] == 3)
   {
      $sql = " select *
               from stockcounts_preitem
               where
               stc_id = {$stcid}
               order by id";
      $tmpposdata = $CON->select($sql);

      $selitemstr = "";
      foreach($tmpposdata AS $pos)
         $selitemstr .= $pos["item_id"].",";
      $selitemstr = substr($selitemstr, 0, -1);
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockcounts_lists
            where
            stc_id = {$stcid}
            order by lst_pos asc";
   $stclists = $CON->select($sql);

   foreach($stclists AS $stclist)
   {
      if($headdata["stc_tran_mode"] != 3)
      {
         $sql = " select distinct t1.id, t4.item_costprice_avg_netto
                  from item t1 ";

         if((int)$stclist["lst_catid"])
            $sql .= "INNER JOIN item_productcats t2 ON ( t1.id = t2.item_id and t2.cat_id = {$stclist["lst_catid"]} ) ";
         elseif($headdata["stc_cat_mode"] == 2)
            $sql .= "INNER JOIN item_productcats t2 ON ( t1.id = t2.item_id and t2.cat_id IN ({$pcatstr})) ";
            
         if((int)$stclist["lst_sthid"])
            $sql .= "INNER JOIN item_shops_storehouses t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["stc_shopid"]} and t3.st_id = {$stclist["lst_sthid"]} ) ";
         elseif($headdata["stc_sth_mode"] == 2)
            $sql .= "INNER JOIN item_shops_storehouses t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["stc_shopid"]} and t3.st_id IN ({$sthstr}) ) ";
         
         $sql .= " LEFT OUTER JOIN tran_average_costprices t4 ON ( t1.id = t4.item_id and t4.company_id = {$headdata["stc_companyid"]} )
                   where
                   t1.item_status    = 1 and
                   t1.item_released  = 1 ";

         if($headdata["stc_tran_mode"] == 2)
            $sql .= " and t1.id = {$headdata["stc_itemid"]} ";
         if((int)$headdata["stc_onlystock"])
            $sql .= " and t3.iss_inventory > 0.00 ";


         if($headdata["stc_order_mode"] == 1)
            $sql .= " order by t1.item_number_prod ";
         elseif($headdata["stc_order_mode"] == 2)
            $sql .= " order by t1.item_title ";

         $lstitems = $CON->select($sql);
      }
      else
      {
         $sql = " select t1.item_id 'id', t4.item_costprice_avg_netto
                  from stockcounts_preitem t1
                  LEFT OUTER JOIN item t2 ON t1.item_id = t2.id
                  LEFT OUTER JOIN tran_average_costprices t4 ON ( t1.id = t4.item_id and t4.company_id = {$headdata["stc_companyid"]} )
                  where
                  t1.stc_id = {$stcid} ";
         if($headdata["stc_order_mode"] == 1)
            $sql .= " order by t2.item_number_prod ";
         elseif($headdata["stc_order_mode"] == 2)
            $sql .= " order by t2.item_title ";
         $lstitems = $CON->select($sql);
      }
      $itemposcounter = 0;
      foreach($lstitems AS $lstitem)
      {
         $currstock = (float)getItemShopStorehouseCurrentStock($CON, $headdata["stc_shopid"], $stclist["lst_sthid"], $lstitem["id"], "item");
         $max_price = 0.00;
         $max_docno = "";
         $max_docda = 0;

         if((int)$headdata["stc_repo_mode"])
         {
            $searchyear = date('Y', $headdata["stc_bookdate"]);
            $searchinit = mktime(0, 0, 0, 1, 1, $searchyear);
            $searchend  = mktime(23, 59, 59, 12, 31, $searchyear);
            $itemsup    = getItemSuppliers($CON, $lstitem["id"], "item");
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
                        t3.item_id        = {$lstitem["id"]} and
                        t3.item_type      = 'item'
                        order by 3 desc
                        LIMIT 0,1";
               $maxinvc = $CON->select($sql);
               $max_price = (float)$maxinvc[0]["year_max_buyprice"];
               $max_docno = $maxinvc[0]["invc_docnumber"];
               $max_docda = $maxinvc[0]["invc_date"];
               if(!$max_price)
               {
                  $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $lstitem["id"], "item", $headdata["stc_bookdate"]);
                  $max_price     = $lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"];
                  $max_docno     = $lastbuyprice["invc_docnumber"];
                  $max_docda     = $lastbuyprice["invc_date"];
               }
            }
            else
            {
               $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $lstitem["id"], "item", $headdata["stc_bookdate"]);
               $max_price     = $lastbuyprice["item_costprice_import_total"] / $lastbuyprice["item_amount"];
               $max_docno     = $lastbuyprice["invc_docnumber"];
               $max_docda     = $lastbuyprice["invc_date"];
            }
         }
         else
         {
            $itemsup    = getItemSuppliers($CON, $lstitem["id"], "item");
            $itemsup    = $itemsup[0];
            $supptax    = getSupplierTaxes($CON, $itemsup["supplier_id"]);

            if((int)$supptax)
            {
               $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $lstitem["id"], "item", $headdata["stc_bookdate"]);
               $max_price     = $lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"];
               $max_docno     = $lastbuyprice["invc_docnumber"];
               $max_docda     = $lastbuyprice["invc_date"];
            }
            else
            {
               $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $lstitem["id"], "item", $headdata["stc_bookdate"]);
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
            $cost = getSupplierFinalCostNetto($CON, 0, $lstitem["id"]);
            $item_costprice_avg_netto = (float)$cost;
         }

         $sql = " insert into stockcounts_lists_items
                  (stc_id, stc_lst_posid, item_id, item_pos, item_stid, item_amount_stock, item_amount_count,
                  item_costprice_avg_netto, item_costprice_docnumber, item_costprice_docdate)
                  VALUES
                  ({$stcid}, {$stclist["lst_pos"]}, {$lstitem["id"]}, {$itemposcounter},
                   {$stclist["lst_sthid"]}, {$currstock}, NULL, {$item_costprice_avg_netto},
                   '{$item_costprice_docnumber}', {$item_costprice_docdate})";
         $CON->no_result($sql);

         $itemposcounter++;
      }
   }
   foreach($stclists AS $stclist)
   {
      $sql = " select count(*) 'cc'
               from stockcounts_lists_items
               where
               stc_id = {$stcid} and
               stc_lst_posid = {$stclist["lst_pos"]}";
      $cc = $CON->select($sql);
      $cc = $cc[0]["cc"];
      if(!$cc)
      {
         $sql = " delete from stockcounts_lists
                  where
                  stc_id = {$stcid} and
                  lst_pos = {$stclist["lst_pos"]}";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
function printFancyBoxStock($CON, $shop_id, $item_id, $item_type, $mode, $reserva = 0)
{
   $urlparams = "shopid={$shop_id}&itemid={$item_id}&itemtype={$item_type}";
   
   if($mode == "storehousestock")
   {
      $stock = getItemShopCurrentStock($CON, $shop_id, $item_id, $item_type, true, $reserva);
      if($stock != 0)
      {  ?>
         <div style="cursor:pointer;" onclick="showFancybox('/libs/modules/orders/show.storehousestock.php?<?=$urlparams?>', 'iframe', 600, 400, 'auto')">
            <?=printPrice($stock,2)?>
         </div>
         <?php
      }
      else
         echo printPrice($stock,2);
      return $stock;
   }
   elseif($mode == "transstock")
   {
      $stock = getItemShopTransStock($CON, $shop_id, $item_id, $item_type, true);
      if($stock != 0)
      {  ?>
         <div style="cursor:pointer" onclick="showFancybox('/libs/modules/orders/show.transstock.php?<?=$urlparams?>', 'iframe', 800, 400, 'auto')">
            <?=printPrice($stock,2)?>
         </div>
         <?php
      }
      else
         echo printPrice($stock,2);
      return $stock;
   }
}

//----------------------------------------------------------------------------------
function getItemUnitDesc($CON, $itemid, $itemtype, $shwtype = "full")
{
   $sql = " select t1.item_unit_amount, t2.unit_name, t2.unit_desc
            from {$itemtype} t1
            LEFT OUTER JOIN item_units t2 ON t1.item_unit = t2.id
            where
            t1.id = {$itemid}";
   $item = $CON->select($sql);
   $item = $item[0];

   if($shwtype == "small")
      return $item["unit_name"];
   
   if($itemtype == "item")
      return "{$item["unit_name"]}";
   elseif($itemtype == "itemlist")
   {
      $posamt = 0;
      $itemlistpos = getItemListContent($CON, $itemid);
      foreach($itemlistpos AS $itemlistrow)
      {
         $posamt += $itemlistrow["item_amount"];
         $pouname = $itemlistrow["unit_name"];
      }
         
      return "{$item["unit_name"]}-".printPrice($posamt,0)."x";
   }
   else
      return " ";
}

//----------------------------------------------------------------------------------
function recalcAutomatedSellPrices($CON, $itemid, $itemtype)
{
   $currtme = time();
   
   $sql = " select *
            from {$itemtype}
            where
            id = {$itemid}";
   $itemdata = $CON->select($sql);
   $itemdata = $itemdata[0];

   if((int)$itemdata["item_sellprice_calc"])
   {
      $sql = " select t1.item_costprice_netto
               from {$itemtype}_suppliers t1
               where
               t1.item_id        = {$itemid} and
               t1.item_supp_act  = 1";
      $suppinfo = $CON->select($sql);
      $suppinfo = $suppinfo[0];

      $item_sellprice_netto         = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", (float)$suppinfo["item_costprice_netto"] + ((float)$suppinfo["item_costprice_netto"] / 100 * $itemdata["item_sellprice_calc_perc"]));
      $item_sellprice_taxes_perc    = $itemdata["item_sellprice_taxes_perc"];
      $item_sellprice_taxes         = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto / 100 * $item_sellprice_taxes_perc);
      $item_sellprice_brutto        = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto + $item_sellprice_taxes);

      $sql = " update {$itemtype}
               set
               item_sellprice_netto    = {$item_sellprice_netto},
               item_sellprice_brutto   = {$item_sellprice_brutto},
               item_sellprice_taxes    = {$item_sellprice_taxes},
               item_updusr             = {$_SESSION["user_id"]},
               item_upddat             = {$currtme}
               where
               id = {$itemid}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function registerSellPriceHistory($CON, $itemid, $itemtype)
{
   
   $currtme = time();
   $itemshops = getItemShops($CON, $itemid, $itemtype);
   foreach($itemshops AS $itemshop)
   {
      $sellprice = getShopItemStorePrice($CON, $itemshop["shop_id"], $itemid, $itemtype);
      $lastprice = getLastSellPriceHistory($CON, $itemshop["shop_id"], $itemid, $itemtype);

      if($sellprice["itemshop_sellprice_brutto"] != $lastprice["prc_sellprice_brutto"] ||
         $sellprice["itemshop_sellprice_netto"] != $lastprice["prc_sellprice_netto"])
      {
         $sql = " insert into pricehist_sell
                  (prc_item_id, prc_item_type, prc_shop_id, prc_crtdat, prc_crtusr, prc_sellprice_netto, prc_sellprice_brutto,
                   prc_sellprice_taxes, prc_sellprice_taxes_perc)
                  VALUES
                  ({$itemid}, '{$itemtype}', {$itemshop["shop_id"]}, {$currtme}, {$_SESSION["user_id"]},
                  {$sellprice["itemshop_sellprice_netto"]}, {$sellprice["itemshop_sellprice_brutto"]},
                  {$sellprice["itemshop_sellprice_taxes"]}, {$sellprice["itemshop_sellprice_taxes_perc"]})";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
function registerCostPriceHistory($CON, $itemid, $itemtype)
{
   $currtme = time();
   $suppliers = getItemSuppliers($CON, $itemid, $itemtype);
   
   foreach($suppliers AS $supplier)
   {
      $lastprice = getLastCostPriceHistory($CON, $supplier["supplier_id"], $itemid, $itemtype);

      if($supplier["item_costprice_brutto"] != $lastprice["prc_costprice_brutto"] ||
         $supplier["item_costprice_netto"] != $lastprice["prc_costprice_netto"])
      {
         $sql = " insert into pricehist_buy
                  (prc_item_id, prc_item_type, prc_supplier_id, prc_crtdat, prc_crtusr, prc_costprice_netto, prc_costprice_brutto,
                   prc_costprice_taxes, prc_costprice_taxes_perc)
                  VALUES
                  ({$itemid}, '{$itemtype}', {$supplier["supplier_id"]}, {$currtme}, {$_SESSION["user_id"]},
                  {$supplier["item_costprice_netto"]}, {$supplier["item_costprice_brutto"]},
                  {$supplier["item_costprice_taxes"]}, {$supplier["item_costprice_taxes_perc"]})";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
function getLastSellPriceHistory($CON, $shopid, $itemid, $itemtype)
{
   $sql = " select * 
            from pricehist_sell
            where
            prc_item_id    = {$itemid} and
            prc_item_type  = '{$itemtype}' and
            prc_shop_id    = {$shopid}
            order by prc_crtdat desc
            LIMIT 0,1";
   $ret = $CON->select($sql);
   return $ret[0];
}

//----------------------------------------------------------------------------------
function getLastCostPriceHistory($CON, $supplierid, $itemid, $itemtype)
{
   $sql = " select * 
            from pricehist_buy
            where
            prc_item_id       = {$itemid} and
            prc_item_type     = '{$itemtype}' and
            prc_supplier_id   = {$supplierid}
            order by prc_crtdat desc
            LIMIT 0,1";
   $ret = $CON->select($sql);
   return $ret[0];
}

//----------------------------------------------------------------------------------
function getSupplierTaxes($CON, $suppid)
{
   $sql = " select t2.taxes_active
            from supplier t1
            LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
            where
            t1.id = {$suppid}";
   $supptax = $CON->select($sql);
   $supptax = $supptax[0]["taxes_active"];

   return (int)$supptax;
}

//----------------------------------------------------------------------------------
function getSupplierItemLastBuyPrice($CON, $supplierid, $itemid, $itemtype, $smallerdate = 0, $companyid = 0, $shopid = 0)
{
   $sql = " select *
            from invoices_buy t1
            INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
            where
            t1.invc_supplier_id  = {$supplierid} and
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t2.item_id           = {$itemid} and
            t2.item_type         = '{$itemtype}' ";
   if((int)$smallerdate)
      $sql .= " and t1.invc_date < {$smallerdate} ";
   if((int)$companyid)
      $sql .= " and t1.invc_company_id = {$companyid} ";
   if((int)$shopid)
      $sql .= " and t1.invc_shop_id = {$shopid} ";
   $sql .= " order by t1.invc_date desc
             LIMIT 0,1";
   $lastbuy = $CON->select($sql);
   return $lastbuy[0];
}

//----------------------------------------------------------------------------------
function getSupplierPaymentDiscount($CON, $supplier_id, $paymentid)
{
   $sql = " select *
            from dscbuy_supplier_payment
            where
            dct_supplier_id   = {$supplier_id} and
            dct_payid         = {$paymentid}";
   $suppaydsc = $CON->select($sql);
   return $suppaydsc[0];
}

//----------------------------------------------------------------------------------
function recalcInvoiceBuy($CON, $invcid, $completerecalc = true)
{
   $sql = " select *
            from invoices_buy
            where
            id = {$invcid}";
   $invoice = $CON->select($sql);
   $invoice = $invoice[0];

   //----------------------------------------------------------------------------------
   $invc_item_netto_total  = 0.00;
   $invc_importation       = (int)$invoice["invc_importation"];
   if((int)$invc_importation)
   {
      $numberlim = "4";
      $numberlim2 = "2";
   }
   else
   {
      $numberlim = "2";
      $numberlim2 = "0";
   }

   //----------------------------------------------------------------------------------
   // SUM ITEM VALUES
   //----------------------------------------------------------------------------------
   $invcparts = getInvoiceBuyParts($CON, $invcid);
   foreach($invcparts AS $invcpart)
   {
      $posdata = getInvoiceBuyPartsItems($CON, $invcid, $invcpart["id"]);
      foreach($posdata AS $posrow)
      {
         $invc_item_netto_total += $posrow["item_costprice_netto_dsc"];
         $tax_struct[$posrow["item_costprice_taxes_perc"]] += $posrow["item_costprice_netto_dsc"];

         if((int)$invc_importation)
         {
            $itemprc_usd                  = round($posrow["item_costprice_netto_dsc"] / $posrow["item_amount"],2);
            $item_costprice_import_item   = round($itemprc_usd * $invoice["invc_exc_rate"],0);
            $item_costprice_import_total  = round($posrow["item_costprice_netto_dsc"] * $invoice["invc_exc_rate"],0);

            $sql = " update invoices_buy_parts_items
                     set
                     item_costprice_import_item    = {$item_costprice_import_item},
                     item_costprice_import_total   = {$item_costprice_import_total}
                     where
                     invc_id  = {$invcid} and
                     part_id  = {$invcpart["id"]} and
                     item_id  = {$posrow["item_id"]} and
                     item_pos = {$posrow["item_pos"]}";
            $CON->no_result($sql);
         }
      }
   }

   $invc_total_netto_dsc = $invc_item_netto_total;

   //----------------------------------------------------------------------------------
   // CALC GLOBAL INVOICE DISCOUNTS 1-5
   //----------------------------------------------------------------------------------
   for($x = 1; $x <= 5; $x++)
   {
      if($invoice["invc_discount{$x}"] != 0.00)
      {
         $new_netto = calcDCTDiscount($invc_total_netto_dsc, $invoice["invc_discount{$x}"], $invoice["invc_discount_type{$x}"], $numberlim2);
         $invc_total_netto_dsc = $new_netto["val_val"];
      }
   }

   //----------------------------------------------------------------------------------
   // CALC GLOBAL INVOICE DISCOUNT 6
   //----------------------------------------------------------------------------------
   $invc_total_netto = $invc_total_netto_dsc;
   if($invoice["invc_discount6"] != 0.00)
   {
      $new_netto = calcDCTDiscount($invc_total_netto, $invoice["invc_discount6"], $invoice["invc_discount_type6"], $numberlim2);
      $invc_total_netto = $new_netto["val_val"];
   }

   //----------------------------------------------------------------------------------
   // TAXES CALC
   //----------------------------------------------------------------------------------
   $invc_total_netto = round($invc_total_netto, $numberlim2);
   $ges_dscper = ($invc_item_netto_total - $invc_total_netto) / $invc_item_netto_total * 100;
   foreach(array_keys($tax_struct) AS $taxval)
   {
      $total_netto       = $tax_struct[$taxval] - ($tax_struct[$taxval] / 100 * $ges_dscper);
      $total_taxes       = $total_netto / 100 * $taxval;
      $invc_total_taxes += $total_taxes;
   }
   $invc_total_taxes    = round($invc_total_taxes, $numberlim2);
   $invc_total_brutto   = $invc_total_netto + $invc_total_taxes;

   //----------------------------------------------------------------------------------
   $invc_total_import = 0;
   if((int)$invc_importation)
      $invc_total_import = round($invc_total_brutto * $invoice["invc_exc_rate"],0);

   $invc_total_taxes_exclude = (float)$tax_struct["0.00"];

   //----------------------------------------------------------------------------------
   $sql = " update invoices_buy
            set
            invc_item_netto_total      = {$invc_item_netto_total},
            invc_total_netto_dsc       = {$invc_total_netto_dsc},
            invc_total_netto           = {$invc_total_netto},
            invc_total_taxes           = {$invc_total_taxes},
            invc_total_brutto          = {$invc_total_brutto},
            invc_import_total          = {$invc_total_import},
            invc_total_taxes_exclude   = {$invc_total_taxes_exclude}
            where
            id = {$invcid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_buy
            where
            id = {$invcid}";
   $invoice = $CON->select($sql);
   $invoice = $invoice[0];
   if($invoice["invc_total_taxes_add"] > 0.00)
   {
      $taxes_items    = $invoice["invc_total_taxes"];
      $brutto_items   = $invoice["invc_total_brutto"];

      $invc_total_taxes_orig = $taxes_items;
      $taxes_items   += $invoice["invc_total_taxes_add"];
      $brutto_items  += $invoice["invc_total_taxes_add"];

      //----------------------------------------------------------------------------------
      $sql = " update invoices_buy
               set
               invc_total_taxes        = {$taxes_items},
               invc_total_brutto       = {$brutto_items},
               invc_total_taxes_orig   = {$invc_total_taxes_orig}
               where
               id = {$invcid}";
      $CON->no_result($sql);
   }

   $item_perc_dsc2 = ($invc_item_netto_total - $invc_total_netto) / $invc_item_netto_total * 100;

   //----------------------------------------------------------------------------------
   // CALCULATE ITEM FULL DISCOUNTS VALUES
   //----------------------------------------------------------------------------------
   foreach($invcparts AS $invcpart)
   {
      $posdata = getInvoiceBuyPartsItems($CON, $invcid, $invcpart["id"]);
      foreach($posdata AS $posrow)
      {
         $item_costprice_netto_dsc2 = round($posrow["item_costprice_netto_dsc"] - ($posrow["item_costprice_netto_dsc"] / 100 * $item_perc_dsc2), $numberlim2);
         $itemprc_usd                  = round($item_costprice_netto_dsc2 / $posrow["item_amount"],2);
         $item_costprice_import_item   = round($itemprc_usd * $invoice["invc_exc_rate"],0);
         $item_costprice_import_total  = round($item_costprice_netto_dsc2 * $invoice["invc_exc_rate"],0);

         $sql = " update invoices_buy_parts_items
                  set
                  item_costprice_netto_dsc2     = {$item_costprice_netto_dsc2},
                  item_costprice_import_item    = {$item_costprice_import_item},
                  item_costprice_import_total   = {$item_costprice_import_total}
                  where
                  invc_id  = {$invcid} and
                  part_id  = {$invcpart["id"]} and
                  item_id  = {$posrow["item_id"]} and
                  item_pos = {$posrow["item_pos"]}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   if($completerecalc && ($invoice["invc_type"] == 3 || $invoice["invc_type"] == 4))
   {
      $dsc_value           = getValueDiscounts($CON, "dscbuy_supplier_value", "dct_supplier_id", $invoice["invc_supplier_id"], $invc_total_netto_dsc);
      $sord_value_dsc      = (float)$dsc_value["dct_scale_discount"];
      $sord_value_dsctype  = (int)$dsc_value["dct_scale_type"];
      $dsc_price           = calcDCTDiscount($invc_total_netto_dsc, $sord_value_dsc, $sord_value_dsctype, 2);
      $dsc_price           = $dsc_price["dsc_val"];

      //----------------------------------------------------------------------------------
      $sql = " select *
               from supplier
               where
               id = {$invoice["invc_supplier_id"]}";
      $suppdata = $CON->select($sql);
      if($suppdata[0]["supp_dsc_finance_calc"] == "OC")
         $invc_discount3 = $suppdata[0]["supp_dsc_finance"];
      else
         $invc_discount3 = 0.00;

      //----------------------------------------------------------------------------------
      $dsc_pay                = getSupplierPaymentDiscount($CON, $invoice["invc_supplier_id"], $invoice["invc_paymentid"]);
      $sord_payment_dsc       = (float)$dsc_pay["dct_scale_discount"];
      $sord_payment_dsctype   = (int)$dsc_pay["dct_scale_type"];

      //----------------------------------------------------------------------------------
      $sql = " update invoices_buy
               set
               invc_discount1       = {$sord_value_dsc},
               invc_discount_type1  = {$sord_value_dsctype},
               invc_discount2       = {$sord_payment_dsc},
               invc_discount_type2  = {$sord_payment_dsctype},
               invc_discount3       = {$invc_discount3},
               invc_discount_type3  = 0
               where
               id = {$invcid}";
      $CON->no_result($sql);

      recalcInvoiceBuy($CON, $invcid, false);
   }
}

//----------------------------------------------------------------------------------
function recalcInvoiceBuyNote($CON, $noteid)
{
   $sql = " select *
            from invoices_notes_buy
            where
            id = {$noteid}";
   $note = $CON->select($sql);
   $note = $note[0];

   //----------------------------------------------------------------------------------
   $note_item_netto_total  = 0.00;
   $note_importation       = (int)$note["note_importation"];
   if((int)$note_importation)
   {
      $numberlim = "4";
      $numberlim2 = "2";
   }
   else
   {
      $numberlim = "0";
      $numberlim2 = "0";
   }

   //----------------------------------------------------------------------------------
   // SUM ITEM VALUES
   //----------------------------------------------------------------------------------
   $posdata = getInvoiceBuyNoteItems($CON, $noteid);
   foreach($posdata AS $posrow)
   {
      $note_item_netto_total += $posrow["item_costprice_netto_dsc"];
      $tax_struct[$posrow["item_costprice_taxes_perc"]] += $posrow["item_costprice_netto_dsc"];

      if((int)$note_importation)
      {
         $itemprc_usd                  = round($posrow["item_costprice_netto_dsc"] / $posrow["item_amount"],2);
         $item_costprice_import_item   = round($itemprc_usd * $note["note_exc_rate"],0);
         $item_costprice_import_total  = round($posrow["item_costprice_netto_dsc"] * $note["note_exc_rate"],0);

         $sql = " update invoices_notes_buy_items
                  set
                  item_costprice_import_item    = {$item_costprice_import_item},
                  item_costprice_import_total   = {$item_costprice_import_total}
                  where
                  note_id  = {$noteid} and
                  item_id  = {$posrow["item_id"]} and
                  item_pos = {$posrow["item_pos"]}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   // TAXES CALC
   //----------------------------------------------------------------------------------
   foreach(array_keys($tax_struct) AS $taxval)
   {
      $total_netto       = $tax_struct[$taxval];
      $total_taxes       = $total_netto / 100 * $taxval;
      $note_total_taxes += $total_taxes;
   }
   $note_total_taxes    = round($note_total_taxes, $numberlim);
   $note_total_brutto   = $note_item_netto_total + $note_total_taxes;

   //----------------------------------------------------------------------------------
   $note_total_import = 0;
   if((int)$note_importation)
      $note_total_import = round($note_total_brutto * $note["note_exc_rate"],0);

   $note_total_taxes_exclude = (float)$tax_struct["0.00"];

   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_buy
            set
            note_total_netto           = {$note_item_netto_total},
            note_total_taxes           = {$note_total_taxes},
            note_total_brutto          = {$note_total_brutto},
            note_import_total          = {$note_total_import},
            note_total_taxes_exclude   = {$note_total_taxes_exclude}
            where
            id = {$noteid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function recalcInvoiceSellNote($CON, $noteid)
{
   $sql = " select *
            from invoices_notes_sell
            where
            id = {$noteid}";
   $note = $CON->select($sql);
   $note = $note[0];

   //----------------------------------------------------------------------------------
   $note_item_netto_total  = 0.00;

   //----------------------------------------------------------------------------------
   // SUM ITEM VALUES
   //----------------------------------------------------------------------------------
   $posdata = getInvoiceSellNoteItems($CON, $noteid);
   foreach($posdata AS $posrow)
   {
      $note_item_netto_total += $posrow["item_sellprice_netto_dsc"];
      $tax_struct[$posrow["item_sellprice_taxes_perc"]] += $posrow["item_sellprice_netto_dsc"];
   }

   //----------------------------------------------------------------------------------
   // TAXES CALC
   //----------------------------------------------------------------------------------
   foreach(array_keys($tax_struct) AS $taxval)
   {
      $total_netto       = $tax_struct[$taxval];
      $total_taxes       = $total_netto / 100 * $taxval;
      $note_total_taxes += $total_taxes;
   }
   $note_total_taxes    = round($note_total_taxes);
   $note_total_brutto   = $note_item_netto_total + $note_total_taxes;

   //----------------------------------------------------------------------------------
   $note_total_taxes_exclude = (float)$tax_struct["0.00"];

   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_sell
            set
            note_total_netto           = {$note_item_netto_total},
            note_total_taxes           = {$note_total_taxes},
            note_total_brutto          = {$note_total_brutto},
            note_total_taxes_exclude   = {$note_total_taxes_exclude}
            where
            id = {$noteid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function recalcOrder($CON, $reqid, $mode = "")
{
   $req_total_netto       = 0.00;
   $req_total_taxes       = 0.00;
   $req_total_brutto      = 0.00;
   $dlv_mode              = 0;

   //----------------------------------------------------------------------------------
   $_TABLENAME    = "orders_items";
   $_TABLENAMEHD  = "orders";
   $_COLPREFIX    = "req";
   $_AMOUNTFIELD  = "item_amount";

   //----------------------------------------------------------------------------------
   if($mode == "DELIVERY")
   {
      $_TABLENAME    = "orders_delivery_items";
      $_TABLENAMEHD  = "orders_delivery";
      $_COLPREFIX    = "dlv";
      $_AMOUNTFIELD  = "item_amount_shipped";

      $sql = " select dlv_mode
               from orders_delivery 
               where
               id = {$reqid}";
      $dlv_mode = $CON->select($sql);
      $dlv_mode = (int)$dlv_mode[0]["dlv_mode"];
   }
   elseif($mode == "INVOICENOTE")
   {
      $_TABLENAME    = "invoices_notes_sell_items";
      $_TABLENAMEHD  = "invoices_notes_sell";
      $_COLPREFIX    = "note";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "INVOICE")
   {
      $_TABLENAME    = "invoices_sell_parts_items";
      $_TABLENAMEHD  = "invoices_sell";
      $_COLPREFIX    = "invc";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "INVOICEBOL")
   {
      $_TABLENAME    = "invoices_sell_bol_parts_items";
      $_TABLENAMEHD  = "invoices_sell_bol";
      $_COLPREFIX    = "invc";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "OFFER")
   {
      $_TABLENAME    = "offers_items";
      $_TABLENAMEHD  = "offers";
      $_COLPREFIX    = "req";
      $_AMOUNTFIELD  = "item_amount";
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from {$_TABLENAMEHD} t1
            where
            t1.id = {$reqid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   if($mode == "DELIVERY")
      $posdata = getOrderDeliveryPos($CON, $reqid);
   elseif($mode == "INVOICENOTE")
      $posdata = getInvoiceSellNoteItems($CON, $reqid);
   elseif($mode == "INVOICE")
   {
      $posdata    = Array();
      $invcparts  = getInvoiceSellParts($CON, $reqid);
      for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
      {
         $partposdata = getInvoiceSellPartsItems($CON, $reqid, $invcparts[$x]["id"]);
         for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            $posdata[] = $partposdata[$y];
      }
   }
   elseif($mode == "OFFER")
      $posdata = getOfferPos($CON, $reqid);
   else
      $posdata = getOrderPos($CON, $reqid);

   if($dlv_mode < 2)
   {
      //----------------------------------------------------------------------------------
      // CAT VALUE DISCOUNTS
      //----------------------------------------------------------------------------------
      foreach($posdata AS $row)
      {
         $prc_netto = $row["item_sellprice_netto"] * $row[$_AMOUNTFIELD];
         $prc_brutto = $row["item_sellprice_brutto"] * $row[$_AMOUNTFIELD];
         if(!(int)$row["item_sell_nodsc"] && !(int)$row["cat_dsc_off"])
         {
            $_CATVALUES[$row["cat_id"]] += $prc_netto;
            $_CATVALUES_BRUTTO[$row["cat_id"]] += $prc_brutto;
            $_CATITEMS[$row["cat_id"]][$row["item_id"]][$row["item_pos"]] = $row;
         }
      }

      //----------------------------------------------------------------------------------
      // UPDATE CAT VALUE DISCOUNTS
      //----------------------------------------------------------------------------------
      foreach(array_keys($_CATVALUES) AS $catid)
      {
         $cat_value_dsc       = 0;
         $cat_value_dsctype   = 0;

         $selstr  = $headdata["{$_COLPREFIX}_cust_id"]." and dct_cat_id = {$catid} ";
         $val_dsc = getValueDiscounts($CON, "dscsell_customer_productcats_value", "dct_cust_id", $selstr, $_CATVALUES[$catid]);

         //----------------------------------------------------------------------------------
         if((int)$val_dsc["dct_cust_id"])
         {
            $cat_value_dsc       = (float)$val_dsc["dct_scale_discount"];
            $cat_value_dsctype   = (int)$val_dsc["dct_scale_type"];
         }
         else
         {
            $val_dsc = getValueDiscounts($CON, "dscsell_productcats_value", "dct_cat_id", $catid, $_CATVALUES[$catid]);
            if((int)$val_dsc["dct_cat_id"])
            {
               $cat_value_dsc       = (float)$val_dsc["dct_scale_discount"];
               $cat_value_dsctype   = (int)$val_dsc["dct_scale_type"];
            }
         }

         //----------------------------------------------------------------------------------
         foreach(array_keys($_CATITEMS[$catid]) AS $cat_item_id)
         {
            foreach(array_keys($_CATITEMS[$catid][$cat_item_id]) AS $cat_item_pos)
            {
               $checkrow = $_CATITEMS[$catid][$cat_item_id][$cat_item_pos];

               if(($mode == "") || ($mode == "INVOICENOTE") || ($mode == "OFFER") ||
                  ($mode == "DELIVERY" && (int)$checkrow["item_order_pos"] == -1) ||
                  ($mode == "INVOICE" && (int)$checkrow["item_order_pos"] == -1 && (int)$checkrow["item_dlv_pos"] == -1))
               {
                  $sql = " update {$_TABLENAME}
                           set
                           item_value_dsc       = {$cat_value_dsc},
                           item_value_dsctype   = {$cat_value_dsctype}
                           where
                           {$_COLPREFIX}_id     = {$reqid} and
                           item_id              = {$cat_item_id} and
                           item_pos             = {$cat_item_pos}";
                  $CON->no_result($sql);
               }
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($mode == "DELIVERY")
      $posdata = getOrderDeliveryPos($CON, $reqid);
   elseif($mode == "INVOICENOTE")
      $posdata = getInvoiceSellNoteItems($CON, $reqid);
   elseif($mode == "INVOICE")
   {
      $posdata    = Array();
      $invcparts  = getInvoiceSellParts($CON, $reqid);
      for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
      {
         $partposdata = getInvoiceSellPartsItems($CON, $reqid, $invcparts[$x]["id"]);
         for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            $posdata[] = $partposdata[$y];
      }
   }
   elseif($mode == "INVOICEBOL")
   {
      $posdata    = Array();
      $invcparts  = getInvoiceSellParts($CON, $reqid, "_bol");
      for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
      {
         $partposdata = getInvoiceSellPartsItems($CON, $reqid, $invcparts[$x]["id"], "", "_bol");
         for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            $posdata[] = $partposdata[$y];
      }
   }
   elseif($mode == "OFFER")
      $posdata = getOfferPos($CON, $reqid);
   else
      $posdata = getOrderPos($CON, $reqid);

   //----------------------------------------------------------------------------------
   foreach($posdata AS $row)
   {
      $prc_netto  = $row["item_sellprice_netto"] * $row[$_AMOUNTFIELD];
      $prc_brutto = $row["item_sellprice_brutto"] * $row[$_AMOUNTFIELD];
      $prc_netto_orig   = $prc_netto;
      $prc_brutto_orig  = $prc_brutto;

      if(!(int)$row["item_sell_nodsc"] && !(int)$row["cat_dsc_off"] && $dlv_mode < 2)
      {
         //----------------------------------------------------------------------------------
         // GLOBAL DISCOUNT
         //----------------------------------------------------------------------------------
         if($row["item_discount"] != 0.00)
         {
            $new_netto  = calcDCTDiscount($prc_netto, $row["item_discount"], $row["item_discount_type"], 4);
            $new_brutto = calcDCTDiscount($prc_brutto, $row["item_discount"], $row["item_discount_type"], 4);
            $prc_netto  = $new_netto["val_val"];
            $prc_brutto = $new_brutto["val_val"];
         }
         
         //----------------------------------------------------------------------------------
         // CAT DISCOUNTS
         //----------------------------------------------------------------------------------
         if((int)$row["item_pcat_dsc_act"])
         {
            for($x = 1; $x <=4; $x++)
            {
               if($row["item_pcat_dsc{$x}"] > 0.00)
               {
                  $new_netto  = calcDCTDiscount($prc_netto, $row["item_pcat_dsc{$x}"], $row["item_pcat_dsctype{$x}"], 4);
                  $new_brutto = calcDCTDiscount($prc_brutto, $row["item_pcat_dsc{$x}"], $row["item_pcat_dsctype{$x}"], 4);
                  $prc_netto  = $new_netto["val_val"];
                  $prc_brutto = $new_brutto["val_val"];
               }
            }
         }

         //----------------------------------------------------------------------------------
         // VOLUME DISCOUNT
         //----------------------------------------------------------------------------------
         if((int)$row["item_vol_act"] && $row["item_vol_dsc"] > 0.00)
         {
            $new_netto  = calcDCTDiscount($prc_netto, $row["item_vol_dsc"], $row["item_vol_dsctype"], 4);
            $new_brutto = calcDCTDiscount($prc_brutto, $row["item_vol_dsc"], $row["item_vol_dsctype"], 4);
            $prc_netto  = $new_netto["val_val"];
            $prc_brutto = $new_brutto["val_val"];
         }

         //----------------------------------------------------------------------------------
         // VALUE DISCOUNT
         //----------------------------------------------------------------------------------
         if((int)$row["item_value_act"] && $row["item_value_dsc"] > 0.00)
         {
            $new_netto  = calcDCTDiscount($prc_netto, $row["item_value_dsc"], $row["item_value_dsctype"], 4);
            $new_brutto = calcDCTDiscount($prc_brutto, $row["item_value_dsc"], $row["item_value_dsctype"], 4);
            $prc_netto  = $new_netto["val_val"];
            $prc_brutto = $new_brutto["val_val"];
         }
      }
      elseif((int)$row["item_sell_nodsc"] || (int)$row["cat_dsc_off"])
      {
         //----------------------------------------------------------------------------------
         $sql = " update {$_TABLENAME}
                  set
                  item_pcat_dsc1       = 0,
                  item_pcat_dsc2       = 0,
                  item_pcat_dsc3       = 0,
                  item_pcat_dsc4       = 0,
                  item_pcat_dsctype1   = 0,
                  item_pcat_dsctype2   = 0,
                  item_pcat_dsctype3   = 0,
                  item_pcat_dsctype4   = 0,
                  item_discount        = 0,
                  item_discount_type   = 0,
                  item_pcat_dsc_act    = 0,
                  item_vol_act         = 0,
                  item_value_act       = 0
                  where
                  {$_COLPREFIX}_id           = {$reqid} and
                  item_id                    = {$row["item_id"]} and
                  item_pos                   = {$row["item_pos"]} ";
         $CON->no_result($sql);
      }
                   
      
      $prc_netto  = round($prc_netto, $_SESSION["_CONF"]["conf_number_decimal_places"]);
      $prc_brutto = round($prc_brutto, $_SESSION["_CONF"]["conf_number_decimal_places"]);

      if($row["cat_sellprice_min"] > 0.00 && $prc_netto / $row[$_AMOUNTFIELD] < $row["cat_sellprice_min"])
      {
         $sql = " update {$_TABLENAME}
                  set
                  {$_AMOUNTFIELD} = 0
                  where
                  {$_COLPREFIX}_id           = {$reqid} and
                  item_id                    = {$row["item_id"]} and
                  item_pos                   = {$row["item_pos"]} ";
         $CON->no_result($sql);
         ?>
         <script language="JavaScript">
            alert('ADVERTENCIA:\nEL ARTICULO \'<?=str_replace("'","",$row["item_number_prod"])?>\' TIENE UN PRECIO < $<?=printPrice($row["cat_sellprice_min"])?>\n\nSE HA ELIMINADO LA CANTIDAD DEL PRODUCTO.\nREINGRESA CANTIDAD Y CORRIGE MONTO PARA GUARDAR!');
         </script>
         <?php
      }

      //----------------------------------------------------------------------------------
      $sql = " update {$_TABLENAME}
               set
               item_sellprice_netto_dsc   = {$prc_netto} ";
      if((int)$headdata["invc_isinvcbrutto"] || (int)$headdata["dlv_isinvcbrutto"] ||
         (int)$headdata["req_isinvcbrutto"]  || (int)$headdata["note_isinvcbrutto"])
         $sql .= " , item_sellprice_brutto_dsc  = {$prc_brutto} ";
      $sql .= " where
               {$_COLPREFIX}_id           = {$reqid} and
               item_id                    = {$row["item_id"]} and
               item_pos                   = {$row["item_pos"]} ";
      if((int)$row["part_id"])
         $sql .= " and part_id = {$row["part_id"]} ";
      $CON->no_result($sql);

      $req_total_netto     += $prc_netto;
      $br_req_total_brutto += $prc_brutto;

      $tax_struct[$row["item_sellprice_taxes_perc"]]     += $prc_netto;
      $br_tax_struct[$row["item_sellprice_taxes_perc"]]  += $prc_brutto;

      $_ORIG_NETTO   += $prc_netto;
      $_ORIG_BRUTTO  += $prc_brutto;
      
      $xcounter++;
   }


   if((float)$headdata["{$_COLPREFIX}_weightprice_netto"] > 0.00)
   {
      $req_total_netto  += (float)$headdata["{$_COLPREFIX}_weightprice_netto"];
      $req_total_brutto += (float)$headdata["{$_COLPREFIX}_weightprice_netto"];
      $tax_struct[$row["item_sellprice_taxes_perc"]] += (float)$headdata["{$_COLPREFIX}_weightprice_netto"];
      $br_tax_struct[$row["item_sellprice_taxes_perc"]] += (float)$headdata["{$_COLPREFIX}_weightprice_netto"];
   }

   if((float)$headdata["{$_COLPREFIX}_trazlogoprice_netto"] > 0.00)
   {
      $req_total_netto  += (float)$headdata["{$_COLPREFIX}_trazlogoprice_netto"];
      $req_total_brutto += (float)$headdata["{$_COLPREFIX}_trazlogoprice_netto"];
      $tax_struct[$row["item_sellprice_taxes_perc"]] += (float)$headdata["{$_COLPREFIX}_trazlogoprice_netto"];
      $br_tax_struct[$row["item_sellprice_taxes_perc"]] += (float)$headdata["{$_COLPREFIX}_trazlogoprice_netto"];
   }

   //----------------------------------------------------------------------------------
   if($headdata["{$_COLPREFIX}_discount_perc"] > 0.00)
   {
      $taxes_index = array_keys($tax_struct);
      $taxes_index = $taxes_index[0];

      $netto_discount = round($tax_struct[$taxes_index] / 100 * $headdata["{$_COLPREFIX}_discount_perc"],0);
      $tax_struct[$taxes_index] = round($tax_struct[$taxes_index] - $netto_discount,0);
      $req_total_netto -= $netto_discount;

      $brutto_discount = round($br_tax_struct[$taxes_index] / 100 * $headdata["{$_COLPREFIX}_discount_perc"],0);
      $br_tax_struct[$taxes_index]  = round($br_tax_struct[$taxes_index] - $brutto_discount,0);
      $br_req_total_brutto -= $brutto_discount;
   }

   //----------------------------------------------------------------------------------
   if($headdata["{$_COLPREFIX}_discount_amt"] > 0.00)
   {
      $taxes_index = array_keys($tax_struct);
      $taxes_index = $taxes_index[0];

      $tax_struct[$taxes_index] = round($tax_struct[$taxes_index] - $headdata["{$_COLPREFIX}_discount_amt"],0);
      $req_total_netto -= $headdata["{$_COLPREFIX}_discount_amt"];
      $br_tax_struct[$taxes_index] = round($br_tax_struct[$taxes_index] - $headdata["{$_COLPREFIX}_discount_amt"],0);
      $br_req_total_brutto -= $headdata["{$_COLPREFIX}_discount_amt"];
   }

   //----------------------------------------------------------------------------------
   // TAXES CALC
   //----------------------------------------------------------------------------------
   if((int)$headdata["invc_isinvcbrutto"] || (int)$headdata["dlv_isinvcbrutto"] ||
      (int)$headdata["req_isinvcbrutto"] || (int)$headdata["note_isinvcbrutto"])
   {
      foreach(array_keys($br_tax_struct) AS $taxval)
      {
         $total_brutto      = $br_tax_struct[$taxval];
         $total_taxes       = $total_brutto / (100 + $taxval) * $taxval;
         $req_total_taxes  += $total_taxes;
      }

      $req_total_taxes  = round($req_total_taxes, $_SESSION["_CONF"]["conf_number_decimal_places"]);
      $req_total_netto  = $br_req_total_brutto - $req_total_taxes;
      $req_total_brutto = $br_req_total_brutto;

      $req_discount_amount_netto = round($_ORIG_BRUTTO - $req_total_brutto,0);
   }
   else
   {
      foreach(array_keys($tax_struct) AS $taxval)
      {
         $total_netto       = $tax_struct[$taxval];
         $total_taxes       = $total_netto / 100 * $taxval;
         $req_total_taxes  += $total_taxes;
      }

      $req_total_taxes    = round($req_total_taxes, $_SESSION["_CONF"]["conf_number_decimal_places"]);
      $req_total_brutto   = $req_total_netto + $req_total_taxes;

      $req_discount_amount_netto = round($_ORIG_NETTO - $req_total_netto,0);
   }

   
   
   /*
   $_ORIG_NETTO = $req_total_netto;
   $req_total_netto = 0.00;
   
   //----------------------------------------------------------------------------------
   // TAXES CALC
   //----------------------------------------------------------------------------------
   $ges_dscper = ($dsc_price + $dsc_pay) / $req_total_netto * 100;
   foreach(array_keys($tax_struct) AS $taxval)
   {
      $req_total_netto  += $tax_struct[$taxval];
      $total_netto       = $tax_struct[$taxval] - ($tax_struct[$taxval] / 100 * $ges_dscper);
      $total_taxes       = $total_netto / 100 * $taxval;
      $req_total_taxes  += $total_taxes;
   }
   $req_total_taxes    = round($req_total_taxes, $_SESSION["_CONF"]["conf_number_decimal_places"]);
   $req_total_brutto   = $req_total_netto + $req_total_taxes;

   $req_discount_amount_netto = round($_ORIG_NETTO - $req_total_netto,0);
   */

   //----------------------------------------------------------------------------------
   $sql = " update {$_TABLENAMEHD}
            set
            {$_COLPREFIX}_total_netto  = {$req_total_netto},
            {$_COLPREFIX}_total_taxes  = {$req_total_taxes},
            {$_COLPREFIX}_total_brutto = {$req_total_brutto},
            {$_COLPREFIX}_discount_amount_netto = {$req_discount_amount_netto}
            where
            id = {$reqid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($mode == "INVOICE" || $mode == "INVOICENOTE")
   {
      $invc_total_taxes_exclude = (float)$tax_struct["0.00"];
      
      $sql = " update {$_TABLENAMEHD}
               set
               {$_COLPREFIX}_total_taxes_exclude = {$invc_total_taxes_exclude}
               where
               id = {$reqid}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function calcDCTDiscount($val, $dsc, $type, $dec)
{
   if((int)$type == 1)
   {
      $temp["dsc_val"] = $dsc;
      $temp["val_val"] = $val - $dsc;
   }
   else
   {
      $temp["dsc_val"] = (float)sprintf("%.{$dec}f", ($val / 100 * $dsc));
      $temp["val_val"] = $val - $temp["dsc_val"];
   }

   return $temp;
}

//----------------------------------------------------------------------------------
function calcDiscount($val, $dsc, $type)
{
   if($type == "val")
   {
      $temp["dsc_val"] = $dsc;
      $temp["val_val"] = $val - $dsc;
   }
   else
   {
      $temp["dsc_val"] = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", ($val / 100 * $dsc));
      $temp["val_val"] = $val - $temp["dsc_val"];
   }

   return $temp;
}

//----------------------------------------------------------------------------------
function getItemOrderAlternativeUnits($CON, $itemid, $itemtype, $shopid, $supplierid = 0)
{
   if($itemtype == "itemlist")
   {
      $itemlistpos = getItemListContent($CON, $itemid);
      $itemlistpos[0]["item_type"] = "item";

      $hasSupplier = false;
      $hasShop     = false;

      $itemshops = getItemShops($CON, $itemid, $itemtype);
      foreach($itemshops AS $itemshop)
         if($itemshop["shop_id"] == $shopid)
            $hasShop = true;

      if($supplierid == 0)
         $hasSupplier = true;
      else
      {
         $itemsuppliers = getItemSuppliers($CON, $itemid, $itemtype);
         foreach($itemsuppliers AS $itemsupplier)
            if($itemsupplier["supplier_id"] == $supplierid)
               $hasSupplier = true;
      }
 
      if($hasSupplier && $hasShop)
         return $itemlistpos;
   }
   else
   {
      $x = 0;
      $itemlists = getItemListsForItem($CON, $itemid);
      foreach($itemlists AS $itemlist)
      {
         $hasSupplier = false;
         $hasShop     = false;

         $itemshops = getItemShops($CON, $itemlist["id"], "itemlist");
         foreach($itemshops AS $itemshop)
            if($itemshop["shop_id"] == $shopid)
               $hasShop = true;
               
         if($supplierid == 0)
            $hasSupplier = true;
         else
         {
            $itemsuppliers = getItemSuppliers($CON, $itemlist["id"], "itemlist");
            foreach($itemsuppliers AS $itemsupplier)
               if($itemsupplier["supplier_id"] == $supplierid)
                  $hasSupplier = true;
         }
         if($hasSupplier && $hasShop)
         {
            $ret[$x]["item_id"]     = $itemlist["id"];
            $ret[$x]["item_type"]   = "itemlist";
            $ret[$x]["unit_name"]   = getItemUnitDesc($CON, $ret[$x]["item_id"], $ret[$x]["item_type"]);
            $x++;
         }
      }

      return $ret;
   }
}

//----------------------------------------------------------------------------------
function getItemListContent($CON, $itemlistid)
{
   $sql = " select t1.item_id, t1.item_pos, t2.item_title, t2.item_number,
                   t2.item_unit_amount, t3.unit_name, t3.unit_desc,
                   SUM(t1.item_amount) 'item_amount'
            from itemlist_pos t1
            LEFT OUTER JOIN item t2       ON t1.item_id = t2.id
            LEFT OUTER JOIN item_units t3 ON t2.item_unit = t3.id
            where
            t1.itemlist_id    = {$itemlistid} and
            t2.item_status    = 1 and
            t2.item_released  = 1
            group by t1.item_id
            order by t1.item_pos";
   $items = $CON->select($sql);

   return $items;
}

//----------------------------------------------------------------------------------
function getItemListsForItem($CON, $itemid)
{
   $sql = " select distinct t1.id
            from itemlist t1
            INNER JOIN itemlist_pos t2 ON t1.id = t2.itemlist_id
            where
            t2.item_id        = {$itemid} and
            t1.item_status    = 1 and
            t1.item_released  = 1";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function updateItemsInProcess($CON, $shipment_id, $mode = "supporder")
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   if($mode == "supporder")
   {
      $sql = " select t1.*
               from shipment t1
               where
               t1.id = {$shipment_id}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if((int)$headdata["shp_supporder_based"] && (int)$headdata["shp_supporder_id"])
      {
         $posdata = getShipmentPos($CON, $shipment_id);
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $row = $posdata[$x];

            if((float)$row["item_amount"] > 0.00 && (float)$row["item_amount_shipped"] > 0.00 && (int)$row["item_supporder_pos"] > -1)
            {
               $sql = " update supplier_order_items
                        set
                        item_amount_shipped = item_amount_shipped + {$row["item_amount_shipped"]}
                        where
                        sord_id  = {$headdata["shp_supporder_id"]} and
                        item_id  = {$row["item_id"]} and
                        item_pos = {$row["item_supporder_pos"]}";
               $CON->no_result($sql);
            }
         }
         autocloseSupplierOrder($CON, $headdata["shp_supporder_id"]);
      }
   }
   elseif($mode == "order")
   {
      $sql = " select t1.*
               from orders_delivery t1
               where
               t1.id = {$shipment_id}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if((int)$headdata["dlv_order_based"] && (int)$headdata["dlv_order_id"])
      {
         $posdata = getOrderDeliveryPos($CON, $shipment_id);
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $row = $posdata[$x];

            if((float)$row["item_amount"] > 0.00 && (float)$row["item_amount_shipped"] > 0.00 && (int)$row["item_order_pos"] > -1)
            {
               $sql = " update orders_items
                        set
                        item_amount_shipped = item_amount_shipped + {$row["item_amount_shipped"]}
                        where
                        req_id   = {$headdata["dlv_order_id"]} and
                        item_id  = {$row["item_id"]} and
                        item_pos = {$row["item_order_pos"]}";
               $CON->no_result($sql);
            }
         }
         autocloseSupplierOrder($CON, $headdata["dlv_order_id"], $mode);
      }

      //----------------------------------------------------------------------------------
      if($headdata["dlv_invoice_generated"] > 0)
      {
         $sql = " select t1.*
                  from invoices_sell t1
                  where
                  t1.id = {$headdata["dlv_invoice_generated"]}";
         $invcdata = $CON->select($sql);
         $invcdata = $invcdata[0];

         if($invcdata["invc_type"] == 2)
         {
            //----------------------------------------------------------------------------------
            $invcparts  = getInvoiceSellParts($CON, $headdata["dlv_invoice_generated"]);
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               if((int)$invcparts[$x]["part_req_id"])
               {
                  $partposdata = getInvoiceSellPartsItems($CON, $headdata["dlv_invoice_generated"], $invcparts[$x]["id"]);

                  for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                  {
                     $row = $partposdata[$y];
                     
                     if((float)$row["item_amount"] > 0.00 && (int)$row["item_order_pos"] > -1)
                     {
                        $sql = " update orders_items
                                 set
                                 item_amount_shipped = item_amount_shipped + {$row["item_amount"]}
                                 where
                                 req_id   = {$invcparts[$x]["part_req_id"]} and
                                 item_id  = {$row["item_id"]} and
                                 item_pos = {$row["item_order_pos"]}";
                        $CON->no_result($sql);
                        
                     }
                  }
                  autocloseSupplierOrder($CON, $invcparts[$x]["part_req_id"], $mode);
               }
            }
         }
      }
   }
   elseif($mode == "invoicesell")
   {
      $sql = " select t1.*
               from invoices_sell t1
               where
               t1.id = {$shipment_id}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if((int)$headdata["invc_type"] == 1)
      {
         //----------------------------------------------------------------------------------
         $posdata    = Array();
         $invcparts  = getInvoiceSellParts($CON, $shipment_id);
         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            if((int)$invcparts[$x]["part_dlv_id"])
            {
               $partposdata = getInvoiceSellPartsItems($CON, $shipment_id, $invcparts[$x]["id"]);

               for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               {
                  $row = $partposdata[$y];
                  if((float)$row["item_amount"] > 0.00 && (int)$row["item_dlv_pos"] > -1)
                  {
                     $sql = " update orders_delivery_items
                              set
                              item_amount_invoiced = item_amount_invoiced + {$row["item_amount"]}
                              where
                              dlv_id   = {$invcparts[$x]["part_dlv_id"]} and
                              item_id  = {$row["item_id"]} and
                              item_pos = {$row["item_dlv_pos"]}";
                     $CON->no_result($sql);
                  }
               }
               $dlvclosealways = false;
               if((int)$headdata["invc_dlv_addtxt"])
                  $dlvclosealways = true;
               autocloseSupplierOrder($CON, $invcparts[$x]["part_dlv_id"], $mode, $dlvclosealways);
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
function getShipmentPos($CON, $shipment_id)
{
   $sql = " select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note
            from shipment_items t1, item t2
            where
            t1.shipment_id = {$shipment_id} and
            t1.item_id     = t2.id and
            t1.item_type   = 'item' 
            UNION ALL
            select t1.*, t2.item_title, t2.item_number_prod, t2.item_invoicebuy_note
            from shipment_items t1, itemlist t2
            where
            t1.shipment_id = {$shipment_id} and
            t1.item_id     = t2.id and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' ''
            from shipment_items t1
            where
            t1.shipment_id = {$shipment_id} and
            t1.item_type   = 'manual'
            order by 3 asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getProdExtPos($CON, $req_id, $setPosOrder = "")
{
   $orderBy = "3";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";
      
   $sql = " select t1.*, t2.item_title, t2.item_number_prod, t3.unit_name,
                   t4.item_title 'item_titledest', t4.item_number_prod 'item_number_proddest',
                   t5.unit_name 'unit_namedest',
                   t6.item_title 'serv_title', t6.id 'serv_id'
            from prod_item_ext_pos t1
            INNER JOIN item t2                           ON t1.item_id        = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit      = t3.id
            LEFT OUTER JOIN item t4                      ON t1.item_id_dest   = t4.id
            LEFT OUTER JOIN item_units t5                ON t4.item_unit      = t5.id
            LEFT OUTER JOIN item t6                      ON t2.item_ext_prod_serv_item_id = t6.id
            where
            t1.req_id      = {$req_id} and
            t1.item_type   = 'item' 
            UNION ALL
            select t1.*, t2.item_title, t2.item_number_prod, t3.unit_name,
                   t4.item_title 'item_titledest', t4.item_number_prod 'item_number_proddest',
                   t5.unit_name 'unit_namedest',
                   t6.item_title 'serv_title', t6.id 'serv_id'
            from prod_item_ext_pos t1
            INNER JOIN itemlist t2                       ON t1.item_id        = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit      = t3.id
            LEFT OUTER JOIN itemlist t4                  ON t1.item_id_dest   = t4.id
            LEFT OUTER JOIN item_units t5                ON t4.item_unit      = t5.id
            LEFT OUTER JOIN item t6                      ON t2.item_ext_prod_serv_item_id = t6.id
            where
            t1.req_id      = {$req_id} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', '' '', '' '', '' '', '' '', '' '0'
            from prod_item_ext_pos t1
            where
            t1.req_id      = {$req_id} and
            t1.item_type   = 'manual'
            order by {$orderBy} asc";
   $posdata = $CON->select($sql);

   return $posdata;
}

//----------------------------------------------------------------------------------
function getOrderDeliveryPos($CON, $dlv_id, $setPosOrder = "")
{
   $orderBy = "3";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";
      
   $sql = " select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id,
                   t5.item_costprice_netto, t2.item_weight, t2.item_weight_price, t5.item_code,
                   t8.item_amount 'order_amount_orig', t8.item_amount_shipped 'item_amount_shipped_orig',
                   t2.item_sell_withotheritems, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t8.item_sellprice_netto 'item_sellprice_netto_orig',
                   t8.item_amount_shipped_stop, t8.item_amount_shipped_comment
            from orders_delivery_items t1
            INNER JOIN item t2                           ON t1.item_id   = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN orders_delivery t6           ON ( t1.dlv_id = t6.id )
            LEFT OUTER JOIN orders t7                    ON ( t6.dlv_order_id = t7.id )
            LEFT OUTER JOIN orders_items t8              ON ( t8.req_id = t7.id and
                                                              t1.item_order_pos = t8.item_pos and
                                                              t1.item_id = t8.item_id and
                                                              t1.item_type = t8.item_type )
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.dlv_id      = {$dlv_id} and
            t1.item_type   = 'item' 
            UNION ALL
            select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id,
                   t5.item_costprice_netto, t2.item_weight, t2.item_weight_price, t5.item_code,
                   t8.item_amount 'order_amount_orig', t8.item_amount_shipped 'item_amount_shipped_orig',
                   t2.item_sell_withotheritems, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t8.item_sellprice_netto 'item_sellprice_netto_orig',
                   t8.item_amount_shipped_stop, t8.item_amount_shipped_comment
            from orders_delivery_items t1
            INNER JOIN itemlist t2                       ON t1.item_id   = t2.id
            LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
            LEFT OUTER JOIN item_productcats_itemlist t4 ON t1.item_id = t4.item_id
            LEFT OUTER JOIN item_suppliers t5            ON ( t1.item_id = t5.item_id and t5.item_supp_act = 1 )
            LEFT OUTER JOIN orders_delivery t6           ON ( t1.dlv_id = t6.id )
            LEFT OUTER JOIN orders t7                    ON ( t6.dlv_order_id = t7.id )
            LEFT OUTER JOIN orders_items t8              ON ( t8.req_id = t7.id and
                                                              t1.item_order_pos = t8.item_pos and
                                                              t1.item_id = t8.item_id and
                                                              t1.item_type = t8.item_type )
            LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
            where
            t1.dlv_id      = {$dlv_id} and
            t1.item_type   = 'itemlist'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', '' '', '' '', '' '', '' '', '' '', '' '',
                   0 '', 0 '', 0, 0, 0, 0, 0, 0, 0, 0, '' ''
            from orders_delivery_items t1
            where
            t1.dlv_id      = {$dlv_id} and
            t1.item_type   = 'manual'
            order by {$orderBy} asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"])."-";
      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"])."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"])."-";
      if((int)$row["item_value_act"] && (float)$row["item_value_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_value_dsc"])."-";

         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }

   return $posdata;
   
}

//----------------------------------------------------------------------------------
function recalcSupplierOrderItem($CON, $sordid, $itemid, $itempos, $fullupdate = true, $simmode = false, $simsupplierid = 0)
{
   if($simmode && $simsupplierid > 0)
      $sqlmod = $simsupplierid;
   else
      $sqlmod = "t0.sord_supplier_id";
      
   $sql = " select distinct t0.sord_supplier_id, t1.*, t4.*
            from supplier_order t0
            INNER JOIN supplier_order_items t1                 ON t0.id = t1.sord_id
            INNER JOIN item t2                                 ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats t3                ON t1.item_id = t3.item_id
            LEFT OUTER JOIN dscbuy_supplier_productcats t4     ON (t4.dct_supp_id = {$sqlmod} and t3.cat_id = t4.dct_cat_id)
            where
            t0.id          = {$sordid} and
            t1.item_id     = {$itemid} and
            t1.item_pos    = {$itempos} and
            t1.item_type   = 'item'
            UNION ALL
            select distinct t0.sord_supplier_id, t1.*, t4.*
            from supplier_order t0
            INNER JOIN supplier_order_items t1                 ON t0.id = t1.sord_id
            INNER JOIN itemlist t2                             ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats_itemlist t3       ON t1.item_id = t3.item_id
            LEFT OUTER JOIN dscbuy_supplier_productcats t4     ON (t4.dct_supp_id = {$sqlmod} and t3.cat_id = t4.dct_cat_id)
            where
            t0.id          = {$sordid} and
            t1.item_id     = {$itemid} and
            t1.item_pos    = {$itempos} and
            t1.item_type   = 'itemlist'
            order by 3 asc";
   $posdata = $CON->select($sql);

   foreach($posdata AS $row)
   {
      if($simmode && $simsupplierid > 0)
         $row["sord_supplier_id"] = $simsupplierid;
         
      $selstr = $row["sord_supplier_id"]." and dct_item_id = {$row["item_id"]} and dct_item_type = '{$row["item_type"]}' ";
      
      $val_dsc = getValueDiscounts($CON, "dscbuy_supplier_item_volume", "dct_supplier_id", $selstr , $row["item_amount"]);

      $val_dsc["dct_scale_discount"]   = (float)$val_dsc["dct_scale_discount"];
      $val_dsc["dct_scale_type"]       = (int)$val_dsc["dct_scale_type"];

      for($z = 1; $z <= 4; $z++)
      {
         $row["dct_scale_discount{$z}"]   = (float)$row["dct_scale_discount{$z}"];
         $row["dct_scale_type{$z}"]       = (int)$row["dct_scale_type{$z}"];
      }

      $sql = " select *
               from dscbuy_supplier_item
               where
               dct_item_id       = {$row["item_id"]} and
               dct_item_type     = '{$row["item_type"]}' and
               dct_supplier_id   = {$row["sord_supplier_id"]}";
      $itemdsc = $CON->select($sql);
      $itemdsc = $itemdsc[0];

      if((int)$itemdsc["dct_scale_override"])
      {
         for($z = 1; $z <= 4; $z++)
         {
            $row["dct_scale_discount{$z}"]   = (float)$itemdsc["dct_scale_discount{$z}"];
            $row["dct_scale_type{$z}"]       = (int)$itemdsc["dct_scale_type{$z}"];
         }
         $row["dct_apply_level"] = $itemdsc["dct_apply_level"];
         $row["dct_apply_round"] = $itemdsc["dct_apply_round"];
      }

      if((int)$itemdsc["dct_dsc_off"])
      {
         for($z = 1; $z <= 4; $z++)
         {
            $row["dct_scale_discount{$z}"]   = 0;
            $row["dct_scale_type{$z}"]       = 0;
         }
         $row["dct_apply_level"]          = "";
         $row["dct_apply_round"]          = "";
         $val_dsc["dct_scale_discount"]   = 0;
         $val_dsc["dct_scale_type"]       = 0;
      }
               
      if(!$simmode || $simsupplierid == 0)
      {
         $sql = " update supplier_order_items
                  set ";

         if($fullupdate)
            $sql .= "item_pcat_dsc1       = {$row["dct_scale_discount1"]},
                     item_pcat_dsc2       = {$row["dct_scale_discount2"]},
                     item_pcat_dsc3       = {$row["dct_scale_discount3"]},
                     item_pcat_dsc4       = {$row["dct_scale_discount4"]},
                     item_pcat_dsctype1   = {$row["dct_scale_type1"]},
                     item_pcat_dsctype2   = {$row["dct_scale_type2"]},
                     item_pcat_dsctype3   = {$row["dct_scale_type3"]},
                     item_pcat_dsctype4   = {$row["dct_scale_type4"]},
                     item_pcat_dsc_apply_level = '{$row["dct_apply_level"]}',
                     item_pcat_dsc_apply_round = '{$row["dct_apply_round"]}', ";

         $sql .= "item_vol_dsc         = {$val_dsc["dct_scale_discount"]},
                  item_vol_dsctype     = {$val_dsc["dct_scale_type"]}
                  where
                  sord_id  = {$sordid} and
                  item_id  = {$row["item_id"]} and
                  item_pos = {$row["item_pos"]}";
         $CON->no_result($sql);
      }
      else
      {
         $_RET["ROW"] = $row;
         $_RET["VAL"] = $val_dsc;
         return $_RET;
      }
   }
}

//----------------------------------------------------------------------------------
function recalcOrderItem($CON, $reqid, $itemid, $itempos, $fullupdate = true, $mode = "", $partid = 0, $dscspec = false)
{
   $initpaydsc = 1;
   $endpaydsc  = 4;
   $dlv_mode   = 0;
   
   //----------------------------------------------------------------------------------
   $_TABLENAME    = "orders_items";
   $_TABLENAMEHD  = "orders";
   $_COLPREFIX    = "req";
   $_AMOUNTFIELD  = "item_amount";

   //----------------------------------------------------------------------------------
   if($mode == "DELIVERY")
   {
      $_TABLENAME    = "orders_delivery_items";
      $_TABLENAMEHD  = "orders_delivery";
      $_COLPREFIX    = "dlv";
      $_AMOUNTFIELD  = "item_amount_shipped";

      $sql = " select dlv_mode
               from orders_delivery 
               where
               id = {$reqid}";
      $dlv_mode = $CON->select($sql);
      $dlv_mode = (int)$dlv_mode[0]["dlv_mode"];
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "INVOICENOTE")
   {
      $_TABLENAME    = "invoices_notes_sell_items";
      $_TABLENAMEHD  = "invoices_notes_sell";
      $_COLPREFIX    = "note";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "INVOICE")
   {
      $_TABLENAME    = "invoices_sell_parts_items";
      $_TABLENAMEHD  = "invoices_sell";
      $_COLPREFIX    = "invc";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "INVOICEBOL")
   {
      $_TABLENAME    = "invoices_sell_bol_parts_items";
      $_TABLENAMEHD  = "invoices_sell_bol";
      $_COLPREFIX    = "invc";
      $_AMOUNTFIELD  = "item_amount";
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "OFFER")
   {
      $_TABLENAME    = "offers_items";
      $_TABLENAMEHD  = "offers";
      $_COLPREFIX    = "req";
      $_AMOUNTFIELD  = "item_amount";
   }
   
   $sql = " select distinct t0.{$_COLPREFIX}_cust_id, t0.{$_COLPREFIX}_paymentid, t1.*, t3.cat_id,
                   t2.item_dct_active, t2.item_dct_allprice, t2.item_dct_alltype, t2.item_sell_nodsc,
                   t4.cat_dsc_off, t4.cat_dsc_maxperc
            from {$_TABLENAMEHD} t0
            INNER JOIN {$_TABLENAME} t1                     ON t0.id = t1.{$_COLPREFIX}_id
            INNER JOIN item t2                              ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats t3             ON t1.item_id = t3.item_id
            LEFT OUTER JOIN productcats t4                  ON t3.cat_id = t4.id
            where
            t0.id          = {$reqid} and
            t1.item_id     = {$itemid} and
            t1.item_pos    = {$itempos} and
            t1.item_type   = 'item' ";
            
   if($partid > 0)
      $sql .= " and t1.part_id = {$partid} ";
      
   $sql .= "UNION ALL
            select distinct t0.{$_COLPREFIX}_cust_id, t0.{$_COLPREFIX}_paymentid, t1.*, t3.cat_id,
                   t2.item_dct_active, t2.item_dct_allprice, t2.item_dct_alltype, t2.item_sell_nodsc,
                   t4.cat_dsc_off, t4.cat_dsc_maxperc
            from {$_TABLENAMEHD} t0
            INNER JOIN {$_TABLENAME} t1                     ON t0.id = t1.{$_COLPREFIX}_id
            INNER JOIN itemlist t2                          ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats_itemlist t3    ON t1.item_id = t3.item_id
            LEFT OUTER JOIN productcats t4                  ON t3.cat_id = t4.id
            where
            t0.id          = {$reqid} and
            t1.item_id     = {$itemid} and
            t1.item_pos    = {$itempos} and
            t1.item_type   = 'itemlist' ";
            
   if($partid > 0)
      $sql .= " and t1.part_id = {$partid} ";
      
   $sql .= " order by 3 asc";
   $posdata = $CON->select($sql);

   $row = $posdata[0];

   //----------------------------------------------------------------------------------
   //if($dlv_mode < 2)
   if(1 == 1)
   {
      // GET ITEM GLOBAL DISCOUNT
      if((int)$row["item_dct_active"])
      {
         $glb_discount        = $row["item_dct_allprice"];
         $glb_discount_type   = $row["item_dct_alltype"];
      }
      else
      {
         // GET CUSTOMER > PRODUCTCAT GLOBAL DISCOUNT
         $sql = " select dct_cust_id, dct_active, dct_allprice, dct_alltype
                  from dscsell_customer_productcats_base
                  where
                  dct_cust_id = {$row["{$_COLPREFIX}_cust_id"]} and
                  dct_cat_id  = {$row["cat_id"]}";
         $cust_glb_dsc = $CON->select($sql);
         $cust_glb_dsc = $cust_glb_dsc[0];

         if((int)$cust_glb_dsc["dct_active"])
         {
            $glb_discount        = $cust_glb_dsc["dct_allprice"];
            $glb_discount_type   = $cust_glb_dsc["dct_alltype"];
         }
         else
         {
            // GET PRODUCTCAT GLOBAL DISCOUNT
            $sql = " select id, cat_dct_allprice, cat_dct_alltype
                     from productcats
                     where
                     id = {$row["cat_id"]}";
            $cat_glb_dsc = $CON->select($sql);
            $cat_glb_dsc = $cat_glb_dsc[0];

            if((int)$cat_glb_dsc["id"])
            {
               $glb_discount        = $cat_glb_dsc["cat_dct_allprice"];
               $glb_discount_type   = $cat_glb_dsc["cat_dct_alltype"];
            }
         }
      }

      //----------------------------------------------------------------------------------
      // GET ITEM PAYMENT DISCOUNTS
      $sql = " select *
               from dscsell_item_payment
               where
               dct_item_id    = {$row["item_id"]} and
               dct_item_type  = '{$row["item_type"]}' and
               dct_payid      = {$row["{$_COLPREFIX}_paymentid"]} ";
      $item_pay_dsc = $CON->select($sql);
      $item_pay_dsc = $item_pay_dsc[0];

      // GET CUSTOMER > PRODUCTCAT > PAYMENT DISCOUNTS
      if(!(int)$item_pay_dsc["dct_item_id"])
      {
         $sql = " select *
                  from dscsell_customer_productcats_payment
                  where
                  dct_cust_id = {$row["{$_COLPREFIX}_cust_id"]} and
                  dct_cat_id  = {$row["cat_id"]} and
                  dct_payid   = {$row["{$_COLPREFIX}_paymentid"]}";
         $item_pay_dsc = $CON->select($sql);
         $item_pay_dsc = $item_pay_dsc[0];

         // GET PRODUCTCAT > PAYMENT DISCOUNTS
         if(!(int)$item_pay_dsc["dct_cust_id"])
         {
            $sql = " select *
                     from dscsell_productcats_payment
                     where
                     dct_cat_id  = {$row["cat_id"]} and
                     dct_payid   = {$row["{$_COLPREFIX}_paymentid"]}";
            $item_pay_dsc = $CON->select($sql);
            $item_pay_dsc = $item_pay_dsc[0];
         }
      }

      

      //----------------------------------------------------------------------------------
      for($x = 1; $x <= 4; $x++)
      {
         $row["dct_scale_discount{$x}"]   = 0.00;
         $row["dct_scale_type{$x}"]       = 0;
      }

      if(!(int)$row["item_sell_nodsc"] && !(int)$row["cat_dsc_off"])
      {
         for($x = $initpaydsc; $x <= $endpaydsc; $x++)
         {
            if((!$dscspec && !(int)$item_pay_dsc["dct_scale_mode{$x}"]) || $dscspec)
            {
               $row["dct_scale_discount{$x}"]   = (float)$item_pay_dsc["dct_scale_discount{$x}"];
               $row["dct_scale_type{$x}"]       = (int)$item_pay_dsc["dct_scale_type{$x}"];
            }
         }
      }
      
      //----------------------------------------------------------------------------------
      // GET ITEM VOLUME DISCOUNT
      $selstr = $row["item_id"]." and dct_item_type = '{$row["item_type"]}' ";
      $val_dsc = getValueDiscounts($CON, "dscsell_item_volume", "dct_item_id", $selstr , $row[$_AMOUNTFIELD]);

      //----------------------------------------------------------------------------------
      $row["item_vol_dsc"]       = (float)$val_dsc["dct_scale_discount"];
      $row["item_vol_dsctype"]   = (int)$val_dsc["dct_scale_type"];
      $glb_discount              = (float)$glb_discount;
      $glb_discount_type         = (int)$glb_discount_type;

      if((int)$row["item_sell_nodsc"] || (int)$row["cat_dsc_off"])
      {
         $glb_discount              = 0.00;
         $glb_discount_type         = 0;
         $row["item_vol_dsc"]       = 0.00;
         $row["item_vol_dsctype"]   = 0;

         for($x = 1; $x <= 4; $x++)
         {
            $row["dct_scale_discount{$x}"]   = 0.00;
            $row["dct_scale_type{$x}"]       = 0;
         }
      }

      //----------------------------------------------------------------------------------
      if((int)$item_pay_dsc["dct_voldeact"])
      {
         $row["item_vol_dsc"]       = 0.00;
         $row["item_vol_dsctype"]   = 0;
      }

      //----------------------------------------------------------------------------------
      $sql = " update {$_TABLENAME}
               set ";

      if($fullupdate)
         $sql .= "item_pcat_dsc1       = {$row["dct_scale_discount1"]},
                  item_pcat_dsc2       = {$row["dct_scale_discount2"]},
                  item_pcat_dsc3       = {$row["dct_scale_discount3"]},
                  item_pcat_dsc4       = {$row["dct_scale_discount4"]},
                  item_pcat_dsctype1   = {$row["dct_scale_type1"]},
                  item_pcat_dsctype2   = {$row["dct_scale_type2"]},
                  item_pcat_dsctype3   = {$row["dct_scale_type3"]},
                  item_pcat_dsctype4   = {$row["dct_scale_type4"]},
                  item_discount        = {$glb_discount},
                  item_discount_type   = {$glb_discount_type}, ";
                  
      if((int)$row["item_sell_nodsc"] || (int)$row["cat_dsc_off"] || (int)$item_pay_dsc["dct_voldeact"])
         $sql .= " item_pcat_dsc_act   = 0,
                   item_vol_act        = 0,
                   item_value_act      = 0, ";
         
      //----------------------------------------------------------------------------------
      // UPDATE VOLUME DISCOUNT FOR: ORDERS, INVOICES + FULLUPDATE, MANUAL ITEMS DELIVERY
      //----------------------------------------------------------------------------------
      if(($mode == "") ||
         ($mode == "OFFER" && $fullupdate) ||
         ($mode == "INVOICENOTE" && $fullupdate) ||
         ($mode == "INVOICE" &&  ($fullupdate || ((int)$row["item_order_pos"] == -1 && (int)$row["item_dlv_pos"] == -1))) ||
         ($mode == "DELIVERY" && ($fullupdate || (int)$row["item_order_pos"] == -1)))
         $sql .= " item_vol_dsc         = {$row["item_vol_dsc"]},
                   item_vol_dsctype     = {$row["item_vol_dsctype"]}, ";

      $sql .= "item_id = item_id
               where
               {$_COLPREFIX}_id        = {$reqid} and
               item_id                 = {$itemid} and
               item_pos                = {$itempos} ";

      if($partid > 0)
         $sql .= " and part_id = {$partid} ";
   }
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function recalcSupplierOrder($CON, $sordid, $simmode = false, $simstruct = NULL, $supplierid = 0)
{
   $sord_item_netto_total  = 0.00;
   $sord_total_netto       = 0.00;
   $sord_total_taxes       = 0.00;
   $sord_total_brutto      = 0.00;
   $dsc_finance            = 0.00;
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from supplier_order t1
            where
            t1.id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $numberlim = 0;
   if(!(int)$headdata["sord_taxes"])
   {
      $numberlim  = "4";
      $numberlim2 = "2";
   }

   //----------------------------------------------------------------------------------
   $posdata = getSupplierOrderPos($CON, $sordid);

   if($simmode && $simstruct != NULL)
   {
      $temp = $posdata;
      unset($posdata);
      $posdata = Array();
      foreach($temp AS $temprow)
      {
         if($temprow["item_id"] == $simstruct["ROW"]["item_id"] && $temprow["item_pos"] == $simstruct["ROW"]["item_pos"])
         {
            $temprow["item_pcat_dsc_act"]    = 1;
            $temprow["item_vol_act"]         = 1;
            $temprow["item_costprice_netto"] = $simstruct["ROW"]["item_costprice_netto"];
            $temprow["item_pcat_dsc1"]       = $simstruct["ROW"]["dct_scale_discount1"];
            $temprow["item_pcat_dsc2"]       = $simstruct["ROW"]["dct_scale_discount2"];
            $temprow["item_pcat_dsc3"]       = $simstruct["ROW"]["dct_scale_discount3"];
            $temprow["item_pcat_dsc4"]       = $simstruct["ROW"]["dct_scale_discount4"];
            $temprow["item_pcat_dsctype1"]   = $simstruct["ROW"]["dct_scale_type1"];
            $temprow["item_pcat_dsctype2"]   = $simstruct["ROW"]["dct_scale_type2"];
            $temprow["item_pcat_dsctype3"]   = $simstruct["ROW"]["dct_scale_type3"];
            $temprow["item_pcat_dsctype4"]   = $simstruct["ROW"]["dct_scale_type4"];
            $temprow["item_vol_dsc"]         = $simstruct["VAL"]["dct_scale_discount"];
            $temprow["item_vol_dsctype"]     = $simstruct["VAL"]["dct_scale_type"];
            $temprow["item_amount"]          = $simstruct["ROW"]["item_amount"];
            $temprow["item_pcat_dsc_apply_level"] = "ITEM";
            $temprow["item_pcat_dsc_apply_round"] = "UP";

            $posdata[] = $temprow;
         }
      }
   }

   //----------------------------------------------------------------------------------
   foreach($posdata AS $row)
   {
      if($headdata["sord_type"] == 1 || $headdata["sord_type"] == 2)
      {
         $row["item_amount"] = $row["item_kgs"];
      }
      
      if($row["item_pcat_dsc_apply_level"] == "TOTAL" || $row["item_pcat_dsc_apply_level"] == "")
         $prc_netto = $row["item_costprice_netto"] * $row["item_amount"];
      else
         $prc_netto = $row["item_costprice_netto"];

      //----------------------------------------------------------------------------------
      // CAT DISCOUNTS
      //----------------------------------------------------------------------------------
      if((int)$row["item_pcat_dsc_act"])
      {
         for($x = 1; $x <=4; $x++)
         {
            if($row["item_pcat_dsc{$x}"] > 0.00)
            {
               $new_netto = calcDCTDiscount($prc_netto, $row["item_pcat_dsc{$x}"], $row["item_pcat_dsctype{$x}"], 4);

               $nextx1 = $x +1;
               $nextx2 = $x +2;
               $nextx3 = $x +3;
               if($x == 4 || ((float)$row["item_pcat_dsc{$nextx1}"] == 0.00 && (float)$row["item_pcat_dsc{$nextx2}"] == 0.00 && (float)$row["item_pcat_dsc{$nextx3}"] == 0.00))
               {
                  $new_netto["val_val"] = round($new_netto["val_val"], 20);

                  if($row["item_pcat_dsc_apply_round"] == "UP")
                     $new_netto["val_val"] = round_up($new_netto["val_val"], $numberlim);
                  elseif($row["item_pcat_dsc_apply_round"] == "DOWN")
                     $new_netto["val_val"] = round_down($new_netto["val_val"], $numberlim);
                  else
                     $new_netto["val_val"] = round($new_netto["val_val"], $numberlim);
               }
               else
               {
                  if($row["item_pcat_dsc_apply_round"] == "UP")
                     $new_netto["val_val"] = round_up($new_netto["val_val"], 20);
                  elseif($row["item_pcat_dsc_apply_round"] == "DOWN")
                     $new_netto["val_val"] = round_down($new_netto["val_val"], 20);
                  else
                     $new_netto["val_val"] = round($new_netto["val_val"], 20);
               }
               $prc_netto = $new_netto["val_val"];
            }
         }
      }

      //----------------------------------------------------------------------------------
      // VOLUME DISCOUNT
      //----------------------------------------------------------------------------------
      if((int)$row["item_vol_act"] && $row["item_vol_dsc"] > 0.00)
      {
         $new_netto = calcDCTDiscount($prc_netto, $row["item_vol_dsc"], $row["item_vol_dsctype"], $numberlim);
         $prc_netto = $new_netto["val_val"];
      }
      
      //----------------------------------------------------------------------------------
      // MANUAL DISCOUNT
      //----------------------------------------------------------------------------------
      if($row["item_discount"] != 0.00)
      {
         $new_netto = calcDCTDiscount($prc_netto, $row["item_discount"], $row["item_discount_type"], $numberlim);
         $prc_netto = $new_netto["val_val"];
      }

      //----------------------------------------------------------------------------------
      if($row["item_pcat_dsc_apply_level"] == "TOTAL" || $row["item_pcat_dsc_apply_level"] == "")
         $prc_netto = round($prc_netto, $numberlim2);
      else
         $prc_netto = round($prc_netto * $row["item_amount"], $numberlim2);

      //----------------------------------------------------------------------------------
      if(!$simmode || $simstruct == NULL)
      {
         $sql = " update supplier_order_items
                  set
                  item_costprice_netto_dsc = {$prc_netto}
                  where
                  sord_id  = {$sordid} and
                  item_id  = {$row["item_id"]} and
                  item_pos = {$row["item_pos"]}";
         $CON->no_result($sql);
//          echo $sql;
//          echo "<pre>";
//          print_r($row);
      }
      
      $sord_item_netto_total += $prc_netto;

      $tax_struct[$row["item_costprice_taxes_perc"]] += $prc_netto;
      
      $xcounter++;
   }

   if(!$simmode || $simstruct == NULL)
   {
      //----------------------------------------------------------------------------------
      // TOTAL ORDER VALUE DISCOUNT
      //----------------------------------------------------------------------------------
      $dsc_value           = getValueDiscounts($CON, "dscbuy_supplier_value", "dct_supplier_id", $headdata["sord_supplier_id"], $sord_item_netto_total);
      $sord_value_dsc      = (float)$dsc_value["dct_scale_discount"];
      $sord_value_dsctype  = (int)$dsc_value["dct_scale_type"];
      $dsc_price           = calcDCTDiscount($sord_item_netto_total, $sord_value_dsc, $sord_value_dsctype, $numberlim);
      $dsc_price           = $dsc_price["dsc_val"];

      //----------------------------------------------------------------------------------
      // TOTAL ORDER PAYMENT DISCOUNT
      //----------------------------------------------------------------------------------
      $dsc_pay    = calcDCTDiscount(($sord_item_netto_total - $dsc_price), $headdata["sord_payment_dsc"], $headdata["sord_payment_dsctype"], $numberlim);
      $dsc_pay    = $dsc_pay["dsc_val"];

      //----------------------------------------------------------------------------------
      // TOTAL ORDER SUPPLIER FINANCE DISCOUNT
      //----------------------------------------------------------------------------------
      if($headdata["sord_supplier_dsc_finance"] > 0.00 && $headdata["sord_supplier_dsc_finance_calc"] == "OC")
      {
         $dsc_finance = calcDCTDiscount($sord_item_netto_total - $dsc_price - $dsc_pay, $headdata["sord_supplier_dsc_finance"], 0, $numberlim);
         $dsc_finance = $dsc_finance["dsc_val"];
      }

      $sord_total_netto = $sord_item_netto_total - $dsc_price - $dsc_pay - $dsc_finance;

      //----------------------------------------------------------------------------------
      // TAXES CALC
      //----------------------------------------------------------------------------------
      $ges_dscper = ($dsc_price + $dsc_pay + $dsc_finance) / $sord_item_netto_total * 100;
      foreach(array_keys($tax_struct) AS $taxval)
      {
         $total_netto       = $tax_struct[$taxval] - ($tax_struct[$taxval] / 100 * $ges_dscper);
         $total_taxes       = $total_netto / 100 * $taxval;
         $sord_total_taxes  += $total_taxes;
      }

      $sord_total_taxes    = round($sord_total_taxes, $numberlim);
      $sord_total_brutto   = $sord_total_netto + $sord_total_taxes;

      //----------------------------------------------------------------------------------
      $sql = " update supplier_order
               set
               sord_item_netto_total            = {$sord_item_netto_total},
               sord_value_dsc                   = {$sord_value_dsc},
               sord_value_dsctype               = {$sord_value_dsctype},
               sord_value_dsc_netto_total       = {$dsc_price},
               sord_payment_dsc_netto_total     = {$dsc_pay},
               sord_supplier_dsc_finance_total  = {$dsc_finance},
               sord_total_netto                 = {$sord_total_netto},
               sord_total_taxes                 = {$sord_total_taxes},
               sord_total_brutto                = {$sord_total_brutto}
               where
               id = {$sordid}";
      $CON->no_result($sql);
   }
   else
   {
      //----------------------------------------------------------------------------------
      $sql = " select supp_paymentid, supp_dsc_finance, supp_dsc_finance_calc
               from supplier
               where
               id = {$supplierid}";
      $sord_paymentid = $CON->select($sql);
      $sord_paymentid = $sord_paymentid[0];
      if($sord_paymentid["supp_dsc_finance"] > 0.00 && $sord_paymentid["supp_dsc_finance_calc"] == "OC")
      {
         $dsc_finance = calcDCTDiscount($sord_item_netto_total, $sord_paymentid["supp_dsc_finance"], 0, $numberlim);
         $dsc_finance = $dsc_finance["dsc_val"];
         $sord_item_netto_total -= $dsc_finance;
      }
      return $sord_item_netto_total;
   }
}

//----------------------------------------------------------------------------------
function getOfferPos($CON, $ordid, $setPosOrder = "")
{
   $orderBy = "3";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";
      
   $sql = " select distinct t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.cat_id, t4.item_costprice_netto,
                   t4.item_code, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t2.item_fabricate_act, t2.item_fabricate_extern_act
            from offers t0
            INNER JOIN offers_items t1          ON t0.id = t1.req_id
            INNER JOIN item t2                  ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats t3 ON t1.item_id = t3.item_id
            LEFT OUTER JOIN item_suppliers t4   ON ( t1.item_id = t4.item_id and t4.item_supp_act = 1 )
            LEFT OUTER JOIN productcats t9      ON ( t3.cat_id = t9.id )
            where
            t0.id          = {$ordid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', 0, 0, '' '', 0, 0, 0, 0, 0, 0, 0
            from offers t0
            INNER JOIN offers_items t1                   ON t0.id = t1.req_id
            where
            t0.id          = {$ordid} and
            t1.item_type   = 'manual'
            order by {$orderBy} asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"])."-";
      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"])."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"])."-";
      if((int)$row["item_value_act"] && (float)$row["item_value_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_value_dsc"])."-";

         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }

   return $posdata;
}

//----------------------------------------------------------------------------------
function precalcSupplierCost($CON, $itemid, $itemtype)
{
   if($itemtype == "itemlist")
      $suffix = "_itemlist";
      
   $sql = " select t1.item_costprice_netto, t3.cat_id, t1.supplier_id, t4.*, t5.supp_dsc_finance
            from {$itemtype}_suppliers t1
            INNER JOIN {$itemtype} t2                       ON t1.item_id = t2.id
            INNER JOIN item_productcats{$suffix} t3         ON t2.id = t3.item_id
            LEFT OUTER JOIN dscbuy_supplier_productcats t4  ON (t4.dct_supp_id = t1.supplier_id and t3.cat_id = t4.dct_cat_id)
            INNER JOIN supplier t5                          ON t1.supplier_id = t5.id
            where
            t1.item_id = {$itemid} and
            t1.item_supp_act = 1";
   $itemdata = $CON->select($sql);
   $itemdata = $itemdata[0];

   $sql = " select *
            from dscbuy_supplier_item
            where
            dct_item_id       = {$itemid} and
            dct_item_type     = '{$itemtype}' and
            dct_supplier_id   = {$itemdata["supplier_id"]}";
   $itemdsc = $CON->select($sql);
   $itemdsc = $itemdsc[0];

   if((int)$itemdsc["dct_scale_override"])
   {
      for($z = 1; $z <= 4; $z++)
      {
         $itemdata["dct_scale_discount{$z}"]   = (float)$itemdsc["dct_scale_discount{$z}"];
         $itemdata["dct_scale_type{$z}"]       = (int)$itemdsc["dct_scale_type{$z}"];
      }
      $itemdata["dct_apply_level"] = $itemdsc["dct_apply_level"];
      $itemdata["dct_apply_round"] = $itemdsc["dct_apply_round"];
   }

   if((int)$itemdsc["dct_dsc_off"])
   {
      for($z = 1; $z <= 4; $z++)
      {
         $itemdata["dct_scale_discount{$z}"]   = 0;
         $itemdata["dct_scale_type{$z}"]       = 0;
      }
      $itemdata["dct_apply_level"]     = "";
      $itemdata["dct_apply_round"]     = "";
   }

   $prc_netto = $itemdata["item_costprice_netto"];
   for($x = 1; $x <=4; $x++)
   {
      if($itemdata["dct_scale_discount{$x}"] > 0.00)
      {
         $new_netto = calcDCTDiscount($prc_netto, $itemdata["dct_scale_discount{$x}"], $itemdata["dct_scale_type{$x}"], 4);

         $nextx1 = $x +1;
         $nextx2 = $x +2;
         $nextx3 = $x +3;
         if($x == 4 || ((float)$itemdata["dct_scale_discount{$nextx1}"] == 0.00 && (float)$itemdata["dct_scale_discount{$nextx2}"] == 0.00 && (float)$itemdata["dct_scale_discount{$nextx3}"] == 0.00))
            $new_netto["val_val"] = round($new_netto["val_val"], 2);
         else
         {
            if($itemdata["dct_apply_round"] == "UP")
               $new_netto["val_val"] = round_up($new_netto["val_val"], 2);
            elseif($itemdata["dct_apply_round"] == "DOWN")
               $new_netto["val_val"] = round_down($new_netto["val_val"], 2);
            else
               $new_netto["val_val"] = round($new_netto["val_val"], 2);
         }
         $prc_netto = $new_netto["val_val"];
      }
   }
   $prc_netto = round($prc_netto, 0);
   $prc_netto = $prc_netto - ($prc_netto / 100 * (float)$itemdata["supp_dsc_finance"]);
   $prc_netto = round($prc_netto, 0);

   return $prc_netto;
}

//----------------------------------------------------------------------------------
function getProdnumerCallbackRes($a, $b)
{
   if((int)$a["item_number_prod"] == (int)$b["item_number_prod"])
      return ((int)$a["item_id"] > (int)$b["item_id"]);
   else
      return ((int)$a["item_number_prod"] > (int)$b["item_number_prod"]);
}

//----------------------------------------------------------------------------------
function getOrderPos($CON, $ordid, $setPosOrder = "")
{
   // $orderBy = "3";
   // if($setPosOrder == "prodnumber")
      // $orderBy = "item_number_prod";

   $sql = " select distinct t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.cat_id, t4.item_costprice_netto,
                   t4.item_code, t5.unit_name, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                   t9.cat_dsc_maxperc, t9.cat_sellprice_min, t2.item_weight, t2.item_weight_price, t11.ubi_name,
                   t2.item_unitembalaje_amount, t2.item_fabricate_act
            from orders t0
            INNER JOIN orders_items t1          ON t0.id = t1.req_id
            INNER JOIN item t2                  ON t1.item_id = t2.id
            LEFT OUTER JOIN item_productcats t3 ON t1.item_id = t3.item_id
            LEFT OUTER JOIN item_suppliers t4   ON ( t1.item_id = t4.item_id and t4.item_supp_act = 1 )
            LEFT OUTER JOIN item_units t5       ON t2.item_unit = t5.id
            LEFT OUTER JOIN productcats t9      ON ( t3.cat_id = t9.id )
            LEFT OUTER JOIN ubicacion t11       ON t2.item_ubicacion = t11.id
            where
            t0.id          = {$ordid} and
            t1.item_type   = 'item'
            UNION ALL
            select t1.*, t1.item_desc 'item_title', '' '', '' '', 0, 0, '' '', '' '', 0, 0, 0, 0, 0, 0, 0, '' '', '' '', 0
            from orders t0
            INNER JOIN orders_items t1                   ON t0.id = t1.req_id
            where
            t0.id          = {$ordid} and
            t1.item_type   = 'manual'
            order by item_number_prod asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"])."-";
      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"])."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"])."-";
      if((int)$row["item_value_act"] && (float)$row["item_value_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_value_dsc"])."-";

         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }

   return $posdata;
}

//----------------------------------------------------------------------------------
function getSupplierOrderPos($CON, $sordid, $setPosOrder = "", $posid = 0)
{
   $orderBy = "3";
   if($setPosOrder == "prodnumber")
      $orderBy = "item_number_prod";
      
   $sql = " select distinct t1.*, t2.item_title, t2.item_number_prod, t3.item_code, t2.item_invoicebuy_note,
                   t2.item_unit_amount, t2.item_unitbuy_act, t2.item_unitbuy, t2.item_unitbuy_amount,
                   t2.item_nameshop, t2.item_img
            from supplier_order t0
            INNER JOIN supplier_order_items t1     ON t0.id = t1.sord_id
            INNER JOIN item t2                     ON t1.item_id = t2.id
            LEFT OUTER JOIN item_suppliers t3      ON (t1.item_id = t3.item_id and t3.supplier_id = t0.sord_supplier_id)
            where
            t0.id          = {$sordid} and
            t1.item_type   = 'item' ";
   if((int)$posid)
      $sql .= " and t1.id = {$posid} ";
   $sql .= " UNION ALL
            select distinct t1.*, t1.item_desc 'item_title', '' '', '' '', '' '', '' '', '' '', '' '', '' '', '' '',''
            from supplier_order t0
            INNER JOIN supplier_order_items t1     ON t0.id = t1.sord_id
            where
            t0.id          = {$sordid} and
            t1.item_type   = 'manual' ";
   if((int)$posid)
      $sql .= " and t1.id = {$posid} ";
   $sql .= " order by {$orderBy} asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row     = $posdata[$x];
      $dscstr  = "";

      if((int)$row["item_pcat_dsc_act"])
      {
         for($y = 1; $y <= 4; $y++)
            if($row["item_pcat_dsc{$y}"] > 0.00)
               $dscstr .= printPrice($row["item_pcat_dsc{$y}"],2)."-";
      }
      if((int)$row["item_vol_act"] && (float)$row["item_vol_dsc"] > 0.00)
         $dscstr .= printPrice($row["item_vol_dsc"],2)."-";
      if((float)$row["item_discount"] > 0.00)
         $dscstr .= printPrice($row["item_discount"],2)."-";
         
      $posdata[$x]["_dsc_str"] = substr($dscstr, 0, -1);
   }
   
   return $posdata;
}

//----------------------------------------------------------------------------------
function getValueDiscounts($CON, $table, $field, $itemid, $amount)
{
   $sql = " select *
            from {$table}
            where
            {$field} = {$itemid}
            order by dct_pos asc ";
   $discounts = $CON->select($sql);

   for($x = 0; $x < count($discounts) && $discounts != false; $x++)
   {
      if(($amount >= $discounts[$x]["dct_scale_amtfrom"] && $amount <= $discounts[$x]["dct_scale_amtto"]) ||
         ($amount >= $discounts[$x]["dct_scale_amtfrom"] && $x == count($discounts) -1))
      {
         $ret["dct_scale_discount"]    = $discounts[$x]["dct_scale_discount"];
         $ret["dct_scale_type"]        = $discounts[$x]["dct_scale_type"];
         $ret[$field]                  = $discounts[$x][$field];
      }
   }
   
   return $ret;
}

//----------------------------------------------------------------------------------
function getOrderPartPrice($CON, $order_id, $part_id)
{
   $sql = " select req_tax_active
            from orders
            where
            id = {$order_id}";
   $hastaxes   = $CON->select($sql);
   $hastaxes   = (int)$hastaxes[0]["req_tax_active"];

   if($hastaxes)
      $field = "item_sellprice_brutto";
   else
      $field = "item_sellprice_netto";
   
   $gesprice   = 0.00;
   $posdata    = getOrderPartPos($CON, $order_id, $part_id, "rel");
   
   foreach($posdata AS $pos)
      $gesprice += ($pos[$field] * $pos["item_amount"]);

   return $gesprice;
}

//----------------------------------------------------------------------------------
function getOrderPartPos($CON, $order_id, $part_id, $mode = "all")
{
   $sql = " select t1.*, t2.item_title
            from orders_parts_items t1, item t2
            where
            t1.req_id      = {$order_id} and
            t1.part_id     = {$part_id} and
            t1.item_id     = t2.id and
            t1.item_type   = 'item' ";
   if($mode == "rel")
      $sql .= " and t1.item_relevant = 1 ";
   $sql .= "UNION ALL
            select t1.*, t2.item_title
            from orders_parts_items t1, itemlist t2
            where
            t1.req_id      = {$order_id} and
            t1.part_id     = {$part_id} and
            t1.item_id     = t2.id and
            t1.item_type   = 'itemlist' ";
   if($mode == "rel")
      $sql .= " and t1.item_relevant = 1 ";
   $sql .= "UNION ALL
            select t1.*, t1.item_desc 'item_title'
            from orders_parts_items t1
            where
            t1.req_id      = {$order_id} and
            t1.part_id     = {$part_id} and
            t1.item_type   = 'manual' ";
   if($mode == "rel")
      $sql .= " and t1.item_relevant = 1 ";
   $sql .= " order by 4 asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getOrderParts($CON, $order_id)
{
   $sql = " select *
            from orders_parts
            where
            part_req_id = {$order_id}
            order by id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function checkOrderStorehouseAssignments($CON, $order_id)
{
   $ret = true;
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from orders t1
            where
            t1.id = {$order_id}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $partdata = getOrderParts($CON, $order_id);

   foreach($partdata AS $part)
   {
      $posdata = getOrderPartPos($CON, $order_id, $part["id"]);

      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row = $posdata[$x];
         $itemsts = getItemStorehouses($CON, $headdata["req_shopid"], $row["item_id"], $row["item_type"]);

         //----------------------------------------------------------------------------------
         $componentmode = false;
         if($row["item_type"] == "itemlist")
         {
            $sql = " select item_stock_set
                     from itemlist
                     where
                     id = {$row["item_id"]}";
            $item_stock_set = $CON->select($sql);
            $item_stock_set = (int)$item_stock_set[0]["item_stock_set"];

            if(!$item_stock_set)
            {
               $componentmode = true;
               $itemsts       = NULL;
            }
         }

         //----------------------------------------------------------------------------------
         if($componentmode)
         {
            $itemlistpos = getItemListContent($CON, $row["item_id"]);
            $subposcounter = 0;
            foreach($itemlistpos AS $itemlistrow)
            {
               $amount_calced = $itemlistrow["item_amount"] * $row["item_amount"];
               $itemsts       = getItemStorehouses($CON, $headdata["req_shopid"], $itemlistrow["item_id"], "item");

               if(count($itemsts) > 1)
               {
                  $sql = " select SUM(item_amount_shipped) 'st_shipped'
                           from orders_parts_items_storehouses
                           where
                           req_id      = {$order_id} and
                           part_id     = {$part["id"]} and
                           item_id     = {$row["item_id"]} and
                           item_pos    = {$row["item_pos"]} and
                           item_type   = '".sprintf("%03d",$subposcounter)."itemlistpos'";
                  $st_shipped = $CON->select($sql);
                  $st_shipped = (float)$st_shipped[0]["st_shipped"];

                  if($st_shipped != $amount_calced)
                     $ret = false;
               }

               $subposcounter++;
            }
         }
         //----------------------------------------------------------------------------------
         elseif(count($itemsts) > 1)
         {
            $sql = " select SUM(t1.item_amount_shipped) 'st_shipped'
                     from
                     orders_parts_items_storehouses t1
                     where
                     req_id      = {$order_id} and
                     part_id     = {$part["id"]} and
                     item_id     = {$row["item_id"]} and
                     item_pos    = {$row["item_pos"]} and
                     item_type   = '{$row["item_type"]}' ";
            $st_shipped = $CON->select($sql);
            $st_shipped = (float)$st_shipped[0]["st_shipped"];

            if($st_shipped != $row["item_amount"])
               $ret = false;
         }
      }
   }

   return $ret;
}

//----------------------------------------------------------------------------------
function getItemSubItem($CON, $item_id)
{
   $sql = " select item_int_prod_act
            from item
            where
            id = {$item_id}";
   $item_int_prod_act = $CON->select($sql);
   $item_int_prod_act = (int)$item_int_prod_act[0]["item_int_prod_act"];

   if($item_int_prod_act)
   {
      $sql = " select prod_item_id, prod_item_amount
               from prod_item_int_pos
               where
               item_id = {$item_id}";
      $subitem = $CON->select($sql);
      $subitem = $subitem[0];

      return $subitem;
   }
   return false;
}

//----------------------------------------------------------------------------------
function getItemStorehouses($CON, $shop_id, $item_id, $item_type, $orderbyamt = false, $sthidrestrict = 0, $reserva = 0, $reservalast = false)
{
   $orderbyamt = true;
   $sql = " select t2.id 'st_id', t2.st_name, t2.st_repuestos_act
            from company_shops_storehouses t2 ";
   if($orderbyamt)
      $sql .= " LEFT OUTER JOIN item_shops_storehouses t1 ON ( t1.st_id = t2.id and t1.item_id = {$item_id} ) ";
   $sql .= " where
             t2.st_status = 1 and
             t2.st_shop_id = {$shop_id} ";

   if((int)$sthidrestrict)
      $sql .= " and t2.id = {$sthidrestrict} ";

   if($reserva == 1)
      $sql .= " and t2.st_reserva = 1 ";
   if($reserva == 2)
      $sql .= " and t2.st_reserva = 0 ";

   if($reservalast)
      $sql .= " order by t2.st_reserva asc, t1.iss_inventory desc, t2.st_name asc";
   else
   {
      if($orderbyamt)
         $sql .= " order by t1.iss_inventory desc, t2.st_name asc";
      else
         $sql .= " order by t2.st_name";
   }
   $itemsts = $CON->select($sql);

   foreach($itemsts AS $itemst)
      $ret[$itemst["st_id"]] = $itemst["st_name"];

   return $ret;
}

//----------------------------------------------------------------------------------
function getShipmentStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 0: return "<b style='color:#999999'>Borrado</b>"; break;
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
         case 3: return "<b style='color:navy'>Archivado</b>"; break;
         // case 3: return "<b style='color:navy'>Facturado</b>"; break;
         case 4: return "<b style='color:navy'>Generado por factura</b>"; break;
         case 5: return "<b style='color:#333333'>Anulado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 0: return "Borrado"; break;
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
         case 3: return "Archivado"; break;
         // case 3: return "Facturado"; break;
         case 4: return "Generado por factura"; break;
         case 5: return "Anulado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getOrdersDeliveryDlvMode($CON, $mode)
{
   switch($mode)
   {
      case 0: return "A cliente &gt; Manual"; break;
      case 1: return "A cliente &gt; Basado en confirmación de compra"; break;
      case 2: return "A cliente &gt; Traslado sin valor comercial"; break;
      case 3: return "Al proveedor &gt; Manual"; break;
      case 4: return "Al proveedor &gt; Traslado sin valor comercial"; break;
   }
}

//----------------------------------------------------------------------------------
function getGuaranteeStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b style='color:darkorange'>Finalizado</b>"; break;
         case 3: return "<b class='msg_save_ok'>Archivado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
         case 3: return "Archivado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getStockcountStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>Pendiente</b>"; break;
         case 2: return "<b style='color:darkorange'>Aprobado</b>"; break;
         case 3: return "<b class='msg_save_ok'>Finalizado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "Pendiente"; break;
         case 2: return "Aprobado"; break;
         case 3: return "Finalizado"; break;
      }
   }
}


//----------------------------------------------------------------------------------
function getSupplierAccOrderStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getOrderStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
         case 3: return "<b style='color:purple'>Enviado</b>"; break;
         case 4: return "<b style='color:navy'>Despachado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
         case 3: return "Enviado"; break;
         case 4: return "Despachado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getOfferStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
         case 3: return "<b style='color:purple'>Aceptado</b>"; break;
         case 4: return "<b style='color:navy'>Rechazado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
         case 3: return "Aceptado"; break;
         case 4: return "Rechazado"; break;
      }
   }
}


//----------------------------------------------------------------------------------
function getSupplierOrderStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
         case 3: return "<b style='color:purple'>Enviado</b>"; break;
         case 4: return "<b style='color:navy'>Archivado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
         case 3: return "Enviado"; break;
         case 4: return "Archivado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getSupplierContentdorStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En preparacion</b>"; break;
         case 2: return "<b style='color:purple'>En transito</b>"; break;
         case 3: return "<b style='color:#666666'>Recibido</b>"; break;
         case 4: return "<b class='msg_save_ok'>Archivado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En preparacion"; break;
         case 2: return "En transito"; break;
         case 3: return "Recibido"; break;
         case 4: return "Archivado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getInvoiceBuyStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b style='color:darkorange'>Por pagar</b>"; break;
         case 3: return "<b class='msg_save_ok'>Pagado</b>"; break;
         case 4: return "<b style='color:#333333'>Anulado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Por pagar"; break;
         case 3: return "Pagado"; break;
         case 4: return "Anulado"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getProdExtStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b style='color:darkorange'>Enviado</b>"; break;
         case 3: return "<b class='msg_save_ok'>Recibido</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Enviado"; break;
         case 3: return "Recibido"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getInvoiceBuyType($type)
{
   switch($type)
   {
      case 1: return "Basado en Guias de despacho"; break;
      case 2: return "Basado en Ordenes de compra"; break;
      case 3: return "Manual"; break;
      case 4: return "Manual (asignar como costo a otra factura)"; break;
   }
}

//----------------------------------------------------------------------------------
function getInvoiceSellType($type)
{
   switch($type)
   {
      case 1: return "Basado en Guias de despacho"; break;
      case 2: return "Basado en Confirm. de Compra"; break;
      case 3: return "Manual"; break;
   }
}

//----------------------------------------------------------------------------------
function getInvoiceBuyNoteType($type)
{
   switch($type)
   {
      case 1: return "Nota de credito"; break;
      case 2: return "Nota de debito"; break;
   }
}

//----------------------------------------------------------------------------------
function itemIsListedForShop($CON, $item_id, $item_type, $shop_id)
{
   if($item_type == "item")
   {
      $sql = " select count(*) 'cc'
               from item_shops
               where
               item_id  = {$item_id} and
               shop_id  = {$shop_id}";
   }
   else
   {
      $sql = " select count(*) 'cc'
               from itemlist_shops
               where
               item_id  = {$item_id} and
               shop_id  = {$shop_id}";
   }

   $listingcheck = $CON->select($sql);

   return (int)$listingcheck[0]["cc"];
}

//----------------------------------------------------------------------------------
/*
function itemIsListedForShopStorehouse($CON, $item_id, $item_type, $shop_id, $st_id)
{
   if($item_type == "item")
   {
      $sql = " select count(t2.*) 'cc'
               from item_shops t1
               INNER JOIN item_shops_storehouses t2 ON ( t1.item_id = t2.item_id and t1.shop_id = t2.shop_id )
               where
               t1.item_id  = {$item_id} and
               t1.shop_id  = {$shop_id} and
               t2.st_id    = {$st_id} ";
   }
   else
   {
      $sql = " select count(*) 'cc'
               from itemlist_shops
               where
               item_id  = {$item_id} and
               shop_id  = {$shop_id}";
   }

   $listingcheck = $CON->select($sql);

   return (int)$listingcheck[0]["cc"];
}
*/

//----------------------------------------------------------------------------------
function createSupplierOrdersFromAcc($CON, $sordid)
{
   $currtme = time();
   
   $sql = " select *
            from supplier_accorder
            where
            id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $headdata["sord_title"] = trim(addslashes($headdata["sord_title"]));
   $headdata["sord_desc"]  = trim(addslashes($headdata["sord_desc"]));

   $sql = " select distinct t1.item_supplier_id, t2.shop_id
            from supplier_accorder_items t1
            INNER JOIN supplier_accorder_amounts t2 ON ( t1.sord_id = t2.sord_id and t1.item_id = t2.item_id and t1.item_pos = t2.item_pos)
            where
            t1.sord_id = {$sordid}
            order by t1.item_pos asc";
   $suppshops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   foreach($suppshops AS $suppshop)
   {
      $sord_num = createTransactionNumber($CON, $headdata["sord_company_id"], "order");

      $sql = " select supp_delivery_days
               from supplier
               where
               id = {$suppshop["item_supplier_id"]}";
      $delivdays  = $CON->select($sql);
      $delivdays  = (int)$delivdays[0]["supp_delivery_days"];
      $sord_date  = mktime(0, 0, 0, date('m'), date('d'), date('Y')) + ($delivdays * 86400);

      //----------------------------------------------------------------------------------
      $sql = " insert into supplier_order
               (sord_number, sord_supplier_id, sord_company_id, sord_shop_id, sord_date,
                sord_title, sord_desc, sord_crtdat, sord_crtusr)
               VALUES
               ('{$sord_num}', {$suppshop["item_supplier_id"]}, {$headdata["sord_company_id"]},
                 {$suppshop["shop_id"]}, {$sord_date}, '{$headdata["sord_title"]}', '{$headdata["sord_desc"]}',
                 {$currtme}, {$_SESSION["user_id"]})";
      $res = $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from supplier_order
                  where
                  sord_crtusr = {$_SESSION["user_id"]}";
         $newid = $CON->select($sql);
         $newid = (int)$newid[0]["id"];

         $sql = " select t1.*, t2.item_amount
                  from supplier_accorder_items t1
                  INNER JOIN supplier_accorder_amounts t2 ON ( t1.sord_id = t2.sord_id and t1.item_id = t2.item_id and t1.item_pos = t2.item_pos)
                  where
                  t1.sord_id           = {$sordid} and
                  t1.item_supplier_id  = {$suppshop["item_supplier_id"]} and
                  t2.shop_id           = {$suppshop["shop_id"]}
                  order by t1.item_pos asc";
         $posdata = $CON->select($sql);

         //----------------------------------------------------------------------------------
         $poscounter = 0;
         foreach($posdata AS $row)
         {
            $sql = " insert into supplier_order_items
                     (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                     VALUES
                     ({$newid}, {$row["item_id"]}, {$poscounter}, {$row["item_amount"]}, '{$row["item_type"]}',
                      {$row["item_costprice_brutto"]}, {$row["item_costprice_taxes_perc"]}, {$row["item_costprice_netto"]},
                      {$row["item_costprice_taxes"]} )";
            $CON->no_result($sql);
            $poscounter++;
         }
      } 
   }
}

//----------------------------------------------------------------------------------
function getItemAverageCost($CON, $company_id, $item_id)
{
   $sql = " select *
            from tran_average_costprices
            where
            item_id     = {$item_id} and
            company_id  = {$company_id}";
   $avgcost = $CON->select($sql);
   $avgcost = $avgcost[0];

   if((int)$avgcost["item_id"])
      return round($avgcost["item_costprice_avg_netto"],8);
   return false;
}

//----------------------------------------------------------------------------------
function getSupplierItemCode($CON, $supplierid, $itemid, $itemtype)
{
   if($itemtype == "item")
   {
      $sql = " select item_code
               from item_suppliers t1
               where
               t1.item_id = {$itemid} and ";
      if($supplierid == 0)
         $sql .= " t1.item_supp_act = 1 ";
      else
         $sql .= " t1.supplier_id = {$supplierid}";
   }
   else
   {
      $sql = " select item_code
               from itemlist_suppliers t1
               where
               t1.item_id = {$itemid} and ";
      if($supplierid == 0)
         $sql .= " t1.item_supp_act = 1 ";
      else
         $sql .= " t1.supplier_id = {$supplierid}";
   }
   $item_code = $CON->select($sql);

   return $item_code[0]["item_code"];
}

//----------------------------------------------------------------------------------
function autocloseSupplierOrder($CON, $sordid, $mode = "supporder", $dlvclosealways = false)
{
   $currtme = time();

   if($mode == "supporder")
   {
      $sql = " select *
               from supplier_order
               where
               id = {$sordid}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if($headdata["sord_order_shipped"] == 0 && $headdata["sord_status"] < 4)
      {
         $sql = " select *
                  from supplier_order_items
                  where
                  sord_id = {$sordid}";
         $posdata = $CON->select($sql);

         $closeorderauto = true;
         foreach($posdata AS $row)
         {
            $item_amount         = $row["item_amount"];
            $item_amount_shipped = $row["item_amount_shipped"];

            if($item_amount_shipped < $item_amount)
               $closeorderauto = false;
         }

         if($closeorderauto)
         {
            $sql = " update supplier_order
                     set
                     sord_status          = 4,
                     sord_order_shipped   = 1,
                     sord_updusr          = {$_SESSION["user_id"]},
                     sord_upddat          = {$currtme}
                     where
                     id = {$sordid}";
            $CON->no_result($sql);
         }
      }
   }
   elseif($mode == "order")
   {
      $sql = " select *
               from orders
               where
               id = {$sordid}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if($headdata["req_order_shipped"] == 0 && $headdata["req_status"] < 4)
      {
         $sql = " select *
                  from orders_items
                  where
                  req_id = {$sordid}";
         $posdata = $CON->select($sql);

         $closeorderauto = true;
         foreach($posdata AS $row)
         {
            $item_amount         = $row["item_amount"];
            $item_amount_shipped = $row["item_amount_shipped"];

            if($item_amount_shipped < $item_amount && !(int)$row["item_amount_shipped_stop"])
               $closeorderauto = false;
         }

         if($closeorderauto)
         {
            $sql = " update orders
                     set
                     req_status          = 4,
                     req_order_shipped   = 1,
                     req_updusr          = {$_SESSION["user_id"]},
                     req_upddat          = {$currtme}
                     where
                     id = {$sordid}";
            $CON->no_result($sql);
            return true;
         }
         return false;
      }
      return false;
   }
   elseif($mode == "invoicesell")
   {
      $sql = " select *
               from orders_delivery
               where
               id = {$sordid}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      if($headdata["dlv_invoiced"] == 0 && $headdata["req_status"] < 3)
      {
         $sql = " select *
                  from orders_delivery_items
                  where
                  dlv_id = {$sordid}";
         $posdata = $CON->select($sql);

         $closeorderauto = true;
         foreach($posdata AS $row)
         {
            $item_amount         = $row["item_amount_shipped"];
            $item_amount_shipped = $row["item_amount_invoiced"];

            if($item_amount_shipped < $item_amount)
               $closeorderauto = false;
         }
         if($dlvclosealways)
            $closeorderauto = true;

         if($closeorderauto)
         {
            $sql = " update orders_delivery
                     set
                     dlv_status          = 3,
                     dlv_invoiced        = 1,
                     dlv_crtusr          = {$_SESSION["user_id"]},
                     dlv_upddat          = {$currtme}
                     where
                     id = {$sordid}";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
function getItemShopCurrentStock($CON, $shopid, $itemid, $itemtype, $breakdown = false, $reserva = 0)
{
   if($itemtype == "item")
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from item_shops_storehouses t1
               LEFT OUTER JOIN company_shops_storehouses t2 ON t1.st_id = t2.id
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid} and
               t2.st_status = 1 ";
      if($reserva == 1)
         $sql .= " and t2.st_reserva = 1 ";
      if($reserva == 2)
         $sql .= " and t2.st_reserva = 0 ";
   }
   else
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from itemlist_shops_storehouses t1
               LEFT OUTER JOIN company_shops_storehouses t2 ON t1.st_id = t2.id
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid} and
               t2.st_status = 1 ";
      if($reserva == 1)
         $sql .= " and t2.st_reserva = 1 ";
      if($reserva == 2)
         $sql .= " and t2.st_reserva = 0 ";
   }

   if($breakdown == true && $itemtype == "itemlist")
   {
      $posamt = 0;
      $shopstock = 0;
      $itemlistpos   = getItemListContent($CON, $itemid);
      foreach($itemlistpos AS $itemlistrow)
         $posamt += $itemlistrow["item_amount"];

      $itemsts = getItemStorehouses($CON, $shopid, $itemlistpos[0]["item_id"],"item");
      foreach(array_keys($itemsts) AS $stid)
      {
         $currstock = getItemShopStorehouseCurrentStock($CON, $shopid, $stid, $itemlistpos[0]["item_id"], "item");
         $currstock = $currstock / $posamt;
         $shopstock += $currstock;
      }
      return $shopstock;
   }

   $stockcount = $CON->select($sql);
   $stockcount = $stockcount[0]["iss_inventory"];

   return $stockcount;
}

//----------------------------------------------------------------------------------
function getItemShopStorehouseCurrentStock($CON, $shopid, $stid, $itemid, $itemtype, $breakdown = false, $orderid = 0)
{
   $orderid = (int)$orderid;
   if($itemtype == "item")
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from item_shops_storehouses t1
               where
               t1.item_id  = {$itemid} and
               t1.shop_id  = {$shopid} and
               t1.st_id    = {$stid} and
               t1.iss_order_id = {$orderid} ";
   }
   else
   {
      $sql = " select SUM(t1.iss_inventory) 'iss_inventory'
               from itemlist_shops_storehouses t1
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid} and
               t1.st_id   = {$stid} and
               t1.iss_order_id = {$orderid} ";
   }

   if($breakdown == true && $itemtype == "itemlist")
   {
      $posamt = 0;
      $itemlistpos   = getItemListContent($CON, $itemid);
      foreach($itemlistpos AS $itemlistrow)
         $posamt += $itemlistrow["item_amount"];

      $currstock = getItemShopStorehouseCurrentStock($CON, $shopid, $stid, $itemlistpos[0]["item_id"], "item");
      $currstock = $currstock / $posamt;
      return $currstock;
   }

   $stockcount = $CON->select($sql);
   $stockcount = (float)$stockcount[0]["iss_inventory"];

   return $stockcount;
}

//----------------------------------------------------------------------------------
function getItemShopStorehouseMinStock($CON, $shopid, $stid, $itemid, $itemtype, $breakdown = false, $pedmode = false)
{
   $dbfield = "iss_inventory_min";
   if($pedmode)
      $dbfield = "iss_order_amount";
      
   if($itemtype == "item")
   {
      $sql = " select t1.{$dbfield}
               from item_shops_storehouses t1
               where
               t1.item_id  = {$itemid} and
               t1.shop_id  = {$shopid} and
               t1.st_id    = {$stid}";
   }
   else
   {
      $sql = " select t1.{$dbfield}
               from itemlist_shops_storehouses t1
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid}
               t1.st_id   = {$stid}";
   }

   /*
   if($breakdown == true && $itemtype == "itemlist")
   {
      $posamt = 0;
      $itemlistpos   = getItemListContent($CON, $itemid);
      foreach($itemlistpos AS $itemlistrow)
         $posamt += $itemlistrow["item_amount"];

      $minstock = getItemShopStorehouseMinStock($CON, $shopid, $stid, $itemlistpos[0]["item_id"], "item");
      $minstock = $minstock / $posamt;
      return $minstock;
   }
   */

   $minstock = $CON->select($sql);
   $minstock = $minstock[0][$dbfield];

   return $minstock;
}

//----------------------------------------------------------------------------------
function getItemShopTransOrders($CON, $shopid, $itemid, $itemtype, $breakdown = false)
{
   $sql = " select t1.*, t3.supp_company, SUM(t2.item_amount - t2.item_amount_shipped) 'transstock'
            from supplier_order t1
            INNER JOIN supplier_order_items t2  ON t1.id = t2.sord_id
            INNER JOIN supplier t3              ON t1.sord_supplier_id  = t3.id
            where
            t1.sord_shop_id         = {$shopid} and
            t1.sord_order_shipped   = 0 and
            t1.sord_status          IN (2,3) and
            t2.item_id              = {$itemid} and
            t2.item_type            = '{$itemtype}' and
            t2.item_amount          > t2.item_amount_shipped
            group by t1.id
            order by t1.id, t2.item_pos desc";
   $supporders = $CON->select($sql);

   if($breakdown == true)
   {
      //----------------------------------------------------------------------------------
      if(count($supporders) && $supporders != false)
         $newidx = count($supporders);
      else
      {
         unset($supporders);
         $newidx = 0;
      }
         
      //----------------------------------------------------------------------------------
      if($itemtype == "itemlist")
      {
         $posamt = 0;
         $itemlistpos = getItemListContent($CON, $itemid);
         foreach($itemlistpos AS $itemlistrow)
            $posamt += $itemlistrow["item_amount"];

         $compsupporders = getItemShopTransOrders($CON, $shopid, $itemlistpos[0]["item_id"], "item");
         for($x = 0; $x < count($compsupporders) && $compsupporders != false; $x++)
         {
            $compsupporder = $compsupporders[$x];
            $compsupporder["transstock"] = $compsupporder["transstock"] / $posamt;
            $supporders[$newidx] = $compsupporder;
            $newidx++;
            
         }
      }

      //----------------------------------------------------------------------------------
      elseif($itemtype == "item")
      {
         $itemlists = getItemListsForItem($CON, $itemid);
         foreach($itemlists AS $itemlist)
         {
            $posamt = 0;
            $itemlistpos = getItemListContent($CON, $itemlist["id"]);
            foreach($itemlistpos AS $itemlistrow)
               $posamt += $itemlistrow["item_amount"];

            $listsupporders  = getItemShopTransOrders($CON, $shopid, $itemlist["id"], "itemlist");
            for($x = 0; $x < count($listsupporders) && $listsupporders != false; $x++)
            {
               $listsupporder = $listsupporders[$x];
               $listsupporder["transstock"] = $listsupporder["transstock"] * $posamt;
               $supporders[$newidx] = $listsupporder;
               $newidx++;
            }
         }
      }
   }

   return $supporders;
}

//----------------------------------------------------------------------------------
function getItemShopTransStock($CON, $shopid, $itemid, $itemtype, $breakdown = false)
{
   $sql = " select SUM(t2.item_amount - t2.item_amount_shipped) 'transstock'
            from supplier_order t1
            INNER JOIN supplier_order_items t2 ON t1.id = t2.sord_id
            where
            t1.sord_shop_id         = {$shopid} and
            t1.sord_order_shipped   = 0 and
            t1.sord_status          IN (2,3) and
            t2.item_id              = {$itemid} and
            t2.item_type            = '{$itemtype}' and
            t2.item_amount          > t2.item_amount_shipped";
   $transstock = $CON->select($sql);
   $transstock = $transstock[0]["transstock"];

   //----------------------------------------------------------------------------------
   if($breakdown == true)
   {
      //----------------------------------------------------------------------------------
      if($itemtype == "itemlist")
      {
         $posamt = 0;
         $itemlistpos = getItemListContent($CON, $itemid);
         foreach($itemlistpos AS $itemlistrow)
            $posamt += $itemlistrow["item_amount"];

         $comptransstock = getItemShopTransStock($CON, $shopid, $itemlistpos[0]["item_id"], "item");
         $comptransstock = $comptransstock / $posamt;
         return $transstock + $comptransstock;
      }

      //----------------------------------------------------------------------------------
      elseif($itemtype == "item")
      {
         $comptransstock = 0;
         
         $itemlists = getItemListsForItem($CON, $itemid);
         foreach($itemlists AS $itemlist)
         {
            $posamt = 0;
            $itemlistpos = getItemListContent($CON, $itemlist["id"]);
            foreach($itemlistpos AS $itemlistrow)
               $posamt += $itemlistrow["item_amount"];

            $temptransstock  = getItemShopTransStock($CON, $shopid, $itemlist["id"], "itemlist");
            $comptransstock += ($temptransstock * $posamt);
            return $transstock + $comptransstock;
         }
      }
   }

   return $transstock;
}

//----------------------------------------------------------------------------------
function getItemShopDeliveryNoInvoice($CON, $dlvid, $dlvpos, $itemid, $itemtype)
{
   $sql = " select SUM(t2.item_amount_shipped - t2.item_amount_invoiced) 'transstock'
            from orders_delivery t1
            INNER JOIN orders_delivery_items t2 ON t1.id = t2.dlv_id
            where
            t1.id                   = {$dlvid} and
            t1.dlv_invoiced         = 0 and
            t1.dlv_mode             <= 2 and
            t2.item_pos             = {$dlvpos} and
            t2.item_id              = {$itemid} and
            t2.item_type            = '{$itemtype}' and
            t2.item_amount_shipped  > t2.item_amount_invoiced";
   $transstock = $CON->select($sql);
   $transstock = $transstock[0]["transstock"];

   return $transstock;
}

//----------------------------------------------------------------------------------
function getSupplierItemCosts($CON, $supplierid, $itemid, $itemtype)
{
   if($itemtype == "item")
   {
      $sql = " select t1.supplier_id, t1.item_costprice_brutto, t1.item_costprice_taxes_perc, t1.item_costprice_netto, t1.item_costprice_taxes, t1.item_code, t1.item_costprice_usd
               from item_suppliers t1
               where
               t1.item_id     = {$itemid} and ";
      if((int)$supplierid == 0)
         $sql .= " t1.item_supp_act = 1 ";
      else
         $sql .= "t1.supplier_id = {$supplierid}"; 
   }
   else
   {
      $sql = " select t1.supplier_id, t1.item_costprice_brutto, t1.item_costprice_taxes_perc, t1.item_costprice_netto, t1.item_costprice_taxes, t1.item_code, t1.item_costprice_usd
               from itemlist_suppliers t1
               where
               t1.item_id     = {$itemid} and ";
      if((int)$supplierid == 0)
         $sql .= " t1.item_supp_act = 1 ";
      else
         $sql .= " t1.supplier_id = {$supplierid}";
   }

   $costdata = $CON->select($sql);
   $costdata = $costdata[0];
   
   return $costdata;
}

//----------------------------------------------------------------------------------
function getSupplierAutoOrderAmount($CON, $shopid, $itemid, $itemtype)
{
   if($itemtype == "item")
   {
      $sql = " select SUM(t1.iss_order_amount) 'iss_order_amount'
               from item_shops_storehouses t1
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid}";
   }
   else
   {
      $sql = " select SUM(t1.iss_order_amount) 'iss_order_amount'
               from itemlist_shops_storehouses t1
               where
               t1.item_id = {$itemid} and
               t1.shop_id = {$shopid}";
   }

   $orderamount = $CON->select($sql);
   $orderamount = printPrice($orderamount[0]["iss_order_amount"],2);

   return $orderamount;
}

//----------------------------------------------------------------------------------
function getItemSuppliers($CON, $itemid, $itemtype)
{
   $sql = " select t1.*, t2.supp_short
            from {$itemtype}_suppliers t1
            INNER JOIN supplier t2 ON ( t1.supplier_id = t2.id and t2.supp_status = 1 )
            where
            t1.item_id = {$itemid} 
            order by t1.item_supp_act desc, t1.item_costprice_brutto asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getItemShops($CON, $itemid, $itemtype)
{
   $sql = " select t1.*
            from {$itemtype}_shops t1
            INNER JOIN company_shops t2 ON ( t1.shop_id = t2.id and t2.shop_status = 1 )
            INNER JOIN company_data t3  ON ( t2.shop_company_id = t3.id and t3.company_status = 1 )
            where
            t1.item_id = {$itemid}
            order by t1.shop_id";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getItemUnits($CON)
{
   $sql = " select *
            from item_units
            where
            unit_status = 1
            order by id asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function updateItemStorePrices($CON, $itemid)
{
   $sql = " select *
            from item 
            where
            id = {$itemid} ";
   $item = $CON->select($sql);
   $item = $item[0];

   $sql = " update item_shops
            set
            itemshop_sellprice_brutto     = {$item["item_sellprice_brutto"]},
            itemshop_sellprice_netto      = {$item["item_sellprice_netto"]},
            itemshop_sellprice_taxes_perc = {$item["item_sellprice_taxes_perc"]},
            itemshop_sellprice_taxes      = {$item["item_sellprice_taxes"]}
            where
            item_id = {$itemid} and
            itemshop_price_man = 0";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function getShopItemStorePrice($CON, $shop_id, $item_id, $item_type)
{
   if($item_type == "item")
   {
      $sql = " select t1.itemshop_sellprice_brutto, t1.itemshop_sellprice_netto, t1.itemshop_sellprice_taxes_perc,
                      t1.itemshop_sellprice_taxes
               from item_shops t1
               where
               item_id = {$item_id} and
               shop_id = {$shop_id}";
   }
   else
   {
      $sql = " select t1.itemshop_sellprice_brutto, t1.itemshop_sellprice_netto, t1.itemshop_sellprice_taxes_perc,
                      t1.itemshop_sellprice_taxes
               from itemlist_shops t1
               where
               item_id = {$item_id} and
               shop_id = {$shop_id}";
   }

   $selldata = $CON->select($sql);
   $selldata = $selldata[0];
   
   return $selldata;
}

//----------------------------------------------------------------------------------
function updateItemlistStorePrices($CON, $itemlistid)
{
   $sql = " select *
            from itemlist 
            where
            id = {$itemlistid} ";
   $item = $CON->select($sql);
   $item = $item[0];

   $sql = " update itemlist_shops
            set
            itemshop_sellprice_brutto     = {$item["item_sellprice_brutto"]},
            itemshop_sellprice_netto      = {$item["item_sellprice_netto"]},
            itemshop_sellprice_taxes_perc = {$item["item_sellprice_taxes_perc"]},
            itemshop_sellprice_taxes      = {$item["item_sellprice_taxes"]}
            where
            item_id = {$itemlistid} and
            itemshop_price_man = 0";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function getPropuestaStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 0: return "<b style='color:yellow'>Ingresada</b>"; break;         
         case 1: return "<b style='color:orange'>Solicitadas</b>"; break;
         case 2: return "<b class='msg_save_err'>En proceso</b>"; break;
         /* case 2: return "<b style='color:red'>En proceso</b>"; break; */
         case 3: return "<b class='msg_save_ok'>Terminadas</b>"; break;
         case 4: return "<b style='color:purple'>Anuladas</b>"; break;
      } 
   }
   else
   {
      switch($stat)
      {
         case 0: return "Ingresada"; break;         
         case 1: return "Solicitadas"; break;
         case 2: return "En Proceso"; break;
         case 3: return "Terminadas"; break;
         case 4: return "Anuladas"; break;
      }
   }
}
//----------------------------------------------------------------------------------------
function CallApiCRM($accion, $idcrm, $monto,$nombre,$rut,$direccion,$empresa,$email,$celular,$comuna,$region,$pais,$giro,$vendedor,$fechaseg,$iderp,$canal,$rubro,$subrubro)
{

   /*
   ?	$accion = 
      	    "cotizacion" (en el caso de estar informando una cotización)
      	    "compra" ( en el caso de una confirmación de compra)
             "contacto" (para actualizar datos de cl,iente)
             "pedido" (para que avance la propuesta comercial)
   ?	$idcrm = [el id crm del contacto al que cotizamos]
   ?	$monto = [el monto de la cotización del item cotizado]
   ?  
   */
   $monto   = number_format($monto,2,',','');
   $string  = $accion.'&idcrm='.$idcrm
                     .'&monto='.$monto
                     .'&nombre='.$nombre
                     .'&rut='.$rut
                     .'&direccion='.$direccion
                     .'&empresa='.$empresa
                     .'&email='.$email
                     .'&celular='.$celular
                     .'&comuna='.$comuna
                     .'&region='.$region
                     .'&pais='.$pais
                     .'&giro='.$giro
                     .'&vendedor='.$vendedor
                     .'&seguimiento='.$fechaseg
                     .'&iderp='.$iderp
                     .'&canal='.$canal
                     .'&rubro='.$rubro
                     .'&subrubro='.$subrubro;
   $url = 'https://backend.leadconnectorhq.com/hooks/W1NJ20RQOnCyE3CKDRFp/webhook-trigger/f4f4f9c5-9c9f-4f70-8fd3-a22608dae409';
   $parametros = [
      'accion'       => $accion,
      'idcrm'        => $idcrm,
      'monto'        => $monto,
      'nombre'       => $nombre,
      'rut'          => $rut,
      'direccion'    => $direccion,
      'empresa'      => $empresa,
      'email'        => $email,
      'celular'      => $celular,
      'comuna'       => $comuna,
      'region'       => $region,
      'pais'         => $pais,
      'giro'         => $giro,
      'vendedor'     => $vendedor,
      'seguimiento'  => $fechaseg,
      'iderp'        => $iderp,
      'canal'        => $canal,
      'rubro'        => $rubro,
      'subrubro'     => $subrubro,
   ];

   $urlConParametros = $url . '?' . http_build_query($parametros);

   /* codigo de depuración  ---------------------------------------------------------------------------------------------------------------*/ 

   $host = "localhost";
   $dbname = "unibag_unibag";
   $user = "unibag_unibag";
   $pass = "ccsadfsdM1Ffd12";
   $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   $urlSafe = addslashes($urlConParametros); // Para evitar errores por comillas
   // $sql = "INSERT INTO mensajes(texto) VALUES('{$urlSafe}')";
   // $pdo->exec($sql);

   /* fin codigo de depuracion ------------------------------------------------------------------------------------------------------------*/

   $ch = curl_init();

   curl_setopt($ch, CURLOPT_URL, $urlConParametros); 
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

   $response = curl_exec($ch);

   if (curl_errno($ch)) {
      echo 'Error en cURL: ' . curl_error($ch);
      curl_close($ch);
   }

   curl_close($ch);

   return $response;


   
}

//----------------------------------------------------------------------------------
function generatePropuestaDesignMail($CON, $propuesta)
{

   $propuesta = $propuesta[0];

   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id
            where
            t1.user_status > 0 and
            t2.group_id    = 20 and
            (t1.id = case when {$propuesta["pro_dis_asignado"]} = 0 then t1.id else {$propuesta["pro_dis_asignado"]} end) and
            t1.user_mail like '%@%'";
   $users = $CON->select($sql);
 

   $sql = " select *
            from tran_docs
            where
            doc_tran_id = {$propuesta["pro_dis_items_pro_id"]} and
            doc_tran_type = 'designer_types'";
   $anexos = $CON->select($sql);

   /*
   $sql = " select * from pro_dis_items 
            where pro_dis_items_id = {$_REQUEST["id_item"]}";
   $propuesta = $CON->select($sql);
   */

   $attachments = Array();
   foreach($anexos AS $anexo)
   {
      $attachment["FILE"] = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/designer_types/{$anexo["doc_file"]}";
      $attachment["NAME"] = $anexo["doc_name"];
      $attachments[] = $attachment;
   }

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {

         // $uhash   = md5($headdata["id"].$user["id"]);
         // $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}designupload.php?id={$headdata["id"]}&uid={$user["id"]}&k={$uhash}";

         $uhash   = md5($propuesta["pro_dis_items_id"].$user["id"]);
         $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}asignar.dis.php?id={$propuesta["pro_dis_items_id"]}&uid={$user["id"]}&k={$uhash}";
         $title   = "Solicitud diseño Propuesta : {$propuesta["pro_dis_items_codigo"]}";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     Se ha creado una nueva propuesta de diseño, favor revisar Solicitudes de Propuestas.
                     <br><br>
                     En caso que quiera asignarse la actividad, favor ingresar al siguiente link
                     <br><br>
                     <a href='{$urllink}' target='_blank'>{$urllink}</a>";

         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachments);

      }      
   }
}

//----------------------------------------------------------------------------------
function generateSolicitudAprobacionCC($CON, $id, $solicitado)
{
   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail
            from user t1
            where  t1.user_status > 0 and
            t1.user_autoriza_cc_perm = 1 and
            t1.user_mail like '%@%'";
   $users = $CON->select($sql);

   $attachments = Array();

   $sql   = "select * from orders o
               inner join customer c on c.id = o.req_cust_id
               inner join orders_items oi on oi.req_id = o.id
               inner join user u on u.id = o.req_userid_seller
             where o.id = {$id}";
   $order = $CON->select($sql);
   $order = $order[0];


   /*   
   $sql = " select *
            from tran_docs
            where
            doc_tran_id = {$propuesta["pro_dis_items_pro_id"]} and
            doc_tran_type = 'designer_types'";
   $anexos = $CON->select($sql);

   $attachments = Array();
   foreach($anexos AS $anexo)
   {
      $attachment["FILE"] = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/designer_types/{$anexo["doc_file"]}";
      $attachment["NAME"] = $anexo["doc_name"];
      $attachments[] = $attachment;
   }
   */

   if(count($users) && $users != false)
   {
      foreach($users AS $user)
      {
         $uhash   = md5($id.$user["id"]);
         $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}autorizacc.php?id={$id}&uid={$user["id"]}&k={$uhash}&sid={$solicitado}";
         $title   = "Autorizacion de Confirmacion de Compra : {$order["req_number"]}";
         $monto   = printPrice($order["req_total_brutto"],0);
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     Favor autorizar confirmacion de compra segun referencia, ya que se encuentra con Stock Negativos.
                     <br><br>
                     Datos de la Confirmacion de Compra:<br>
                     Nombre Cliente&nbsp;&nbsp;: {$order["cust_company"]}<br>
                     Rut Cliente&nbsp;&nbsp;: {$order["cust_rut"]}<br>
                     Vendedor&nbsp;&nbsp;: {$order["user_firstname"]} {$order["user_lastname"]}<br>
                     Monto de la Operación&nbsp;&nbsp;: {$monto}<br>
                     <br>
                     En caso que quiera realizar la actividad ahora, favor ingresar al siguiente link
                     <br><br>
                     <a href='{$urllink}' target='_blank'>{$urllink}</a>";

         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachments);
      }      
   }
}

//----------------------------------------------------------------------------------
function getTipoDeDocumentos($type)
{
   switch($type)
   {
      case 1: return "Basado en Guias de despacho"; break;
      case 2: return "Basado en Factura"; break;
      case 3: return "Manual"; break;
   }
}

//----------------------------------------------------------------------------------
function getEstadoDeDesapacho($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b style='color:#4bf542'>Ingresado</b>"; break;
         case 2: return "<b style='color:#f54242'>Archivado</b>"; break;
         case 3: return "<b style='color:#080808'>Anulado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "Ingresado"; break;
         case 2: return "Archivado"; break;
         case 3: return "Anulada"; break;
      }
   }
}

//----------------------------------------------------------------------------------
function getEstadoDeDesapachoRechazo($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b style='color:#4260f5'>Despacho</b>"; break;
         case 2: return "<b style='color:#ecf542'>Rechazo</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "Despacho"; break;
         case 2: return "Rechazo"; break;
      }
   }
}

function sendMensajesInvoice($CON, $asunto, $detalle,  $usrid )
{

   // get current time
   $currtme = time();

   // format parameters
   $_REQUEST["msg_parent"] = 0;
   $_REQUEST["msg_header"] = trim(addslashes($asunto));
   $_REQUEST["msg_body"]   = trim(addslashes($detalle));
   $_REQUEST["msg_docid"]  = 0;

   // insert message
   $sql = " insert into message_data
            (msg_header, msg_body, msg_parent, msg_docid, msg_crtusr, msg_crtdat)
            VALUES
            ('{$_REQUEST["msg_header"]}', '{$_REQUEST["msg_body"]}',
            {$_REQUEST["msg_parent"]}, {$_REQUEST["msg_docid"]},
            {$_SESSION["user_id"]}, {$currtme})";
   $res = $CON->no_result($sql);

   if($res)
   {
      // get id of the message
      $sql = " select MAX(id) 'msgid'
               from message_data";
      $msgid = $CON->select($sql);
      $msgid = $msgid[0]["msgid"];

      // create a entry for sent messages
      $sql = " insert into message_users
            (user_id, msg_id, msg_type, msg_status)
            VALUES
            ({$_SESSION["user_id"]}, {$msgid}, 1, 1)";
      $res = $CON->no_result($sql);

      // set Savemessage
      $savemsg = getSaveMessage($res);
   
      if($res)
      {
            // create message entry
            $sql = " insert into message_users
                     (user_id, msg_id, msg_type, msg_status)
                     VALUES
                     ({$usrid}, {$msgid}, 0, 0)";
            $CON->no_result($sql);

            // get userdata
            $sql = " select user_firstname, user_lastname, user_mailforward, user_mail
                     from user
                     where
                     id = {$usrid}";
            $mailforw = $CON->select($sql);

            // if mailforwarding is active send notification
            if((int)$mailforw[0]["user_mailforward"])
            {
               // set title
               $title  = stripslashes($_REQUEST["msg_header"]);

               // set body
               $text    = '<html>
                           <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
                           <body>'.stripslashes($_REQUEST["msg_body"]).'</body></html>';

               $attachfile = NULL;
               
               // get message attachment
               $sql = " select msg_docid
                        from message_data
                        where
                        id = {$msgid}";
               $msg_attachment = $CON->select($sql);
               $msg_attachment = $msg_attachment[0];
               if((int)$msg_attachment["msg_docid"])
               {
                  $sql = " select t2.*
                           from menu_items t1, menu_docs t2
                           where
                           t1.menu_docid = t2.id and
                           t1.id = {$msg_attachment["msg_docid"]}";
                  $docdata = $CON->select($sql);
                  $docdata = $docdata[0];
                  
                  if((int)$docdata["id"])
                  {
                     $attachfile[0]["FILE"] = "./docs/{$docdata["id"]}.{$docdata["doc_hash"]}";
                     $attachfile[0]["NAME"] = $docdata["doc_name"];
                  }
               }
            }
      }
   }
}


//----------------------------------------------------------------------------------
function generateInvoiceSellAprobResponseMail($CON, $invcid, $aprobstate)
{

   $sql = " select t1.*, t5.user_firstname, t5.user_lastname, t5.user_mail, t6.cust_name
            from invoices_sell t1
               LEFT OUTER JOIN customer t6  ON t1.invc_cust_id = t6.id
               LEFT OUTER JOIN user t5          ON t1.invc_updusr = t5.id
            where  t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if($headdata["user_mail"] != "")
   {
      if($aprobstate == 0)
         $aprobstate = "rechazada";
      else
         $aprobstate = "aprobada";
         
      $title   = "Solicitud OC {$aprobstate}: {$headdata["invc_number"]}";
      $body    = "Estimado(a) {$headdata["user_firstname"]} {$headdata["user_lastname"]},<br><br>
                  la solicitud documento numero {$headdata["invc_number"]} de {$headdata["cust_name"]} fue {$aprobstate}. <br>
                  y ya se puede facturar.";
                     
      sendExternalMail($title, $body, $headdata["user_mail"], $headdata["user_firstname"]." ".$headdata["user_lastname"],
                       $_SESSION["_CONF"]["conf_mail_accountname"], $_SESSION["_CONF"]["conf_mail_sendername"]);
   }
}

//----------------------------------------------------------------------------------
function generateInvoiceSellAprobMail($CON, $invid, $mensajestocknegativo)
{
   //----------------------------------------------------------------------------------
   $_AMTRANGES = Array();

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
            from invoices_sell t1
            LEFT OUTER JOIN customer t2           ON t1.invc_cust_id         = t2.id
            LEFT OUTER JOIN company_data t3       ON t1.invc_company_id      = t3.id
            LEFT OUTER JOIN company_shops t4      ON t1.invc_shop_id         = t4.id
            LEFT OUTER JOIN user t5               ON t1.invc_updusr          = t5.id
            LEFT OUTER JOIN user t6               ON t1.invc_crtusr          = t6.id
            LEFT OUTER JOIN payments t7           ON t1.invc_paymentid       = t7.id
            LEFT OUTER JOIN transports t8         ON t1.invc_transportid     = t8.id
            LEFT OUTER JOIN user t9               ON t1.invc_userid_seller   = t9.id
            LEFT OUTER JOIN user t10              ON t1.invc_userid_cashing  = t10.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " select *
            from invoices_sell
            where
            id = {$invid}";
   $sordtemp = $CON->select($sql);
   $sordtemp = $sordtemp[0];

   /*
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
               t2.user_mail like '%@%' and t2.user_status > 0
               order by 1,2";
      $temp["USERS"] = $CON->select($sql);
      $_AMTRANGES[] = $temp;
   }

   $_FOUNDRANGE = Array();
   if((int)$_SESSION["user_type"] != 1)
   {
      $sql = " select *
               from invoices_sell
               where
               id = {$invid}";
      $invcdata = $CON->select($sql);
      $invcdata = $invcdata[0];

      $invc_total_brutto = $invcdata["invc_total_brutto"];
      if(!(int)$invcdata["invc_taxes"])
      {
         $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
         $invc_total_brutto = $invc_total_brutto * $usdval;
      }

      foreach($_AMTRANGES AS $_AMTRANGE)
      {
         $_FOUNDRANGE = $_AMTRANGE;
      }
   }
   */

   $sql = "select * from user t2
               where t2.user_mail like '%@%' and t2.user_status > 0 and t2.user_autoriza_fact_perm = 1
               order by 1,2";
   $autoriza = $CON->select($sql);

   foreach($autoriza AS $user)
   {
        $uhash   = md5($headdata["id"].$user["id"]);
        $urllink = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}aprobinvoicesell.php?id={$headdata["id"]}&uid={$user["id"]}&k={$uhash}";
        
        $title   = "Solicitud aprobación Factura: {$headdata["invc_number"]} por stock negativo";
        $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                    se solicita aprobación de factura {$headdata["invc_number"]} debido a que algunos productos generarían un stock negativo.<br><br>
                    Los articulos con stock negativo: <br><br>
                    {$mensajestocknegativo}<br><br>
                    Favor pinchar el siguiente enlace para aprobar o rechazar:<br><br>
                    <a href='{$urllink}' target='_blank'>{$urllink}</a>";
                    sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "");
                    sendMensajesInvoice($CON, $title, $body, $user["id"] );
   }

}
//----------------------------------------------------------------------------------
function ObtieneToken($CON, $company)
{
      // URL base de la API
      // $url_base = "https://replapi.defontana.com/api/Auth";

      $sql = "select * from company_data where id = {$company} ";
      $company = $CON->select($sql);
      $company = $company[0];

      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'Auth'";
      $url_base = $CON->select($sql);
      $url_base = $url_base[0]['url'];
   
      // Parámetros que deseas enviar en la solicitud GET
      $parametros = array(
         'client'   => $company["company_invc_cliente"],
         'company'  => $company["company_invc_cliente"],
         'user'     => $company["company_invc_ftp_user"],
         'password' => $company["company_invc_ftp_pass"],
      );

      $url_completa = $url_base . '?' . http_build_query($parametros);
   
   
      $ch = curl_init($url_completa);
      
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devuelve la respuesta como una cadena
      
      $respuesta = curl_exec($ch);

      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $resp = curl_errno($ch);

      if (curl_errno($ch)) 
      {
         return null;
      } 
      else 
      {
         $datos = json_decode($respuesta, true);
         curl_close($ch);
      }
      return $datos;   
   
}

//----------------------------------------------------------------------------------
function getRespuestosEntregaStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
      }
   }
}

function getReservasStatus($stat, $formated = false)
{
   if($formated)
   {
      switch($stat)
      {
         case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
         case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
      }
   }
   else
   {
      switch($stat)
      {
         case 1: return "En proceso"; break;
         case 2: return "Finalizado"; break;
      }
   }
}
//----------------------------------------------------------------------------------
function generateDiseñoOrderAprobMail($CON, $sordid)
{
   //----------------------------------------------------------------------------------
   $sql = " select * from orders 
            where id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = "select o.id
                 ,oi.fab_id_diseño
                 ,oi.fab_design_imagehash
                 ,oi.fab_design_name
                 ,oi.item_img_hash
                 ,o.req_numero_aprob
                 ,fab_id_propuesta
                 ,fab_id_item
                ,ad.*
             from orders o
                inner join orders_items oi on oi.req_id = o.id
                inner join aprobacion_dis ad on o.req_numero_aprob = ad.id
            where o.id = {$sordid}";
   $aprobaciones = $CON->select($sql);
   $aprobaciones = $aprobaciones[0];

   $sql = "select user_firstname
                , user_lastname
                , user_mail
            from user
               where id = {$aprobaciones["apro_id_user_sol"]}";
   $solicitante = $CON->select($sql);
   $solicitante = $solicitante[0];

   $sql = "select user_firstname
                , user_lastname
	             , user_mail
            from user 
              where id = {$aprobaciones["apro_id_user_rev"]}";
   $diseñador = $CON->select($sql);
   $diseñador = $diseñador[0];

   $CON->no_result("insert into mensajes(texto) values('{$sql}')");

   $title   = "Aprobación de Revisión de Diseño OC: {$headdata["req_number"]}";
   $body    = "Estimado(a) {$solicitante["user_firstname"]} {$solicitante["user_lastname"]},<br><br>
                   Con fecha ".date("d/m/Y", $aprobaciones["apro_fec_resolucion"])." se aprueba revisión del diseño asociado a la Confirmación de Compra {$headdata["req_number"]},<br><br>
                   <br><br><br><br></a>
                   Saludos cordiales,<br>
                   {$diseñador["user_firstname"]} {$diseñador["user_lastname"]}<br>";
   sendExternalMail($title, $body, $diseñador["user_mail"], $diseñador["user_firstname"]." ".$diseñador["user_lastname"], "", "");
}
//---------------------------------------------------------------------------------------------------------------------------------------
function generateDiseñoOrderApruebaMail($CON, $sordid)
{
   $sql = " select * from orders 
            where id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = "select user_firstname
                , user_lastname
	             , user_mail
             from user_group 
             inner join user on user_id = id and user_status > 0
           where group_id = 20";
   $solicitante = $CON->select($sql);

   $sql = "select user_firstname
                , user_lastname
                , user_mail
            from user
               where id = {$_SESSION["user_id"]}";
   $revisor = $CON->select($sql);

   if((int)count($revisor))
   {
         $title   = "Aprobación de Diseño OC: {$headdata["req_number"]}";
         $body    = "Estimado(a) {$solicitante["user_firstname"]} {$solicitante["user_lastname"]},<br><br>
                     Se aprueba el diseño asociado a la Confirmación de Compra : {$headdata["req_number"]},<br><br>
                     Le agradecería revisarlo para verificar si ha tenido alguna modificación. <br><br><br><br></a>
                     Saludos cordiales,<br>
                     {$revisor["user_firstname"]} {$revisor["user_lastname"]}<br>";
         sendExternalMail($title, $body, $solicitante["user_mail"], $solicitante["user_firstname"]." ".$solicitante["user_lastname"], "", "");
   }
}
//---------------------------------------------------------------------------------------------------------------------------------------
function generateDiseñoOrderRechazaMail($CON, $sordid)
{
   $sql = " select * from orders 
            where id = {$sordid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = "select user_firstname
                , user_lastname
	             , user_mail
             from user_group 
             inner join user on user_id = id and user_status > 0
           where group_id = 20";
   $solicitante = $CON->select($sql);

   $sql = "select user_firstname
                , user_lastname
                , user_mail
            from user
               where id = {$_SESSION["user_id"]}";
   $revisor = $CON->select($sql);

   if((int)count($revisor))
   {
      foreach($revisor AS $user)
      {
         $title   = "Rechazo de Diseño OC: {$headdata["req_number"]}";
         $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                     Le informamos que el diseño asociado a la Confirmación de Compra : {$headdata["req_number"]},<br>
                     ha sido rechazado.<br><br><br>
                     Por favor, revise el diseño y comuníquese con el área de diseño para cualquier ajuste necesario.<br><br><br><br></a>
                     Saludos cordiales,<br>
                     {$revisor["user_firstname"]} {$revisor["user_lastname"]}<br>";
         sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "");
      }
   }
  
}
//---------------------------------------------------------------------------------------------------------------------------------------
   function formatearRut($rut) 
   {
      // Separar el cuerpo del RUT y el dígito verificador
      $rut = str_replace(".", "", $rut);
      $partes = explode('-', $rut);
      if (count($partes) != 2) {
          return "Formato inválido";
      }
  
      $cuerpo = $partes[0];
      $dv = $partes[1];
  
      // Asegurarse de que el cuerpo tenga al menos 8 caracteres
      $cuerpo = str_pad($cuerpo, 8, '0', STR_PAD_LEFT);
  
      // Dividir el cuerpo en bloques de tres con un punto separador
      $cuerpo_formateado = substr($cuerpo, 0, 2) . '.' . substr($cuerpo, 2, 3) . '.' . substr($cuerpo, 5, 3);
  
      // Formar el RUT completo con el dígito verificador
      $rut_formateado = $cuerpo_formateado . '-' . $dv;
  
      return $rut_formateado;
   }

//----------------------------------------------------------------------------------
   function LimpiaData($val)
   {
      $val = str_replace(">", "", $val);
      $val = str_replace("<", "", $val);
      $val = str_replace("'", "", $val);
      $val = str_replace('"', "", $val);
      $val = str_replace("&", "", $val);
      $val = str_replace("Ä", "Ae", $val);
      $val = str_replace("Ü", "Ue", $val);
      $val = str_replace("Ö", "Oe", $val);
      $val = str_replace("ä", "ae", $val);
      $val = str_replace("ü", "ue", $val);
      $val = str_replace("ö", "oe", $val);
      $val = str_replace("ß", "ss", $val);
      $val = str_replace("ñ", "n", $val);
      $val = str_replace("Ñ", "N", $val);
      $val = str_replace("Á", "A", $val);
      $val = str_replace("á", "a", $val);
      $val = str_replace("ç", "c", $val);
      $val = str_replace("É", "E", $val);
      $val = str_replace("é", "e", $val);
      $val = str_replace("Í", "I", $val);
      $val = str_replace("í", "i", $val);
      $val = str_replace("Ó", "O", $val);
      $val = str_replace("ó", "o", $val);
      $val = str_replace("Ú", "U", $val);
      $val = str_replace("ú", "u", $val);

      $val = preg_replace("/[^a-zA-Z0-9 -_]/", "", $val);
   
      return $val;
   }
   //************************************************************************************************************************* */
   //----------------------------------------------------------------------------------
   function generateDiseñoFinalizadobMail2($CON, $propuesta)
   {
      //----------------------------------------------------------------------------------
      $id                         = $propuesta["pro_dis_items_pro_id"];
      $pro_dis_items_codigo       = $propuesta["pro_dis_items_id"];
      $pro_dis_user_cr            = $propuesta["pro_dis_items_user_cr"];
      $pro_dis_asignado           = $propuesta["pro_dis_asignado"];
      $fecha                      = $propuesta["pro_dis_items_fecha_md"];
      $pro_dis_items_descripcion  = $propuesta["pro_dis_items_descripcion"];

      $sql = "select * from pro_dis pd1
                 inner join customer c1 on c1.id = pd1.pro_dis_custid
               where pd1.id = {$id}";
      $cliente = $CON->select($sql);
      $cliente = $cliente[0];
      $cliente = $cliente["cust_company"];
      $sql = "select user_firstname
                  , user_lastname
                  , user_mail
               from user
                  where id = {$pro_dis_user_cr}";
      $solicitante = $CON->select($sql);
      $solicitante = $solicitante[0];

      $sql = "select user_firstname
                  , user_lastname
                  , user_mail
               from user 
               where id = {$pro_dis_asignado}";
      $diseñador = $CON->select($sql);
      $diseñador = $diseñador[0];

      $title   = "Finalización de Propuesta de Diseño Código: {$pro_dis_items_codigo} - {$pro_dis_items_descripcion}";
      $body    = "Estimado(a) {$solicitante["user_firstname"]} {$solicitante["user_lastname"]},<br><br>
                     Con fecha ".date("d/m/Y H:i", $fecha)." se finaliza revisión del diseño asociado a la propuesta {$propuesta["pro_dis_items_codigo"]}<br>
                     del cliente {$cliente}.<br><br>
                     <br></a>
                     Saludos cordiales,<br>
                     {$diseñador["user_firstname"]} {$diseñador["user_lastname"]}<br>";
      sendExternalMail($title, $body, $solicitante["user_mail"], $solicitante["user_firstname"]." ".$solicitante["user_lastname"], "", "");
   }
//---------------------------------------------------------------------------------------------------------------------------------------

