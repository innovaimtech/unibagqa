<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Copyright:     2020 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "delImage")
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/";

   $sql = " select conf_mail_image
            from config_system";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["conf_mail_image"] != "")
      unlink("{$doc_dir}{$orgimg[0]["conf_mail_image"]}");

   $sql = " update config_system
            set
            conf_mail_image = ''";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   $_REQUEST["conf_title"]                   = trim(addslashes($_REQUEST["conf_title"]));
   $_REQUEST["conf_mailserver"]              = trim(addslashes($_REQUEST["conf_mailserver"]));
   $_REQUEST["conf_mail_accountname"]        = trim(addslashes($_REQUEST["conf_mail_accountname"]));
   $_REQUEST["conf_mail_password"]           = trim(addslashes($_REQUEST["conf_mail_password"]));
   $_REQUEST["conf_mail_sendername"]         = trim(addslashes($_REQUEST["conf_mail_sendername"]));
   $_REQUEST["conf_mail_replyto"]            = trim(addslashes($_REQUEST["conf_mail_replyto"]));
   $_REQUEST["conf_shopadmin_path"]          = trim(addslashes($_REQUEST["conf_shopadmin_path"]));
   $_REQUEST["conf_shopadmin_url"]           = trim(addslashes($_REQUEST["conf_shopadmin_url"]));
   $_REQUEST["conf_lang"]                    = (int)$_REQUEST["conf_lang"];
   $_REQUEST["conf_mailsystem"]              = (int)$_REQUEST["conf_mailsystem"];
   $_REQUEST["conf_number_decimal_places"]   = (int)$_REQUEST["conf_number_decimal_places"];
   $_REQUEST["conf_taxes"]                   = (float)sprintf("%.2f", (float)str_replace(",", ".", str_replace(".", "", $_REQUEST["conf_taxes"])));
   $_REQUEST["conf_price_per_kilo"]          = (float)sprintf("%.6f", (float)str_replace(",", ".", str_replace(".", "", $_REQUEST["conf_price_per_kilo"])));
   $_REQUEST["conf_ocamt_limit"]             = (float)getPrice($_REQUEST["conf_ocamt_limit"]);

   $sql = " update config_system
            set
            conf_title                    = '{$_REQUEST["conf_title"]}',
            conf_lang                     =  {$_REQUEST["conf_lang"]},
            conf_shopadmin_path           = '{$_REQUEST["conf_shopadmin_path"]}',
            conf_shopadmin_url            = '{$_REQUEST["conf_shopadmin_url"]}',
            conf_taxes                    =  {$_REQUEST["conf_taxes"]},
            conf_currency                 = '{$_REQUEST["conf_currency"]}',
            conf_number_thousand_point    = '{$_REQUEST["conf_number_thousand_point"]}',
            conf_number_decimal_places    =  {$_REQUEST["conf_number_decimal_places"]},
            conf_number_decimal_point     = '{$_REQUEST["conf_number_decimal_point"]}',
            conf_price_per_kilo           =  {$_REQUEST["conf_price_per_kilo"]},
            conf_mailsystem               =  {$_REQUEST["conf_mailsystem"]},
            conf_mailserver               = '{$_REQUEST["conf_mailserver"]}',
            conf_mail_accountname         = '{$_REQUEST["conf_mail_accountname"]}',
            conf_mail_password            = '{$_REQUEST["conf_mail_password"]}',
            conf_mail_sendername          = '{$_REQUEST["conf_mail_sendername"]}',
            conf_mail_replyto             = '{$_REQUEST["conf_mail_replyto"]}',
            conf_ocamt_limit              =  {$_REQUEST["conf_ocamt_limit"]}";
   $res = $CON->no_result($sql);

   if($_FILES["conf_mail_image"]["name"] != "" &&
      $_FILES["conf_mail_image"]["tmp_name"] != "" &&
      $_FILES["conf_mail_image"]["error"] == 0 &&
      $_FILES["conf_mail_image"]["size"] > 0)
   {
      $doc_type = substr($_FILES["conf_mail_image"]["name"], strrpos($_FILES["conf_mail_image"]["name"], ".") +1);
      $doc_hash = md5(microtime());
      $doc_name = "comp_{$doc_hash}.{$doc_type}";
      $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/";

      $sql = " select conf_mail_image
               from config_system";
      $orgimg = $CON->select($sql);

      if($orgimg[0]["conf_mail_image"] != "")
         unlink("{$doc_dir}{$orgimg[0]["conf_mail_image"]}");

      $xres = move_uploaded_file($_FILES["conf_mail_image"]["tmp_name"], "{$doc_dir}{$doc_name}");

      if($xres)
      {
         $sql = " update config_system
                  set conf_mail_image = '{$doc_name}'";
         $res = $CON->no_result($sql);
      }
   }

   if($res)
   {  ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&saveok=1';
      </script>
      <?php
   }
   else
      $savemsg = getSaveMessage(false);
}

if($_REQUEST["saveok"] == "1")
   $savemsg = getSaveMessage(true);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);

$sql = " select *
         from config_lang
         order by lang_name";
