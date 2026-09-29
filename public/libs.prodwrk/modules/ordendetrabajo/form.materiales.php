<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["sql_catid"] == $_CONFIG["TELA_CATID"])
   $_btnsuffix = " Bobina";

//----------------------------------------------------------------------------------
if((int)$_REQUEST["fromautocontrol"])
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
                   to1.req_number 'reqnum_mezcla_1', to2.req_number 'reqnum_mezcla_2', to3.req_number 'reqnum_mezcla_3'
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
}


$_REQUEST["agid"] = (int)$_REQUEST["agid"];
$_REQUEST["sql_catid"] = (int)$_REQUEST["sql_catid"];
foreach(array_keys($_REQUEST) AS $reqkey)
{
   if(strpos($reqkey, "sql_comvals_") !== false && strpos($reqkey, "sql_comvals_") == 0)
   {
      $compid     = substr($reqkey, strrpos($reqkey, "_") +1);
      $compvalid  = (int)$_REQUEST[$reqkey];
      if((int)$compid && (int)$compvalid)
      {
         $_SQL_COMPIDS[$compid] = $compvalid;
      }
   }
}

//----------------------------------------------------------------------------------
$_IS_SELLADORA = false;
if(strpos(strtoupper($hasopeninit["equipo_name"]), "SELLADORA") !== false)
   $_IS_SELLADORA = true;

//----------------------------------------------------------------------------------
$_SQL_CATIDS = "{$_CONFIG["PINTURAS_CATID"]},{$_CONFIG["TELA_CATID"]}, 8";

// if($_IS_SELLADORA)
//    $_SQL_CATIDS .= ", 27";

$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.item_status       > 0 and 
         t1.item_released     > 0 and 
         t1.item_prodwrk_act  = 1 and
         (
            t3.cat_itemreg_machine_assign = 0 or
            (
               t3.cat_itemreg_machine_assign = 1 and
               (
                  select count(*)
                  from item_equipos_rel trel
                  where
                  trel.item_id   = t1.id and 
                  trel.equipo_id = {$hasopeninit["win_equipoid"]}
               ) > 0
            )
         ) and
         t3.cat_repuestos_act = 0 and
         t3.id IN ({$_SQL_CATIDS}) ";
if($_IS_SELLADORA)
   $sql .= " and t3.id NOT IN ({$_CONFIG["PINTURAS_CATID"]}) ";
$sql .= " order by t3.cat_title, t1.item_title";
$allitems = $CON->select($sql);

