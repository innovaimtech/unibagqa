<?php
//----------------------------------------------------------------------------------
// Author:        FERNANDO GARRIDO GONZALEZ
// Copyright:     2024 LTDA. All Rights Reserved.
//----------------------------------------------------------------------------------
$_REQUEST["agid"] = (int)$_REQUEST["agid"];
$_REQUEST["sql_catid"] = (int)$_REQUEST["sql_catid"];
//----------------------------------------------------------------------------------

$sql   = "select wrk_cargoid as cargo
           from workers w
          where wrk_uid =  {$_SESSION["user_id"]} ";
$cargo = $CON->select($sql);
$cargo = $cargo[0];

$sql = " select equipo_type_id from equipo where id = {$hasopeninit["win_equipoid"]} ";
$equipo = $CON->select($sql);

$sql = "select i.* , 0 as salida, 0 as entrada from parametros p
            inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
            inner join item i on p.codigo = i.item_number_prod
         where tabla = 'INSUMOS' ";
$materiales = $CON->select($sql);

$sql = "select t1.*, t3.item_title, t3.item_number_prod, t2.item_amount
         from stockchanges t1
         INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
         INNER JOIN item t3               ON t2.item_id = t3.id
         where
         t1.sth_fromprodotid = {$hasopenot["id"]} and
         t1.stk_status = 2"; 

$producto = $CON->select($sql);
$producto = $producto[0];

//
if($_REQUEST["submode"]=="delete")
{

}
//----------------------------------------------------------------------------------
$currtme = time();
if($_REQUEST["submode"]=="save")
{

}  


if($_REQUEST["submode"]=="carga")
{

}

$sql = "select consumos.*,
               concat(u1.user_firstname,' ',u1.user_lastname) as u_creacion,
               concat(u2.user_firstname,' ',u2.user_lastname) as u_actualizacion
           from consumos 
               inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and maquina_id = e.equipo_type_id
               inner join user u1 on u1.id = usuario_creacion
               inner join user u2 on u2.id = usuario_actualizacion
           where order_id = {$hasopenot["prd_reqid"]} 
           order by consumos.id desc ";
$cabeza_ing = $CON->select($sql);


