<?php

/* Informacion de la Cabecera */

$sql = " select t1.*
              , t3.company_short
              , t4.shop_name
              , t5.trans_name
              , concat(u1.user_firstname,' ',u1.user_lastname) as usuario1
              , concat(u2.user_firstname,' ',u2.user_lastname) as usuario2
              , dlv_company_id
              , dlv_shop_id
           from orders_delivery t1
              LEFT OUTER JOIN company_data t3  ON t1.dlv_company_id = t3.id
              LEFT OUTER JOIN company_shops t4 ON t1.dlv_shop_id    = t4.id
              LEFT OUTER JOIN transports t5    ON t5.id = t1.dlv_transportid
              LEFT OUTER JOIN user u1          ON u1.id = t1.dlv_crtusr
              LEFT OUTER JOIN user u2          ON u2.id = t1.dlv_updusr
          where t1.id = {$_REQUEST["id"]}";


$informacion = $CON->select($sql);
$informacion = $informacion[0];
$tipo_documento = "GV";

if( !(int)$informacion["dlv_docnum"])
{
   $sql     = " select * from invoice_sell where invc_number = {$informacion["dlv_num"]}";
   $factura = $CON->select($sql);
   $factura = $factura[0];
   $informacion["dlv_docnum"]            = $factura["invc_docnumber"];
   $informacion["dlv_annotation"]        = $factura["invc_desc"];
   $informacion["dlv_annotation_intern"] = $factura["invc_desc_intern"];
   $tipo_documento = "FA";
}

$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

/* Informacion de detalle */

$sql = " select * from orders_delivery_items where dlv_id = {$_REQUEST["id"]} ";
$detalle = $CON->select($sql);

/* cargar datos de parametros */

$sql   = " select * from parametros where tabla = 'MEDIDACAJA' ";
$cajas = $CON->select($sql);

$sql      = " select * from transports_vehiculo where transports_vh_status > 0 ";
$patentes = $CON->select($sql);

$sql      = " select * from transports_chofer where transports_chofer_status > 0";
$choferes = $CON->select($sql);
 

