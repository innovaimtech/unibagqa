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
if(in_array($equipo[0]["equipo_type_id"], [7, 11]))
{
   $sql = " select fab_print_colordesc_1
		            ,fab_print_colordesc_2
		            ,fab_print_colordesc_3
		            ,fab_print_colordesc_4
		            ,fab_print_colordesc_5
		            ,fab_print_colordesc_6
		            ,fab_print_colordesc_7
		            ,fab_print_colordesc_8
		            ,fab_print_colordesc_9
		            ,fab_print_colordesc_10
                  ,0 as entrada_1
                  ,0 as entrada_2
                  ,0 as entrada_3
                  ,0 as entrada_4
                  ,0 as entrada_5
                  ,0 as entrada_6
                  ,0 as entrada_7
                  ,0 as entrada_8
                  ,0 as entrada_9
                  ,0 as entrada_10
                  ,0 as salida_1
                  ,0 as salida_2
                  ,0 as salida_3
                  ,0 as salida_4
                  ,0 as salida_5
                  ,0 as salida_6
                  ,0 as salida_7
                  ,0 as salida_8
                  ,0 as salida_9
                  ,0 as salida_10
            from orders_items 
               where req_id = {$hasopenot["prd_reqid"]} ";
   $colores = $CON->select($sql);
   $colores = $colores[0];
}     
//
if($_REQUEST["submode"]=="delete")
{

   $sql = "delete from consumos_item where consumo_id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);

   $sql = "delete from consumos_color where consumo_id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);

   $sql = "delete from consumos where id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);

   $_REQUEST["idconsumo"] = 0;
   $_REQUEST["submode"]="";
}
//----------------------------------------------------------------------------------
$currtme = time();
if($_REQUEST["submode"]=="save")
{
  if((int)$_REQUEST["idconsumo"])
  {
      $sql = "update consumos set fecha_actualizacion = {$currtme},
                           usuario_actualizacion  = {$_SESSION["user_id"]}
              where id = {$_REQUEST["idconsumo"]} ";
      $res = $CON->no_result($sql);

      $sql = "delete from consumos_item where consumo_id = {$_REQUEST["idconsumo"]} ";
      $res = $CON->no_result($sql);

      $x = 0;
      foreach($materiales AS $material)
      {  
         $consumo1 = $_REQUEST["entrada_".$x] - $_REQUEST["salida_".$x];
         $sql = " insert into consumos_item(consumo_id,
                                              item_id,
                                             consumo,
                                             entrada,
                                             salida)
                                       VALUES
                                       ({$_REQUEST["idconsumo"]},
                                       {$material["id"]},
                                       {$consumo1},
                                       {$_REQUEST["entrada_".$x]},
                                       {$_REQUEST["salida_".$x]} ) " ;
         $CON->no_result($sql);
         $x++;
      }

      $sql = "delete from consumos_color where consumo_id = {$_REQUEST["idconsumo"]} ";
      $res = $CON->no_result($sql);

      for ($x = 1; $x <= 10; $x++)
      { 
         if($colores["fab_print_colordesc_".$x]!="")
         {
            $consumo1 = $_REQUEST["entrada_color".$x] - $_REQUEST["salida_color".$x];
            $sql = " insert into consumos_color(numero
                                               ,consumo_id
                                               ,color
                                               ,consumo
                                               ,entrada
                                               ,salida)
                                 values({$x}
                                       ,{$_REQUEST["idconsumo"]}
                                       ,'{$colores["fab_print_colordesc_".$x]}'
                                       ,{$consumo1}
                                       ,{$_REQUEST["entrada_color".$x]}
                                       ,{$_REQUEST["salida_color".$x]} )" ;
            $CON->no_result($sql);
         }
      }

      $_REQUEST["idconsumo"] = 0;
      $_REQUEST["submode"]="";
  }
  else
  {    
       $stk_num  = createTransactionNumber($CON, $_SESSION["user_company_id"], "consumo");
       $sql = " insert into consumos(numero,
                           anotacion,
                           maquina_id,
                           compañia,
                           sucursal,
                           estado,
                           fecha_creacion,
                           usuario_creacion,
                           fecha_actualizacion,
                           usuario_actualizacion,
                           order_id,
                           ag_id)
                  VALUES(
                  '{$stk_num}',
                  '',
                  {$equipo[0]["equipo_type_id"]},
                  0,
                  0,
                  1,
                  {$currtme},
                  {$_SESSION["user_id"]},
                  {$currtme},
                  {$_SESSION["user_id"]},
                  {$hasopenot["prd_reqid"]},
                  {$_REQUEST["agid"]} )";
       $res = $CON->no_result($sql);
       if($res)
       { 
          $stk_id = mysql_insert_id();
          $x = 0;
          foreach($materiales AS $material)
          {  
             $consumo1 = $_REQUEST["entrada_".$x] - $_REQUEST["salida_".$x];
             $sql = " insert into consumos_item(consumo_id,
                                                item_id,
                                                consumo,
                                                entrada,
                                                salida)
                                          VALUES
                                          ({$stk_id},
                                          {$material["id"]},
                                          {$consumo1},
                                          {$_REQUEST["entrada_".$x]},
                                          {$_REQUEST["salida_".$x]} ) " ;
            $CON->no_result($sql);
            $x++;
         }
         
         
         for ($x = 1; $x <= 10; $x++)
         { 
            if($colores["fab_print_colordesc_".$x]!="")
            {
               $consumo1 = $_REQUEST["entrada_color".$x] - $_REQUEST["salida_color".$x];
               $sql = " insert into consumos_color(numero
                                                  ,consumo_id
                                                  ,color
                                                  ,consumo
                                                  ,entrada
                                                  ,salida)
                                    values({$x}
                                          ,{$stk_id}
                                          ,'{$colores["fab_print_colordesc_".$x]}'
                                          ,{$consumo1}
                                          ,{$_REQUEST["entrada_color".$x]}
                                          ,{$_REQUEST["salida_color".$x]} )" ;
               $CON->no_result($sql);
            }
         }

      }
      $_REQUEST["submode"] = "";
  }
}  


