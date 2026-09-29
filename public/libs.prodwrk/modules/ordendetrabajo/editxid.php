<?php


if((int)$_REQUEST["validateprdsave"])
{
   $_REQUEST["askprdclose_axx_username"]  = trim(addslashes($_REQUEST["askprdclose_axx_username"]));
   $_REQUEST["askprdclose_axx_pass"]      = md5(trim($_REQUEST["askprdclose_axx_pass"]));

   $sql = " select t1.*
            from user t1
            where
            t1.user_login  = '{$_REQUEST["askprdclose_axx_username"]}' and
            t1.user_pass   = '{$_REQUEST["askprdclose_axx_pass"]}' and
            t1.user_status = 1 and
            (
               t1.user_type = 1 or
               (
                  select count(*) 'cc'
                  from user_group t2
                  where
                  t2.user_id  = t1.id and
                  t2.group_id IN ({$_CONFIG["_PROD_SUPERVISOR_ROLEID"]})
               ) > 0
            ) ";
   $checkuser = $CON->select($sql);
   $checkuser = $checkuser[0];

   if(!(int)$checkuser["id"])
   {  ?>
      <script language="Javascript">
         alert("Usuario/Clave erroneo.");
         location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';
      </script>
      <?php
      exit;
   }
}



$_REQUEST["agid"] = (int)$_REQUEST["agid"];

if(isset($_REQUEST["setcolormode"]))
   $_SESSION["_PROD_setcolormode"] = $_REQUEST["setcolormode"];

//----------------------------------------------------------------------------------
if((int)$_REQUEST["setspecparams"])
{
   $hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"], $_REQUEST["agid"]);
   $_REQUEST["bastidoramt"] = (int)$_REQUEST["bastidoramt"];

   $sql = " update orders
            set
            req_operador_bastidoramt = {$_REQUEST["bastidoramt"]}
            where
            id = {$hasopenot["prd_reqid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------

$hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"] , $_SESSION["user_planta_id"],$_REQUEST["agid"]);
$hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
   
$sql = " select *
from prod_worker_ot_events
where
evt_prod_worker_otid = {$hasopenot["id"]} and
evt_status           > 0
order by evt_crtdat desc";
$events2 = $CON->select($sql);
foreach($events2 AS $event)
{
   if($event["evt_type"] == "prod")
   {
      $_PROD_EVENT = $event;
   }
}

//----------------------------------------------------------------------------------
$_ISREBOBINADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "REBOBIN") !== false)
   $_ISREBOBINADORA = true;

//----------------------------------------------------------------------------------
$_ISSELLADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "SELLADORA") !== false)
   $_ISSELLADORA = true;

//----------------------------------------------------------------------------------
$_ISEMBALAJE = false;
if((int)$hasopeninit["type_createstock_act"])
   $_ISEMBALAJE = true;

//----------------------------------------------------------------------------------
$_ISSERIPULPO = false;
if((int)$hasopeninit["equipo_prod_isprinter_seri"] && strpos(strtoupper($hasopeninit["equipo_name"]), "PULPO") !== false)
   $_ISSERIPULPO = true;

// var_dump($_ISSERIPULPO);
// echo "<pre>";
// print_r($hasopeninit);

//----------------------------------------------------------------------------------
if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
{
   
   if((int)$_REQUEST["save_anilox"])
   {
      
      $sql = "select * from prod_anilox_ot
                  where prod_anilox_agid = {$_REQUEST["agid"]} and prod_anilox_reqid = {$hasopenot["prd_reqid"]} ";
      $valida = $CON->select($sql);
      $stk_id = $valida[0]["id"];

      $sql   = "select * from prod_anilox_ot_worker where paow_ot_worker_id = {$stk_id} ";
      $paows = $CON->select($sql);

      $sw_error = false;
      for($x=1;$x<=count($paows);$x++)
      {
         $variable = "anilox".$x;
         for($xx=1;$xx<=count($paows);$xx++)
         {
            $variable2 = "anilox".$xx;
            if($x!=$xx)
            {
               if($_REQUEST[$variable2] != "")
               {
                  if($_REQUEST[$variable] == $_REQUEST[$variable2])
                     $sw_error = true;
               }
            }
         }
      }
      if($sw_error)
      {
         ?>
            <script>alert("No se puede seleccionar mas de un Anilox en la misma CC ")</script>;
         <?php
      }
      else
      {
         $x = 1;
         foreach($paows AS $paow)
         {
            $variable = "anilox".$x;
            $_REQUEST[$variable] = (int)($_REQUEST[$variable]);
            $sql = "update prod_anilox_ot_worker set paow_anilox = {$_REQUEST[$variable]} 
                        where paow_ot_worker_id = {$stk_id} and paow_unidad = {$x} ";
            $CON->no_result($sql);
            $x++;
         }
      }
   }

   $_REQUEST["mover"]  = (int)$_REQUEST["mover"];
   $_REQUEST["unidad"] = (int)$_REQUEST["unidad"];

   if( (int)$_REQUEST["mover"] == 1 )
   {

      $sql = "select * from prod_anilox_ot
                 where prod_anilox_agid = {$_REQUEST["agid"]} and prod_anilox_reqid = {$hasopenot["prd_reqid"]} ";
      $valida = $CON->select($sql);
      $stk_id = $valida[0]["id"];

      $sql = "select * from prod_anilox_ot_worker 
                     where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]} ";
      $pos = $CON->select($sql);
      $posicion = $pos[0];

      $sql = "select * from prod_anilox_ot_worker 
                     where paow_ot_worker_id = {$stk_id} and paow_posicion = {$posicion["paow_posicion"]} - 1 ";
      $pos = $CON->select($sql);
      $new_unidad = $pos[0];
   
      $sw = 0;
      if($posicion == 1)
      {
         ?>
            <script>alert("No puede Mover, estas en la primera posición")</script>;
         <?php
          $sw = 1;
      }
      
      if($posicion==0)
         $sw = 1;
      
      if($sw==0)
      {
         
         $sql = "update prod_anilox_ot_worker set paow_color    = '{$new_unidad["paow_color"]}'
                                                 ,paow_anilox   = {$new_unidad["paow_anilox"]}
                        where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]}";
         $CON->no_result($sql);

         $sql = "update prod_anilox_ot_worker set paow_color    = '{$posicion["paow_color"]}'
                                                 ,paow_anilox   = {$posicion["paow_anilox"]}
                        where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]}-1";
         $CON->no_result($sql);
      }
   }

   if( (int)$_REQUEST["mover"] == 2 )
   {

      $sql = "select * from prod_anilox_ot
                 where prod_anilox_agid = {$_REQUEST["agid"]} and prod_anilox_reqid = {$hasopenot["prd_reqid"]} ";
      $valida = $CON->select($sql);
      $stk_id = $valida[0]["id"];


      $sql = "select * from prod_anilox_ot_worker 
               where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]} ";
      $pos = $CON->select($sql);
      $posicion = $pos[0];

      // echo($sql);

      $sql = "select * from prod_anilox_ot_worker 
                where paow_ot_worker_id = {$stk_id} and paow_posicion = {$posicion["paow_posicion"]} + 1 ";
      $pos = $CON->select($sql);
      $new_unidad = $pos[0];

      // echo($sql);

      $sql = "select count(*) as maximo from prod_anilox_ot_worker 
                 where paow_ot_worker_id = {$stk_id}";
      $maximo = $CON->select($sql);
      $maximo = $maximo[0]["maximo"];
   
      $sw = 0;
      /*if($posicion >= $maximo)
      {
         ?>
         <script>alert("No puede Mover, estas en la ultima posición");</script>;
         <?php
          $sw = 1;
      }
      */
      
      if($posicion==0)
         $sw = 1;
      
      if($sw==0)
      {
         $sql = "update prod_anilox_ot_worker set paow_color    = '{$posicion["paow_color"]}'
                                                 ,paow_anilox   = {$posicion["paow_anilox"]}
                        where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]} + 1";
         $CON->no_result($sql);
         // echo($sql);

         $sql = "update prod_anilox_ot_worker set paow_color    = '{$new_unidad["paow_color"]}'
                                                 ,paow_anilox   = {$new_unidad["paow_anilox"]}
                  where paow_ot_worker_id = {$stk_id} and paow_unidad = {$_REQUEST["unidad"]}";
         $CON->no_result($sql);
         // echo($sql);                        

      }
   }


   $sql = "select i.id, i.item_number_prod, i.item_title from item i
              inner join item_productcats ip on i.id = ip.item_id 
	           inner join productcats p on p.id = ip.cat_id and p.cat_prefix = 'ANI'
              inner join item_equipos_rel ier on ier.item_id = i.id
	           inner join equipo e on ier.equipo_id = e.id
               where i.item_status > 0 and e.id = {$hasopeninit["win_equipoid"]} ";
   $item_anilox = $CON->select($sql);

   $sql = "select * from prod_anilox_ot
               where prod_anilox_agid = {$_REQUEST["agid"]} and prod_anilox_reqid = {$hasopenot["prd_reqid"]} ";
   $valida = $CON->select($sql);

   
   if(!(int)$valida[0]["id"])
   {
      $sql = "select fab_print_colordesc_1  as color1
                  , fab_print_colordesc_2   as color2 
                  , fab_print_colordesc_3   as color3
                  , fab_print_colordesc_4   as color4
                  , fab_print_colordesc_5   as color5
                  , fab_print_colordesc_6   as color6
                  , fab_print_colordesc_7   as color7
                  , fab_print_colordesc_8   as color8
                  , fab_print_colordesc_9   as color9
                  , fab_print_colordesc_10  as color10
      from orders_items where req_id = {$hasopenot["prd_reqid"]}";
      $anilox = $CON->select($sql);
      $anilox = $anilox[0];
      $currtme = time();
      
      $sql = "insert into prod_anilox_ot(prod_anilox_agid
                  , prod_anilox_reqid
                  , prod_anilox_unidad
                  , prod_anilox_fecha_creacion
                  , prod_anilox_user_creacion 
                  , prod_anilox_fecha_actualiza
                  , prod_anilox_user_actualiza)
               values({$_REQUEST["agid"]}
                  ,{$hasopenot["prd_reqid"]}
                  ,0
                  ,{$currtme}
                  ,{$_SESSION["user_id"]}
                  ,{$currtme}
                  ,{$_SESSION["user_id"]}
                  )";
      $res = $CON->no_result($sql);
      if($res)
      {
         $stk_id = mysql_insert_id();
         for($x=1;$x<=6;$x++)
         {
            // if($anilox["color".$x] != "")
            {
               $sql = "insert into prod_anilox_ot_worker(paow_unidad
                                                         ,paow_posicion
                                                         ,paow_color
                                                         ,paow_anilox
                                                         ,paow_ot_worker_id) 
                                                   values({$x}
                                                         ,{$x}
                                                         ,'{$anilox["color".$x]}'
                                                         ,0
                                                         ,{$stk_id}) ";
               $CON->no_result($sql);               
            }
         }
      }
   }
   else
   {
      /* paso por aca */
   }
   $sql = "select * from prod_anilox_ot pao 
              inner join prod_anilox_ot_worker paow on pao.id = paow.paow_ot_worker_id
              where prod_anilox_agid = {$_REQUEST["agid"]} and prod_anilox_reqid = {$hasopenot["prd_reqid"]}
           order by paow_unidad, paow_posicion ";
   $detalle_anilox = $CON->select($sql);
}


//----------------------------------------------------------------------------------
if($_REQUEST["reboexec"] == "save")
{
   $sql = " delete from orders_classify_subproducts_inputweights
            where
            req_id            = {$hasopenot["prd_reqid"]} and
            prod_worker_ot_id = {$hasopenot["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "subprod_weight_enter_") !== false && strpos($reqkey, "subprod_weight_enter_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $subprod_weight_enter = (float)getPrice(trim($_REQUEST["subprod_weight_enter_{$idx}"]));
         $subprod_rollo_idx    = (int)$idx;

         $_INPS = Array();
         for($z = 0; $z < 20; $z++)
         {
            $_INPS[$z] = (float)getPrice(trim($_REQUEST["subprod_salida_weight_{$idx}_{$z}"]));
         }
         if($subprod_weight_enter)
         {
            $sql = " insert into orders_classify_subproducts_inputweights
                     (req_id, prod_worker_ot_id, subprod_rollo_idx, subprod_weight_enter,
                      subprod_salida_weight_0, subprod_salida_weight_1, subprod_salida_weight_2, subprod_salida_weight_3,
                      subprod_salida_weight_4, subprod_salida_weight_5, subprod_salida_weight_6, subprod_salida_weight_7,
                      subprod_salida_weight_8, subprod_salida_weight_9, subprod_salida_weight_10, subprod_salida_weight_11,
                      subprod_salida_weight_12, subprod_salida_weight_13, subprod_salida_weight_14, subprod_salida_weight_15,
                      subprod_salida_weight_16, subprod_salida_weight_17, subprod_salida_weight_18, subprod_salida_weight_19
                      )
                     VALUES
                     ({$hasopenot["prd_reqid"]}, {$hasopenot["id"]}, {$subprod_rollo_idx}, {$subprod_weight_enter},
                      {$_INPS[0]}, {$_INPS[1]}, {$_INPS[2]}, {$_INPS[3]}, {$_INPS[4]}, {$_INPS[5]}, {$_INPS[6]}, {$_INPS[7]},
                      {$_INPS[8]}, {$_INPS[9]}, {$_INPS[10]}, {$_INPS[11]}, {$_INPS[12]}, {$_INPS[13]}, {$_INPS[14]},
                      {$_INPS[15]}, {$_INPS[16]}, {$_INPS[17]}, {$_INPS[18]}, {$_INPS[19]})";
            $CON->no_result($sql);
         }
      }
   }
}

?>
<font style="font-size:20px"><b>En curso</b></font>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:12px">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td align="left">
            &nbsp;&nbsp;<i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
            <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
            En curso
         </td>
         <td align="right" width="250">
            <?php
            if((int)$hasopeninit["type_createstock_act"] && (int)$_PROD_EVENT["id"])
            {  
               ?>
               <div class="btngreen" style="visibility:hidden" id="otterm_btn"
               onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.embalaje.termot.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&ruid=<?=md5(microtime())?>', 'iframe', '1000', '650', 'auto')">
                   <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT&nbsp;
               </div>
               <?php
            }
            else
            {  
               ?>
               <div class="btngreen" style="visibility:hidden" id="otterm_btn"
               onclick="location.href='prodwrk.php?mid=2&mode=terminarot&agid=<?=$_REQUEST["agid"]?>'">
                   <i class="fa fa-fw fa-check" style="color:white;"></i>Terminar OT&nbsp;
               </div>
               <?php
            }
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<div style="clear:both;height:10px"></div>
<?php
// print_r($hasopeninit);

$_GOTO_AUTOCONTROL = false;
$_APERTURA = false;

if((int)$hasopeninit["id"] && (int)$hasopenot["id"])
{
   $_HASALIS = false;
   $sql = " select *
            from prod_worker_ot_events
            where
            evt_prod_worker_otid = {$hasopenot["id"]} and
            evt_status           > 0
            order by evt_crtdat desc";

   $events = $CON->select($sql);

   for($x = 0; $x < count($events) && $events != false; $x++)
   {
      if($events[$x]["evt_type"] == "apertura")
         $_APERTURA = true;

      
      if($events[$x]["evt_type"] == "apertura" && (int)$events[$x]["evt_enddat"])
         $_HASALIS = true;
      
   }
         
   $sql = " select count(*) 'cc'
            from equipo_puntos_autocontrol
            where
            ac_equipotype_id  = {$hasopeninit["equipo_type_id"]} and
            ac_status         > 0";
   $hasautocontrol_act = $CON->select($sql);
   $hasautocontrol_act = (int)$hasautocontrol_act[0]["cc"];
   
   if($hasautocontrol_act)
   {
      $sql = " select count(*) 'cc'
               from prod_worker_ot_autocontrol
               where
               ctr_init_id = {$hasopenot["id"]} and
               ctr_type    = 'supervisor'";
      $hasautocontrol_super_inputs = $CON->select($sql);
      $hasautocontrol_super_inputs = (int)$hasautocontrol_super_inputs[0]["cc"];

      $sql = " select count(*) 'cc'
               from prod_worker_ot_autocontrol
               where
               ctr_init_id = {$hasopenot["id"]} and
               ctr_type    = 'supervisor' and
               ctr_res     = 0";
      $hasautocontrol_super_ncinputs = $CON->select($sql);
      $hasautocontrol_super_ncinputs = (int)$hasautocontrol_super_ncinputs[0]["cc"];

      if($_HASALIS && ($hasautocontrol_super_inputs == 0 || $hasautocontrol_super_ncinputs > 0))
      {
         $_GOTO_AUTOCONTROL = true;
      }
   }
}

