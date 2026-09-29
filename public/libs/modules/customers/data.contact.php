<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["ccom"] == "save")
{
   $currtme = time();

   if($_REQUEST["cid"] != "")
   {
      $sql = " delete from customer_contacts
               where
               id          = {$_REQUEST["cid"]} and
               add_cust_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   $_REQUEST["add_rut"]                = trim(addslashes($_REQUEST["add_rut"]));
   $_REQUEST["add_website"]            = trim(addslashes($_REQUEST["add_website"]));
   $_REQUEST["add_cellphone"]          = trim(addslashes($_REQUEST["add_cellphone"]));
   $_REQUEST["add_phone"]              = trim(addslashes($_REQUEST["add_phone"]));
   $_REQUEST["add_email"]              = trim(addslashes($_REQUEST["add_email"]));
   $_REQUEST["add_city"]               = trim(addslashes($_REQUEST["add_city"]));
   $_REQUEST["add_postcode"]           = trim(addslashes($_REQUEST["add_postcode"]));
   $_REQUEST["add_street"]             = trim(addslashes($_REQUEST["add_street"]));
   $_REQUEST["add_lastname"]           = trim(addslashes($_REQUEST["add_lastname"]));
   $_REQUEST["add_firstname"]          = trim(addslashes($_REQUEST["add_firstname"]));
   $_REQUEST["add_id_crm"]             = trim(addslashes($_REQUEST["add_id_crm"]));
   $_REQUEST["add_tipo_cust"]          = trim(addslashes($_REQUEST["add_tipo_cust"]));
   $_REQUEST["add_cargo"]              = trim(addslashes($_REQUEST["add_cargo"]));
   $_REQUEST["add_seller"]             = (int)$_REQUEST["add_seller"];

   $sql = " insert into customer_contacts
            (add_cust_id, add_firstname, add_lastname, add_street, add_postcode, add_city,
             add_email, add_phone, add_cellphone, add_website, add_rut, add_id_crm, add_tipo_cust, add_cargo,add_seller)
            VALUES
            ({$_REQUEST["id"]}, '{$_REQUEST["add_firstname"]}', '{$_REQUEST["add_lastname"]}', '{$_REQUEST["add_street"]}',
             '{$_REQUEST["add_postcode"]}', '{$_REQUEST["add_city"]}', '{$_REQUEST["add_email"]}',
             '{$_REQUEST["add_phone"]}', '{$_REQUEST["add_cellphone"]}', '{$_REQUEST["add_website"]}',
             '{$_REQUEST["add_rut"]}', '{$_REQUEST["add_id_crm"]}' ,  '{$_REQUEST["add_tipo_cust"]}','{$_REQUEST["add_cargo"]}',{$_REQUEST["add_seller"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from customer_contacts
               where
               add_cust_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      if($_REQUEST["add_pos"] != "")
      {
         $sql = " update customer_contacts
                  set
                  add_pos     = {$_REQUEST["add_pos"]}
                  where
                  add_cust_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
      else
      {
         $sql = " select count(id) 'idcount'
                  from customer_contacts
                  where
                  add_cust_id = {$_REQUEST["id"]} and
                  id          != {$thisid}";
         $idcount = $CON->select($sql);
         $idcount = (int)$idcount[0]["idcount"];
   
         $sql = " update customer_contacts
                  set
                  add_pos     = {$idcount}
                  where
                  add_cust_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
   }

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from customer_contacts
               where
               add_cust_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["cid"] = $thisid[0]["thisid"];
   }

   $savemsg = getSaveMessage($res);

   $sql = " update customer
            set
            cust_updusr = {$_SESSION["user_id"]},
            cust_upddat = {$currtme}
            where
            id          = {$_REQUEST["id"]}";
   $CON->no_result($sql);

      /* Actualiza informacion en API */

      $sql = "select * from customer where id = {$_REQUEST["id"]}";
      $idcliente  = $CON->select($sql);
      $idcliente  = $idcliente[0];

      $sql        = "select nombre from comunas where id = {$idcliente['cust_comunaid']}";
      $comunas    = $CON->select($sql);
      $comuna     = $comunas[0]['nombre'];

      $sql        = "select * from regions where id = {$idcliente['cust_regionid']}";
      $regiones   = $CON->select($sql);
      $region     = $regiones[0]['name'];

      $sql        = "select * from country where id = {$idcliente['cust_countryid']}";
      $paises     = $CON->select($sql);
      $pais       = $paises[0]['country_name'];

      $sql         = "select * from parametros where tabla = 'CANAL' and codigo = {$_REQUEST["cust_canal"]}";
      $canal       = $CON->select($sql);
      $canal       = $canal[0];
      $canal       = $canal["descripcion"];

      $sql        = "select * from giros where id = {$idcliente['cust_giroid']}";
      $giros      = $CON->select($sql);
      $giro       = $giros[0]['giro_name'];

      $monto      = 0;
      $rut        = $idcliente['cust_rut'];
      $direccion  = $idcliente['cust_street'];
      $empresa    = $idcliente['cust_company'];

     
      $sql        = "SELECT cat_name from customer_cats where id = {$idcliente['cust_catid']}";
      $rubro      = $CON->select($sql);
      $rubro      = $rubro[0]["cat_name"];
   
      $sql = "select * from customer_sub_cats where id = {$idcliente['cust_subrubro']}";
      $subrubro = $CON->select($sql);
      $subrubro  = $subrubro[0]['sub_cat_name'];
      
      $sql        = "select add_id_crm from customer_contacts where add_id_crm = {$_REQUEST["add_id_crm"]} ";
      $idcrm      = $CON->select($sql);
      $idcrm2     = $_REQUEST["add_id_crm"];
      $nombre     = $idcrm['add_firstname'].' '.$idcrm['add_lastname'];
      $email      = $idcrm['add_email'];
      $celular    = $idcrm['add_cellphone'];

      $sql         = "select * from parametros where tabla = 'CRM_SELLER' and codigo = {$_REQUEST["add_seller"]}";
      $vendedor    = $CON->select($sql);
      $vendedor    = $vendedor[0];
      $vendedor    = $vendedor["descripcion"];

      
      if((int)$idcliente['cust_seg_act'])
          $fechaseg   = $idcliente['cust_seg_alertdate'];
      else         
          $fechaseg   = time();
      $iderp      = $idcliente['id'];

      // echo($idcrm2.' - '.$monto.' - '.$nombre.' - '.$rut.' - '.$direccion.' - '.$empresa.' - '.$email.' - '.$celular.' - '.$comuna.' - '.$region.' - '.$pais.' - '.$giro.' - '.$vendedor.' - '.$fechaseg.' - '.$canal.' - '.$rubro.' - '.$subrubro."<br>");
          
      CallApiCRM('contacto', $idcrm2,$monto,$nombre,$rut,$direccion,$empresa,$email,$celular,$comuna,$region,$pais,$giro,$vendedor,$fechaseg,$iderp,$canal,$rubro,$subrubro);
}

if($_REQUEST["cid"] != "")
{
   $sql = " select *
            from customer_contacts
            where
            id          = {$_REQUEST["cid"]} and
            add_cust_id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}
else
{
   $data["add_city"] = "Santiago de Chile";
}

$sellers    = getSellers($CON);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer"
onsubmit="return checkform(new Array(this.add_firstname))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<table cellpadding="0" border="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del contacto I</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="add_firstname" type="text" class="text" style="width:365px" value="<?=$data["add_firstname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Apellido</td>
         <td class="content_row">
            <input name="add_lastname" type="text" class="text" style="width:365px" value="<?=$data["add_lastname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cargo</td>
         <td class="content_row">
            <input name="add_cargo" type="text" class="text" style="width:365px" value="<?=$data["add_cargo"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección</td>
         <td class="content_row">
            <input name="add_street" type="text" class="text" style="width:365px" value="<?=$data["add_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>

      <?php
      // Consulta para obtener los valores del combo box
      $sql = "select * from parametros WHERE tabla = 'TIPOCLIENTE'";
      $resultado = $CON->select($sql);

      // Almacenar las opciones en un array
      $opciones = [];
      if ($resultado && $resultado->num_rows > 0) {
         while ($fila = $resultado->fetch_assoc()) {
            $opciones[] = $fila;
         }
      }
      ?>
      <tr>
         <td class="content_rowl">Tipo Contacto</td>
         <td class="content_row">
            <select name="add_tipo_cust" class="text" style="width:365px" onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php for ($i = 0; $i < count($resultado); $i++): ?>
                  <option value="<?= htmlspecialchars($resultado[$i]['codigo']) ?>" 
                     <?= $data["add_tipo_cust"] === $resultado[$i]['codigo'] ? "selected" : "" ?>>
                     <?= htmlspecialchars($resultado[$i]['descripcion']) ?>
                  </option>
               <?php endfor; ?>
            </select>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Datos del contacto II</td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="add_email" type="text" class="text" style="width:320px" value="<?=$data["add_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Teléfono</td>
         <td class="content_row">
            <input name="add_phone" type="text" class="text" style="width:320px" value="<?=$data["add_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Celular</td>
         <td class="content_row">
            <input name="add_cellphone" type="text" class="text" style="width:320px" value="<?=$data["add_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select class="text" style="width:320px" name="add_seller" id="add_seller" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers as $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>"
                  <?php if($seller["id"] == $data["add_seller"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Codigo Postal</td>
         <td class="content_row">
            <input name="add_postcode" type="text" class="text" style="width:320px" value="<?=$data["add_postcode"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">ID CRM</td>
         <td class="content_row">
            <input name="add_id_crm" type="text" class="text" style="width:320px" value="<?=$data["add_id_crm"]?>" 
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=additional1", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=additional1&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_customer)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_customer');" ?>