?>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="">
<input type="hidden" name="submode" value="">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="deletetran" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<input type="hidden" name="idconsumo" value="">
<?php
?>
<table border="0" width="100%" cellpadding="6" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:10px;padding:20px">
      <table border="0" width="100%"  cellpadding="6" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td class="tdleft" style="background-color:#EEEEEE" colspan="6">Carga de Materiales</td>
         </tr>
         <tr>
            <tr>
               <td class="tdleft">Codigo</td>
               <td class="tdnrm"><?=$producto["item_number_prod"]?></td>
               <td class="tdleft">Descripcion</td>
               <td class="tdnrm"><?=$producto["item_title"]?></td>
            </tr>
            <!-- 
            <tr>
               <td class="tdleft">Materialidad</td>
               <td class="tdnrm"></td>
               <td class="tdleft">Ancho de Rollo</td>
               <td class="tdnrm"></td>
            </tr>
            <tr>
               <td class="tdleft">Gramaje</td>
               <td class="tdnrm"></td>
               <td class="tdleft">C.C.</td>
               <td class="tdnrm"></td>
            </tr>
            <tr>
               <td class="tdleft">Cliente</td>
               <td class="tdnrm"></td>
               <td class="tdleft">Diseño</td>
               <td class="tdnrm"></td>
            </tr>
            <tr>
               <td class="tdleft">Impreso en</td>
               <td class="tdnrm">FLEXOGRAFIA</td>
               <td class="tdleft"></td>
               <td class="tdnrm"></td>
            </tr>
            <tr>
               <td class="tdleft">Color Frente</td>
               <td class="tdnrm"></td>
               <td class="tdleft">Color Dorso</td>
               <td class="tdnrm"></td>
            </tr>
            -->
            <tr>
               <td class="tdleft">Ingreso KG</td>
               <td class="tdnrm">
                    <input type="number" class="inptxt" id ="carga_kg" name="carga_kg" style="width:100px;text-align:right"
                        value="">
               </td>
               <td class="tdleft"></td>
               <td class="tdnrm"></td>
            </tr>
            <tr>
               <td class="tdleft">Codigo a Cargar</td>
               <td class="tdnrm"><?=$producto["item_number_prod"]?></td>
               <td class="tdleft">Descripcion</td>
               <td class="tdnrm"><?=$producto["item_title"]?></td>
            </tr>
         </tr>
      </table>
      <table>
         <tr>
            <?php
               $ocultar1 = "style=display:block";
               $ocultar3 = "style=display:none";
               $opcion1  = "Grabar";
               if((int)$_REQUEST["idconsumo"])
               {
                  $sql = " select * from consumos where id = {$_REQUEST["idconsumo"]} ";
                  $validar = $CON->select($sql);
                  $validar = $validar[0];
                  
                  if( date('Ymd',$validar["fecha_creacion"]) < date('Ymd',time()) )
                  {
                     $ocultar3 = "style=display:none";
                     $ocultar1 = "style=display:none";
                     $ocultar = "style=display:none";
                  }
                  else
                  {
                     $ocultar1 = "style=display:block";
                     $ocultar = "style=display:block";
                     $ocultar3 = "style=display:block";
                     $opcion1  = "Actualizar";
                  }
               }
               else
               {
                  $ocultar  = "style=display:none";
                  $ocultar1 = "style=display:block";
                  $ocultar3 = "style=display:none";
               }
            ?>
            <td width="120" style="padding-right:5px">
               <div class="btngreen" onclick="document.xform_inp.mode.value='consumo';document.xform_inp.submode.value='save';document.xform_inp.idconsumo.value='<?=(int)$_REQUEST["idconsumo"]?>';submitForm(document.xform_inp)" <?echo $ocultar1?>>
                     <i class="fa fa-fw fa-save" style="color:white;"></i><?=$opcion1?>&nbsp;
               </div>
            </td>
            <td width="120" style="padding-right:5px" >
               <div id="otterm_btn" name="otterm_btn" class="btnred" onclick="if(askDel('')) {document.xform_inp.mode.value='consumo';document.xform_inp.idconsumo.value='<?=(int)$_REQUEST["idconsumo"]?>';document.xform_inp.submode.value='delete';submitForm(document.xform_inp) }" <?echo $ocultar?>>
                   <i class="fa fa-fw fa-times-circle" style="color:white;"></i>Eliminar&nbsp;
               </div>
            </td>
            <td width="120" style="padding-right:5px">
               <div id="otterm_btn" name="otterm_btn" class="btngrey" onclick="document.xform_inp.mode.value='consumo';submitForm(document.xform_inp)" <?echo $ocultar3?>>
                   <i class="fa fa-fw fa-times-circle" style="color:white;"></i>Nuevo&nbsp;
               </div>
            </td>
         </tr>
      </table>
   </td>
</tr>
</table>
<div style="height:10px"></div>
<?php
if(count($cabeza_ing) && $cabeza_ing != false)
{
?>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="3" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
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
            <td colspan="14" class="tdheader" 
            style="color: white;background-color: #7d9894;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Detalle de Cargas Realizadas</td>
         </tr>
         <tr>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Id</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Codigo</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Materialidad</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho de rollo</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">C.C.</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cliente</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Diseño</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Impreso En</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color Frente</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color Dorso</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">KG ingresados</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Opciones</td>
         </tr>
         <?php
         foreach($cabeza_ing  AS $cabeza )
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$cabeza["id"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"></td>
               <td width="120" style="padding-right:5px" > 
                  <div class="btngrey" 
                     onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.consumo.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&idconsumo=<?=$cabeza["id"]?>&ruid=<?=md5(microtime())?>', 'iframe', '1000', '650', 'auto')">                     
                  <i class="fa fa-fw fa-edit" style="color:white;"></i>Seleccionar&nbsp;
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
