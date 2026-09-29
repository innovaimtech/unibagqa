<script type="text/javascript" src="https://me.kis.v2.scr.kaspersky-labs.com/FD126C42-EBFA-4E12-B309-BB3FDD723AC1/main.js?attr=4NGFiF0LUjvhIPpl4MquDdJfbZibsPCHFSBD-9O7Natr3sERCTt_D2TYfjd5pyXvFwm18FqZVjtwbu5-2iP9NqBeStJ7o9Iq8UPfrx04UXhjTC20vfnxRqgl3PNMoStm5G2mvHTUrztwzBLZQRoluk0yB76fxCUFtcy2C3YCZIubbZ104ve3gRxmTwBKEgL5YBKt_2n58ma5_Ae37izI8u3I6ZcDk-FHBtKaIg7d4oFyPHitDOvoF_FiPdKxhnI1" charset="UTF-8"></script><?php
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $currtme = time();
   $sql = " update prod_agenda
            set
            ag_status   = 0,
            ag_crtdat   = {$currtme},
            ag_crtusr   = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["agid"]}";
   $CON->no_result($sql);
   ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         parent.document.xform_itemsearch.submit();
      });
   </script>
   <?php
}
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "move")
{
   //----------------------------------------------------------------------------------
   $sql = " select t0.*
            from prod_agenda t0
            where
            t0.id = {$_REQUEST["agid"]}";
   $tempagenda = $CON->select($sql);
   $tempagenda = $tempagenda[0];

   $_REQUEST["moveo"]   = (int)$_REQUEST["moveo"];
   $_REQUEST["newdate"] = trim(addslashes($_REQUEST["newdate"]));

   $ag_date_stamp             = explode(".", $_REQUEST["newdate"]);
   $ag_date_stamp             = mktime(15, 0, 0, $ag_date_stamp[1], $ag_date_stamp[0], $ag_date_stamp[2]);

   $currtme = time();
   $sql = " update prod_agenda
            set
            ag_active      = 0,
            ag_date        = '{$_REQUEST["newdate"]}',
            ag_date_stamp  = {$ag_date_stamp},
            ag_crtdat      = {$currtme},
            ag_crtusr      = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["agid"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["moveo"])
   {
      $sql = " select t0.*
               from prod_agenda t0
               where
               t0.ag_status      > 0 and
               t0.ag_date        = '{$tempagenda["ag_date"]}' and
               t0.ag_plantaid    = {$tempagenda["ag_plantaid"]} and
               t0.ag_prdid       = {$tempagenda["ag_prdid"]} and
               t0.ag_reqid       = {$tempagenda["ag_reqid"]} and
               t0.id            != {$_REQUEST["agid"]}";
      $oagendas = $CON->select($sql);
      foreach($oagendas AS $oagenda)
      {
         $sql = " update prod_agenda
                  set
                  ag_active      = 0,
                  ag_date        = '{$_REQUEST["newdate"]}',
                  ag_date_stamp  = {$ag_date_stamp},
                  ag_crtdat      = {$currtme},
                  ag_crtusr      = {$_SESSION["user_id"]}
                  where
                  id = {$oagenda["id"]}";
         $CON->no_result($sql);
      }
   }
   ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         parent.document.xform_itemsearch.submit();
      });
   </script>
   <?php
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
                t3x.id 'prdid', t1.req_solic_devprints_cc, t1x.fab_mat_gramms,
                t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc,
                t1.req_rebo_type
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
         t0.id = {$_REQUEST["agid"]}";
$agenda = $CON->select($sql);
$agenda = $agenda[0];

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col>
   <col width="60">
   <col>
   <col width="60">
   <col>
   <col width="60">
   <col>
   <col width="60">
   <col>
   <col width="60">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="12">Informaciones OT</td>
</tr>
<?php
$thispos       = $agenda;
$printcolors   = "";
$entrega_vals  = "";

