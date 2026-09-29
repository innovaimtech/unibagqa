<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["tabla"]         = trim(addslashes($_REQUEST["tabla"]));
   $_REQUEST["codigo"]        = trim(addslashes($_REQUEST["codigo"]));
   $_REQUEST["descripcion"]   = trim(addslashes($_REQUEST["descripcion"]));
   $_REQUEST["fecha"]         = strtotime($_REQUEST["fecha"]);
   $_REQUEST["valor1"]        = (int)$_REQUEST["valor1"];
   $_REQUEST["valor2"]        = (int)$_REQUEST["valor2"];
   $_REQUEST["valor3"]        = (int)$_REQUEST["valor3"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " select count(*) as contador from parametros where tabla = '{$_REQUEST["tabla"]}' and codigo = '{$_REQUEST["codigo"]}' ";
      $val = $CON->select($sql);
      if( $val[0]["contador"] > 0)
      {
         $savemsg = "<b class='msg_save_err'>Parametro ya existe</b>";
      }
      else
      {
         $newDate = date("Ymd", $_REQUEST["fecha"]);
         $sql = " insert into parametros
                  (tabla, codigo, descripcion, fecha,
                  valor1, valor2, valor3)
                  VALUES
                  ('{$_REQUEST["tabla"]}',
                  '{$_REQUEST["codigo"]}', '{$_REQUEST["descripcion"]}',
                  '{$newDate}',{$_REQUEST["valor1"]},
                  {$_REQUEST["valor2"]}, {$_REQUEST["valor3"]})";
         $res = $CON->no_result($sql);
         $savemsg = getSaveMessage($res);
         if($res)
         {
            $sql = " select MAX(id) 'thisid'
                     from parametros ";
            $thisid = $CON->select($sql);
            $_REQUEST["id"] = $thisid[0]["thisid"];
         }
      }
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $newDate = date("Ymd", $_REQUEST["fecha"]);
      $sql = " update parametros
               set
               tabla         = '{$_REQUEST["tabla"]}',
               codigo        = '{$_REQUEST["codigo"]}',
               descripcion   = '{$_REQUEST["descripcion"]}',
               fecha         = '{$newDate}',
               valor1        = {$_REQUEST["valor1"]},
               valor2        = {$_REQUEST["valor2"]},
               valor3        = {$_REQUEST["valor3"]}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $title = "Modificar Parametros";
   
   $sql = " select * from parametros
            where
            id = {$_REQUEST["id"]} ";
   $parametros = $CON->select($sql);
   $parametros = $parametros[0];
   $parametros["fecha"] = strtotime($parametros["fecha"]);
}
else
{
   $title = "Agregar Parametros";
   $parametros["fecha"]  = strtotime("now");
}

$rdlo       = " readonly ";
$dabl       = " disabled ";
$rowcount   = count($posdata);

//----------------------------------------------------------------------------------
// Carga tablas registradas
   
   $sql = " select id
   ,codigo as tabla
   ,Descripcion
   from parametros
   WHERE TABLA = 'TABLA'
   order by tabla";
$tablas = $CON->select($sql);

//----------------------------------------------------------------------------------


?>

<script language="JavaScript">

   function custformcheck(obj)
   {
      var frmchk = checkform(new Array(obj.tabla, obj.codigo, obj.descripcion, obj.fecha));
         
      if(!frmchk)
        return false;

      return true;
  }

</script>

<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
   printJSsetCompanyShop($shops);
?>

<?php
//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<style>.xselb:hover { text-decoration:underline; }</style>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust" onsubmit="return custformcheck(this)">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="req_status" value="1">
<input type="hidden" name="autoprintmode" value="">
<input type="hidden" name="previewprintmode" value="">
<input type="hidden" name="setPosOrder" value="">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricesell_perm" value="<?=$_REQUEST["user_pricesell_perm"]?>">
<input type="hidden" name="delposimg" value="">
<input type="hidden" name="delposmultiitem" value="">
<input type="hidden" name="openfancymode" value="">


<table cellpadding="0" cellspacing="0" width="650" style="table-layout:fixed">
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de Parametros</td>
      </tr>
      <tr>
         <td class="content_rowl">Tabla</td>
         <td class="content_row">
            <select class="text" style="width:280px" name="tabla" id="tabla" 
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                     foreach($tablas as $tabla)
                     {
                        ?>
                           <option value="<?=$tabla["tabla"]?>"
                           <?php if($tabla["tabla"] == $parametros["tabla"]) echo "selected"?>><?=$tabla["Descripcion"]?></option>
                        <?php
                     }
                  ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Codigo</td>
         <td class="content_row">
            <input name="codigo" type="text" class="text" style="width:280px" value="<?=$parametros["codigo"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Descripcion</td>
         <td class="content_row" width="130">
           <input name="descripcion" type="text" class="text" style="width:280px" value="<?=$parametros["descripcion"]?>"
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fecha</td>
         <td class="content_row">
           <input type="text" style="width:70px" id="fecha" name="fecha"
              class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
              onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date("d.m.Y",$parametros["fecha"])?>">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Valor 1</td>
         <td class="content_row">
           <input name="valor1" type="text" class="text" style="width:280px" value="<?=$parametros["valor1"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Valor 2</td>
         <td class="content_row">
            <input name="valor2" type="text" class="text" style="width:280px" value="<?=$parametros["valor2"]?>"
             onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Valor 3</td>
         <td class="content_row">
           <input name="valor3" type="text" class="text" style="width:280px" value="<?=$parametros["valor3"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "650")?>


<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>