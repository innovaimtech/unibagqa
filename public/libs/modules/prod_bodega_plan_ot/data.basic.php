<?php
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
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="idx_xform"
onsubmit="<?if($rdlo != "") echo "return false;"?>return checkform(new Array(this.prd_plantaid))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="activate" value="">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="1080">
<tr>
   <td class="content_row_clear" valign="top">
      <?=Nifty_printH("box1", "1020")?>
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
         <td class="content_rowl">Nº CC</td>
         <td class="content_row"><?=$headdata["req_number"]?></td>
         <td class="content_rowl">Nº OT</td>
         <td class="content_row"><?=$proddata["prd_number"]?></td>
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
      <?=Nifty_printH("box2", "1020")?>
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
      <?=Nifty_printH("box2", "1020")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="260">
         <col width="180">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Adjuntos y fechas cliché o pélicula</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Descripción</td>
         <td class="content_tbl_subheader">Valor</td>
         <td class="content_tbl_subheader">Comentarios</td>
      </tr>
      <tr>
         <td class="content_rowl">
            Fecha solicitud 
            <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "película";
            ?>
         </td>
         <td class="content_row" colspan="2">
            <?php
            if($headdata["req_cliche_peli_solic_dat"] > 0) 
               echo date('d.m.Y', $headdata["req_cliche_peli_solic_dat"]);
            else
               echo "<b class=msg_save_err>N/A</b>";
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">
            Fecha recepción
            <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "película";
            ?>
         </td>
         <td class="content_row" colspan="2">
            <?php 
            if($headdata["req_cliche_peli_recep_dat"] > 0) 
               echo date('d.m.Y', $headdata["req_cliche_peli_recep_dat"]);
            else
               echo "<b class=msg_save_err>N/A</b>";
            ?>
         </td>
      </tr>
      <?php
      for($x = 0; $x < 5; $x++)
      {  
         if($headdata["req_prod_adjfile_{$x}"] != "")
         {  ?>
            <tr>
               <td class="content_rowl">Imagen #<?=($x + 1)?></td>
               <td class="content_row">
                  <?php
                  printButton("Mostrar imagen", "postnav", "javascript: deactivateFormChange()", "window.open('/docs.prod/{$headdata["req_prod_adjfile_{$x}"]}')", "image");
                  ?>
               </td>
               <td class="content_row"><?=$headdata["req_prod_adjcomments_{$x}"]?></td>
            </tr>
            <?php
            $_HAS_FILES = true;
         }
      }
      if(!$_HAS_FILES)
      {  ?>
         <tr>
            <td class="content_rowl">Imagenes</td>
            <td class="content_row"><b class=msg_save_err>N/A</b></td>
            <td class="content_row">&nbsp;</td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF(false)?>
      <br>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box2", "1020")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Fechas de entrega y observaciones</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">Cantidad</td>
         <td class="content_tbl_subheader content_row_os">Fecha entrega</td>
         <td class="content_tbl_subheader content_row_os">Planta y observaciones</td>
      </tr>
      <?php
      $rowcount = count($prodplans) +3;
      if($rdlo != "")
         $rowcount = count($prodplans);
      for($x = 0; $x < $rowcount; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><?=printPrice($prodplans[$x]["prodplan_amt"])?></td>
            <td class="content_row"><?=date("d.m.Y", $prodplans[$x]["prodplan_date"])?></td>
            <?php
            if(!$x)
            {  ?>
               <td class="content_row" valign="top" rowspan="<?=$rowcount?>">
                  <?php
                  foreach($plantas AS $planta)
                  {  
                     if($planta["id"] == $proddata["prd_plantaid"])
                     {  ?>
                        <?=$planta["planta_name"]?><br>
                        <?php
                     }
                  }
                  ?>
                  <div style="height:3px"></div>
                  <?=$proddata["prd_desc"]?>
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
<tr>
   <td>
      <?=Nifty_printH("box2", "1020")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="260">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Proceso / máquinas de fabricacción compatibles</td>
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
               type_ant_status > 0 ";
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
                  equipo_planta_id  = {$proddata["prd_plantaid"]}
                  order by equipo_name";
         $equipos = $CON->select($sql);
         if(count($equipos) && $equipos != false)
         {
            $showline = false;
            if($rdlo != "")
            {
               foreach($equipos AS $equipo)
                  if((int)$_EQUIPO_ACT[$equipo["id"]])
                     $showline = true;
            }
            else
               $showline = true;

            if($showline)
            {  ?>
               <tr bgcolor="<?=getRowColor($px)?>">
                  <td class="content_row_os"><?=$equipotype["type_ant_title"]?></td>
                  <td class="content_row_os">
                     <?php
                     foreach($equipos AS $equipo)
                     {
                        if($rdlo != "")
                        {
                           if((int)$_EQUIPO_ACT[$equipo["id"]])
                           {  ?>
                              <?=$equipo["equipo_name"]?><br>
                              <?php
                           }
                        }
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
</table>
<br>
<!--
<?=Nifty_printH("boxopt_b", "1020")?>
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
      if((int)$proddata["id"] && $_PLAN_AMT == $thispos["item_amount"] && is_array($_EQUIPO_ACT) && count($_EQUIPO_ACT) > 0 &&
         $_HAS_FILES && (int)$headdata["req_cliche_peli_solic_dat"] && (int)$headdata["req_cliche_peli_recep_dat"])
      {  ?>
         <td align="right" width="130" style="padding-left:5px" id="idx_finalbtnx">
            <?php
            printButton("Activar para planificación", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.idx_xform.activate.value='1';submitForm(document.idx_xform) }", "tick-circle-frame", 180);
            ?>
         </td>
         <?php
      }
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
-->
</form>