if($_REQUEST["submode"]=="carga")
{


   $sql = "select i.*,ci.salida,ci.entrada from parametros p
               inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
               inner join item i on p.codigo = i.item_number_prod
               inner join consumos_item ci on ci.consumo_id = {$_REQUEST["idconsumo"]} and item_id = i.id
           where tabla = 'INSUMOS' ";
   $materiales = $CON->select($sql);

   
   for ($x = 1; $x <= 10; $x++)
   {
      $_REQUEST["fab_print_colordesc_".$x] = "";
   }

   $sql = "select * from consumos_color where consumo_id = {$_REQUEST["idconsumo"]} ";
   $ccc = $CON->select($sql);
   $x = 1;
   foreach($ccc AS $c)
   {
      $colores["fab_print_colordesc_".$x] = $c["color"];
      $colores["entrada".$x] = $c["entrada"];
      $colores["salida".$x] = $c["salida"];
      $x++;
   }
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


<div class="btngrey" onclick="location.href = '/prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';">
   <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
</div>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td colspan="6" class="tdheader" 
            style="color: white;background-color: #7d9894;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cargar Insumos</td>
         </tr>
         <tr>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Codigo</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Detalle</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Entrada</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Salida</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Consumo</td>
         </tr>
         <?php
         $x = 0;
         foreach($materiales AS $material)
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$material["item_number_prod"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$material["item_title"]?></td>
               <td class="tdnrm">
                    <input type="text" class="inptxt" id="entrada_<?=$x?>" name="entrada_<?=$x?>" style="width:100px;text-align:center"
                       value="<?=printPrice($material["entrada"],2)?>">
                    <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                    <input type="text" class="inptxt" id ="salida_<?=$x?>" name="salida_<?=$x?>" style="width:100px;text-align:center"
                           value="<?=printPrice($material["salida"],2)?>">
                    <select class="inptxt" style="" name="unidad_s_<?=$x?>" id="unidad_s_<?=$x?>" disabled> 
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                  <?php
                        $sql = "select i.id
                                      ,sum(ci.consumo) as consumo
                              from consumos c
                                 inner join consumos_item ci on c.id = ci.consumo_id
                                 inner join item i on i.id = ci.item_id
                                 inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and maquina_id = e.equipo_type_id
                                 where order_id = {$hasopenot["prd_reqid"]} and ag_id = {$_REQUEST["agid"]}
                                    and i.id = {$material["id"]}
                                    group by i.id" ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                  ?>
                  <input type="text" class="inptxt" name="consumo_x" id="consumo_x"  
                  style="width:100px;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
               </td>
            </tr>
            <?php
            $x++;
         }
         for ($x = 1; $x <= 10; $x++)
         { 
            if($colores["fab_print_colordesc_".$x]!="")
            {
               ?>
               
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD">Color # <?=$x?></td>
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$colores["fab_print_colordesc_".$x]?></td>
                  <td class="tdnrm">
                     <input type="text" class="inptxt" id="entrada_color<?=$x?>" name="entrada_color<?=$x?>" style="width:100px;text-align:center"
                            value="<?=printPrice($colores["entrada".$x],2)?>"> 
                     <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                    <option value="1">Kilos</option>
                                    <option value="2">Litros</option>
                        </select>
                  </td>
                  <td class="tdnrm">
                     <input type="text" class="inptxt" id ="salida_color<?=$x?>" name="salida_color<?=$x?>" style="width:100px;text-align:center" 
                         value="<?=printPrice($colores["salida".$x],2)?>"> 
                     <select class="inptxt" style="" name="unidad_s_<?=$x?>" id="unidad_s_<?=$x?>" disabled>
                                    <option value="1">Kilos</option>
                                    <option value="2">Litros</option>
                        </select>
                  </td>
                  <td class="tdnrm">
                     <?php
                        $sql = " select order_id
                                      ,sum(ci.consumo) as consumo
                                     from consumos c
                                  inner join consumos_color ci on c.id = ci.consumo_id and ci.numero = {$x}
                                  where order_id = {$hasopenot["prd_reqid"]}
                                     group by c.order_id " ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                     ?>
                     <input type="text" class="inptxt" name="consumo_x" id="consumo_x"  
                     style="width:100px;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
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
               /*
               if($cargo==4 && $cargo == 10)
               {
                  $ocultar1 = "style=display:block";
               }
               */
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
      <table border="0" width="100%" cellpadding="1" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td colspan="7" class="tdheader" 
            style="color: white;background-color: #7d9894;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Detalle de Ingresos</td>
         </tr>
         <tr>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Id</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Carga</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Fecha Creación</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Operador</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Fecha Actualización</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Operador</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Opciones</td>
         </tr>
         <?php
         foreach($cabeza_ing  AS $cabeza )
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$cabeza["id"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$cabeza["numero"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=date('d.m.Y H:i:s', $cabeza["fecha_creacion"])?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$cabeza["u_creacion"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=date('d.m.Y H:i:s', $cabeza["fecha_actualizacion"])?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$cabeza["u_actualizacion"]?></td>
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
