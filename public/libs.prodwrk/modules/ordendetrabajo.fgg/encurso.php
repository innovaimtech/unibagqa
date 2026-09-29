<?php
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "initot")
{
   $_REQUEST["agid"]    = (int)$_REQUEST["agid"];
   $_REQUEST["initid"]  = (int)$_REQUEST["initid"];
   $currtme             = time();
   
   $sql = " insert into prod_worker_ot
            (wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status)
            VALUES
            ({$_REQUEST["agid"]}, {$_REQUEST["initid"]}, {$currtme}, 0, 1)";
   $xres = $CON->no_result($sql);
   if($xres)
   {  ?>
      <script language="JavaScript">
         location.href = 'prodwrk.php?mid=2';
      </script>
      <?php
      exit;
   }
}
?>
<font style="font-size:20px"><b>Iniciar nueva OT</b></font>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
      <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
      <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
      Iniciar nueva OT
   </td>
</tr>
</table>
<div style="clear:both;height:10px"></div>
<?php
//----------------------------------------------------------------------------------
$hasopenot   = getProdOpenWorkerOT($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
$hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
if((int)$hasopeninit["id"])
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="120">
            <col>
         </colgroup>
         <tr>
            <td class="tdleft">Fecha inicio</td>
            <td class="tdnrm"><?=date("d.m.Y", $hasopeninit["win_crtdat"])?></td>
         </tr>
         <tr>
            <td class="tdleft">Hora inicio</td>
            <td class="tdnrm"><?=date("H:i:s", $hasopeninit["win_crtdat"])?></td>
         </tr>
         <tr>
            <td class="tdleft">Tipo</td>
            <td class="tdnrm"><?=$hasopeninit["type_ant_title"]?></td>
         </tr>
         <tr>
            <td class="tdleft">Máquina</td>
            <td class="tdnrm"><?=$hasopeninit["equipo_name"]?></td>
         </tr>
         </table>
         <div style="clear:both;height:10px"></div>
      </td>
   </tr>
   </table>
   <div style="clear:both;height:10px"></div>
   <?php

   /*
   if((int)$hasopenot["id"])
   {  ?>
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
            <b class="msg_save_err">
               <i class="fa fa-fw fa-exclamation-triangle"></i>
               La OT <b><?=$hasopenot["prd_number"]?> se encuentra activada..
            </b>
         </td>
      </tr>
      </table>
      <?php
   }
   else
   */

   {
      //----------------------------------------------------------------------------------
      $sqldate_from  = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
      $sqldate_to    = mktime(23, 59, 59, date("m"), date("d"), date("Y"));
      $sql = " select distinct t0.*,
                      t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                      t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                      t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                      t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                      fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                      fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                      fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                      t3x.id 'prdid'
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
               t0.ag_status      > 0 and
               t0.ag_date_stamp  between {$sqldate_from} and {$sqldate_to} and
               t0.ag_plantaid    = {$hasopeninit["win_plantaid"]} and
               t0.ag_equipo_id   = {$hasopeninit["win_equipoid"]} 
               and t0.id in(select wok_ag_id from prod_worker_ot where wok_status = 1) 
               order by t0.ag_active desc, t0.ag_date_stamp asc, t0.ag_order, t0.id asc";
      

      $agendas = $CON->select($sql);

      if(count($agendas) && $agendas != false)
      {  ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
               <table border="0" width="100%" cellpadding="6" cellspacing="0">
               <colgroup>
                  <col width="60">
                  <col width="60">
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col width="110">
               </colgroup>
               <tr>
                  <td class="tdheader">OT</td>
                  <td class="tdheader">CC</td>
                  <td class="tdheader">Cliente</td>
                  <td class="tdheader">Código</td>
                  <td class="tdheader">Descripción</td>
                  <td class="tdheader">Tipo</td>
                  <td class="tdheader">Material</td>
                  <td class="tdheader" align="center">Cantidad</td>
                  <td class="tdheader" align="center">Producido</td>
                  <td class="tdheader" align="center">Saldo</td>
                  <td class="tdheader" align="center">Estado</td>
                  <td class="tdheader" align="center">Opciones</td>
               </tr>
               <?php
               for($x = 0; $x < count($agendas) && $agendas != false; $x++)
               {
                  $_OT_STATS = getProdStats($CON, $agendas[$x]["ag_prdid"], 0, 0, 0, 0, $agendas[$x]["ag_equipotype_id"]);
                  ?>
                  <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="tdleft"><?=$agendas[$x]["prd_number"]?>&nbsp;</td>
                     <td class="tdnrm"><nobr><?=$agendas[$x]["req_number"]?></nobr>&nbsp;</td>
                     <td class="tdnrm"><?=$agendas[$x]["cust_name"]?>&nbsp;</td>
                     <td class="tdnrm"><?=$agendas[$x]["item_number_prod"]?>&nbsp;</td>
                     <td class="tdnrm"><?=$agendas[$x]["item_title"]?>&nbsp;</td>
                     <td class="tdnrm"><?=$agendas[$x]["fab_printtype"]?>&nbsp;</td>
                     <td class="tdnrm"><?=$agendas[$x]["fab_type"]?>&nbsp;</td>
                     <td class="tdnrm" align="center"><?=printPrice($agendas[$x]["ag_amount"])?>&nbsp;</td>
                     <td class="tdnrm" align="center"><?=printPrice($_OT_STATS["_PROD_AMOUNT"])?></td>
                     <td class="tdnrm" align="center"><?=printPrice($agendas[$x]["item_amount"] - $_OT_STATS["_PROD_AMOUNT"])?></td>
                     <td class="tdnrm" align="center">
                        <?php
                        $btncls = "btngreen";
                        // if((int)$agendas[$x]["ag_active"])
                        {  ?>
                           <b class="msg_save_ok">Activado</b>
                           <?php
                        }
                        /*
                        else
                        {  ?>
                           <b class="msg_save_err">Inactivo</b>
                           <?php
                           $btncls = "btnorange";
                        }
                        */
                        ?>
                     </td>
                     <td class="tdnrm" align="center">
                        <div class="<?=$btncls?>"
                        <?echo($agendas[$x]["id"])?>
                        onclick="location.href='prodwrk.php?mid=2&exec=initot&agid=<?=$agendas[$x]["id"]?>&initid=<?=$hasopeninit["id"]?>'">
                           <nobr><i class="fa fa-fw fa-edit" style="color:white;"></i>Ingresar a OT&nbsp;</nobr>
                        </div>
                     </td>
                  </tr>
                  <?php
               }
               ?>
               </table>
            </td>
         </tr>
         </table>
         <?php
      }
      else
      {  ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
               <b class="msg_save_err">
                  <i class="fa fa-fw fa-exclamation-triangle"></i>
                  No existen OT's planificadas.
               </b>
            </td>
         </tr>
         </table>
         <?php
      }
   }
}
else
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <b class="msg_save_err">
            <i class="fa fa-fw fa-exclamation-triangle"></i>
            Falta inicio del puesto y máquina.
         </b>
      </td>
   </tr>
   </table>
   <?php
}
?>