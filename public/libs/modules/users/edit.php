<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if((int)$_REQUEST["delsig"])
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/user_signatures/";

   $sql = " select user_doc_signature
            from user
            where
            id = {$_REQUEST["uid"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["user_doc_signature"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["user_doc_signature"]}");
      unlink("{$doc_dir}s{$orgimg[0]["user_doc_signature"]}");
   }

   $sql = " update user
            set user_doc_signature = ''
            where
            id = {$_REQUEST["uid"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["delpic"])
{
   $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/user_pics/";

   $sql = " select user_pic
            from user
            where
            id = {$_REQUEST["uid"]}";
   $orgimg = $CON->select($sql);

   if($orgimg[0]["user_pic"] != "")
   {
      unlink("{$doc_dir}{$orgimg[0]["user_pic"]}");
      unlink("{$doc_dir}s{$orgimg[0]["user_pic"]}");
   }

   $sql = " update user
            set user_pic = ''
            where
            id = {$_REQUEST["uid"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["exec"] == "save")
{
   $currtme = time();

   $_REQUEST["user_login"]       = trim(addslashes($_REQUEST["user_login"]));
   $_REQUEST["user_pass1"]       = trim(addslashes($_REQUEST["user_pass1"]));
   $_REQUEST["user_firstname"]   = trim(addslashes($_REQUEST["user_firstname"]));
   $_REQUEST["user_lastname"]    = trim(addslashes($_REQUEST["user_lastname"]));
   $_REQUEST["user_mail"]        = trim(addslashes($_REQUEST["user_mail"]));
   $_REQUEST["user_street"]      = trim(addslashes($_REQUEST["user_street"]));
   $_REQUEST["user_code"]        = trim(addslashes($_REQUEST["user_code"]));
   $_REQUEST["country"]          = (int)$_REQUEST["country"];
   $_REQUEST["regions"]          = (int)$_REQUEST["regions"];
   $_REQUEST["provincias"]       = (int)$_REQUEST["provincias"];
   $_REQUEST["comunas"]          = (int)$_REQUEST["comunas"];
   $_REQUEST["user_seller_ext"]  = (int)$_REQUEST["user_seller_ext"];
   $_REQUEST["user_cajapricesell_perm"]   = (int)$_REQUEST["user_cajapricesell_perm"];
   $_REQUEST["user_cajaamt_direct"]       = (int)$_REQUEST["user_cajaamt_direct"];

   $_REQUEST["user_telephone"]   = trim(addslashes($_REQUEST["user_telephone"]));
   $_REQUEST["user_cellphone"]   = trim(addslashes($_REQUEST["user_cellphone"]));
   $_REQUEST["user_internet"]    = trim(addslashes($_REQUEST["user_internet"]));
   $_REQUEST["user_annotations"] = trim(addslashes($_REQUEST["user_annotations"]));

   $_REQUEST["user_type"]              = (int)$_REQUEST["user_type"];
   $_REQUEST["user_status"]            = (int)$_REQUEST["user_status"];
   $_REQUEST["user_seller"]            = (int)$_REQUEST["user_seller"];
   $_REQUEST["user_visible"]           = (int)$_REQUEST["user_visible"];
   $_REQUEST["user_mailforward"]       = (int)$_REQUEST["user_mailforward"];
   $_REQUEST["user_pw_renew"]          = (int)$_REQUEST["user_pw_renew"];
   $_REQUEST["user_sidepanel_active"]  = (int)$_REQUEST["user_sidepanel_active"];
   $_REQUEST["user_sidepanel_login"]   = (int)$_REQUEST["user_sidepanel_login"];
   $_REQUEST["user_pricesell_perm"]    = (int)$_REQUEST["user_pricesell_perm"];
   $_REQUEST["user_pricebuy_perm"]     = (int)$_REQUEST["user_pricebuy_perm"];
   $_REQUEST["user_docopen_perm"]      = (int)$_REQUEST["user_docopen_perm"];
   $_REQUEST["user_printer_mode"]      = (int)$_REQUEST["user_printer_mode"];
   $_REQUEST["user_orderaprob_perm"]   = (int)$_REQUEST["user_orderaprob_perm"];
   $_REQUEST["user_invclimit_perm"]    = (int)$_REQUEST["user_invclimit_perm"];
   $_REQUEST["user_ocmax_perm"]        = (int)$_REQUEST["user_ocmax_perm"];
   $_REQUEST["user_offerlimit_perm"]   = (int)$_REQUEST["user_offerlimit_perm"];
   $_REQUEST["user_orderlimit_perm"]   = (int)$_REQUEST["user_orderlimit_perm"];
   $_REQUEST["user_orderabono_perm"]   = (int)$_REQUEST["user_orderabono_perm"];
   $_REQUEST["user_printer_name"]      = trim(str_replace('"',"",str_replace("'","",$_REQUEST["user_printer_name"])));
   $_REQUEST["user_printer_port"]      = trim(addslashes($_REQUEST["user_printer_port"]));
   $_REQUEST["user_rut"]               = trim(addslashes($_REQUEST["user_rut"]));
   $_REQUEST["user_comission_perc"]    = getPrice($_REQUEST["user_comission_perc"],2);
   $_REQUEST["user_autoriza_cc_perm"]    = (int)$_REQUEST["user_autoriza_cc_perm"];
   $_REQUEST["user_autoriza_fact_perm"]  = (int)$_REQUEST["user_autoriza_fact_perm"];
   $_REQUEST["user_st_email_cierre"]     = (int)$_REQUEST["user_st_email_cierre"];
   

   $_REQUEST["conf_mailserver"]        = trim(addslashes($_REQUEST["conf_mailserver"]));
   $_REQUEST["conf_mail_accountname"]  = trim(addslashes($_REQUEST["conf_mail_accountname"]));
   $_REQUEST["conf_mail_password"]     = trim(addslashes($_REQUEST["conf_mail_password"]));

   $_REQUEST["user_appmode_0"]               = (int)$_REQUEST["user_appmode_0"];
   $_REQUEST["user_appmode_1"]               = (int)$_REQUEST["user_appmode_1"];
   $_REQUEST["user_appmode_2"]               = (int)$_REQUEST["user_appmode_2"];
   $_REQUEST["user_appmode_3"]               = (int)$_REQUEST["user_appmode_3"];
   $_REQUEST["user_appmode_4"]               = (int)$_REQUEST["user_appmode_4"];

   $_REQUEST["user_informar_cierre_cotiza"]  = (int)$_REQUEST["user_informar_cierre_cotiza"];

   
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "update")
   {
      $sql = " select count(*) 'cc'
               from user
               where
               user_login = '{$_REQUEST["user_login"]}' and
               id != {$_REQUEST["uid"]} and
               user_status >= 0";
      $loginchk = $CON->select($sql);
      $loginchk = (int)$loginchk[0]["cc"];

      if(!$loginchk)
      {
         $sql = " update user
                  set
                  user_login                  = '{$_REQUEST["user_login"]}',
                  user_code                   = '{$_REQUEST["user_code"]}',
                  user_firstname              = '{$_REQUEST["user_firstname"]}',
                  user_lastname               = '{$_REQUEST["user_lastname"]}',
                  user_type                   =  {$_REQUEST["user_type"]},
                  user_status                 =  {$_REQUEST["user_status"]},
                  user_visible                =  {$_REQUEST["user_visible"]},
                  user_mailforward            =  {$_REQUEST["user_mailforward"]},
                  user_mail                   = '{$_REQUEST["user_mail"]}',
                  user_street                 = '{$_REQUEST["user_street"]}',
                  user_telephone              = '{$_REQUEST["user_telephone"]}',
                  user_cellphone              = '{$_REQUEST["user_cellphone"]}',
                  user_internet               = '{$_REQUEST["user_internet"]}',
                  user_annotations            = '{$_REQUEST["user_annotations"]}',
                  user_pw_renew               =  {$_REQUEST["user_pw_renew"]},
                  user_countryid              =  {$_REQUEST["country"]},
                  user_regionid               =  {$_REQUEST["regions"]},
                  user_provinciaid            =  {$_REQUEST["provincias"]},
                  user_comunaid               =  {$_REQUEST["comunas"]},
                  user_cajapricesell_perm     = {$_REQUEST["user_cajapricesell_perm"]},
                  user_sidepanel_active       = {$_REQUEST["user_sidepanel_active"]},
                  user_sidepanel_login        = {$_REQUEST["user_sidepanel_login"]},
                  user_printer_name           = '{$_REQUEST["user_printer_name"]}',
                  user_pricesell_perm         = {$_REQUEST["user_pricesell_perm"]},
                  user_pricebuy_perm          = {$_REQUEST["user_pricebuy_perm"]},
                  user_docopen_perm           = {$_REQUEST["user_docopen_perm"]},
                  user_printer_mode           = {$_REQUEST["user_printer_mode"]},
                  user_printer_port           = '{$_REQUEST["user_printer_port"]}',
                  user_rut                    = '{$_REQUEST["user_rut"]}',
                  user_seller_ext             = {$_REQUEST["user_seller_ext"]},
                  user_comission_perc         = {$_REQUEST["user_comission_perc"]},
                  user_cajaamt_direct         = {$_REQUEST["user_cajaamt_direct"]},
                  user_orderaprob_perm        = {$_REQUEST["user_orderaprob_perm"]},
                  user_ocmax_perm             = {$_REQUEST["user_ocmax_perm"]},
                  user_offerlimit_perm        = {$_REQUEST["user_offerlimit_perm"]},
                  user_orderlimit_perm        = {$_REQUEST["user_orderlimit_perm"]},
                  user_orderabono_perm        = {$_REQUEST["user_orderabono_perm"]},
                  user_invclimit_perm         = {$_REQUEST["user_invclimit_perm"]},  
                  user_autoriza_cc_perm       = {$_REQUEST["user_autoriza_cc_perm"]},
                  user_autoriza_fact_perm     = {$_REQUEST["user_autoriza_fact_perm"]},
                  conf_mailserver             = '{$_REQUEST["conf_mailserver"]}',
                  conf_mail_accountname       = '{$_REQUEST["conf_mail_accountname"]}',
                  conf_mail_password          = '{$_REQUEST["conf_mail_password"]}',
                  user_appmode_0              = {$_REQUEST["user_appmode_0"]},
                  user_appmode_1              = {$_REQUEST["user_appmode_1"]},
                  user_appmode_2              = {$_REQUEST["user_appmode_2"]},
                  user_appmode_3              = {$_REQUEST["user_appmode_3"]},
                  user_appmode_4              = {$_REQUEST["user_appmode_4"]},
                  user_updusr                 = {$_SESSION["user_id"]},
                  user_upddat                 = {$currtme},
                  user_st_email_cierre        = {$_REQUEST["user_st_email_cierre"]},
                  user_informar_cierre_cotiza = {$_REQUEST["user_informar_cierre_cotiza"]}
                  where
                  id = {$_REQUEST["uid"]}";
         $res = $CON->no_result($sql);

         if($res)
         {
            if((int)$_REQUEST["pw_mail"])
               sendUserPasswordMail($CON);
            
            if($_REQUEST["user_pass1"] != "")
            {
               $_REQUEST["user_pass1"] = md5($_REQUEST["user_pass1"]);

               $sql = " update user
                        set
                        user_pass = '{$_REQUEST["user_pass1"]}'
                        where
                        id = {$_REQUEST["uid"]}";
               $CON->no_result($sql);
            }

            $sql = " delete from user_group
                     where
                     user_id = {$_REQUEST["uid"]}";
            $CON->no_result($sql);

            $sql = " delete from user_companies
                     where
                     user_id = {$_REQUEST["uid"]}";
            $CON->no_result($sql);

            $savemsg = getSaveMessage(true);
         }
         else
            $savemsg = getSaveMessage(false);
      }
      else
         $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["USER"][11]}</b>";

      $userid = $_REQUEST["uid"];
   }
   else
   {
      $sql = " select count(*) 'cc'
               from user
               where
               user_login = '{$_REQUEST["user_login"]}' and
               user_status >= 0";
      $loginchk = $CON->select($sql);
      $loginchk = (int)$loginchk[0]["cc"];

      if(!$loginchk)
      {
         if((int)$_REQUEST["pw_mail"])
            sendUserPasswordMail($CON);

         if($_REQUEST["user_pass1"] == "")
            $_REQUEST["user_pass1"] = time();
            
         $_REQUEST["user_pass1"] = md5($_REQUEST["user_pass1"]);

         $sql = " insert into user
                  (user_login, user_pass, user_firstname, user_lastname, user_type, user_status,
                   user_visible, user_mailforward, user_mail, user_street, user_countryid, user_regionid, user_comunaid,
                   user_telephone, user_cellphone, user_internet, user_annotations, user_pw_renew, user_sidepanel_active,
                   user_sidepanel_login, user_printer_name, user_pricesell_perm, user_pricebuy_perm,
                   user_docopen_perm, user_printer_mode, user_printer_port, user_rut,
                   user_seller_ext, user_crtusr, user_crtdat, user_provinciaid, user_code,
                   user_comission_perc, user_cajapricesell_perm, user_cajaamt_direct, user_orderaprob_perm,
                   user_ocmax_perm, user_offerlimit_perm, user_orderlimit_perm, user_orderabono_perm,
                   conf_mailserver, conf_mail_accountname, conf_mail_password, user_appmode_0, user_appmode_1, user_appmode_2,
                   user_appmode_3, user_invclimit_perm, user_appmode_4,user_autoriza_cc_perm, user_autoriza_fact_perm,user_st_email_cierre,
                   user_informar_cierre_cotiza)
                  VALUES
                  ('{$_REQUEST["user_login"]}', '{$_REQUEST["user_pass1"]}', '{$_REQUEST["user_firstname"]}', '{$_REQUEST["user_lastname"]}',
                    {$_REQUEST["user_type"]}, {$_REQUEST["user_status"]}, {$_REQUEST["user_visible"]}, {$_REQUEST["user_mailforward"]},
                   '{$_REQUEST["user_mail"]}', '{$_REQUEST["user_street"]}', {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]},
                   '{$_REQUEST["user_telephone"]}', '{$_REQUEST["user_cellphone"]}', '{$_REQUEST["user_internet"]}', '{$_REQUEST["user_annotations"]}',
                    {$_REQUEST["user_pw_renew"]}, {$_REQUEST["user_sidepanel_active"]}, {$_REQUEST["user_sidepanel_login"]}, '{$_REQUEST["user_printer_name"]}',
                    {$_REQUEST["user_pricesell_perm"]}, {$_REQUEST["user_pricebuy_perm"]}, {$_REQUEST["user_docopen_perm"]},
                    {$_REQUEST["user_printer_mode"]}, '{$_REQUEST["user_printer_port"]}', '{$_REQUEST["user_rut"]}', {$_REQUEST["user_seller_ext"]}, {$_SESSION["user_id"]},
                    {$currtme}, {$_REQUEST["provincias"]}, '{$_REQUEST["user_code"]}', {$_REQUEST["user_comission_perc"]}, {$_REQUEST["user_cajapricesell_perm"]},
                    {$_REQUEST["user_cajaamt_direct"]}, {$_REQUEST["user_orderaprob_perm"]},
                    {$_REQUEST["user_ocmax_perm"]}, {$_REQUEST["user_offerlimit_perm"]}, {$_REQUEST["user_orderlimit_perm"]},
                    {$_REQUEST["user_orderabono_perm"]}, '{$_REQUEST["conf_mailserver"]}', '{$_REQUEST["conf_mail_accountname"]}',
                    '{$_REQUEST["conf_mail_password"]}', {$_REQUEST["user_appmode_0"]}, {$_REQUEST["user_appmode_1"]},
                    {$_REQUEST["user_appmode_2"]}, {$_REQUEST["user_appmode_3"]}, {$_REQUEST["user_invclimit_perm"]}, {$_REQUEST["user_appmode_4"]}, {$_REQUEST["user_autoriza_cc_perm"]}, {$_REQUEST["user_autoriza_fact_perm"]}
                    ,{$_REQUEST["user_st_email_cierre"]}, {$_REQUEST["user_informar_cierre_cotiza"]} )";
         $res = $CON->no_result($sql);

         $savemsg = getSaveMessage($res);

         $sql = " select MAX(id) 'uid'
                  from user";
         $userid = $CON->select($sql);
         $userid = $userid[0]["uid"];

         if($res)
         {
            $redirect = true;
         }
      }
      else
      {
         $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["USER"][11]}</b>";

         $userdata[0]["user_login"]       = $_REQUEST["user_login"];
         $userdata[0]["user_type"]        = $_REQUEST["user_type"];
         $userdata[0]["user_firstname"]   = $_REQUEST["user_firstname"];
         $userdata[0]["user_lastname"]    = $_REQUEST["user_lastname"];
         $userdata[0]["user_status"]      = $_REQUEST["user_status"];
      }
   }
   if($res)
   {
      $sql = " delete from user_shops
               where
               user_id = {$userid}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      foreach($_REQUEST["shop_act"] AS $shopid)
      {
         $sql = " insert into user_shops
                  (user_id, shop_id)
                  VALUES
                  ({$userid}, {$shopid})";
         $res = $CON->no_result($sql);
      }
   
      //----------------------------------------------------------------------------------
      if((int)$userid &&
         $_FILES["user_pic"]["name"] != "" &&
         $_FILES["user_pic"]["tmp_name"] != "" &&
         $_FILES["user_pic"]["error"] == 0 &&
         $_FILES["user_pic"]["size"] > 0)
      {
         $doc_type   = strtolower(substr($_FILES["user_pic"]["name"], strrpos($_FILES["user_pic"]["name"], ".") +1));
         $doc_hash   = md5(microtime());
         $doc_name   = "{$_REQUEST["uid"]}.{$doc_hash}.{$doc_type}";
         $doc_dir    = "./images/user_pics/";
         $res        = move_uploaded_file($_FILES["user_pic"]["tmp_name"], "{$doc_dir}{$doc_name}");

         if($res)
         {
            resizeImage("{$doc_dir}{$doc_name}", 80, "", "{$doc_dir}s{$doc_name}");

            $sql = " select *
                     from user
                     where
                     id = {$userid}";
            $oldpic = $CON->select($sql);
            $oldpic = $oldpic[0]["user_pic"];

            if($oldpic != "")
            {
               unlink("{$doc_dir}{$oldpic}");
               unlink("{$doc_dir}s{$oldpic}");
            }

            $sql = " update user
                     set
                     user_pic = '{$doc_name}'
                     where
                     id = {$userid}";
            $CON->no_result($sql);
         }
      }

      //----------------------------------------------------------------------------------
      if((int)$userid &&
         $_FILES["user_doc_signature"]["name"] != "" &&
         $_FILES["user_doc_signature"]["tmp_name"] != "" &&
         $_FILES["user_doc_signature"]["error"] == 0 &&
         $_FILES["user_doc_signature"]["size"] > 0)
      {
         $doc_type = substr($_FILES["user_doc_signature"]["name"], strrpos($_FILES["user_doc_signature"]["name"], ".") +1);
         $doc_hash = md5(microtime());
         $doc_name = "{$userid}_{$doc_hash}.{$doc_type}";
         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/user_signatures/";

         $sql = " select user_doc_signature
                  from user
                  where
                  id = {$userid}";
         $orgimg = $CON->select($sql);

         if($orgimg[0]["user_doc_signature"] != "")
         {
            unlink("{$doc_dir}{$orgimg[0]["user_doc_signature"]}");
            unlink("{$doc_dir}s{$orgimg[0]["user_doc_signature"]}");
         }

         $res = move_uploaded_file($_FILES["user_doc_signature"]["tmp_name"], "{$doc_dir}{$doc_name}");

         if($res)
         {
            resizeImage("{$doc_dir}{$doc_name}", 800, "", "{$doc_dir}{$doc_name}");
            resizeImage("{$doc_dir}{$doc_name}", 150, "", "{$doc_dir}s{$doc_name}");

            $sql = " update user
                     set user_doc_signature = '{$doc_name}'
                     where
                     id = {$userid}";
            $res = $CON->no_result($sql);

            $savemsg = getSaveMessage($res);
         }
         else
            $savemsg = getSaveMessage(false);
      }
   
      if(is_array($_REQUEST["groupids"]))
      {
         foreach($_REQUEST["groupids"] AS $groupid)
         {
            $is_adm = (int)$_REQUEST["is_adm_{$groupid}"];

            $sql = " insert into user_group
                     (user_id, group_id, is_adm)
                     VALUES
                     ({$userid}, {$groupid}, {$is_adm})";
            $CON->no_result($sql);
         }
      }
      if(is_array($_REQUEST["companies"]))
      {
         foreach($_REQUEST["companies"] AS $companyid)
         {
            $sql = " insert into user_companies
                     (user_id, company_id)
                     VALUES
                     ({$userid}, {$companyid})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "update")
{
   $title = $_LANG["MODULE"]["USER"][12];

   $sql = " select *
            from user
            where
            id = {$_REQUEST["uid"]}";
   $userdata = $CON->select($sql);

   $sql = " select group_id
            from user_group 
            where
            user_id = {$_REQUEST["uid"]}";
   $groupsels = $CON->select($sql);
   foreach($groupsels AS $groupsel)
      $_SELGROUP[$groupsel["group_id"]] = 1;

   $sql = " select company_id
            from user_companies
            where
            user_id = {$_REQUEST["uid"]}";
   $usercompanies = $CON->select($sql);
   foreach($usercompanies AS $usercompany)
      $_SELCOMP[$usercompany["company_id"]] = 1;
}
else
{
   $title = $_LANG["MODULE"]["USER"][13];

   $userdata[0]["user_countryid"] = 81;
}

$sql = " select id, group_name, group_desc
         from `group`
         where
         group_status = 1
         order by group_name asc";
$groups = $CON->select($sql);
   
if($redirect)
{  ?>
   <script language="JavaScript">
      location.href='index.php?mid=21&subexec=update&uid=<?=$userid?>&saveok=1';
   </script>
   <?php
}

if($_REQUEST["saveok"] == "1")
   $savemsg = getSaveMessage(true);

$countries  = getCountries($CON);
$regions    = getRegions($CON);
$provincias = getProvincias($CON);
$comunas    = getComunas($CON);

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_user" enctype="multipart/form-data"
<?php
if($_REQUEST["subexec"] == "update")
{  ?>
   onsubmit="var rutchk = Rut(this.user_rut, this.user_rut.value);if(!rutchk) return false;
             return checkuserform(new Array(this.user_login,this.user_code,this.user_firstname,this.user_lastname));"
   <?php
}
else
{  ?>
   onsubmit="var rutchk = Rut(this.user_rut, this.user_rut.value);if(!rutchk) return false;
             return checkuserform(new Array(this.user_login,this.user_code,this.user_firstname,this.user_lastname));"
   <?php
}
?>>
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="subexec" value="<?=$_REQUEST["subexec"]?>">
<input type="hidden" name="uid" value="<?=$_REQUEST["uid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="delsig" value="0">
<input type="hidden" name="delpic" value="0">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
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
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["USER"][1]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Usuario *</td>
         <td class="content_row">
            <input name="user_login" type="text" class="text" style="width:357px" value="<?=$userdata[0]["user_login"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <input style="display:none" type="password" name="foilautofill">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Codigo *</td>
         <td class="content_row">
            <input name="user_code" type="text" class="text" style="width:150px" value="<?=$userdata[0]["user_code"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Contraseña</td>
         <td class="content_row">
            <input name="user_pass1" type="password" class="text" style="width:150px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Contraseña</td>
         <td class="content_row">
            <input name="user_pass2" type="password" class="text" style="width:150px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][16]?></td>
         <td class="content_row">
            <select name="user_type" class="text" style="width:150px" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="2" <?php if($userdata[0]["user_type"] != "1") echo "selected"?>><?=$_LANG["MODULE"]["USER"][33]?></option>
               <option value="1" <?php if($userdata[0]["user_type"] == "1") echo "selected"?>><?=$_LANG["MODULE"]["USER"][34]?></option>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top">Permisos especiales</td>
         <td class="content_row" valign="top">
            <input name="user_pricesell_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_pricesell_perm"]) echo "checked"?>>
            Cambiar precios / descuentos en ventas<br>
            <input name="user_pricebuy_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_pricebuy_perm"]) echo "checked"?>>
            Cambiar precios / descuentos en compras<br>
            <input name="user_docopen_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_docopen_perm"]) echo "checked"?>>
            Restablecer documentos tributarios (finalizadas)<br>
            <input name="user_orderaprob_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_orderaprob_perm"]) echo "checked"?>>
            Aprobar y enviar confirmación de compra<br>
            <input name="user_orderabono_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_orderabono_perm"]) echo "checked"?>>
            Registrar abonos en confirmación de compra<br>
            <!--
            <input name="user_ocmax_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_ocmax_perm"]) echo "checked"?>>
            Aprobar ordenes de compra sobre el máximo<br>
            -->
            <input name="user_offerlimit_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_offerlimit_perm"]) echo "checked"?>>
            Solo puede ver cotizaciones propias<br>
            <input name="user_orderlimit_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_orderlimit_perm"]) echo "checked"?>>
            Solo puede ver confirmaciónes de compra propias<br>
            
            <input name="user_invclimit_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_invclimit_perm"]) echo "checked"?>>
            Solo puede ver doc.trib. de ventas propias<br>

            <input name="user_autoriza_cc_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_autoriza_cc_perm"]) echo "checked"?>>
            Autorizar Nota de Ventas<br>

            <input name="user_autoriza_fact_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_autoriza_fact_perm"]) echo "checked"?>>
            Autorizar Facturación<br>

            <input name="user_informar_cierre_cotiza" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_informar_cierre_cotiza"]) echo "checked"?>>
            Informar Cierres de Cotizaciones<br>

         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][36]?></td>
         <td class="content_row">
            <input name="user_visible" type="checkbox" value="1">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl" height="30">Opciones caja</td>
         <td class="content_row">
            <input name="user_cajapricesell_perm" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_cajapricesell_perm"]) echo "checked"?>>
            Autorizar anulaciones
            <input name="user_cajaamt_direct" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_cajaamt_direct"]) echo "checked"?>>
            Confirmar cantidad immediato
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Imagen de usuario</td>
         <td class="content_row">
            <?php
            if($userdata[0]["user_pic"] != "")
            {  ?>
               <table border="0" cellspacing="0" cellpadding="0" width="185">
               <tr>
                  <td style="padding-right:5px" width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][5], "postnav", "javascript: deactivateFormChange()", "showFancyboxAuto('../../../../images/user_pics/{$userdata[0]["user_pic"]}', 'image')", "navigation-270-white");
                     ?>
                  </td>
                  <td width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) document.xform_user.delpic.value='1';submitForm(document.xform_user) ", "cross-circle-frame");
                     ?>
                  </td>
               </tr>
               </table>
               <?php
            }
            else
            {  ?>
               <input class="text" type="file" name="user_pic" maxlength="100" style="width:120px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
            ?>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Imagen de firma</td>
         <td class="content_row">
            <?php
            if($userdata[0]["user_doc_signature"] != "")
            {  ?>
               <table border="0" cellspacing="0" cellpadding="0" width="185">
               <tr>
                  <td style="padding-right:5px" width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][5], "postnav", "javascript: deactivateFormChange()", "showFancyboxAuto('../../../../images/user_signatures/{$userdata[0]["user_doc_signature"]}', 'image')", "navigation-270-white");
                     ?>
                  </td>
                  <td width="90">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) document.xform_user.delsig.value='1';submitForm(document.xform_user) ", "cross-circle-frame");
                     ?>
                  </td>
               </tr>
               </table>
               <?php
            }
            else
            {  ?>
               <input class="text" type="file" name="user_doc_signature" maxlength="100" style="width:120px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
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
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["USER"][35]?></td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][19]?></td>
         <td class="content_row">
            <input name="user_rut" type="text" class="text" style="width:100px" value="<?=$userdata[0]["user_rut"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][17]?></td>
         <td class="content_row">
            <input name="user_firstname" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_firstname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][18]?></td>
         <td class="content_row">
            <input name="user_lastname" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_lastname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="user_mail" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_mail"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][43]?></td>
         <td class="content_row">
            <input name="user_mailforward" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_mailforward"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][37]?></td>
         <td class="content_row">
            <input name="user_street" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">País</td>
         <td class="content_row" width="130">
            <select class="text" style="width:310px" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $userdata[0]["user_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Región</td>
         <td class="content_row" width="130">
            <select class="text" style="width:310px" name="regions" id="regions"
            onchange="setProvincias(this.value);"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$userdata[0]["user_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $userdata[0]["user_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $userdata[0]["user_regionid"]) echo "selected"?>><?=$region["name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Provincia</td>
         <td class="content_row">
            <select class="text" style="width:310px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$userdata[0]["user_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $userdata[0]["user_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $userdata[0]["user_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Comuna</td>
         <td class="content_row">
            <select class="text" style="width:310px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$userdata[0]["user_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $userdata[0]["user_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $userdata[0]["user_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][39]?></td>
         <td class="content_row">
            <input name="user_telephone" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_telephone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][40]?></td>
         <td class="content_row">
            <input name="user_cellphone" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl"><?=$_LANG["MODULE"]["USER"][41]?></td>
         <td class="content_row">
            <input name="user_internet" type="text" class="text" style="width:310px" value="<?=$userdata[0]["user_internet"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Comisión Vendedor</td>
         <td class="content_row">
            <input name="user_comission_perc" type="text" class="text" style="width:80px"
            value="<?=printPrice($userdata[0]["user_comission_perc"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="38"><?=$_LANG["MODULE"]["USER"][19]?></td>
         <td class="content_row">
            <input name="user_status" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_status"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
            &nbsp;&nbsp;Panel lateral
            <input name="user_sidepanel_active" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_sidepanel_active"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
            Activado
            <input name="user_sidepanel_login" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_sidepanel_login"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
            Encima / Login
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="38">Recibir Correos</td>
         <td class="content_row"> 
            <input name="user_st_email_cierre" type="checkbox" value="1"
            <?php if((int)$userdata[0]["user_st_email_cierre"] || $_REQUEST["subexec"] != "update") echo "checked"?>>Cierres de Producción
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="52"><?=$_LANG["MODULE"]["USER"][49]?></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="184" id="idx_td_pwsel" height="20">
                  <?php
                  if($_REQUEST["uid"] != "")
                  {  ?>
                     <ul class="postnav">
                        <a href="javascript: deactivateFormChange()"
                        onclick="openEMailSigWindow('<?=$_REQUEST["uid"]?>')"><?=$_LANG["FORM"]["BUTTON"][3]?></a>
                     </ul>
                     <?php
                  }
                  ?>
               </td>
               <td class="content_row_clear">&nbsp;</td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <?php
   if($_REQUEST["subexec"] == "update")
   {  ?>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&uid={$_REQUEST["uid"]}')", "cross-circle-frame");
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
   <td width="130" align="right">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_user)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="160">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Configuración email</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][4]?></td>
   <td class="content_row">
      <input name="conf_mailserver" type="text" class="text" style="width:357px" value="<?=$userdata[0]["conf_mailserver"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][5]?></td>
   <td class="content_row">
      <input name="conf_mail_accountname" type="text" class="text" style="width:357px" value="<?=$userdata[0]["conf_mail_accountname"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CONF"][6]?></td>
   <td class="content_row">
      <input name="conf_mail_password" type="password" class="text" style="width:357px" value="<?=$userdata[0]["conf_mail_password"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Acceso a subsistemas</td>
