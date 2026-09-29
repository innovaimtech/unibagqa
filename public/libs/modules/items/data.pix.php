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
            from item
            where
            id = {$_REQUEST["id"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["item_img"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["item_img"]}");
      unlink("{$doc_dir}s{$orgimg[0]["item_img"]}");
   }

   $sql = " update item
            set item_img = NULL
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["subexec"] == "save")
{  
   $thisid = (int)$_REQUEST["id"];
   
   if((int)$thisid &&
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
               from item
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

         $sql = " update item
                  set item_img = '{$doc_name}'
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

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from item t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from website_subsubcats
         where
         cat_id    = {$item["item_catid"]} and
         subcat_id = {$item["item_subcatid"]} and
         website_subsubcat_status > 0
         order by website_subsubcat_title";
$subsubcats = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select distinct t1.id, t1.website_title
         from website_productcats t1
         where 
         t1.website_status = 1
         order by t1.website_title";
$wdata = $CON->select($sql);

$wcats = array();
$x = 0;
foreach($wdata AS $wcat)
{
   $sql = " select t2.*
            from website_productcats_subcategories t1
            LEFT OUTER JOIN website_subcategories t2 ON t1.id_subcat = t2.id
            where
            t2.website_subcat_status = 1 and
            t1.id_prod               = {$wcat["id"]}
            order by t2.website_subcat_title";
   $wsubcats = $CON->select($sql);

   foreach($wsubcats AS $wsubcat)
   {
      $wcats[$x]["title"] = $wcat["website_title"]." > ".$wsubcat["website_subcat_title"];
      $wcats[$x]["id"]    = $wcat["id"]."#".$wsubcat["id"];
      $x++;
   }
}

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function setSubSubCats(catid)
   {
      var dataString = "catid="+catid;
      $.ajax({
         type:       "POST",
         cache:      false,
         url:        "/libs/modules/items/jquery.subsubcats.php",
         data:       dataString,
         dataType:   "html",
         success: function(res)
         {
            
            $("#item_subsubcatid").html(res);
         }
      });
   }
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
   <td class="content_tbl_header" colspan="2">Imagen</td>
</tr>
<?php
// $doc_path = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/items/";
$doc_path = "https://www.erp-unibag.cl/images/items/";


if($item["item_img"] != "")
{
   $doc_img       = "{$doc_path}{$item["item_img"]}";
   $doc_img_org   = "{$doc_path}{$item["item_img"]}";
}
else
{
   $doc_img       = "{$doc_path}item_nopic.gif";
   $doc_img_org   = "";
}

?>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row">Imagen</td>
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
            if($item["item_img"] != "")
            {
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=delImage&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}')", "cross-circle-frame", 90); 
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