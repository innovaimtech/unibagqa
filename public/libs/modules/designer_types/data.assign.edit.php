<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: Ingreso de Itemas de Propuestas de Diseño
//----------------------------------------------------------------------------------
/*
echo("Identificador : ".$_REQUEST["id"]);
echo("Solicitud     : ".$_REQUEST["id_item"]);
echo("Grabar : ".$_REQUEST["saveAmts"]);
*/
//----------------------------------------------------------------------------------
   // Graba solicitud 

   if((int)$_REQUEST["edit"] && (int)$_REQUEST["id_item"])
   {
      $_REQUEST["saveAmts"] = "";
      // $sql = "update pro_dis_items set pro_dis_items_status = 0 where pro_dis_items_pro_id = {$_REQUEST["id"]}";
      $sql = "update pro_dis_items set pro_dis_items_status = 0 where pro_dis_items_id = {$_REQUEST["id_item"]}";
      $CON->no_result($sql);
   }
   if((int)$_REQUEST["saveAmts"])
   {
      $_REQUEST["saveAmts"] = "";

      $currtme = time();
      $_REQUEST["pro_dis_items_cantidad_color"] = 0;
      while($x <= 10)
      {  
         if($_REQUEST["pro_dis_items_color{$x}"] != "")
         {
            $_REQUEST["pro_dis_items_cantidad_color"]++;
         }
         $x++;
      }
      $_REQUEST["pro_dis_items_codigo"]          = trim(addslashes($_REQUEST["pro_dis_items_codigo"]));
      $_REQUEST["pro_dis_items_fecha"]           = explode(".", $_REQUEST["pro_dis_items_fecha"]);
      $_REQUEST["pro_dis_items_fecha"]           = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["pro_dis_items_fecha"][1], $_REQUEST["pro_dis_items_fecha"][0], $_REQUEST["pro_dis_items_fecha"][2]);
      $_REQUEST["pro_dis_items_descripcion"]     = trim(addslashes($_REQUEST["pro_dis_items_descripcion"]));
      $_REQUEST["pro_dis_items_observacion"]     = trim(addslashes($_REQUEST["pro_dis_items_observacion"]));
      $_REQUEST["pro_dis_items_observacion1"]     = trim(addslashes($_REQUEST["pro_dis_items_observacion1"]));
   
      $_REQUEST["pro_dis_items_modelo_bolsa"]    = (int)$_REQUEST["pro_dis_items_modelo_bolsa"];
      $_REQUEST["pro_dis_items_medida_bolsa"]    = trim(addslashes($_REQUEST["pro_dis_items_medida_bolsa"]));
      $_REQUEST["pro_dis_items_color_tela"]      = trim(addslashes($_REQUEST["pro_dis_items_color_tela"]));
      $_REQUEST["pro_dis_items_color_manilla"]   = trim(addslashes($_REQUEST["pro_dis_items_color_manilla"]));
      $_REQUEST["pro_dis_items_materialidad"]    = trim(addslashes($_REQUEST["pro_dis_items_materialidad"]));
      $_REQUEST["pro_dis_items_tipo_impresion"]  = trim(addslashes($_REQUEST["pro_dis_items_tipo_impresion"]));
      $_REQUEST["pro_dis_items_pie_imprenta"]    = trim(addslashes($_REQUEST["pro_dis_items_pie_imprenta"]));

      $_REQUEST["pro_dis_items_codigo_barra"] = trim(addslashes($_REQUEST["pro_dis_items_codigo_barra"]));

      $_REQUEST["pro_dis_items_cantidad_color"]  = (int)$_REQUEST["pro_dis_items_cantidad_color"];
      $_REQUEST["pro_dis_items_status"]          = (INT)$_REQUEST["pro_dis_items_status"];
      
      $_REQUEST["pro_dis_items_color1"]      = $_REQUEST["pro_dis_items_color1"];
      $_REQUEST["pro_dis_items_color2"]      = $_REQUEST["pro_dis_items_color2"];
      $_REQUEST["pro_dis_items_color3"]      = $_REQUEST["pro_dis_items_color3"];
      $_REQUEST["pro_dis_items_color4"]      = $_REQUEST["pro_dis_items_color4"];
      $_REQUEST["pro_dis_items_color5"]      = $_REQUEST["pro_dis_items_color5"];
      $_REQUEST["pro_dis_items_color6"]      = $_REQUEST["pro_dis_items_color6"];
      $_REQUEST["pro_dis_items_color7"]      = $_REQUEST["pro_dis_items_color7"];
      $_REQUEST["pro_dis_items_color8"]      = $_REQUEST["pro_dis_items_color8"];
      $_REQUEST["pro_dis_items_color9"]      = $_REQUEST["pro_dis_items_color9"];
      $_REQUEST["pro_dis_items_color10"]     = $_REQUEST["pro_dis_items_color10"];
     
      $_REQUEST["pro_dis_items_dorso1"]      = (int)$_REQUEST["pro_dis_items_dorso1"];
      $_REQUEST["pro_dis_items_dorso2"]      = (int)$_REQUEST["pro_dis_items_dorso2"];
      $_REQUEST["pro_dis_items_dorso3"]      = (int)$_REQUEST["pro_dis_items_dorso3"];
      $_REQUEST["pro_dis_items_dorso4"]      = (int)$_REQUEST["pro_dis_items_dorso4"];
      $_REQUEST["pro_dis_items_dorso5"]      = (int)$_REQUEST["pro_dis_items_dorso5"];
      $_REQUEST["pro_dis_items_dorso6"]      = (int)$_REQUEST["pro_dis_items_dorso6"];
      $_REQUEST["pro_dis_items_dorso7"]      = (int)$_REQUEST["pro_dis_items_dorso7"];
      $_REQUEST["pro_dis_items_dorso8"]      = (int)$_REQUEST["pro_dis_items_dorso8"];
      $_REQUEST["pro_dis_items_dorso9"]      = (int)$_REQUEST["pro_dis_items_dorso9"];
      $_REQUEST["pro_dis_items_dorso10"]     = (int)$_REQUEST["pro_dis_items_dorso10"];
   
      $_REQUEST["pro_dis_items_frente1"]     = (int)$_REQUEST["pro_dis_items_frente1"];
      $_REQUEST["pro_dis_items_frente2"]     = (int)$_REQUEST["pro_dis_items_frente2"];
      $_REQUEST["pro_dis_items_frente3"]     = (int)$_REQUEST["pro_dis_items_frente3"];
      $_REQUEST["pro_dis_items_frente4"]     = (int)$_REQUEST["pro_dis_items_frente4"];
      $_REQUEST["pro_dis_items_frente5"]     = (int)$_REQUEST["pro_dis_items_frente5"];
      $_REQUEST["pro_dis_items_frente6"]     = (int)$_REQUEST["pro_dis_items_frente6"];
      $_REQUEST["pro_dis_items_frente7"]     = (int)$_REQUEST["pro_dis_items_frente7"];
      $_REQUEST["pro_dis_items_frente8"]     = (int)$_REQUEST["pro_dis_items_frente8"];
      $_REQUEST["pro_dis_items_frente9"]     = (int)$_REQUEST["pro_dis_items_frente9"];
      $_REQUEST["pro_dis_items_frente10"]    = (int)$_REQUEST["pro_dis_items_frente10"];
      $_REQUEST["pro_dis_items_print_ancho"] = (int)$_REQUEST["pro_dis_items_print_ancho"];
      $_REQUEST["pro_dis_items_print_alto"]  = (int)$_REQUEST["pro_dis_items_print_alto"];
      $_REQUEST["pro_dis_items_opc_area"] = (int)$_REQUEST["pro_dis_items_opc_area"];

    
      if((int)$_REQUEST["id_item"] == 0 )
      {
         $sql = "insert into pro_dis_items(pro_dis_items_pro_id
                                         , pro_dis_items_codigo  
                                         , pro_dis_items_fecha
                                         , pro_dis_items_descripcion
                                         , pro_dis_items_user_cr
                                         , pro_dis_items_fecha_cr
                                         , pro_dis_items_user_md
                                         , pro_dis_items_fecha_md
                                         , pro_dis_items_modelo_bolsa
                                         , pro_dis_items_medida_bolsa
                                         , pro_dis_items_color_tela
                                         , pro_dis_items_color_manilla
                                         , pro_dis_items_materialidad
                                         , pro_dis_items_tipo_impresion
                                         , pro_dis_items_pie_imprenta
                                         , pro_dis_items_cantidad_color
                                         , pro_dis_items_color1
                                         , pro_dis_items_color2
                                         , pro_dis_items_color3
                                         , pro_dis_items_color4
                                         , pro_dis_items_color5
                                         , pro_dis_items_color6
                                         , pro_dis_items_color7
                                         , pro_dis_items_color8
                                         , pro_dis_items_color9
                                         , pro_dis_items_color10
                                         , pro_dis_items_dorso1
                                         , pro_dis_items_dorso2
                                         , pro_dis_items_dorso3
                                         , pro_dis_items_dorso4
                                         , pro_dis_items_dorso5
                                         , pro_dis_items_dorso6
                                         , pro_dis_items_dorso7
                                         , pro_dis_items_dorso8
                                         , pro_dis_items_dorso9
                                         , pro_dis_items_dorso10
                                         , pro_dis_items_frente1
                                         , pro_dis_items_frente2
                                         , pro_dis_items_frente3
                                         , pro_dis_items_frente4
                                         , pro_dis_items_frente5
                                         , pro_dis_items_frente6
                                         , pro_dis_items_frente7
                                         , pro_dis_items_frente8
                                         , pro_dis_items_frente9
                                         , pro_dis_items_frente10
                                         , pro_dis_items_status
                                         , pro_dis_items_observacion
                                         , pro_dis_items_codigo_barra
                                         , pro_dis_items_print_ancho
                                         , pro_dis_items_print_alto
                                         , pro_dis_items_opc_area)
               select {$_REQUEST["id"]}
                     , '{$_REQUEST["pro_dis_items_codigo"]}'
                     , {$_REQUEST["pro_dis_items_fecha"]}
                     , '{$_REQUEST["pro_dis_items_descripcion"]}'
                     , {$_SESSION["user_id"]}
                     , {$currtme} 
                     , {$_SESSION["user_id"]}
                     , {$currtme} 
                     , '{$_REQUEST["pro_dis_items_modelo_bolsa"]}'
                     , '{$_REQUEST["pro_dis_items_medida_bolsa"]}'
                     , '{$_REQUEST["pro_dis_items_color_tela"]}'
                     , '{$_REQUEST["pro_dis_items_color_manilla"]}'
                     , '{$_REQUEST["pro_dis_items_materialidad"]}'
                     , '{$_REQUEST["pro_dis_items_tipo_impresion"]}'
                     , '{$_REQUEST["pro_dis_items_pie_imprenta"]}'
                     , {$_REQUEST["pro_dis_items_cantidad_color"]}
                     , '{$_REQUEST["pro_dis_items_color1"]}'
                     , '{$_REQUEST["pro_dis_items_color2"]}'
                     , '{$_REQUEST["pro_dis_items_color3"]}'
                     , '{$_REQUEST["pro_dis_items_color4"]}'
                     , '{$_REQUEST["pro_dis_items_color5"]}'
                     , '{$_REQUEST["pro_dis_items_color6"]}'
                     , '{$_REQUEST["pro_dis_items_color7"]}'
                     , '{$_REQUEST["pro_dis_items_color8"]}'
                     , '{$_REQUEST["pro_dis_items_color9"]}'
                     , '{$_REQUEST["pro_dis_items_color10"]}'
                     , {$_REQUEST["pro_dis_items_dorso1"]}
                     , {$_REQUEST["pro_dis_items_dorso2"]}
                     , {$_REQUEST["pro_dis_items_dorso3"]}
                     , {$_REQUEST["pro_dis_items_dorso4"]}
                     , {$_REQUEST["pro_dis_items_dorso5"]}
                     , {$_REQUEST["pro_dis_items_dorso6"]}
                     , {$_REQUEST["pro_dis_items_dorso7"]}
                     , {$_REQUEST["pro_dis_items_dorso8"]}
                     , {$_REQUEST["pro_dis_items_dorso9"]}
                     , {$_REQUEST["pro_dis_items_dorso10"]}
                     , {$_REQUEST["pro_dis_items_frente1"]}
                     , {$_REQUEST["pro_dis_items_frente2"]}
                     , {$_REQUEST["pro_dis_items_frente3"]}
                     , {$_REQUEST["pro_dis_items_frente4"]}
                     , {$_REQUEST["pro_dis_items_frente5"]}
                     , {$_REQUEST["pro_dis_items_frente6"]}
                     , {$_REQUEST["pro_dis_items_frente7"]}
                     , {$_REQUEST["pro_dis_items_frente8"]}
                     , {$_REQUEST["pro_dis_items_frente9"]}
                     , {$_REQUEST["pro_dis_items_frente10"]}
                     , 0
                     , '{$_REQUEST["pro_dis_items_observacion"]}' 
                     , '{$_REQUEST["pro_dis_items_codigo_barra"]}' 
                     , {$_REQUEST["pro_dis_items_print_ancho"]}
                     , {$_REQUEST["pro_dis_items_print_alto"]}
                     , {$_REQUEST["pro_dis_items_opc_area"]} ";
         $res = $CON->no_result($sql); 

         $savemsg = getSaveMessage($res);
         $sql = " select MAX(pro_dis_items_id) as 'thisid'
                  from pro_dis_items " ;
         $sorder = $CON->select($sql);
         $_REQUEST["id_item"] = $sorder[0]["thisid"];

         $sql = "update pro_dis set pro_dis_status = 0 where id = {$_REQUEST["id"]}";
         $CON->no_result($sql);

         /* creacion de codigo de barra */
         $sql = "select * from parametros where tabla = 'BARRA' and codigo = '{$_REQUEST["pro_dis_items_codigo_barra"]}'";
         $res = $CON->select($sql);
         if(!$res)
         {
            $sql = "insert into parametros(tabla,codigo,descripcion,fecha) values('BARRA','{$_REQUEST["pro_dis_items_codigo_barra"]}','{$_REQUEST["pro_dis_items_descripcion"]}',{$currtme}) ";
            $res = $CON->no_result($sql);
         }
         /* fin de codigo de barra */
         
      }
      else
      {
         $_REQUEST["pro_dis_asignado"]  = (int)$_REQUEST["pro_dis_asignado"];
         $sql = " update pro_dis_items set 
                   pro_dis_items_fecha           =  {$_REQUEST["pro_dis_items_fecha"]}
                  ,pro_dis_items_descripcion     = '{$_REQUEST["pro_dis_items_descripcion"]}'
                  ,pro_dis_items_modelo_bolsa    =  {$_REQUEST["pro_dis_items_modelo_bolsa"]}
                  ,pro_dis_items_medida_bolsa    = '{$_REQUEST["pro_dis_items_medida_bolsa"]}'
                  ,pro_dis_items_color_tela      = '{$_REQUEST["pro_dis_items_color_tela"]}'
                  ,pro_dis_items_materialidad    = '{$_REQUEST["pro_dis_items_materialidad"]}'
                  ,pro_dis_items_tipo_impresion  = '{$_REQUEST["pro_dis_items_tipo_impresion"]}'
                  ,pro_dis_items_pie_imprenta    = '{$_REQUEST["pro_dis_items_pie_imprenta"]}'
                  ,pro_dis_items_cantidad_color  =  {$_REQUEST["pro_dis_items_cantidad_color"]}
                  ,pro_dis_items_color1          = '{$_REQUEST["pro_dis_items_color1"]}'
                  ,pro_dis_items_color2          = '{$_REQUEST["pro_dis_items_color2"]}'
                  ,pro_dis_items_color3          = '{$_REQUEST["pro_dis_items_color3"]}'
                  ,pro_dis_items_color4          = '{$_REQUEST["pro_dis_items_color4"]}'
                  ,pro_dis_items_color5          = '{$_REQUEST["pro_dis_items_color5"]}'
                  ,pro_dis_items_color6          = '{$_REQUEST["pro_dis_items_color6"]}'
                  ,pro_dis_items_color7          = '{$_REQUEST["pro_dis_items_color7"]}'
                  ,pro_dis_items_color8          = '{$_REQUEST["pro_dis_items_color8"]}'
                  ,pro_dis_items_color9          = '{$_REQUEST["pro_dis_items_color9"]}'
                  ,pro_dis_items_color10         = '{$_REQUEST["pro_dis_items_color10"]}'
                  ,pro_dis_items_dorso1          = {$_REQUEST["pro_dis_items_dorso1"]}
                  ,pro_dis_items_dorso2          = {$_REQUEST["pro_dis_items_dorso2"]}
                  ,pro_dis_items_dorso3          = {$_REQUEST["pro_dis_items_dorso3"]}
                  ,pro_dis_items_dorso4          = {$_REQUEST["pro_dis_items_dorso4"]}
                  ,pro_dis_items_dorso5          = {$_REQUEST["pro_dis_items_dorso5"]}
                  ,pro_dis_items_dorso6          = {$_REQUEST["pro_dis_items_dorso6"]}
                  ,pro_dis_items_dorso7          = {$_REQUEST["pro_dis_items_dorso7"]}
                  ,pro_dis_items_dorso8          = {$_REQUEST["pro_dis_items_dorso8"]}
                  ,pro_dis_items_dorso9          = {$_REQUEST["pro_dis_items_dorso9"]}
                  ,pro_dis_items_dorso10         = {$_REQUEST["pro_dis_items_dorso10"]}
                  ,pro_dis_items_frente1         = {$_REQUEST["pro_dis_items_frente1"]}
                  ,pro_dis_items_frente2         = {$_REQUEST["pro_dis_items_frente2"]}
                  ,pro_dis_items_frente3         = {$_REQUEST["pro_dis_items_frente3"]}
                  ,pro_dis_items_frente4         = {$_REQUEST["pro_dis_items_frente4"]}
                  ,pro_dis_items_frente5         = {$_REQUEST["pro_dis_items_frente5"]}
                  ,pro_dis_items_frente6         = {$_REQUEST["pro_dis_items_frente6"]}
                  ,pro_dis_items_frente7         = {$_REQUEST["pro_dis_items_frente7"]}
                  ,pro_dis_items_frente8         = {$_REQUEST["pro_dis_items_frente8"]}
                  ,pro_dis_items_frente9         = {$_REQUEST["pro_dis_items_frente9"]}
                  ,pro_dis_items_frente10        = {$_REQUEST["pro_dis_items_frente10"]}
                  ,pro_dis_items_status          = {$_REQUEST["pro_dis_items_status"]}
                  ,pro_dis_items_color_manilla   = '{$_REQUEST["pro_dis_items_color_manilla"]}'
                  ,pro_dis_items_observacion     = '{$_REQUEST["pro_dis_items_observacion"]}'
                  ,pro_dis_items_user_md         = {$_SESSION["user_id"]}
                  ,pro_dis_items_fecha_md        = {$currtme}
                  ,pro_dis_items_codigo_barra    = '{$_REQUEST["pro_dis_items_codigo_barra"]}'
                  ,pro_dis_items_print_ancho           = {$_REQUEST["pro_dis_items_print_ancho"]}
                  ,pro_dis_items_print_alto            = {$_REQUEST["pro_dis_items_print_alto"]}
               where pro_dis_items_id = {$_REQUEST["id_item"]}
            ";
         $res = $CON->no_result($sql); 
         $savemsg = getSaveMessage($res);

         /* creacion de codigo de barra */
         $sql = "select * from parametros where tabla = 'BARRA' and codigo = '{$_REQUEST["pro_dis_items_codigo_barra"]}'";
         $res = $CON->select($sql);
         if(!$res)
         {
            $sql = "insert into parametros(tabla,codigo,descripcion,fecha) values('BARRA','{$_REQUEST["pro_dis_items_codigo_barra"]}','{$_REQUEST["pro_dis_items_descripcion"]}',{$currtme}) ";
            $res = $CON->no_result($sql);
         }

         /* fin de codigo de barra */

      }
   
   }
