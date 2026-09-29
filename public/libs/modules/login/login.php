<?php
//----------------------------------------------------------------------------------
// Copyright:     2020 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$idx = 0;
$_MAIN[$idx]["NAME"] = "ERP";
$_MAIN[$idx]["SUBN"] = "#00A9A6";
$_MAIN[$idx]["ICON"] = "<i class='fa fa-fw fa-file-text-o icon0' style='color:#FFFFFF;font-size:22px;padding-top:4px;padding-left:15px'></i>";
$_MAIN[$idx]["DIVC"] = "margin-left:28px;margin-top:8px;color:#FFFFFF";
$_MAIN[$idx]["EXEC"] = "resetIconColors();setIconOption('0');document.getElementById('user_login').focus();$('#idx_planta_opts').hide(0);";
$_MAIN[$idx]["APID"] = "0";
$idx++;
$_MAIN[$idx]["NAME"] = "Producción";
$_MAIN[$idx]["SUBN"] = "#F9F9F9";
$_MAIN[$idx]["ICON"] = "<i class='fa fa-fw fa-print icon1' style='color:#666666;font-size:26px;padding-top:2px;padding-left:12px'></i>";
$_MAIN[$idx]["DIVC"] = "margin-left:9px;margin-top:8px;color:#666666";
$_MAIN[$idx]["EXEC"] = "resetIconColors();setIconOption('1');document.getElementById('user_login').focus();$('#idx_planta_opts').hide(0);";
$_MAIN[$idx]["APID"] = "1";
$idx++;
$_MAIN[$idx]["NAME"] = "Operador";
$_MAIN[$idx]["SUBN"] = "#F9F9F9";
$_MAIN[$idx]["ICON"] = "<i class='fa fa-fw fa-wrench icon2' style='color:#666666;font-size:24px;padding-top:3px;padding-left:14px'></i>";
$_MAIN[$idx]["DIVC"] = "margin-left:14px;margin-top:8px;color:#666666";
$_MAIN[$idx]["EXEC"] = "resetIconColors();setIconOption('2');document.getElementById('user_login').focus();$('#idx_planta_opts').show(0);";
$_MAIN[$idx]["APID"] = "2";
$idx++;
$_MAIN[$idx]["NAME"] = "Bodega";
$_MAIN[$idx]["SUBN"] = "#F9F9F9";
$_MAIN[$idx]["ICON"] = "<i class='fa fa-fw fa-cubes icon3' style='color:#666666;font-size:24px;padding-top:3px;padding-left:12px'></i>";
$_MAIN[$idx]["DIVC"] = "margin-left:14px;margin-top:8px;color:#666666";
$_MAIN[$idx]["EXEC"] = "resetIconColors();setIconOption('3');document.getElementById('user_login').focus();$('#idx_planta_opts').show(0);";
$_MAIN[$idx]["APID"] = "3";
$idx++;
$_MAIN[$idx]["NAME"] = "Mkt-Diseño";
$_MAIN[$idx]["SUBN"] = "#F9F9F9";
$_MAIN[$idx]["ICON"] = "<i class='fa fa-fw fa-paint-brush icon4' style='color:#666666;font-size:24px;padding-top:3px;padding-left:12px'></i>";
$_MAIN[$idx]["DIVC"] = "margin-left:6px;margin-top:8px;color:#666666";
$_MAIN[$idx]["EXEC"] = "resetIconColors();setIconOption('4');document.getElementById('user_login').focus();$('#idx_planta_opts').hide(0);";
$_MAIN[$idx]["APID"] = "4";
$idx++;