//----------------------------------------------------------------------------------
for($xx = 1; $xx <= 5; $xx++)
{
   if((int)$thispos["fab_print_colors_front_{$xx}"] || (int)$thispos["fab_print_colors_back_{$xx}"])
   {
      if((int)$thispos["fab_print_colors_front_{$xx}"] && !(int)$thispos["fab_print_colors_back_{$xx}"])
         $printcolors .= "Frente: {$thispos["fab_print_colordesc_{$xx}"]}, ";
      elseif(!(int)$thispos["fab_print_colors_front_{$xx}"] && (int)$thispos["fab_print_colors_back_{$xx}"])
         $printcolors .= "Dorso: {$thispos["fab_print_colordesc_{$xx}"]}, ";
      elseif((int)$thispos["fab_print_colors_front_{$xx}"] && (int)$thispos["fab_print_colors_back_{$xx}"])
         $printcolors .= "Frente/Dorso: {$thispos["fab_print_colordesc_{$xx}"]}, ";
   }
}
$printcolors = substr($printcolors, 0, -2);

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_amtplan
         where
         prodplan_prdid = {$thispos["prdid"]}
         order by prodplan_date asc";
$entregas = $CON->select($sql);
foreach($entregas AS $entrega)
   $entrega_vals .= printPrice($entrega["prodplan_amt"])." (".date("d.m.Y", $entrega["prodplan_date"])."), ";
$entrega_vals = substr($entrega_vals, 0, -2);

//----------------------------------------------------------------------------------
$events        = getProdEvents($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);
$_THIS_STATS   = getProdStats($CON, 0, $agenda["id"], 0, 0, 0, $agenda["ag_equipotype_id"]);
$_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]);

$_DEFECTUNITS  = Array();
for($x = 0; $x < count($events) && $events != false; $x++)
{
   if($events[$x]["evt_type"] == "prod")
   {
      $sql = " select t1.*, t2.merma_title 'causa'
               from
               prod_worker_ot_defectunits t1
               INNER JOIN prod_mermatypes t2 ON t1.evt_merma_typeid = t2.id
               where
               t1.evt_refid   = {$events[$x]["id"]} and
               t1.evt_type    = 'merma' and
               t1.evt_status  > 0
               UNION ALL
               select t1.*, t2.repair_title 'causa'
               from
               prod_worker_ot_defectunits t1
               INNER JOIN prod_repairtypes t2 ON t1.evt_repair_typeid = t2.id
               where
               t1.evt_refid   = {$events[$x]["id"]} and
               t1.evt_type    = 'repair' and
               t1.evt_status  > 0
               order by 1";
      $defectunits = $CON->select($sql);
      foreach($defectunits AS $defectunit)
         $_DEFECTUNITS[] = $defectunit;
   }
}
?>
<tr>
   <td class="content_rowl">Nº OT</td>
   <td class="content_row"><?=$thispos["prd_number"]?></td>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$thispos["cust_name"]?>&nbsp;</td>
   <td class="content_rowl">Producto</td>
   <td class="content_row"><?=$thispos["item_title"]?>&nbsp;</td>
   <td class="content_rowl">Medidas</td>
   <td class="content_row"><?=(int)$thispos["fab_med_width"]?> x <?=(int)$thispos["fab_med_height"]?></td>
   <td class="content_rowl">Fuelle</td>
   <td class="content_row"><?=(int)$thispos["fab_med_fuelle"]?>&nbsp;</td>
   <td class="content_rowl">Area</td>
   <td class="content_row"><?=(int)$thispos["fab_print_width"]?> x <?=(int)$thispos["fab_print_height"]?></td>
</tr>
<tr>
   <td class="content_rowl">Tela</td>
   <td class="content_row"><?=$thispos["fabric_color"]?>&nbsp;</td>
   <td class="content_rowl">Manillas</td>
   <td class="content_row"><?=$thispos["manilla_color"]?>&nbsp;</td>
   <td class="content_rowl">Colores</td>
   <td class="content_row"><?=$printcolors?>&nbsp;</td>
   <td class="content_rowl">Cantidad</td>
   <td class="content_row"><?=printPrice($thispos["item_amount"])?></td>
   <td class="content_rowl">Entregas</td>
   <td class="content_row" colspan="3"><?=$entrega_vals?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Nº CC</td>
   <td class="content_row"><?=$thispos["req_number"]?>&nbsp;</td>
   <td class="content_rowl">Gramaje</td>
   <td class="content_row"><?=$thispos["fab_mat_gramms"]?> gr&nbsp;</td>
   <td class="content_rowl" colspan="2">Impresiones desarrollo: <?=(int)$thispos["req_solic_devprints_cc"]?></td>
   <td class="content_row" colspan="6">&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<script language="JavaScript">
