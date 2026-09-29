<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

$_REQUEST["agid"] = (int)$_REQUEST["agid"];

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

require_once("../../../libs/lang/es.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------
$hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"],$_REQUEST["agid"]);
//----------------------------------------------------------------------------------
$currtme = time();
if($_REQUEST["mode"] == "save")
{
   if((int)$_REQUEST["idconsumo"])
   {
       $sql = "update consumos set fecha_actualizacion = {$currtme},
                            usuario_actualizacion  = {$_SESSION["user_id"]}
               where id = {$_REQUEST["idconsumo"]} ";
       $res = $CON->no_result($sql);
 
       
       $sql = "select i.*,ci.salida,ci.entrada from parametros p
                inner join equipo e on e.id = {$hasopenot["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
               inner join item i on p.codigo = i.item_number_prod
               inner join consumos_item ci on ci.consumo_id = {$_REQUEST["idconsumo"]} and item_id = i.id
               where tabla = 'INSUMOS' ";
       $materiales = $CON->select($sql);

       $sql = "delete from consumos_item where consumo_id = {$_REQUEST["idconsumo"]} ";
       $res = $CON->no_result($sql);

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
                                        {$_REQUEST["salida_".$x]} ) ";
          $CON->no_result($sql);
          $x++;
       }
 
       $sql = "delete from consumos_color where consumo_id = {$_REQUEST["idconsumo"]} ";
       $res = $CON->no_result($sql);

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
       ?>
       <script>
          parent.document.xform_inp.submit();
       </script>
    <?php
   }
}

if($_REQUEST["deletemode"] == "delete")
{

   $sql = "delete from consumos_item where consumo_id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);

   $sql = "delete from consumos_color where consumo_id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);

   $sql = "delete from consumos where id = {$_REQUEST["idconsumo"]} ";
   $res = $CON->no_result($sql);
   ?>
      <script>
         parent.document.xform_inp.submit();
         /* document.xform_inp.mode.value='consumo';submitForm(document.xform_inp) */
      </script>
   <?php
}


//----------------------------------------------------------------------------------
unset($_COMVALS);
function getProdOpenWorkerOTId($CON, $wrkid, $plantaid, $idagid)
{
   $hasopeninit = getProdOpenWorkerInit($CON, $wrkid, $plantaid);
   if((int)$hasopeninit["id"])
   {
      $sql = " select t1.*, t4.prd_number, t4.prd_reqid, t2.win_equipoid
                  from prod_worker_ot t1
                  INNER JOIN prod_worker_init t2   ON t1.wok_init_id = t2.id
                  INNER JOIN prod_agenda t3        ON t1.wok_ag_id = t3.id
                  INNER JOIN prod_header t4        ON t3.ag_prdid = t4.id
                  where
                  t1.wok_init_id = {$hasopeninit["id"]} and
                  t1.wok_ag_id   = {$idagid} and
                  t1.wok_status  = 1";
      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];
    
      return $hasopenot;
   }
   return false;
}

// echo( "Maquina : ".$hasopenot["win_equipoid"] );

$sql = "select consumos.*,
               concat(u1.user_firstname,' ',u1.user_lastname) as u_creacion,
               concat(u2.user_firstname,' ',u2.user_lastname) as u_actualizacion
           from consumos 
               inner join equipo e on e.id = {$hasopenot["win_equipoid"]} and maquina_id = e.equipo_type_id
               inner join user u1 on u1.id = usuario_creacion
               inner join user u2 on u2.id = usuario_actualizacion
           where consumos.id = {$_REQUEST["idconsumo"]} ";
$titulo = $CON->select($sql);
$titulo = $titulo[0];

