<?php
$_REQUEST["agid"] = (int)$_REQUEST["agid"];

//----------------------------------------------------------------------------------
if((int)$_REQUEST["setspecparams"])
{
   $hasopenot = getProdOpenWorkerOT($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);

   $_REQUEST["bastidoramt"] = (int)$_REQUEST["bastidoramt"];

   $sql = " update orders
            set
            req_operador_bastidoramt = {$_REQUEST["bastidoramt"]}
            where
            id = {$hasopenot["prd_reqid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------

$hasopenot   = getProdOpenWorkerOT($CON, $_SESSION["wrk_id"] , $_SESSION["user_planta_id"]);
$hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);

$_ISREBOBINADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "REBOBIN") !== false)
   $_ISREBOBINADORA = true;

//----------------------------------------------------------------------------------
$_ISSELLADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "SELLADORA") !== false)
   $_ISSELLADORA = true;

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
            if((int)$hasopeninit["type_createstock_act"])
            {  ?>
               <div class="btngreen" style="visibility:hidden" id="otterm_btn"
               onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.embalaje.termot.php?mid=<?=$_REQUEST["mid"]?>&ruid=<?=md5(microtime())?>', 'iframe', '800', '600', 'auto')">
                   <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT&nbsp;
               </div>
               <?php
            }
            else
            {  ?>
               <div class="btngreen" style="visibility:hidden" id="otterm_btn"
               onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=terminarot'">
                   <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT&nbsp;
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
   echo($hasopenot["id"].' '.$hasopeninit["id"])

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
                         t1x.fab_manilla_length, t2x.item_prodcalc_fuelle_act
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
            for($x = 1; $x <= 5; $x++)
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
                     t1.prd_status  = 2 and
                     t2.ag_status   > 0 and
                     (
                        t3.wok_status  = 2 or
                        t3.id = {$hasopenot["id"]}
                     ) and
                     t4.evt_status  > 0 and
                     t1.prd_plantaid   = {$ag_plantaid} and
                     t1.prd_reqid      = {$ag_reqid} and
                     t4.evt_type       = 'prodsericolor' and
                     t4.evt_amount     > 0 and
                     (t4.evt_amount - t4.prod_seri_converted_amt) > 0
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
                        (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_enddat)
                        VALUES
                        ({$hasopenot["id"]}, {$_MINIMUM_AMT}, {$currtme}, 1, 'prod',
                         'Generado por cantidades seri-multicolor.', {$currtme})";
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

         createEmbalajeProdStock($CON, $hasopenot["id"]);

         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&askcloseturno=1';
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
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>';
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
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>';
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
                        evt_comments   = '{$_REQUEST["evt_comments"]}',
                        evt_medida_fromid = {$_REQUEST["evt_medida_fromid"]},
                        evt_medida_toid = {$_REQUEST["evt_medida_toid"]},
                        evt_enddat     = {$currtme}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_comments = '{$_REQUEST["evt_comments"]}',
                        evt_medida_fromid = {$_REQUEST["evt_medida_fromid"]},
                        evt_medida_toid = {$_REQUEST["evt_medida_toid"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments,
                      evt_medida_fromid, evt_medida_toid)
                     VALUES
                     ({$hasopenot["id"]}, 0, {$currtme}, 1, 'apertura', '{$_REQUEST["evt_comments"]}',
                      {$_REQUEST["evt_medida_fromid"]}, {$_REQUEST["evt_medida_toid"]})";
            $CON->no_result($sql);
         }
         ?>
         <script language="JavaScript">
            location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>';
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
               $xamount          = (int)trim($_REQUEST["merma_amount_{$xtypeid}"]);
               $xcomment         = trim(addslashes($_REQUEST["merma_comments_{$xtypeid}"]));
               $evt_kgstounits   = (int)trim($_REQUEST["merma_kgs_{$xtypeid}"]);
               $evt_mtstounits   = (int)trim($_REQUEST["merma_mts_{$xtypeid}"]);

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
               $xamount          = (int)trim($_REQUEST["repair_amount_{$xtypeid}"]);
               $xcomment         = trim(addslashes($_REQUEST["repair_comments_{$xtypeid}"]));
               $evt_kgstounits   = (int)trim($_REQUEST["repair_kgs_{$xtypeid}"]);

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

            $sql = " insert into prod_worker_embalajes
                     (evt_prod_worker_otid, evt_reqid, evt_crtdat, req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
                      req_embalaje_cajas_por_pallet_amt, req_embalaje_pallets_completos_amt, req_embalaje_palletcajas_incompletos_amt,
                      req_embalaje_cajas_completas_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt)
                     VALUES
                     ({$hasopenot["id"]}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
                      {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]}, {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
                      {$_REQUEST["req_embalaje_pallets_completos_amt"]}, {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
                      {$_REQUEST["req_embalaje_cajas_completas_amt"]}, {$_REQUEST["req_embalaje_caja_final"]},
                      {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]})";
            $CON->no_result($sql);
         }
      }
      
      //----------------------------------------------------------------------------------
      if($_REQUEST["mode"] == "productionmulticolorsave")
      {
         $_REQUEST["prod_amount"]   = getPrice(trim($_REQUEST["prod_amount"]));
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
                      evt_comments, prod_bobina_kg, prod_seri_color)
                     VALUES
                     ({$hasopenot["id"]}, {$_REQUEST["prod_amount"]}, {$currtme}, 1, 'prodsericolor',
                      '{$_REQUEST["evt_comments"]}', {$_REQUEST["prod_bobina_kg"]}, '{$_REQUEST["prod_seri_color"]}')";
            $CON->no_result($sql);
         }

         if(!(int)$_REQUEST["reloadact"])
         {  ?>
            <script language="JavaScript">
               location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>';
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
         $_REQUEST["prod_amount"]         = getPrice(trim($_REQUEST["prod_amount"]));
         $_REQUEST["evt_comments"]        = trim(addslashes($_REQUEST["evt_comments"]));
         $_REQUEST["prod_bobina_kg"]      = getPrice(trim($_REQUEST["prod_bobina_kg"]),2);

         $_REQUEST["evt_amount_metros_maquina"]    = (int)getPrice(trim($_REQUEST["evt_amount_metros_maquina"]));
         $_REQUEST["evt_amount_metros_lineales"]   = (int)getPrice(trim($_REQUEST["evt_amount_metros_lineales"]));
         $currtme = time();
         if((int)$_REQUEST["refid"])
         {
            $sql_recalceventid = $_REQUEST["refid"];
            if($_REQUEST["submode"] == "end")
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount                 = {$_REQUEST["prod_amount"]},
                        prod_bobina_kg             = {$_REQUEST["prod_bobina_kg"]},
                        evt_comments               = '{$_REQUEST["evt_comments"]}',
                        evt_amount_metros_maquina  = {$_REQUEST["evt_amount_metros_maquina"]},
                        evt_amount_metros_lineales = {$_REQUEST["evt_amount_metros_lineales"]},
                        evt_metrotype              = '{$_REQUEST["sql_metrotype"]}',
                        evt_enddat                 = {$currtme}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " update prod_worker_ot_events
                        set
                        evt_amount                 = {$_REQUEST["prod_amount"]},
                        prod_bobina_kg             = {$_REQUEST["prod_bobina_kg"]},
                        evt_comments               = '{$_REQUEST["evt_comments"]}',
                        evt_amount_metros_maquina  = {$_REQUEST["evt_amount_metros_maquina"]},
                        evt_amount_metros_lineales = {$_REQUEST["evt_amount_metros_lineales"]},
                        evt_metrotype              = '{$_REQUEST["sql_metrotype"]}'
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);
            }
         }
         else
         {
            $sql = " insert into prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, prod_bobina_kg,
                      evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype)
                     VALUES
                     ({$hasopenot["id"]}, {$_REQUEST["prod_amount"]}, {$currtme}, 1, 'prod',
                      '{$_REQUEST["evt_comments"]}', {$_REQUEST["prod_bobina_kg"]}, {$_REQUEST["evt_amount_metros_maquina"]},
                      {$_REQUEST["evt_amount_metros_lineales"]}, '{$_REQUEST["evt_metrotype"]}')";
            $evres = $CON->no_result($sql);
            if($evres)
               $sql_recalceventid = mysql_insert_id();
         }

         if(!(int)$_REQUEST["reloadact"])
         {  ?>
            <script language="JavaScript">
               location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&recalctype=<?=$_REQUEST["sql_metrotype"]?>&recalcevtid=<?=$sql_recalceventid?>&mermaconvert=<?=(int)$_MERMACONVERT?>&mermamtsconvert=<?=$_MERMAMTSCONVERT?>&repairconvert=<?=(int)$_REPAIRCONVERT?>';
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
                      t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc
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

      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY
//       sendV2PordSupervisorNotify($CON, $hasopenot, $hasopeninit, $agenda);
      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY

      $printcolors   = "";
      $entrega_vals  = "";

      //----------------------------------------------------------------------------------
      for($xx = 1; $xx <= 5; $xx++)
      {
         if((int)$agenda["fab_print_colors_front_{$xx}"] || (int)$agenda["fab_print_colors_back_{$xx}"])
         {
            if((int)$agenda["fab_print_colors_front_{$xx}"] && !(int)$agenda["fab_print_colors_back_{$xx}"])
               $printcolors .= "Frente: {$agenda["fab_print_colordesc_{$xx}"]}, ";
            elseif(!(int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
               $printcolors .= "Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
            elseif((int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
               $printcolors .= "Frente/Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
         }
      }
      $printcolors = substr($printcolors, 0, -2);

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

      $_THIS_STATS   = getProdStats($CON, 0, $hasopenot["wok_ag_id"], $hasopenot["id"], 0, 0);
      $_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);

      ?>
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:15px">
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
            var xurl = '/prodwrk.php?mid=2&setspecparams=1';
            <?php
            if((int)$hasopeninit["equipo_prod_isprinter_seri"])
            {  ?>
               xurl = xurl + '&bastidoramt=' +$('#idx_bastidor_amt').val();
               <?php
            }
            ?>
            location.href = xurl;
         }
      </script>
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
            location.href = 'prodwrk.php?mid=2';
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
      {  ?>
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
      }
      ?>
      <div style="clear:both;height:10px"></div>
      <?php
      if($_REQUEST["mode"] == "")
      {  ?>
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
                     Fecha recepciï¿½n
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
                           <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar imï¿½gen&nbsp;
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
                              <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar imï¿½gen&nbsp;
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
      }
      if($_REQUEST["mode"] == "production")
         require_once("form.production.php");
      elseif($_REQUEST["mode"] == "apertura")
         require_once("form.apertura.php");
      elseif($_REQUEST["mode"] == "mantencion")
         require_once("form.mantencion.php");
      elseif($_REQUEST["mode"] == "pause")
         require_once("form.pause.php");
      elseif($_REQUEST["mode"] == "materiales")
         require_once("form.materiales.php");
      else
      {
         $_CANTERM            = true;
         $_HASALIS            = false;
         $_HASPROD            = false;
         $_HASPAUSE_PENDING   = false;
         $sql = " select *
                  from prod_worker_ot_events
                  where
                  evt_prod_worker_otid = {$hasopenot["id"]} and
                  evt_status           > 0
                  order by evt_crtdat desc";
         $events = $CON->select($sql);
         for($x = 0; $x < count($events) && $events != false; $x++)
         {
            if($events[$x]["evt_type"] == "apertura" && (int)$events[$x]["evt_enddat"])
               $_HASALIS = true;
            if($events[$x]["evt_type"] == "prod" || $events[$x]["evt_type"] == "prodsericolor")
               $_HASPROD = true;
            if($events[$x]["evt_type"] == "pause" && !(int)$events[$x]["evt_enddat"])
               $_HASPAUSE_PENDING = true;
         }
         ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <?php
            if(!$_HASPAUSE_PENDING && $_HASALIS && !$_HASPROD)
            {  ?>
               <td width="25%" style="padding-right:2px">
                  <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&refid=';">
                     <i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar producción&nbsp;
                  </div>
               </td>
               <?php
            }
            if(!$_HASPAUSE_PENDING && $_HASALIS)
            {  ?>
               <td width="25%" style="padding-right:2px;padding-left:2px">
                  <div class="btnblue" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=materiales&refid=';">
                     <i class="fa fa-fw fa-cube" style="color:white;"></i> Utilizar materiales&nbsp;
                  </div>
               </td>
               <?php
            }
            if(!$_HASPAUSE_PENDING && !$_HASALIS)
            {
               ?>
               <td width="25%" style="padding-right:2px;padding-left:2px">
                  <div class="btnorange" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=apertura&refid=';">
                     <i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar alistamiento&nbsp;
                  </div>
               </td>
               <?php
            }
            if(!$_HASPAUSE_PENDING && $_HASALIS)
            {  ?>
               <td width="25%" style="padding-right:2px;padding-left:2px">
                  <div class="btnred" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=mantencion&refid=';">
                     <i class="fa fa-fw fa-wrench" style="color:white;"></i> Registrar mantenciï¿½n&nbsp;
                  </div>
               </td>
               <?php
            }
            if($_HASALIS && !$_HASPAUSE_PENDING)
            {  ?>
               <td width="25%" style="padding-left:2px">
                  <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=pause&refid=';">
                     <i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;
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
                        $evttype = "Producciï¿½n";
                     elseif($events[$x]["evt_type"] == "apertura")
                        $evttype = "Alistamiento";
                     elseif($events[$x]["evt_type"] == "mantencion")
                        $evttype = "Mantenciï¿½n";
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
                              if($events[$x]["evt_type"] == "prodsericolor" && !(int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btnorange"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "prodsericolor" && (int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btngrey"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "prod" && !(int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btnorange"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "prod" && (int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btngrey"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=production&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "apertura" && !(int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btnorange"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=apertura&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "apertura" && (int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btngrey"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=apertura&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "mantencion" && !(int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btnorange"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=mantencion&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "mantencion" && (int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btngrey"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=mantencion&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar&nbsp;
                                 </div>
                                 <?php
                              }
                              if($events[$x]["evt_type"] == "pause" && !(int)$events[$x]["evt_enddat"])
                              {  ?>
                                 <div class="btnorange"
                                 onclick="location.href='prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=pause&refid=<?=$events[$x]["id"]?>'">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;
                                 </div>
                                 <?php
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
?>