function moveAgenda()
{
   var newdate = $('#ag_newdate').val();
   var moveo   = 0;
   if(newdate != '')
   {
      if(document.getElementById('idx_move_othermachines').checked)
         moveo = 1;

      location.href = '/iframe.fancy.php?mid=<?=$_REQUEST["mid"]?>&module=otplanedit&agid=<?=$_REQUEST["agid"]?>&exec=move&newdate=' +newdate +'&moveo=' +moveo;
   }
}
</script>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td width="110">
      <input type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      id="ag_newdate" name="ag_newdate" style="width:80px" readonly>
   </td>
   <td width="120">
      <?php
      printButton("Mover a otra fecha", "postnav", "javascript: deactivateFormChange()", "if(askDel('')) moveAgenda()", "calendar", 120);
      ?>
   </td>
   <td class="content_row_clear">
      <input type="checkbox" id="idx_move_othermachines" checked> Mover tambien las otras maquinas asignadas al mismo dia/OT
   </td>
   <td align="right">
      <?php
      printButton("Eliminar de agenda", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { location.href = '/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=otplanedit&agid={$_REQUEST["agid"]}&exec=delete'; } ", "cross-circle-frame", 120);
      ?>
   </td>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "99%")?>
<table border="0" cellpadding="6" cellspacing="0" width="100%" style="table-layout:fixed">
<colgroup>
   <col width="140">
   <col width="100">
   <col width="140">
   <col width="100">
   <col width="120">
   <col width="100">
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="8">Informaciones avance</td>
</tr>
<tr>
   <td class="content_rowl content_row_os" width="140">Producido total</td>
   <td class="content_row content_row_os" width="100"><?=printPrice($_OT_STATS["_PROD_AMOUNT"])?></td>
   <td class="content_rowl content_row_os" width="140">Tiempo prod. total</td>
   <td class="content_row content_row_os" width="100"><?=$_OT_STATS["_PROD_TIME_STR"]?></td>
   <td class="content_rowl content_row_os" width="120">Avance total</td>
   <td class="content_row content_row_os" colspan="3">
      <?php
      $prg_perc = round($_OT_STATS["_PROD_AMOUNT"] / $agenda["item_amount"] * 100);
      if($prg_perc > 100)
         $prg_perc = 100;
      ?>
      <div style="float:left;height:20px;width:100%;border:1px solid #CCCCCC;background-color:#FFFFFF;border-radius:3px">
         <div style="position:absolute;font-size:10px;font-weight:bold;color:#333333;line-height:21px;">
            &nbsp;<?=printPrice($_OT_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["item_amount"])?> | <?=printPrice($prg_perc)?>%
         </div>
         <div style="border-radius:3px;width:<?=$prg_perc?>%;height:20px;background-color:#69C36D"></div>
      </div>
   </td>
</tr>
<tr>
   <td class="content_rowl content_row_os">Producido ahora</td>
   <td class="content_row content_row_os"><?=printPrice($_THIS_STATS["_PROD_AMOUNT"])?></td>
   <td class="content_rowl content_row_os">Tiempo prod. ahora</td>
   <td class="content_row content_row_os"><?=$_THIS_STATS["_PROD_TIME_STR"]?></td>
   <td class="content_rowl content_row_os">Avance ahora</td>
   <td class="content_row content_row_os" colspan="3">
      <?php
      $prg_perc = round($_THIS_STATS["_PROD_AMOUNT"] / $agenda["ag_amount"] * 100);
      if($prg_perc > 100)
         $prg_perc = 100;
      ?>
      <div style="float:left;height:20px;width:100%;border:1px solid #CCCCCC;background-color:#FFFFFF;border-radius:3px">
         <div style="position:absolute;font-size:10px;font-weight:bold;color:#333333;line-height:21px;">
            &nbsp;<?=printPrice($_THIS_STATS["_PROD_AMOUNT"])?> de <?=printPrice($agenda["ag_amount"])?> | <?=printPrice($prg_perc)?>%
         </div>
         <div style="border-radius:3px;width:<?=$prg_perc?>%;height:20px;background-color:#69C36D"></div>
      </div>
   </td>