</tr>
<tr>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][21]?></td>
   <td class="content_tbl_subheader">Nombre subsistema</td>
</tr>
<tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row" align="center">
      <input type="checkbox" name="user_appmode_0" value="1"
      <?php if((int)$userdata[0]["user_appmode_0"]) echo "checked"?>>
   </td>
   <td class="content_row">ERP</td>
</tr>
<tr bgcolor="<?=getRowColor(1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row" align="center">
      <input type="checkbox" name="user_appmode_1" value="1"
      <?php if((int)$userdata[0]["user_appmode_1"]) echo "checked"?>>
   </td>
   <td class="content_row">Producción</td>
</tr>
<!--
<tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row" align="center">
      <input type="checkbox" name="user_appmode_2" value="1"
      <?php if((int)$userdata[0]["user_appmode_2"]) echo "checked"?>>
   </td>
   <td class="content_row">Operador</td>
</tr>
-->
<tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row" align="center">
      <input type="checkbox" name="user_appmode_3" value="1"
      <?php if((int)$userdata[0]["user_appmode_3"]) echo "checked"?>>
   </td>
   <td class="content_row">Bodega</td>
</tr>
<tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row" align="center">
      <input type="checkbox" name="user_appmode_4" value="1"
      <?php if((int)$userdata[0]["user_appmode_4"]) echo "checked"?>>
   </td>
   <td class="content_row">Diseñadores</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col width="300">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Roles disponibles</td>
