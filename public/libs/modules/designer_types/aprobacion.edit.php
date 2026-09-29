<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: Ingreso de Itemas de Propuestas de Diseño
//----------------------------------------------------------------------------------
/*
echo("Identificador : ".$_REQUEST["id"]);
*/
/*

$_REQUEST["req_id_propuesta"] = (int)$_REQUEST["req_id_propuesta"];
$_REQUEST["req_id_item"]      = (int)$_REQUEST["req_id_item"];
$_REQUEST["fab_design_name"]  = trim(addslashes($_REQUEST["fab_design_name"]));
*/
$_REQUEST["req_data_id"] = (int)$_REQUEST["req_data_id"];
$posdata = getOrderPos($CON, $_REQUEST["id"],0);
$posdata = $posdata[0];
if($_REQUEST["subexec"] == "del")
{
   $sql = "select req_numero_aprob, req_estado_aprob from orders where id = {$_REQUEST["id"]}";
   $apr = $CON->select($sql); 
   $apr = $apr[0];

   $sql = "update orders set req_estado_aprob = '3' where id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $fecha = time();
   $sql = "update aprobacion_dis set apro_fec_resolucion = {$fecha}
                                    ,apro_id_user_rev    = {$_SESSION["user_id"]}
                                    ,apro_observacion    = ''
                                    ,apro_estado         = 3
              where id = {$apr["req_numero_aprob"]};";
   $res = $CON->no_result($sql);
   if($res)
   {
      generateDiseñoOrderRechazaMail($CON,$_REQUEST["id"]);
      ?>
         <script>alert("Rechazado, se envío mail de revisión")</script>
      <?php
   }
   $_REQUEST["subexec"] = "";
} 
if($_REQUEST["subexec"] == "save")
{
   if((int)$_REQUEST["req_data_id"])
   {
      $sql = " update orders_items
                  set item_img_hash   = '',
                      fab_design_name = '' 
               where req_id   = {$_REQUEST["id"]} and
                     item_id  = {$posdata["item_id"]} and
                     item_pos = 0 ";
      $CON->no_result($sql);

      echo("fab_design_imagehash2".$_REQUEST["fab_design_imagehash2"]);
      ?>
      <br>
      <?php
      echo("fab_design_imagehash3".$_REQUEST["fab_design_imagehash3"]);
      if($_REQUEST["req_dise1"]=="Si") 
      {
         $newitemimgdesign = $_REQUEST["fab_design_imagehash2"];
         $origen  = $_SERVER['DOCUMENT_ROOT'] . "/docs.tran/versiones/".$_REQUEST["fab_design_imagehash2"];
         $destino = $_SERVER['DOCUMENT_ROOT'] . "/docs.order/".$_REQUEST["fab_design_imagehash2"];
      }
      else
      {
         $newitemimgdesign = $_REQUEST["fab_design_imagehash3"];
         $origen  = $_SERVER['DOCUMENT_ROOT'] . "/docs.tran/versiones/".$_REQUEST["fab_design_imagehash3"];
         $destino = $_SERVER['DOCUMENT_ROOT'] . "/docs.order/".$_REQUEST["fab_design_imagehash3"];
      }
      unlink($destino);
      copy($origen, $destino);

      $sql = " update orders_items
               set fab_design_imagehash = '{$_REQUEST["fab_design_imagehash"]}', 
                   fab_design_name      = '{$_REQUEST["fab_design_name"]}', 
                   fab_id_diseño        = {$_REQUEST["req_data_id"]},
                   fab_id_propuesta     = {$_REQUEST["req_id_propuesta"]},
                   fab_id_item          = {$_REQUEST["req_id_item"]}
               where
               req_id   = {$_REQUEST["id"]} and
               item_id  = {$posdata["item_id"]} and
               item_pos = 0";
      $CON->no_result($sql);
   }
   $sql = "select req_numero_aprob, req_estado_aprob from orders where id = {$_REQUEST["id"]}";
   $apr = $CON->select($sql); 
   $apr = $apr[0];

   $sql = "update orders set req_estado_aprob = '2' where id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $fecha = time();
   $sql = "update aprobacion_dis set apro_fec_resolucion = {$fecha}
                                    ,apro_id_user_rev    = {$_SESSION["user_id"]}
                                    ,apro_observacion    = ''
                                    ,apro_estado         = 2
              where id = {$apr["req_numero_aprob"]};";
   $res = $CON->no_result($sql);
   if($res)
   {
      generateDiseñoOrderAprobMail($CON,$_REQUEST["id"]);
      ?>
         <script>alert("Aprobación Existosa, se envío mail de revisión")</script>
      <?php
   }
   $_REQUEST["subexec"] = "";
}