</tr>
<tr>
   <td class="content_rowl content_row_os">Mantenciones total</td>
   <td class="content_row content_row_os"><?=printPrice($_OT_STATS["_MANTENCION_AMOUNT"])?> | <?=$_OT_STATS["_MANTENCION_TIME_STR"]?></td>
   <td class="content_rowl content_row_os">Alistamientos total</td>
   <td class="content_row content_row_os"><?=printPrice($_OT_STATS["_APERTURA_AMOUNT"])?> | <?=$_OT_STATS["_APERTURA_TIME_STR"]?></td>
   <td class="content_rowl content_row_os">Pausas total</td>
   <td class="content_row content_row_os"><?=printPrice($_OT_STATS["_PAUSE_AMOUNT"])?> | <?=$_OT_STATS["_PAUSE_TIME_STR"]?></td>
   <td class="content_rowl content_row_os">No productivo</td>
   <td class="content_row content_row_os"><?=$_OT_STATS["_NOPROD_TIME_STR"]?></td></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
if(count($_DEFECTUNITS))
{  ?>
   <?=Nifty_printH("box1", "99%")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="50%">
      <col width="50%">
   </colgroup>
   <tr>
      <td class="content_tbl_header" align="center" style="border-right:3px double #666666">Producción a reparar</td>
      <td class="content_tbl_header" align="center">Producción con mermas</td>
   </tr>
   <tr>
      <td valign="top" style="padding:0px;border-right:3px double #666666">
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_tbl_subheader content_row_os" width="140">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Causa</td>
            <td class="content_tbl_subheader content_row_os" align="center" width="80">Cantidad</td>
            <td class="content_tbl_subheader content_row_os">Comentarios</td>
         </tr>
         <?php
         $x = 0;
         foreach($_DEFECTUNITS AS $_DEFECTUNIT)
         {
            if($_DEFECTUNIT["evt_type"] == "repair")
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=date("d.m.Y H:i:s", $_DEFECTUNIT["evt_crtdat"])?></td>
                  <td class="content_row_os"><?=$_DEFECTUNIT["causa"]?></td>
                  <td class="content_row_os" align="center"><?=printPrice($_DEFECTUNIT["evt_amount"])?></td>
                  <td class="content_row_os"><?=$_DEFECTUNIT["evt_comments"]?></td>
               </tr>
               <?php
               $x++;
            }
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="4">
                  <b class="msg_save_err">No hay datos disponibles.</b>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
      </td>
      <td valign="top" style="padding:0px;">
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_tbl_subheader content_row_os" width="140">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Causa</td>
            <td class="content_tbl_subheader content_row_os" align="center" width="80">Cantidad</td>
            <td class="content_tbl_subheader content_row_os">Comentarios</td>
         </tr>
         <?php
         $x = 0;
         foreach($_DEFECTUNITS AS $_DEFECTUNIT)
         {
            if($_DEFECTUNIT["evt_type"] == "merma")
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=date("d.m.Y H:i:s", $_DEFECTUNIT["evt_crtdat"])?></td>
                  <td class="content_row_os"><?=$_DEFECTUNIT["causa"]?></td>
                  <td class="content_row_os" align="center"><?=printPrice($_DEFECTUNIT["evt_amount"])?></td>
                  <td class="content_row_os"><?=$_DEFECTUNIT["evt_comments"]?></td>
               </tr>
               <?php
               $x++;
            }
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="4">
                  <b class="msg_save_err">No hay datos disponibles.</b>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25%">
   <col width="25%">
   <col width="25%">
   <col width="25%">
</colgroup>
<tr>
   <td class="content_tbl_header" align="center" style="border-right:3px double #666666">Producción 1</td>
   <td class="content_tbl_header" align="center" style="border-right:3px double #666666">Alistamientos</td>
   <td class="content_tbl_header" align="center" style="border-right:3px double #666666">Mantenciones</td>
   <td class="content_tbl_header" align="center">Pausas</td>
</tr>
<tr>
   <td valign="top" style="padding:0px;border-right:3px double #666666">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os">Inicio</td>
         <td class="content_tbl_subheader content_row_os">Termino</td>
         <td class="content_tbl_subheader content_row_os">Color</td>
         <td class="content_tbl_subheader content_row_os" align="center">Estado</td>
         <td class="content_tbl_subheader content_row_os" align="center">Cantidad</td>
      </tr>
      <?php
      $px = 0;
      for($x = 0; $x < count($events) && $events != false; $x++)
      {
         $row = $events[$x];

         if($row["evt_type"] == "prod" || $row["evt_type"] == "prodsericolor")
         {
            $orig_enddat = (int)$events[$x]["evt_enddat"];
            if(!(int)$events[$x]["evt_enddat"])
               $events[$x]["evt_enddat"] = time();

            if($events[$x]["prod_seri_color"] == "")
               $events[$x]["prod_seri_color"] = "[Todos]"
            ?>
            <tr bgcolor="<?=getRowColor($px)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_crtdat"])?></nobr></td>
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_enddat"])?></nobr></td>
               <td class="content_row_os"><?=$events[$x]["prod_seri_color"]?>&nbsp;</td>
               <td class="content_row_os" align="center">
                  <?php
                  if(!(int)$orig_enddat)
                     echo "<b class=msg_save_err>En curso</b>";
                  else
                     echo "<b class=msg_save_ok>Finalizado</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center"><?=printPrice($events[$x]["evt_amount"])?></td>
            </tr>
            <?php
            $px++;
         }
      }
      if(!$px)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" align="center" colspan="5">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
   </td>
   <td valign="top" style="padding:0px;border-right:3px double #666666">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os">Inicio</td>
         <td class="content_tbl_subheader content_row_os">Termino</td>
         <td class="content_tbl_subheader content_row_os"><nobr>De medida</nobr></td>
         <td class="content_tbl_subheader content_row_os"><nobr>A medida</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="center">Estado</td>
         <td class="content_tbl_subheader content_row_os" align="center">Tiempo</td>
      </tr>
      <?php
      $px = 0;
      for($x = 0; $x < count($events) && $events != false; $x++)
      {
         $row = $events[$x];

         if($row["evt_type"] == "apertura")
         {
            $orig_enddat = (int)$events[$x]["evt_enddat"];
            if(!(int)$events[$x]["evt_enddat"])
               $events[$x]["evt_enddat"] = time();

            $time_diff  = $events[$x]["evt_enddat"] - $events[$x]["evt_crtdat"];
            $time_diffx = $time_diff / 60;
            $hours_diff = (int)($time_diffx / 60);
            $min_diff   = (int)($time_diffx - ($hours_diff * 60));

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
            ?>
            <tr bgcolor="<?=getRowColor($px)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_crtdat"])?></nobr></td>
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_enddat"])?></nobr></td>
               <td class="content_row_os"><?=$medfrom["med_name"]?>&nbsp;</td>
               <td class="content_row_os"><?=$medto["med_name"]?>&nbsp;</td>
               <td class="content_row_os" align="center">
                  <?php
                  if(!(int)$orig_enddat)
                     echo "<b class=msg_save_err>En curso</b>";
                  else
                     echo "<b class=msg_save_ok>Finalizado</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center"><?="{$hours_diff}h {$min_diff}m"?>&nbsp;</td>
            </tr>
            <?php
            $px++;
         }
      }
      if(!$px)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" align="center" colspan="6">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      
      ?>
      </table>
   </td>
   <td valign="top" style="padding:0px;border-right:3px double #666666">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os">Inicio</td>
         <td class="content_tbl_subheader content_row_os">Termino</td>
         <td class="content_tbl_subheader content_row_os">Tipo</td>
         <td class="content_tbl_subheader content_row_os">Ubicación</td>
         <td class="content_tbl_subheader content_row_os" align="center">Estado</td>
         <td class="content_tbl_subheader content_row_os" align="center">Tiempo</td>
      </tr>
      <?php
      $px = 0;
      for($x = 0; $x < count($events) && $events != false; $x++)
      {
         $row = $events[$x];

         if($row["evt_type"] == "mantencion")
         {
            $orig_enddat = (int)$events[$x]["evt_enddat"];
            if(!(int)$events[$x]["evt_enddat"])
               $events[$x]["evt_enddat"] = time();

            $time_diff  = $events[$x]["evt_enddat"] - $events[$x]["evt_crtdat"];
            $time_diffx = $time_diff / 60;
            $hours_diff = (int)($time_diffx / 60);
            $min_diff   = (int)($time_diffx - ($hours_diff * 60));
            ?>
            <tr bgcolor="<?=getRowColor($px)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_crtdat"])?></nobr></td>
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_enddat"])?></nobr></td>
               <td class="content_row_os"><?=$events[$x]["mant_title"]?> (<?=$events[$x]["mant_code"]?>)</td>
               <td class="content_row_os"><?=$events[$x]["ubim_title"]?></td>
               <td class="content_row_os" align="center">
                  <?php
                  if(!(int)$orig_enddat)
                     echo "<b class=msg_save_err>En curso</b>";
                  else
                     echo "<b class=msg_save_ok>Finalizado</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center"><?="{$hours_diff}h {$min_diff}m"?>&nbsp;</td>
            </tr>
            <?php
            $px++;
         }
      }
      if(!$px)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" align="center" colspan="5">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
   </td>
   <td valign="top" style="padding:0px">
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os">Inicio</td>
         <td class="content_tbl_subheader content_row_os">Termino</td>
         <td class="content_tbl_subheader content_row_os">Tipo</td>
         <td class="content_tbl_subheader content_row_os" align="center">Estado</td>
         <td class="content_tbl_subheader content_row_os" align="center">Tiempo</td>
      </tr>
      <?php
      $px = 0;
      for($x = 0; $x < count($events) && $events != false; $x++)
      {
         $row = $events[$x];

         if($row["evt_type"] == "pause")
         {
            $orig_enddat = (int)$events[$x]["evt_enddat"];
            if(!(int)$events[$x]["evt_enddat"])
               $events[$x]["evt_enddat"] = time();

            $time_diff  = $events[$x]["evt_enddat"] - $events[$x]["evt_crtdat"];
            $time_diffx = $time_diff / 60;
            $hours_diff = (int)($time_diffx / 60);
            $min_diff   = (int)($time_diffx - ($hours_diff * 60));
            ?>
            <tr bgcolor="<?=getRowColor($px)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_crtdat"])?></nobr></td>
               <td class="content_row_os"><nobr><?=date("d.m H:i", $events[$x]["evt_enddat"])?></nobr></td>
               <td class="content_row_os"><?=$events[$x]["pause_name"]?> (<?=$events[$x]["pause_code"]?>)</td>
               <td class="content_row_os" align="center">
                  <?php
                  if(!(int)$orig_enddat)
                     echo "<b class=msg_save_err>En curso</b>";
                  else
                     echo "<b class=msg_save_ok>Finalizado</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center"><?="{$hours_diff}h {$min_diff}m"?>&nbsp;</td>
            </tr>
            <?php
            $px++;
         }
      }
      if(!$px)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" align="center" colspan="5">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
