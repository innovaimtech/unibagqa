<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: Ingreso de Itemas de Propuestas de DiseÃ±os
//----------------------------------------------------------------------------------
if((int)$_REQUEST["saveAmts"])
{

   $currtme = time();
   $_REQUEST["pro_dis_detalle_codigo"]       = trim(addslashes($_REQUEST["pro_dis_detalle_codigo"]));
   $_REQUEST["pro_dis_detalle_fecha"]        = explode(".", $_REQUEST["pro_dis_detalle_fecha"]);
   $_REQUEST["pro_dis_detalle_fecha"]        = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["pro_dis_detalle_fecha"][1], $_REQUEST["pro_dis_detalle_fecha"][0], $_REQUEST["pro_dis_detalle_fecha"][2]);
   $_REQUEST["pro_dis_detalle_descripcion"]  = trim(addslashes($_REQUEST["pro_dis_detalle_descripcion"]));
   $_REQUEST["pro_dis_detalle_status"]       = (int)$_REQUEST["pro_dis_detalle_status"];


   if($_REQUEST["id_detalle"] == 0)
   {
      $sql = "insert into pro_dis_detalle(pro_dis_detalle_items_id
                                    , pro_dis_detalle_codigo  
                                    , pro_dis_detalle_fecha
                                    , pro_dis_detalle_descripcion
                                    , pro_dis_detalle_user_cr
                                    , pro_dis_detalle_fecha_cr
                                    , pro_dis_detalle_status
                                    , pro_dis_detalle_fecha_md
                                    , pro_dis_detalle_user_md
                                    , pro_dis_detalle_observa)
              select {$_REQUEST["id_item"]}
                  , '{$_REQUEST["pro_dis_detalle_codigo"]}'
                  , {$_REQUEST["pro_dis_detalle_fecha"]}
                  , '{$_REQUEST["pro_dis_detalle_descripcion"]}'
                  , {$_SESSION["user_id"]}
                  , {$currtme} 
                  , {$_REQUEST["pro_dis_detalle_status"]}
                  , {$currtme}
                  , {$_SESSION["user_id"]} 
                  , '{$_REQUEST["pro_dis_detalle_observa"]}'

            ";
      $res = $CON->no_result($sql); 
      $savemsg = getSaveMessage($res);

      $sql = " select MAX(pro_dis_detalle_id) as 'thisid'
               from pro_dis_detalle " ;
      $sorder = $CON->select($sql);
      $_REQUEST["id_detalle"] = $sorder[0]["thisid"];
   }
   else
   {
         $sql = "update pro_dis_detalle set pro_dis_detalle_descripcion = '{$_REQUEST["pro_dis_detalle_descripcion"]}'
                                           ,pro_dis_detalle_status      = {$_REQUEST["pro_dis_detalle_status"]}
                                           ,pro_dis_detalle_fecha_md    = {$currtme} 
                                           ,pro_dis_detalle_user_md     = {$_SESSION["user_id"]}
                                           ,pro_dis_detalle_observa     = '{$_REQUEST["pro_dis_detalle_observa"]}'
                     where pro_dis_detalle_id = {$_REQUEST["id_detalle"]}
                ";
         $res = $CON->no_result($sql); 
         $savemsg = getSaveMessage($res);
   }
   


   /*
   ?>
   <script language="JavaScript">
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   exit;
   */
  
}


//----------------------------------------------------------------------------------
$sql = " select t1.*
              , t2.add_name as modelobolsa
         from pro_dis_items t1
            inner join tran_comments_vals t2 on t1.pro_dis_items_modelo_bolsa = t2.id
         where t1.pro_dis_items_pro_id = {$_REQUEST["id"]}
            and t1.pro_dis_items_id = {$_REQUEST["id_item"]}
         ";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
//----------------------------------------------------------------------------------
// busqueda de Modelo de Bolsas
/*
      $sql = " select id         as id
                  , add_name   as bolsa
                  from tran_comments_vals 
                  where add_com_id = 20
                     and add_status > 0" ;
      $modelobolsas = $CON->select($sql);
*/
//----------------------------------------------------------------------------------
// Medidas de Bolsa
$sql = "select id as codigo, med_name as descripcion
          from prod_medidas
          where id = {$headdata["pro_dis_items_medida_bolsa"]} ";
