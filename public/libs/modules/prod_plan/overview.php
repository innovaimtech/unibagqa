<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "prod_plan";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc";

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_plantaid"]      = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]  = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]      = (int)$_REQUEST["sql_equipoid"];

   $_SESSION[$_sesmodulename]["sql_xstate"]        = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_otnum"]         = trim(addslashes($_REQUEST["sql_otnum"]));
   $_SESSION[$_sesmodulename]["sql_ccnum"]         = trim(addslashes($_REQUEST["sql_ccnum"]));
   
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_fab_design_name"] = trim(addslashes($_REQUEST["sql_fab_design_name"]));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time());
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y', time() + 2*86400);
}

//----------------------------------------------------------------------------------
$sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_date_pfrom"]);
$sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_date_pto"], false);

for($x = $sqldate_from; $x <= $sqldate_to; $x += 30000)
{
   $idx = date("d.m.Y", $x);
   $_SELDAYS[$idx] = 1;
}

//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(count($plantas) == 1)
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];
   
$selplantas = getPlantas($CON, $_SESSION[$_sesmodulename]["sql_plantaid"]);

if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
{
   $sql = " select *
            from equipo_type
            where
            type_ant_status > 0 and
            type_ant_prod_dabl = 0
            order by type_ant_title";
   $selequipotypes = $CON->select($sql);
   $temp = Array();
   for($x = 0; $x < count($selequipotypes) && $selequipotypes != false; $x++)
   {
      $sql = " select *
               from equipo
               where
               equipo_status     > 0 and
               equipo_type_id    = {$selequipotypes[$x]["id"]} and
               equipo_planta_id  = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
               equipo_prod_dabl = 0
               order by equipo_name";
      $equipos = $CON->select($sql);
      if(count($equipos) && $equipos != false)
      {
         $_SELEQUIPOTYPES[$selequipotypes[$x]["id"]] = $selequipotypes[$x]["type_ant_title"];

         foreach($equipos AS $equipo)
         {
            if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] && (int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] == $equipo["equipo_type_id"])
            {
               $_SELEQUIPOS[$equipo["id"]] = $equipo["equipo_name"];
            }
         }
      }
   }
}
   