//----------------------------------------------------------------------------------
$sql = "select * from orders o
             inner join customer c on c.id = o.req_cust_id 
          where o.id = {$_REQUEST["id"]}";
$ordenes = $CON->select($sql);
$ordenes = $ordenes[0];

$sql = "select * from orders_items oi
           inner join item i on oi.item_id = i.id
         where req_id = {$_REQUEST["id"]}";
$order_item = $CON->select($sql);
$order_item = $order_item[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from pro_dis_items t1
         where
         t1.pro_dis_items_id = {$order_item["fab_id_item"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
         t2.add_status  = 1
         order by t2.add_name";
$colors = $CON->select($sql);

// busqueda de Modelo de Bolsas
$sql = " select id         as id
              , add_name   as bolsa
            from tran_comments_vals 
             where add_com_id = 20
               and add_status > 0" ;
$modelobolsas = $CON->select($sql);
//----------------------------------------------------------------------------------
// Medidas de Bolsa
$sql = "select id as codigo, med_name as descripcion
            from prod_medidas
         where med_status > 0";
$medidabolsas = $CON->select($sql);
//----------------------------------------------------------------------------------
// MAterialidad
$sql = "select fabt_code, fabt_name from fabric_types where fabt_status > 0";
$materialidades = $CON->select($sql);
//----------------------------------------------------------------------------------
/* CARGAR PIE DE IMPRENTA */
$sql = "select * from parametros where tabla = 'PIEIMPRENTA'";
$pieimprenta = $CON->select($sql);

$sql = " select t1.*
               ,concat(t2.user_firstname,' ',t2.user_lastname) as user_cr
               ,concat(t3.user_firstname,' ',t3.user_lastname) as user_md
         from pro_dis_items t1
               inner join user t2 on pro_dis_items_user_cr = t2.id
               inner join user t3 on pro_dis_items_user_md = t3.id
         where t1.pro_dis_items_pro_id = {$_REQUEST["id"]}
         and t1.pro_dis_items_id = (case when {$_REQUEST["id_item"]} = 0 then t1.pro_dis_items_id else {$_REQUEST["id_item"]} end)
         order by t1.pro_dis_items_id desc ";
$items = $CON->select($sql);
$items = $items[0];

//----------------------------------------------------------------------------------
// Buscador de Detalles
$sql = "select t1.* from pro_dis_detalle t1
          where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
$detalle = $CON->select($sql);

// busca items_A
$sql = "select * from pro_dis_items
          where pro_dis_items_pro_id = {$_REQUEST["id"]}
          order by pro_dis_items_id";
$posdata = $CON->select($sql);

/*---------------------------------------------------------------*/
$sql = "select tran_docs.id
              ,tran_docs.doc_crtdat
              ,tran_docs.doc_name
              ,tran_docs.doc_file
              ,tran_docs.doc_tran_id
              ,pro_dis_items.pro_dis_items_pro_id as id_propuesta
              ,pro_dis_items.pro_dis_items_id     as id_item
         from pro_dis 
            inner join pro_dis_items on id = pro_dis_items_pro_id and pro_dis_items_status = 3
            inner join pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id and pro_dis_detalle_status = 3
            inner join tran_docs on doc_tran_id = pro_dis_detalle_id and doc_tran_type = 'versiones'
         where pro_dis_custid = {$ordenes["req_cust_id"]} ";
$versiones = $CON->select($sql);

/*---------------------------------------------------------------*/
$sql = "select tran_docs.id
              ,tran_docs.doc_crtdat
              ,tran_docs.doc_name
              ,tran_docs.doc_file
              ,tran_docs.doc_tran_id
              ,pro_dis_items.pro_dis_items_pro_id as id_propuesta
              ,pro_dis_items.pro_dis_items_id     as id_item
         from pro_dis 
            inner join pro_dis_items on id = pro_dis_items_pro_id and pro_dis_items_status = 3
            inner join pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id and pro_dis_detalle_status = 3
            inner join tran_docs on doc_tran_id = pro_dis_detalle_id and doc_tran_type = 'versiones'
         where pro_dis_custid in(124,1328)";
$version_generico = $CON->select($sql);

/*---------------------------------------------------------------*/
?>
<script>
   function proformcheck(select)
   {
      // alert("paso por aca inicio");
      let dataId = document.getElementById("req_data_id").value; // Este valor debería venir de tu lógica
      document.getElementById("req_data_id").value = dataId; // Asignar el valor antes del envío
      let seleccionado = document.querySelector('input[name="req_dise1"]:checked');
      if (seleccionado) 
      {
          seleccionado.checked = true; 
      }
      else
      {
         document.querySelector('input[name="req_dise1"][value="No"]').checked = true;
      }
      // console.log("Valor seleccionado:", seleccionado ? seleccionado.value : "Ninguno seleccionado");
      // document.getElementById("subexec").value = "save"; // Asignar el valor antes del envío
      // console.log("Valor antes del submit:", document.getElementById("subexec").value);
      // alert("paso por aca fin 2");
      // let selectedOption = select.options[select.selectedIndex]; // Obtiene la opción seleccionada
      // let dataId = selectedOption.getAttribute("data-id");                              
      // let dataId = "12345"; // Aquí debes asignar el valor correcto
      // document.getElementById("req_data_id").value = 5555; // Asegura que el campo tenga el valor antes del envío
      return true;
   }
</script>
<form action="index.php" method="post" name="xform_aprueba"  class="fokusfirst" onsubmit="return proformcheck(this);">
   <input type="hidden" name="subexec" value="">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="deldesignimg" value="">
   <input type="hidden" name="senddesignmail" value="">
   <input type="hidden" name="autoopensendmail" value="">
   <input type="hidden" name="autoopenpdf" value="">
   <input type="hidden" name="req_id_propuesta" id="req_id_propuesta" value="<?=$_REQUEST["req_id_propuesta"]?>">
   <input type="hidden" name="req_id_item" id="req_id_item" value="<?=$_REQUEST["req_id_item"]?>">
   <input type="hidden" name="fab_design_name" id="fab_design_name" value="<?=$_REQUEST["fab_design_name"]?>">
   <input type="hidden" name="fab_design_imagehash" id="fab_design_imagehash" value="<?=$_REQUEST["fab_design_imagehash"]?>">
   <input type="hidden" name="req_data_id" id="req_data_id" value="">
   <input type="hidden" name="delposimg" value="">
</form>
   <?=Nifty_printH("box2", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" colspan="4">Datos de la Confirmación de Compra</td>
      </tr>
      <tr>
         <td colspan="4">
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <tr>
               <td class="content_rowl">Propuesta</td>
               <td class="content_row"><nobr><?=$ordenes["req_number"]?></nobr></td>
               <td class="content_rowl">Versión</td>
               <td class="content_row"><nobr><?=date('d.m.Y',$ordenes["req_crtdat"])?></nobr></td>
               <td class="content_rowl"><nobr>Nombre de la Imagen</nobr></td>
               <td class="content_row" colspan="2"><?=$order_item["item_title"]?></td>
            </tr>
         </table>
         </td>
      <tr>
      <tr>
         <td class="content_rowl">Número de C.C.</td>
         <td class="content_row"><?=$ordenes["req_number"]?></td>
         <td class="content_rowl">Fecha</td>
         <td class="content_row"><?=date('d.m.Y',$ordenes["req_crtdat"])?></td>
      </tr>
      <tr>
         <td class="content_rowl">Rut</td>
         <td class="content_row"><?=$ordenes["cust_rut"]?></td>
         <td class="content_rowl">Clientes</td>
         <td class="content_row"><?=$ordenes["cust_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">SKU</td>
         <td class="content_row"><?=$order_item["item_number_prod"]?></td>
         <td class="content_rowl">Descripción</td>
         <td class="content_row"><?=$order_item["item_title"]?></td>
      </tr>
      <tr>
         <td class="content_tbl_header" colspan="4">Detalle de las Solicitud de la Propuesta</td>
      </tr>
      <tr>
         <td class="content_rowl">Modelo de Bolsa</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px" name="pro_dis_items_modelo_bolsa" id="pro_dis_items_modelo_bolsa" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($modelobolsas as $modelobolsa)
                     {  ?>
                        <option value="<?=$modelobolsa["id"]?>"
                        <?php if($modelobolsa["id"] == $headdata["pro_dis_items_modelo_bolsa"]) echo "selected"?>><?=$modelobolsa["bolsa"]?></option>
                        <?php
                     }
               ?>
            </select>
         </td>
         <td class="content_rowl">Medida de Bolsa</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px" name="pro_dis_items_medida_bolsa" id="pro_dis_items_medida_bolsa" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($medidabolsas as $medidabolsa)
                     {?>
                        <option value="<?=$medidabolsa["codigo"]?>"
                        <?php if($medidabolsa["codigo"] == $headdata["pro_dis_items_medida_bolsa"]) echo "selected"?>><?=$medidabolsa["descripcion"]?></option>
                     <?php
                     }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Materialidad de Bolsa</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px" name="pro_dis_items_materialidad" id="pro_dis_items_materialidad" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($materialidades as $materialidad)
                     {  ?>
                        <option value="<?=$materialidad["fabt_code"]?>"
                          <?php if($materialidad["fabt_code"] == $headdata["pro_dis_items_materialidad"]) echo "selected"?>><?=$materialidad["fabt_name"]?></option>
                          <?php
                     }
               ?>
            </select>
         </td>
         <td class="content_rowl">Color de Tela</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px;" name="pro_dis_items_color_tela">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                  foreach($colors AS $color)
                  {  ?>
                     <option value="<?=$color["id"]?>" <?if($color["id"] == $headdata["pro_dis_items_color_tela"]) echo "selected"?>>
                        <?=$color["add_name"]?>
                     </option>
                     <?php
                  }
               ?>
            </select>
         </td>

      </tr>
      <tr>
         <td class="content_rowl">Tipo de Impresion</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px;" name="pro_dis_items_tipo_impresion" onfocus="markfield(this,0)" onblur="markfield(this,1)>
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <option value="FLEX" <?if($headdata["pro_dis_items_tipo_impresion"] == "FLEX") echo "selected"?>>Flexografia</option>
                  <option value="SERI" <?if($headdata["pro_dis_items_tipo_impresion"] == "SERI") echo "selected"?>>Serigrafia</option>
            </select>
         </td>
         <td class="content_rowl">Color de Manilla *</td>
         <td class="content_row">
            <select disabled class="text" style="width:330px;" name="pro_dis_items_color_manilla">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                  foreach($colors AS $color)
                  {  ?>
                     <option value="<?=$color["id"]?>" <?if($color["id"] == $headdata["pro_dis_items_color_manilla"]) echo "selected"?>>
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
            <select disabled class="text" style="width:330px" name="pro_dis_items_pie_imprenta">
            <?php
                  ?>
                      <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($pieimprenta AS $pimprenta)
                  {  ?>
                     <option value="<?=$pimprenta["codigo"]?>" <?if($headdata["pro_dis_items_pie_imprenta"] == $pimprenta["codigo"]) echo "selected"?>>
                        <?=$pimprenta["descripcion"]?>
                     </option>
                     <?php
                 }
            ?>
            </select>
         </td>
         <td class="content_rowl">Cantidad de Colores</td>
         <td class="content_row">
             <input name="pro_dis_items_cantidad_color" type="text" class="text" 
                 value="<?=$headdata["pro_dis_items_cantidad_color"]?>" readonly>
         </td>
      <tr>
         <td class="content_tbl_header" colspan="4">Lados y colores de impresion 99<td>
      </tr>
      <?php
         $x = 1;
         $xx = 6;
         while($x <= 5)
         {  
            ?>
            <tr>
                <td class="content_rowl" height="1">Color Nro<?=$x?></td>
                <td class="content_row">
                   <input type="checkbox" disabled name="pro_dis_items_frente<?=$x?>" value="1" <?php if((int)$order_item["pro_dis_items_frente{$x}"]) echo "checked"?>>Frente
                   <input type="checkbox" disabled name="pro_dis_items_dorso<?=$x?>" value="1" <?php if((int)$order_item["pro_dis_items_dorso{$x}"]) echo "checked"?>>Dorso
                   <input type="text" disabled class="text" style="width:200px" name="pro_dis_items_color<?=$x?>" value="<?=$order_item["pro_dis_items_color{$x}"]?>">
                </td>
                <td class="content_rowl" height="1">Color * Nro<?=$xx?></td>
                <td class="content_row">
                   <input type="checkbox" disabled name="pro_dis_items_frente<?=$xx?>" value="1" <?php if((int)$order_item["pro_dis_items_frente{$xx}"]) echo "checked"?>>Frente
                   <input type="checkbox" disabled name="pro_dis_items_dorso<?=$xx?>" value="1" <?php if((int)$order_item["pro_dis_items_dorso{$xx}"]) echo "checked"?>>Dorso
                   <input type="text" disabled class="text" style="width:200px" name="pro_dis_items_color<?=$xx?>" value="<?=$order_item["pro_dis_items_color{$xx}"]?>">
                </td>
            </tr>
            <?php
            $x++;
            $xx++;
         }
      ?>
      <tr>
         <td class="content_tbl_header" colspan=4>Cambiar Imagen<td>
      </tr>
      <table border="0" cellspacing="0" cellpadding="3" width="100%">
         <tr>
            <td class="content_rowl" valign="top">Cambiar Diseño</td>
            <td class="content_row" >
               <input type="radio" onchange="mostrarTD('Si')" value="Si" name="req_dise1" <?echo "checked"?>>Cliente
               <input type="radio" onchange="mostrarTD('No')" value="No" name="req_dise1">Generico
            <td>
            <td id="tdCliente" style="display: table-cell;">
                     <select id="fab_design_imagehash2" name="fab_design_imagehash2" onchange="mostrarImagen(this)" style="width:330px">
                        <option value="" data-img="" data-id="" data-ip="" data-it="" data-nm="">-- Seleccionar --</option>
                        <?php  foreach($versiones as $version)
                        {
                        ?>
                           <option value="<?= $version['doc_file'] ?>" 
                                   data-img="<?=$version['doc_file'] ?>"
                                   data-id="<?=$version['id']?>"
                                   data-ip="<?=$version['id_propuesta']?>"
                                   data-it="<?=$version['id_item']?>"
                                   data-nm="<?=$version['doc_name']?>"
                                   >
                              <?= $version['doc_name'] ?>
                           </option>
                        <?php } ?>
                     </select>
                     <br>
                     <img id="preview" src="" style="display:none; width:100px; margin-top:10px;">
            </td>
            <td id="tdGenerico" style="display: none;">
                     <select id="fab_design_imagehash3" name="fab_design_imagehash3" onchange="mostrarImagen(this)" style="width:330px">
                        <option value="" data-img="" data-id="" data-ip="" data-it="" data-nm="">-- Seleccionar -- </option>
                        <?php  foreach($version_generico as $version)
                        {
                        ?>
                           <option value="<?= $version['doc_file'] ?>" 
                                   data-img="<?=$version['doc_file'] ?>"
                                   data-id="<?=$version['id']?>"
                                   data-ip="<?=$version['id_propuesta']?>"
                                   data-it="<?=$version['id_item']?>"
                                   data-nm="<?=$version['doc_name']?>"
                                   >
                              <?= $version['doc_name'] ?>
                           </option>
                        <?php } ?>
                     </select>
                     <br>
                     <img id="preview2" src="" style="display:none; width:100px; margin-top:10px;">
            </td>

            <script>

               function mostrarImagen(select)
               {
                     let img = document.getElementById("preview");
                     let img2 = document.getElementById("preview3");

                     let selectedOption = select.options[select.selectedIndex]; // Obtiene la opción seleccionada
                     let imgSrc = selectedOption.getAttribute("data-img"); // Obtiene la imagen asociada
                     let dataId = selectedOption.getAttribute("data-id");                              
                     let dataIp = selectedOption.getAttribute("data-ip");                              
                     let dataIt = selectedOption.getAttribute("data-it");                              
                     let dataNm = selectedOption.getAttribute("data-nm");                              
                     document.getElementById("req_data_id").value = dataId;
                     document.getElementById("req_id_propuesta").value = dataIp;
                     document.getElementById("req_id_item").value = dataIt;
                     document.getElementById("fab_design_name").value = dataNm;
                     document.getElementById("fab_design_imagehash").value = imgSrc;
                     if (imgSrc) {
                        img.src = "./docs.tran/versiones/" + imgSrc; // Ajusta la ruta donde están las imágenes
                        img.style.display = "none"; // Muestra la imagen
                        img2.src = "./docs.tran/versiones/" + imgSrc; // Ajusta la ruta donde están las imágenes
                        img2.style.display = "block"; // Muestra la imagen
                     } else {
                        img.style.display = "none"; // Oculta si no hay selección
                     }
                     let archivoSeleccionado = selectedOption.value;
                     document.getElementById("nombreArchivo").textContent = dataId || "Seleccione un archivo";
               }
               /*
               function mostrarImagen2(select)
               {
                     let img = document.getElementById("preview2");
                     let img2 = document.getElementById("preview3");
                     let selectedOption = select.options[select.selectedIndex]; // Obtiene la opción seleccionada
                     let imgSrc = selectedOption.getAttribute("data-img"); // Obtiene la imagen asociada
                     let dataId = selectedOption.getAttribute("data-id");                              
                     let dataIp = selectedOption.getAttribute("data-ip");                              
                     let dataIt = selectedOption.getAttribute("data-it");  
                     let dataNm = selectedOption.getAttribute("data-nm");                                                          
                     document.getElementById("req_data_id").value = dataId;
                     document.getElementById("req_id_propuesta").value = dataIp;
                     document.getElementById("req_id_item").value = dataIt;
                     document.getElementById("fab_design_name").value = dataNm;
                     document.getElementById("fab_design_imagehash").value = imgSrc;
                     sessionStorage.setItem("data_id").value = dataId;
                     if (imgSrc) {
                        img.src = "./docs.tran/versiones/" + imgSrc; // Ajusta la ruta donde están las imágenes
                        img.style.display = "block"; // Muestra la imagen
                        img2.src = "./docs.tran/versiones/" + imgSrc; // Ajusta la ruta donde están las imágenes
                        img2.style.display = "block"; // Muestra la imagen
                     } else {
                        img.style.display = "none"; // Oculta si no hay selección
                     }
                     let archivoSeleccionado = selectedOption.value;
                     document.getElementById("nombreArchivo").textContent = dataId || "Seleccione un archivo";
               }
               */

               function mostrarTD(valor) 
               {
                  var selectElement = document.getElementById("fab_design_imagehash2");
                  selectElement.value = ""; // Limpia el valor
                  selectElement.dispatchEvent(new Event("change")); // Dispara el evento onchange

                  var selectElement = document.getElementById("fab_design_imagehash3");
                  selectElement.value = ""; // Limpia el valor
                  selectElement.dispatchEvent(new Event("change")); // Dispara el evento onchange
                  // document.getElementById("fab_design_imagehash").value = "";
                  // document.getElementById("fab_design_imagehash2").value = "";
                  // document.getElementById("fab_design_imagehash3").value = "";
                  if (valor === "Si") {
                     document.getElementById("tdCliente").style.display = "table-cell";
                     document.getElementById("tdGenerico").style.display = "none";
                  } else {
                     document.getElementById("tdCliente").style.display = "none";
                     document.getElementById("tdGenerico").style.display = "table-cell";
                  }
               }

            </script>
        </tr>
      </table>
      <tr>
          <td class="content_tbl_header" colspan=4>Imagen<td>
      </tr>
      <tr>
         <td class="content_row" colspan="4">
            <table border="0" cellspacing="0" cellpadding="3" width="100%">
               <colgroup>
                  <col width="100%">
               </colgroup>
               <tr>
                  <?php
                  $nombre_del_archivo = ''
                  ?>
                  <td id="nombreArchivo" align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Nombre del Archivo.pdf</td>
               </tr>
               <tr>
                   <td align="center" class="content_rowl content_row_os" style="cursor:pointer"
                       <a href="./docs.order/<?=$order_item["fab_design_imagehash"]?>" target="_blank">
                           <img id="preview3" border="0" src="./docs.order/<?=$order_item["fab_design_imagehash"]?>" width="100%" style="float:left">
                       </a>
                   </td>
               </tr>
         </td>
      </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("boxopt_b", "980",0)?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td align="left" width="130" style="padding-right:5px">
            <?php printButton("Volver", "postnav", "index.php?mid={$_REQUEST["mid"]}","","arrow-180",150); ?>
         </td>
         <td>&nbsp;</td>
         <td align="right" width="130" style="padding-right:5px">
            <?php 
               // printButton("Rechazar", "postnav_del","javascript: deactivateFormChange()","askDel('index.php?mid={$_REQUEST["mid"]}&subexec=rechaza&req_data_id={$_REQUEST["req_data_id"]}')","cross-circle-frame", 150);
               printButton("Rechazar", "postnav_del", "javascript: deactivateFormChange()", "document.forms['xform_aprueba'].elements['subexec'].value = 'del'; submitForm(document.xform_aprueba);","disk-black");               
               // printButton("Rechazar", "postnav_del","javascript: deactivateFormChange()","submitForm(document.xform_aprueba)", "disk-black");
            ?>
         </td>                              
         <td align="right" width="130">                           
            <?php
            if($_REQUEST["req_dise1"]=="Si") 
               $newitemimgdesign = $_REQUEST["fab_design_imagehash2"];
            else
               $newitemimgdesign = $_REQUEST["fab_design_imagehash3"];
            // printButton("Aprobar                  ", "postnav_save", "javascript: deactivateFormChange()","askDel('index.php?mid={$_REQUEST["mid"]}&subexec=save&id={$_REQUEST["id"]}&id_item={$_REQUEST["id_item"]}&fab_design_imagehash={$_REQUEST["id"]}')","tick-circle-frame", 150); 
            // printButton("Aprobar", "postnav_save", "javascript: deactivateFormChange()","askDel('index.php?mid={$_REQUEST["mid"]}&subexec=save&id={$_REQUEST["id"]}&id_item={$_REQUEST["id_item"]}&fab_design_imagehash={$_REQUEST["id"]}')","tick-circle-frame", 150); 
            printButton("Aprobar", "postnav_save", "javascript: deactivateFormChange()", "document.forms['xform_aprueba'].elements['subexec'].value = 'save'; submitForm(document.xform_aprueba);","disk-black");
            // printButton("Aprobar 3", "postnav","javascript: deactivateFormChange()","index.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}&subexec=save&exec=edit&req_data_id={$_REQUEST["req_data_id"]}", "disk-black");
            ?>
            <script>
               function submitForGrabar(form)
               {
                  // console.log("Enviando formulario con req_data_id:", form.elements["req_data_id"].value);
                  document.getElementById("subexec").value = "save";
                  form.submit();
               }
            </script>
         </td>
      </tr>
   </table>
   <?=Nifty_printF(false)?>
<?php
