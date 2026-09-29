<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$imagetypes["company_img"]       = "Informes";
$imagetypes["company_img_buy"]   = "Orden de compra";
$imagetypes["company_img_sell"]  = "Confirmación de compra";
$imagetypes["company_bandera"]   = "Bandera";

if($_REQUEST["subexec"] == "delImage")
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/";

   $sql = " select {$_REQUEST["imgtype"]} 'company_img'
            from company_data
            where
            id = {$_REQUEST["id"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["company_img"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["company_img"]}");
      unlink("{$doc_dir}s{$orgimg[0]["company_img"]}");
   }

   $sql = " update company_data
            set {$_REQUEST["imgtype"]} = NULL
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["subexec"] == "save")
{
   $thisid = (int)$_REQUEST["id"];

   foreach(array_keys($imagetypes) AS $imidx)
   {
   
      if((int)$thisid &&
         $_FILES[$imidx]["name"] != "" &&
         $_FILES[$imidx]["tmp_name"] != "" &&
         $_FILES[$imidx]["error"] == 0 &&
         $_FILES[$imidx]["size"] > 0)
      {
         $doc_type = substr($_FILES[$imidx]["name"], strrpos($_FILES[$imidx]["name"], ".") +1);
         $doc_hash = md5(microtime());
         $doc_name = "{$thisid}_{$doc_hash}.{$doc_type}";
         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/";

         $sql = " select {$imidx} 'company_img'
                  from company_data
                  where
                  id = {$thisid}";
         $orgimg = $CON->select($sql);

         if($orgimg[0]["company_img"] != "")
         {
            unlink("{$doc_dir}{$orgimg[0]["company_img"]}");
            unlink("{$doc_dir}s{$orgimg[0]["company_img"]}");
         }

         $res = move_uploaded_file($_FILES[$imidx]["tmp_name"], "{$doc_dir}{$doc_name}");

         if($res)
         {
            resizeImage("{$doc_dir}{$doc_name}", 900, "", "{$doc_dir}{$doc_name}");
            resizeImage("{$doc_dir}{$doc_name}", 900, "", "{$doc_dir}s{$doc_name}");

            $sql = " update company_data
                     set {$imidx} = '{$doc_name}'
                     where
                     id = {$thisid}";
            $res = $CON->no_result($sql);

            $savemsg = getSaveMessage($res);
         }
         else
            $savemsg = getSaveMessage(false);
      }

      if($savemsg == "")
         $savemsg = getSaveMessage(true);
   }
}
$sql = " select t1.*
         from company_data t1
         where
         t1.id = {$_REQUEST["id"]} ";
$company = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<script type="text/javascript">
   var GB_ROOT_DIR = "./libs/jscripts/GreyBox_v5_53/greybox/";
</script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/AJS.js"></script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/AJS_fx.js"></script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/gb_scripts.js"></script>
<link href="./libs/jscripts/GreyBox_v5_53/greybox/gb_styles.css" rel="stylesheet" type="text/css" />
<form action="index.php" method="post" class="fokusfirst" enctype="multipart/form-data" name="xform_pix">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
   <col width="260">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Imagenes de empresa</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Tipo</td>
   <td class="content_tbl_subheader">Imagen</td>
   <td class="content_tbl_subheader">Opciones</td>
</tr>
<?php
$doc_path = "/images/companies/";

foreach(array_keys($imagetypes) AS $imidx)
{
   if($company[0][$imidx] != "")
   {
      $doc_img       = "{$doc_path}s{$company[0][$imidx]}";
      $doc_img_org   = "{$doc_path}{$company[0][$imidx]}";
   }
   else
      $doc_img_org   = "";
   ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row"><?=$imagetypes[$imidx]?></td>
      <td class="content_row">
         <?php
         if($doc_img_org != "")
         {  ?>
            <a href="<?=$doc_img_org?>" rel="gb_imageset[gallery]"><img
            border="0" width="150" src="<?=$doc_img?>"></a>
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <?php
         if($company[0][$imidx] != "")
         {
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=delImage&imgtype={$imidx}&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}')", "cross-circle-frame", 160);
         }
         else
         {  ?>
            <input class="text" type="file" name="<?=$imidx?>" maxlength="100" style="width:120px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <?php
         }
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pix)", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_pix');" ?>