if((int)$_REQUEST["clonexec"])
{
   $currtme = time();
   $sql = " insert into despacho(fecha_ingreso,
                         mes,
                         año,
                         cantidad_cajas,
                         cantidad_pallet,
                         codigo_medidacajas1,
                         codigo_medidacajas2,
                         id_transporte,
                         id_patente,
                         id_chofer,
                         sello,
                         fecha_creacion,
                         fecha_actualizacion,
                         id_usuario_creacion,
                         id_usuario_actualizacion,
                         estado,
                         tipo_documento,
                         numero_documento,
                         empresa,
                         sucursal,
                         id_cliente,
                         id_ingreso)
                  select {$currtme},
                         mes,
                         año,
                         cantidad_cajas,
                         cantidad_pallet,
                         codigo_medidacajas1,
                         codigo_medidacajas2,
                         id_transporte,
                         id_patente,
                         id_chofer,
                         sello,
                         fecha_creacion,
                         fecha_actualizacion,
                         id_usuario_creacion,
                         id_usuario_actualizacion,
                         1,
                         tipo_documento,
                         numero_documento,
                         empresa,
                         sucursal,
                         id_cliente,
                         2
                         from despacho where numero_documento = {$_REQUEST["id"]} ";
      $res = $CON->no_result($sql);
      if($res)
      {
         $savemsg = getSaveMessage($res);
         $_REQUEST["clonexec"] = "";

         $sql = " select MAX(id) 'thisid'
         from despacho
         where
         id_usuario_creacion = {$_SESSION["user_id"]}";
         $despacho2 = $CON->select($sql);
         /* $_REQUEST["id_despacho"] = $despacho2[0]["thisid"]; */


         $sql = " insert into detalle_despacho(id_item
                                             , cantidad
                                             , id_despacho
                                             , salida
                                             , descripcion) 
                  select id_item
                       , cantidad
                       , {$despacho2[0]["thisid"]}
                       , salida
                       , descripcion
                  from detalle_despacho where id_despacho = {$_REQUEST["id_despacho"]} ";
         $respuesta = $CON->no_result($sql);
      }

}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   $_REQUEST["mes"] = date("m")-1;
   $_REQUEST["año"] = date("Y");

   $_REQUEST["mes"]                       = (int)$_REQUEST["mes"];
   $_REQUEST["año"]                       = (int)$_REQUEST["año"];
   $_REQUEST["cantidad_cajas"]            = (int)$_REQUEST["cantidad_cajas"];
   $_REQUEST["cantidad_pallet"]           = (int)$_REQUEST["cantidad_pallet"];
   $_REQUEST["codigo_medidacajas1"]       = trim(addslashes($_REQUEST["codigo_medidacajas1"]));
   $_REQUEST["codigo_medidacajas2"]       = trim(addslashes($_REQUEST["codigo_medidacajas2"]));
   $_REQUEST["id_transporte"]             = (int)$_REQUEST["id_transporte"];
   $_REQUEST["id_patente"]                = (int)$_REQUEST["id_patente"];   
   $_REQUEST["id_chofer"]                 = (int)$_REQUEST["id_chofer"];
   $_REQUEST["sello"]                     = trim(addslashes($_REQUEST["sello"]));
   $_REQUEST["fecha_creacion"]            = (int)$_REQUEST["fecha_creacion"];  
   $_REQUEST["fecha_actualizacion"]       = (int)$_REQUEST["fecha_actualizacion"];
   $_REQUEST["id_usuario_creacion"]       = trim(addslashes($_REQUEST["id_usuario_creacion"]));
   $_REQUEST["id_usuario_actualizacion"]  = trim(addslashes($_REQUEST["id_usuario_actualizacion"]));
   $_REQUEST["empresa"]                   = (int)$_REQUEST["empresa"];
   $_REQUEST["sucursal"]                  = (int)$_REQUEST["sucursal"];

   //----------------------------------------------------------------------------------
  
   $sql = " insert into despacho
                        (fecha_ingreso,
                         mes,
                         año,
                         cantidad_cajas,
                         cantidad_pallet,
                         codigo_medidacajas1,
                         codigo_medidacajas2,
                         id_transporte,
                         id_patente,
                         id_chofer,
                         sello,
                         fecha_creacion,
                         fecha_actualizacion,
                         id_usuario_creacion,
                         id_usuario_actualizacion,
                         estado,
                         tipo_documento,
                         numero_documento,
                         empresa,
                         sucursal,
                         id_cliente,
                         id_ingreso)
                  VALUES( {$currtme},
                          {$_REQUEST["mes"]},
                          {$_REQUEST["año"]},
                          {$_REQUEST["cantidad_cajas"]},
                          {$_REQUEST["cantidad_pallet"]},
                         '{$_REQUEST["codigo_medidacajas1"]}',
                         '{$_REQUEST["codigo_medidacajas2"]}',
                          {$_REQUEST["id_transporte"]},
                          {$_REQUEST["id_patente"]},
                          {$_REQUEST["id_chofer"]},
                         '{$_REQUEST["sello"]}',
                          {$currtme},
                          {$currtme},
                          {$_SESSION["user_id"]},
                          {$_SESSION["user_id"]},
                          1,
                          '{$tipo_documento}',
                          {$_REQUEST["id"]} ,
                          {$informacion["dlv_company_id"]} ,
                          {$informacion["dlv_shop_id"]}  ,
                          {$informacion["dlv_cust_id"]} ,
                          1)";
   $res = $CON->no_result($sql);
   if($res)
   {
      $sql = " select MAX(id) 'thisid'
      from despacho
      where
      id_usuario_creacion = {$_SESSION["user_id"]}";
      $despacho = $CON->select($sql);
      $_REQUEST["id_despacho"] = $despacho[0]["thisid"];

      /* carga detalle en tabla de detalle */

    
      for($y = 0; $y < count($detalle); $y++)
      {
         $sql = " insert into detalle_despacho(id_item
                                             , cantidad
                                             , id_despacho
                                             , salida
                                             , descripcion) 
                   values({$detalle[$y]["item_id"]}
                         ,{$detalle[$y]["item_amount_shipped"]}
                         ,{$_REQUEST["id_despacho"]}
                         ,{$detalle[$y]["item_amount_shipped"]}
                         ,'{$detalle[$y]["item_desc"]}'
                         ) ";
         $respuesta = $CON->no_result($sql);
      }
   }
   
   $savemsg = getSaveMessage($res);

}

 
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["fecha_ingreso"]         = trim(addslashes($_REQUEST["fecha_ingreso"]));
   $_REQUEST["fecha_ingreso"]         = explode(".", $_REQUEST["fecha_ingreso"]);
   $_REQUEST["fecha_ingreso"]         = (int)mktime(3, 0, 0, $_REQUEST["fecha_ingreso"][1], $_REQUEST["fecha_ingreso"][0], $_REQUEST["fecha_ingreso"][2]);
                
   $_REQUEST["mes"]                   = (int)$_REQUEST["mes"];
   $_REQUEST["año"]                   = (int)$_REQUEST["año"];
   $_REQUEST["cantidad_cajas"]        = (int)$_REQUEST["cantidad_cajas"];
   $_REQUEST["cantidad_pallet"]       = (int)$_REQUEST["cantidad_pallet"];
   $_REQUEST["codigo_medidacajas1"]   = trim(addslashes($_REQUEST["codigo_medidacajas1"]));
   $_REQUEST["codigo_medidacajas2"]   = trim(addslashes($_REQUEST["codigo_medidacajas2"]));
   $_REQUEST["id_transporte"]         = (int)$_REQUEST["id_transporte"];
   $_REQUEST["id_patente"]            = (int)$_REQUEST["id_patente"];   
   $_REQUEST["id_chofer"]             = (int)$_REQUEST["id_chofer"];
   $_REQUEST["sello"]                 = trim(addslashes($_REQUEST["sello"]));
   $_REQUEST["observacion"]           = trim(addslashes($_REQUEST["observacion"]));
   
   $res = $CON->no_result($sql);
   $rowcount = count($detalle);
   for($y = 0; $y < $rowcount; $y++)
   {
      // $_REQUEST["salida_$y"] = $_REQUEST["salida_$y"];
      $salida_int = str_replace(",","", str_replace(".","", str_replace('"',"", trim($_REQUEST["salida_$y"]))));
      $salida_int = (int)$salida_int;

      $sql = " update detalle_despacho set salida = {$salida_int} where id = {$_REQUEST["id_detalle_$y"]} ";
      $CON->no_result($sql);

   }
   $sql = "select sum(cantidad)-sum(salida) as total from detalle_despacho where id_despacho = {$_REQUEST["id_despacho"]} ";
   $resultado = $CON->select($sql);
   $resultado = $res[0];

   $sql = " update despacho
              set fecha_ingreso            =  {$_REQUEST["fecha_ingreso"]},
                  mes                      =  {$_REQUEST["mes"]},
                  año                      =  {$_REQUEST["año"]},
                  cantidad_cajas           =  {$_REQUEST["cantidad_cajas"]},
                  cantidad_pallet          =  {$_REQUEST["cantidad_pallet"]},
                  codigo_medidacajas1      = '{$_REQUEST["codigo_medidacajas1"]}',
                  codigo_medidacajas2      = '{$_REQUEST["codigo_medidacajas2"]}',
                  id_transporte            =  {$_REQUEST["id_transporte"]},
                  id_patente               =  {$_REQUEST["id_patente"]},
                  id_chofer                =  {$_REQUEST["id_chofer"]},
                  hora_ingreso             =  '{$_REQUEST["hora_ingreso"]}',
                  hora_salida              =  '{$_REQUEST["hora_salida"]}',
                  sello                    = '{$_REQUEST["sello"]}',
                  fecha_actualizacion      =  {$currtme},
                  id_usuario_actualizacion =  {$_SESSION["user_id"]},
                  observacion              = '{$_REQUEST["observacion"]}'
                  WHERE id = {$_REQUEST["id_despacho"]}";

   $res = $CON->no_result($sql);   
   
   $sql = " update orders_delivery set dlv_cod_despacho = 3 where id = {$_REQUEST["id"]} ";
   $res = $CON->no_result($sql);   

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
         where
         t1.id = {$informacion["dlv_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];


//----------------------------------------------------------------------------------
$sellers     = getSellers($CON);
$payments    = getPayments($CON, $headdata["invc_shop_id"]);
$transports  = getTransports($CON);
$invcparts   = getInvoiceSellParts($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
$rowcount = 22;

$custselarr = Array();
   
$custname = str_replace(",","", str_replace("'","", str_replace('"',"", trim($headdata["cust_name"]))));
$custmail = str_replace(",","", str_replace("'","", str_replace('"',"", trim($customer["cust_email"]))));
if($custmail != "")
{
   $temp["NAME"] = $custname;
   $temp["MAIL"] = $custmail;

   array_push($custselarr, $temp);
}

$sql = " select t2.add_email, t2.add_firstname, t2.add_lastname
         from customer t1
         INNER JOIN customer_contacts t2 ON t1.id = t2.add_cust_id
         where
         t1.cust_status > 0 and
         t1.id = {$customer["id"]}
         order by t2.add_pos asc";
$suppcontacts = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = "select * from despacho where id = {$_REQUEST["id_despacho"]} ";
$despacho = $CON->select($sql);
$despacho = $despacho[0];

//----------------------------------------------------------------------------------
$sql = " select i.item_number_prod
               ,case when i.item_number_prod = '9999999' then descripcion else i.item_title end as item_nameshop
               ,dd.cantidad
               ,dd.salida
               ,i.item_unit
               ,iu.unit_name
               ,dd.id
            from detalle_despacho dd
               left outer join item i on i.id = dd.id_item
               left outer join item_units iu on iu.id = i.item_unit
            where dd.id_despacho = {$_REQUEST["id_despacho"]}";
$detalle = $CON->select($sql);


?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function despachocheck(obj)
   {
      // var frmchk = checkform(new Array(obj.mes, obj.año, obj.fecha_ingreso, obj.id_transporte, obj.id_patente, obj.id_chofer, obj.hora_ingreso, obj.hora_salida));
      var frmchk = checkform(new Array(obj.mes, obj.año, obj.fecha_ingreso, obj.hora_ingreso, obj.hora_salida));
      if(!frmchk)
         return false;

      if(obj.hora_ingreso.value > obj.hora_salida.value)
      {
         alert("Hora de Ingreso no puede ser mayor que la Hora salida");
         return false;
      }
      
      return true;
   }

   function setTransito(pid)
   {
      var obj = document.all.id_patente;
      obj.options.length = 0;

      var newIndex   = obj.options.length;
      var newOpt     = new Option('<?=$_LANG["FORM"]["OPTION"][0]?>');
      newOpt.value   = "";
      obj.options[newIndex] = newOpt;
      <?php
      foreach($patentes AS $patente)
      {  ?>
         if(pid == <?=$patente["idtransports_vh"]?>)
         {
            var newIndex   = obj.options.length;
            var newOpt     = new Option('<?=$patente["transports_vh_patente"]?>');
            newOpt.value   = '<?=$patente["id"]?>';
            obj.options[newIndex] = newOpt;
         }
         <?php
      }
      ?>

      var obj1 = document.all.id_chofer;
      obj1.options.length = 0;
      <?php
      ?>
      var newIndex1   = obj1.options.length;
      var newOpt1     = new Option('<?=$_LANG["FORM"]["OPTION"][0]?>');
      newOpt1.value   = "";
      obj1.options[newIndex1] = newOpt1;
      <?php
      foreach($choferes AS $chofere)
      {  ?>
         if(pid == '<?=$chofere["transports_chofer_trans_id"]?>')
         {
            var newIndex1   = obj1.options.length;
            var newOpt1     = new Option('<?=$chofere["transports_chofer_nombre"].' '.$chofere["transports_chofer_paterno"]?>');
            newOpt1.value   = '<?=$chofere["id"]?>';
            obj1.options[newIndex1] = newOpt1;
         }
         <?php
      }
      ?>



   }

</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); --></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="form_shppos" onsubmit="return despachocheck(this)" >
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id_despacho" value="<?=$_REQUEST["id_despacho"]?>">
<input type="hidden" name="desp_status" value="1">
<?=Nifty_printH("box1", "980", 0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos de Salida</td>
</tr>
<tr>
   <td class="content_rowl">Documento</td>
   <td class="content_row"><?=$informacion["dlv_num"]?></td>
   <td class="content_rowl">Numero</td>
   <td class="content_row"><?=$informacion["dlv_docnum"]?></td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$informacion["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$informacion["shop_name"]?></td>
</tr>
<tbody>
<tr>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$customer["cust_company"]?></td>
   <td class="content_rowl">Rut</td>
   <td class="content_row"><?=$customer["cust_rut"]?></td>
</tr>
<tr id="id_vendedor" <?if($tipo_documento == "GV") echo 'style="display:none"'?>>
   <td class="content_rowl">Vendedor</td>
   <td class="content_row"></td>
   <td class="content_rowl">Canal</td>
   <td class="content_row"></td>
</tr>
<tr>
   <td class="content_rowl">Transportista</td>
   <td class="content_row"><?=$informacion["trans_name"]?></td>
   <?if($tipo_documento == "GV")
     {?>
        <td class="content_rowl"></td>
      <?php
     }
     else
     {?>
          <td class="content_rowl">Dirección factura <?=$require;?></td>
      <?php
     }?>
   <td class="content_row"></td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[cliente]</td>
   <td class="content_row">
      <textarea class="text" style="width:350px; height:45px" name="invc_desc" readonly <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($informacion["dlv_annotation"])?></textarea>
   </td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row">
      <textarea class="text" style="width:350px; height:45px" name="invc_desc_intern" readonly <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($informacion["dlv_annotation_intern"])?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$informacion["usuario1"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$informacion["usuario2"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($informacion["dlv_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($informacion["dlv_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
&nbsp;
<?=Nifty_printH("box1", "980", 0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
   <colgroup>
      <col width="130">
      <col width="360">
      <col width="130">
      <col>
   </colgroup>

   <tr>
      <td class="content_tbl_header" colspan="4">Detalle del Despacho <?=$_REQUEST["id_estado"]?></td>
   </tr>
   <tr>
      <td class="content_rowl">Perido *</td>
      <td class="content_row">
          <select class="text" style="width:150px" name="mes" id="mes"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <option value="1" <?if(1==$despacho["mes"]) echo "selected"?>>ENERO</option>
               <option value="2" <?if(2==$despacho["mes"]) echo "selected"?>>FEBRERO</option>
               <option value="3" <?if(3==$despacho["mes"]) echo "selected"?>>MARZO</option>
               <option value="4" <?if(4==$despacho["mes"]) echo "selected"?>>ABRIL</option>
               <option value="5" <?if(5==$despacho["mes"]) echo "selected"?>>MAYO</option>
               <option value="6" <?if(6==$despacho["mes"]) echo "selected"?>>JUNIO</option>
               <option value="7" <?if(7==$despacho["mes"]) echo "selected"?>>JULIO</option>
               <option value="8" <?if(8==$despacho["mes"]) echo "selected"?>>AGOSTO</option>
               <option value="9" <?if(9==$despacho["mes"]) echo "selected"?>>SEPTIEMBRE</option>
               <option value="10" <?if(10==$despacho["mes"]) echo "selected"?>>OCTUBRE</option>
               <option value="11" <?if(11==$despacho["mes"]) echo "selected"?>>NOVIEMBRE</option>
               <option value="12" <?if(12==$despacho["mes"]) echo "selected"?>>DICIEMBRE</option>
         </select>
         <select class="text" style="width:150px" name="año" id="anos"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               $año = date("Y")-1;
               for($puntero = 0; $puntero < 4; $puntero++)
               {
                  ?>
                  <option value=<?=$año?>
                     <?if($año==$despacho["año"]) echo "selected"?>><?=$año?>
                  </option>
                  <?php
                  $año++;
               }
               ?>
         </select>
      </td>
      <td class="content_rowl">Fecha *</td>
      <td class="content_row">
          <input type="text" style="width:80px" id="fecha_ingreso" name="fecha_ingreso" <?=$rdlo?>
          class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
          onfocus="markfield(this,0)" onblur="markfield(this,1)"
          value="<?if((int)$despacho["fecha_ingreso"]) echo date('d.m.Y', $despacho["fecha_ingreso"])?>">
      </td>
   </tr> 
   <tr>
      <td class="content_rowl">Cantidad de Cajas</td>
      <td class="content_row">
         <input type="number" class="text" name="cantidad_cajas" style="width:70px" value="<?=$despacho["cantidad_cajas"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_rowl">Cantidad de Pallets</td>
      <td class="content_row">
         <input type="number" class="text" name="cantidad_pallet" style="width:70px" value="<?=$despacho["cantidad_pallet"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      
      <td class="content_rowl">Medida Caja 1</td>
      <td class="content_row">
         <select class="text" name="codigo_medidacajas1" style="width:150px" onmousedown="markfield(this,0)" 
            onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($cajas as $caja)
            {  
               ?>
                  <option value="<?=$caja["codigo"]?>"
                     <?php if($caja["codigo"] == $despacho["codigo_medidacajas1"]) echo "selected"?>><?=$caja["descripcion"]?>
                  </option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_rowl">Medida Caja 2</td>
      <td class="content_row">
      <select class="text" name="codigo_medidacajas2" style="width:150px" onmousedown="markfield(this,0)" 
            onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($cajas as $caja)
            {  ?>
               <option value="<?=$caja["codigo"]?>"
               <?php if($caja["codigo"] == $despacho["codigo_medidacajas2"]) echo "selected"?>><?=$caja["descripcion"]?></option><?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Empresa Transporte *</td>
      <td class="content_row">
         <select class="text" name="id_transporte" style="width:300px" onmousedown="markfield(this,0)" 
            onblur="markfield(this,1)" onchange="setTransito(this.value)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($transports as $transport)
            {  ?>
               <option value="<?=$transport["id"]?>"
               <?php if($transport["id"] == $despacho["id_transporte"]) echo "selected"?>><?=$transport["trans_name"]?></option><?php
            }
            ?>
         </select>
      </td>
      <td class="content_rowl">Patente *</td>
      <td class="content_row">
         <select class="text" name="id_patente" style="width:150px" onmousedown="markfield(this,0)" 
            onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($patentes as $patente)
            {  
               if($patente["idtransports_vh"] == $despacho["id_transporte"])
               {  ?>
                  <option value="<?=$patente["id"]?>"
                  <?php if($patente["id"] == $despacho["id_patente"]) echo "selected"?>><?=$patente["transports_vh_patente"]?></option>
                  <?php
               }
            }
         ?>  
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Chofer *</td>
      <td class="content_row">
         <select class="text" name="id_chofer" style="width:300px" onmousedown="markfield(this,0)" 
            onblur="markfield(this,1)" onchange="setChofer(this.value)"" >
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($choferes as $chofere)
            {  
               if($chofere["id"] == $despacho["id_chofer"])
               {  ?>
                  <option value="<?=$chofere["id"]?>"
                  <?php if($chofere["id"] == $despacho["id_chofer"]) echo "selected"?>><?=$chofere["transports_chofer_nombre"].' '.$chofere["transports_chofer_paterno"]?></option>
                  <?php
               }
            }
         ?>  
         </select>
      </td>
      <td class="content_rowl">Sello</td>
      <td class="content_row">        
          <input type="text" class="text" name="sello" style="width:160px" value="<?=$despacho["sello"]?>"
          onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Hora de Entrada *</td>
      <td class="content_row">
         <input type="time" class="text" name="hora_ingreso" id="hora_ingreso" style="width:100px" min="08:00" max="18:00" value="<?=$despacho["hora_ingreso"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_rowl">Hora de Salida *</td>
      <td class="content_row">
         <input type="time" class="text" name="hora_salida" id="hora_salida" style="width:100px" min="08:00" max="18:00" value="<?=$despacho["hora_salida"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Observaciones</td>
      <td class="content_row" style="color:navy">
         <textarea class="text" style="width:100%; height:45px" name="observacion" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($despacho["observacion"])?></textarea>
      </td>
      <td class="content_rowl">Estado</td>
      <td class="content_row">        
         <?php
            $statimg = "";
            switch((int)$despacho["estado"])
            {
               case 1: $statimg = "green_active.gif"; break;
               case 2: $statimg = "red_active.gif"; break;
               case 3: $statimg = "gray.gif"; break;
            }
         ?>
         <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
         <?=getEstadoDeDesapacho($despacho["estado"], true)?>
      </td>
   </tr>
</table>
<?=Nifty_printF()?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<br>
<?php
//----------------------------------------------------------------------------------
?>
   <?=Nifty_printH("box1", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" valign="top" align="center">Código</td>
      <td class="content_tbl_header" valign="top" align="center">Descripción</td>
      <td class="content_tbl_header" valign="top" align="center">Unidad</td>
      <td class="content_tbl_header" valign="top" align="right">Cantidad</td>
      <td class="content_tbl_header" valign="top" align="right">Despacho</td>
   </tr>
   <?php
   $rowcount = count($detalle);
   for($y = 0; $y < $rowcount; $y++)
   {
      ?>
      <tr>
         <input type="hidden" name="id_detalle_<?=$y?>" id="id_detalle_<?=$y?>" value="<?=$detalle[$y]["id"]?>">
         <td class="content_row" valign="top" align="center"><?=$detalle[$y]["item_number_prod"]?></td>
         <td class="content_row" valign="top" ><?=$detalle[$y]["item_nameshop"]?></td>
         <td class="content_row" valign="top" align="center"><?=$detalle[$y]["unit_name"]?></td>
         <td class="content_row" valign="top" align="center"><?=printPrice($detalle[$y]["cantidad"],0)?></td>
         <td class="content_row" align="right" valign="top">
               <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" onkeypress="$('#idx_finalbtn1, #idx_finalbtn2').hide(0);"
               name="salida_<?=$y?>" id="salida_<?=$y?>"
               value="<?=printPrice($detalle[$y]["salida"],0)?>">
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php

?>
<?=Nifty_printH("boxopt_t", "980", 0)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="16%" style="padding-right:5px">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
         if($despacho["estado"] == 1) /*|| $despacho["estado"] == 2 || $despacho["estado"] == 4*/
            printButton("Guardar", "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
         if($despacho["estado"] == 1 || $despacho["estado"] == 2 )
            printButton("Anular", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=anu&id_despacho={$_REQUEST["id_despacho"]}')", "cross-circle-frame");
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
         if($despacho["estado"] == 1 || $despacho["estado"] == 2 )
            printButton("Eliminar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id_despacho={$_REQUEST["id_despacho"]}')", "cross-circle-frame");
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
         if($despacho["estado"] == 3)
              printButton("Abrir", "postnav", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=abrir&id_despacho={$_REQUEST["id_despacho"]}')", "disk-black");
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
         if($despacho["estado"] == 1)
            printButton("Finalizar", "postnav", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=archivar&id_despacho={$_REQUEST["id_despacho"]}')", "disk-black");
      ?>
   </td>
</tr>
</table>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if($_REQUEST["setPosOrder"] == "prodnumber")
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
}


