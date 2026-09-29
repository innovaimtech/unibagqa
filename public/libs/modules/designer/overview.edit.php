<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if((int)$_REQUEST["assignuid"])
{
   $sql = " update orders
            set
            req_design_assign_uid = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["id"]} ";
   $CON->no_result($sql);
}

if((int)$_REQUEST["deleteuid"])
{
   $sql = " update orders
            set
            req_design_assign_uid = 0
            where
            id = {$_REQUEST["id"]} ";
   $CON->no_result($sql);
}

if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from orders t1
            LEFT OUTER JOIN user t2 ON t1.req_design_assign_uid = t2.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);

   $title = "Nº CC: {$invoice[0]["req_number"]}";
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="40" valign="top"><div style="height:8px"></div><b class="content_header"><?=$title?></b></td>
   <td align="center"><div style="height:8px"></div><div id="idx_status_msg"></div></td>
   <td align="right">
      <div style="display:none" id="idx_button_assign">
         <?php
         printButton("Asignar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { location.href = '/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&assignuid=1' }", "tick-circle-frame", 160);
         ?>
      </div>
      <div style="display:none" id="idx_button_assign_mio">
         <b class="msg_save_ok">Asignado a mi usuario</b>
         <input type="button" class="buttonred" value="Liberar asignación"
         onclick="if(askDel('')) { location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>&deleteuid=1' }">
      </div>
      <div style="display:none" id="idx_button_assign_other">
         <b class="msg_save_err">Asignado a: <?=$invoice[0]["user_firstname"]?> <?=$invoice[0]["user_lastname"]?></b>
         <input type="button" class="buttonred" value="Liberar asignación"
         onclick="if(askDel('')) { location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>&deleteuid=1' }">
      </div>
   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");

if($headdata["req_design_assign_uid"] == 0)
{  ?>
   <script language="Javascript">
      $(document).ready(function()
      {
         $('#idx_button_assign').show(0);
      });
   </script>
   <?php
}
elseif($headdata["req_design_assign_uid"] == $_SESSION["user_id"])
{  ?>
   <script language="Javascript">
      $(document).ready(function()
      {
         $('#idx_button_assign_mio').show(0);
      });
   </script>
   <?php
}
elseif($headdata["req_design_assign_uid"] != $_SESSION["user_id"])
{  ?>
   <script language="Javascript">
      $(document).ready(function()
      {
         $('#idx_button_assign_other').show(0);
      });
   </script>
   <?php
}