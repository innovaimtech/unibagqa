<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_REQUEST["MSGNEW_LIMIT"] = 1;
if($_SESSION["_INITMODE"] == "RRHH" || $_SESSION["_INITMODE"] == "RRHH2" || $_SESSION["_INITMODE"] == "RRHH3" || $_SESSION["_INITMODE"] == "TEMAS Y ROLES")
   $_REQUEST["MSGNEW_LIMIT"] = 0;
     

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "show")
{
   require_once("./libs.prodwrk/modules/ad_messaging/show.php");
}
elseif($_REQUEST["exec"] == "new")
{
   require_once("./libs.prodwrk/modules/ad_messaging/new.php");
}
//----------------------------------------------------------------------------------
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["msgexec"] != "" && is_array($_REQUEST["msgid"]))
   {
      if($_REQUEST["msgexec"] == "UNSEEN")
         $upd_stat = "0";
      elseif($_REQUEST["msgexec"] == "SEEN")
         $upd_stat = "1";
      elseif($_REQUEST["msgexec"] == "TRASH")
         $upd_stat = "2";
      elseif($_REQUEST["msgexec"] == "DELETE")
         $upd_stat = "3";

      foreach($_REQUEST["msgid"] AS $idx)
      {
         $row = explode("-", $idx);
      
         $sql = " update message_users
                  set
                  msg_status = {$upd_stat}
                  where
                  user_id  = {$_SESSION["user_id"]} and
                  msg_id   = {$row[0]} and
                  msg_type = {$row[1]}";
         $CON->no_result($sql);
      }
      $_SESSION["JSEXEC"] .= ";top.document.getElementById('idx_main_menu').contentWindow.loadMainMsgData('{$_SESSION["user_id"]}');";
   }
   if($_REQUEST["filter"] == "")
      $_REQUEST["filter"] = "incoming";

   //----------------------------------------------------------------------------------
   //

   $cssinc = "cursor:pointer;border:2px solid #115452;background-color:#FFFFFF;font-size:14px;color:#3F4A5B;";
   $icninc = "color:#3F4A5B;";
   $cssout = "cursor:pointer;border:2px solid #115452;background-color:#FFFFFF;font-size:14px;color:#3F4A5B;";
   $icnout = "color:#3F4A5B;";
   $csspap = "cursor:pointer;border:2px solid #115452;background-color:#FFFFFF;font-size:14px;color:#3F4A5B;";
   $icnpap = "color:#3F4A5B;";
   if($_REQUEST["filter"] == "incoming")
   {
      $cssinc = "cursor:pointer;border:2px solid #115452;background-color:#0E7572;font-size:14px;color:#FFFFFF;";
      $icninc = "color:#FFFFFF;";
   }
   elseif($_REQUEST["filter"] == "outgoing")
   {
      $cssout = "cursor:pointer;border:2px solid #115452;background-color:#0E7572;font-size:14px;color:#FFFFFF;";
      $icnout = "color:#FFFFFF;";
   }
   elseif($_REQUEST["filter"] == "trash")
   {
      $csspap = "cursor:pointer;border:2px solid #115452;background-color:#0E7572;font-size:14px;color:#FFFFFF;";
      $icnpap = "color:#FFFFFF;";
   }
   ?>
   <input type="hidden" id="exec" name="exec" value="<?=$_REQUEST["exec"]?>">
   <table cellpadding="0" border="0" cellspacing="0" width="100%" class="content_table" style="padding-top:10px;padding-left:10px;padding-right:10px">
   <colgroup>
      <col width="140">
      <col width="10">
      <col>
   </colgroup>
   <tr>
      <td valign="top">
         <table cellpadding="5" cellspacing="0" border="0" width="100%">
         <tr>
            <td height="95" style="<?=$cssinc?>;border-radius:5px"
            onclick="location.href='prodwrk.php?mid=999&filter=incoming'"
            onmouseover="markMenu($(this),1)" onmouseout="markMenu($(this),0)">
               <span style="margin-left:35px"><i class="fa fa-envelope" style="<?=$icninc?>font-size:52px"></i></span><br>
               <div style="text-align:center">RECIBIDOS</div>
            </td>
         </tr>
         <tr>
            <td><img src="./images/content/pixel.gif" height="1"></td>
         </tr>
         <tr>
            <td height="95" style="<?=$cssout?>;border-radius:5px"
            onclick="location.href='prodwrk.php?mid=999&filter=outgoing'"
            onmouseover="markMenu($(this),1)" onmouseout="markMenu($(this),0)">
               <span style="margin-left:35px"><i class="fa fa-paper-plane" style="<?=$icnout?>font-size:52px"></i></span><br>
               <div style="text-align:center">ENVIADOS</div>
            </td>
         </tr>
         <tr>
            <td><img src="./images/content/pixel.gif" height="1"></td>
         </tr>
         <tr>
            <td height="95" style="<?=$csspap?>;border-radius:5px"
            onclick="location.href='prodwrk.php?mid=999&filter=trash'"
            onmouseover="markMenu($(this),1)" onmouseout="markMenu($(this),0)">
               <span style="margin-left:42px"><i class="fa fa-trash" style="<?=$icnpap?>font-size:52px"></i></span><br>
               <div style="text-align:center">PAPELERA</div>
            </td>
         </tr>
         </table>
      </td>
      <td></td>
      <td valign="top">
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
            <table border="0" cellpadding="3" cellspacing="0" width="100%" class="aula_listtbl" style="margin-top:0px">
            <form action="prodwrk.php" method="post" id="idx_inboxform" style="margin:0px;padding:0px">
            <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
            <input type="hidden" name="filter" value="<?=$_REQUEST["filter"]?>">
            <colgroup>
               <col width="30">
               <col width="26">
               <col>
               <col>
               <col width="140">
               <col width="140">
            </colgroup>
            <tr>
               <td class="tdheader">
                  <input type="checkbox" onclick="markSelBoxes(document.all.idx_inboxform, 'msgid[]', this)">
               </td>
               <td class="tdheader">&nbsp;</td>
               <?php
               if($_REQUEST["filter"] == "outgoing")
               {  ?>
                  <td class="tdheader"><?=$_LANG["MODULE"]["MSG"][5]?></td>
                  <?php
               }
               else
               {  ?>
                  <td class="tdheader"><?=$_LANG["MODULE"]["MSG"][6]?></td>
                  <?php
               }
               ?>
               <td class="tdheader"><?=$_LANG["MODULE"]["MSG"][7]?></td>
               <td class="tdheader"><?=$_LANG["MODULE"]["MSG"][8]?></td>
               <td class="tdheader" align="center"><?=$_LANG["MODULE"]["MSG"][9]?></td>
            </tr>
            <?php
            //----------------------------------------------------------------------------------
            if($_REQUEST["filter"] == "incoming")
            {
               $sql_msgtype   = "0";
               $sql_status    = "0,1";
            }
            elseif($_REQUEST["filter"] == "outgoing")
            {
               $sql_msgtype   = "1";
               $sql_status    = "0,1";
            }
            elseif($_REQUEST["filter"] == "trash")
            {
               $sql_msgtype   = "0,1";
               $sql_status    = "2";
            }

            //----------------------------------------------------------------------------------
            $sql = " select t1.*, t3.user_firstname, t3.user_lastname, t2.msg_status, t2.msg_type
                     from message_data t1, message_users t2, user t3
                     where
                     t1.id = t2.msg_id and
                     t1.msg_crtusr = t3.id and
                     t2.user_id = {$_SESSION["user_id"]} ";
      
            if($sql_msgtype != "")
               $sql .= " and t2.msg_type IN ({$sql_msgtype}) ";
      
            if($sql_status != "")
               $sql .= " and t2.msg_status IN ({$sql_status}) ";
            
            $sql .= " order by t1.msg_crtdat desc ";
            
            $messages = $CON->select($sql);

            //----------------------------------------------------------------------------------
            for($x = 0; $x < count($messages) && $messages != false; $x++)
            {
               $bgcolor = "#FFFFFF";
               if(($x +1) % 2 == 0)
                  $bgcolor = "#EEEEEE";
               if((int)$messages[$x]["msg_status"] == 0)
               {
                  $font_prefix = "<b>";
                  $font_suffix = "</b>";
               }
               else
               {
                  $font_prefix = "";
                  $font_suffix = "";
               }

               if($messages[$x]["msg_type"] == "0")
                  $img_str = "msg_incoming.gif";
               elseif($messages[$x]["msg_type"] == "1")
                  $img_str = "msg_outgoing.gif";

               $msguserstr = "";
               if($_REQUEST["filter"] == "outgoing")
               {
                  $sql = " select t2.user_firstname, t2.user_lastname
                           from message_users t1, user t2
                           where
                           t1.msg_id = {$messages[$x]["id"]} and
                           t1.msg_type = 0 and
                           t1.user_id = t2.id
                           order by t2.user_firstname, t2.user_lastname";
                  $msgusers = $CON->select($sql);
                  
                  for($y = 0; $y < count($msgusers) && $msgusers != false; $y++)
                     $msguserstr .= "{$msgusers[$y]["user_firstname"]} {$msgusers[$y]["user_lastname"]}, ";
      
                  $msguserstr = substr($msguserstr, 0, -2);
               }
               else
               {
                  $msguserstr = "{$messages[$x]["user_firstname"]} {$messages[$x]["user_lastname"]}";
//                   if(trim($msguserstr) == "")
//                      $msguserstr = "{$_SESSION["wrk_firstname"]} {$_SESSION["wrk_lastname"]}";
               }

               //----------------------------------------------------------------------------------
               ?>
               <tr bgcolor="<?=$bgcolor?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os">
                     <input type="checkbox" name="msgid[]" value="<?=$messages[$x]["id"]?>-<?=$messages[$x]["msg_type"]?>">
                  </td>
                  <td class="content_row_os"><img src="./images/content/<?=$img_str?>"></td>
                  <td class="content_row_os">
                  <?php
                     $numeric = strlen($font_prefix." ".$msguserstr." ".$font_suffix);
                     $string  = $font_prefix." ".$msguserstr." ".$font_suffix;
                     
                     if((int)$numeric > 50 )
                        echo substr($string, 0 , 50)."...";
                     else
                        echo $string;
                  ?>
                  </td>
                  <td class="content_row_os"><?=$font_prefix?><?=$messages[$x]["msg_header"]?><?=$font_suffix?></td>
                  <td class="content_row_os"><?=$font_prefix?><?=displayDate($messages[$x]["msg_crtdat"])?><?=$font_suffix?></td>
                  <td class="content_row_os" align="center" width="120">
                     <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=999&exec=show&msgid=<?=$messages[$x]["id"]?>&type=<?=$messages[$x]["msg_type"]?>&filter=<?=$_REQUEST["filter"]?>';">
                        <i class="fa fa-fw fa-angle-right" style="color:white;"></i> Mostrar&nbsp;
                     </div>
                  </td>
               </tr>
               <?php
            }
            if(!$x)
            {  ?>
               <tr bgcolor="">
                  <td class="content_row_os" colspan="6" align="center" height="50">
                     <b class="msg_save_err"><?=$_LANG["MODULE"]["MSG"][10]?></b>
                  </td>
               </tr>
               <?php
            }
            ?>
            </table>
            <?php
            //----------------------------------------------------------------------------------
            if($x)
            {  ?>
               <br>
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td class="content_row_clear">
                     <b class="content_message"><?=$_LANG["MODULE"]["MSG"][11]?></b>
                     <select class="inptxt" name="msgexec" onchange="if(this.value != '') document.all.idx_inboxform.submit()"
                     style="background-color:#FFFFFF">
                        <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <option value="">- - - - - - - - - - - - - - - - - - - - -</option>
                        <option value="SEEN"><?=$_LANG["MODULE"]["MSG"][12]?></option>
                        <option value="UNSEEN"><?=$_LANG["MODULE"]["MSG"][13]?></option>
                        <option value="">- - - - - - - - - - - - - - - - - - - - -</option>
                        <?php
                        if($_REQUEST["filter"] != "trash")
                        {  ?>
                           <option value="TRASH"><?=$_LANG["MODULE"]["MSG"][14]?></option>
                           <?php
                        }
                        if($_REQUEST["filter"] == "trash")
                        {  ?>
                           <option value="DELETE"><?=$_LANG["MODULE"]["MSG"][15]?></option>
                           <?php
                        }
                        ?>
                     </select>
                  </td>
               </tr>
               </table>
               <?php
            }
            ?>
            </form>
            </td>
         </tr>
         </table>
            
         
      </td>
   </tr>
   </table>
   <?php
}
?>
<script language="JavaScript">
   //top.frames['idx_main_menu'].menuDetectNotifies();
</script>
<script language="JavaScript">
setTimeout(function()
{
   top.frames['idx_main_menu'].resetTimer();
}, 1000);
</script>
<?php