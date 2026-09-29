<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*
         from invoices_buy t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if((int)$_REQUEST["delcontrefid"])
{
   $sql = " delete from invoices_buy_contenedores
            where
            id = {$_REQUEST["delcontrefid"]} and
            invc_id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["addcontid"])
{
   $sql = " insert into invoices_buy_contenedores
            (invc_id, cont_id)
            VALUES
            ({$_REQUEST["id"]}, {$_REQUEST["addcontid"]})";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select t2.*, t1.id 'refid'
         from invoices_buy_contenedores t1
         INNER JOIN supplier_contenedor t2 ON t1.cont_id = t2.id
         where
         t1.invc_id = {$_REQUEST["id"]}
         order by t2.id asc";
$cont_ass = $CON->select($sql);
foreach($cont_ass AS $cont_assrow)
   $sql_excl .= $cont_assrow["id"].", ";
$sql_excl = substr($sql_excl, 0, -2);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from supplier_contenedor t1
         where
         t1.sord_status > 1 ";
if($sql_excl != "")
   $sql .= " and t1.id NOT IN ({$sql_excl}) ";
$sql .= " order by t1.id desc
         LIMIT 0,80";
$cont_disp = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<div style="text-align:center;padding:10px;background-color:#00A9A6;color:white;font-family:Arial;font-size:12px;text-shadow:none;width:950px">
   <b>Información importante:</b><br>
   Solo asignar facturas a contendores que respresentan un gasto adicional.<br>
   La factura de la mercaderia se asigna automaticamente via la OC y no requiere un asignación manual al contenedor.
</div>
<br>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<?php
if(count($cont_ass) && $cont_ass != false)
{  ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="50">
            <col>
            <col>
            <col>
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6" style="background-color:#52BB60;color:white;text-shadow:none">Contenedores asignados</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">Número</td>
            <td class="content_tbl_subheader">OC's</td>
            <td class="content_tbl_subheader">Buque</td>
            <td class="content_tbl_subheader">Forward</td>
            <td class="content_tbl_subheader">Bill of Landing</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($cont_ass) && $cont_ass != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=sprintf("%05s", $cont_ass[$x]["id"])?></td>
               <td class="content_row"><?=$cont_ass[$x]["sord_ocs"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_ass[$x]["sord_buque"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_ass[$x]["sord_forward"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_ass[$x]["sord_billoflanding"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <?php
                  if($headdata["invc_status"] == 1)
                     printButton("Desasociar", "postnav_del", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&subcatexec=contassign&id={$_REQUEST["id"]}&delcontrefid={$cont_ass[$x]["refid"]}", "", "minus");
                  else
                     echo "&nbsp;";
                  ?>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php
}

if($headdata["invc_status"] == 1)
{  ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="50">
            <col>
            <col>
            <col>
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6" style="background-color:#DC4353;color:white;text-shadow:none">Contenedores disponibles</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">Número</td>
            <td class="content_tbl_subheader">OC's</td>
            <td class="content_tbl_subheader">Buque</td>
            <td class="content_tbl_subheader">Forward</td>
            <td class="content_tbl_subheader">Bill of Landing</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($cont_disp) && $cont_disp != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=sprintf("%05s", $cont_disp[$x]["id"])?></td>
               <td class="content_row"><?=$cont_disp[$x]["sord_ocs"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_disp[$x]["sord_buque"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_disp[$x]["sord_forward"]?>&nbsp;</td>
               <td class="content_row"><?=$cont_disp[$x]["sord_billoflanding"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <?php
                  if($headdata["invc_status"] == 1)
                     printButton("Asignar", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&subcatexec=contassign&id={$_REQUEST["id"]}&addcontid={$cont_disp[$x]["id"]}", "", "plus");
                  else
                     echo "&nbsp;";
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="6" align="center">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php
}
?>
</table>
</form>