<?php
//----------------------------------------------------------------------------------
// Author:        1bit LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$currtme = time();

//----------------------------------------------------------------------------------
if((int)$_REQUEST["delpic"])
{
   $doc_dir = "./images/worker_images/";

   $sql = " select wrk_foto
            from workers
            where
            id = {$_REQUEST["id"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["wrk_foto"] != "")
      unlink("{$doc_dir}{$orgimg[0]["wrk_foto"]}");

   $sql = " update workers
            set wrk_foto = ''
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["wrk_firstname"]       = trim(addslashes($_REQUEST["wrk_firstname"]));
   $_REQUEST["wrk_lastname"]        = trim(addslashes($_REQUEST["wrk_lastname"]));
   $_REQUEST["wrk_telefono1"]       = trim(addslashes($_REQUEST["wrk_telefono1"]));
   $_REQUEST["wrk_telefono2"]       = trim(addslashes($_REQUEST["wrk_telefono2"]));
   $_REQUEST["wrk_telefono3"]       = trim(addslashes($_REQUEST["wrk_telefono3"]));
   $_REQUEST["wrk_rut"]             = trim(addslashes($_REQUEST["wrk_rut"]));
   $_REQUEST["wrk_email"]           = trim(addslashes($_REQUEST["wrk_email"]));
   $_REQUEST["wrk_axx_pass"]        = trim(addslashes($_REQUEST["wrk_axx_pass"]));
   $_REQUEST["wrk_costo_hh"]        = getPrice($_REQUEST["wrk_costo_hh"]);
   $_REQUEST["wrk_turno_turnoid"]   = (int)$_REQUEST["wrk_turno_turnoid"];
   $_REQUEST["wrk_turno_state"]     = (int)$_REQUEST["wrk_turno_state"];
   $_REQUEST["wrk_cargoid"]         = (int)$_REQUEST["wrk_cargoid"];
   $_REQUEST["wrk_birthday"]        = trim(addslashes($_REQUEST["wrk_birthday"]));
   $_REQUEST["wrk_titulo"]          = trim(addslashes($_REQUEST["wrk_titulo"]));
   $_REQUEST["wrk_street"]          = trim(addslashes($_REQUEST["wrk_street"]));
   $_REQUEST["wrk_turno_startdate"] = explode(".", trim($_REQUEST["wrk_turno_startdate"]));
   $_REQUEST["wrk_turno_startdate"] = (int)mktime(0, 0, 0, $_REQUEST["wrk_turno_startdate"][1], $_REQUEST["wrk_turno_startdate"][0], $_REQUEST["wrk_turno_startdate"][2]);
   
   $_REQUEST["wrk_birthday"]        = explode(".", $_REQUEST["wrk_birthday"]);
   $_REQUEST["wrk_birthday"]        = (int)mktime(15, 0, 0, $_REQUEST["wrk_birthday"][1], $_REQUEST["wrk_birthday"][0], $_REQUEST["wrk_birthday"][2]);
   if((int)$_REQUEST["wrk_birthday"])
      $_REQUEST["wrk_birthday"] = date("d.m.Y", $_REQUEST["wrk_birthday"]);
   else
      $_REQUEST["wrk_birthday"] = "";

   $sql_id = (int)$_REQUEST["id"];
   $sql = " select count(*) 'cc'
            from workers
            where
            wrk_rut     = '{$_REQUEST["wrk_rut"]}' and
            wrk_status  > 0 and
            id != {$sql_id}";
   $check = $CON->select($sql);
   $check = (int)$check[0]["cc"];

   if(!(int)$check)
   {
      if($_REQUEST["id"] == "")
      {
	      $sql = " INSERT INTO workers
                  (wrk_firstname, wrk_lastname, wrk_crtdat, wrk_crtusr,
                   wrk_telefono1, wrk_telefono2, wrk_telefono3,
                   wrk_rut, wrk_email, wrk_turno_turnoid, wrk_turno_state, wrk_cargoid, wrk_costo_hh,
                   wrk_birthday, wrk_titulo, wrk_street, wrk_turno_startdate, wrk_axx_pass)
	               VALUES
                  ('{$_REQUEST["wrk_firstname"]}', '{$_REQUEST["wrk_lastname"]}', {$currtme}, {$_SESSION["user_id"]},
                   '{$_REQUEST["wrk_telefono1"]}', '{$_REQUEST["wrk_telefono2"]}',
                   '{$_REQUEST["wrk_telefono3"]}', '{$_REQUEST["wrk_rut"]}', '{$_REQUEST["wrk_email"]}',
                   {$_REQUEST["wrk_turno_turnoid"]}, {$_REQUEST["wrk_turno_state"]}, {$_REQUEST["wrk_cargoid"]},
                   {$_REQUEST["wrk_costo_hh"]}, '{$_REQUEST["wrk_birthday"]}', '{$_REQUEST["wrk_titulo"]}',
                   '{$_REQUEST["wrk_street"]}', {$_REQUEST["wrk_turno_startdate"]}, '{$_REQUEST["wrk_axx_pass"]}')";
	      $res = $CON->no_result($sql);
         if($res)
            $_REQUEST["id"] = mysql_insert_id();
      }
      else
      {
         $sql = " UPDATE workers
	               SET
                  wrk_firstname              = '{$_REQUEST["wrk_firstname"]}',
                  wrk_lastname               = '{$_REQUEST["wrk_lastname"]}',
                  wrk_telefono1              = '{$_REQUEST["wrk_telefono1"]}',
                  wrk_telefono2              = '{$_REQUEST["wrk_telefono2"]}',
                  wrk_telefono3              = '{$_REQUEST["wrk_telefono3"]}',
                  wrk_birthday               = '{$_REQUEST["wrk_birthday"]}',
                  wrk_titulo                 = '{$_REQUEST["wrk_titulo"]}',
                  wrk_street                 = '{$_REQUEST["wrk_street"]}',
                  wrk_rut                    = '{$_REQUEST["wrk_rut"]}',
                  wrk_email                  = '{$_REQUEST["wrk_email"]}',
                  wrk_turno_turnoid          = {$_REQUEST["wrk_turno_turnoid"]},
                  wrk_turno_state            = {$_REQUEST["wrk_turno_state"]},
                  wrk_cargoid                = {$_REQUEST["wrk_cargoid"]},
                  wrk_costo_hh               = {$_REQUEST["wrk_costo_hh"]},
                  wrk_turno_startdate        = {$_REQUEST["wrk_turno_startdate"]},
                  wrk_axx_pass               = '{$_REQUEST["wrk_axx_pass"]}',
                  wrk_updusr                 = {$_SESSION["user_id"]},
                  wrk_upddat                 = {$currtme}
                  WHERE
                 id = {$_REQUEST["id"]}";
         $res = $CON->no_result($sql);
      }
   }

   if((int)$_REQUEST["id"])
   {
      $sql = " SELECT wrk_uid, wrk_firstname, wrk_lastname
               FROM workers t1
               WHERE
               t1.id = {$_REQUEST["id"]} ";
      $worker = $CON->select($sql);
      $worker = $worker[0];

      $user_firstname   = trim(addslashes($worker["wrk_firstname"]));
      $user_lastname    = trim(addslashes($worker["wrk_lastname"]));
         
      if(!(int)$worker["wrk_uid"])
      {
         
         $sql = " insert into user
                  (user_firstname, user_lastname, user_login, user_pass, user_status)
                  VALUES
                  ('{$user_firstname}', '{$user_lastname}', 'xxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxxxx', -1)";
         $fres = $CON->no_result($sql);
         if($fres)
         {
            $wrk_uid = mysql_insert_id();
            $sql = " update workers
                     set
                     wrk_uid = {$wrk_uid}
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
         }
      }
      else
      {
         $sql = " update user
                  set
                  user_firstname = '{$user_firstname}', 
                  user_lastname  = '{$user_lastname}'
                  where
                  id = {$worker["wrk_uid"]}";
         $CON->no_result($sql);
      }
   }
   else
   {  ?>
      <script language="JavaScript">
         alert('EL RUT YA EXISTE PARA OTRO TRABAJADOR. LOS DATOS NO FUERON GUARDADOS.');
      </script>
      <?php
      $res = false;
   }

   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["id"] &&
      $_FILES["wrk_foto"]["name"] != "" &&
      $_FILES["wrk_foto"]["tmp_name"] != "" &&
      $_FILES["wrk_foto"]["error"] == 0 &&
      $_FILES["wrk_foto"]["size"] > 0)
   {
      $doc_type   = strtolower(substr($_FILES["wrk_foto"]["name"], strrpos($_FILES["wrk_foto"]["name"], ".") +1));
      $doc_hash   = md5(microtime());
      $doc_name   = "{$_REQUEST["id"]}.{$doc_hash}.{$doc_type}";
      $doc_dir    = "./images/worker_images/";
      $res        = move_uploaded_file($_FILES["wrk_foto"]["tmp_name"], "{$doc_dir}{$doc_name}");

      if($res)
      {
         resizeImage("{$doc_dir}{$doc_name}", 300, "", "{$doc_dir}{$doc_name}");

         $sql = " select *
                  from workers
                  where
                  id = {$_REQUEST["id"]}";
         $oldpic = $CON->select($sql);
         $oldpic = $oldpic[0]["wrk_foto"];

         if($oldpic != "")
            unlink("{$doc_dir}{$oldpic}");

         $sql = " update workers
                  set
                  wrk_foto = '{$doc_name}'
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar Trabajador";
}
else
{
   $title = "Cambiar Trabajador ";
   
	$sql = " SELECT t1.*,
	         t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
	         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
	         FROM workers t1
	         LEFT OUTER JOIN user t2 ON t1.wrk_updusr = t2.id
	         LEFT OUTER JOIN user t3 ON t1.wrk_crtusr = t3.id
	         WHERE
	         t1.id = {$_REQUEST["id"]} ";
	$worker = $CON->select($sql);
   $worker = $worker[0];
}
$cargos     = getCargos($CON);
?>
<style type="text/css"><!-- @import url(../libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="../libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<script language="JavaScript">
function wrkformcheck(obj)
{
   var rutchk = Rut(obj.wrk_rut, obj.wrk_rut.value);
   if(!rutchk)
      return false;

   var frmchk = checkform(new Array(obj.wrk_rut, obj.wrk_firstname, obj.wrk_lastname, obj.wrk_cargoid));

   if(!frmchk)
      return false;

   return true;
}

</script>
<form action="index.php" method="post" name="idx_workers" class="fokusfirst" enctype="multipart/form-data"
onsubmit="return wrkformcheck(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="delpic" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td width="475" valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table cellpadding="3" cellspacing="0" width="100%" border="0">
      <colgroup>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del Trabajador</td>
      </tr>
      <tr>
         <td class="content_rowl" width="100">Rut *</td>
         <td class="content_row">
            <input name="wrk_rut" type="text" class="text" style="width:120px" value="<?=$worker["wrk_rut"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nombres *</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_firstname" style="width:100%" value="<?=$worker["wrk_firstname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1);">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Apellidos *</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_lastname" style="width:100%" value="<?=$worker["wrk_lastname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cargo *</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="wrk_cargoid" id="wrk_cargoid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($cargos as $cargo)
               {  ?>
                  <option value="<?=$cargo["id"]?>"
                  <?php if($cargo["id"] == $worker["wrk_cargoid"]) echo "selected"?>><?=$cargo["type_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Clave acceso</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_axx_pass" style="width:100%" value="<?=$worker["wrk_axx_pass"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
         <td class="content_row"><?php if($worker["wrk_crtusr"] != "") echo ""."{$worker["crt_firstname"]} {$worker["crt_lastname"]}"?>&nbsp;</td> </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
         <td class="content_row"><?php if($worker["wrk_crtusr"] != "") echo displayDate($worker["wrk_crtdat"])?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
         <td class="content_row"><?php if($worker["wrk_updusr"] != "") echo "{$worker["upd_firstname"]} {$worker["upd_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
         <td class="content_row"><?php if($worker["wrk_updusr"] != "") echo displayDate($worker["wrk_upddat"])?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td width="15">&nbsp;</td>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos del Trabajador</td>
      </tr>
      <tr>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <?php
            switch((int)$worker["wrk_turno_state"])
            {
               case 0: $bgcolor = "#5CCD4A"; break;
               case 1: $bgcolor = "#5CCD4A"; break;
               case 2: $bgcolor = "#FF9292"; break;
            }
            ?>
            <select class="text" style="width:100%;color:#333333;background-color:<?=$bgcolor?>" name="wrk_turno_state" id="wrk_turno_state">
               <option value="1"  <?php if($worker["wrk_turno_state"] == 1) echo "selected"?>>Activado</option>
               <option value="2"  <?php if($worker["wrk_turno_state"] == 2) echo "selected"?>>Terminado</option>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Inicio Trabajo</td>
         <td class="content_row">
            <input name="wrk_turno_startdate" id="wrk_turno_startdate" type="text" style="width:85px"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            value="<?if((int)$worker["wrk_turno_startdate"]) echo date("d.m.Y", $worker["wrk_turno_startdate"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" readonly>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_street" style="width:100%" value="<?=$worker["wrk_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_email" style="width:100%" value="<?=$worker["wrk_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Telefono</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_telefono1" style="width:140px" value="<?=$worker["wrk_telefono1"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">$ Valor HH</td>
         <td class="content_row">
            <input type="text" class="text" name="wrk_costo_hh" style="width:140px"
            value="<?=printPrice($worker["wrk_costo_hh"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> por hora
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="56">Foto</td>
         <td class="content_row">
            <?php
            if($worker["wrk_foto"] != "")
            {  ?>
               <table border="0" cellspacing="0" cellpadding="0" width="185">
               <tr>
                  <td style="padding-right:5px" width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][5], "postnav", "javascript: deactivateFormChange()", "showFancyboxAuto('/images/worker_images/{$worker["wrk_foto"]}', 'image')", "navigation-270-white");
                     ?>
                  </td>
                  <td width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) document.idx_workers.delpic.value='1';submitForm(document.idx_workers) ", "cross-circle-frame");
                     ?>
                  </td>
               </tr>
               </table>
               <?php
            }
            else
            {  ?>
               <input class="text" type="file" name="wrk_foto" maxlength="255" style="width:100%"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
</table>
<br>
<script language="JavaScript">
function toggleZonas(zidx)
{
   $('#zonadummy_' +zidx).fadeToggle(300);
   $('.clszona_' +zidx).fadeToggle(300);
}
</script>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}&tipo_persona={$_REQUEST["tipo_persona"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
       printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_workers)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
$_SESSION["JSEXEC"] .= "addFormListeners('idx_workers');";
?>