$medidabolsas = $CON->select($sql);
$medidabolsas = $medidabolsas[0];
//----------------------------------------------------------------------------------
// Materialidad
$sql = "select fabt_code, fabt_name from fabric_types where fabt_status > 0 and fabt_code = '{$headdata["pro_dis_items_materialidad"]}'";
$materialidades = $CON->select($sql);
$materialidades = $materialidades[0];
//----------------------------------------------------------------------------------
/* CARGAR PIE DE IMPRENTA */
$sql = "select * from parametros where tabla = 'PIEIMPRENTA'";
$pieimprenta = $CON->select($sql);
/*
//----------------------------------------------------------------------------------
if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('d.m.Y', time() - (86400 * 60));
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
$_REQUEST["sord_number"]         = trim(addslashes($_REQUEST["sord_number"]));
$sqldate_from                    = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to                      = getDateFromString($_REQUEST["sql_dateto"], false);

//----------------------------------------------------------------------------------
*/
$sql = " select t1.*
         from pro_dis_detalle t1
         where t1.pro_dis_detalle_id = {$_REQUEST["id_detalle"]}
         order by t1.pro_dis_detalle_id desc ";
$items = $CON->select($sql);
$items = $items[0];

// busca datos de propuesta cabecera
$sql = " select   t5.user_firstname as 'upd_firstname'
                , t5.user_lastname  as 'upd_lastname'
         from user t5 where {$items["pro_dis_detalle_user_cr"]}  = t5.id ";

$usuario1 = $CON->select($sql);
$usuario1 = $usuario1[0];

// busca datos de propuesta cabecera
$sql = " select  t6.user_firstname as 'crt_firstname'
               , t6.user_lastname  as 'crt_lastname'
         from user t6 where {$items["pro_dis_detalle_user_md"]}  = t6.id ";
$usuario2 = $CON->select($sql);
$usuario2 = $usuario2[0];

//----------------------------------------------------------------------------------
$sql = " select pro_dis_items_color_manilla as CodigoColorManilla
               ,t2.add_name                 as DescripcionColorManilla
               ,pro_dis_items_color_tela    as CodigoColorTela
               ,t3.add_name                 as DescripcionColorTela
            from pro_dis_items
               INNER JOIN tran_comments_vals t2 ON t2.id = pro_dis_items_color_manilla
               INNER JOIN tran_comments_vals t3 ON t3.id = pro_dis_items_color_tela
         where pro_dis_items_id = {$_REQUEST["id_item"]}";
$colors = $CON->select($sql);
$colors = $colors[0];

//----------------------------------------------------------------------------------
$sql = "select * from pro_dis_items 
          where pro_dis_items_id = {$_REQUEST["id"]}";
$codigo = $CON->select($sql);
$codigo = $codigo[0];
$items["pro_dis_detalle_descripcion"] = $headdata["pro_dis_items_descripcion"];
//----------------------------------------------------------------------------------
$sql = "Select * from tran_docs
         where doc_tran_id = {$_REQUEST["id_detalle"]}
         and doc_tran_type = 'versiones' " ;
