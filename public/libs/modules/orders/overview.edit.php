<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

if($_REQUEST["id"] != "")
{
   $sql = " select req_number
            from orders 
            where
            id = {$_REQUEST["id"]} ";
   $title = $CON->select($sql);

   global $_RESGLBIDS;
   getOrdersRelations($CON, $_REQUEST["id"]);
   if(count($_RESGLBIDS) > 1)
      $_HASORDERSRELS = true;
   
   $title = "Cambiar confirmación de compra: {$title[0]["req_number"]}";
}
else
   $title = "Agregar confirmación de compra:";
?>
<table border="0" cellpadding="0" cellspacing="0" width="1020">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("boxopt_t", "1020",0)?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}&user_pricesell_perm={$_REQUEST["user_pricesell_perm"]}", "", "cookies");
      ?>
   </td>
   <?php
   if((int)$userdata["user_orderabono_perm"])
   {  ?>
      <td width="20%" style="padding-right:5px">
         <?php
         if($_REQUEST["subcatexec"] == "anticipos")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Abonos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=anticipos&id={$_REQUEST["id"]}&user_pricesell_perm={$_REQUEST["user_pricesell_perm"]}", "", "money");
         ?>
      </td>
      <?php
   }
   ?>
   <td class="content_row_clear">&nbsp;</td>
   <td width="110" align="right" style="padding-right:5px">
      <?php
      $sql = "select count(1) as contador  from tran_docs where doc_tran_id = {$_REQUEST["id"]} and doc_tran_type = 'orders'";
      $contador = $CON->select($sql);
      $contador = $contador[0]["contador"];
      $anexo = "Anexos";
      if ($contador > 0) {
         $anexo .= " (".$contador.")";
      }      
         if($_REQUEST["id"] != "")
         // printButton("Anexos (".$contador.")" , "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=orders&id={$_REQUEST["id"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
         printButton(
            $anexo,
            "postnav",
            "javascript:void(0)",
            "$.fancybox({
               href: '/libs/modules/docs_management/overview.php?mode=orders&id={$_REQUEST["id"]}',
               type: 'iframe',
               width: 850,
               height: 450,
               autoSize: true,
               onClosed: function() {
                     location.reload(); // refresca la página al cerrar el modal
               }
            });",
            "scanner--plus",
            110
         );
      ?>
   </td>
   <?php
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"])
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:5px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"]}", "", "arrow-180", 53);
      ?>
      </td>
      <?php
   }
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"])
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:2px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"]}", "", "arrow", 53);
      ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "nvrels")
   require_once("data.conectednv.php");
elseif($_REQUEST["subcatexec"] == "anticipos")
   require_once("data.anticipos.php");
?>