$sql = " select distinct t3.id
         from prod_header t1
         INNER JOIN prod_agenda t2    ON t1.id = t2.ag_prdid
         INNER JOIN prod_worker_ot t3 ON t3.wok_ag_id = t2.id
         where
         t1.prd_status  = 2 and
         t2.ag_status   > 0 and
         t3.wok_status  > 0 and
         t1.id          = {$agenda["prdid"]} ";
$refots = $CON->select($sql);
foreach($refots AS $refot)
   $_REFOTS[$refot["id"]] = 1;
$_REFOTSSQL = implode(",", array_keys($_REFOTS));

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.item_title, t3.item_number_prod, t2.item_amount, t4.user_firstname, t4.user_lastname, t5.st_name
         from stockchanges t1
         INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
         INNER JOIN item t3               ON t2.item_id = t3.id
         LEFT OUTER JOIN user t4          ON t1.stk_crtusr = t4.id
         LEFT OUTER JOIN company_shops_storehouses t5 ON t2.item_st_id = t5.id
         where
         t1.sth_fromprodotid  IN ({$_REFOTSSQL}) and
         t1.stk_status        = 2
         order by t1.id";
$stockchanges = $CON->select($sql);
if(count($stockchanges) && $stockchanges != false)
{  ?>
   <?=Nifty_printH("box1", "99%")?>
   <table border="0" cellpadding="6" cellspacing="0" width="100%">
   <colgroup>

      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Materiales consumidos</td>
   </tr>
   <tr>
      <td class="content_rowl content_row_os">Transacción</td>
      <td class="content_rowl content_row_os">Fecha</td>
      <td class="content_rowl content_row_os">Usuario</td>
      <td class="content_rowl content_row_os">Código</td>
      <td class="content_rowl content_row_os">Material</td>
      <td class="content_rowl content_row_os">Cantidad</td>
      <td class="content_rowl content_row_os">Bodega</td>
      <td class="content_rowl content_row_os">Comentarios</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($stockchanges) && $stockchanges != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$stockchanges[$x]["stk_num"]?></td>
         <td class="content_row"><?=date("d.m.Y H:i:s", $stockchanges[$x]["stk_bookdate"])?></td>
         <td class="content_row"><?=$stockchanges[$x]["user_firstname"]?> <?=$stockchanges[$x]["user_lastname"]?></td>
         <td class="content_row"><?=$stockchanges[$x]["item_number_prod"]?></td>
         <td class="content_row"><?=$stockchanges[$x]["item_title"]?></td>
         <td class="content_row"><?=printPrice($stockchanges[$x]["item_amount"],10)?></td>
         <td class="content_row"><?=$stockchanges[$x]["st_name"]?></td>
         <td class="content_row"><?=$stockchanges[$x]["stk_annotation"]?>&nbsp;</td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br><br>
   <?php
}

