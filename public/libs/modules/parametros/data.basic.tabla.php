<?php
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["codigo"]        = strtoupper(trim(addslashes($_REQUEST["codigo"])));
   $_REQUEST["descripcion"]   = strtoupper(trim(addslashes($_REQUEST["descripcion"])));

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"]==0)
   {
      $newDate = date("Ymd", $currtme);

      $sql = " select count(*) as contador from parametros where tabla = 'TABLA' and codigo = '{$_REQUEST["codigo"]}' ";
      $val = $CON->select($sql);
      if($val[0]["contador"] > 0)
      {
         $savemsg = "<b class='msg_save_err'>Codigo de Tabla ya existe</b>";
      }
      else
      {
         $sql = " insert into parametros
                  ( tabla
                  , codigo
                  , descripcion
                  , fecha
                  , valor1
                  , valor2
                  , valor3)
                  VALUES
                  ('TABLA'
                  ,'{$_REQUEST["codigo"]}'
                  ,'{$_REQUEST["descripcion"]}'
                  ,'{$newDate}'
                  ,0
                  ,0
                  ,0)";
         $res = $CON->no_result($sql);
         if($res)
         {
            $sql = " select MAX(id) 'id'
                     from parametros ";
            $thisid = $CON->select($sql);
            $thisid = $thisid[0]["id"];
            $_REQUEST["id"] = $thisid;
         }
         $savemsg = getSaveMessage($res);
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $newDate = date("Ymd", $currtme);
      $sql = " update parametros
               set
               tabla         = 'TABLA',
               codigo        = '{$_REQUEST["codigo"]}',
               descripcion   = '{$_REQUEST["descripcion"]}',
               fecha         = '{$newDate}'
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["id"])
{
   $title = "Modificar Tablas";
   
   $sql = " select * from parametros
            where
            id = {$_REQUEST["id"]} ";
   $parametros = $CON->select($sql);
   $parametros = $parametros[0];
   $parametros["fecha"] = strtotime($parametros["fecha"]);
}
else
{
   $title = "Agregar Tablas";
   $parametros["fecha"]  = strtotime("now");
   $_REQUEST["tabla"]  = "TABLA";
}

$rdlo       = " readonly ";
$dabl       = " disabled ";
$rowcount   = count($posdata);
?>

<script language="JavaScript">
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
<form action="index.php" method="post" class="fokusfirst" name="xform_cust">
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
            <td class="content_tbl_header" colspan="2">Datos de Tabla</td>
         </tr>
         <tr>
            <td class="content_rowl">Tabla</td>
            <td class="content_row">
               <input name="codigo" type="text" class="text" style="width:280px" value="<?=$parametros["codigo"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" <?if((int)$_REQUEST["id"]) echo $rdlo?> >
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Descripcion</td>
            <td class="content_row" width="130">
            <input name="descripcion" type="text" class="text" style="width:280px" value="<?=$parametros["descripcion"]?>"
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
      <?php
         if($_REQUEST["id"]>0)
         {
            ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?}?>
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