$lang = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<script type="text/javascript">
   var GB_ROOT_DIR = "./libs/jscripts/GreyBox_v5_53/greybox/";
</script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/AJS.js"></script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/AJS_fx.js"></script>
<script type="text/javascript" src="./libs/jscripts/GreyBox_v5_53/greybox/gb_scripts.js"></script>
<link href="./libs/jscripts/GreyBox_v5_53/greybox/gb_styles.css" rel="stylesheet" type="text/css" />
<table border="0" cellpadding="0" cellspacing="0" width="650">
<form action="index.php" method="post" enctype="multipart/form-data" name="xform_pconfig"
onsubmit="return checkform(new Array(this.conf_title, this.conf_lang))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="save">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CONF"][9]?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CONF"][0]?></td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][2]?></td>
   <td class="content_row">
      <select name="conf_lang" class="text" style="width:100px">
         <?php
         for($x = 0; $x < count($lang); $x++)
         {  ?>
            <option value="<?=$lang[$x]["id"]?>"
            <?php if($lang[$x]["id"] == $conf[0]["conf_lang"]) echo "selected"?>><?=$lang[$x]["lang_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][1]?></td>
   <td class="content_row">
      <input name="conf_title" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<?php
$doc_path = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/companies/";

if($conf[0]["conf_mail_image"] != "")
{
   $doc_img       = "{$doc_path}{$conf[0]["conf_mail_image"]}";
   $doc_img_org   = "{$doc_path}{$conf[0]["conf_mail_image"]}";
}
else
{
   $doc_img       = "{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/items/item_nopic.gif";
   $doc_img_org   = "";
}
?>
<tr>
   <td class="content_rowl" valign="top">Imagen email</td>
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
            if($conf[0]["conf_mail_image"] != "")
            {
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=delImage&subcatexec={$_REQUEST["subcatexec"]}')", "cross-circle-frame", 90);
            }
            else
            {  ?>
               <input class="text" type="file" name="conf_mail_image"
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
<?=Nifty_printH("box2", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CONF"][15]?></td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][19]?></td>
   <td class="content_row">
      <input name="conf_shopadmin_path" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_shopadmin_path"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][20]?></td>
   <td class="content_row">
      <input name="conf_shopadmin_url" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_shopadmin_url"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][16]?></td>
   <td class="content_row">
      <input name="conf_taxes" type="text" class="text" style="width:80px" value="<?=printPrice($conf[0]["conf_taxes"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][21]?></td>
   <td class="content_row">
      <input name="conf_currency" type="text" class="text" style="width:80px" value="<?=$conf[0]["conf_currency"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][22]?>: <?=$_LANG["MODULE"]["CONF"][23]?></td>
   <td class="content_row">
      <input name="conf_number_thousand_point" type="text" class="text" style="width:80px" value="<?=$conf[0]["conf_number_thousand_point"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][22]?>: <?=$_LANG["MODULE"]["CONF"][24]?></td>
   <td class="content_row">
      <input name="conf_number_decimal_places" type="text" class="text" style="width:80px" value="<?=$conf[0]["conf_number_decimal_places"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][22]?>: <?=$_LANG["MODULE"]["CONF"][25]?></td>
   <td class="content_row">
      <input name="conf_number_decimal_point" type="text" class="text" style="width:80px" value="<?=$conf[0]["conf_number_decimal_point"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Limite aprobación OC</td>
   <td class="content_row">
      <input name="conf_ocamt_limit" type="text" class="text" style="width:80px" value="<?=printPrice($conf[0]["conf_ocamt_limit"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> <?=$conf[0]["conf_currency"]?>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Conducción: Precio por Kg</td>
   <td class="content_row">
      <input name="conf_price_per_kilo" type="text" class="text" style="width:80px" value="<?=printPrice($conf[0]["conf_price_per_kilo"],6)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> <?=$conf[0]["conf_currency"]?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CONF"][3]?></td>
</tr>
<tr>
   <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["CONF"][14]?></td>
   <td class="content_row">
      <input type="radio" name="conf_mailsystem" value="1"
      <?php if((int)$conf[0]["conf_mailsystem"] == 1) echo "checked"?>> Sendmail
      <br>
      <input type="radio" name="conf_mailsystem" value="2"
      <?php if((int)$conf[0]["conf_mailsystem"] == 2) echo "checked"?>> Googlemail
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][4]?></td>
   <td class="content_row">
      <input name="conf_mailserver" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_mailserver"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][5]?></td>
   <td class="content_row">
      <input name="conf_mail_accountname" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_mail_accountname"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][6]?></td>
   <td class="content_row">
      <input name="conf_mail_password" type="password" class="text" style="width:300px" value="<?=$conf[0]["conf_mail_password"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][7]?></td>
   <td class="content_row">
      <input name="conf_mail_sendername" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_mail_sendername"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][8]?></td>
   <td class="content_row">
      <input name="conf_mail_replyto" type="text" class="text" style="width:300px" value="<?=$conf[0]["conf_mail_replyto"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pconfig)", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br><br>