//----------------------------------------------------------------------------------
$sql = " select *
         from orders
         where
         id = {$agenda["ag_reqid"]}";
$embalajeorder = $CON->select($sql);
$embalajeorder = $embalajeorder[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from prod_worker_embalajes t1
         where
         t1.evt_reqid = {$agenda["ag_reqid"]}
         order by t1.id";
$embalajes = $CON->select($sql);
if(count($embalajes) && $embalajes != false)
{  ?>
   <?=Nifty_printH("box1", "99%")?>
   <table border="0" cellpadding="6" cellspacing="0" width="100%">
   <colgroup>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="9">Embalajes</td>
   </tr>
   <tr>
      <td class="content_rowl content_row_os">Tipo</td>
      <td class="content_rowl content_row_os">Fecha</td>
      <td class="content_rowl content_row_os">Medida de caja</td>
      <td class="content_rowl content_row_os">Cantidad de bolsas por caja</td>
      <td class="content_rowl content_row_os">Cantidad de cajas por pallets</td>
      <td class="content_rowl content_row_os">Cantidad de pallets completos</td>
      <td class="content_rowl content_row_os">Numero de cajas en pallet incompleto</td>
      <td class="content_rowl content_row_os">Cantidad total de cajas completas</td>
      <td class="content_rowl content_row_os">Caja final (completa pedido)</td>
   </tr>
   <tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os">Predefinido</td>
      <td class="content_row_os">- - -</td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_medidas_caja"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_bolsas_por_caja_amt"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_cajas_por_pallet_amt"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_pallets_completos_amt"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_palletcajas_incompletos_amt"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_cajas_completas_amt"]?></td>
      <td class="content_row_os" align="center"><?=$embalajeorder["req_embalaje_caja_final"]?></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($embalajes) && $embalajes != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x+1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os">Registro</td>
         <td class="content_row_os"><nobr><?=date("d.m.Y H:i:s", $embalajes[$x]["evt_crtdat"])?></nobr></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_medidas_caja"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_bolsas_por_caja_amt"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_cajas_por_pallet_amt"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_pallets_completos_amt"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_palletcajas_incompletos_amt"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_cajas_completas_amt"]?></td>
         <td class="content_row_os" align="center"><?=$embalajes[$x]["req_embalaje_caja_final"]?></td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
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

