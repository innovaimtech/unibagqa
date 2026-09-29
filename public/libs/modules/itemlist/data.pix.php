<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "delImage")
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/items/";

   $sql = " select item_img
            from itemlist
            where
            id = {$_REQUEST["id"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["item_img"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["item_img"]}");
      unlink("{$doc_dir}s{$orgimg[0]["item_img"]}");
   }

   $sql = " update itemlist
            set item_img = NULL
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["subexec"] == "delGalleryImage")
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/items/";

   $sql = " select item_img_gal{$_REQUEST["idx"]} 'item_img_gal'
            from itemlist
            where
            id = {$_REQUEST["id"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["item_img_gal"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["item_img_gal"]}");
      unlink("{$doc_dir}s{$orgimg[0]["item_img_gal"]}");
   }

   $sql = " update itemlist
            set item_img_gal{$_REQUEST["idx"]} = NULL
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["subexec"] == "save")
{
   $thisid = (int)$_REQUEST["id"];
   
   if(   (int)$thisid &&
         $_FILES["item_img"]["name"] != "" &&
         $_FILES["item_img"]["tmp_name"] != "" &&
         $_FILES["item_img"]["error"] == 0 &&
         $_FILES["item_img"]["size"] > 0)
   {
      $doc_type = substr($_FILES["item_img"]["name"], strrpos($_FILES["item_img"]["name"], ".") +1);
      $doc_hash = md5(microtime());
      $doc_name = "{$thisid}_{$doc_hash}.{$doc_type}";
      $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/items/";

      $sql = " select item_img
               from itemlist
               where
               id = {$thisid}";
      $orgimg = $CON->select($sql);

      if($orgimg[0]["item_img"] != "")
      {
         unlink("{$doc_dir}{$orgimg[0]["item_img"]}");
         unlink("{$doc_dir}s{$orgimg[0]["item_img"]}");
      }

      $res = move_uploaded_file($_FILES["item_img"]["tmp_name"], "{$doc_dir}{$doc_name}");

      if($res)
      {
         resizeImage("{$doc_dir}{$doc_name}", 800, "", "{$doc_dir}{$doc_name}");
         resizeImage("{$doc_dir}{$doc_name}", 100, "", "{$doc_dir}s{$doc_name}");

         $sql = " update itemlist
                  set item_img = '{$doc_name}'
                  where
                  id = {$thisid}";
         $res = $CON->no_result($sql);

         $savemsg = getSaveMessage($res);
      }
      else
         $savemsg = getSaveMessage(false);
   }

   if((int)$thisid)
   {
      $fkeys = array_keys($_FILES);
      for($x = 0; $x < count($fkeys); $x++)
      {
         if(strpos($fkeys[$x], "item_img_gal_") !== false &&
            $_FILES[$fkeys[$x]]["name"] != "" &&
            $_FILES[$fkeys[$x]]["tmp_name"] != "" &&
            $_FILES[$fkeys[$x]]["error"] == 0 &&
            $_FILES[$fkeys[$x]]["size"] > 0)
         {
            $idx        = substr($fkeys[$x], strrpos($fkeys[$x], "_") +1);
            $doc_type   = substr($_FILES[$fkeys[$x]]["name"], strrpos($_FILES[$fkeys[$x]]["name"], ".") +1);
            $doc_hash   = md5(microtime());
            $doc_name   = "gal_{$idx}_{$thisid}_{$doc_hash}.{$doc_type}";
            $doc_dir    = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/items/";

            $sql = " select item_img_gal{$idx} 'item_img_gal'
                     from itemlist
                     where
                     id = {$thisid}";
            $orgimg = $CON->select($sql);

            if($orgimg[0]["item_img_gal"] != "")
            {
               unlink("{$doc_dir}{$orgimg[0]["item_img_gal"]}");
               unlink("{$doc_dir}s{$orgimg[0]["item_img_gal"]}");
            }

            $res = move_uploaded_file($_FILES[$fkeys[$x]]["tmp_name"], "{$doc_dir}{$doc_name}");
            
            if($res)
            {
               resizeImage("{$doc_dir}{$doc_name}", 800, "", "{$doc_dir}{$doc_name}");
               resizeImage("{$doc_dir}{$doc_name}", 100, "", "{$doc_dir}s{$doc_name}");

               $sql = " update itemlist
                        set item_img_gal{$idx} = '{$doc_name}'
                        where
                        id = {$thisid}";
               $res = $CON->no_result($sql);
               
               $savemsg = getSaveMessage($res);
            }
            else
               $savemsg = getSaveMessage(false);
         }
      }
   }

   if($savemsg == "")
      $savemsg = getSaveMessage(true);
}
$sql = " select t1.*
         from itemlist t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
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
   <col width="110">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Imagenes de set</td>