</tr>
<tr>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][21]?></td>
   <td class="content_tbl_subheader">Nombre rol</td>
   <td class="content_tbl_subheader">Descripción</td>
</tr>
<?php
for($x = 0; $x < count($groups); $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" align="center">
         <input type="checkbox" name="groupids[]" value="<?=$groups[$x]["id"]?>"
         <?php if((int)$_SELGROUP[$groups[$x]["id"]]) echo "checked"?>>
      </td>
      <td class="content_row"><?=$groups[$x]["group_name"]?></td>
      <td class="content_row"><?=nl2br($groups[$x]["group_desc"])?>&nbsp;</td>
   </tr>
   <?php
   if((int)$_SELGROUP[$groups[$x]["id"]] && $groups[$x]["id"] == 15)
      $_ISCAJERO = true;
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);
?>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Acceso a empresas</td>
</tr>
<tr>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][21]?></td>
   <td class="content_tbl_subheader">Nombre empresa</td>
</tr>
<?php
for($x = 0; $x < count($companies); $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" align="center">
         <input type="checkbox" name="companies[]" value="<?=$companies[$x]["id"]?>"
         <?php if((int)$_SELCOMP[$companies[$x]["id"]]) echo "checked"?>>
      </td>
      <td class="content_row"><?=$companies[$x]["company_name"]?></td>
   </tr>
   <?php
}  ?>
</table>
<?=Nifty_printF()?>

