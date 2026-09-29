<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

// echo($_SESSION["user_id"]);

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "show")
{
   require_once("./libs/modules/messaging/show.php");
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
   }

   if($_REQUEST["filter"] == "")
      $_REQUEST["filter"] = "incoming";

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["MSG"][0]?></b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
   <colgroup>
      <col width="140" valign="top">
      <col>
      <col width="828" valign="top">
   </colgroup>
   <tr>
      <td valign="top">
         <?=Nifty_printH("box1", "100%")?>
         <table cellpadding="2" cellspacing="0" class="content_table" width="100%">
         <tr>
            <td class="content_tbl_header">
               <b><?=$_LANG["MODULE"]["MSG"][1]?></b>
            </td>
         </tr>
         <tr>
            <td class="<?php if($_REQUEST["filter"] == "incoming") echo "content_row_select_active"; else echo "content_row_select"?>"
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&filter=incoming'"
            onmouseover="mark(this,0)" onmouseout="mark(this,1)">
               &nbsp;<img src="./images/content/msg_incoming.gif"> <?=$_LANG["MODULE"]["MSG"][2]?>
            </td>
         </tr>
         <tr>
            <td class="<?php if($_REQUEST["filter"] == "outgoing") echo "content_row_select_active"; else echo "content_row_select"?>"
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&filter=outgoing'"
            onmouseover="mark(this,0)" onmouseout="mark(this,1)">
               &nbsp;<img src="./images/content/msg_outgoing.gif"> <?=$_LANG["MODULE"]["MSG"][3]?>
            </td>
         </tr>
         <tr>
            <td class="<?php if($_REQUEST["filter"] == "trash") echo "content_row_select_active"; else echo "content_row_select"?>"
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&filter=trash'"
            onmouseover="mark(this,0)" onmouseout="mark(this,1)">
               &nbsp;<img src="./images/content/msg_trash.gif"> <?=$_LANG["MODULE"]["MSG"][4]?>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
      </td>
      <td></td>
      <td valign="top">
         <form action="index.php" method="post" id="idx_inboxform" name="idx_inboxform">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="filter" value="<?=$_REQUEST["filter"]?>">
          <?=Nifty_printH("box1", "100%", 0)?>
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col>
                  <col width="600">
                  <col>
               </colgroup>
               <tr>
                  <td class="content_rowl">Buscar</td>
                  <td class="content_row" style="text-align:right;">
                     <input type="text" class="text" style="width:600px;" name="sql_texto" id="sql_texto"
                              value="<?=isset($_REQUEST["sql_texto"]) ? $_REQUEST["sql_texto"] : ''?>"
                              onfocus="markfield(this,0)" onblur="markfield(this,1)">

                  </td>
                  <td align="center">
                     <!-- <input type="submit" value="Buscar" class="button"> -->
                     <?php
                       printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_inboxform)", "magnifier", 130);
                     ?>

                  </td>
               </tr>
            </table>
         </form>
         <?=Nifty_printF()?>
         <br>
         <?=Nifty_printH("box1", "100%")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         
         <colgroup>
            <col width="30">
            <col width="26">
            <col>
            <col>
            <col width="100">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6">
               <?=$_LANG["MODULE"]["MSG"][16]?>
               <?php
               if($_REQUEST["filter"] == "incoming")
                  echo $_LANG["MODULE"]["MSG"][2];
               elseif($_REQUEST["filter"] == "outgoing")
                  echo $_LANG["MODULE"]["MSG"][3];
               elseif($_REQUEST["filter"] == "trash")
                  echo $_LANG["MODULE"]["MSG"][4];
               ?>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">
               <input type="checkbox" onclick="markSelBoxes(document.all.idx_inboxform, 'msgid[]', this)">
            </td>
            <td class="content_tbl_subheader">&nbsp;</td>
            <?php
            if($_REQUEST["filter"] == "outgoing")
            {  ?>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["MSG"][5]?></td>
               <?php
            }
            else
            {  ?>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["MSG"][6]?></td>
               <?php
            }
            ?>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["MSG"][7]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["MSG"][8]?></td>
            <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["MSG"][9]?></td>
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

         if($_REQUEST["sql_texto"] != "")
            $sql .= "and ( t1.msg_header LIKE CONCAT('%', '{$_REQUEST['sql_texto']}', '%') OR
                           t1.msg_body   LIKE CONCAT('%', '{$_REQUEST['sql_texto']}', '%') OR
                           t3.user_firstname LIKE CONCAT('%', '{$_REQUEST['sql_texto']}', '%') OR
                           t3.user_lastname  LIKE CONCAT('%', '{$_REQUEST['sql_texto']}', '%')
                         )";
         
         $sql .= " order by t1.msg_crtdat desc ";
         $messages = $CON->select($sql);
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($messages) && $messages != false; $x++)
         {
            $_SESSION[$_sesmodulename]["FLW"][$messages[$x]["id"]]["L"] = (int)$messages[($x -1)]["id"];
            $_SESSION[$_sesmodulename]["FLW"][$messages[$x]["id"]]["N"] = (int)$messages[($x +1)]["id"];

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
               $msguserstr = "{$messages[$x]["user_firstname"]} {$messages[$x]["user_lastname"]}";

            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row">
                  <input type="checkbox" name="msgid[]" value="<?=$messages[$x]["id"]?>-<?=$messages[$x]["msg_type"]?>">
               </td>
               <td class="content_row"><img src="./images/content/<?=$img_str?>"></td>
               <td class="content_row"><?=$font_prefix?><?=$msguserstr?><?=$font_suffix?></td>
               <td class="content_row"><?=$font_prefix?><?=$messages[$x]["msg_header"]?><?=$font_suffix?></td>
               <td class="content_row"><?=$font_prefix?><?=displayDate($messages[$x]["msg_crtdat"])?><?=$font_suffix?></td>
               <td class="content_row" align="center">
                  <ul class="postnav">
                     <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=show&msgid=<?=$messages[$x]["id"]?>&type=<?=$messages[$x]["msg_type"]?>&filter=<?=$_REQUEST["filter"]?>"><?=$_LANG["FORM"]["BUTTON"][5]?></a>
                  </ul>
               </td>
            </tr>
            <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="6" align="center" height="30">
                  <b class="msg_save_err"><?=$_LANG["MODULE"]["MSG"][10]?></b>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <?php
         //----------------------------------------------------------------------------------
         if($x)
         {  ?>
            <br>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td>
                  <b class="content_message"><?=$_LANG["MODULE"]["MSG"][11]?></b>
                  <select class="text" name="msgexec" onchange="if(this.value != '' && confirm('<?=$_LANG["FORM"]["MESSAGE"][3]?>')) document.all.idx_inboxform.submit()">
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
         }  ?>
      </td>
   </tr>
   </table>
   <?php
}