<form action="index.php" method="post" class="fokusfirst" name="xform_login"
 onsubmit="return checkuserform(new Array(this.user_pass1, this.user_pass2))">
<input type="hidden" name="exec" value="login">
<input type="hidden" name="old_user_login" value="<?=$_REQUEST["user_login"]?>">
<input type="hidden" name="old_user_pass" value="<?=$_REQUEST["user_pass"]?>">
<input type="hidden" name="user_setnewpw" value="1">
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
<tr>
   <td align="center" style="padding-top:30px" valign="top">
      <?=Nifty_printH("boxmenu", "450")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_row_clear" colspan="2">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" width="30"><img src="./images/menu/icons/cross-circle-frame.png"></td>
               <td class="content_row_clear"><b class="msg_save_err"><?=$_LANG["MODULE"]["LOGIN"][7]?></b></td>
            </tr>
            </table>
            <br><br>
            <b><?=$_LANG["MODULE"]["LOGIN"][8]?></b>
            <br><br>
         </td>
      </tr>
      <tr>
         <td class="content_row_clear"><nobr><?=$_LANG["MODULE"]["LOGIN"][3]?></nobr></td>
         <td>
            <input name="user_pass1" type="password" class="text" style="width:180px"
            onkeyup="return passwordChanged()" id="user_pass"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <span id="strength"><font style="color:red"><?=$_LANG["MODULE"]["LOGIN"][9]?></font></span>
         </td>
      </tr>
      <tr>
         <td class="content_row_clear"><nobr><?=$_LANG["MODULE"]["LOGIN"][3]?> <?=$_LANG["MODULE"]["LOGIN"][10]?></nobr></td>
         <td>
            <input name="user_pass2" type="password" class="text" style="width:180px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <table border="0" cellpadding="0" cellspacing="0" width="450">
      <tr>
         <td>&nbsp;</td>
         <td align="right" width="130">
            <ul class="postnav">
               <a href="javascript: deactivateFormChange()" onclick="submitForm(document.xform_login)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
            </ul>
         </td>
      </tr>
      </table>
      <input type='submit' value='' style='width:1px;height:1px;border:none'>
   </td>
</tr>
</table>
</form>