if($_REQUEST["exec"] == "login")
{
   if((int)$_REQUEST["appmode"] == 2)
   {
      $_REQUEST["user_login"] = mysql_real_escape_string(trim(addslashes($_REQUEST["user_login"])), $CON->CMYSQL_CON);
      $_REQUEST["user_login"] = str_replace(".", "", $_REQUEST["user_login"]);
      $wrk_axx_pass           = trim(addslashes($_REQUEST["user_pass"]));
      
      $sql = " select t1.*
               FROM workers t1
               WHERE
               t1.wrk_status              > 0 and
               REPLACE(t1.wrk_rut,'.','') like '{$_REQUEST["user_login"]}%' and
               t1.wrk_axx_pass            = '{$wrk_axx_pass}' and
               t1.wrk_axx_pass            != ''";
      $worker = $CON->select($sql);
      $worker = $worker[0];

      if((int)$worker["id"])
      {
         $_SESSION["wrk_id"]          = $worker["id"];
         $_SESSION["wrk_firstname"]   = $worker["wrk_firstname"];
         $_SESSION["wrk_lastname"]    = $worker["wrk_lastname"];
         $_SESSION["user_id"]         = $worker["wrk_uid"];
         $_SESSION["wrk_alldata"]     = $worker;

         $sql = " select *
                  from plantas
                  where
                  id = {$_REQUEST["user_planta_id"]}";
         $planta = $CON->select($sql);
         $_SESSION["user_planta_id"]        = (int)$_REQUEST["user_planta_id"];
         $_SESSION["planta_name"]           = $planta[0]["planta_name"];
         $_SESSION["user_company_id"]       = (int)$_REQUEST["user_company_id"];

         ?>
         <script language="JavaScript">
         location.href = '/prodwrk.php?ruid=<?=md5(microtime())?>';
         </script>
         <?php
      }
   }
   else
   {
      $_REQUEST["user_pass"] = md5($_REQUEST["user_pass"]);
      $_REQUEST["user_login"] = mysql_real_escape_string(trim(addslashes($_REQUEST["user_login"])), $CON->CMYSQL_CON);

      // select userdata for submitted credentials
      $sql = " select t1.*
               from user t1
               INNER JOIN user_companies t2 ON t1.id = t2.user_id
               where
               t1.user_login  = '{$_REQUEST["user_login"]}' and
               t1.user_pass   = '{$_REQUEST["user_pass"]}' and
               t1.user_status = 1 and
               t2.company_id  = {$_REQUEST["user_company_id"]}";
      $usrlogin = $CON->select($sql);
      $usrlogin = $usrlogin[0];

      $dellogin = true;
      if((int)$usrlogin["id"])
      {
         if((int)$usrlogin["user_appmode_{$_REQUEST["appmode"]}"])
            $dellogin = false;
      }
      if($dellogin)
         unset($usrlogin);

      //----------------------------------------------------------------------------------
      // if user was found, register to session
      //----------------------------------------------------------------------------------
      if($usrlogin["id"] != "")
      {
         // register user data
         $_SESSION["user_id"]          = $usrlogin["id"];
         $_SESSION["user_firstname"]   = $usrlogin["user_firstname"];
         $_SESSION["user_lastname"]    = $usrlogin["user_lastname"];
         $_SESSION["user_name"]        = $usrlogin["user_login"];
         $_SESSION["user_type"]        = $usrlogin["user_type"];
         $_SESSION["user_mail"]        = $usrlogin["user_mail"];
         $_SESSION["user_printer_name"] = $usrlogin["user_printer_name"];
         $_SESSION["user_printer_mode"] = $usrlogin["user_printer_mode"];
         $_SESSION["user_printer_port"] = $usrlogin["user_printer_port"];
         $_SESSION["user_code"]         = $usrlogin["user_code"];
         $_SESSION["user_login"]        = time();
         $_SESSION["user_sidepanel_active"] = (int)$usrlogin["user_sidepanel_active"];
         $_SESSION["jschk_obitpanel"]       = (int)$usrlogin["user_sidepanel_login"];
         $_SESSION["user_pricesell_perm"]   = (int)$usrlogin["user_pricesell_perm"];
         $_SESSION["user_pricebuy_perm"]    = (int)$usrlogin["user_pricebuy_perm"];
         $_SESSION["user_docopen_perm"]     = (int)$usrlogin["user_docopen_perm"];
         $_SESSION["user_company_id"]       = (int)$_REQUEST["user_company_id"];
         $_SESSION["menu_appmode"]          = (int)$_REQUEST["appmode"];
         
         $_SESSION["user_appmode_0"]        = (int)$usrlogin["user_appmode_0"];
         $_SESSION["user_appmode_1"]        = (int)$usrlogin["user_appmode_1"];
         $_SESSION["user_appmode_2"]        = (int)$usrlogin["user_appmode_2"];
         $_SESSION["user_appmode_3"]        = (int)$usrlogin["user_appmode_3"];
         $_SESSION["user_appmode_4"]        = (int)$usrlogin["user_appmode_4"];

         $sql = " select *
                  from plantas
                  where
                  id = {$_REQUEST["user_planta_id"]}";
         $planta = $CON->select($sql);
         $_SESSION["user_planta_id"]        = (int)$_REQUEST["user_planta_id"];
         $_SESSION["planta_name"]           = $planta[0]["planta_name"];

         // get groups for this user
         $sql = " select t1.group_id
                  from user_group t1, `group` t2
                  where
                  t1.user_id = {$_SESSION["user_id"]} and
                  t1.group_id = t2.id and
                  t2.group_status = 1";
         $usergroups = $CON->select($sql);

         // register user groups
         $_SESSION["user_groups"] = Array();
         for($x = 0; $x < count($usergroups) && $usergroups != false; $x++)
            array_push($_SESSION["user_groups"], $usergroups[$x]["group_id"]);

         ?>
         <script language="JavaScript">
         location.href = 'index.php?ruid=<?=md5(microtime())?>';
         </script>
         <?php
      }
   }
}

if($_REQUEST["loginfailed"] == "1")
   $savemsg = "<b>{$_LANG["MODULE"]["LOGIN"][5]}</b>";