//----------------------------------------------------------------------------------
if($_GOTO_AUTOCONTROL)
{
   require_once("autocontrol.form.php");
}
else
{
   if((int)$hasopenot["id"] && (int)$hasopeninit["id"])
   {
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "terminarot")
      {
         
         $sql = " select *
                  from prod_worker_ot
                  where
                  id = {$hasopenot["id"]}";
         $prod_worker_ot = $CON->select($sql);
         $prod_worker_ot = $prod_worker_ot[0];

         $sql = " select *
                  from prod_worker_init
                  where
                  id = {$prod_worker_ot["wok_init_id"]}";
         $prod_worker_init = $CON->select($sql);
         $prod_worker_init = $prod_worker_init[0];

         $sql = " select *
                  from prod_agenda
                  where
                  id = {$prod_worker_ot["wok_ag_id"]}";
         $prod_agenda = $CON->select($sql);
         $prod_agenda = $prod_agenda[0];

         $ag_reqid      = (int)$prod_agenda["ag_reqid"];
         $ag_plantaid   = (int)$prod_agenda["ag_plantaid"];

         $sql = " select distinct t0.*,
                         t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                         t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                         t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                         t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                         t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                         fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                         fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                         fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                         t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                         t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
                         t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                         t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                         t1x.fab_manilla_length, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cc_genrefid
                  from prod_agenda t0
                  INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
                  INNER JOIN orders t1             ON t0.ag_reqid = t1.id
                  LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
                  LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
                  LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
                  INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                  INNER JOIN item t2x              ON t1x.item_id = t2x.id
                  LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
                  LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
                  where
                  t0.id = {$hasopenot["wok_ag_id"]}";
         $tempagenda = $CON->select($sql);
         $tempagenda = $tempagenda[0];

         $_SERICOLORS      = Array();
         $_SERICOLORS_ACT  = false;
         if($tempagenda["fab_printtype"] == "SERI" && (int)$hasopeninit["equipo_prod_serimulticolors_act"])
         {
            for($x = 1; $x <= 10; $x++)
            {
               if($tempagenda["fab_print_colordesc_{$x}"] != "")
                  $_SERICOLORS[$tempagenda["fab_print_colordesc_{$x}"]] = 0;
            }
            if(count($_SERICOLORS) > 1)
            {
               $_SERICOLORS_ACT  = true;
            }
         }
         if($_SERICOLORS_ACT)
         {
            $sql = " select t4.*
                     from prod_header t1
                     INNER JOIN prod_agenda t2           ON t1.id = t2.ag_prdid
                     INNER JOIN prod_worker_ot t3        ON t3.wok_ag_id = t2.id
                     INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
                     where
                     t1.prd_status     = 2 and
                     t2.ag_status      > 0 and
                     t3.id             = {$hasopenot["id"]} and
                     t4.evt_status     > 0 and
                     t1.prd_plantaid   = {$ag_plantaid} and
                     t1.prd_reqid      = {$ag_reqid} and
                     t4.evt_type       = 'prodsericolor' and
                     t4.evt_amount     > 0
                     order by t4.evt_crtdat desc";
            $serievents = $CON->select($sql);

            foreach($serievents AS $serievent)
            {
               $dispoamt = ($serievent["evt_amount"] - $serievent["prod_seri_converted_amt"]);
               $_SERICOLORS[$serievent["prod_seri_color"]] += $dispoamt;
            }

            $_MINIMUM_AMT = 0;
            foreach(array_keys($_SERICOLORS) AS $_SERICOLORSIDX)
            {
               $dispoamt = $_SERICOLORS[$_SERICOLORSIDX];

               if($_MINIMUM_AMT == 0 || $dispoamt <= $_MINIMUM_AMT)
                  $_MINIMUM_AMT = $dispoamt;
            }

            if($_MINIMUM_AMT > 0.00)
            {
               foreach(array_keys($_SERICOLORS) AS $_SERICOLORSIDX)
               {
                  $_THIS_MINIMUM_AMT = $_MINIMUM_AMT;
                  foreach($serievents AS $serievent)
                  {
                     if($serievent["prod_seri_color"] == $_SERICOLORSIDX && $_THIS_MINIMUM_AMT > 0.00)
                     {
                        $evt_amount = $serievent["evt_amount"];
                        $prod_seri_converted_amt = $serievent["prod_seri_converted_amt"];
                        $dispoamt = $evt_amount - $prod_seri_converted_amt;

                        if($dispoamt >= $_THIS_MINIMUM_AMT)
                        {
                           $sql = " update prod_worker_ot_events
                                    set
                                    prod_seri_converted_amt = prod_seri_converted_amt + {$_THIS_MINIMUM_AMT}
                                    where
                                    id = {$serievent["id"]}";
                           $CON->no_result($sql);
                        }
                        else
                        {
                           $sql = " update prod_worker_ot_events
                                    set
                                    prod_seri_converted_amt = prod_seri_converted_amt + {$dispoamt}
                                    where
                                    id = {$serievent["id"]}";
                           $CON->no_result($sql);

                           $_THIS_MINIMUM_AMT -= $dispoamt;
                        }
                     }

                  }
               }

               $currtme = time();
               $sql = " insert into prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_enddat, evt_amount2)
                        VALUES
                        ({$hasopenot["id"]}, {$_MINIMUM_AMT}, {$currtme}, 1, 'prod',
                         'Generado por cantidades seri-multicolor.', {$currtme}, 0)";
               $CON->no_result($sql);
            }
         }

         //----------------------------------------------------------------------------------
         $currtme = time();
         
         $sql = " update prod_worker_ot
                  set
                  wok_status  = 2,
                  wok_enddat  = {$currtme}
                  where
                  id = {$hasopenot["id"]}";
         $CON->no_result($sql);

         
         EnvioCorreo( $CON , $_REQUEST["req_codigo_cierre"], $hasopenot["id"]);

         createEmbalajeProdStock($CON, $hasopenot["id"]);
        
         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=5&askcloseturno=1&agid=<?=$_REQUEST["agid"]?>';
         </script>
         <?php
         exit;

      } 
      
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "mantencionsave")
      {
         $_REQUEST["evt_comments"]        = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["evt_equipo_mantid"]   = (int)$_REQUEST["evt_equipo_mantid"];
         $_REQUEST["evt_ubim_id"]         = (int)$_REQUEST["evt_ubim_id"];

         $currtme = time();
         if((int)$_REQUEST["refid"])
         {
            if($_REQUEST["submode"] == "end")
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_comments         = '{$_REQUEST["evt_comments"]}',
                        evt_enddat           = {$currtme},
                        evt_equipo_mantid    = {$_REQUEST["evt_equipo_mantid"]},
                        evt_ubim_id          = {$_REQUEST["evt_ubim_id"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_comments      = '{$_REQUEST["evt_comments"]}',
                        evt_equipo_mantid = {$_REQUEST["evt_equipo_mantid"]},
                        evt_ubim_id       = {$_REQUEST["evt_ubim_id"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_equipo_mantid, evt_ubim_id)
                     VALUES
                     ({$hasopenot["id"]}, 0, {$currtme}, 1, 'mantencion', '{$_REQUEST["evt_comments"]}',
                      {$_REQUEST["evt_equipo_mantid"]}, {$_REQUEST["evt_ubim_id"]})";
            $CON->no_result($sql);
         }
         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>';
         </script>
         <?php
         exit;
      }

      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "pausesave")
      {
         $_REQUEST["evt_comments"]     = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["evt_pause_id"]     = (int)$_REQUEST["evt_pause_id"];
         $currtme = time();
         if((int)$_REQUEST["refid"])
         {
            $sql = " update prod_worker_ot_events
                     set
                     evt_comments   = '{$_REQUEST["evt_comments"]}',
                     evt_enddat     = {$currtme},
                     evt_pause_id   = {$_REQUEST["evt_pause_id"]}
                     where
                     id = {$_REQUEST["refid"]}";
            $CON->no_result($sql);
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_pause_id)
                     VALUES
                     ({$hasopenot["id"]}, 0, {$currtme}, 1, 'pause', '{$_REQUEST["evt_comments"]}',
                      {$_REQUEST["evt_pause_id"]})";
            $CON->no_result($sql);
         }
         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>';
         </script>
         <?php
         exit;
      }
      
      
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "aperturasave")
      {
         $_REQUEST["evt_comments"] = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["evt_medida_fromid"] = (int)$_REQUEST["evt_medida_fromid"];
         $_REQUEST["evt_medida_toid"] = (int)$_REQUEST["evt_medida_toid"];

         $currtme = time();

         if((int)$_REQUEST["refid"])
         {
            if($_REQUEST["submode"] == "end")
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_comments      = '{$_REQUEST["evt_comments"]}',
                        evt_medida_fromid = {$_REQUEST["evt_medida_fromid"]},
                        evt_medida_toid   = {$_REQUEST["evt_medida_toid"]},
                        evt_enddat        = {$currtme},
                        evt_idayudante    = {$_REQUEST["evt_idayudante"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else 
            if($_REQUEST["submode"] == "cerrar")
            {
               $sql = " select evt_prod_worker_otid from prod_worker_ot_events where id = {$_REQUEST["refid"]} ";
               $evt = $CON->select($sql);
               $evt = $evt[0];

               $sql = " update prod_worker_ot_events
                           set
                           evt_comments      = '{$_REQUEST["evt_comments"]}',
                           evt_medida_fromid = {$_REQUEST["evt_medida_fromid"]},
                           evt_medida_toid   = {$_REQUEST["evt_medida_toid"]},
                           evt_enddat        = {$currtme},
                           evt_idayudante    = {$_REQUEST["evt_idayudante"]}
                           where
                           id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);

               $sql = "update prod_worker_ot set wok_enddat = {$currtme} , wok_status = 2 where id = {$evt["evt_prod_worker_otid"]} ";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_comments      = '{$_REQUEST["evt_comments"]}',
                        evt_medida_fromid = {$_REQUEST["evt_medida_fromid"]},
                        evt_medida_toid   = {$_REQUEST["evt_medida_toid"]},
                        evt_idayudante    = {$_REQUEST["evt_idayudante"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments,
                      evt_medida_fromid, evt_medida_toid, evt_idayudante)
                     VALUES
                     ({$hasopenot["id"]}, 0, {$currtme}, 1, 'apertura', '{$_REQUEST["evt_comments"]}',
                      {$_REQUEST["evt_medida_fromid"]}, {$_REQUEST["evt_medida_toid"]},{$_REQUEST["evt_idayudante"]})";
            $CON->no_result($sql);
         }

         

         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';
         </script>
         <?php
         exit;
      }

      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["refid"] && ($_REQUEST["mode"] == "productionmulticolorsave" || $_REQUEST["mode"] == "productionsave"))
      {
         $_MERMACONVERT    = (int)$_REQUEST["mermaconvert"];
         $_MERMAMTSCONVERT = (int)$_REQUEST["mermamtsconvert"];
         $_REPAIRCONVERT   = (int)$_REQUEST["repairconvert"];

         foreach(array_keys($_REQUEST) AS $reqkey)
         {
            if(strpos($reqkey, "merma_comments_") !== false && strpos($reqkey, "merma_comments_") == 0)
            {
               $xtypeid          = substr($reqkey, strrpos($reqkey, "_") +1);
               $xamount          = getPrice($_REQUEST["merma_amount_{$xtypeid}"],2);
               $xcomment         = trim(addslashes($_REQUEST["merma_comments_{$xtypeid}"]));
               $evt_kgstounits   = getPrice($_REQUEST["merma_kgs_{$xtypeid}"],2);
               $evt_mtstounits   = (int)trim($_REQUEST["merma_mts_{$xtypeid}"]);
               $unidad_medida    = (int)trim($_REQUEST["unidaddemedida_{$xtypeid}"]);

               if($unidad_medida == 2)
               {
                  $peso_unitario = getCalculaPesoUnitario($CON, $hasopenot);
                  $evt_kgstounits = $peso_unitario * $evt_kgstounits;
               }

               if($unidad_medida == 5)
               {
                  $peso_unitario = getCalculaPesoUnitario($CON, $hasopenot);
                  $xamount =  round($xamount / $peso_unitario,0);
               }

               /* ********************************************************************************** */
               $sql = " select *
                        from prod_worker_ot_defectunits
                        where
                        evt_refid = {$_REQUEST["refid"]} and
                        evt_type  = 'merma' and
                        evt_merma_typeid = {$xtypeid}";
               $mermathisdata = $CON->select($sql);
               $mermathisdata = $mermathisdata[0];

               if($xamount != 0 || $evt_kgstounits != 0 || $evt_mtstounits != 0)
               {

                  if((int)$mermathisdata["id"])
                  {
                     $sql = " update prod_worker_ot_defectunits
                              set
                              evt_amount     = {$xamount},
                              evt_kgstounits = {$evt_kgstounits},
                              evt_mtstounits = {$evt_mtstounits},
                              evt_comments   = '{$xcomment}'
                              where
                              id = {$mermathisdata["id"]}";
                     $CON->no_result($sql);
                  }
                  else
                  {
                     $currtme = time();
                     $sql = " insert into prod_worker_ot_defectunits
                              (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments,
                               evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                              VALUES
                              ({$xamount}, {$currtme}, 1, 'merma', '{$xcomment}',
                               {$xtypeid}, {$_REQUEST["refid"]}, {$evt_kgstounits}, {$evt_mtstounits})";
                     $CON->no_result($sql);
                  }
               }
               else
               {
                  if((int)$mermathisdata["id"])
                  {
                     $sql = " delete from prod_worker_ot_defectunits
                              where
                              id = {$mermathisdata["id"]}";
                     $CON->no_result($sql);
                  }
               }
            }

            if(strpos($reqkey, "repair_comments_") !== false && strpos($reqkey, "repair_comments_") == 0)
            {
               $xtypeid          = substr($reqkey, strrpos($reqkey, "_") +1);
               $xamount          = getPrice($_REQUEST["repair_amount_{$xtypeid}"],2);
               $xcomment         = trim(addslashes($_REQUEST["repair_comments_{$xtypeid}"]));
               $evt_kgstounits   = getPrice($_REQUEST["repair_kgs_{$xtypeid}"],2);
               $unidad_medida    = (int)trim($_REQUEST["unidaddemedida_{$xtypeid}"]);

               if($unidad_medida == 2)
               {
                  $peso_unitario = getCalculaPesoUnitario($CON, $hasopenot);
                  $evt_kgstounits = $peso_unitario * $evt_kgstounits;
               }
               if($unidad_medida == 5)
               {
                  $peso_unitario = getCalculaPesoUnitario($CON, $hasopenot);
                  $xamount =  round($xamount / $peso_unitario,0);
               }
              
               $sql = " select *
                        from prod_worker_ot_defectunits
                        where
                        evt_refid = {$_REQUEST["refid"]} and
                        evt_type  = 'repair' and
                        evt_repair_typeid = {$xtypeid}";
               $repthisdata = $CON->select($sql);
               $repthisdata = $repthisdata[0];

               if($xamount != 0 || $evt_kgstounits != 0)
               {
                  if((int)$repthisdata["id"])
                  {
                     $sql = " update prod_worker_ot_defectunits
                              set
                              evt_amount     = {$xamount},
                              evt_kgstounits = {$evt_kgstounits},
                              evt_mtstounits = {$evt_mtstounits},
                              evt_comments   = '{$xcomment}'
                              where
                              id = {$repthisdata["id"]}";
                     $CON->no_result($sql);
                  }
                  else
                  {
                     $currtme = time();
                     $sql = " insert into prod_worker_ot_defectunits
                              (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_repair_typeid,
                               evt_refid, evt_kgstounits)
                              VALUES
                              ({$xamount}, {$currtme}, 1, 'repair', '{$xcomment}',
                               {$xtypeid}, {$_REQUEST["refid"]}, {$evt_kgstounits})";
                     $CON->no_result($sql);
                  }
               }
               else
               {
                  if((int)$repthisdata["id"])
                  {
                     $sql = " delete from prod_worker_ot_defectunits
                              where
                              id = {$repthisdata["id"]}";
                     $CON->no_result($sql);
                  }
               }
            }
         }

         //----------------------------------------------------------------------------------
         if((int)$_REQUEST["embalaje_save_params"])
         {
            $sql = " delete from prod_worker_embalajes
                     where
                     evt_prod_worker_otid = {$hasopenot["id"]} and
                     evt_reqid            = {$hasopenot["prd_reqid"]}";
            $CON->no_result($sql);

            $currtme = time();
            $_REQUEST["req_embalaje_medidas_caja"]                = trim(addslashes($_REQUEST["req_embalaje_medidas_caja"]));
            $_REQUEST["req_embalaje_bolsas_por_caja_amt"]         = (int)trim($_REQUEST["req_embalaje_bolsas_por_caja_amt"]);
            $_REQUEST["req_embalaje_cajas_por_pallet_amt"]        = (int)trim($_REQUEST["req_embalaje_cajas_por_pallet_amt"]);
            $_REQUEST["req_embalaje_pallets_completos_amt"]       = (int)trim($_REQUEST["req_embalaje_pallets_completos_amt"]);
            $_REQUEST["req_embalaje_palletcajas_incompletos_amt"] = (int)trim($_REQUEST["req_embalaje_palletcajas_incompletos_amt"]);
            $_REQUEST["req_embalaje_cajas_completas_amt"]         = (int)trim($_REQUEST["req_embalaje_cajas_completas_amt"]);
            $_REQUEST["req_embalaje_caja_final"]                  = (int)trim($_REQUEST["req_embalaje_caja_final"]);
            $_REQUEST["req_embalaje_bolsas_sobrantes_amt"]        = (int)trim($_REQUEST["req_embalaje_bolsas_sobrantes_amt"]);
            $_REQUEST["req_embalaje_showroom"]                    = (int)trim($_REQUEST["req_embalaje_showroom"]);
            $_REQUEST["req_codigo_cierre"]                        = trim(addslashes($_REQUEST["req_codigo_cierre"]));
            $_REQUEST["req_id_bodega"]                            = (int)trim($_REQUEST["req_id_bodega"]);
            $_REQUEST["req_observacion"]                          = trim(addslashes($_REQUEST["req_observacion"]));

            $_REQUEST["refid"] = (int)$_REQUEST["refid"];
            $sql = " insert into prod_worker_embalajes
                     (evt_prod_worker_otid, evt_reqid, evt_crtdat, req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
                      req_embalaje_cajas_por_pallet_amt, req_embalaje_pallets_completos_amt, req_embalaje_palletcajas_incompletos_amt,
                      req_embalaje_cajas_completas_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt,
                      req_embalaje_showroom, req_id_bodega, req_codigo_cierre, req_observacion, req_v2refid)
                     VALUES
                     ({$hasopenot["id"]}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
                      {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]}, {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
                      {$_REQUEST["req_embalaje_pallets_completos_amt"]}, {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
                      {$_REQUEST["req_embalaje_cajas_completas_amt"]}, {$_REQUEST["req_embalaje_caja_final"]},
                      {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]},{$_REQUEST["req_embalaje_showroom"]},
                      {$_REQUEST["req_id_bodega"]}, '{$_REQUEST["req_codigo_cierre"]}','{$_REQUEST["req_observacion"]}',
                      {$_REQUEST["refid"]})";
            $resp = $CON->no_result($sql);
         }
      }
      
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "productionmulticolorsave")
      {
         $_REQUEST["prod_amount"]   = getPrice(trim($_REQUEST["prod_amount"]));
         $_REQUEST["prod_amount2"]   = getPrice(trim($_REQUEST["prod_amount2"]));
         $_REQUEST["evt_comments"]  = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["prod_seri_color"]  = trim(addslashes($_REQUEST["prod_seri_color"]));
         $_REQUEST["prod_bobina_kg"]   = getPrice(trim($_REQUEST["prod_bobina_kg"]),2);

         $currtme = time();
         if((int)$_REQUEST["refid"])
         {
            if($_REQUEST["submode"] == "end")
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount     = {$_REQUEST["prod_amount"]},
                        evt_amount2    = {$_REQUEST["prod_amount2"]},
                        prod_bobina_kg = {$_REQUEST["prod_bobina_kg"]},
                        evt_comments   = '{$_REQUEST["evt_comments"]}',
                        prod_seri_color = '{$_REQUEST["prod_seri_color"]}',
                        evt_enddat     = {$currtme}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount     = {$_REQUEST["prod_amount"]},
                        evt_amount2     = {$_REQUEST["prod_amount2"]},
                        prod_bobina_kg = {$_REQUEST["prod_bobina_kg"]},
                        prod_seri_color = '{$_REQUEST["prod_seri_color"]}',
                        evt_comments   = '{$_REQUEST["evt_comments"]}'
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type,
                      evt_comments, prod_bobina_kg, prod_seri_color, evt_amount2)
                     VALUES
                     ({$hasopenot["id"]}, {$_REQUEST["prod_amount"]}, {$currtme}, 1, 'prodsericolor',
                      '{$_REQUEST["evt_comments"]}', {$_REQUEST["prod_bobina_kg"]}, '{$_REQUEST["prod_seri_color"]}', {$_REQUEST["prod_amount2"]})";
            $CON->no_result($sql);
         }

         if((int)$_REQUEST["prod_bobina_kg"])
         {

            $sql = " select distinct t0.*,
                        t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                        t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                        t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                        t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                        t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                        fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                        fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                        fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                        t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                        t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
                        t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                        t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                        t1x.fab_manilla_length, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cc_genrefid
               from prod_agenda t0
               INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
               INNER JOIN orders t1             ON t0.ag_reqid = t1.id
               LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
               LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
               INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
               INNER JOIN item t2x              ON t1x.item_id = t2x.id
               LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
               LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
               where
               t0.id = {$hasopenot["wok_ag_id"]}";
            $tempagenda = $CON->select($sql);
            $tempagenda = $tempagenda[0];

            if($tempagenda["fab_printtype"] == "FLEX")
            {
               $sql = " select t2.id, t2.st_name
                        from company_shops_storehouses t2 
                        where
                        t2.st_status               = 1 and
                        t2.st_shop_id              = {$tempagenda["req_shop_id"]} and
                        t2.st_unibagflexo_act      = 1
                        order by t2.st_name";
               $destsths = $CON->select($sql);
            }
            elseif($tempagenda["fab_printtype"] == "SERI")
            {
               $sql = " select t2.id, t2.st_name
                        from company_shops_storehouses t2 
                        where
                        t2.st_status               = 1 and
                        t2.st_shop_id              = {$tempagenda["req_shop_id"]} and
                        t2.st_unibagseri_act       = 1
                        order by t2.st_name";
               $destsths = $CON->select($sql);
            }

            if( (int)$events2["evt_item_id_sobrante"] < 1)
            {
               $sql              = "select si.item_id
                                   , i.item_number_prod
                                   , i.item_title
                                   , i.item_nameshop 
                                   , cat_id
		                           from stockchanges_items si
			                           inner join item i on i.id = si.item_id
                                    inner join stockchanges s on s.sth_fromprodotid = {$hasopenot["id"]} and si.stk_id = s.id
                                    inner join item_productcats ipc on ipc.item_id = i.id and cat_id = 6
                                 ORDER BY si.item_id DESC LIMIT 1;";
               $itemid           = $CON->select($sql);
               $itemid           = $itemid[0];
            
               $sql = " update prod_worker_ot_events, klfdkldsfkld
                        set
                        evt_item_id_sobrante = {$itemid},
                        where id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
               $itemid = (int)$events2["evt_item_id_sobrante"];

            $annottext        = $_REQUEST["prod_bobina_kg"].' Kilogramos';
            $shopid           = (int)$tempagenda["req_shop_id"];
            $stk_fixedsthid   = (int)$destsths[0]["id"];
            $stk_bookdate     = time();
            $currtme          = time();

            $stk_num          = createTransactionNumber($CON, $_SESSION["user_company_id"], "stockchange");

            
            $sql = " insert into stockchanges
                     (stk_num
                    , stk_annotation
                    , stk_issueid
                    , stk_companyid
                    , stk_shopid
                    , stk_bookdate
                    , stk_negative
                    , stk_crtdat
                    , stk_crtusr
                    , stk_fixedsthid
                    , sth_fromprodotid)
                     VALUES
                     ('{$stk_num}'
                     , '{$annottext}'
                     , 2
                     , {$_SESSION["user_company_id"]}
                     , {$shopid}
                     , {$stk_bookdate}
                     , 1
                     , {$currtme}
                     , {$_SESSION["user_id"]}
                     , {$stk_fixedsthid}
                     , {$hasopenot["id"]})";

            $res = $CON->no_result($sql);
            if($res)
            {
               $stk_id = mysql_insert_id();
               $sql = " insert into stockchanges_items
                        (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                         item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                         item_sellprice_brutto)
                        VALUES
                        ({$stk_id}, {$itemid}, 0, {$_REQUEST["prod_bobina_kg"]}, 'item',
                         {$stk_fixedsthid}, 0.00, 0.00, 0.00, 0.00, 0, 0)";
               $CON->no_result($sql);
               bookStockChange($CON, $stk_id);
            }
         }

         if(!(int)$_REQUEST["reloadact"])
         {  ?>
            <script language="JavaScript">
               location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>';
            </script>
            <?php
            exit;
         }
         else
         {
            $_REQUEST["mode"] = "production";
         }
      }

      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "productionsave")
      {
         $_REQUEST["overrideembalaje"] = (int)$_REQUEST["overrideembalaje"];
         $_REQUEST["prod_amount"]         = getPrice(trim($_REQUEST["prod_amount"]));
         $_REQUEST["prod_amount2"]        = getPrice(trim($_REQUEST["prod_amount2"]));
         $_REQUEST["evt_comments"]        = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["prod_bobina_kg"]      = getPrice(trim($_REQUEST["prod_bobina_kg"]),2);

         $_REQUEST["evt_amount_metros_maquina"]    = (int)getPrice(trim($_REQUEST["evt_amount_metros_maquina"]));
         $_REQUEST["evt_amount_metros_lineales"]   = (int)getPrice(trim($_REQUEST["evt_amount_metros_lineales"]));

         $_REQUEST["idx_pulpo_seri_pasadas_inp_frente"]  = getPrice(trim($_REQUEST["idx_pulpo_seri_pasadas_inp_frente"]));
         $_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"]   = getPrice(trim($_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"]));
         $_REQUEST["idx_xamt_colors_frente"]             = (int)$_REQUEST["idx_xamt_colors_frente"];
         $_REQUEST["idx_xamt_colors_dorso"]              = (int)$_REQUEST["idx_xamt_colors_dorso"];
         if(!$_REQUEST["idx_xamt_colors_frente"])
            $_REQUEST["idx_pulpo_seri_pasadas_inp_frente"] = 0;
         if(!$_REQUEST["idx_xamt_colors_dorso"])
            $_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"] = 0;

         $currtme = time();
         if((int)$_REQUEST["refid"])
         {
            $sql_recalceventid = $_REQUEST["refid"];
            if($_REQUEST["submode"] == "end")
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount                 = {$_REQUEST["prod_amount"]},
                        evt_amount2                = {$_REQUEST["prod_amount2"]},
                        prod_bobina_kg             = {$_REQUEST["prod_bobina_kg"]},
                        evt_comments               = '{$_REQUEST["evt_comments"]}',
                        evt_amount_metros_maquina  = {$_REQUEST["evt_amount_metros_maquina"]},
                        evt_amount_metros_lineales = {$_REQUEST["evt_amount_metros_lineales"]},
                        evt_metrotype              = '{$_REQUEST["sql_metrotype"]}',
                        evt_enddat                 = {$currtme},
                        evt_amount_pulpo_frente    = {$_REQUEST["idx_pulpo_seri_pasadas_inp_frente"]},
                        evt_amount_pulpo_dorso     = {$_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount                 = {$_REQUEST["prod_amount"]},
                        evt_amount2                = {$_REQUEST["prod_amount2"]},
                        prod_bobina_kg             = {$_REQUEST["prod_bobina_kg"]},
                        evt_comments               = '{$_REQUEST["evt_comments"]}',
                        evt_amount_metros_maquina  = {$_REQUEST["evt_amount_metros_maquina"]},
                        evt_amount_metros_lineales = {$_REQUEST["evt_amount_metros_lineales"]},
                        evt_metrotype              = '{$_REQUEST["sql_metrotype"]}',
                        evt_amount_pulpo_frente    = {$_REQUEST["idx_pulpo_seri_pasadas_inp_frente"]},
                        evt_amount_pulpo_dorso     = {$_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            // echO $sql;
            // exit;
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, prod_bobina_kg,
                      evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype, evt_amount2,
                      evt_amount_pulpo_frente, evt_amount_pulpo_dorso, overrideembalaje_act)
                     VALUES
                     ({$hasopenot["id"]}, {$_REQUEST["prod_amount"]}, {$currtme}, 1, 'prod',
                      '{$_REQUEST["evt_comments"]}', {$_REQUEST["prod_bobina_kg"]}, {$_REQUEST["evt_amount_metros_maquina"]},
                      {$_REQUEST["evt_amount_metros_lineales"]}, '{$_REQUEST["evt_metrotype"]}', {$_REQUEST["prod_amount2"]},
                      {$_REQUEST["idx_pulpo_seri_pasadas_inp_frente"]}, {$_REQUEST["idx_pulpo_seri_pasadas_inp_dorso"]},
                      {$_REQUEST["overrideembalaje"]})";
            $evres = $CON->no_result($sql);
            if($evres)
               $sql_recalceventid = mysql_insert_id();
         }
        
         if((int)$_REQUEST["prod_bobina_kg"])
         {

            $sql = " select distinct t0.*,
                        t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                        t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                        t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                        t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                        t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                        fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                        fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                        fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                        t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                        t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
                        t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                        t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                        t1x.fab_manilla_length, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cc_genrefid
               from prod_agenda t0
               INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
               INNER JOIN orders t1             ON t0.ag_reqid = t1.id
               LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
               LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
               INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
               INNER JOIN item t2x              ON t1x.item_id = t2x.id
               LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
               LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
               where
               t0.id = {$hasopenot["wok_ag_id"]}";
            $tempagenda = $CON->select($sql);
            $tempagenda = $tempagenda[0];

            if($tempagenda["fab_printtype"] == "FLEX")
            {
               $sql = " select t2.id, t2.st_name
                        from company_shops_storehouses t2 
                        where
                        t2.st_status               = 1 and
                        t2.st_shop_id              = {$tempagenda["req_shop_id"]} and
                        t2.st_unibagflexo_act      = 1
                        order by t2.st_name";
               $destsths = $CON->select($sql);
            }
            elseif($tempagenda["fab_printtype"] == "SERI")
            {
               $sql = " select t2.id, t2.st_name
                        from company_shops_storehouses t2 
                        where
                        t2.st_status               = 1 and
                        t2.st_shop_id              = {$tempagenda["req_shop_id"]} and
                        t2.st_unibagseri_act       = 1
                        order by t2.st_name";
               $destsths = $CON->select($sql);
            }
            if( (int)$events2["evt_item_id_sobrante"] < 1)
            {
               $sql              = "select si.item_id
                                   , i.item_number_prod
                                   , i.item_title
                                   , i.item_nameshop 
                                   , cat_id
		                           from stockchanges_items si
			                           inner join item i on i.id = si.item_id
                                    inner join stockchanges s on s.sth_fromprodotid = {$hasopenot["id"]} and si.stk_id = s.id
                                    inner join item_productcats ipc on ipc.item_id = i.id and cat_id = 6
                                 ORDER BY si.item_id DESC LIMIT 1;";
               $itemid           = $CON->select($sql);
               $itemid           = $itemid[0];
               $sql = " update prod_worker_ot_events
                        set
                        evt_item_id_sobrante = {$itemid["item_id"]}
                        where id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
              
            } 
            else
               $itemid = (int)$events2["evt_item_id_sobrante"];

            $annottext        = $_REQUEST["prod_bobina_kg"].' Kilogramos';
            $shopid           = (int)$tempagenda["req_shop_id"];
            $stk_fixedsthid   = (int)$destsths[0]["id"];
            $stk_bookdate     = time();
            $currtme          = time();
            
            $stk_num          = createTransactionNumber($CON, $_SESSION["user_company_id"], "stockchange");
            $sql = " insert into stockchanges
                     (stk_num
                    , stk_annotation
                    , stk_issueid
                    , stk_companyid
                    , stk_shopid
                    , stk_bookdate
                    , stk_negative
                    , stk_crtdat
                    , stk_crtusr
                    , stk_fixedsthid
                    , sth_fromprodotid)
                     VALUES
                     ('{$stk_num}'
                     , '{$annottext}'
                     , 2
                     , {$_SESSION["user_company_id"]}
                     , {$shopid}
                     , {$stk_bookdate}
                     , 1
                     , {$currtme}
                     , {$_SESSION["user_id"]}
                     , {$stk_fixedsthid}
                     , {$hasopenot["id"]})";
            $res = $CON->no_result($sql);
            if($res)
            {
               $stk_id = mysql_insert_id();
               $sql = " insert into stockchanges_items
                        (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                         item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                         item_sellprice_brutto)
                        VALUES
                        ({$stk_id}, {$itemid}, 0, {$_REQUEST["prod_bobina_kg"]}, 'item',
                         {$stk_fixedsthid}, 0.00, 0.00, 0.00, 0.00, 0, 0)";
               $CON->no_result($sql);
               bookStockChange($CON, $stk_id);
            }
         }

         /* datos de consumo */
         $currtme = time();
         // echo($hasopeninit["win_equipoid"]);
         $sql = " select equipo_type_id from equipo where id = {$hasopeninit["win_equipoid"]} ";
         $equipo = $CON->select($sql);

         
         $sql = "select i.* , 0 as salida, 0 as entrada from parametros p
         inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
         inner join item i on p.codigo = i.item_number_prod
         where tabla = 'INSUMOS' ";
         $materiales = $CON->select($sql);

         if(in_array($equipo[0]["equipo_type_id"], [7, 11]))
         {
            $sql = " select fab_print_colordesc_1
               ,fab_print_colordesc_2
               ,fab_print_colordesc_3
               ,fab_print_colordesc_4
               ,fab_print_colordesc_5
               ,fab_print_colordesc_6
               ,fab_print_colordesc_7
               ,fab_print_colordesc_8
               ,fab_print_colordesc_9
               ,fab_print_colordesc_10
               ,0 as entrada_1
               ,0 as entrada_2
               ,0 as entrada_3
               ,0 as entrada_4
               ,0 as entrada_5
               ,0 as entrada_6
               ,0 as entrada_7
               ,0 as entrada_8
               ,0 as entrada_9
               ,0 as entrada_10
               ,0 as salida_1
               ,0 as salida_2
               ,0 as salida_3
               ,0 as salida_4
               ,0 as salida_5
               ,0 as salida_6
               ,0 as salida_7
               ,0 as salida_8
               ,0 as salida_9
               ,0 as salida_10
            from orders_items 
               where req_id = {$hasopenot["prd_reqid"]} ";
            $colores = $CON->select($sql);
            $colores = $colores[0];
         }     

         $stk_num  = createTransactionNumber($CON, $_SESSION["user_company_id"], "consumo");
         $sql = " insert into consumos(numero,
                             anotacion,
                             maquina_id,
                             compañia,
                             sucursal,
                             estado,
                             fecha_creacion,
                             usuario_creacion,
                             fecha_actualizacion,
                             usuario_actualizacion,
                             order_id,
                             ag_id)
                    VALUES(
                    '{$stk_num}',
                    '',
                    {$equipo[0]["equipo_type_id"]},
                    0,
                    0,
                    1,
                    {$currtme},
                    {$_SESSION["user_id"]},
                    {$currtme},
                    {$_SESSION["user_id"]},
                    {$hasopenot["prd_reqid"]},
                    {$_REQUEST["agid"]} )";

         $res = $CON->no_result($sql);
         if($res)
         { 
            $stk_id = mysql_insert_id();
            $x = 0;
            foreach($materiales AS $material)
            {  
               $consumo1 = $_REQUEST["entrada_".$x] - $_REQUEST["salida_".$x];
               $sql = " insert into consumos_item(consumo_id,
                                                  item_id,
                                                  consumo,
                                                  entrada,
                                                  salida)
                                            VALUES
                                            ({$stk_id},
                                            {$material["id"]},
                                            {$consumo1},
                                            {$_REQUEST["entrada_".$x]},
                                            {$_REQUEST["salida_".$x]} ) " ;
              $CON->no_result($sql);
              $x++;
           }
           
           
           for ($x = 1; $x <= 10; $x++)
           { 
              if($colores["fab_print_colordesc_".$x]!="")
              {
                 $consumo1 = $_REQUEST["entrada_color".$x] - $_REQUEST["salida_color".$x];
                 $sql = " insert into consumos_color(numero
                                                    ,consumo_id
                                                    ,color
                                                    ,consumo
                                                    ,entrada
                                                    ,salida)
                                      values({$x}
                                            ,{$stk_id}
                                            ,'{$colores["fab_print_colordesc_".$x]}'
                                            ,{$consumo1}
                                            ,{$_REQUEST["entrada_color".$x]}
                                            ,{$_REQUEST["salida_color".$x]} )" ;
                 $CON->no_result($sql);
              }
           }
  
         }

         /* fin grabacion de consumo */


         if(!(int)$_REQUEST["reloadact"])
         {  ?>
            <script language="JavaScript">
               location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&recalctype=<?=$_REQUEST["sql_metrotype"]?>&recalcevtid=<?=$sql_recalceventid?>&mermaconvert=<?=(int)$_MERMACONVERT?>&mermamtsconvert=<?=$_MERMAMTSCONVERT?>&repairconvert=<?=(int)$_REPAIRCONVERT?>&agid=<?=$_REQUEST["agid"]?>';
            </script>
            <?php
            exit;
         }
         else
         {
            $_REQUEST["mode"] = "production";
         }
      }
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "eventdelete")
      {
         $sql = " update prod_worker_ot_events
                  set
                  evt_status = 0
                  where
                  id = {$_REQUEST["refid"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select distinct t0.*,
                      t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                      t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                      t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                      t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                      fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                      fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                      fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                      t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                      t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4, 
                      t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                      t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                      t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms,
                      t1.req_operador_bastidoramt, t1.req_operador_mermaperc, t1.req_operador_tela_width,
                      t1.req_solic_devprints_poltype, t1x.fab_manilla_length, t1.req_rebo_type,
                      t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc,
                      t1x.item_sellprice_barcodenumber,
                      t1.req_infoaddprd_dado_manillas,
                      t1.req_infoaddprd_cabezal_act,
                      t1.req_infoaddprd_procedencia,
                      t1.req_infoaddprd_reversa_act,
                      t1.req_infoaddprd_cliche_ubicacion,
                      t1.req_infoaddprd_cliche_codigo,
                      t1.req_solic_supp_file_0, t1.req_solic_supp_file_1, t1.req_solic_supp_file_2,
                      t1.req_solic_supp_file_3, t1.req_solic_supp_file_4, t1.req_solic_supp_name_0,
                      t1.req_solic_supp_name_1, t1.req_solic_supp_name_2, t1.req_solic_supp_name_3,
                      t1.req_solic_supp_name_4, t1x.fab_mat_dispositivo, tz.descripcion 'dispositivo',
                      t1x.item_id, tz2.cat_id,
                      t1.req_embalaje_medidas_caja, t1.req_embalaje_cajas_por_pallet_amt,
                      t1.req_embalaje_bolsas_por_caja_amt,
                      t1.req_caja_impresa, t1.req_embalaje_cajas_completas_amt, t1.req_embalaje_caja_final,
                      t1.req_pie_imprenta, t1.req_despacho_desc,
                      to1.req_number 'reqnum_mezcla_1', to2.req_number 'reqnum_mezcla_2',
                      to3.req_number 'reqnum_mezcla_3', t1.req_rebo_cc_genrefid
               from prod_agenda t0
               INNER JOIN prod_header t3x                ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
               INNER JOIN orders t1                      ON t0.ag_reqid = t1.id
               LEFT OUTER JOIN company_data t2           ON t1.req_company_id = t2.id
               LEFT OUTER JOIN company_shops t3          ON t1.req_shop_id    = t3.id
               LEFT OUTER JOIN customer t4               ON t1.req_cust_id    = t4.id
               INNER JOIN orders_items t1x               ON t1.id = t1x.req_id
               INNER JOIN item t2x                       ON t1x.item_id = t2x.id
               LEFT OUTER JOIN tran_comments_vals v1     ON t1x.fab_mat_fabric_color = v1.id
               LEFT OUTER JOIN tran_comments_vals v2     ON t1x.fab_mat_manilla_color = v2.id
               LEFT OUTER JOIN parametros tz             ON t1x.fab_mat_dispositivo = tz.codigo and tz.tabla = 'DISPOSITIVO'
               LEFT OUTER JOIN item_productcats tz2      ON t1x.item_id = tz2.item_id
               LEFT OUTER JOIN productcats tz3           ON tz2.cat_id = tz3.id
               LEFT OUTER JOIN orders to1                ON t1.req_id_cc_m1 = to1.id
               LEFT OUTER JOIN orders to2                ON t1.req_id_cc_m2 = to2.id
               LEFT OUTER JOIN orders to3                ON t1.req_id_cc_m3 = to3.id
               where
               t0.id = {$hasopenot["wok_ag_id"]}";
      $agenda = $CON->select($sql);
      $agenda = $agenda[0];


      $sql = "select * from orders where id = {$agenda["req_id_cc_m1"]}";
      $mixto1 = $CON->select($sql);
      $mixto1 = $mixto1[0];

      $sql = "select * from orders where id = {$agenda["req_id_cc_m2"]}";
      $mixto2 = $CON->select($sql);
      $mixto2 = $mixto2[0];

      $sql = "select * from orders where id = {$agenda["req_id_cc_m3"]}";
      $mixto3 = $CON->select($sql);
      $mixto3 = $mixto3[0];


      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY
//       sendV2PordSupervisorNotify($CON, $hasopenot, $hasopeninit, $agenda);
      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY

      $printcolors      = "";
      $entrega_vals     = "";
      $printcolors_emb  = "";

      //----------------------------------------------------------------------------------
      for($xx = 1; $xx <= 10; $xx++)
      {
         if((int)$agenda["fab_print_colors_front_{$xx}"] || (int)$agenda["fab_print_colors_back_{$xx}"])
         {
            if((int)$agenda["fab_print_colors_front_{$xx}"] && !(int)$agenda["fab_print_colors_back_{$xx}"])
            {
               $printcolors .= "Frente: {$agenda["fab_print_colordesc_{$xx}"]}, ";
               $printcolors_emb .= "{$agenda["fab_print_colordesc_{$xx}"]}, ";
            }
            elseif(!(int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
            {
               $printcolors .= "Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
               $printcolors_emb .= "{$agenda["fab_print_colordesc_{$xx}"]}, ";
            }
            elseif((int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
            {
               $printcolors .= "Frente/Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
               $printcolors_emb .= "{$agenda["fab_print_colordesc_{$xx}"]}, ";
            }
         }
      }
      $printcolors = substr($printcolors, 0, -2);
      $printcolors_emb = substr($printcolors_emb, 0, -2);

      //----------------------------------------------------------------------------------
      $sql = " select *
               from prod_amtplan
               where
               prodplan_prdid = {$agenda["prdid"]}
               order by prodplan_date asc";
      $entregas = $CON->select($sql);
      foreach($entregas AS $entrega)
         $entrega_vals .= printPrice($entrega["prodplan_amt"])." (".date("d.m.Y", $entrega["prodplan_date"])."), ";
      $entrega_vals = substr($entrega_vals, 0, -2);

      $_THIS_STATS   = getProdStats($CON, 0               , $hasopenot["wok_ag_id"], $hasopenot["id"], 0, 0,0);
      $_OT_STATS     = getProdStats($CON, $agenda["prdid"],0,0,0,0,$agenda["ag_equipotype_id"]);

      ?>
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <input type="hidden" name="prod_eve" value="<?$_PROD_EVENT["id"]?>">
         <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
            <?php
            //----------------------------------------------------------------------------------
            // ITEM PARAMS
            //----------------------------------------------------------------------------------
            $sql = " select t1.com_name, t3.*
                     from tran_comments t1
                     INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                     INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                     where
                     t1.com_status  > 0 and
                     t2.cat_id      = {$agenda["cat_id"]} and
                     t3.add_status  > 0
                     order by t1.com_name, t3.add_name";
            $trancoms = $CON->select($sql);
            foreach($trancoms AS $trancom)
            {
               $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
               $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
            }

            $sql = " select t1.*, t2.add_name
                     from tran_comments_item_vals t1
                     INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
                     where
                     t1.item_id = {$agenda["item_id"]}";
            $comvals = $CON->select($sql);
            foreach($comvals AS $comval)
               $_COMVALS[$comval["com_id"]] = $comval["add_name"];

            $_TRANSCOMINFOS = Array();
            foreach(array_keys($_TRANSCOM) AS $comid)
            {
               $commname = ucwords(strtolower($_TRANSCOM[$comid]["NAME"]));
               if($_COMVALS[$comid] != "")
               {
                  $_TRANSCOMINFOS[$comid]["NAME"]  = $commname;
                  $_TRANSCOMINFOS[$comid]["VALUE"] = $_COMVALS[$comid];
               }
            }

            //----------------------------------------------------------------------------------
            $req_infoaddprd_alto_tiro        = "";
            $req_infoaddprd_alto_retiro      = "";
            $req_infoaddprd_doblez_superior  = "";
            $req_infoaddprd_ancho_manillas   = "";
            $req_infoaddprd_itemtype         = "";
            foreach(array_keys($_TRANSCOMINFOS) AS $comid)
            {
               switch($comid)
               {
                  case 20: $req_infoaddprd_itemtype = $_TRANSCOMINFOS[$comid]["VALUE"]; break;
                  case 39: $req_infoaddprd_alto_tiro = $_TRANSCOMINFOS[$comid]["VALUE"]; break;
                  case 40: $req_infoaddprd_alto_retiro = $_TRANSCOMINFOS[$comid]["VALUE"]; break;
                  case 41: $req_infoaddprd_doblez_superior = $_TRANSCOMINFOS[$comid]["VALUE"]; break;
                  case 49: $req_infoaddprd_ancho_manillas = $_TRANSCOMINFOS[$comid]["VALUE"]; break;
               }
            }

            if((int)$agenda["item_prodcalc_fuelle_act"])
               $param_medida = (int)$agenda["fab_med_width"] + (int)$agenda["fab_med_fuelle"];
            else
               $param_medida = (int)$agenda["fab_med_width"];

            $sql = " select *
                     from equipo_params
                     where
                     param_equipo_id = {$hasopeninit["win_equipoid"]} and
                     param_medida    >= {$param_medida}
                     order by param_medida asc
                     LIMIT 0,1";
            $equipo_params = $CON->select($sql);
            $equipo_params = $equipo_params[0];

            if((int)$hasopeninit["equipo_prod_isprinter_seri"])
            {
               $corte_m2 = (float)$equipo_params["param_corte"];
               $corte_z  = (int)$equipo_params["param_z"];
            }
            elseif((int)$hasopeninit["equipo_prod_isprinter_flexo"])
            {
               if($agenda["req_solic_devprints_poltype"] == "pol284")
               {
                  $corte_m2 = (float)$equipo_params["param_poly28"];
                  $corte_z  = (int)$equipo_params["param_z"];
               }
               elseif($agenda["req_solic_devprints_poltype"] == "pol170")
               {
                  $corte_m2 = (float)$equipo_params["param_poly17"];
                  $corte_z  = (int)$equipo_params["param_z"];
               }

               //print_r($agenda);
               // echo "<pre>";
               // print_r($equipo_params);
               // echo $agenda["req_solic_devprints_poltype"];
            }
            elseif($_ISSELLADORA)
            {
               $sql = " select t4.*, t5.mant_title, t7.req_number, t1.prd_number, t8.cust_name,
                               t2x.item_number_prod, t2x.item_title, t5x.pause_name, t5x.pause_code,
                               t5.mant_code, t2.ag_equipotype_id, t2.ag_prdid, t1x.item_amount,
                               t6.win_equipoid
                        from prod_header t1
                        INNER JOIN prod_agenda t2           ON t1.id = t2.ag_prdid
                        INNER JOIN prod_worker_ot t3        ON t3.wok_ag_id = t2.id
                        INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
                        LEFT OUTER JOIN equipo_manttype t5  ON t4.evt_equipo_mantid = t5.id
                        INNER JOIN prod_worker_init t6      ON t3.wok_init_id = t6.id
                        INNER JOIN orders t7                ON t2.ag_reqid = t7.id
                        LEFT OUTER JOIN customer t8         ON t7.req_cust_id = t8.id
                        INNER JOIN orders_items t1x         ON t7.id = t1x.req_id
                        INNER JOIN item t2x                 ON t1x.item_id = t2x.id
                        LEFT OUTER JOIN prod_pause_types t5x ON t4.evt_pause_id = t5x.id
                        INNER JOIN equipo t6x               ON t6.win_equipoid = t6x.id
                        where
                        t1.prd_reqid   = {$hasopenot["prd_reqid"]} and
                        t1.prd_status  = 2 and
                        t2.ag_status   > 0 and
                        t3.wok_status  > 0 and
                        t4.evt_status  > 0 and
                        t6.win_status  > 0 and
                        t4.evt_type    = 'prod' and
                        t4.evt_amount  > 0 and
                        (
                           t6x.equipo_prod_isprinter_seri   = 1 or
                           t6x.equipo_prod_isprinter_flexo  = 1
                        )
                        order by t4.evt_crtdat desc
                        LIMIT 0,1";
               $lastprodevent = $CON->select($sql);
               $lastprodevent = $lastprodevent[0];

               $sql = " select *
                        from equipo_params
                        where
                        param_equipo_id = {$lastprodevent["win_equipoid"]} and
                        param_medida    >= {$param_medida}
                        order by param_medida asc
                        LIMIT 0,1";
               $equipo_params = $CON->select($sql);
               $equipo_params = $equipo_params[0];

               if($agenda["fab_printtype"] == "SERI")
               {
                  $corte_m2 = (float)$equipo_params["param_corte"];
                  $corte_z  = (int)$equipo_params["param_z"];
               }
               else
               {
                  if($agenda["req_solic_devprints_poltype"] == "pol284")
                  {
                     $corte_m2 = (float)$equipo_params["param_poly28"];
                     $corte_z  = (int)$equipo_params["param_z"];
                  }
                  elseif($agenda["req_solic_devprints_poltype"] == "pol170")
                  {
                     $corte_m2 = (float)$equipo_params["param_poly17"];
                     $corte_z  = (int)$equipo_params["param_z"];
                  }
               }
            }

            //----------------------------------------------------------------------------------
            if($_ISEMBALAJE)
            {
               $_HIDE_OLD_MAINTABLE = true;
               ?>
               <table border="0" width="100%" cellpadding="0" cellspacing="0">
               <tr>
                  <td align="left" valign="top" width="60%" style="padding-right:10px">
                     <table border="0" width="100%" cellpadding="6" cellspacing="0">
                     <colgroup>
                        <col width="150">
                        <col>
                     </colgroup>
                     <tr>
                        <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="2" align="center">
                           <i class="fa fa-fw fa-info-circle"></i> Información de Fabricación
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">N° O.T.</td>
                        <td class="tdnrm"><?=$agenda["prd_number"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                        <td class="tdnrm"><?=$agenda["cust_name"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">N° C.C.</td>
                        <td class="tdnrm"><?=$agenda["req_number"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Tipo de Material</td>
                        <td class="tdnrm"><?=$agenda["fab_type"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Tipo de Bolsa</td>
                        <td class="tdnrm"><?=$req_infoaddprd_itemtype?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Medidas</td>
                        <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?>x<?=(int)$agenda["fab_med_height"]?>x<?=(int)$agenda["fab_med_fuelle"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Procedencia</td>
                        <td class="tdnrm"><?=$agenda["req_infoaddprd_procedencia"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Tipo de Impresión</td>
                        <td class="tdnrm">
                           <?php
                           if($agenda["fab_printtype"] == "FLEX")
                              echo "Flexografia";
                           elseif($agenda["fab_printtype"] == "SERI")
                              echo "Serigrafia";
                           else
                              echo "Externa";
                           ?>
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Colores</td>
                        <td class="tdnrm"><?=$printcolors_emb?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Color Manillas</td>
                        <td class="tdnrm"><?=$agenda["manilla_color"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Medida Manillas</td>
                        <td class="tdnrm"><?=(int)$agenda["fab_manilla_length"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Reversa</td>
                        <td class="tdnrm"><?=$agenda["req_infoaddprd_reversa_act"]?></td>
                     </tr>
                     </table>
                  </td>
                  <td align="left" valign="top" width="40%" style="padding-left:10px">
                     <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                     <colgroup>
                        <col width="170">
                        <col>
                     </colgroup>
                     <tr>
                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                           <i class="fa fa-fw fa-cube"></i> Datos Embalaje
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Mezcla</td>
                        <td class="tdnrm">
                           <?php
                           if($agenda["reqnum_mezcla_1"] != "" || $agenda["reqnum_mezcla_2"] != "" || $agenda["reqnum_mezcla_3"] != "")
                           {
                              $mstr = "";
                              if($agenda["reqnum_mezcla_1"] != "") $mstr .= "{$agenda["reqnum_mezcla_1"]} / ";
                              if($agenda["reqnum_mezcla_2"] != "") $mstr .= "{$agenda["reqnum_mezcla_2"]} / ";
                              if($agenda["reqnum_mezcla_3"] != "") $mstr .= "{$agenda["reqnum_mezcla_3"]} / ";
                              $mstr = substr($mstr, 0, -2);
                              echo $mstr;
                           }
                           else
                              echo "Sin Mezcla";
                           ?>
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Medida de Caja</td>
                        <td class="tdnrm"><?=$agenda["req_embalaje_medidas_caja"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Logotipo empresa</td>
                        <td class="tdnrm"><?=$agenda["req_caja_impresa"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Cant. Cajas por Pallet</td>
                        <td class="tdnrm"><?=printPrice($agenda["req_embalaje_cajas_por_pallet_amt"])?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Cant. Cajas a Utilizar</td>
                        <td class="tdnrm"><?=printPrice($agenda["req_embalaje_cajas_completas_amt"])?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Unidades por caja</td>
                        <td class="tdnrm"><?=printPrice($agenda["req_embalaje_bolsas_por_caja_amt"])?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Unidades caja final</td>
                        <td class="tdnrm"><?=printPrice($agenda["req_embalaje_caja_final"])?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Total de Pedido</td>
                        <td class="tdnrm">
                           <b>
                           <?php
                           $totalpedido_amt = ($agenda["req_embalaje_cajas_completas_amt"] * $agenda["req_embalaje_bolsas_por_caja_amt"]) + $agenda["req_embalaje_caja_final"];
                           echo printPrice($totalpedido_amt);
                           ?>
                           </b>
                        </td>
                     </tr>
                     </table>
                  </td>
               </tr>
               <tr>
                  <td colspan="2">
                     <div style="height:15px"></div>
                     <?php
                     $_ADJFILELIST = Array();
                     if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
                     {
                        unset($newfile);
                        $newfile["NAME"] = "Imagen Diseño";
                        $newfile["LINK"] = "/docs.order/{$agenda["fab_design_imagehash"]}";
                        $_ADJFILELIST[] = $newfile;
                     }
                     for($x = 0; $x < 5; $x++)
                     {
                        if($agenda["req_prod_adjfile_{$x}"] != "")
                        {
                           unset($newfile);
                           if($x == 0) $newfile["NAME"] = "Cliché";
                           if($x == 1) $newfile["NAME"] = "Cliché";
                           if($x == 2) $newfile["NAME"] = "Película";
                           if($x == 3) $newfile["NAME"] = "Película";
                           if($x == 4) $newfile["NAME"] = "Montaje";

                           $newfile["LINK"] = "/docs.prod/{$agenda["req_prod_adjfile_{$x}"]}";
                           $_ADJFILELIST[] = $newfile;
                        }
                     }
                     for($x = 0; $x < 5; $x++)
                     {
                        if($agenda["req_solic_supp_file_{$x}"] != "")
                        {
                           $dlname  = $agenda["req_solic_supp_file_{$x}"];
                           if($agenda["req_solic_supp_name_{$x}"] != "")
                              $dlname = $agenda["req_solic_supp_name_{$x}"];

                           $xkey = md5($agenda["req_solic_supp_file_{$x}"]."_5gfffd".$dlname);
                           $pdflink = "/getprodfile.php?xkey={$xkey}&hash={$agenda["req_solic_supp_file_{$x}"]}&name={$dlname}&type=req_solic_supp_file";

                           unset($newfile);
                           $newfile["NAME"] = "Adjunto: <font style='font-weight:normal'>{$dlname}</font>";
                           $newfile["LINK"] = $pdflink;
                           $_ADJFILELIST[] = $newfile;
                        }
                     }

                     if(count($_ADJFILELIST) > 0)
                     {  ?>
                        <table border="0" width="100%" cellpadding="3" cellspacing="0">
                        <colgroup>
                           <col>
                           <col width="160">

                        </colgroup>
                        <tr>
                           <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                              <i class="fa fa-fw fa-file"></i> Adjuntos
                           </td>
                        </tr>
                        <?php
                        foreach($_ADJFILELIST AS $_ADJFILE)
                        {  ?>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;"><?=$_ADJFILE["NAME"]?></td>
                              <td class="tdnrm">
                                 <div class="btngrey" type="button" style="padding:5px;font-size:12px" onclick="window.open('<?=$_ADJFILE["LINK"]?>');">
                                    <i class="fa fa-fw fa-download" style="color:white;"></i> Descargar
                                 </div>
                              </td>
                           </tr>
                           <?php
                        }
                        ?>
                        </table>
                        <?php
                     }
                     ?>
                  </td>
               </tr>
               </table>
               <?php
               // print_r($agenda);
            }
            //----------------------------------------------------------------------------------
            elseif($_ISREBOBINADORA || $_ISSELLADORA)
            {
               $_HIDE_OLD_MAINTABLE = true;

               //----------------------------------------------------------------------------------
               // CALC CORTE
               //----------------------------------------------------------------------------------
               $_CORTE    = 0;
               $_MEDIDAS  = sprintf("%02d", $agenda["fab_med_width"])."X";
               $_MEDIDAS .= sprintf("%02d", $agenda["fab_med_height"])."X";
               $_MEDIDAS .= sprintf("%02d", $agenda["fab_med_fuelle"]);

               $part1     = substr($_MEDIDAS, 0, 2);
               $part1_num = (float)$part1;

               if(strpos(strtoupper($req_infoaddprd_itemtype), "BOUTIQUE") !== false)
               {
                   $part2     = substr($_MEDIDAS, 6, 2);
                   $part2_num = (float)$part2;
                   $_CORTE    = $part1_num + $part2_num + 1.5;
               }
               else
                  $_CORTE = $part1_num;

               //----------------------------------------------------------------------------------
               // CALC CANTIDAD BOBINAS
               //----------------------------------------------------------------------------------
               $_MATERIALIDAD    = $agenda["fab_type"];
               $_CANTPROGRAMADA  = $agenda["ag_amount"];
               $_GRAMAJE         = (int)$agenda["fab_mat_gramms"];
               $_CANT_BOBINAS    = 0;
               if($_MATERIALIDAD == "PP")
                  $_CANT_BOBINAS = ($_CORTE / 100) * $_CANTPROGRAMADA / 1100;
               else
               {
                  if($_GRAMAJE == 70)
                     $_CANT_BOBINAS = ($_CORTE / 100) * $_CANTPROGRAMADA / 1950;
                  elseif($_GRAMAJE == 55)
                     $_CANT_BOBINAS = ($_CORTE / 100) * $_CANTPROGRAMADA / 2100;
                  elseif ($_GRAMAJE == 80)
                     $_CANT_BOBINAS = ($_CORTE / 100) * $_CANTPROGRAMADA / 1700;
                  else
                     $_CANT_BOBINAS = ($_CORTE / 100) * $_CANTPROGRAMADA / 1400;
               }

               //----------------------------------------------------------------------------------
               // CALC CANTIDAD MANILLAS
               //----------------------------------------------------------------------------------
               $_LARGO_MANILLAS  = (int)$agenda["fab_manilla_length"];
               $_CANT_MANILLAS   = ($_LARGO_MANILLAS / 100) * $_CANTPROGRAMADA * 2 / 1200;
               ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="160">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="2" align="center">
                     <i class="fa fa-fw fa-info-circle"></i> Información de Fabricación
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° O.T.</td>
                  <td class="tdnrm"><?=$agenda["prd_number"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                  <td class="tdnrm"><?=$agenda["cust_name"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° C.C.</td>
                  <td class="tdnrm"><?=$agenda["req_number"]?></td>
               </tr>
               </table>
               <table border="0" width="100%" cellpadding="0" cellspacing="0">
               <tr>
                  <td align="left" valign="top" width="50%" style="padding-right:10px">
                     <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                     <colgroup>
                        <col width="160">
                        <col>
                     </colgroup>
                     <tr>
                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                           <i class="fa fa-fw fa-wrench"></i> Configuración
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                        <td class="tdnrm"><?=$agenda["item_title"]?></td>
                     </tr>
                     <?php
                     if($_ISSELLADORA)
                     {  ?>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Corte de Bolsa</td>
                           <td class="tdnrm"><?=printPrice($corte_m2,4)?> mtrs</td>
                        </tr>
                        <?php
                     }
                     if(!(int)$agenda["req_rebo_cc_genrefid"])
                     {  ?>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                           <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Alto (Tiro)</td>
                           <td class="tdnrm"><?=$req_infoaddprd_alto_tiro?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Alto (Retiro)</td>
                           <td class="tdnrm"><?=$req_infoaddprd_alto_retiro?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Doblez superior</td>
                           <td class="tdnrm"><?=$req_infoaddprd_doblez_superior?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                           <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Dado de Manillas</td>
                           <td class="tdnrm"><?=$agenda["req_infoaddprd_dado_manillas"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Cabezal</td>
                           <td class="tdnrm"><?=$agenda["req_infoaddprd_cabezal_act"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Alarma</td>
                           <td class="tdnrm">
                              <?php
                              if(strpos(strtoupper($agenda["dispositivo"]), "ALARMA") !== false)
                                 echo "Si";
                              else
                                 echo "No";
                              ?>
                           </td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                           <td class="tdnrm"><?=$agenda["item_sellprice_barcodenumber"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Etiqueta adhesiva</td>
                           <td class="tdnrm">
                              <?php
                              if(strpos(strtoupper($agenda["dispositivo"]), "ETIQUETA") !== false)
                                 echo "Si";
                              else
                                 echo "No";
                              ?>
                           </td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                           <td class="tdnrm">
                              <?php
                              if($agenda["item_sellprice_barcodenumber"] != "")
                                 echo "Si";
                              else
                                 echo "No";
                              ?>
                           </td>
                        </tr>
                        <?php
                     }
                     ?>
                     </table>
                  </td>
                  <td align="left" valign="top" width="50%" style="padding-left:10px">
                     <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                     <colgroup>
                        <col width="160">
                        <col>
                     </colgroup>
                     <tr>
                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                           <i class="fa fa-fw fa-cubes"></i> Insumos
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                           Caracteristicas de tela
                        </td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                        <td class="tdnrm"><?=$agenda["fab_type"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Color Tela</td>
                        <td class="tdnrm"><?=$agenda["fabric_color"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Ancho Tela</td>
                        <td class="tdnrm"><?=$agenda["req_operador_tela_width"]?></td>
                     </tr>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                        <td class="tdnrm"><?=$agenda["fab_mat_gramms"]?> gr</td>
                     </tr>
                     <?php
                     if(!(int)$agenda["req_rebo_cc_genrefid"])
                     {  ?>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Cant. Bobinas</td>
                           <td class="tdnrm"><?=printPrice($_CANT_BOBINAS,2)?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                              Caracteristicas de manillas
                           </td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Color Manilla</td>
                           <td class="tdnrm"><?=$agenda["manilla_color"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Ancho de Manillas</td>
                           <td class="tdnrm"><?=$req_infoaddprd_ancho_manillas?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Largo de Manillas</td>
                           <td class="tdnrm"><?=(int)$agenda["fab_manilla_length"]?></td>
                        </tr>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE;">Cant. Manillas</td>
                           <td class="tdnrm"><?=printPrice($_CANT_MANILLAS,2)?></td>
                        </tr>
                        <?php
                     }
                     ?>
                     </table>
                  </td>
               </tr>
               </table>
               <div style="height:6px"></div>
               <?php
               if($_ISREBOBINADORA)
               {
                  $sql = " select t1.*
                           from orders t1
                           where
                           t1.id = {$hasopenot["prd_reqid"]}";
                  $reborder = $CON->select($sql);
                  $reborder = $reborder[0];


                  $sql = " select *
                           from orders_classify_rebo_values
                           where
                           req_id = {$hasopenot["prd_reqid"]}
                           order by id";
                  $rebovals = $CON->select($sql);
                  foreach($rebovals AS $reboval)
                  {
                     $_REBOVALS[$reboval["req_rebo_type"]][$reboval["id"]] = $reboval;
                  }

                  if($reborder["req_rebo_type"] != "")
                  {  ?>
                     <table border="0" width="100%" cellpadding="0" cellspacing="0">
                     <tr>
                        <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                           <i class="fa fa-fw fa-wrench"></i> Tarea Reobinadora
                        </td>
                     </tr>
                     <tr>
                        <td align="left" valign="top" width="50%" style="padding-right:10px">
                           <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                           <colgroup>
                              <col width="160">
                              <col>
                           </colgroup>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">Tarea</td>
                              <td class="tdnrm"><?=$reborder["req_rebo_type"]?></td>
                           </tr>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">Tipo</td>
                              <td class="tdnrm"><?=$reborder["req_rebo_rolloscc_opttype"]?></td>
                           </tr>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">Rollos</td>
                              <td class="tdnrm">
                                 <?php
                                 if($reborder["req_rebo_rolloscc_opt"] == "100cm_manillas")
                                    echo "Rollos de 100cm para manillas";
                                 elseif($reborder["req_rebo_rolloscc_opt"] == "100cm_algunos_metros")
                                    echo "Rollos de 100cm (algunos metros) a rollo de 76cm más manillas y restante debe mantener código";
                                 elseif($reborder["req_rebo_rolloscc_opt"] == "solo_rebobinar")
                                    echo "Rollos solo rebobinar";
                                 else
                                    echo "- - -";
                                 ?>
                              </td>
                           </tr>
                           </table>
                        </td>
                        <td align="left" valign="top" width="50%" style="padding-left:10px">
                           <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                           <colgroup>
                              <col width="160">
                              <col>
                           </colgroup>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">Estado</td>
                              <td class="tdnrm"><?=$reborder["req_rebo_state"]?></td>
                           </tr>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de cortes</td>
                              <td class="tdnrm"><?if((float)$reborder["req_rebo_cortescc"]) echo printPrice($reborder["req_rebo_cortescc"]); else echo "- - -"?></td>
                           </tr>
                           <tr>
                              <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                              <td class="tdnrm">&nbsp;</td>
                           </tr>
                           </table>
                        </td>
                     </tr>
                     </table>
                     <?php
                     if($reborder["req_rebo_rolloscc_opttype"] != "" && $reborder["req_rebo_rolloscc_opttype"] != "Por rollo")
                     {  ?>
                        <div style="height:6px"></div>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                           <td align="left" valign="top" width="50%" style="padding-right:10px">
                              <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                              <colgroup>
                                 <col width="160">
                                 <col>
                                 <col>
                              </colgroup>
                              <tr>
                                 <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                    <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                 </td>
                              </tr>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                              </tr>
                              <?php
                              $rebovals = array_values($_REBOVALS["metro"]);
                              $xtotal   = 0;
                              for($x = 0; $x < count($rebovals); $x++)
                              {
                                 $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
                                 ?>
                                 <tr>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?></td>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?></td>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?></td>
                                 </tr>
                                 <?php
                                 $xtotal += $subtotal;
                              }
                              ?>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;"><?=printPrice($xtotal, 2, true)?></td>
                              </tr>
                              </table>
                           </td>
                           <td align="left" valign="top" width="50%" style="padding-left:10px">
                              <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                              <tr>
                                 <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                    <i class="fa fa-fw fa-info"></i> Largos
                                 </td>
                              </tr>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Largo</td>
                              </tr>
                              <?php
                              $rebovals = array_values($_REBOVALS["metro_amt"]);
                              $xtotal2   = 0;
                              for($x = 0; $x < count($rebovals); $x++)
                              {  ?>
                                 <tr>
                                    <td class="tdnrm"><?if($x == 0) echo $agenda["fab_type"]?></td>
                                    <td class="tdnrm"><?if($x == 0) echo $agenda["fabric_color"]?></td>
                                    <td class="tdnrm"><?if($x == 0) echo printPrice($agenda["fab_mat_gramms"])." gr"?></td>
                                    <td class="tdnrm"><?if($x == 0) echo $reborder["req_operador_tela_width"]?></td>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?></td>
                                 </tr>
                                 <?php
                                 $xtotal2 += $rebovals[$x]["rollo_amt"];
                              }
                              ?>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;"><?=printPrice($xtotal2, 0, true)?></td>
                              </tr>
                              </table>
                           </td>
                        </tr>
                        </table>
                        <div style="height:6px"></div>
                        <?php
                     }
                     if($reborder["req_rebo_rolloscc_opttype"] != "" && $reborder["req_rebo_rolloscc_opttype"] != "Por metro")
                     {  ?>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                           <td align="left" valign="top" width="50%" style="padding-right:10px">
                              <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                              <colgroup>
                                 <col width="160">
                                 <col>
                                 <col>
                              </colgroup>
                              <tr>
                                 <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                    <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                 </td>
                              </tr>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                              </tr>
                              <?php
                              $rebovals = array_values($_REBOVALS["rollo"]);
                              $xtotal   = 0;
                              for($x = 0; $x < count($rebovals); $x++)
                              {
                                 $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
                                 ?>
                                 <tr>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?></td>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?></td>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?></td>
                                 </tr>
                                 <?php
                                 $xtotal += $subtotal;
                              }
                              ?>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;"><?=printPrice($xtotal, 2, true)?></td>
                              </tr>
                              </table>
                           </td>
                           <td align="left" valign="top" width="50%" style="padding-left:10px">
                              <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                              <tr>
                                 <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                    <i class="fa fa-fw fa-info"></i> Rollos
                                 </td>
                              </tr>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de rollos</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                              </tr>
                              <?php
                              $rebovals = array_values($_REBOVALS["rollo_amt"]);
                              $xtotal2   = 0;
                              for($x = 0; $x < count($rebovals); $x++)
                              {  ?>
                                 <tr>
                                    <td class="tdnrm"><?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?></td>
                                    <td class="tdnrm"><?if($x == 0) echo $agenda["fab_type"]?></td>
                                    <td class="tdnrm"><?if($x == 0) echo $agenda["fabric_color"]?></td>
                                    <td class="tdnrm"><?if($x == 0) echo printPrice($agenda["fab_mat_gramms"])." gr"?></td>
                                    <td class="tdnrm"><?if($x == 0) echo $reborder["req_operador_tela_width"]?></td>
                                 </tr>
                                 <?php
                                 $xtotal2 += $rebovals[$x]["rollo_amt"];
                              }
                              ?>
                              <tr>
                                 <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                 <td class="tdleft" style="background-color:#EEEEEE;"><?=printPrice($xtotal2, 0, true)?></td>
                              </tr>
                              </table>
                           </td>
                        </tr>
                        </table>
                        <div style="height:6px"></div>
                        <?php
                     }
                  }
                  // echo "<pre>";
                  // print_r($reborder);
               }

               if($_REQUEST["mode"] == "reboprod" || (int)$agenda["req_rebo_cc_genrefid"])
               {  ?>
                  <div style="display:none">
                  <?php
               }
               ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-calendar"></i> Fechas de entrega
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad programada</td>
                  <td class="tdnrm" style="width:150px"><?=printPrice($agenda["ag_amount"])?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">&nbsp;</td>
                  <td class="tdnrm" style="width:150px">&nbsp;</td>
               </tr>
               <?php
               $hasentregasplan = false;
               foreach($entregas AS $entrega)
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad de Bolsas</td>
                     <td class="tdnrm" style="width:150px"><?=printPrice($entrega["prodplan_amt"])?></td>
                     <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Fechas de Entrega</td>
                     <td class="tdnrm" style="width:150px"><?=date("d.m.Y", $entrega["prodplan_date"])?></td>
                  </tr>
                  <?php
                  $hasentregasplan = true;
               }
               if(!$hasentregasplan)
               {  ?>
                  <tr>
                     <td class="tdleft" align="center" colspan="4"><b class=msg_save_err>No hay fechas de entrega registradas</b></td>
                  </tr>
                  <?php
               }
               ?>
               </table>
               <?php
               if($_REQUEST["mode"] == "reboprod" || (int)$agenda["req_rebo_cc_genrefid"])
               {  ?>
                  </div>
                  <?php
               }
               $_ADJFILELIST = Array();
               if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
               {
                  unset($newfile);
                  $newfile["NAME"] = "Imagen Diseño";
                  $newfile["LINK"] = "/docs.order/{$agenda["fab_design_imagehash"]}";
                  $_ADJFILELIST[] = $newfile;
               }
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {
                     unset($newfile);
                     if($x == 0) $newfile["NAME"] = "Cliché";
                     if($x == 1) $newfile["NAME"] = "Cliché";
                     if($x == 2) $newfile["NAME"] = "Película";
                     if($x == 3) $newfile["NAME"] = "Película";
                     if($x == 4) $newfile["NAME"] = "Montaje";

                     $newfile["LINK"] = "/docs.prod/{$agenda["req_prod_adjfile_{$x}"]}";
                     $_ADJFILELIST[] = $newfile;
                  }
               }
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_solic_supp_file_{$x}"] != "")
                  {
                     $dlname  = $agenda["req_solic_supp_file_{$x}"];
                     if($agenda["req_solic_supp_name_{$x}"] != "")
                        $dlname = $agenda["req_solic_supp_name_{$x}"];

                     $xkey = md5($agenda["req_solic_supp_file_{$x}"]."_5gfffd".$dlname);
                     $pdflink = "/getprodfile.php?xkey={$xkey}&hash={$agenda["req_solic_supp_file_{$x}"]}&name={$dlname}&type=req_solic_supp_file";

                     unset($newfile);
                     $newfile["NAME"] = "Adjunto: <font style='font-weight:normal'>{$dlname}</font>";
                     $newfile["LINK"] = $pdflink;
                     $_ADJFILELIST[] = $newfile;
                  }
               }

               if(count($_ADJFILELIST) > 0)
               {  ?>
                  <table border="0" width="100%" cellpadding="3" cellspacing="0">
                  <colgroup>
                     <col>
                     <col width="160">

                  </colgroup>
                  <tr>
                     <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                        <i class="fa fa-fw fa-file"></i> Adjuntos
                     </td>
                  </tr>
                  <?php
                  foreach($_ADJFILELIST AS $_ADJFILE)
                  {  ?>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;"><?=$_ADJFILE["NAME"]?></td>
                        <td class="tdnrm">
                           <div class="btngrey" type="button" style="padding:5px;font-size:12px" onclick="window.open('<?=$_ADJFILE["LINK"]?>');">
                              <i class="fa fa-fw fa-download" style="color:white;"></i> Descargar
                           </div>
                        </td>
                     </tr>
                     <?php
                  }
                  ?>
                  </table>
                  <?php
               }

               if(!$_ISSELLADORA && (int)$agenda["req_rebo_cc_genrefid"] == 0)
               {  ?>
                  <div style="height:20px"></div>
                  <table border="0" width="100%" cellpadding="3" cellspacing="0">
                  <colgroup>
                     <col>
                     <col width="160">
                  </colgroup>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE;border-top:1px solid #DDDDDD;">Bobinas Impresas</td>
                     <td class="tdnrm" style="border-top:1px solid #DDDDDD;">
                        <div class="btngrey" type="button" style="padding:5px;font-size:12px;"
                        onclick="location.href = '/prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=btestock';">
                           <i class="fa fa-fw fa-info-circle" style="color:white;"></i> Ver stock actual
                        </div>
                     </td>
                  </tr>
                  </table>
                  <?php
               }

            }
            elseif((int)$hasopeninit["equipo_prod_isprinter_flexo"])
            {
               $aniloxdescarr = Array();
               foreach($detalle_anilox AS $detalle_aniloxrow)
               {
                  if((int)$detalle_aniloxrow["paow_anilox"])
                  {
                     foreach($item_anilox AS $ia)
                     {
                        if($ia["id"] == $detalle_aniloxrow["paow_anilox"])
                           $aniloxdescarr[$detalle_aniloxrow["paow_posicion"]] = $ia["item_number_prod"]."-".$ia["item_title"];
                     }
                  }
               }

               $_HIDE_OLD_MAINTABLE = true;
               ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="220">
                  <col width="30%">
                  <col width="160">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-user"></i> Información Cliente
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° OT</td>
                  <td class="tdnrm"><?=$agenda["prd_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 1</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_1"] || (int)$agenda["fab_print_colors_back_1"])
                        echo $agenda["fab_print_colordesc_1"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                  <td class="tdnrm"><?=$agenda["cust_name"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 2</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_2"] || (int)$agenda["fab_print_colors_back_2"])
                        echo $agenda["fab_print_colordesc_2"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° CC</td>
                  <td class="tdnrm"><?=$agenda["req_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 3</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_3"] || (int)$agenda["fab_print_colors_back_3"])
                        echo $agenda["fab_print_colordesc_3"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Diseño</td>
                  <td class="tdnrm"><?=$agenda["fab_design_name"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 4</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_4"] || (int)$agenda["fab_print_colors_back_4"])
                        echo $agenda["fab_print_colordesc_4"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                  <td class="tdnrm"><?=$agenda["item_number_prod"]?> | <?=$agenda["item_title"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 5</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_5"] || (int)$agenda["fab_print_colors_back_5"])
                        echo $agenda["fab_print_colordesc_5"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                  <td class="tdnrm"><?=$agenda["fab_type"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 6</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_6"] || (int)$agenda["fab_print_colors_back_6"])
                        echo $agenda["fab_print_colordesc_6"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                  <td class="tdnrm"><?=(int)$_TRANSCOMINFOS[31]["VALUE"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Alto</td>
                  <td class="tdnrm"><?=(int)$_TRANSCOMINFOS[39]["VALUE"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de Bolsas</td>
                  <td class="tdnrm"><?=printPrice($agenda["ag_amount"])?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Fecha de Entrega</td>
                  <td class="tdnrm"><?=$agenda["req_despacho_desc"]?></td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;" colspan="2">Fecha de solicitud de cliché</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_solic_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_solic_dat"]);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Fecha de recepción de cliché</td>
                  <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_recep_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_recep_dat"]);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen Diseño</td>
                  <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
                     {  ?>
                        <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                        onclick="showColorbox('/docs.order/<?=$agenda["fab_design_imagehash"]?>', 'image', '99%', '99%', 'auto')">
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                        </div>
                        <?php
                     }
                     ?>
                  </td>
               </tr>
               <?php
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {  ?>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2">
                           <?php
                           $ftype = strtoupper(substr($agenda["req_prod_adjfile_{$x}"], strrpos($agenda["req_prod_adjfile_{$x}"], ".")+1));
                           if($ftype == "PNG" || $ftype == "JPG" || $ftype == "JPEG" || $ftype == "BMP" ||$ftype == "GIF")
                           {  ?>
                              <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                              onclick="showColorbox('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>', 'image', '99%', '99%', 'auto')">
                                 <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                              </div>
                              <?php
                           }
                           else
                           {  ?>
                              <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                              onclick="window.open('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>')">
                                 <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar documento&nbsp;
                              </div>
                              <?php
                           }
                           ?>
                        </td>
                     </tr>
                     <?php
                     $_HAS_FILES = true;
                  }
               }
               ?>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-info-circle"></i> Información de Fabricación
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Rodillo a utilizar</td>
                  <td class="tdnrm">Z = <?=printPrice($corte_z,0);?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 1</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_1"] || (int)$agenda["fab_print_colors_back_1"])
                        echo $agenda["fab_print_colordesc_1"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Corte de bolsa</td>
                  <td class="tdnrm"><?=printPrice($corte_m2,4)?> mtrs</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 2</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_2"] || (int)$agenda["fab_print_colors_back_2"])
                        echo $agenda["fab_print_colordesc_2"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ubicación Clisé</td>
                  <td class="tdnrm"><?=$agenda["req_infoaddprd_cliche_ubicacion"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 3</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_3"] || (int)$agenda["fab_print_colors_back_3"])
                        echo $agenda["fab_print_colordesc_3"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Codigo Cliché</td>
                  <td class="tdnrm"><?=$agenda["req_infoaddprd_cliche_codigo"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 4</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_4"] || (int)$agenda["fab_print_colors_back_4"])
                        echo $agenda["fab_print_colordesc_4"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Pie de Imprenta</td>
                  <td class="tdnrm"><?=$agenda["req_pie_imprenta"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 5</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_5"] || (int)$agenda["fab_print_colors_back_5"])
                        echo $agenda["fab_print_colordesc_5"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° Código de Barra</td>
                  <td class="tdnrm"><?=$agenda["item_sellprice_barcodenumber"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 6</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_6"] || (int)$agenda["fab_print_colors_back_6"])
                        echo $agenda["fab_print_colordesc_6"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Impresiones al eje</td>
                  <td class="tdnrm">Sin información</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 1</td>
                  <td class="tdnrm"><?=$aniloxdescarr[1]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Impresiones por desarrollo</td>
                  <td class="tdnrm"><?=(int)$agenda["req_solic_devprints_cc"]?> impresiones</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 2</td>
                  <td class="tdnrm"><?=$aniloxdescarr[2]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">% de merma</td>
                  <td class="tdnrm"><?=printPrice($agenda["req_operador_mermaperc"],2)?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 3</td>
                  <td class="tdnrm"><?=$aniloxdescarr[3]?></td>
               </tr>
               <?php
               $_THIS_STATS   = getProdStats($CON, 0, $agenda["id"], 0, 0, 0, $agenda["ag_equipotype_id"]);
               $_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);
               $thisprodperc  = $_THIS_STATS["_PROD_AMOUNT"] / $agenda["ag_amount"] * 100;
               $totlprodperc  = $_OT_STATS["_PROD_AMOUNT"] / $agenda["item_amount"] * 100;
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Bolsas producidas / en curso</td>
                  <td class="tdnrm"><?=printPrice($_THIS_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["ag_amount"])?> (<?=printPrice($thisprodperc,2)?> %)</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 4</td>
                  <td class="tdnrm"><?=$aniloxdescarr[4]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Bolsas producidas / total</td>
                  <td class="tdnrm"><?=printPrice($_OT_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["item_amount"])?> (<?=printPrice($totlprodperc,2)?> %)</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 5</td>
                  <td class="tdnrm"><?=$aniloxdescarr[5]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Saldo total</td>
                  <td class="tdnrm"><?=printPrice($agenda["item_amount"] - $_OT_STATS["_PROD_AMOUNT"])?> unidades</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Unidad N° 6</td>
                  <td class="tdnrm"><?=$aniloxdescarr[6]?></td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <?php
               $mlin_prog = round($agenda["ag_amount"] * $corte_m2);
               $mlin_addi = round($mlin_prog / 100 * (float)$agenda["req_operador_mermaperc"]);

               $_METROS_A_IMPRIMIR = round($mlin_prog + $mlin_addi);
               $_KG_A_IMPRIMIR     = round($_METROS_A_IMPRIMIR * $agenda["req_operador_tela_width"] / 100 * $agenda["fab_mat_gramms"] / 1000);;
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Materialidad</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=$agenda["fab_type"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Metros a imprimir</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=printPrice($_METROS_A_IMPRIMIR,2)?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color Tela</td>
                  <td class="tdnrm"><?=$agenda["fabric_color"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Contador impresora</td>
                  <td class="tdnrm"><?=printPrice(round($_METROS_A_IMPRIMIR / 0.41))?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ancho Tela</td>
                  <td class="tdnrm"><?=$agenda["req_operador_tela_width"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Kg a imprimir</td>
                  <td class="tdnrm"><?=printPrice($_KG_A_IMPRIMIR,2)?> kgs</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                  <td class="tdnrm"><?=$agenda["fab_mat_gramms"]?> gr&nbsp;</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               </table>
               <?php
            }
            elseif((int)$hasopeninit["equipo_prod_isprinter_seri"] && $_ISSERIPULPO)
            {
               $_HIDE_OLD_MAINTABLE = true;
               ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="250">
                  <col width="30%">
                  <col width="160">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-user"></i> Información Cliente
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° OT</td>
                  <td class="tdnrm"><?=$agenda["prd_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                  <td class="tdnrm"><?=$agenda["cust_name"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Alto</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_height"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° CC</td>
                  <td class="tdnrm"><?=$agenda["req_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                  <td class="tdnrm"><?=$agenda["item_number_prod"]?> | <?=$agenda["item_title"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de Bolsas</td>
                  <td class="tdnrm"><?=printPrice($agenda["ag_amount"])?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                  <td class="tdnrm"><?=$agenda["fab_type"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <?php
               $amt_colors_frente   = 0;
               $amt_colors_dorso    = 0;
               for($xx = 1; $xx <= 10; $xx++)
               {
                  if((int)$agenda["fab_print_colors_front_{$xx}"])
                     $amt_colors_frente++;
                  if((int)$agenda["fab_print_colors_back_{$xx}"])
                     $amt_colors_dorso++;
               }
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Cant. Colores Frente</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=printPrice($amt_colors_frente)?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Cant. Colores Dorso</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=printPrice($amt_colors_dorso)?></td>
               </tr>
               <?php
               //----------------------------------------------------------------------------------
               for($xx = 1; $xx <= 10; $xx++)
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE;">Color N° <?=$xx?> </td>
                     <td class="tdnrm">
                        <?php
                        if((int)$agenda["fab_print_colors_front_{$xx}"])
                           echo $agenda["fab_print_colordesc_{$xx}"];
                        ?>
                     </td>
                     <td class="tdleft" style="background-color:#EEEEEE;">Color N° <?=$xx?> </td>
                     <td class="tdnrm">
                        <?php
                        if((int)$agenda["fab_print_colors_back_{$xx}"])
                           echo $agenda["fab_print_colordesc_{$xx}"];
                        ?>
                     </td>
                  </tr>
                  <?php
               }
               ?>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;" colspan="2">Fecha de Entrega</td>
                  <td class="tdnrm" style=";border-top: 1px solid #DDDDDD;" colspan="2"><?=$agenda["req_despacho_desc"]?></td>
               </tr>
               <tr>
                   <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Fecha de solicitud de película</td>
                   <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_solic_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_solic_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <tr>
                   <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Fecha de recepción de película</td>
                   <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_recep_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_recep_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen Diseño</td>
                  <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
                     {  ?>
                        <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                        onclick="showColorbox('/docs.order/<?=$agenda["fab_design_imagehash"]?>', 'image', '99%', '99%', 'auto')">
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                        </div>
                        <?php
                     }
                     ?>
                  </td>
               </tr>
               <?php
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {  ?>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2">
                           <?php
                           $ftype = strtoupper(substr($agenda["req_prod_adjfile_{$x}"], strrpos($agenda["req_prod_adjfile_{$x}"], ".")+1));
                           if($ftype == "PNG" || $ftype == "JPG" || $ftype == "JPEG" || $ftype == "BMP" ||$ftype == "GIF")
                           {  ?>
                              <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                              onclick="showColorbox('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>', 'image', '99%', '99%', 'auto')">
                                 <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                              </div>
                              <?php
                           }
                           else
                           {  ?>
                              <div class="btngrey" style="padding:0px;padding-top:4px;padding-bottom:4px"
                              onclick="window.open('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>')">
                                 <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar documento&nbsp;
                              </div>
                              <?php
                           }
                           ?>
                        </td>
                     </tr>
                     <?php
                     $_HAS_FILES = true;
                  }
               }
               ?>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">% de merma</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;" colspan="3"><?=printPrice($agenda["req_operador_mermaperc"],2)?></td>
               </tr>
               <?php
               $_THIS_STATS   = getProdStats($CON, 0, $agenda["id"], 0, 0, 0, $agenda["ag_equipotype_id"]);
               $_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);
               $thisprodperc  = $_THIS_STATS["_PROD_AMOUNT"] / $agenda["ag_amount"] * 100;
               $totlprodperc  = $_OT_STATS["_PROD_AMOUNT"] / $agenda["item_amount"] * 100;
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Bolsas producidas / en curso</td>
                  <td class="tdnrm" colspan="3"><?=printPrice($_THIS_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["ag_amount"])?> (<?=printPrice($thisprodperc,2)?> %)</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Bolsas producidas / total</td>
                  <td class="tdnrm" colspan="3"><?=printPrice($_OT_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["item_amount"])?> (<?=printPrice($totlprodperc,2)?> %)</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Saldo total</td>
                  <td class="tdnrm" colspan="3"><?=printPrice($agenda["item_amount"] - $_OT_STATS["_PROD_AMOUNT"])?> unidades</td>
               </tr>
               <?php
               if($amt_colors_frente)
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE">Pasadas frente</td>
                     <td class="tdnrm" colspan="3"><?=printPrice($_THIS_STATS["_PROD_PULPO_FRENTE_PASADAS"])?></td>
                  </tr>
                  <?php
               }
               if($amt_colors_dorso)
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE">Pasadas dorso</td>
                     <td class="tdnrm" colspan="3"><?=printPrice($_THIS_STATS["_PROD_PULPO_DORSO_PASADAS"])?></td>
                  </tr>
                  <?php
               }
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Pasadas totales a imprimir</td>
                  <td class="tdnrm" style="background-color:#EEEEEE" colspan="3">
                     <b>
                     <?php
                     $pasadas_print = round($agenda["ag_amount"] / $agenda["req_operador_bastidoramt"]);
                     echo printPrice($pasadas_print);
                     ?>
                     </b>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Imágenes impresas en bastidor</td>
                  <td class="tdnrm" colspan="3">
                     <select class="inptxt" style="background-color:#FFFFFF;width:120px;padding:2px;float:left"
                     name="idx_bastidor_amt" id="idx_bastidor_amt">
                        <option value="">Seleccione</option>
                        <?php
                        for($zzz = 1; $zzz <= 5; $zzz++)
                        {  ?>
                           <option value="<?=$zzz?>" <?if($agenda["req_operador_bastidoramt"] == $zzz) echo "selected"?>><?=$zzz?></option>
                           <?php
                        }
                        ?>
                     </select>
                     <input type="button" class="btngreen" value="Guardar" style="padding:5px;border:0px;font-size:12px;margin-left:10px"
                     onclick="setSpecParams()">
                  </td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <?php
               $sql = " select *
                        from prod_worker_ot_events
                        where
                        evt_prod_worker_otid = {$hasopenot["id"]} and
                        evt_type = 'apertura' and
                        evt_status = 1";
               $aperturarefevent = $CON->select($sql);
               $aperturarefevent = $aperturarefevent[0];

               $sql = " select id, concat(wrk_firstname,' ',wrk_lastname) as ayudantes
                        FROM workers
                        where
                        id = {$aperturarefevent["evt_idayudante"]}";
               $aperturaayudante = $CON->select($sql);
               $aperturaayudante = $aperturaayudante[0];
               ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Tipo</td>
                  <td class="tdnrm" colspan="3"><?=$hasopeninit["type_ant_title"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Máquina</td>
                  <td class="tdnrm" colspan="3"><?=$hasopeninit["equipo_name"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ayudante</td>
                  <td class="tdnrm" colspan="3"><?=$aperturaayudante["ayudantes"]?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Comentarios</td>
                  <td class="tdnrm" colspan="3"><?=$aperturarefevent["evt_comments"]?></td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr id="idx_v2pulpo_tr_DISABLED">
                  <td class="tdleft" style="background-color:#EEEEEE;">Lado a imprimir</td>
                  <td class="tdnrm" colspan="3">
                     <select class="inptxt" style="background-color:#FFFFFF;width:120px;padding:2px;float:left"
                     name="setcolormode" id="setcolormode"
                     onchange="var xurl = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&setcolormode=' +this.value; if(this.value != '') xurl = xurl +'#idx_v2pulpo_tr'; location.href = xurl;">
                        <option value="">Seleccione</option>
                        <?php
                        if((int)$amt_colors_frente > 0)
                        {  ?>
                           <option value="Frente" <?if($_SESSION["_PROD_setcolormode"] == "Frente") echo "selected"?>>Frente</option>
                           <?php
                        }
                        if($amt_colors_dorso > 0)
                        {  ?>
                           <option value="Dorso" <?if($_SESSION["_PROD_setcolormode"] == "Dorso") echo "selected"?>>Dorso</option>
                           <?php
                        }
                        ?>
                     </select>
                  </td>
               </tr>
               </table>
               <?php
               if($_SESSION["_PROD_setcolormode"] != "")
                  require_once("form.v2pulpo.php");
            }
            elseif((int)$hasopeninit["equipo_prod_isprinter_seri"] && !$_ISSERIPULPO)
            {
               $_HIDE_OLD_MAINTABLE = true;

               // echo "<pre>";
               // print_R($agenda);
               $amt_colors_seri = 0;
               for($xx = 1; $xx <= 10; $xx++)
               {
                  if((int)$agenda["fab_print_colors_front_{$xx}"] || (int)$agenda["fab_print_colors_back_{$xx}"])
                     $amt_colors_seri++;
               }
               ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="200">
                  <col width="40%">
                  <col width="185">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-user"></i> Información Cliente
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° OT</td>
                  <td class="tdnrm"><?=$agenda["prd_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cant. Colores</td>
                  <td class="tdnrm"><?=printPrice($amt_colors_seri)?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                  <td class="tdnrm"><?=$agenda["cust_name"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 1</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_1"] || (int)$agenda["fab_print_colors_back_1"])
                        echo $agenda["fab_print_colordesc_1"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° CC</td>
                  <td class="tdnrm"><?=$agenda["req_number"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 2</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_2"] || (int)$agenda["fab_print_colors_back_2"])
                        echo $agenda["fab_print_colordesc_2"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                  <td class="tdnrm"><?=$agenda["item_number_prod"]?> | <?=$agenda["item_title"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 3</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_3"] || (int)$agenda["fab_print_colors_back_3"])
                        echo $agenda["fab_print_colordesc_3"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                  <td class="tdnrm"><?=$agenda["fab_type"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 4</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_4"] || (int)$agenda["fab_print_colors_back_4"])
                        echo $agenda["fab_print_colordesc_4"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Alto</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_height"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Cantidad de Bolsas</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=printPrice($agenda["ag_amount"])?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Fecha de Entrega</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;"><?=$agenda["req_despacho_desc"]?></td>
               </tr>
               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;" colspan="2">Fecha solicitud película</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_solic_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_solic_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Fecha recepción película</td>
                  <td class="tdnrm" style="" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_recep_dat"] > 0)
                        echo date('d.m.Y', $agenda["req_cliche_peli_recep_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <?php
               if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen diseño</td>
                     <td class="content_row">
                        <div class="btngrey"
                        onclick="showColorbox('/docs.order/<?=$agenda["fab_design_imagehash"]?>', 'image', '99%', '99%', 'auto')">
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                        </div>
                     </td>
                     <td class="tdnrm">&nbsp;</td>
                  </tr>
                  <?php
               }
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {  ?>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm">
                           <div class="btngrey"
                           onclick="showColorbox('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>', 'image', '99%', '99%', 'auto')">
                              <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                           </div>
                        </td>
                        <td class="tdnrm"><?=$agenda["req_prod_adjcomments_{$x}"]?></td>
                     </tr>
                     <?php
                     $_HAS_FILES = true;
                  }
               }
               ?>
               </table>

               <table border="0" width="100%" cellpadding="6" cellspacing="0">
                  <colgroup>
                  <col width="200">
                  <col width="40%">
                  <col width="185">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                     <i class="fa fa-fw fa-info-circle"></i> Información de Fabricación
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Desarrollo</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["item_prodcalc_fuelle_act"])
                        $param_medida = (int)$agenda["fab_med_width"] + (int)$agenda["fab_med_fuelle"];
                     else
                        $param_medida = (int)$agenda["fab_med_width"];

                     $sql = " select *
                              from equipo_params
                              where
                              param_equipo_id = {$hasopeninit["win_equipoid"]} and
                              param_medida    >= {$param_medida}
                              order by param_medida asc
                              LIMIT 0,1";
                     $equipo_params = $CON->select($sql);
                     $equipo_params = $equipo_params[0];
                     $corte_m2 = (float)$equipo_params["param_corte"];
                     $corte_z  = (int)$equipo_params["param_z"];
                     ?>
                     <?=printPrice($corte_m2,4)?> mtrs
                  </td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 1</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_1"] || (int)$agenda["fab_print_colors_back_1"])
                        echo $agenda["fab_print_colordesc_1"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Pie de Imprenta</td>
                  <td class="tdnrm"><?=$agenda["req_pie_imprenta"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 2</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_2"] || (int)$agenda["fab_print_colors_back_2"])
                        echo $agenda["fab_print_colordesc_2"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">N° Código de Barra</td>
                  <td class="tdnrm"><?=$agenda["item_sellprice_barcodenumber"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 3</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_3"] || (int)$agenda["fab_print_colors_back_3"])
                        echo $agenda["fab_print_colordesc_3"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Impresiones en cilindro</td>
                  <td class="tdnrm">&nbsp;</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color N° 4</td>
                  <td class="tdnrm">
                     <?php
                     if((int)$agenda["fab_print_colors_front_4"] || (int)$agenda["fab_print_colors_back_4"])
                        echo $agenda["fab_print_colordesc_4"];
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Impresiones por desarrollo</td>
                  <td class="tdnrm"><?=(int)$agenda["req_solic_devprints_cc"]?> impresiones</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm">&nbsp;</td>
               </tr>

               <tr>
                  <td colspan="4">
                     <div style="height:15px"></div>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD">Materialidad</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD"><?=$agenda["fab_type"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-top: 1px solid #DDDDDD;">Metros a imprimir</td>
                  <td class="tdnrm" style="border-top: 1px solid #DDDDDD;">
                     <?php
                     $mlin_prog = round($agenda["ag_amount"] * $corte_m2);
                     $mlin_addi = round($mlin_prog / 100 * (float)$agenda["req_operador_mermaperc"]);

                     $mlin_print = round($mlin_prog + $mlin_addi);
                     echo printPrice($mlin_print);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Color Tela</td>
                  <td class="tdnrm" style=""><?=$agenda["fabric_color"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Contador impresora</td>
                  <td class="tdnrm" style="">
                     <?php
                     $mera_units = round($agenda["ag_amount"] / 100 * (float)$agenda["req_operador_mermaperc"]);
                     $real_unit = $agenda["ag_amount"] + $mera_units;
                     $pasadas_print = round($real_unit / $agenda["req_operador_bastidoramt"]);
                     echo printPrice($pasadas_print);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Ancho Tela</td>
                  <td class="tdnrm" style=""><?=$agenda["req_operador_tela_width"]?></td>
                  <td class="tdleft" style="background-color:#EEEEEE;">Kg a imprimir</td>
                  <td class="tdnrm" style="">
                     <?php
                     $kgs_process = round($mlin_print * $agenda["req_operador_tela_width"] / 100 * $agenda["fab_mat_gramms"] / 1000);
                     echo printPrice($kgs_process);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                  <td class="tdnrm" style=""><?=$agenda["fab_mat_gramms"]?> gr&nbsp;</td>
                  <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                  <td class="tdnrm" style="">&nbsp;</td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Cantidad de imágenes impresas en bastidor</td>
                  <td class="tdnrm" style="padding:2px" colspan="3">
                     <select class="inptxt" style="background-color:#FFFFFF;width:120px;padding:2px;float:left"
                     name="idx_bastidor_amt" id="idx_bastidor_amt">
                        <option value="">Seleccione</option>
                        <?php
                        for($zzz = 1; $zzz <= 5; $zzz++)
                        {  ?>
                           <option value="<?=$zzz?>" <?if($agenda["req_operador_bastidoramt"] == $zzz) echo "selected"?>><?=$zzz?></option>
                           <?php
                        }
                        ?>
                     </select>
                     <input type="button" class="btngreen" value="Guardar" style="padding:5px;border:0px;font-size:12px;margin-left:10px"
                     onclick="setSpecParams()">
                  </td>
               </tr>
               <?php
               if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
               {  ?>
                  <tr>
                     <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen diseño</td>
                     <td class="content_row">
                        <div class="btngrey"
                        onclick="showColorbox('/docs.order/<?=$agenda["fab_design_imagehash"]?>', 'image', '99%', '99%', 'auto')">
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                        </div>
                     </td>
                     <td class="tdnrm">&nbsp;</td>
                  </tr>
                  <?php
               }
               for($x = 0; $x < 5; $x++)
               {
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {  ?>
                     <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm">
                           <div class="btngrey"
                           onclick="showColorbox('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>', 'image', '99%', '99%', 'auto')">
                              <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                           </div>
                        </td>
                        <td class="tdnrm"><?=$agenda["req_prod_adjcomments_{$x}"]?></td>
                     </tr>
                     <?php
                     $_HAS_FILES = true;
                  }
               }
               ?>
               </table>
               <?php
            }
            else
            {  ?>
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="120">
                  <col width="40%">
                  <col width="120">
                  <col width="40%">
               </colgroup>
               <tr>
                  <td class="tdleft">N° OT</td>
                  <td class="tdnrm"><?=$agenda["prd_number"]?></td>
                  <td class="tdleft">N° CC</td>
                  <td class="tdnrm"><?=$agenda["req_number"]?></td>
               </tr>
               <tr>
                  <td class="tdleft">Cliente</td>
                  <td class="tdnrm"><?=$agenda["cust_name"]?></td>
                  <td class="tdleft">Producto</td>
                  <td class="tdnrm"><?=$agenda["item_number_prod"]?> | <?=$agenda["item_title"]?></td>
               </tr>
               <tr>
                  <td class="tdleft">Medidas</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?> x <?=(int)$agenda["fab_med_height"]?></td>
                  <td class="tdleft">Fuelle</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
               </tr>
               <tr>
                  <td class="tdleft">Area</td>
                  <td class="tdnrm"><?=(int)$agenda["fab_print_width"]?> x <?=(int)$agenda["fab_print_height"]?></td>
                  <td class="tdleft">Tela</td>
                  <td class="tdnrm"><?=$agenda["fabric_color"]?></td>
               </tr>
               <tr>
                  <td class="tdleft">Manillas</td>
                  <td class="tdnrm"><?=$agenda["manilla_color"]?></td>
                  <td class="tdleft">Colores</td>
                  <td class="tdnrm"><?=$printcolors?></td>
               </tr>
               <tr>
                  <td class="tdleft">Diseño</td>
                  <td class="tdnrm"><?=$agenda["fab_design_name"]?></td>
                  <td class="tdleft">Entregas</td>
                  <td class="tdnrm"><?=$entrega_vals?></td>
               </tr>
               <tr>
                  <td class="tdleft" style="border:0px">Gramaje</td>
                  <td class="tdnrm" style="border:0px"><?=$agenda["fab_mat_gramms"]?> gr&nbsp;</td>
                  <td class="tdleft" style="border:0px">Desarrollo</td>
                  <td class="tdnrm" style="border:0px"><?=(int)$agenda["req_solic_devprints_cc"]?> impresiones de desarrollo</td>
               </tr>
               </table>
               <?php
            }
            ?>
         </td>
      </tr>
      </table>
      <?php
      if(!(int)$hasopeninit["equipo_prod_isprinter_seri"] && !(int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {  ?>
         <div style="display:none">
         <?php
      }
      ?>
      <script language="JavaScript">
         function setSpecParams()
         {
            var xurl = '/prodwrk.php?mid=2&setspecparams=1&agid=<?=$_REQUEST["agid"]?>';
            <?php
            if((int)$hasopeninit["equipo_prod_isprinter_seri"])
            {  ?>
               xurl = xurl + '&bastidoramt=' +$('#idx_bastidor_amt').val();
               <?php
            }
            ?>
            location.href = xurl;
         }

         function GrabaAnilox(unidad)
         {
            var xurl = '/prodwrk.php?mid=2&save_anilox=1&agid=<?=$_REQUEST["agid"]?>';
            for(let x=1;x<unidad;x++)
            {
               var revisar = document.getElementById("id_anilox_"+x);
               if(revisar)
               {
                  xurl = xurl + '&anilox'+x+'='+revisar.value;
               }
            }
            location.href = xurl;
         }

         function MoverFlecha(ir,unidad)
         {
            var xurl = '/prodwrk.php?mid=2&mover='+ir+'&agid=<?=$_REQUEST["agid"]?>&unidad='+unidad;
            location.href = xurl;
         }

      </script>
      <div style="clear:both;height:10px"></div>

      <?php
      if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {
         ?>
            <table border="0" width="75%" cellpadding="0" cellspacing="0" align="center">
               <tr>
                  <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
                     <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                           <col width="10">
                           <col>
                           <col>
                           <col width="10">
                           <col width="10">
                        </colgroup>
                        <tr>
                           <td class="tdleft" style="background-color:#EEEEEE" colspan="6">Conformación de Anilox</td>
                        </tr>
                        <tr>
                            <td class="tdheader" align="center">Unidad</td>
                            <td class="tdheader" align="center">Color</td>
                            <td class="tdheader" align="center">Anilox</td>
                            <td class="tdheader" align="center" colspan="2">Opciones</td>
                        </tr>
                        <?php
                           $x = 1;
                           foreach($detalle_anilox AS $anilox)
                           {  
                              ?>
                              <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                                 <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$anilox["paow_unidad"]?></td>
                                 <td class="tdnrm" style="border-left:1px solid #DDDDDD;width:300px">
                                    <input type="text" class="inptxt" id ="color_<?=$anilox["paow_unidad"]?>" name="color_<?=$anilox["paow_unidad"]?>" style="width:600px;"
                                    value="<?=$anilox["paow_color"]?>" disabled>
                                 </td>
                                 <td class="tdnrm" style="border-left:1px solid #DDDDDD;width:600px">
                                       <select name="id_anilox_<?=$anilox["paow_unidad"]?>" 
                                                 id="id_anilox_<?=$anilox["paow_unidad"]?>" 
                                             class="inptxt" style="width:400px;background-color:#FFFFFF">
                                             <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                                             <?php
                                             foreach($item_anilox AS $ia)
                                             {  ?>
                                                <option value="<?=$ia["id"]?>"
                                                <?if($ia["id"] == $anilox["paow_anilox"]) echo "selected"?>>
                                                   <?=$ia["item_number_prod"]."-".$ia["item_title"]?>
                                                </option>
                                                <?php
                                             }
                                             ?>
                                       </select>
                                 </td>
                                 <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                       <span style="float:left">
                                          <img src="/images/menu/icons/arrow-090.png" style="cursor:pointer" title="Mover arriba"
                                          onclick="MoverFlecha(1,<?=$anilox['paow_unidad']?>);">
                                       </span>
                                 </td> 
                                 <!--
                                 <td>
                                    <span style="float:left">
                                         <img src="/images/menu/icons/arrow-skip-090.png" style="cursor:pointer" title="Mover a primera posicion"
                                          onclick="ArribaInicio(<?=$anilox['paow_unidad']?>);">
                                    </span>
                                 </td>
                                 -->
                                 <td>
                                    <span style="float:left">
                                          <img src="/images/menu/icons/arrow-270.png" style="cursor:pointer" title="Mover abajo"
                                          onclick="MoverFlecha(2,<?=$anilox['paow_unidad']?>);">

                                     </span>
                                 </td>
                                 <!--
                                 <td>
                                    <span style="float:left">
                                         <img src="/images/menu/icons/arrow-skip-270.png" style="cursor:pointer" title="Mover a ultima posicion"
                                          onclick="AbajoFin(<?=$anilox['paow_unidad']?>);">
                                     </span>
                                 </td>
                                 -->
                              <?php
                              $x++;
                           }
                        ?>
                        <tr>
                            <td colspan="6">
                              <div class="btngreen" onclick="GrabaAnilox(<?=$x?>);">
                                 <i class="fa fa-fw fa-save" style="color:white;"></i>Registrar Anilox&nbsp;
                              </div>
                            </td>
                        </tr>
                     </table>
                  </td>
               </tr>
            </table>
         <?php
      }
      ?>

      <div style="clear:both;height:10px"></div>
      <?php
      if($_HIDE_OLD_MAINTABLE)
         echo "<div style='display:none'>";
      ?>
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
            <table border="0" width="100%" cellpadding="6" cellspacing="0">
            <colgroup>
               <col width="350">
               <col>
            </colgroup>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE">Unidades solicitadas</td>
               <td class="tdnrm" style="background-color:#EEEEEE"><b><?=printPrice($agenda["ag_amount"])?></b></td>
            </tr>
            <tr>
               <td class="tdleft">% de merma</td>
               <td class="tdnrm"><?=printPrice($agenda["req_operador_mermaperc"],2)?></td>
            </tr>
            <tr>
               <td class="tdleft">Unidades adicionales por merma</td>
               <td class="tdnrm">
                  <?php
                  $mera_units = round($agenda["ag_amount"] / 100 * (float)$agenda["req_operador_mermaperc"]);
                  echo printPrice($mera_units);
                  ?>
               </td>
            </tr>
            <tr>
               <td class="tdleft">Unidades reales a imprimir</td>
               <td class="tdnrm">
                  <?php
                  $real_unit = $agenda["ag_amount"] + $mera_units;
                  echo printPrice($real_unit);
                  ?>
               </td>
            </tr>
            <?php
            if((int)$hasopeninit["equipo_prod_isprinter_seri"])
            {  ?>
               <tr>
                  <td class="tdleft" style="padding:2px">&nbsp;Cantidad de imágenes impresas en bastidor</td>
                  <td class="tdnrm" style="padding:2px">
                     <select class="inptxt" style="background-color:#FFFFFF;width:120px;padding:2px;float:left"
                     name="idx_bastidor_amt" id="idx_bastidor_amt">
                        <option value="">Seleccione</option>
                        <?php
                        for($zzz = 1; $zzz <= 5; $zzz++)
                        {  ?>
                           <option value="<?=$zzz?>" <?if($agenda["req_operador_bastidoramt"] == $zzz) echo "selected"?>><?=$zzz?></option>
                           <?php
                        }
                        ?>
                     </select>
                     <input type="button" class="btngreen" value="Guardar" style="padding:5px;border:0px;font-size:12px;margin-left:10px"
                     onclick="setSpecParams()">
                  </td>
               </tr>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Pasadas totales a imprimir</td>
                  <td class="tdnrm" style="background-color:#EEEEEE">
                     <b>
                     <?php
                     $pasadas_print = round($real_unit / $agenda["req_operador_bastidoramt"]);
                     echo printPrice($pasadas_print);
                     ?>
                     </b>
                  </td>
               </tr>
               <?php
            }

            ?>
            <tr>
               <td class="tdleft">Corte de bolsa</td>
               <td class="tdnrm">
                  <?php
                  if((int)$agenda["item_prodcalc_fuelle_act"])
                     $param_medida = (int)$agenda["fab_med_width"] + (int)$agenda["fab_med_fuelle"];
                  else
                     $param_medida = (int)$agenda["fab_med_width"];

                  $sql = " select *
                           from equipo_params
                           where
                           param_equipo_id = {$hasopeninit["win_equipoid"]} and
                           param_medida    >= {$param_medida}
                           order by param_medida asc
                           LIMIT 0,1";
                  $equipo_params = $CON->select($sql);
                  $equipo_params = $equipo_params[0];

                  if((int)$hasopeninit["equipo_prod_isprinter_seri"])
                  {
                     $corte_m2 = (float)$equipo_params["param_corte"];
                     $corte_z  = (int)$equipo_params["param_z"];
                  }
                  elseif((int)$hasopeninit["equipo_prod_isprinter_flexo"])
                  {
                     if($agenda["req_solic_devprints_poltype"] == "pol284")
                     {
                        $corte_m2 = (float)$equipo_params["param_poly28"];
                        $corte_z  = (int)$equipo_params["param_z"];
                     }
                     elseif($agenda["req_solic_devprints_poltype"] == "pol170")
                     {
                        $corte_m2 = (float)$equipo_params["param_poly17"];
                        $corte_z  = (int)$equipo_params["param_z"];
                     }

                     //print_r($agenda);
                     // echo "<pre>";
                     // print_r($equipo_params);
                     // echo $agenda["req_solic_devprints_poltype"];
                  }
                  elseif($_ISSELLADORA)
                  {
                     $sql = " select t4.*, t5.mant_title, t7.req_number, t1.prd_number, t8.cust_name,
                                     t2x.item_number_prod, t2x.item_title, t5x.pause_name, t5x.pause_code,
                                     t5.mant_code, t2.ag_equipotype_id, t2.ag_prdid, t1x.item_amount,
                                     t6.win_equipoid
                              from prod_header t1
                              INNER JOIN prod_agenda t2           ON t1.id = t2.ag_prdid
                              INNER JOIN prod_worker_ot t3        ON t3.wok_ag_id = t2.id
                              INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
                              LEFT OUTER JOIN equipo_manttype t5  ON t4.evt_equipo_mantid = t5.id
                              INNER JOIN prod_worker_init t6      ON t3.wok_init_id = t6.id
                              INNER JOIN orders t7                ON t2.ag_reqid = t7.id
                              LEFT OUTER JOIN customer t8         ON t7.req_cust_id = t8.id
                              INNER JOIN orders_items t1x         ON t7.id = t1x.req_id
                              INNER JOIN item t2x                 ON t1x.item_id = t2x.id
                              LEFT OUTER JOIN prod_pause_types t5x ON t4.evt_pause_id = t5x.id
                              INNER JOIN equipo t6x               ON t6.win_equipoid = t6x.id
                              where
                              t1.prd_reqid   = {$hasopenot["prd_reqid"]} and
                              t1.prd_status  = 2 and
                              t2.ag_status   > 0 and
                              t3.wok_status  > 0 and
                              t4.evt_status  > 0 and
                              t6.win_status  > 0 and
                              t4.evt_type    = 'prod' and
                              t4.evt_amount  > 0 and
                              (
                                 t6x.equipo_prod_isprinter_seri   = 1 or
                                 t6x.equipo_prod_isprinter_flexo  = 1
                              )
                              order by t4.evt_crtdat desc
                              LIMIT 0,1";
                     $lastprodevent = $CON->select($sql);
                     $lastprodevent = $lastprodevent[0];

                     $sql = " select *
                              from equipo_params
                              where
                              param_equipo_id = {$lastprodevent["win_equipoid"]} and
                              param_medida    >= {$param_medida}
                              order by param_medida asc
                              LIMIT 0,1";
                     $equipo_params = $CON->select($sql);
                     $equipo_params = $equipo_params[0];

                     if($agenda["fab_printtype"] == "SERI")
                     {
                        $corte_m2 = (float)$equipo_params["param_corte"];
                        $corte_z  = (int)$equipo_params["param_z"];
                     }
                     else
                     {
                        if($agenda["req_solic_devprints_poltype"] == "pol284")
                        {
                           $corte_m2 = (float)$equipo_params["param_poly28"];
                           $corte_z  = (int)$equipo_params["param_z"];
                        }
                        elseif($agenda["req_solic_devprints_poltype"] == "pol170")
                        {
                           $corte_m2 = (float)$equipo_params["param_poly17"];
                           $corte_z  = (int)$equipo_params["param_z"];
                        }
                     }
                  }
                  ?>
                  <?=printPrice($corte_m2,4)?> mtrs
               </td>
            </tr>
            <?php
            if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
            {  ?>
               <tr>
                  <td class="tdleft">Rodillo a utilizar</td>
                  <td class="tdnrm">Z = <?=printPrice($corte_z,0);?></td>
               </tr>
               <?php
            }
            ?>
            <tr>
               <td class="tdleft">Metros lineales programados</td>
               <td class="tdnrm">
                  <?php
                  $mlin_prog = round($agenda["ag_amount"] * $corte_m2);
                  echo printPrice($mlin_prog);
                  ?>
               </td>
            </tr>
            <tr>
               <td class="tdleft">Metros lineales adicionales a procesar</td>
               <td class="tdnrm">
                  <?php
                  $mlin_addi = round($mlin_prog / 100 * (float)$agenda["req_operador_mermaperc"]);
                  echo printPrice($mlin_addi);
                  ?>
               </td>
            </tr>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE">Total metros lineales a imprimir</td>
               <td class="tdnrm" style="background-color:#EEEEEE">
                  <b>
                  <?php
                  $mlin_print = round($mlin_prog + $mlin_addi);
                  echo printPrice($mlin_print);
                  ?>
                  </b>
               </td>
            </tr>
            <?php
            if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
            {  ?>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE">Total mts/máquina</td>
                  <td class="tdnrm" style="background-color:#EEEEEE">
                     <b>
                     <?php
                     $mlin_maquina = round($mlin_print / (float)$hasopeninit["equipo_prod_divisor_perc"]);
                     echo printPrice($mlin_maquina);
                     ?>
                     </b>
                  </td>
               </tr>
               <?php
            }
            ?>
            <tr>
               <td class="tdleft">Ancho de bobina (cm)</td>
               <td class="tdnrm"><?=$agenda["req_operador_tela_width"]?></td>
            </tr>
            <tr>
               <td class="tdleft">Gramaje de tela</td>
               <td class="tdnrm"><?=$agenda["fab_mat_gramms"]?> gr&nbsp;</td>
            </tr>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE">Kgs a procesar</td>
               <td class="tdnrm" style="background-color:#EEEEEE">
                  <b>
                  <?php
                  $kgs_process = round($mlin_print * $agenda["req_operador_tela_width"] / 100 * $agenda["fab_mat_gramms"] / 1000);
                  echo printPrice($kgs_process);
                  ?>
                  </b>
               </td>
            </tr>
            <?php
            $_THIS_STATS   = getProdStats($CON, 0, $agenda["id"], 0, 0, 0, $agenda["ag_equipotype_id"]);
            $_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);
            $thisprodperc  = $_THIS_STATS["_PROD_AMOUNT"] / $agenda["ag_amount"] * 100;
            $totlprodperc  = $_OT_STATS["_PROD_AMOUNT"] / $agenda["item_amount"] * 100;
            ?>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE;border-top:3px solid #0E7572">Bolsas producidas / en curso</td>
               <td class="tdnrm" style="background-color:#EEEEEE;border-top:3px solid #0E7572"><?=printPrice($_THIS_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["ag_amount"])?> (<?=printPrice($thisprodperc,2)?> %)</td>
            </tr>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE">Bolsas producidas / total</td>
               <td class="tdnrm" style="background-color:#EEEEEE"><?=printPrice($_OT_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["item_amount"])?> (<?=printPrice($totlprodperc,2)?> %)</td>
            </tr>
            <tr>
               <td class="tdleft" style="background-color:#EEEEEE">Saldo total</td>
               <td class="tdnrm" style="background-color:#EEEEEE"><?=printPrice($agenda["item_amount"] - $_OT_STATS["_PROD_AMOUNT"])?> unidades</td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?php
      if($_HIDE_OLD_MAINTABLE)
         echo "</div>";

      //----------------------------------------------------------------------------------
      if($_REQUEST["recalctype"] != "" && (int)$_REQUEST["recalcevtid"])
      {
         $recalcevent = Array();
         foreach($events AS $event)
         {
            if($event["id"] == (int)$_REQUEST["recalcevtid"])
               $recalcevent = $event;
         }
         if((int)$recalcevent["id"])
         {
            if($recalcevent["evt_metrotype"] == "metros_maquina")
            {
               $unit_prod = $recalcevent["evt_amount_metros_maquina"] * $hasopeninit["equipo_prod_divisor_perc"];
               $unit_prod = floor($unit_prod / $corte_m2);
            }
            elseif($recalcevent["evt_metrotype"] == "metros_lineales")
            {
               $unit_prod = $recalcevent["evt_amount_metros_lineales"] * $hasopeninit["equipo_prod_divisor_perc"];
               $unit_prod = floor($unit_prod / $corte_m2);
            }

            $unit_prod = (int)$unit_prod;
            $sql = " update prod_worker_ot_events
                     set
                     evt_amount = {$unit_prod}
                     where
                     id = {$recalcevent["id"]}";
            $CON->no_result($sql);

            $_REIDRECT_TOBASE = true;
         }
      }

      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["mermaconvert"])
      {
         foreach($events AS $event)
         {
            $sql = " select *
                     from prod_worker_ot_defectunits
                     where
                     evt_refid = {$event["id"]} and
                     evt_type  = 'merma' and
                     evt_kgstounits != 0";
            $defectunits = $CON->select($sql);
            foreach($defectunits AS $defectunit)
            {
               $evt_amount = ceil($corte_m2 * ($agenda["req_operador_tela_width"] / 100) * ($defectunit["evt_kgstounits"] / 1000));

               $sql = " update prod_worker_ot_defectunits
                        set
                        evt_amount = {$evt_amount}
                        where
                        id = {$defectunit["id"]}";
               $CON->no_result($sql);
            }
         }
         $_REIDRECT_TOBASE = true;
      }

      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["repairconvert"])
      {
         foreach($events AS $event)
         {
            $sql = " select *
                     from prod_worker_ot_defectunits
                     where
                     evt_refid = {$event["id"]} and
                     evt_type  = 'repair' and
                     evt_kgstounits != 0";
            $defectunits = $CON->select($sql);
            foreach($defectunits AS $defectunit)
            {
               $evt_amount = ceil($corte_m2 * ($agenda["req_operador_tela_width"] / 100) * ($defectunit["evt_kgstounits"] / 1000));

               $sql = " update prod_worker_ot_defectunits
                        set
                        evt_amount = {$evt_amount}
                        where
                        id = {$defectunit["id"]}";
               $CON->no_result($sql);
            }
         }
         $_REIDRECT_TOBASE = true;
      }

      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["mermamtsconvert"])
      {
         foreach($events AS $event)
         {
            $sql = " select *
                     from prod_worker_ot_defectunits
                     where
                     evt_refid = {$event["id"]} and
                     evt_type  = 'merma' and
                     evt_mtstounits != 0";
            $defectunits = $CON->select($sql);

            foreach($defectunits AS $defectunit)
            {
               $evt_amount = $corte_m2 * ($agenda["req_operador_tela_width"] / 100) * ($defectunit["evt_mtstounits"] / 1000);
               $evt_amount = $evt_amount + (6 * $agenda["fab_manilla_length"]) * ($defectunit["evt_mtstounits"] / 1000);
               $evt_amount = ceil($evt_amount);

               $sql = " update prod_worker_ot_defectunits
                        set
                        evt_amount = {$evt_amount}
                        where
                        id = {$defectunit["id"]}";
               $CON->no_result($sql);
            }
         }
      }

      //----------------------------------------------------------------------------------
      if($_REIDRECT_TOBASE)
      {  ?>
         <script language="Javascript">
            location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';
         </script>
         <?php
         exit;
      }
      if(!(int)$hasopeninit["equipo_prod_isprinter_seri"] && !(int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {  ?>
         </div>
         <?php
      }

      $sql = " select *
               from orders_classify_subproducts
               where
               req_id = {$agenda["ag_reqid"]}
               order by sub_pos";
      $subproducts = $CON->select($sql);
      foreach($subproducts AS $subproduct)
         $_SUBPRODUCTS[$subproduct["sub_pos"]] = $subproduct;

      //----------------------------------------------------------------------------------
      if($_ISREBOBINADORA && (int)$agenda["req_rebo_rolloscc"] && count($_SUBPRODUCTS))
      {
         $sql = " select *
                  from orders_classify_subproducts_inputweights
                  where
                  req_id            = {$agenda["ag_reqid"]} and
                  prod_worker_ot_id = {$hasopenot["id"]}
                  order by subprod_rollo_idx";
         $inputweights = $CON->select($sql);
         foreach($inputweights AS $inputweight)
            $_INPUTWEIGHTS[$inputweight["subprod_rollo_idx"]] = $inputweight;
         ?>
         <form action="prodwrk.php" method="post" name="xform_rebo">
         <input type="hidden" name="mid" value="2">
         <input type="hidden" name="reboexec" value="save">
         <input type="hidden" name="agid" value="<?$_REQUEST["agid"]?>">
         <input type="hidden" name="req_codigo_cierre" value="<?$_REQUEST["req_codigo_cierre"]?>">
         <div style="clear:both;height:10px"></div>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
               <b>Tarea: <?=$agenda["req_rebo_type"]?></b>
               <div style="clear:both;height:10px"></div>
               <table border="0" width="100%" cellpadding="3" cellspacing="0">
               <colgroup>
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;" align="center">Nï¿½</td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;">Tipo bobina</td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;">Materialidad</td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;">Color bobina</td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;" align="center">Gramaje</td>
                  <td class="tdleft" style="background-color:#EEEEEE;border-left:3px double #666666;border-top:1px solid #DDDDDD;" align="center">Peso<br>entrante</td>
                  <?php
                  foreach(array_keys($_SUBPRODUCTS) AS $sub_pos)
                  {  ?>
                     <td class="tdleft" style="background-color:#EEEEEE;border-left:3px double #666666;border-top:1px solid #DDDDDD;" align="center">
                        Peso salida<br>
                        Ancho: <?=printPrice($_SUBPRODUCTS[$sub_pos]["subprod_ancho_cm"])?> cm
                        <br>
                        Cantidad: <?=printPrice($_SUBPRODUCTS[$sub_pos]["subprod_amount"])?>
                     </td>
                     <?php
                  }
                  ?>
               </tr>
               <?php
               for($x = 0; $x < (int)$agenda["req_rebo_rolloscc"]; $x++)
               {
                  $xline = $_INPUTWEIGHTS[$x];
                  ?>
                  <tr>
                     <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;"><?=($x+1)?></td>
                     <td class="tdnrm" align="left" style="border-left:1px solid #DDDDDD;"><?=$agenda["req_rebo_state"]?></td>
                     <td class="tdnrm" align="left" style="border-left:1px solid #DDDDDD;"><?=$agenda["fab_type"]?></td>
                     <td class="tdnrm" align="left" style="border-left:1px solid #DDDDDD;"><?=$agenda["fabric_color"]?></td>
                     <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;"><?=$agenda["fab_mat_gramms"]?></td>
                     <td class="tdnrm" align="center" style="border-left:3px double #666666;">
                        <input type="text" class="inptxt" style="width:100%;text-align:center"
                        name="subprod_weight_enter_<?=$x?>" value="<?if((int)$xline["req_id"]) echo printPrice($xline["subprod_weight_enter"])?>">
                     </td>
                     <?php
                     foreach(array_keys($_SUBPRODUCTS) AS $sub_pos)
                     {  ?>
                        <td class="tdnrm" align="center" style="border-left:3px double #666666;">
                           <input type="text" class="inptxt" style="width:100%;text-align:center"
                           name="subprod_salida_weight_<?=$x?>_<?=$sub_pos?>"
                           value="<?if((int)$xline["req_id"]) echo printPrice($xline["subprod_salida_weight_{$sub_pos}"])?>">
                        </td>
                        <?php
                     }
                     ?>
                  </tr>
                  <?php
               }
               ?>
               </table>
            </td>
         </tr>
         </table>

         <div class="btngreen"
              onclick="document.xform_rebo.submit();">
              <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
         </div>
         </form>
         <?php
      }
      if(!(int)$hasopeninit["equipo_prod_isprinter_seri"] && !(int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {
         if($_ISREBOBINADORA || $_ISSELLADORA)
            echo "<div style='display:none'>";
         ?>
         <div style="clear:both;height:10px"></div>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="350">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft" style="border:0px;background-color:#EEEEEE">Unidades solicitadas</td>
                  <td class="tdnrm" style="border:0px;background-color:#EEEEEE"><b><?=printPrice($agenda["ag_amount"])?></b></td>
               </tr>
               </table>
            </td>
         </tr>
         </table>
         <?php
         if($_ISREBOBINADORA || $_ISSELLADORA)
            echo "</div>";
      }
      ?>
      <div style="clear:both;height:10px"></div>
      <?php
      if($_REQUEST["mode"] == "")
      {
         if($_ISREBOBINADORA || $_ISSELLADORA || $_HIDE_OLD_MAINTABLE)
            echo "<div style='display:none'>";
         ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
               <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col width="350">
                  <col width="180">
                  <col>
               </colgroup>
               <tr>
                  <td class="tdleft">
                     Fecha solicitud 
                     <?php
                     if($agenda["fab_printtype"] == "FLEX")
                        echo "cliché";
                     elseif($agenda["fab_printtype"] == "SERI")
                        echo "película";
                     ?>
                  </td>
                  <td class="tdnrm" colspan="2">
                     <?php
                     if($agenda["req_cliche_peli_solic_dat"] > 0) 
                        echo date('d.m.Y', $agenda["req_cliche_peli_solic_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="tdleft">
                     Fecha recepción
                     <?php
                     if($agenda["fab_printtype"] == "FLEX")
                        echo "cliché";
                     elseif($agenda["fab_printtype"] == "SERI")
                        echo "película";
                     ?>
                  </td>
                  <td class="tdnrm" colspan="2">
                     <?php 
                     if($agenda["req_cliche_peli_recep_dat"] > 0) 
                        echo date('d.m.Y', $agenda["req_cliche_peli_recep_dat"]);
                     else
                        echo "<b class=msg_save_err>N/A</b>";
                     ?>
                  </td>
               </tr>
               <?php
               if($agenda["fab_design_imagehash"] != "" && $agenda["fab_design_imagehash"] != "dummy")
               {  ?>
                  <tr>
                     <td class="tdleft">Imagen diseño</td>
                     <td class="content_row">
                        <div class="btngrey" 
                        onclick="showColorbox('/docs.order/<?=$agenda["fab_design_imagehash"]?>', 'image', '99%', '99%', 'auto')">
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                        </div>
                     </td>
                     <td class="tdnrm">&nbsp;</td>
                  </tr>
                  <?php
               }
               for($x = 0; $x < 5; $x++)
               {  
                  if($agenda["req_prod_adjfile_{$x}"] != "")
                  {  ?>
                     <tr>
                        <td class="tdleft">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm">
                           <div class="btngrey" 
                           onclick="showColorbox('/docs.prod/<?=$agenda["req_prod_adjfile_{$x}"]?>', 'image', '99%', '99%', 'auto')">
                              <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen&nbsp;
                           </div>
                        </td>
                        <td class="tdnrm"><?=$agenda["req_prod_adjcomments_{$x}"]?></td>
                     </tr>
                     <?php
                     $_HAS_FILES = true;
                  }
               }
               ?>
               </table>
            </td>
         </tr>
         </table>
         <div style="clear:both;height:10px"></div>
         <?php
         if($_ISREBOBINADORA || $_ISSELLADORA || $_HIDE_OLD_MAINTABLE)
            echo "</div>";
      }
      if($_REQUEST["mode"] == "production")
         require_once("form.production.php");
      elseif($_REQUEST["mode"] == "reboprod")
         require_once("form.reboprod.php");
      elseif($_REQUEST["mode"] == "apertura")
         require_once("form.apertura.php");
      elseif($_REQUEST["mode"] == "mantencion")
         require_once("form.mantencion.php");
      elseif($_REQUEST["mode"] == "consumo")
         require_once("form.consumo.php");
      elseif($_REQUEST["mode"] == "pause")
         require_once("form.pause.php");
      elseif($_REQUEST["mode"] == "materiales")
         require_once("form.materiales.php");
      elseif($_REQUEST["mode"] == "btestock")
         require_once("form.btestock.php");
      else
      {
         $_CANTERM            = true;
         $_HASALIS            = false;
         $_HASPROD            = false;
         $_HASPAUSE_PENDING   = false;
         $sql = " select *
                  from prod_worker_ot_events
                  where
                  (
                     evt_prod_worker_otid = {$hasopenot["id"]} or
                     (
                        overrideembalaje_act = 1 and
                        overrideembalaje_selladora_evtrefid = {$hasopenot["id"]}
                     )
                  ) and
                  evt_status           > 0
                  order by evt_crtdat desc";
         $events = $CON->select($sql);

         // echo "<pre>";
         // print_R($events);
         for($x = 0; $x < count($events) && $events != false; $x++)
         {
            if($events[$x]["evt_type"] == "apertura" && (int)$events[$x]["evt_enddat"])
               $_HASALIS = true;
            // if($events[$x]["evt_type"] == "prod" || $events[$x]["evt_type"] == "prodsericolor")
            if($events[$x]["evt_type"] == "prod")
            {
               if(!(int)$events[$x]["overrideembalaje_act"])
                  $_HASPROD = true;
            }
            if($events[$x]["evt_type"] == "pause" && !(int)$events[$x]["evt_enddat"])
               $_HASPAUSE_PENDING = true;
         }
         // echo('halalis ('.$_HASALIS. ') hasprod ('.$_HASPROD.') hausepause ('.$_HASPAUSE_PENDING.') apertura ('.$_APERTURA.')');
         ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <?php
            if($_ISSELLADORA)
            {
               $sql = " select *
                        from prod_worker_ot_events
                        where
                        evt_prod_worker_otid = {$hasopenot["id"]} and
                        evt_status           = 1 and
                        evt_type             IN ('prod', 'prodsericolor') and
                        overrideembalaje_act = 1
                        order by evt_crtdat desc";
               $sellevents = $CON->select($sql);
               if($sellevents === false || !count($sellevents))
               {  ?>
                  <td style="padding-right:2px">
                     <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&overrideembalaje=1&refid=';">
                        <nobr><i class="fa fa-fw fa-plus" style="color:white;"></i> Embalaje&nbsp;</nobr>
                     </div>
                  </td>
                  <?php
               }
            }
            if(!$_HASPAUSE_PENDING && $_HASALIS && $_ISREBOBINADORA)
            {  ?>
               <td style="padding-right:2px">
                  <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=reboprod&refid=';">
                     <nobr><i class="fa fa-fw fa-gear" style="color:white;"></i> Rebobinado&nbsp;</nobr>
                  </div>
               </td>
               <?php
            }

            if(!$_HASPAUSE_PENDING && $_HASALIS && !$_HASPROD && !(int)$agenda["req_rebo_cc_genrefid"])
            {
               if($_ISREBOBINADORA)
               {  ?>
                  <td width="20%" style="padding-right:2px">
                     <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&refid=';">
                        <i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar producción&nbsp;
                     </div>
                  </td>
                  <?php
               }
               else
               {  ?>
                  <td width="20%" style="padding-right:2px">
                     <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&refid=';">
                        <i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar producción&nbsp;
                     </div>
                  </td>
                  <?php
               }
            }
            if(!$_HASPAUSE_PENDING && $_APERTURA)
            {
               if($_ISREBOBINADORA)
               {  ?>
                  <td width="20%" style="padding-right:2px;padding-left:2px">
                     <div class="btnblue" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=materiales&refid=';">
                        <i class="fa fa-fw fa-cube" style="color:white;"></i> Utilizar materiales&nbsp;
                     </div>
                  </td>
                  <?php
               }
               else
               {  ?>
                  <td width="20%" style="padding-right:2px;padding-left:2px">
                     <div class="btnblue" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=materiales&refid=';">
                        <i class="fa fa-fw fa-cube" style="color:white;"></i> Utilizar materiales&nbsp;
                     </div>
                  </td>
                  <?php
               }
            }
            if(!$_HASPAUSE_PENDING && !$_HASALIS)
            {
               if(!$_APERTURA)
               {
                  $_SHOWDIRECT_ALISFORM = true;
                  /*
                  ?>
                  <td width="20%" style="padding-right:2px;padding-left:2px">
                     <div class="btnorange" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=apertura&agid=<?=$_REQUEST["agid"]?>&refid=';">
                        <i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar Alistamiento.&nbsp;
                     </div>
                  </td>
                  <?php
                  */
               }
            }
            if(!$_HASPAUSE_PENDING && $_APERTURA)
            {  ?>
               <td width="20%" style="padding-right:2px;padding-left:2px">
                  <div class="btnred" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=mantencion&refid=';">
                     <i class="fa fa-fw fa-wrench" style="color:white;"></i> Registrar mantención&nbsp;
                  </div>
               </td>
               <?php
            }
            if(!$_HASPAUSE_PENDING && $_APERTURA)
            {  ?>
               <td width="20%" style="padding-left:2px">
                  <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=pause&refid=';">
                     <i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;
                  </div>
               </td>
               <?php
            }
            if(!$_HASPAUSE_PENDING && $_APERTURA)
            {  ?>
               <td width="20%" style="padding-left:2px">
                  <div class="btnorange" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=consumo&refid=';">
                     <i class="fa fa-fw fa-cube" style="color:white;"></i> Consumo&nbsp;
                  </div>
               </td>
               <?php
            }
            ?>
         </tr>
         </table>
         <?php
         if(count($events) && $events != false)
         {  ?>
            <div style="clear:both;height:10px"></div>
            <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tr>
               <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                  <table border="0" width="100%" cellpadding="6" cellspacing="0">
                  <colgroup>
                     <col width="120">
                     <col width="160">
                     <col width="160">
                     <col width="80">
                     <col width="120">
                     <col>
                     <col width="120">
                  </colgroup>
                  <tr>
                     <td class="tdheader">Evento</td>
                     <td class="tdheader">Inicio</td>
                     <td class="tdheader">Termino</td>
                     <td class="tdheader" align="center">Tiempo</td>
                     <td class="tdheader" align="center">Cantidad</td>
                     <td class="tdheader">Comentarios</td>
                     <td class="tdheader" align="center">Opciones</td>
                  </tr>
                  <?php
                  for($x = 0; $x < count($events) && $events != false; $x++)
                  {
                     $evttype = "";
                     if($events[$x]["evt_type"] == "prod" || $events[$x]["evt_type"] == "prodsericolor")
                     {
                        $evttype = "Producción";
                        if((int)$events[$x]["overrideembalaje_act"])
                           $evttype = "Prod. Embalaje";
                     }
                     elseif($events[$x]["evt_type"] == "apertura")
                        $evttype = "Alistamiento";
                     elseif($events[$x]["evt_type"] == "mantencion")
                        $evttype = "Mantención";
                     elseif($events[$x]["evt_type"] == "pause")
                        $evttype = "Pausa";

                     //----------------------------------------------------------------------------------
                     $endtime = $events[$x]["evt_enddat"];
                     if(!(int)$endtime)
                        $endtime = time();
                        
                     $time_diff  = $endtime - $events[$x]["evt_crtdat"];
                     $time_diffx = $time_diff / 60;
                     $hours_diff = (int)($time_diffx / 60);
                     $min_diff   = (int)($time_diffx - ($hours_diff * 60));

                     if(!$_HASPAUSE_PENDING || ($_HASPAUSE_PENDING) && $evttype == "Pausa" && !$events[$x]["evt_enddat"])
                     {  ?>
                        <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                           <td class="tdnrm"><?=$evttype?></td>
                           <td class="tdnrm"><?=date("d.m.Y H:i:s", $events[$x]["evt_crtdat"])?></td>
                           <td class="tdnrm"><?if((int)$events[$x]["evt_enddat"]) echo date("d.m.Y H:i:s", $events[$x]["evt_enddat"])?></td>
                           <td class="tdnrm" align="center"><?="{$hours_diff}h {$min_diff}m"?>&nbsp;</td>
                           <td class="tdnrm" align="center">
                              <?php
                              if($events[$x]["evt_metrotype"] == "")
                              {
                                 if($events[$x]["evt_amount"] > 0.00)
                                    echo printPrice($events[$x]["evt_amount"]);
                              }
                              else
                              {
                                 if($events[$x]["evt_metrotype"] == "metros_maquina")
                                    echo printPrice($events[$x]["evt_amount_metros_maquina"]);
                                 elseif($events[$x]["evt_metrotype"] == "metros_lineales")
                                    echo printPrice($events[$x]["evt_amount_metros_lineales"]);
                              }
                              ?>
                              &nbsp;
                           </td>
                           <td class="tdnrm">
                              <?php
                              if($events[$x]["evt_type"] == "prodsericolor")
                              {
                                 echo "<b><u>Color: {$events[$x]["prod_seri_color"]}</u></b>";
                                 if($events[$x]["evt_comments"] != "")
                                    echo "<br>";
                              }
                              if($events[$x]["evt_type"] == "apertura")
                              {
                                 $sql = " select *
                                          from prod_medidas t1
                                          where
                                          t1.id = {$events[$x]["evt_medida_fromid"]}";
                                 $medfrom = $CON->select($sql);
                                 $medfrom = $medfrom[0];

                                 $sql = " select *
                                          from prod_medidas t1
                                          where
                                          t1.id = {$events[$x]["evt_medida_toid"]}";
                                 $medto = $CON->select($sql);
                                 $medto = $medto[0];

                                 if($medfrom["id"] && $medto["id"])
                                 {  ?>
                                    De <?=$medfrom["med_name"]?> a <?=$medto["med_name"]?>
                                    <?php
                                    if($events[$x]["evt_comments"] != "")
                                       echo "<br>";
                                 }
                              }
                              ?>
                              <?=$events[$x]["evt_comments"]?>&nbsp;
                           </td>
                           <td class="tdnrm">
                              <?php
                              if((int)$events[$x]["overrideembalaje_selladora_evtrefid"])
                              {
                                 echo "[Contabilizado]";
                              }
                              else
                              {
                                 if($events[$x]["evt_type"] == "prodsericolor" && !(int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btnorange"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&agid=<?=$_REQUEST["agid"]?>&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-stop" style="color:white;"></i>Terminar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "prodsericolor" && (int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btngrey"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&agid=<?=$_REQUEST["agid"]?>&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "prod" && !(int)$events[$x]["evt_enddat"])
                                 {
                                    $addprm = "";
                                    if((int)$events[$x]["overrideembalaje_act"])
                                       $addprm = "&overrideembalaje=1";
                                    ?>
                                    <div class="btnorange"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&refid=<?=$events[$x]["id"]?><?=$addprm?>'">
                                       <i class="fa fa-fw fa-stop" style="color:white;"></i>Terminar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "prod" && (int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btngrey"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "apertura" && !(int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btnorange"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=apertura&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "apertura" && (int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btngrey"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=apertura&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "mantencion" && !(int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btnorange"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=mantencion&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "mantencion" && (int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btngrey"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=mantencion&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                    </div>
                                    <?php
                                 }
                                 if($events[$x]["evt_type"] == "pause" && !(int)$events[$x]["evt_enddat"])
                                 {  ?>
                                    <div class="btnorange"
                                    onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=pause&refid=<?=$events[$x]["id"]?>'">
                                       <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                    </div>
                                    <?php
                                 }
                              }
                              ?>
                           </td>
                        </tr>
                        <?php
                     }
                     if(!(int)$events[$x]["evt_enddat"])
                        $_CANTERM = false;
                  }
                  ?>
                  </table>
               </td>
            </tr>
            </table>
            <?php
         }
      }
      if($_CANTERM)
      {  ?>
         <script language="JavaScript">
            $(document).ready(function()
            {
               $('#otterm_btn').css('visibility','visible');
            });
         </script>
         <?php
      }
   }
   else
   {  ?>
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
            <b class="msg_save_err">
               <i class="fa fa-fw fa-exclamation-triangle"></i>
               No existen OT's en curso
            </b>
         </td>
      </tr>
      </table>
      <?php
      if((int)$_REQUEST["askcloseturno"])
      {  ?>
         <div id="idx_diag_askclose" style="position:fixed;top:0px;left:0px;background-color:rgba(0,0,0,0.3);height:100%;width:100%">
            <div style="text-align:center;position:absolute;left: calc(50% - 100px);top: calc(50% - 100px);width:500px;height:200px;background-color:#FFFFFF">
               <div style="height:50px"></div>
               <b style="font-size:22px">Quieres cerrar el turno?</b>
               <div style="height:50px"></div>

               <div class="btnred" style="width:200px;float:left;margin-left:20px"
               onclick="$('#idx_diag_askclose').fadeOut(600);">
                  <i class="fa fa-fw fa-times" style="color:white;"></i> NO&nbsp;
               </div>

               <div class="btngreen" style="width:200px;float:left;margin-left:20px"
               onclick="location.href='/prodwrk.php?mid=0&autoclose=1'">
                  <i class="fa fa-fw fa-check" style="color:white;"></i> SI&nbsp;
               </div>
            </div>
         </div>
         <?php
      }
   }
}
//----------------------------------------------------------------------------------
function getProdOpenWorkerOTId($CON, $wrkid, $plantaid, $idagid)
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
                 t1.wok_ag_id   = {$idagid} and
                 t1.wok_status  = 1";
      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];
  
      return $hasopenot;
   }
   return false;
}

function EnvioCorreo($CON, $req_codigo_cierre, $id)
{

   $sql     = " select * from parametros where tabla = 'ASUNTO' and codigo = '{$req_codigo_cierre}' ";
   $asunto  = $CON->select($sql);
   $asunto  = $asunto[0];

   $sql      = " select * from prod_worker_embalajes where evt_prod_worker_otid = {$id} ";
   $embalaje = $CON->select($sql);
   $embalaje = $embalaje[0];

   $sql      = " select o1.*
                      , c1.cust_name
                  from orders o1
                    inner join customer c1 on c1.id = o1.req_cust_id
                  where o1.id = {$embalaje["evt_reqid"]} ";
   $agenda   = $CON->select($sql);
   $agenda   = $agenda[0];

   
   $sw_mixto = 1;
   if( (int)$agenda["req_id_cc_m1"] || (int)$agenda["req_id_cc_m2"] || (int)$agenda["req_id_cc_m3"] )
   {
      $sw_mixto = 2;

      $sql = "select * from orders where id = {$agenda["req_id_cc_m1"]}";
      $mixto1 = $CON->select($sql);
      $mixto1 = $mixto1[0];

      $sql = "select * from orders where id = {$agenda["req_id_cc_m2"]}";
      $mixto2 = $CON->select($sql);
      $mixto2 = $mixto2[0];
      
      $sql = "select * from orders where id = {$agenda["req_id_cc_m3"]}";
      $mixto3 = $CON->select($sql);
      $mixto3 = $mixto3[0];

      $sql   = " select * from embalaje_mixto where id_embalaje = {$embalaje["id"]} ";
      $mixto = $CON->select($sql);
      $mixto = $mixto[0];

      $sql = " select dm.*
                    , i.item_number_prod 
                    , (select sum((req_embalaje_bolsas_por_caja_amt * req_embalaje_cajas_completas_amt)
                                    + req_embalaje_caja_final + req_embalaje_bolsas_sobrantes_amt) 
                           from prod_worker_embalajes 
                        where evt_reqid = o.id) as total_bolsas_ingresadas_cc
                from detalle_mixto dm
                    LEFT OUTER JOIN item i on i.id = dm.id_item
                    LEFT OUTER JOIN orders o on dm.req_number = o.req_number
               where id_mixto = {$mixto["id"]} ";
      $detalle_mixto = $CON->select($sql);
   }
   else
   {
      $sql = "select sum((req_embalaje_bolsas_por_caja_amt * req_embalaje_cajas_completas_amt)
                + req_embalaje_caja_final + req_embalaje_bolsas_sobrantes_amt) as total_bolsas_ingresadas_cc
            from prod_worker_embalajes 
              where evt_reqid = {$agenda["id"]} ";
      $total_cc_0 = $CON->select($sql);
      $total_cc_0 = $total_cc_0[0];
   }
   
   $sql = " select t2.id,  concat(t2.user_firstname,' ',user_lastname) as Vendedor 
               from orders t1
            inner join user t2 on t1.req_userid_seller = t2.id
               where t1.id = {$agenda["id"]} ";
   $vendedor = $CON->select($sql);
   $vendedor = $vendedor[0];

   $sql = " select t1.id, concat(t1.user_firstname,' ',t1.user_lastname) embalador, t1.user_mail
                     from user t1
                  where ID = {$_SESSION["user_id"]} ";
   $embalador = $CON->select($sql);
   $embalador = $embalador[0];

   $sql = " select concat(t2.user_firstname,' ',t2.user_lastname) as supervisor
                        ,t2.user_rut       as rutsupervisor
                        ,t2.id
                     from user t2 
                     where  t2.id = {$embalaje["req_id_supervisor"]} ";
   $supervisor = $CON->select($sql);
   $supervisor = $supervisor[0];
  
   $totalprod = ((int)$embalaje["req_embalaje_bolsas_por_caja_amt"] * (int)$embalaje["req_embalaje_cajas_completas_amt"]) + 
                 (int)$embalaje["req_embalaje_bolsas_sobrantes_amt"] +
                 (int)$embalaje["req_embalaje_caja_final"] +
                 (int)$embalaje["req_embalaje_showroom"]; 

   if((int)count($asunto)) 
   {
         $sql = " select distinct t1.id, t1.user_firstname, t1.user_lastname, t1.user_mail from user t1
                          inner join user_group t2 on t2.user_id = t1.id
                  where t1.user_status > 0 and user_st_email_cierre = 1 and t1.user_mail like '%@%' 
                    and (t2.group_id != 18 or t1.id = {$vendedor["id"]}) " ;
         $users = $CON->select($sql);

         $attachments = "";

         if(count($users) && $users != false)
         {
            foreach($users AS $user)
            {
               
               $title   = $asunto["descripcion"] . " - " . $agenda["cust_name"]. " - ". ' ('.$agenda["req_number"].')' ;
               
               if( (int)count($mixto1) )
                  $title  .= '- ('.$mixto1["req_number"].')' ;

               if( (int)count($mixto2) )
                  $title   .= '- ('.$mixto2["req_number"].')' ;

               if( (int)count($mixto3) )
                  $title   .= '- ('.$mixto3["req_number"].')' ;

               $operaciones = $agenda["req_number"];
               if($mixto1["req_number"] != "")
                  $operaciones .= " / ".$mixto1["req_number"];
               if($mixto2["req_number"] != "")
                  $operaciones .= " / ".$mixto2["req_number"];
               if($mixto3["req_number"] != "")
                  $operaciones .= " / ".$mixto3["req_number"];
             
               $body    = "Estimado(a) {$user["user_firstname"]} {$user["user_lastname"]},<br><br>
                           Se adjunta información del cierre de producción:
                           <br><br>
                           <table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                                 <colgroup>
                                    <col>
                                    <col width='500'>
                                 </colgroup>
                                 <tr>
                                    <td class='tdheader'>Cliente</td>
                                    <td class='tdheader'>{$agenda["cust_name"]}</td>
                                 </tr>
                                 <tr>
                                    <td class='tdheader'>C.C</td>
                                    <td class='tdheader'>{$operaciones}</td>
                                 </tr>
                                 <tr>
                                    <td class='tdheader'>Vendedor(a)</td>
                                    <td class='tdheader'>{$vendedor["Vendedor"]}</td>
                                 </tr>
                                 <tr>
                                    <td class='tdheader'>Embalador(a)</td>
                                    <td class='tdheader'>{$embalador["embalador"]}</td>
                                 </tr>
                                 <tr>
                                    <td class='tdheader'>Quien Recibe</td>
                                    <td class='tdheader'>{$supervisor["supervisor"]}</td>
                                 </tr>
                           </table>
                           <br><br>" ;
               if($sw_mixto == 1)
               {
                  $body    .= "<table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                                    <colgroup>
                                       <col>
                                       <col>
                                    </colgroup>
                                    <tr>
                                       <td class='tdheader' align='center'>Total Embalado por C.C.</td>
                                       <td class='tdheader' align='center'>{$total_cc_0["total_bolsas_ingresadas_cc"]}</td>
                                    </tr> 
                              </table>
                              <br>" ;
                  
                  $body    .= "<table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                                    <colgroup>
                                       <col>
                                       <col>
                                    </colgroup>
                                    <tr>
                                       <td class='tdheader' align='center'>Medidas</td>
                                       <td class='tdheader' align='center'>Cantidades embalaje</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Medida de caja</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_medidas_caja"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Cantidad de Bolsas por Caja</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_bolsas_por_caja_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Cantidad de cajas por pallets</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_cajas_por_pallet_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Cantidad de pallets completos</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_pallets_completos_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Numero de cajas en pallet incompleto</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_palletcajas_incompletos_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Cantidad total de cajas completas</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_cajas_completas_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Caja final (completa pedido)</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_caja_final"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Bolsas sobrantes</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_bolsas_sobrantes_amt"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Bolsas a Showroom</td>
                                       <td class='tdleft'>{$embalaje["req_embalaje_showroom"]}</td>
                                    </tr>
                                    <tr>
                                       <td class='tdleft'>Total ingresado</td>
                                       <td class='tdleft'>{$totalprod}</td>
                                    </tr>
                              </table>
                              <br><br> ";
               }
               else
               {
                  $body    .= "<table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                                 <colgroup>
                                    <col>
                                    <col>
                                    <col>
                                    <col>
                                    <col>
                                    <col>
                                    <col>
                                    <col>
                                 </colgroup> 
                                 <tr>
                                    <td class='tdheader' colspan='2' align='center' >Detalle</td>
                                    <td class='tdheader' colspan='5' align='center'>Cantidad de Bolsas</td>
                                 </tr>
                                 <tr>
                                    <td class='tdheader' align='center'>C.C.</td>
                                    <td class='tdheader' align='center'>Producto</td>
                                    <td class='tdheader' align='center'>Cantidad de Caja </td>
                                    <td class='tdheader' align='center'>Unidad por Caja</td>
                                    <td class='tdheader' align='center'>Unidad por Caja Final</td>
                                    <td class='tdheader' align='center'>Unidad de Sobrante</td>
                                    <td class='tdheader' align='center'>Embalado x CC</td>
                                    <td class='tdheader' align='center'>Total Unidades</td>
                                 </tr>
                              ";

                  // for($x = 0; $x < count($detalle_mixto) && $detalle_mixto != false; $x++)
                  $totalprod1 = 0;
                  foreach($detalle_mixto AS $dm)
                  {
                     $totalprod1 = $totalprod1 + $dm["total_bolsas"];
                     $body    .= " <tr>
                                      <td class='tdleft'><nobr>{$dm["req_number"]}</nobr></td>
                                      <td class='tdleft' align='center'>{$dm["item_number_prod"]}</td>
                                      <td class='tdleft' align='center'>{$dm["total_cajas"]}</td>
                                      <td class='tdleft' align='center'>{$dm["bolsas_por_caja"]}</td>
                                      <td class='tdleft' align='center'>{$dm["bolsas_caja_final"]}</td>
                                      <td class='tdleft' align='center'>{$dm["bolsas_sobrante"]}</td>
                                      <td class='tdleft' align='center'>{$dm["total_bolsas_ingresadas_cc"]}</td>
                                      <td class='tdleft' align='center'>{$dm["total_bolsas"]}</td>
                                   </tr>";
                  }
                  $body    .= "</table>
                               <table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                                 <colgroup>
                                       <col>
                                       <col>
                                       <col>
                                       <col>
                                       <col>
                                       <col>
                                       <col>
                                 </colgroup> 
                                 <tr>
                                    <td class='tdheader' colspan='6' align='center'>Total Bolsas</td>
                                    <td class='tdheader' align='center'>{$totalprod1}</td>
                                 </tr>
                               </table>
                               <br>";

               }
               $body    .= "<table border='1' class='content_table' cellpadding='2' cellspacing='0' width='50%'>
                              <colgroup>
                                   <col>
                                   <col>
                              </colgroup>
                              <tr>
                                  <td class='tdheader' colspan='2'  align='center'>Observación</td>
                              </tr>
                              <tr>
                                  <td class='tdleft' colspan='2' style='width:100%;height:60px'>{$embalaje["req_observacion"]}</td>
                               </tr>
                            </table>
                            <br><br>
                           ";
               $body    .= "*Este correo es una notificación, por lo que no es necesario responderlo. ";               

               sendExternalMail($title, $body, $user["user_mail"], $user["user_firstname"]." ".$user["user_lastname"], "", "", $attachments);
               sendMensajes($CON, $title, $body,  $user["id"] );

            }      
         }
      }
}

function sendMensajes($CON, $asunto, $detalle,  $usrid )
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


function getCalculaPesoUnitario($CON, $hasopenot)
{
   $sql = " select distinct t0.*,
               t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
               t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
               t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
               t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
               t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
               fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
               fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
               fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
               t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
               t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4, 
               t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
               t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
               t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms,
               t1.req_operador_bastidoramt, t1.req_operador_mermaperc, t1.req_operador_tela_width,
               t1.req_solic_devprints_poltype, t1x.fab_manilla_length, t1.req_rebo_type,
               t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc, t1.req_rebo_cc_genrefid
                  from prod_agenda t0
                  INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
                  INNER JOIN orders t1             ON t0.ag_reqid = t1.id
                  LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
                  LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
                  LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
                  INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                  INNER JOIN item t2x              ON t1x.item_id = t2x.id
                  LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
                  LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
                  where
                  t0.id = {$hasopenot["wok_ag_id"]}";
   $agenda = $CON->select($sql);
   $agenda = $agenda[0];


   $sql = "select equipo_prod_isprinter_flexo
            ,equipo_prod_isprinter_seri
            ,win_equipoid
            from prod_worker_ot t1 
            inner join prod_agenda t2 ON t1.wok_ag_id = t2.id 
            inner join prod_worker_init t3 ON t1.wok_init_id = t3.id 
            inner join orders t9 ON t2.ag_reqid = t9.id 
            left outer join equipo t7 ON t3.win_equipoid = t7.id 
            left outer join equipo_type t8 ON t7.equipo_type_id = t8.id  
            where t9.id = {$agenda["ag_reqid"]}
            and t7.equipo_type_id in(7,11)";
   $maquina = $CON->select($sql);
   $maquina = $maquina[0]; 

   if((int)$agenda["item_prodcalc_fuelle_act"])
      $param_medida = (int)$agenda["fab_med_width"] + (int)$agenda["fab_med_fuelle"];
   else
      $param_medida = (int)$agenda["fab_med_width"];


   $sql = " select *
            from equipo_params
            where
            param_equipo_id = {$maquina["win_equipoid"]} and
            param_medida    >= {$param_medida}
            order by param_medida asc
            LIMIT 0,1";
   $equipo_params = $CON->select($sql);
   $equipo_params = $equipo_params[0];


   if((int)$maquina["equipo_prod_isprinter_seri"])
   {
      $corte_m2 = (float)$equipo_params["param_corte"];
      $corte_z  = (int)$equipo_params["param_z"];
   }
   elseif((int)$maquina["equipo_prod_isprinter_flexo"])
   {
      if($agenda["req_solic_devprints_poltype"] == "pol284")
      {
         $corte_m2 = (float)$equipo_params["param_poly28"];
         $corte_z  = (int)$equipo_params["param_z"];
      }
   elseif($agenda["req_solic_devprints_poltype"] == "pol170")
      {
         $corte_m2 = (float)$equipo_params["param_poly17"];
         $corte_z  = (int)$equipo_params["param_z"];
      }
   }
   elseif($_ISSELLADORA)
   {
      $sql = " select t4.*, t5.mant_title, t7.req_number, t1.prd_number, t8.cust_name,
                  t2x.item_number_prod, t2x.item_title, t5x.pause_name, t5x.pause_code,
                  t5.mant_code, t2.ag_equipotype_id, t2.ag_prdid, t1x.item_amount,
                  t6.win_equipoid
               from prod_header t1
               INNER JOIN prod_agenda t2           ON t1.id = t2.ag_prdid
               INNER JOIN prod_worker_ot t3        ON t3.wok_ag_id = t2.id
               INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
               LEFT OUTER JOIN equipo_manttype t5  ON t4.evt_equipo_mantid = t5.id
               INNER JOIN prod_worker_init t6      ON t3.wok_init_id = t6.id
               INNER JOIN orders t7                ON t2.ag_reqid = t7.id
               LEFT OUTER JOIN customer t8         ON t7.req_cust_id = t8.id
               INNER JOIN orders_items t1x         ON t7.id = t1x.req_id
               INNER JOIN item t2x                 ON t1x.item_id = t2x.id
               LEFT OUTER JOIN prod_pause_types t5x ON t4.evt_pause_id = t5x.id
               INNER JOIN equipo t6x               ON t6.win_equipoid = t6x.id
               where
               t1.prd_reqid   = {$hasopenot["prd_reqid"]} and
               t1.prd_status  = 2 and
               t2.ag_status   > 0 and
               t3.wok_status  > 0 and
               t4.evt_status  > 0 and
               t6.win_status  > 0 and
               t4.evt_type    = 'prod' and
               t4.evt_amount  > 0 and
               (
                  t6x.equipo_prod_isprinter_seri   = 1 or
                  t6x.equipo_prod_isprinter_flexo  = 1
               )
               order by t4.evt_crtdat desc
               LIMIT 0,1";
      $lastprodevent = $CON->select($sql);
      $lastprodevent = $lastprodevent[0];

      $sql = " select *
               from equipo_params
               where
               param_equipo_id = {$lastprodevent["win_equipoid"]} and
               param_medida    >= {$param_medida}
               order by param_medida asc
               LIMIT 0,1";
      $equipo_params = $CON->select($sql);
      $equipo_params = $equipo_params[0];

      if($agenda["fab_printtype"] == "SERI")
      {
         $corte_m2 = (float)$equipo_params["param_corte"];
         $corte_z  = (int)$equipo_params["param_z"];
      }
      else
      {
         if($agenda["req_solic_devprints_poltype"] == "pol284")
         {
            $corte_m2 = (float)$equipo_params["param_poly28"];
            $corte_z  = (int)$equipo_params["param_z"];
         }
         elseif($agenda["req_solic_devprints_poltype"] == "pol170")
         {
            $corte_m2 = (float)$equipo_params["param_poly17"];
            $corte_z  = (int)$equipo_params["param_z"];
         }
      }
   }

   $sql = "select i.id
            ,i.item_number_prod
            ,i.item_title
            ,tc.id
            ,tc.com_name
            ,tciv.val_id
            ,t3.add_name
            ,item_sellprice_brutto
            from item i
            inner join tran_comments_item_vals tciv on tciv.item_id = i.id
            inner join tran_comments tc on tciv.com_id = tc.id
            inner join tran_comments_vals t3 on t3.id = tciv.val_id 
            where i.item_number_prod = '{$agenda["item_number_prod"]}'";

   $cat_productos = $CON->select($sql);
   foreach($cat_productos AS $cat_producto)
   {
      if($cat_producto[id]==31) // Ancho Bolsa 
         $ancho_bolsa = $cat_producto["add_name"];
      if($cat_producto[id]==35) // fuelle bolsa
         $medida_fuelle_bolsa = $cat_producto["add_name"];
      if($cat_producto[id]==37) // largo manilla
         $largomanilla = $cat_producto["add_name"];
      if($cat_producto[id]==39) // alto frente
         $alto_frente_bolsa = $cat_producto["add_name"];
      if($cat_producto[id]==40) // alto dorso
         $alto_dorso_bolsa = $cat_producto["add_name"];
      if($cat_producto[id]==41) // doblez superior
         $medida_doblez_superior = $cat_producto["add_name"];
   }
   $ancho_bobina = $alto_frente_bolsa + $alto_dorso_bolsa + ($medida_doblez_superior * 2) + $medida_fuelle_bolsa;
   // 

   $manilla       = 0;
   $ancho_manilla = 0;

   if((int)$agenda["fab_manilla_length"])
      $ancho_manilla = 6;

   if(strpos(strtoupper($hasopeninit["type_ant_title"])) == 'EMBALAJE' || strpos(strtoupper($hasopeninit["type_ant_title"])) == 'SELLADORA' )
   {
      $manilla = (($ancho_manilla / 100)*($agenda["fab_manilla_length"]/100)*($agenda["fab_mat_gramms"]/1000))*2;
   }

   // $peso_unitario = ($corte_m2) * ($data[$x]["ancho_bobina"]/100) * ($thispos["fab_mat_gramms"]/1000) + $manilla ;
   $peso_unitario = $corte_m2 * $ancho_bobina / 100 *  $agenda["fab_mat_gramms"] / 1000 + manilla;
   $hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
   return $peso_unitario;
}


if($_SHOWDIRECT_ALISFORM)
{
   $sql = " select id, concat(wrk_firstname,' ',wrk_lastname) as ayudantes FROM workers
            where  wrk_cargoid not in(4,10)
            and wrk_turno_state = 1
            and wrk_status > 0";
   $ayudantes = $CON->select($sql);

   $sql = " select t1.id, t1.med_name, t1.med_crtdat
            from prod_medidas t1
            where
            t1.med_status > 0
            order by t1.med_name ";
   $meds = $CON->select($sql);
   ?>
   <form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp"
   onsubmit="<?if((int)$hasopeninit["type_ant_inpmedidas_act"])
   {  ?>
      return checkform(new Array(this.evt_medida_fromid, this.evt_medida_toid));
      <?php
   }
   else
   {  ?>
      return true;
      <?php
   }
   ?>">
   <input type="hidden" name="mid" value="2">
   <input type="hidden" name="mode" value="aperturasave">
   <input type="hidden" name="submode" value="">
   <input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">

   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="150">
            <col>
         </colgroup>
         <tr>
            <td class="tdleft">Tipo</td>
            <td class="tdnrm"><?=$hasopeninit["type_ant_title"]?></td>
         </tr>
         <tr>
            <td class="tdleft">Maquina</td>
            <td class="tdnrm"><?=$hasopeninit["equipo_name"]?></td>
         </tr>
         <?php
         if((int)$hasopeninit["type_ant_inpmedidas_act"])
         {  ?>
            <tr>
               <td class="tdleft">De Medida *</td>
               <td class="tdnrm">
                  <select name="evt_medida_fromid" class="inptxt" style="width:400px;background-color:#FFFFFF">
                     <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                     <?php
                     foreach($meds AS $med)
                     {  ?>
                        <option value="<?=$med["id"]?>"
                        <?if($med["id"] == $refevent["evt_medida_fromid"]) echo "selected"?>>
                           <?=$med["med_name"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            <tr>
               <td class="tdleft">A Medida *</td>
               <td class="tdnrm">
                  <select name="evt_medida_toid" class="inptxt" style="width:400px;background-color:#FFFFFF">
                     <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                     <?php
                     foreach($meds AS $med)
                     {  ?>
                        <option value="<?=$med["id"]?>"
                        <?if($med["id"] == $refevent["evt_medida_toid"]) echo "selected"?>>
                           <?=$med["med_name"]?>
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
         <tr>
            <td class="tdleft">Ayudante</td>
            <td class="tdnrm">
               <select name="evt_idayudante" class="inptxt" style="width:400px;background-color:#FFFFFF">
                     <option value="0">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                     <?php
                     foreach($ayudantes AS $ayudante)
                     {  ?>
                        <option value="<?=$ayudante["id"]?>"
                        <?if($ayudante["id"] == $refevent["evt_idayudante"]) echo "selected"?>>
                           <?=$ayudante["ayudantes"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
            </td>
         </tr>
         <tr>
            <td class="tdleft" valign="top">Comentarios</td>
            <td class="tdnrm">
               <textarea class="inptxt" name="evt_comments" id="evt_comments"
               style="width:100%;height:60px"><?=stripslashes($refevent["evt_comments"])?></textarea>
            </td>
         </tr>
         </table>
         <div style="clear:both;height:10px"></div>
         <div class="btngreen" type="button" onclick="submitForm(document.xform_inp)">
            <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar alistamiento&nbsp;
         </div>
      </td>
   </tr>
   </table>
   </form>
   <script language="JavaScript">
      $(document).ready(function()
      {
         $('#evt_comments').focus();
      });
   </script>
   <?php
}
?>