//--------------------------------------------------------------------------------------------------------------------
if((int)$_REQUEST["senddesignmail"])
{
   $sql = "update pro_dis set pro_dis_status = 1 where id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = " update pro_dis_items set pro_dis_items_status = 1 where pro_dis_items_id = {$_REQUEST["id_item"]}";
   $CON->no_result($sql);

   $sql = " select * from pro_dis_items 
            where pro_dis_items_id = {$_REQUEST["id_item"]}";
   $propuesta = $CON->select($sql);
   generatePropuestaDesignMail($CON, $propuesta);
}
//--------------------------------------------------------------------------------------------------------------------
$sql = " select t1.*
         from pro_dis t1
         where
         t1.id = {$_REQUEST["id"]}";

$headdata = $CON->select($sql);
$headdata = $headdata[0];

//--------------------------------------------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
         t2.add_status  = 1
         order by t2.add_name";
$colors = $CON->select($sql);
//--------------------------------------------------------------------------------------------------------------------
// busqueda de Modelo de Bolsas
$sql = " select id         as id
              , add_name   as bolsa
            from tran_comments_vals 
             where add_com_id = 20
               and add_status > 0" ;
$modelobolsas = $CON->select($sql);
//--------------------------------------------------------------------------------------------------------------------
// Medidas de Bolsa
$sql = "select id as codigo, med_name as descripcion
            from prod_medidas
         where med_status > 0";