//----------------------------------------------------------------------------------
?>
<script language="Javascript">
function showFancyboxUnibagProd(xurl, xtype, xwidth, xheight, xscroll)
{
   $.fancybox.close();
   $.fancybox(xurl,
   {
      'width'        : xwidth,
      'height'       : xheight,
      'type'         : xtype,
      'scrolling'    : xscroll,
      onClosed: function() { document.xform_itemsearch.submit(); }
   });
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript" src="./libs/jscripts/jquery-ui.min.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td height="30"><b class="content_header">Planificación OT</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst" style="padding:0px;margin:0px"
onsubmit="return checkform(new Array(this.sql_plantaid))">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="printxls" value="0">
<table cellpadding="0" cellspacing="0" width="99%" border="0">
<colgroup>
   <col width="550">
   <col width="15">
   <col>
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "980")?>
      <table cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="80">
         <col width="380">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Filtros</td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <nobr>
            <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
            -
            <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
            </nobr>
         </td>
         <td class="content_rowl">Nº OT</td>
         <td class="content_row">
            <input name="sql_otnum" type="text" class="text" style="width:100%"
            value="<?=$_SESSION[$_sesmodulename]["sql_otnum"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Planta</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_plantaid" id="sql_plantaid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqLoadPlantaEquipoTypes(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($plantas AS $planta)
               {  ?>
                  <option value="<?=$planta["id"]?>"
                  <?php if($planta["id"] == $_SESSION[$_sesmodulename]["sql_plantaid"]) echo "selected"?>><?=$planta["planta_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Nº CC</td>
         <td class="content_row">
            <input name="sql_ccnum" type="text" class="text" style="width:100%"
            value="<?=$_SESSION[$_sesmodulename]["sql_ccnum"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo máquina</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_equipotypeid" id="sql_equipotypeid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqLoadPlantaEquipos(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOTYPES) AS $etypeid)
               {  ?>
                  <option value="<?=$etypeid?>"
                  <?php if($etypeid == $_SESSION[$_sesmodulename]["sql_equipotypeid"]) echo "selected"?>><?=$_SELEQUIPOTYPES[$etypeid]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row">
            <?php printOverviewCustomerSelect($_sesmodulename) ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Máquina</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_equipoid" id="sql_equipoid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOS) AS $eqid)
               {  ?>
                  <option value="<?=$eqid?>"
                  <?php if($eqid == $_SESSION[$_sesmodulename]["sql_equipoid"]) echo "selected"?>><?=$_SELEQUIPOS[$eqid]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <input type="radio" name="sql_xstate" value="0" <?php if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xstate" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 1) echo "checked"?>> Solo activos
            <input type="radio" name="sql_xstate" value="2" <?php if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 2) echo "checked"?>> Solo inactivos
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Diseño</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%"
            name="sql_fab_design_name" value="<?=$_SESSION[$_sesmodulename]["sql_fab_design_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4" height="37">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="1" style="padding-right:3px">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
                     printButton("Agregar OT", "postnav_save", "javascript: deactivateFormChange()", "showFancyboxUnibagProd('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=otplanadd&sql_date_pfrom={$_SESSION[$_sesmodulename]["sql_date_pfrom"]}&sql_date_pto={$_SESSION[$_sesmodulename]["sql_date_pto"]}&sql_plantaid={$_SESSION[$_sesmodulename]["sql_plantaid"]}','iframe', '99%', '99%', 'auto')", "plus", 130);
                  ?>
               </td>
               <td align="left">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
                     printButton("Agregar Mantención", "postnav", "javascript: deactivateFormChange()", "showFancyboxUnibagProd('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=otplanaddmantencion&sql_date_pfrom={$_SESSION[$_sesmodulename]["sql_date_pfrom"]}&sql_date_pto={$_SESSION[$_sesmodulename]["sql_date_pto"]}&sql_plantaid={$_SESSION[$_sesmodulename]["sql_plantaid"]}', 'iframe', 500, 300, 'auto')", "plus", 130);
                  ?>
               </td>
               <td align="right" width="1" style="padding-right:3px">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right" width="1">
                  <?php
                  printButton("Mostrar", "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td class="content_row_clear">&nbsp;</td>
   <td valign="top">&nbsp;</td>
</tr>
</table>
<br>
<?php
if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from equipo_type
            where
            type_ant_status > 0 and
            type_ant_prod_dabl = 0 ";
   if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
      $sql .= " and id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
   $sql .= " order by type_ant_title";
   $equipotypes = $CON->select($sql);
   $temp = Array();
   for($x = 0; $x < count($equipotypes) && $equipotypes != false; $x++)
   {
      $sql = " select *
               from equipo
               where
               equipo_status     > 0 and
               equipo_type_id    = {$equipotypes[$x]["id"]} and
               equipo_planta_id  = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
               equipo_prod_dabl  = 0 and
               equipo_prod_dabl  = 0 ";
      if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
         $sql .= " and id = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";
      $sql .= " order by equipo_name";
      $equipos = $CON->select($sql);
      if(count($equipos) && $equipos != false)
      {
         $equipotypes[$x]["_equipos"] = $equipos;
         $temp[] = $equipotypes[$x];
      }
   }
   $equipotypes = $temp;

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
                   t3x.id 'prdid', t1x.fab_design_name
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
            t0.ag_plantaid    = {$_SESSION[$_sesmodulename]["sql_plantaid"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 1)
      $sql .= " and t0.ag_active = 1 ";
   if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 2)
      $sql .= " and t0.ag_active = 0 ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $sql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_otnum"] != "")
      $sql .= " and t3x.prd_number = '{$_SESSION[$_sesmodulename]["sql_otnum"]}' ";
   if($_SESSION[$_sesmodulename]["sql_ccnum"] != "")
      $sql .= " and t1.req_number = '{$_SESSION[$_sesmodulename]["sql_ccnum"]}' ";
   if($_SESSION[$_sesmodulename]["sql_fab_design_name"] != "")
      $sql .= " and t1x.fab_design_name like '%{$_SESSION[$_sesmodulename]["sql_fab_design_name"]}%' ";
   $sql .= " order by t0.ag_date_stamp asc, t0.ag_order, t0.id asc";
   $agendas = $CON->select($sql);
   foreach($agendas AS $agenda)
   {
      $idx1 = $agenda["ag_date"];
      $idx2 = $agenda["ag_equipotype_id"];
      $idx3 = $agenda["ag_equipo_id"];
      $_AGENDA[$idx1][$idx2][$idx3][] = $agenda;
   }

   $sql = " select distinct t0.*, t1.mant_title, 'admmant' AS 'xtype'
            from prod_agenda_mantencion t0
            INNER JOIN equipo_manttype t1 ON t0.ag_equipo_mantid = t1.id
            where
            t0.ag_status      > 0 and
            t0.ag_date_stamp  between {$sqldate_from} and {$sqldate_to} and
            t0.ag_plantaid    = {$_SESSION[$_sesmodulename]["sql_plantaid"]}
            order by t0.ag_date_stamp asc, t0.id asc";
   $admmants = $CON->select($sql);
   foreach($admmants AS $admmant)
   {
      $idx1 = $admmant["ag_date"];
      $idx2 = $admmant["ag_equipotype_id"];
      $idx3 = $admmant["ag_equipo_id"];
      $_AGENDA[$idx1][$idx2][$idx3][] = $admmant;
   }
   ?>
   <div id="idx_jqout"></div>
   <div id="idx_jqout_move"></div>
   <?=Nifty_printH("box1", "99%")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="75">
   </colgroup>
   <tr>
      <td class="content_tbl_header" rowspan="2" align="center">Fecha</td>
      <?php
      foreach($equipotypes AS $equipotype)
      {  ?>
         <td class="content_tbl_header" style="border-left:3px double #666666" align="center" colspan="<?=count($equipotype["_equipos"])?>">
            <?=$equipotype["type_ant_title"]?>
         </td>
         <?php
      }  
      ?>
   </tr>
   <tr>
      <?php
      foreach($equipotypes AS $equipotype)
      {
         $ex = 0;
         foreach($equipotype["_equipos"] AS $equipo)
         {  ?>
            <td class="content_tbl_subheader content_row_os" style="<?if(!(int)$ex) echo "border-left:3px double #666666"?>" align="center">
               <?=$equipo["equipo_name"]?>
            </td>
            <?php
            $ex++;
         }
      }  
      ?>
   </tr>
   <?php
   foreach(array_keys($_SELDAYS) AS $dayidx)
   {
      $mx = 0;
      $rowcss = "";
      if($dayidx != $last_dayidx)
         $rowcss = "border-top:3px double #666666";

      $divtrcss = "color:white;text-shadow:none";
      if(count($_AGENDA[$dayidx]))
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_tbl_subheader content_row_os" align="center" style="<?=$rowcss?>" valign="top"><?=$dayidx?></td>
            <?php
            foreach($equipotypes AS $equipotype)
            {
               $ex = 0;
               foreach($equipotype["_equipos"] AS $equipo)
               {  ?>
                  <td class="content_tbl_subheader content_row_os"
                  style="color:#CCCCCC;<?=$rowcss?>;<?if(!(int)$ex) echo "border-left:3px double #666666"?>"
                  align="center" valign="top">
                     <?php
                     $rkeys = array_keys($_AGENDA[$dayidx][$equipotype["id"]][$equipo["id"]]);
                     foreach($rkeys AS $rowidx)
                     {
                        $row = $_AGENDA[$dayidx][$equipotype["id"]][$equipo["id"]][$rowidx];

                        if($row["xtype"] == "admmant")
                        {
                           $fancyurl = "showFancyboxUnibagProd('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=otplanaddmantencion&sql_plantaid={$row["ag_plantaid"]}&id={$row["id"]}', 'iframe', 500, 300, 'auto')";
                           ?>
                           <div style="background-color:#E74D4D;border:1px solid #007472;margin-bottom:3px;color:white;padding:4px;padding-left:6px;padding-right:6px;cursor:pointer;text-shadow:none">
                              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>"><b>Mantención preventivo</b></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>"><b>Tipo:</b> <?=$row["mant_title"]?></td>
                              </tr>
                              <?php
                              if($row["ag_notes"] != "")
                              {  ?>
                                 <tr onclick="<?=$fancyurl?>">
                                    <td class="content_row_clear" style="<?=$divtrcss?>"><?=$row["ag_notes"]?></td>
                                 </tr>
                                 <?php
                              }
                              ?>
                              </table>
                           </div>
                           <?php
                        }
                        else
                        {
                           $_THIS_STATS   = getProdStats($CON, 0, $row["id"], 0, 0, 0, $equipotype["id"]);
                           $_OT_STATS     = getProdStats($CON, $row["prdid"], 0, 0, 0, 0, $equipotype["id"]);
      
                           $amt_total     = (int)$row["ag_amount"];
                           $amt_fab       = $_THIS_STATS["_PROD_AMOUNT"];
                           $prg_perc      = round($amt_fab / $amt_total * 100);
                           if($prg_perc > 100)
                              $prg_perc = 100;
                                 
                           $fancyurl      = "showFancyboxUnibagProd('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=otplanedit&agid={$row["id"]}','iframe', '99%', '99%', 'auto')";
                           ?>
                           <div class="dritem clstd_<?=$equipo["id"]?>"
                           style="<?if((int)$row["ag_active"]) echo "background-color:#6EBE6C;"; else echo "background-color:#00A9A6;"?>;border:1px solid #007472;margin-bottom:3px;color:white;padding:4px;padding-left:6px;padding-right:6px;cursor:pointer;text-shadow:none"
                           id="idx_agitem_<?=$row["id"]?>">
                              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>"><b>OT:</b> <?=$row["prd_number"]?></td>
                                 <td class="content_row_clear" style="<?=$divtrcss?>" align="right"><b>CC:</b> <?=$row["req_number"]?></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>" colspan="2"><?=$row["cust_name"]?></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>" colspan="2"><?=$row["fab_design_name"]?></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>"><?=printPrice($row["ag_amount"])?> C/U</td>
                                 <td class="content_row_clear" style="<?=$divtrcss?>" align="right"><?=$row["item_number_prod"]?></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>" colspan="2"><?=$row["item_title"]?></td>
                              </tr>
                              <tr onclick="<?=$fancyurl?>">
                                 <td class="content_row_clear" style="<?=$divtrcss?>"><?=$row["fab_printtype"]?></td>
                                 <td class="content_row_clear" style="<?=$divtrcss?>" align="right"><?=$row["fab_type"]?></td>
                              </tr>
                              <tr>
                                 <td class="content_row_clear" style="<?=$divtrcss?>" colspan="2">
                                    <div style="float:left;height:14px;width:100%;border:1px solid #007472;background-color:#EEEEEE;border-radius:3px">
                                       <div style="position:absolute;font-size:10px;font-weight:bold;color:#333333;line-height:15px;">
                                          &nbsp;<?=printPrice($amt_fab)?> de <?=printPrice($row["ag_amount"])?> | <?=printPrice($prg_perc)?>%
                                       </div>
                                       <div style="border-radius:3px;width:<?=$prg_perc?>%;height:14px;background-color:#69C36D"></div>
                                    </div>
                                 </td>
                              </tr>
                              <?php
                              $amt_total     = (int)$row["item_amount"];
                              $amt_fab       = $_OT_STATS["_PROD_AMOUNT"];
                              $prg_perc      = round($amt_fab / $amt_total * 100);
                              if($prg_perc > 100)
                                 $prg_perc = 100;
                              ?>
                              <tr>
                                 <td class="content_row_clear" style="<?=$divtrcss?>;padding-top:3px" colspan="2">
                                    <div style="float:left;height:14px;width:100%;border:1px solid #007472;background-color:#EEEEEE;border-radius:3px">
                                       <div style="position:absolute;font-size:10px;font-weight:bold;color:#333333;line-height:15px;">
                                          &nbsp;<?=printPrice($amt_fab)?> de <?=printPrice($row["item_amount"])?> | <?=printPrice($prg_perc)?>% Total
                                       </div>
                                       <div style="border-radius:3px;width:<?=$prg_perc?>%;height:14px;background-color:#69C36D"></div>
                                    </div>
                                 </td>
                              </tr>
                              <tr>
                                 <td class="content_row_clear" style="<?=$divtrcss?>;padding-top:3px" colspan="2">
                                    <?php
                                    if(count($rkeys) > 1 && $_AGENDA[$dayidx][$equipotype["id"]][$equipo["id"]][($rowidx+1)]["xtype"] != "admmant")
                                    {  ?>
                                       <span style="float:left">
                                          <img src="/images/menu/icons/arrow-090.png" style="cursor:pointer" title="Mover arriba"
                                          onclick="moveProdAgendaItem('<?=$row["id"]?>', 'up')">
                                          <img src="/images/menu/icons/arrow-skip-090.png" style="cursor:pointer" title="Mover a primera posicion"
                                          onclick="moveProdAgendaItem('<?=$row["id"]?>', 'upfirst')">
                                          &nbsp;
                                          <img src="/images/menu/icons/arrow-270.png" style="cursor:pointer" title="Mover abajo"
                                          onclick="moveProdAgendaItem('<?=$row["id"]?>', 'down')">
                                          <img src="/images/menu/icons/arrow-skip-270.png" style="cursor:pointer" title="Mover a ultima posicion"
                                          onclick="moveProdAgendaItem('<?=$row["id"]?>', 'downlast')">
                                       </span>
                                       <?php
                                    }
                                    ?>
                                    <span style="float:right;">
                                       Activar OT
                                       <input type="checkbox" title="Activar" style="transform:scale(1.15);padding:0px;margin:0px;margin-bottom:0px"
                                       <?php if((int)$row["ag_active"]) echo "checked"?> class="clschk_<?=$equipo["id"]?> clsthischk_<?=$row["id"]?>"
                                       onclick="activateProdAgendaItem('<?=$row["id"]?>', this.checked)">
                                    </span>
                                 </td>
                              </tr>
                              </table>
                           </div>
                           <?php
                        }
                     }
                     ?>
                  </td>
                  <?php
                  $ex++;
               }
            }
            ?>
         </tr>
         <?php
      }
      else
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_tbl_subheader content_row_os" align="center" style="<?=$rowcss?>"><?=$dayidx?></td>
            <?php
            foreach($equipotypes AS $equipotype)
            {
               $ex = 0;
               foreach($equipotype["_equipos"] AS $equipo)
               {  ?>
                  <td class="content_tbl_subheader content_row_os" style="color:#CCCCCC;<?=$rowcss?>;<?if(!(int)$ex) echo "border-left:3px double #666666"?>"
                  align="center">
                     Sin planificación
                  </td>
                  <?php
                  $ex++;
               }
            }
            ?>
         </tr>
         <?php
      }
      $mx++;
      $last_dayidx = $dayidx;
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}

$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>