foreach($allitems AS $allitem)
{
   $_CANADD = false;
   if($allitem["cat_id"] != (int)$_CONFIG["TELA_CATID"])
   {
      $sql = " select *, t3.id 'val_id'
               from tran_comments t1
               INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
               INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
               where
               t1.com_status  > 0 and
               t2.cat_id      = {$allitem["cat_id"]} and
               t3.add_status  > 0
               order by t1.com_name, t3.add_name";
      $chars = $CON->select($sql);

      foreach($chars AS $char)
      {
         if($agenda["fab_printtype"] == "FLEX")
         {
            if((int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID"] ||
               (int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID_2"]) 
            {
               $_CANADD = true;
            }
         }
         elseif($agenda["fab_printtype"] == "SERI")
         {
            if((int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID"] ||
               (int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID_2"]) 
            {
               $_CANADD = true;
            }
         }
      }
   }
   else
      $_CANADD = true;

   if($allitem["cat_id"] == 8)
      $_CANADD = true;

   if($allitem["cat_id"] == 27)
      $_CANADD = true;
   
   if($_CANADD)
      $_ALLCATS[$allitem["cat_id"]] = $allitem["cat_title"];
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.item_status       > 0 and 
         t1.item_released     > 0 and 
         t1.item_prodwrk_act  = 1 and
         (
            t3.cat_itemreg_machine_assign = 0 or
            (
               t3.cat_itemreg_machine_assign = 1 and
               (
                  select count(*)
                  from item_equipos_rel trel
                  where
                  trel.item_id   = t1.id and 
                  trel.equipo_id = {$hasopeninit["win_equipoid"]}
               ) > 0
            )
         ) ";
if((int)$_REQUEST["sql_catid"])
   $sql .= " and t2.cat_id = {$_REQUEST["sql_catid"]} ";
if($_IS_SELLADORA)
   $sql .= " and t3.id NOT IN ({$_CONFIG["PINTURAS_CATID"]}) ";
if(count($_SQL_COMPIDS))
{
   foreach(array_keys($_SQL_COMPIDS) AS $sql_compid)
   {
      $sql .= " and 
                (
                   select count(*)
                   from tran_comments_item_vals tvals
                   where
                   tvals.item_id = t1.id and
                   tvals.com_id  = {$sql_compid} and
                   tvals.val_id  = {$_SQL_COMPIDS[$sql_compid]}
                ) > 0 ";
   }
}
$sql .= " order by t3.cat_title, t1.item_title, t1.item_number_prod";
$allitems = $CON->select($sql);
foreach($allitems AS $allitem)
{
   $_ITEMS[$allitem["cat_id"]][] = $allitem;
}
//----------------------------------------------------------------------------------
// PREFILTER TELA ITEMS
$telaitems = $_ITEMS[$_CONFIG["TELA_CATID"]];
unset($_ITEMS[$_CONFIG["TELA_CATID"]]);

foreach($telaitems AS $telaitem)
{
   $hastelacolor = false;
   $hastelatype  = false;

   $sql = " select t1.*
            from tran_comments_item_vals t1
            INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
            where
            t1.item_id = {$telaitem["id"]} and
            t1.com_id  = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
            t1.val_id  IN ({$agenda["fab_mat_fabric_color"]}, {$agenda["fab_mat_manilla_color"]})";
   $comvals = $CON->select($sql);
   if((int)$comvals[0]["item_id"])
   {
      $hastelacolor = true;
   }

   $sql = " select t1.*
            from tran_comments_item_vals t1
            INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
            where
            t1.item_id  = {$telaitem["id"]} and
            t1.com_id   = {$_CONFIG["TELA_MATERIAL_CHARACTID"]} and
            t2.add_name = '{$agenda["fab_type"]}'";
   $comvals = $CON->select($sql);
   if((int)$comvals[0]["item_id"])
   {
      $hastelatype = true;
   }
   if($hastelacolor && $hastelatype)
   {
      $_ITEMS[$_CONFIG["TELA_CATID"]][] = $telaitem;
   }
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["deletetran"])
{
   $currtme = time();

   delStockChange($CON, $_REQUEST["deletetran"]);

   $sql = " update stockchanges
            set
            stk_status     = 0,
            stk_updusr     = {$_SESSION["user_id"]},
            stk_upddat     = {$currtme}
            where
            id = {$_REQUEST["deletetran"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($agenda["fab_printtype"] == "FLEX")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$agenda["req_shop_id"]} and
            t2.st_unibagflexo_act      = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}
elseif($agenda["fab_printtype"] == "SERI")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$agenda["req_shop_id"]} and
            t2.st_unibagseri_act       = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}


//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.item_id, t3.item_title, t3.item_number_prod, t2.item_amount,
                t3.item_used_parent_id, t4.cat_id
         from stockchanges t1
         INNER JOIN stockchanges_items t2    ON t1.id = t2.stk_id
         INNER JOIN item t3                  ON t2.item_id = t3.id
         LEFT OUTER JOIN item_productcats t4 ON t3.id = t4.item_id
         where
         t1.sth_fromprodotid = {$hasopenot["id"]} and
         t1.stk_status = 2";
$stockchanges = $CON->select($sql);

//----------------------------------------------------------------------------------
if($_IS_SELLADORA && $_REQUEST["sql_catid"] == 9999999)
{
   $sql_stids = "";
   foreach($destsths AS $deststh)
      $sql_stids .= "{$deststh["id"]},";
   $sql_stids = substr($sql_stids, 0, -1);

   $_NEW_ITEM_NAME   = "{$agenda["fab_type"]}";
   $_NEW_ITEM_NAME  .= "/{$agenda["fabric_color"]}";
   $_NEW_ITEM_NAME  .= "/{$agenda["req_operador_tela_width"]}";
   $_NEW_ITEM_NAME  .= "/{$agenda["fab_mat_gramms"]}GR";
   $sql = " select *
            from item t1
            INNER JOIN item_shops_storehouses t2 ON t1.id = t2.item_id
            where
            t1.item_status       > 0 and
            t1.item_number_prod  like 'BTE%' and
            t2.st_id             IN ({$sql_stids}) and
            t2.iss_inventory     > 0 and
            t1.item_title        like '{$_NEW_ITEM_NAME}%'
            order by t1.item_number_prod";
   $allitems = $CON->select($sql);
   foreach($allitems AS $allitem)
   {
      $_ITEMS[9999999][] = $allitem;
   }
}
?>
<div class="btngrey" onclick="location.href = '/prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';">
   <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
</div>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
<input type="hidden" name="submode" value="">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="deletetran" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<input type="hidden" name="fromautocontrol" value="<?=$_REQUEST["fromautocontrol"]?>">
<input type="hidden" name="overridemode" value="<?=$_REQUEST["overridemode"]?>">
<?php
if(count($stockchanges) && $stockchanges != false)
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="100">
            <col width="150">
            <col>
            <col width="120">
            <col width="120">
            <col width="100">
            <col width="120">
            <col width="20">
            <col width="20">
         </colgroup>
         <tr>
            <td colspan="9" class="tdheader" style="color: white;background-color: #D23C48;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Egresos\Ingresos contabilizados</td>
         </tr>
         <tr>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Transacción</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Fecha/Hora</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Material</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Código</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">Cantidad</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">Tipo</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;" align="center" colspan="3">Opciones</td>
         </tr>
         <?php
         foreach($stockchanges AS $stockchange)
         {
            // echo "<pre>";
            // print_r($stockchange);
            ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["stk_num"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=date("d.m.Y H:i:s", $stockchange["stk_bookdate"])?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["item_title"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["item_number_prod"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center"><?=$stockchange["stk_annotation"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                  <?php
                  if((int)$stockchange["stk_negative"])
                     echo "Entrada";
                  else
                     echo "Salida";
                  ?>

               </td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                  <div class="btnred" onclick="if(askDel('')) { document.xform_inp.deletetran.value='<?=$stockchange["id"]?>'; document.xform_inp.submit(); }"
                  style="width:120px">
                     <i class="fa fa-fw fa-times-circle" style="color:white;"></i> Eliminar&nbsp;
                  </div>
               </td>
               <?php
               $style = ((int)$stockchange["cat_id"] != 6) ? "opacity:0.5; cursor:default; pointer-events:none;"
                                                                 : "opacity:1; cursor:pointer; pointer-events:auto;";

               $_thisbtnsuffix = "";
               if($stockchange["cat_id"] == $_CONFIG["TELA_CATID"])
                  $_thisbtnsuffix = " Bobina";

               $fancyitemrefid = $stockchange["item_id"];
               if((int)$stockchange["item_used_parent_id"])
                  $fancyitemrefid = $stockchange["item_used_parent_id"];

               if(!$stockchange["item_used_parent_id"])
               {  ?>
                  <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;cursor:pointer"
                  onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.php?itemid=<?=$stockchange["item_id"]?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                  title="Entrada<?=$_thisbtnsuffix?>"><i class="fa fa-fw fa-download"></i></td>
                  <td class="tdnrm" id="materialTd" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;cursor:pointer;<?=$style?>"
                     onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.reingreso.php?itemid=<?=$fancyitemrefid?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                     title="Salida<?=$_thisbtnsuffix?>"><i class="fa fa-fw fa-upload"></i>
                  </td>
                  <?php
               }
               else
               {  ?>
                  <td colspan="2" class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;">
                     &nbsp;
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
   <div style="height:10px"></div>
   <?php
}
?>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td> 
            <select class="inptxt" style="background-color:#FFFFFF;width:400px" name="sql_catid"
            onchange="location.href = '/prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=<?=$_REQUEST["mode"]?>&agid=<?=$_REQUEST["agid"]?>&refid=<?=$_REQUEST["refid"]?>&overridemode=<?=$_REQUEST["overridemode"]?>&fromautocontrol=<?=$_REQUEST["fromautocontrol"]?>&setnew=1&sql_catid=' +this.value">
               <option value="">Seleccione una categoria</option>
               <option value="9999999" <?if($_REQUEST["sql_catid"] == 9999999) echo "selected"?>>Bobinas impresas</option>
               <?php
               foreach(array_keys($_ALLCATS) AS $_ALLCATID)
               {  ?>
                  <option value="<?=$_ALLCATID?>" <?if($_ALLCATID == $_REQUEST["sql_catid"]) echo "selected"?>>
                     <?=$_ALLCATS[$_ALLCATID]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr id="idx_charact_opts" style="<?if(!(int)$_REQUEST["sql_catid"]) echo "display:none"?>">
         <td style="padding-top:10px">
            <div id="idx_charact_jqres">
               <?php
               if((int)$_REQUEST["setnew"])
               {
                  //PRESET DEFAULT FOR PAPERS
                  if((int)$_REQUEST["sql_catid"] == $_CONFIG["TELA_CATID"])
                  {
                     $sql = " select *, t3.id 'val_id'
                              from tran_comments t1
                              INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                              INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                              where
                              t1.com_status  > 0 and
                              t2.cat_id      = {$_REQUEST["sql_catid"]} and
                              t3.add_status  > 0
                              order by t1.com_name, t3.add_name";
                     $chars = $CON->select($sql);

                     foreach($chars AS $char)
                     {
                        if((int)$char["com_id"] == $_CONFIG["TELA_COLOR_CHARACTID"]) 
                        {
                           if((int)$char["val_id"] == $agenda["fab_mat_fabric_color"])
                           {
                              $_REQUEST["sql_comvals_{$char["com_id"]}"] = $char["val_id"];

                              $hasmat = false;
                              foreach($chars AS $xchar)
                              {
                                 if($xchar["add_name"] == $agenda["fab_type"])
                                 {
                                    $_REQUEST["sql_comvals_{$xchar["com_id"]}"] = $xchar["val_id"];
                                    $hasmat = true;
                                 }
                              }
                           }
                        }
                     }
                  }
               }

               printPcatFiltersWrk($CON, $_REQUEST["sql_catid"], "prodwrk")
               ?>
            </div>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?php
if((int)$_REQUEST["sql_catid"])
{  
   $sql = " select *
            from productcats
            where
            id = {$_REQUEST["sql_catid"]}";
   $pcatdata = $CON->select($sql);
   $pcatdata = $pcatdata[0];

   unset($_TRANSCOM);
   $sql = " select t1.com_name, t3.*
            from tran_comments t1
            INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
            INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
            where
            t1.com_status  > 0 and
            t2.cat_id      = {$_REQUEST["sql_catid"]} and
            t3.add_status  > 0
            order by t1.com_name, t3.add_name";
   $trancoms = $CON->select($sql);
   foreach($trancoms AS $trancom)
   {
      $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
      $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
   }
   ?>
   <div style="height:10px"></div>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
         </colgroup>
            <!--
            <?$style = ((int)$_REQUEST["sql_catid"] == 6) ? "":"display: none;";?>
            <td id="materialTd" align="left" style="<?=$style?>"
                onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.new.php?itemid=<?=$item["id"]?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                title="Ingresa Nueva Bobina Procesada"><i class="fa fa-fw fa-plus"></i>Ingresa nueva bobina procesada
            </td>
            -->
         <tr>
            <td class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Descripción material</td>
            <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
            <?php
            if((int)$pcatdata["cat_itemreg_width"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho(cm)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_length"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Longitud(m)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_gsm"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">GSM(gr)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_kg"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kilogramos</td>
               <?php
            }
            foreach(array_keys($_TRANSCOM) AS $comid)
            {  
               $commname = ucwords(strtolower($_TRANSCOM[$comid]["NAME"]));
               ?>
               <td align="center" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD"><?=$commname?></td>
               <?php
            }
            ?>
            <td width="100" class="tdheader" align="center" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;">Stock</td>
            <td width="20" class="tdheader" align="center" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;border-right:1px solid #DDDDDD"><i class="fa fa-fw fa-download"></i></td>
            <td width="20" class="tdheader" align="center" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;border-right:1px solid #DDDDDD"><i class="fa fa-fw fa-upload"></i></td> 
         </tr>
         <?php
         foreach($_ITEMS[$_REQUEST["sql_catid"]] AS $item)
         {  
            unset($_COMVALS);

            if((int)$_REQUEST["sql_catid"] == 8)
               $sql = "select t1.*, t2.add_name
                        from tran_comments_item_vals t1
                           inner join tran_comments_vals t2 ON t1.val_id = t2.id
                           inner join item i on i.id = {$item["id"]} 
                           inner join parametros p on tabla = 'INSUMOS' and codigo = i.item_number_prod and valor1 in(0,{$agenda["ag_equipotype_id"]})
                        where t1.item_id = {$item["id"]} ";
            else                       
               $sql = " select t1.*, t2.add_name
                        from tran_comments_item_vals t1
                        INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
                        where t1.item_id = {$item["id"]} ";

            $comvals = $CON->select($sql);
            foreach($comvals AS $comval)
               $_COMVALS[$comval["com_id"]] = $comval["add_name"];

            $stock = 0.00;
            foreach($destsths AS $deststh)
               $stock += getItemShopStorehouseCurrentStock($CON, $agenda["req_shop_id"], $deststh["id"], $item["id"], "item");

            if($stock > 0.00 && (int)$comvals[0]["item_id"] > 0)
            {  ?>
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$item["item_title"]?></td>
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$item["item_number_prod"]?></td>
                  <?php
                  if((int)$pcatdata["cat_itemreg_width"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_width"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_length"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_length"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_gsm"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_gsm"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_kg"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_kg"],2)?></td>
                     <?php
                  }
                  foreach(array_keys($_TRANSCOM) AS $comid)
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$_COMVALS[$comid]?>&nbsp;</td>
                     <?php
                  }
                  ?>
                  <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;"><?=printPrice($stock,10)?></td>
                  <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;cursor:pointer"
                  onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.php?itemid=<?=$item["id"]?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                  title="Entrada<?=$_btnsuffix?>"><i class="fa fa-fw fa-download"></i></td>
                  <?php
                  $style = ((int)$_REQUEST["sql_catid"] != 6) ? "opacity:0.5; cursor:default; pointer-events:none;"
                                                                 : "opacity:1; cursor:pointer; pointer-events:auto;";

                  $fancyitemrefid = $item["id"];
                  if((int)$item["item_used_parent_id"])
                     $fancyitemrefid = $item["item_used_parent_id"];
                  ?>
                  <td class="tdnrm" id="materialTd" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;cursor:pointer;<?=$style?>"
                     onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.reingreso.php?itemid=<?=$fancyitemrefid?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                     title="Salida<?=$_btnsuffix?>"><i class="fa fa-fw fa-upload"></i>
                  </td>
               </tr>
               <?php
               $hasstock = true;
            }
         }
         if(!$hasstock)
         {  ?>
            <tr>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;" colspan="20" align="center">
                  <br>
                  <b class="msg_save_err">No hay existencias en la categoria seleccionada.</b>
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
   <?php
}
?>
</form>