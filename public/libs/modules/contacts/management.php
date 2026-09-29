<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       30.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// set default section
//----------------------------------------------------------------------------------
if($_REQUEST["selchar"] == "")
   $_REQUEST["selchar"] = "%";

//----------------------------------------------------------------------------------
// create groupstring from session
//----------------------------------------------------------------------------------
for($x = 0; $x < count($_SESSION["user_groups"]); $x++)
      $grpstr .= "{$_SESSION["user_groups"][$x]},";
   $grpstr = substr($grpstr, 0, -1);

//----------------------------------------------------------------------------------
// Delete contact
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete" && $_REQUEST["con_id"] != "")
{
   $sql = " delete from user_contacts
            where
            id          = {$_REQUEST["con_id"]} and
            user_crtusr = {$_SESSION["user_id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
// load edit / create formular
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "add" || $_REQUEST["exec"] == "edit")
{
   require_once("add.php");
}

//----------------------------------------------------------------------------------
// print contact overview
//----------------------------------------------------------------------------------
else
{
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CON"][0]?></b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="822">
   <tr>
      <td class="content_tbl_header">
         <?php
         //----------------------------------------------------------------------------------
         // Button "NEW"
         //----------------------------------------------------------------------------------
         ?>
         <input type="button" style="width:85px" value="<?=$_LANG["MODULE"]["CON"][1]?>"
         class="button" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selchar=<?=$_REQUEST["selchar"]?>&exec=add'">

         <?php
         //----------------------------------------------------------------------------------
         // Button "ALL"
         //----------------------------------------------------------------------------------
         ?>
         <input type="button" name="selchar" style="width:30px" value="ALL"
         <?php
         if($_REQUEST["selchar"] == "%")
            echo 'class="buttonactive"';
         else
            echo 'class="button" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"';
         ?>
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selchar=%'">
         
         <?php
         //----------------------------------------------------------------------------------
         // Buttons A-Z
         //----------------------------------------------------------------------------------
         for ($x = 65; $x < 91; $x++)
         {
            $thischar = chr($x);
            if($_REQUEST["selchar"] == $thischar)
               $buttonaddon = 'class="buttonactive"';
            else
               $buttonaddon = 'class="button" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"';
            ?>
            <input type="button" name="selchar" style="width:21px" value="<?=$thischar?>"
            <?=$buttonaddon?>
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selchar=' +this.value">
            <?php
         }

         //----------------------------------------------------------------------------------
         // Button "0-9"
         //----------------------------------------------------------------------------------
         ?>
         <input type="button" name="selchar" style="width:26px" value="0-9"
         <?php
         if($_REQUEST["selchar"] == "0-9")
            echo 'class="buttonactive"';
         else
            echo 'class="button" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"';
         ?>
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selchar=' +this.value">

         <?php
         //----------------------------------------------------------------------------------
         // Button "All others"
         //----------------------------------------------------------------------------------
         ?>
         <input type="button" name="selchar" style="width:26px" value="..."
         <?php
         if($_REQUEST["selchar"] == "...")
            echo 'class="buttonactive"';
         else
            echo 'class="button" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"';
         ?>
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selchar=' +this.value">
      </td>
   </tr>
   </table>
   <br>
   <?php
   //----------------------------------------------------------------------------------
   // Create SQL-Statements for selection filter
   //----------------------------------------------------------------------------------
   if($_REQUEST["selchar"] == "0-9" || $_REQUEST["selchar"] == "...")
   {
      $sqladdon = "  user_lastname like '0%' or user_lastname like '1%' or
                     user_lastname like '2%' or user_lastname like '3%' or
                     user_lastname like '4%' or user_lastname like '5%' or
                     user_lastname like '6%' or user_lastname like '7%' or
                     user_lastname like '8%' or user_lastname like '9%' ";

      if($_REQUEST["selchar"] == "...")
      {
         $sqladdon .= " or
                        user_lastname like 'A%' or user_lastname like 'Q%' or
                        user_lastname like 'B%' or user_lastname like 'R%' or
                        user_lastname like 'C%' or user_lastname like 'S%' or
                        user_lastname like 'D%' or user_lastname like 'T%' or
                        user_lastname like 'E%' or user_lastname like 'U%' or
                        user_lastname like 'F%' or user_lastname like 'V%' or
                        user_lastname like 'G%' or user_lastname like 'W%' or
                        user_lastname like 'H%' or user_lastname like 'X%' or
                        user_lastname like 'I%' or user_lastname like 'Y%' or
                        user_lastname like 'J%' or user_lastname like 'Z%' or
                        user_lastname like 'K%' or
                        user_lastname like 'L%' or
                        user_lastname like 'M%' or
                        user_lastname like 'N%' or
                        user_lastname like 'O%' or
                        user_lastname like 'P%' ";
         $sqladdon = " !( {$sqladdon} ) ";
      }
   }
   else
   {
      $sqladdon = "  user_lastname like '{$_REQUEST["selchar"]}%'";
   }

   //----------------------------------------------------------------------------------
   // Select user contacts and system contacts
   //----------------------------------------------------------------------------------
   $sql = " select id, user_firstname, user_lastname, user_crtusr, user_crtdat
                   user_updusr, user_upddat, user_mail, user_street,
                   user_postcode, user_city, user_telephone, user_cellphone,
                   user_internet, 'user_editable' '1'
            from user_contacts
            where
            (
               user_crtusr = {$_SESSION["user_id"]} or
               user_public = 1
            ) and
            ({$sqladdon})
            UNION ALL
            select id, user_firstname, user_lastname, user_crtusr, user_crtdat
                   user_updusr, user_upddat, user_mail, user_street,
                   user_postcode, user_city, user_telephone, user_cellphone,
                   user_internet, 'user_editable' '0'
            from user
            where
            id != {$_SESSION["user_id"]} and
            ({$sqladdon}) ";
            
   if($_SESSION["user_type"] != 1)
      $sql .= " and user_visible = 1 ";
   
   $sql .= " order by user_lastname, user_firstname";
   $contacts = $CON->select($sql);


   //----------------------------------------------------------------------------------
   // Display contacts
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
   <?php
   for($x = 0; $x < count($contacts) && $contacts != false; $x++)
   {
      if(strtolower(substr($contacts[$x]["user_internet"],0,4)) != "http" && trim($contacts[$x]["user_internet"]) != "")
         $contacts[$x]["user_internet"] = "http://{$contacts[$x]["user_internet"]}";

      if($x % 3 == 0)
         $align = "left";
      elseif($x % 3 == 1)
         $align = "center";
      else
         $align = "right";
      ?>
      <td valign="top" align="<?=$align?>">
         <?=Nifty_printH("box1", "265")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="table-layout:fixed">
         <colgroup>
            <col width="70">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="2">
               <img src="./images/content/contact.gif">&nbsp;
               <?=$contacts[$x]["user_lastname"]?>, <?=$contacts[$x]["user_firstname"]?>
            </td>
         </tr>
         <tr>
            <td class="content_row" valign="top"><?=$_LANG["MODULE"]["CON"][2]?></td>
            <td class="content_row">
               <?php
               if($contacts[$x]["user_editable"] == "user_editable0")
                  echo "<b class='msg_save_err'>{$_LANG["MODULE"]["CON"][23]}</b>";
               else
                  echo "<b class='msg_save_ok'>{$_LANG["MODULE"]["CON"][24]}</b>";
               ?>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["CON"][3]?></u></td>
            <td class="content_row"><?=$contacts[$x]["user_telephone"]?>&nbsp;</td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["CON"][4]?></td>
            <td class="content_row"><?=$contacts[$x]["user_cellphone"]?>&nbsp;</td>
         </tr>
         <tr>
            <td class="content_row" valign="top"><?=$_LANG["MODULE"]["CON"][7]?></td>
            <td class="content_row">
               <?=$contacts[$x]["user_street"]?>&nbsp;
               <br>
               <?=$contacts[$x]["user_postcode"]?> <?=$contacts[$x]["user_city"]?>&nbsp;
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["CON"][5]?></td>
            <td class="content_row">
               <?php
               if($contacts[$x]["user_mail"] != "")
               {  ?>
                  <a class="link" href="mailto:<?=$contacts[$x]["user_mail"]?>"><?=$contacts[$x]["user_mail"]?></a>
                  <?php
               }
               else
               {  ?>
                  <?=$contacts[$x]["user_mail"]?>&nbsp;
                  <?php
               }  ?>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["CON"][6]?></td>
            <td class="content_row">
               <?php
               if($contacts[$x]["user_internet"] != "")
               {  ?>
                  <a class="link" target="_blank" href="<?=$contacts[$x]["user_internet"]?>"><?=$contacts[$x]["user_internet"]?></a>
                  <?php
               }
               else
               {  ?>
                  <?=$contacts[$x]["user_internet"]?>&nbsp;
                  <?php
               }  ?>
            </td>
         </tr>
         <tr>
            <td class="content_row" colspan="2" align="right">
               <?php
               if($contacts[$x]["user_editable"] == "user_editable1")
               {  ?>
                  <input type="button" class="button" value="<?=$_LANG["MODULE"]["CON"][8]?>" style="width:120px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&con_id=<?=$contacts[$x]["id"]?>&selchar=<?=$_REQUEST["selchar"]?>'">
                  <?php
               }
               else
               {  ?>
                  <input type="button" class="button" value="<?=$_LANG["MODULE"]["CON"][9]?>" style="width:120px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="location.href='index.php?mid=6&con_id=<?=$contacts[$x]["id"]?>'">
                  <?php
               }  ?>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
      <?php
      if($x % 3 != 2)
      {  ?>
         <td width="13">&nbsp;</td>
         <?php
      }
      if($x % 3 == 2)
      {  ?>
         </tr>
         <tr>
         <?php
      }
   }
   if($x > 0)
   {
      for($y = $x; $y % 3 != 0; $y++)
      {  ?>
         <td width="270">&nbsp;</td>
         <?php
         if($y % 3 != 2)
         {  ?>
            <td width="13">&nbsp;</td>
            <?php
         }
      }
   }
   else
   {  ?>
      <td>
         <br>
         <b class='msg_save_err'><?=$_LANG["MODULE"]["CON"][10]?></b>
      </td>
      <?php
   }
   ?>
   </tr>
   </table>
   <?php
}