$medidabolsas = $CON->select($sql);
//--------------------------------------------------------------------------------------------------------------------
// MAterialidad
$sql = "select fabt_code, fabt_name from fabric_types where fabt_status > 0";
$materialidades = $CON->select($sql);
//--------------------------------------------------------------------------------------------------------------------
/* CARGAR PIE DE IMPRENTA */
$sql = "select * from parametros where tabla = 'PIEIMPRENTA'";
$pieimprenta = $CON->select($sql);

$sql = "select t1.*
              ,count(*) as contador
          from pro_dis_items t1
          where t1.pro_dis_items_pro_id = {$_REQUEST["id"]}
          order by t1.pro_dis_items_id desc ";

$contador = $CON->select($sql);

$_REQUEST["id_item"] = (int)$_REQUEST["id_item"];

if((int)$_REQUEST["id_item"])
{
   $sql = " select t1.*
                  ,concat(t2.user_firstname,' ',t2.user_lastname) as user_cr
                  ,concat(t3.user_firstname,' ',t3.user_lastname) as user_md
            from pro_dis_items t1
                  inner join user t2 on pro_dis_items_user_cr = t2.id
                  inner join user t3 on pro_dis_items_user_md = t3.id
            where t1.pro_dis_items_pro_id = {$_REQUEST["id"]}
            and t1.pro_dis_items_id = (case when {$_REQUEST["id_item"]} = 0 then t1.pro_dis_items_id else {$_REQUEST["id_item"]} end)
            order by t1.pro_dis_items_id desc 
            limit 1";
         
   $items = $CON->select($sql);
   $items = $items[0];
}