//----------------------------------------------------------------------------------
// print login formular
//----------------------------------------------------------------------------------
if(!$nodialog)
{
   $sql = " select *
            from company_data
            where
            company_status = 1
            order by company_short";
   $companies = $CON->select($sql);
   ?>
   <style>
      .datarowmainovw
      {
         display:inline-block;
         border:0px solid #FFFFFF;
         height:60px;
         width:78px;
         box-shadow: 0px 0px 8px 5px rgba(0,0,0,0.12);
         cursor:pointer;
      }
   </style>
   <style>
      .boxlogin
      {
         height:410px !important;
         width: 560px !important;
      }
   </style>
   <script language="JavaScript">
      function resetIconColors()
      {
         $('.datarowmainovw').each(function()
         {
            $(this).css({'background-color':'#F9F9F9'});
         });
         $('.datarowmain_sub').each(function()
         {
            $(this).css({'color':'#666666'});
         });
         $('.fa-fw').each(function()
         {
            $(this).css({'color':'#666666'});
         });
      }
      function setIconOption(apid)
      {
         $('#menu_appmode').val(apid);
         $('.ovw' +apid).animate({'background-color':'#00A9A6'}, 200);
         $('.sub' +apid).animate({'color':'#FFFFFF'}, 200);
         $('.icon' +apid).animate({'color':'#FFFFFF'}, 200);
      }
   </script>
   <link href="./libs/css/font-awesome/css/font-awesome.min.css" rel="stylesheet">
   <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
   <tr>
      <td align="center" valign="top" height="100%">
         <form action="index.php" method="post" class="fokusfirst" name="xform_login" target="_parent"
         onsubmit="return checkform(new Array(this.user_login, this.user_pass, this.user_company_id))">
         <input type="hidden" name="exec" value="login">
         <input type="hidden" name="mid" value="99999">
         <input type="hidden" name="appmode" id="menu_appmode" value="0">
         <?=Nifty_printH("boxlogin", "560")?>
         
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_tbl_header"><?=$_SESSION["_CONF"]["conf_title"]?></td>
         </tr>
         </table>
         <div style="position:absolute; top: 50px;left: 160px" class="boxloginhead"><img src="./images/layout/logov2.png" width="215"></div>

         <div style="position:absolute; top: 130px;left: 60px">
            <?php
            $xx = 0;
            foreach($_MAIN AS $mainitem)
            {  ?>
               <div class="datarows datarowmainovw ovw<?=$mainitem["APID"]?>" style="background-color:<?=$mainitem["SUBN"]?>;margin-top:10px;margin-bottom:10px;margin-right:10px"
               onclick="<?=$mainitem["EXEC"]?>">
                  <table border="0" cellpadding="0" cellspacing="0" width="75" style="table-layout:fixed">
                  <tr>
                     <td align="center" height="60" valign="middle">
                        <div style="position:relative">
                           <div style="font-family:Arial;font-size:12px;position:absolute;<?=$mainitem["DIVC"]?>" class="datarowmain_sub sub<?=$mainitem["APID"]?>">
                              <?=$mainitem["NAME"]?>
                           </div>
                        </div>
                        <div style="position:absolute;margin-top:-25px;margin-left:11px">
                           <?=$mainitem["ICON"]?>
                        </div>
                     </td>
                  </tr>
                  </table>
               </div>
               <?php
               $xx++;
            }
            ?>
         </div>
         
         <div style="position:absolute; top: 220px;left: 60px;font-size:12px;width:445px;line-height:30px">
            <span style="float:left"><?=$_LANG["MODULE"]["LOGIN"][2]?></span>
            <span style="float:right">
               <input name="user_login" id="user_login" type="text" class="text" style="width:330px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </span>
            <br>
            <span style="float:left"><?=$_LANG["MODULE"]["LOGIN"][3]?></span>
            <span style="float:right">
               <input name="user_pass" type="password" class="text" style="width:330px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </span>
            <br>
            <span style="float:left">Empresa</span>
            <span style="float:right">
               <select class="text" name="user_company_id" style="width:330px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  foreach($companies AS $company)
                  {  ?>
                     <option value="<?=$company["id"]?>">
                        <?=$company["company_short"]?>
                     </option><?php
                  }
                  ?>
               </select>
            </span>
            <br>
            <div id="idx_planta_opts" style="display:none">
            <span style="float:left">Planta</span>
            <span style="float:right">
               <select class="text" name="user_planta_id" style="width:330px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  $sql = " select *
                           from plantas
                           where
                           planta_status = 1
                           order by planta_name";
                  $plantas = $CON->select($sql);
                  
                  foreach($plantas AS $planta)
                  {  ?>
                     <option value="<?=$planta["id"]?>">
                        <?=$planta["planta_name"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </span>
            <br>
            </div>
            <div style="height:10px"></div>
            <?php
            printButton($_LANG["FORM"]["BUTTON"][4], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_login)", "tick-circle-frame", 445);
            ?>
            <input type='submit' value='' style='width:1px;height:1px;border:0px;background-color:#333333;opacity:0.05'>
            
         </div>

         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   </table>
   <?php
}