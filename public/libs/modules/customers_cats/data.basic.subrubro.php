<?php

echo("subexec ".$_REQUEST["subexec"]);

$sql = "select * from customer_cats where id = {$_REQUEST["sub_id_cat"]}";
$rubro = $CON->select($sql);
$rubro = $rubro[0];
if($_REQUEST["subexec"] == "save") {
   $currtme = time();
   $_REQUEST["sub_cat_name"] = trim(addslashes($_REQUEST["sub_cat_name"]));

   if($_REQUEST["id"] == "") {
      $sql = "INSERT INTO customer_sub_cats
              (sub_id_cat, sub_cat_name, sub_cat_crtusr, sub_cat_crtdat)
              VALUES
              ({$_REQUEST["sub_id_cat"]},'{$_REQUEST["sub_cat_name"]}', {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);
   } else {
      $sql = "UPDATE customer_sub_cats
              SET sub_cat_name   = '{$_REQUEST["sub_cat_name"]}',
                  sub_cat_updusr = {$_SESSION["user_id"]},
                  sub_cat_upddat = {$currtme}
              WHERE id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}
if($_REQUEST["subexec"] == "delete") 
{
   $currtme = time();
   $sql = "UPDATE customer_sub_cats
              SET sub_cat_status = 0,
                  sub_cat_updusr = {$_SESSION["user_id"]},
                  sub_cat_upddat = {$currtme}
              WHERE id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
}

$title = "Sub-rubro";
$sql = "SELECT t1.*, 
                  t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
           FROM customer_sub_cats t1
           LEFT OUTER JOIN user t2 ON t1.sub_cat_updusr = t2.id
           LEFT OUTER JOIN user t3 ON t1.sub_cat_crtusr = t3.id
           WHERE t1.sub_id_cat = {$_REQUEST["sub_id_cat"]} and sub_cat_status > 0";
$sub_rubro = $CON->select($sql);

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

<form action="index.php" method="post" name="idx_giro" class="fokusfirst"  onsubmit="return checkform(new Array(this.sub_cat_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="">
<input type="hidden" name="sub_id_cat" value="<?=$_REQUEST["sub_id_cat"]?>">

<?=Nifty_printH("box1", "800",0)?>
<table cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Datos de categoria</td>
   </tr>
   <tr>
      <td class="content_rowl">Rubro :</td>
      <td class="content_row">
         <input type="text" class="text" name="_name" 
                value="<?=$rubro["id"]?>" readonly>
      </td>
      <td>
         <input type="text" class="text" name="cat_name" style="width:500px" 
                value="<?=$rubro["cat_name"]?>" readonly>
      </td>
   </tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box1", "800",0)?>
<table cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
   </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Datos de Sub Rubro</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre Sub Rubro *</td>
         <td class="content_row">
            <input type="text" class="text" name="sub_cat_name" style="width:510px" 
                  value="<?=$sub_rubro[$x]["sub_cat_name"]?>">
         </td>
         <td class="content_row" align="center">
            <button type="button" 
                  onclick="SaveSubRubro(document.idx_giro.id.value, document.idx_giro.sub_cat_name.value)">
               <img src="/images/menu/icons/disk-black.png" alt="Guardar">&nbsp;Guardar
            </button>
         </td>      
         <script>
            function SaveSubRubro(id, name) 
            {
               document.idx_giro.id.value = id;
               document.idx_giro.sub_cat_name.value = name;
               document.idx_giro.subexec.value = "save";
               submitForm(document.idx_giro);
            }
         </script>
      </tr>
</table>

<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_subheader">id</td>
      <td class="content_tbl_subheader">Sub rubro</td>
      <td class="content_tbl_subheader" align="center" colspan="2">Opción</td>
   </tr>
         <?php
         for($x = 0; $x < count($sub_rubro) && $sub_rubro != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$sub_rubro[$x]["id"]?></td>
               <td class="content_row" style="width:600px" ><?=$sub_rubro[$x]["sub_cat_name"]?></td>
               <td class="content_row" align="center">
                  <button type="button" 
                        onclick="editSubRubro('<?=$sub_rubro[$x]["id"]?>','<?=htmlspecialchars($sub_rubro[$x]["sub_cat_name"], ENT_QUOTES)?>')">
                     <img src="/images/menu/icons/pencil.png" alt="Editar">&nbsp;Editar
                  </button>
               </td>
               <td class="content_row" align="center">
                  <button type="button" 
                        onclick="DelSubRubro('<?=$sub_rubro[$x]["id"]?>','<?=htmlspecialchars($sub_rubro[$x]["sub_cat_name"], ENT_QUOTES)?>');submitForm(document.idx_giro)" >
                     <img src="/images/menu/icons/cross-circle-frame.png" alt="Borrar">&nbsp;Borrar
                  </button>
               </td>
            </tr>
            <script>
            function editSubRubro(id, name) 
            {
               document.idx_giro.id.value = id;
               document.idx_giro.sub_cat_name.value = name;
               document.idx_giro.subexec.value = "edit";
            }
            function DelSubRubro(id, name) 
            {
               document.idx_giro.id.value = id;
               document.idx_giro.sub_cat_name.value = name;
               document.idx_giro.subexec.value = "delete";
               submitForm(document.idx_giro);
            }
            </script>
            <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="3">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }   
         ?>
</table>
<?=Nifty_printF(false)?>

<br>

<?=Nifty_printH("boxopt_b", "650", 0)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <?php printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180"); ?>
   </td>
   <td>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>

</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');"; ?>