$versiones = $CON->select($sql);
?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>

   <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="id_item" value="<?=$_REQUEST["id_item"]?>">
   <input type="hidden" name="id_detalle" value="<?=$_REQUEST["id_detalle"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="saveAmts" value="">

   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         
         <?=Nifty_printH("box2", "980", 0)?>
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col>
                  <col>
                  <col>
                  <col>
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="4">Solicitud <?=$headdata["pro_dis_items_codigo"]?></td>
               </tr>
               <tr>
                  <td class="content_rowl">Solicitud</td>
                  <td class="content_row"><?=$headdata["pro_dis_items_codigo"]?></td>
                  <td class="content_rowl">Descripción *</td>
                  <td class="content_row">
                     <input name="pro_dis_detalle_descripcion" type="text" class="text" style="width:360px" value="<?=$items["pro_dis_detalle_descripcion"]?>"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)" readonly>
                  </td>
               </tr>
               <tr>
                  <td class="content_rowl">Version</td>
                  <td class="content_row">
                     <?php
                        if($items["pro_dis_detalle_codigo"] == "")
                        {
                           $sql = "select count(*)+1 as numero_version from pro_dis_detalle where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
                           $numero_version = $CON->select($sql);
                           $numero_version = $numero_version[0];
      
                           $items["pro_dis_detalle_codigo"] = $headdata["pro_dis_items_codigo"]."_V".$numero_version["numero_version"];
                        }
                     ?>
                     <input name="pro_dis_detalle_codigo" type="text" class="text" 
                     value="<?=$items["pro_dis_detalle_codigo"]?>"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  </td>
                  <td class="content_rowl">Fecha</td>
                  <td class="content_row">
                     <?php
                        if($_REQUEST["pro_dis_detalle_fecha"] == "")
                        {
                           $items["pro_dis_detalle_fecha"] = time();
                        }
                     ?>
                     <input type="text"  id="pro_dis_detalle_fecha" name="pro_dis_detalle_fecha" style="width:75"
                     class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date("d.m.Y",$items["pro_dis_detalle_fecha"])?>">
                  </td>
               </tr>
               <tr>
                  <td class="content_rowl">Estado</td>
                  <td class="content_row">
                     <?php
                        if($items["pro_dis_detalle_status"] == 0)
                           $items["pro_dis_detalle_status"] = "2";
                     ?>
                     <select class="text" style="width:330px;" name="pro_dis_detalle_status" id="pro_dis_detalle_status" onfocus="markfield(this,0)" onblur="markfield(this,1)" >
                              <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                              <option value="1" <?if($items["pro_dis_detalle_status"] == "1") echo "selected"?>>Solicitada</option>
                              <option value="2" <?if($items["pro_dis_detalle_status"] == "2") echo "selected"?>>En proceso</option>
                              <option value="3" <?if($items["pro_dis_detalle_status"] == "3") echo "selected"?>>Terminada</option>
                              <option value="4" <?if($items["pro_dis_detalle_status"] == "4") echo "selected"?>>Anulada</option>
                     </select>
                  </td>
                  <td class="content_row"></td>
                  <td class="content_row"></td>
               </tr>
               <tr>
                  <td class="content_rowl" valign="top">Otros Detalles</td>
                  <td class="content_row" colspan="3">
                        <textarea class="text" style="width:100%; height:90px" name="pro_dis_detalle_observa" <?=$rdlo?>
                        onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($items["pro_dis_detalle_observa"])?></textarea>
                  </td>
               </tr>
               <tr>
                  <td class="content_rowl">Creado por</td>
                  <td class="content_row"><?=$usuario2["crt_firstname"]?> <?=$usuario2["crt_lastname"]?>&nbsp;</td>
                  <td class="content_rowl">Cambiado por</td>
                  <td class="content_row"><?=$usuario1["upd_firstname"]?> <?=$usuario1["upd_lastname"]?>&nbsp;</td>
               </tr>
               <tr>
                  <td class="content_rowl">Creado</td>
                  <td class="content_row"><?=date('d.m.Y H:i:s', $items["pro_dis_detalle_fecha_cr"])?></td>
                  <td class="content_rowl">Cambiado</td>
                  <td class="content_row"><?=date('d.m.Y H:i:s',$items["pro_dis_detalle_fecha_md"])?></td>
               </tr>
            </table>
         <?=Nifty_printF(false)?>
         <br>
      </td>
   </tr>
   <tr>
      <td>
         <?php
         {  ?>
            <?=Nifty_printH("boxopt_b", "980",0)?>
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
               <td width="130">
                  <?php
                     printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign2&id={$_REQUEST["id"]}&id_item={$_REQUEST["id_item"]}","","arrow-180",150);
                  ?>
               </td>
               <td>&nbsp;</td>
               <td align="right" width="130" style="padding-right:5px">
                  <?php
                     if($_REQUEST["id_detalle"] != 0)
                     {
                        printButton("Versiones Adjuntas", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=versiones&id={$_REQUEST["id_detalle"]}', 'iframe', 850, 450, 'auto')", "scanner--plus",150);
                     }
                  ?>
               </td>
               <td align="right" width="130" style="padding-right:5px">
                  <?php
                     if($_REQUEST["id_detalle"] == 0)
                     {
                        $opcion = "Grabar Version";
                     }
                     else
                     {
                        $opcion = "Actualiza Version";
                     }
                    printButton($opcion, "postnav_save", "javascript: deactivateFormChange()", "document.xform_itemsearch.saveAmts.value='1';document.xform_itemsearch.subexec.value='add';submitForm(document.xform_itemsearch);", "tick-circle-frame", 150);            
                  ?>
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

   </form>
   <iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>
<?php