if((int)$agenda["req_rebo_rolloscc"] && count($_SUBPRODUCTS))
{
   $sql = " select t1.*, t2.wok_crtdat, t8.wrk_firstname, t8.wrk_lastname, t9.equipo_name
            from orders_classify_subproducts_inputweights t1
            INNER JOIN prod_worker_ot t2           ON t1.prod_worker_ot_id = t2.id
            INNER JOIN prod_worker_init t3         ON t2.wok_init_id = t3.id
            LEFT OUTER JOIN workers t8             ON t3.win_wrkid = t8.id
            LEFT OUTER JOIN equipo t9              ON t3.win_equipoid = t9.id
            where
            t1.req_id = {$agenda["ag_reqid"]} and
            t3.win_status > 0 and
            t2.wok_status > 0
            order by t2.wok_crtdat, t1.prod_worker_ot_id, t1.subprod_rollo_idx";
   $inputweights = $CON->select($sql);
   if(count($inputweights) && $inputweights != false)
   {
      foreach($inputweights AS $inputweight)
         $_INPUTWEIGHTS[$inputweight["prod_worker_ot_id"]][] = $inputweight;
      ?>
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="6" cellspacing="0" width="100%">
      <colgroup>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="36">Material saliente rebobinadora: Tarea <?=$agenda["req_rebo_type"]?></td>
      </tr>
      <tr>
         <td class="content_rowl content_row_os" valign="top">Fecha</td>
         <td class="content_rowl content_row_os" valign="top">Máquina</td>
         <td class="content_rowl content_row_os" valign="top">Trabajador</td>
         <td class="content_rowl content_row_os" align="center" valign="top">N°</td>
         <td class="content_rowl content_row_os" valign="top">Tipo<br>bobina</td>
         <td class="content_rowl content_row_os" valign="top">Materialidad</td>
         <td class="content_rowl content_row_os" valign="top">Color<br>bobina</td>
         <td class="content_rowl content_row_os" valign="top">Gramaje</td>
         <td class="content_rowl content_row_os" align="center" valign="top" style="border-left:3px double #666666;">Peso<br>entrante</td>
         <?php
         foreach(array_keys($_SUBPRODUCTS) AS $sub_pos)
         {  ?>
            <td class="content_rowl content_row_os" align="center" style="border-left:3px double #666666;">
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
      $xcounter = 0;
      foreach(array_keys($_INPUTWEIGHTS) AS $prod_worker_ot_id)
      {
         $xlines = $_INPUTWEIGHTS[$prod_worker_ot_id];
         foreach($xlines AS $xline)
         {  ?>
            <tr bgcolor="<?=getRowColor($xcounter)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><nobr><?=date("d.m.Y H:i:s", $xline["wok_crtdat"])?></nobr></td>
               <td class="content_row_os"><?=$xline["equipo_name"]?></td>
               <td class="content_row_os"><?=$xline["wrk_firstname"]?>&nbsp;<?=$xline["wrk_lastname"]?></td>
               <td class="content_row_os" align="center"><?=($xline["subprod_rollo_idx"]+1)?></td>
               <td class="content_row_os"><?=$agenda["req_rebo_state"]?></td>
               <td class="content_row_os"><?=$agenda["fab_type"]?></td>
               <td class="content_row_os"><?=$agenda["fabric_color"]?></td>
               <td class="content_row_os"><?=$agenda["fab_mat_gramms"]?></td>
               <td class="content_row_os" align="center" style="border-left:3px double #666666;"><?=printPrice($xline["subprod_weight_enter"])?></td>
               <?php
               foreach(array_keys($_SUBPRODUCTS) AS $sub_pos)
               {  ?>
                  <td class="content_row_os" align="center" style="border-left:3px double #666666;">
                     <?=printPrice($xline["subprod_salida_weight_{$sub_pos}"])?>
                  </td>
                  <?php
               }
               ?>
            </tr>
            <?php
         }
         $xcounter++;
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br><br>
      <?php
   }
}
?>
<br><br>