//--------------------------------------------------------------------------------------------------------------------
// Buscador de Detalles
$sql = "select t1.* from pro_dis_detalle t1
          where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
$detalle = $CON->select($sql);

// busca items_A
$sql = "select * from pro_dis_items
          where pro_dis_items_pro_id = {$_REQUEST["id"]}
          order by pro_dis_items_id";
$posdata = $CON->select($sql);
//--------------------------------------------------------------------------------------------------------------------
$sql = "select u.id
            , concat(user_firstname,' ',user_lastname) as usuario_asignado
         from user u
            inner join  user_group ug on ug.user_id = u.id and ug.group_id = 20
            where u.user_status > 0";
$asignados = $CON->select($sql);    
//--------------------------------------------------------------------------------------------------------------------

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
   function proformcheck(obj)
   {
      var frmchk = checkform(new Array(obj.pro_dis_items_descripcion, obj.pro_dis_items_modelo_bolsa, obj.pro_dis_items_medida_bolsa
      , obj.pro_dis_items_materialidad, obj.pro_dis_items_color_tela, obj.pro_dis_items_tipo_impresion, obj.pro_dis_items_color_manilla
      , obj.pro_dis_items_pie_imprenta, obj.pro_dis_items_print_ancho, obj.pro_dis_items_print_alto));
      if(!frmchk)
      {
         return false;
      }
      {
         return true;
      }
   }

   /*
   function submit()
   {
      document.xform_itemsearch.submit()
   }
   */
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>