</tr>
<?php
$doc_path = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/items/";

if($item[0]["item_img"] != "")
{
   $doc_img       = "{$doc_path}s{$item[0]["item_img"]}";
   $doc_img_org   = "{$doc_path}{$item[0]["item_img"]}";
}
else
{
   $doc_img       = "{$doc_path}item_nopic.gif";
   $doc_img_org   = "";
}
?>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row">Imagen 1</td>
   <td class="content_row" valign="center">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="150">
            <?php
            if($doc_img_org != "")
            {  ?>
               <a href="<?=$doc_img_org?>" rel="gb_imageset[gallery]"><img
               border="0" width="100" src="<?=$doc_img?>"></a>
               <?php
            }
            else
            {  ?>
               <img border="0" width="100" src="<?=$doc_img?>">
               <?php
            }
            ?>
         </td>
         <td class="content_row_clear">
            <?php
            if($item[0]["item_img"] != "")
            {  ?>
               <i><b>Informacion:</b> Pulsa sobre la imagen para ampliarla.</i>
               <br><br>
               <table border="0" cellspacing="0" cellpadding="0" width="90">
               <tr>
                  <td width="90">
                     <ul class="postnav_del">
                        <a href="javascript: deactivateFormChange()"
                        onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=delImage&subcatexec=<?=$_REQUEST["subcatexec"]?>&id=<?=$_REQUEST["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
                     </ul>
                  </td>
               </tr>
               </table>
               <?php
            }
            else
            {  ?>
               <input class="text" type="file" name="item_img"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
<?php
for($x = 1; $x <= 4; $x++)
{
   if($item[0]["item_img_gal{$x}"] != "")
   {
      $doc_img       = "{$doc_path}s{$item[0]["item_img_gal{$x}"]}";
      $doc_img_org   = "{$doc_path}{$item[0]["item_img_gal{$x}"]}";
   }
   else
   {
      $doc_img       = "{$doc_path}item_nopic.gif";
      $doc_img_org   = "";
   }
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">Imagen <?=($x +1)?></td>
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="150">
               <?php
               if($doc_img_org != "")
               {  ?>
                  <a href="<?=$doc_img_org?>" rel="gb_imageset[gallery]"><img
                  border="0" width="100" src="<?=$doc_img?>"></a>
                  <?php
               }
               else
               {  ?>
                  <img border="0" width="100" src="<?=$doc_img?>">
                  <?php
               }
               ?>
            </td>
            <td class="content_row_clear">
               <?php
               if($item[0]["item_img_gal{$x}"] != "")
               {  ?>
                  <i><b>Informacion:</b> Pulsa sobre la imagen para ampliarla.</i>
                  <br><br>
                  <table border="0" cellspacing="0" cellpadding="0" width="90">
                  <tr>
                     <td width="90">
                        <ul class="postnav_del">
                           <a href="javascript: deactivateFormChange()"
                           onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=delGalleryImage&subcatexec=<?=$_REQUEST["subcatexec"]?>&id=<?=$_REQUEST["id"]?>&idx=<?=$x?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
                        </ul>
                     </td>
                  </tr>
                  </table>
                  <?php
               }
               else
               {  ?>
                  <input class="text" type="file" name="item_img_gal_<?=$x?>">
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
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
   <td width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>&subcatexec=shops"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: deactivateFormChange()"
         onclick="submitForm(document.xform_pix)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_pix');" ?>