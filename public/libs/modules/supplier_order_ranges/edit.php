<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header">Configurar aprobación de ordenes de compra</b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?php
if($_REQUEST["subexec"] == "add")
{
   require_once("edit.amount.php");
}
else
{
   if($_REQUEST["clearData"] != "")
   {
      $sql = " update supplier_order_ranges
               set
               rng_status  = 0
               where
               id          = {$_REQUEST["clearData"]}";
      $CON->no_result($sql);

      $savemsg = getSaveMessage(true);
   }

   $sql = " select *
            from supplier_order_ranges
            where
            rng_status > 0
            order by rng_amt_init";
   $asps = $CON->select($sql);

   printButton("Agregar Rango", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Resumen de rangos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="left">Inicio</td>
      <td class="content_tbl_subheader" align="left">Termino</td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($asps) && $asps != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="left"><?=printPrice($asps[$x]["rng_amt_init"])?>&nbsp;</td>
         <td class="content_row" align="left"><?=printPrice($asps[$x]["rng_amt_end"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=amounts&id={$_REQUEST["id"]}&subexec=add&cid={$asps[$x]["id"]}", "", "pencil", 120);
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="3" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}