<div id="idx_cajeroshops" style="<?php if(!$_ISCAJERO) echo "display:none"?>">
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from user_shops
         where
         user_id = {$_REQUEST["uid"]}";
$usershops = $CON->select($sql);
foreach($usershops AS $usershop)
   $_USERSHOPS[$usershop["shop_id"]] = 1;

/*
?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Habilitar sucursales para cajeros</td>
</tr>
<tr>
   <td class="content_tbl_subheader" align="center">Act.</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Dirección</td>
</tr>
<?php
foreach($companies AS $company)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status       = 1 and
            t1.shop_isremote     = 1 and
            t1.shop_company_id   = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {
      //----------------------------------------------------------------------------------
      $shopid = $shops[$x]["id"];

      //----------------------------------------------------------------------------------
      $sql = " select *
               from user_shops
               where
               user_id  = {$_REQUEST["uid"]} and
               shop_id  = {$shopid}";
      $seldata = $CON->select($sql);
      $seldata = $seldata[0];

      //----------------------------------------------------------------------------------
      if((int)$seldata["shop_id"])
         $rowstyle = "style='color:#000000'";
      else
         $rowstyle = "style='color:#888888'";

      //----------------------------------------------------------------------------------
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="checkbox" name="shop_act[]" value="<?=$shops[$x]["id"]?>"
            <?php if((int)$seldata["shop_id"]) echo "checked" ?>>
         </td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_street"]?>&nbsp;</td>
      </tr>
      <?php
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
*/
?>
</div>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_user');" ?>