$sql = "select i.*,ci.salida,ci.entrada from parametros p
            inner join equipo e on e.id = {$hasopenot["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
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

?>
<html style="padding:0px;margin:0px;width:100%;height:100%">
<head>
   <title>Producción - Operador</title>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <link href="/css/fontawesome/css/all.min.css" rel="stylesheet" type="text/css">
   <link href="/css/style.css?ruid=<?=md5(microtime())?>" rel="stylesheet" type="text/css">
   <script language="Javascript"><?php require_once("../../../libs/jscripts/sourcen.php") ?></script>
   <link rel="stylesheet" href="/libs/jscripts/colorbox/colorbox.css">
   <script src="/libs/jscripts/colorbox/jquery.colorbox-min.js"></script>
</head>
<script language="Javascript">

</script>
<body style="background-color:#FFFFFF;padding:0px;margin:0px;width:100%;height:100%">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
<input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
<form action="fancy.consumo.php" method="post" name="xform_inp" id="xform_inp">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="itemid" value="<?=$_REQUEST["itemid"]?>">
<input type="hidden" name="otid" value="<?=$_REQUEST["otid"]?>">
<input type="hidden" name="deletemode" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<input type="hidden" name="idconsumo" value="<?=$_REQUEST["idconsumo"]?>">


<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #CCCCCC;">
            <colgroup>
               <col>
               <col width="200">
               <col>
               <col width="300">
            </colgroup>
            <tr>
               <td class="tdheader" colspan="4" style="color: white;background-color: #0EA9A4;">Ingreso Materiales</td>
            </tr>
            <tr>
               <td class="tdleft">Id de Ingreso</td>
               <td class="tdnrm"><?=$titulo["id"]?></td>
            </tr>
            <tr>
               <td class="tdleft">Fecha Ingreso</td>
               <td class="tdnrm"><?=date("Y.m.d H:i:s",$titulo["fecha_creacion"])?></td>
               <td class="tdleft">Realizada por</td>
               <td class="tdnrm"><?=$titulo["u_creacion"]?></td>
            </tr>
            <tr>
               <td class="tdleft">Fecha Ultima Actualizacion</td>
               <td class="tdnrm"><?=date("Y.m.d H:i:s",$titulo["fecha_actualizacion"])?></td>
               <td class="tdleft">Realizada por</td>
               <td class="tdnrm"><?=$titulo["u_actualizacion"]?></td>
            </tr>
      </table>
   </td>
</tr>   
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
                    <input type="text" class="inptxt" id="entrada_<?=$x?>" name="entrada_<?=$x?>" style="width:50px;text-align:center"
                       value="<?=printPrice($material["entrada"],2)?>">
                    <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                    <input type="text" class="inptxt" id ="salida_<?=$x?>" name="salida_<?=$x?>" style="width:50px;text-align:center"
                           value="<?=printPrice($material["salida"],2)?>">
                    <select class="inptxt" style="" name="unidad_s_<?=$x?>" id="unidad_s_<?=$x?>" disabled> 
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                  <?php    /*
                        $sql = "select i.id
                                      ,sum(ci.consumo) as consumo
                              from consumos c
                                 inner join consumos_item ci on c.id = ci.consumo_id
                                 inner join item i on i.id = ci.item_id
                                 inner join equipo e on e.id = {$hasopenot["win_equipoid"]} and maquina_id = e.equipo_type_id
                                 where order_id = {$hasopenot["prd_reqid"]} 
                                    and i.id = {$material["id"]}
                                    group by i.id" ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                        */
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
                  style="width:50px;text-align:center;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
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
                     <input type="text" class="inptxt" id="entrada_color<?=$x?>" name="entrada_color<?=$x?>" style="width:50px;text-align:center"
                            value="<?=printPrice($colores["entrada".$x],2)?>"> 
                     <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                    <option value="1">Kilos</option>
                                    <option value="2">Litros</option>
                        </select>
                  </td>
                  <td class="tdnrm">
                     <input type="text" class="inptxt" id ="salida_color<?=$x?>" name="salida_color<?=$x?>" style="width:50px;text-align:center" 
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
                                  inner join consumos_color ci on c.id = ci.consumo_id 
                                  where order_id = {$hasopenot["prd_reqid"]}
                                     group by c.order_id " ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                     ?>
                     <input type="text" class="inptxt" name="consumo_x" id="consumo_x"  
                     style="width:50px;text-align:center;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
      </table>
   </td>
</tr>
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">   
     <div style="height:10px"></div>
     <table border="0" width="100%" cellpadding="0" cellspacing="0" align="center">
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
            <td width="100" style="padding-right:5px">
               <div class="btngrey" onclick="parent.$.colorbox.close();"
                  >
                 <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
               </div>
            </td>
            <td width="100" style="padding-right:5px">
               <div class="btngreen" 
                  onclick="document.xform_inp.mode.value='save';document.xform_inp.idconsumo.value='<?=(int)$_REQUEST["idconsumo"]?>';submitForm(document.xform_inp)" <?echo $ocultar1?>>
                  <i class="fa fa-fw fa-save" style="color:white;"></i><?=$opcion1?>&nbsp;
               </div>
            </td>
            <td width="100" style="padding-right:5px" >
               <div id="otterm_btn" name="otterm_btn" class="btnred" 
                   onclick="if(askDel('')) {document.xform_inp.idconsumo.value='<?=(int)$_REQUEST["idconsumo"]?>';document.xform_inp.deletemode.value='delete';submitForm(document.xform_inp)}" <?echo $ocultar?>>
                   <i class="fa fa-fw fa-times-circle" style="color:white;"></i>Eliminar&nbsp;
               </div>
            </td>
         </tr>
     </table>
   </td>     
</tr>
</table>      
</body>
</html>
