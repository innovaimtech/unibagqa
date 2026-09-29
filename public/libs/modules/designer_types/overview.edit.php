<?php
// desarrollo fgarrido
// Fecha: 13/02/2024
// Descripcion: Modulo de propuestas de diseños.

$sql = " select *  
            from pro_dis
            where
            id = {$_REQUEST["id"]} ";
$sorddata = $CON->select($sql);
$sorddata = $sorddata[0];



if($_REQUEST["subcatexec"] == "")
   if((int)$_REQUEST["id"] && $sorddata["pro_dis_descripcion"] != "")
      $_REQUEST["subcatexec"] = "assign";
   else
      $_REQUEST["subcatexec"] = "basic";

if((int)$_REQUEST["id"])
{
   /*
   $sql = " select *  
            from pro_dis
            where
            id = {$_REQUEST["id"]} ";
   $sorddata = $CON->select($sql);
   $sorddata = $sorddata[0];
   */
   
   $title = "Cambiar Propuesta Codigo: ".$sorddata["pro_dis_codigo"];

   // busca datos de propuesta cabecera
   $sql = " select t1.*
                  , t3.company_short
                  , t4.shop_name
                  , t5.user_firstname 'upd_firstname'
                  , t5.user_lastname  'upd_lastname'
                  , t6.user_firstname 'crt_firstname'
                  , t6.user_lastname  'crt_lastname'
            from pro_dis t1
            LEFT OUTER JOIN company_data t3 ON t1.pro_dis_company_id = t3.id 
            LEFT OUTER JOIN company_shops t4 ON t1.pro_dis_shop_id = t4.id 
            LEFT OUTER JOIN user t5          ON t1.pro_dis_user_md  = t5.id
            LEFT OUTER JOIN user t6          ON t1.pro_dis_user_cr  = t6.id
            where
            t1.id = {$_REQUEST["id"]}";

   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
}
else
   $title = "Agregar Propuesta";
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
</table>
<br>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("boxopt_t", "980", 0)?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      printButton("Datos basicos","postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
      ?>
   </td>
   <?php
   if($_REQUEST["pro_dis_descripcion"] != "" || $headdata["pro_dis_descripcion"] != "")
      {
      ?>
         <td width="20%" style="padding-right:5px">
            <?php
            printButton("Detalle de Propuesta", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign&id={$_REQUEST["id"]}&id_item={$posdata[$x]["pro_dis_items_id"]}", "", "wooden-box--plus");
            ?>
         </td>   
      <?php
      }
   ?>
   <td width="110" align="right" style="padding-right:5px">
      <?php
      $sql = "select count(*) as encontro from tran_docs where doc_tran_type = 'designer_types' and doc_tran_id = {$_REQUEST["id"]}";
      $res = $CON->select($sql);
      if($_REQUEST["id"] != "")
         printButton("Adjuntos de Referencias"." ( {$res[0]["encontro"]} )", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=designer_types&id={$_REQUEST["id"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "assign")
   require_once("data.assign.php"); // ingreso, ediatr y envio de notificaciones 
elseif($_REQUEST["subcatexec"] == "assign2")
   require_once("data.detalle.edit.php"); // LISTA DE VERSIONES
elseif($_REQUEST["subcatexec"] == "assign3")
   require_once("data.detalle.php");
?>