<form action="index.php" method="post" name="xform_itemsearch" id="xform_itemsearch" class="fokusfirst" onsubmit="return proformcheck(this)">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="id_item" value="<?=$_REQUEST["id_item"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="deldesignimg" value="">
<input type="hidden" name="senddesignmail" value="">
<input type="hidden" name="autoopensendmail" value="">
<input type="hidden" name="autoopenpdf" value="">
<input type="hidden" name="saveAmts" value="">
<input type="hidden" name="edit" value="">

<div>
   <?=Nifty_printH("boxopt_b", "980",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="25%">
            <col width="25%">
            <col width="25%">
            <col width="25%">
         </colgroup>
         <tr>
            <td align="left" width="25%">
               <?php
               if($_REQUEST["id_item"] !=0 )
               {
                  printButton("Versiones de Diseño", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign2&id={$_REQUEST["id"]}&id_item={$items["pro_dis_items_id"]}", "", "magnifier-left");
               }
               ?>
            </td>
         </tr>
      </table>
   <?=Nifty_printF(false)?>
   <?=Nifty_printH("box2", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <?if($_REQUEST["id_item"]==0)
         {
         ?>
             <td class="content_tbl_header" colspan="4">Ingreso de Solicitudes</td>
         <?php
         }
         else
         {
         ?>
            <td class="content_tbl_header" colspan="3">Ingreso de Solicitudes</td>
            <td class="content_tbl_header" colspan="1" align="right" style="padding:0px;padding-right:1px">
               <input type="button" class="button" value="Clonar" style="width:100px"
               onclick="if(askDel('')) { location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=assign&id=<?=$_REQUEST["id"]?>&idclonar=<?=$_REQUEST["id_item"]?>&clonexec=1'; }">
            </td>
         <?php
         }
         ?>
      </tr>
      <tr>
         <td class="content_rowl">Codigo de Solicitud</td>
         <td class="content_row">
            <?php
               if($_REQUEST["id_item"] == 0)
                  $items["pro_dis_items_codigo"] = $headdata["pro_dis_codigo"]."-".chr($contador[0]["contador"]+65);
            ?>
            <input name="pro_dis_items_codigo" type="text" class="text" 
               value="<?=$items["pro_dis_items_codigo"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" readonly>
         </td>
         <td class="content_rowl">Descripción *</td>
         <td class="content_row">
            <input name="pro_dis_items_descripcion" type="text" class="text" style="width:360px" value="<?=$items["pro_dis_items_descripcion"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fecha</td>
         <td class="content_row">
            <?php
               if($_REQUEST["pro_dis_items_fecha"] == "")
               {
                  $items["pro_dis_items_fecha"] = time();
               }
            ?>
            <input type="text"  id="pro_dis_items_fecha" name="pro_dis_items_fecha" style="width:75"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date("d.m.Y",$items["pro_dis_items_fecha"])?>">
         </td>
         <td class="content_row"></td>
         <td class="content_row"></td>
      </tr>
      <tr>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px;" name="pro_dis_items_status"  id="pro_dis_items_status" onfocus="markfield(this,0)" onblur="markfield(this,1)" >
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <option value="0" <?if($items["pro_dis_items_status"] == "0") echo "selected"?>>Ingresada</option>
               <option value="1" <?if($items["pro_dis_items_status"] == "1") echo "selected"?>>Solicitada</option>
               <option value="2" <?if($items["pro_dis_items_status"] == "2") echo "selected"?>>En proceso</option>
               <option value="3" <?if($items["pro_dis_items_status"] == "3") echo "selected"?>>Terminada</option>
               <option value="4" <?if($items["pro_dis_items_status"] == "4") echo "selected"?>>Anulada</option>
            </select>
         </td>
         <td class="content_rowl">Asignado</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px" name="pro_dis_asignado" id="pro_dis_asignado" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($asignados as $asignado)
                     {?>
                        <option value="<?=$asignado["id"]?>"
                           <?php if($asignado["id"] == $items["pro_dis_asignado"]) echo "selected"?>><?=$asignado["usuario_asignado"]?>
                        </option><?php
                     } 
               ?>
            </select>
         </td>         
      </tr>
      <tr>
         <td class="content_tbl_header" colspan="4">Detalle de las Solicitud de la Propuesta</td>
      </tr>
      <tr>
         <td class="content_rowl">Modelo de Bolsa *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="pro_dis_items_modelo_bolsa" id="pro_dis_items_modelo_bolsa" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($modelobolsas as $modelobolsa)
                     {  ?>
                        <option value="<?=$modelobolsa["id"]?>"
                        <?php if($modelobolsa["id"] == $items["pro_dis_items_modelo_bolsa"]) echo "selected"?>><?=$modelobolsa["bolsa"]?></option>
                        <?php
                     }
               ?>
            </select>
         </td>
         <td class="content_rowl">Medida de Bolsa *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="pro_dis_items_medida_bolsa" id="pro_dis_items_medida_bolsa" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($medidabolsas as $medidabolsa)
                     {?>
                        <option value="<?=$medidabolsa["codigo"]?>"
                          <?php if($medidabolsa["codigo"] == $items["pro_dis_items_medida_bolsa"]) echo "selected"?>><?=$medidabolsa["descripcion"]?></option>
                     <?php
                     }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Materialidad de Bolsa *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="pro_dis_items_materialidad" id="pro_dis_items_materialidad" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($materialidades as $materialidad)
                     {  ?>
                        <option value="<?=$materialidad["fabt_code"]?>"
                          <?php if($materialidad["fabt_code"] == $items["pro_dis_items_materialidad"]) echo "selected"?>><?=$materialidad["fabt_name"]?></option>
                          <?php
                     }
               ?>
            </select>
         </td>
         <td class="content_rowl">Color de Tela *</td>
         <td class="content_row">
            <select class="text" style="width:330px;" name="pro_dis_items_color_tela">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                  foreach($colors AS $color)
                  {  ?>
                     <option value="<?=$color["id"]?>" <?if($color["id"] == $items["pro_dis_items_color_tela"]) echo "selected"?>>
                        <?=$color["add_name"]?>
                     </option>
                     <?php
                  }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo de Impresion *</td>
         <td class="content_row">
            <select class="text" style="width:330px;" name="pro_dis_items_tipo_impresion" onfocus="markfield(this,0)" onblur="markfield(this,1)>
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <option value="FLEX" <?if($items["pro_dis_items_tipo_impresion"] == "FLEX") echo "selected"?>>Flexografia</option>
                  <option value="SERI" <?if($items["pro_dis_items_tipo_impresion"] == "SERI") echo "selected"?>>Serigrafia</option>
            </select>
         </td>
         <td class="content_rowl">Color de Manilla *</td>
         <td class="content_row">
            <select class="text" style="width:330px;" name="pro_dis_items_color_manilla">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                  foreach($colors AS $color)
                  {  ?>
                     <option value="<?=$color["id"]?>" <?if($color["id"] == $items["pro_dis_items_color_manilla"]) echo "selected"?>>
                        <?=$color["add_name"]?>
                     </option>
                     <?php
                  }
               ?>
               </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Pie imprenta *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="pro_dis_items_pie_imprenta">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php foreach ($pieimprenta as $pimprenta) { ?>
               <option value="<?=$pimprenta["codigo"]?>" <?php if ($items["pro_dis_items_pie_imprenta"] == $pimprenta["codigo"]) echo "selected"; ?>>
                  <?=$pimprenta["descripcion"]?>
               </option>
               <?php } ?>
            </select>
         </td>
         <td class="content_rowl">¿Código de Barra?</td>
         <td class="content_row">
            <select class="text" name="codigo_barra_opcion" onchange="toggleCodigoBarra(this)">
               <option value="si" <?= !empty($items["pro_dis_items_codigo_barra"]) ? "selected" : "" ?>>Sí</option>
               <option value="no" <?= empty($items["pro_dis_items_codigo_barra"]) ? "selected" : "" ?>>No</option>
            </select>

            <input name="pro_dis_items_codigo_barra" type="text" class="text"
               value="<?=$items["pro_dis_items_codigo_barra"]?>"
               id="codigo_barra_input"
               style="margin-left: 10px; <?= empty($items["pro_dis_items_codigo_barra"]) ? 'display:none;' : '' ?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>

         <script>
            function toggleCodigoBarra(select) {
            const input = document.getElementById('codigo_barra_input');
            if (select.value === 'si') {
               input.style.display = 'inline-block';
            } else {
               input.style.display = 'none';
               input.value = ''; // opcional, borrar si se desactiva
            }
            }
         </script>
      </tr>
      <tr>
         <td class="content_rowl">Area de Impresión</td>
         <td class="content_row">
               <input name="pro_dis_items_print_ancho" type="number" class="text" style="width:50px" value="<?=(int)$items["pro_dis_items_print_ancho"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <input name="pro_dis_items_print_alto" type="text" class="text" style="width:50px" value="<?=(int)$items["pro_dis_items_print_alto"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
            <td class="content_rowl">Opcion de Area</td>
            <td class="content_row">
            <label>
               <input type="radio" name="pro_dis_items_opc_area" value="1"
                     <?php if($items["pro_dis_items_opc_area"]=="1") echo "checked"; ?>>
               Dentro del área
            </label>

            <label>
               <input type="radio" name="pro_dis_items_opc_area" value="2"
                     <?php if($items["pro_dis_items_opc_area"]=="2") echo "checked"; ?>>
               Fuera del área
            </label>

            <label>
               <input type="radio" name="pro_dis_items_opc_area" value="3"
                     <?php if($items["pro_dis_items_opc_area"]=="3") echo "checked"; ?>>
               Full
            </label>

         </td>
      </tr>
      <tr>
         <td class="content_tbl_header" colspan="4">1. - Lados y colores de impresion - Cantidad de Colores : <?=$items["pro_dis_items_cantidad_color"]?> <td>
      </tr>
      <?php
         $x = 1;
         $xx = 6;
         while($x <= 5)
         {  
            ?>
            <tr>
                <td class="content_rowl" height="1">Color #<?=$x?></td>
                <td class="content_row">
                   <input type="checkbox" name="pro_dis_items_frente<?=$x?>" value="1" <?php if((int)$items["pro_dis_items_frente{$x}"]) echo "checked"?>>Frente
                   <input type="checkbox" name="pro_dis_items_dorso<?=$x?>" value="1" <?php if((int)$items["pro_dis_items_dorso{$x}"]) echo "checked"?>>Dorso
                   <input type="text" class="text" style="width:200px" name="pro_dis_items_color<?=$x?>" value="<?=$items["pro_dis_items_color{$x}"]?>">
                </td>
                <td class="content_rowl" height="1">Color * #<?=$xx?></td>
                <td class="content_row">
                   <input type="checkbox" name="pro_dis_items_frente<?=$xx?>" value="1" <?php if((int)$items["pro_dis_items_frente{$xx}"]) echo "checked"?>>Frente
                   <input type="checkbox" name="pro_dis_items_dorso<?=$xx?>" value="1" <?php if((int)$items["pro_dis_items_dorso{$xx}"]) echo "checked"?>>Dorso
                   <input type="text" class="text" style="width:200px" name="pro_dis_items_color<?=$xx?>" value="<?=$items["pro_dis_items_color{$xx}"]?>">
                </td>
            </tr>
            <?php
            $x++;
            $xx++;
         }
      ?>
      <tr>
         <td class="content_tbl_header" colspan=4>Otros Detalles<td>
      </tr>
      <tr>
         <td class="content_row" colspan="2" align="center">Observaciones Ejecutivo</td>
         <td class="content_row" colspan="2" align="center">Observaciones Diseñador</td>
      </tr>      
      <tr>
         <td class="content_row" colspan="2">
             <textarea class="text" style="width:100%; height:90px" name="pro_dis_items_observacion"
             onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($items["pro_dis_items_observacion"])?></textarea>
         </td>
         <td class="content_row" colspan="2">
             <textarea readonly class="text" style="width:100%; height:90px" name="pro_dis_items_observacion1"
             onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($items["pro_dis_items_observacion1"])?></textarea>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$items["user_cr"]?>&nbsp;</td>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$items["user_md"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=date('d.m.Y H:i:s', $items["pro_dis_items_fecha_cr"])?></td>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=date('d.m.Y H:i:s',$items["pro_dis_items_fecha_md"])?></td>
      </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("boxopt_b", "980",0)?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <colgroup>
            <col width="150">
            <col width="150">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
      </colgroup>
      <tr>
         <td>
            <?printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign&id={$_REQUEST["id"]}","","arrow-180",150);?>
         </td>
         <?php
            if((int)$items["pro_dis_items_status"] == 0)
            {
            ?>
               &nbsp;
               <td align="right">
                  <?php
                     if($items["pro_dis_items_status"] == 0)
                     {
                        printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.saveAmts.value='1';document.xform_itemsearch.subexec.value='add';document.xform_itemsearch.senddesignmail.value = '';submitForm(document.xform_itemsearch);", "tick-circle-frame", 150);            
                     }
                  ?>
               </td>
            <?php
            }
         ?>
         <?php
            if((int)$items["pro_dis_items_status"] > 0)
            {
            ?>
               &nbsp;
               <td>
               <?php 
                  /* printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.edit.value='1';document.xform_itemsearch.subexec.value='add';submitForm(document.xform_itemsearch);", "pencil", 150);  */
               ?>
               </td>
            <?php
            }
         ?>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td align="right">
            <?php
            if($items["pro_dis_items_status"] == 0 && (int)$_REQUEST["id_item"] > 0)
            {  
               printButton("Enviar notificacion","postnav_save","javascript: deactivateFormChange()","document.xform_itemsearch.saveAmts.value='1';document.xform_itemsearch.subexec.value='add';document.xform_itemsearch.senddesignmail.value = '1'; submitForm(document.xform_itemsearch);","mail",150);
            }
            ?>
        </td>
      </tr>
   </table>
   <?=Nifty_printF(false)?>
</div>
</form>
<iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>
<?php
