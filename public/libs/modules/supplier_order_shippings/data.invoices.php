<?php
//----------------------------------------------------------------------------------
$sql = " select distinct t4.id
         from supplier_contenedor_items t1
         INNER JOIN supplier_order_items t2  ON t1.sord_pos_id = t2.id
         INNER JOIN invoices_buy_parts t3    ON t2.sord_id = t3.part_sord_id
         INNER JOIN invoices_buy t4          ON t3.part_invc_id = t4.id
         where
         t1.sord_id = {$_REQUEST["id"]} and
         t4.invc_status > 1
         order by t1.id asc";
$posdata = $CON->select($sql);

$_SQL_OCS = "";
foreach($posdata AS $posdatarow)
   $_SQL_OCS .= "{$posdatarow["id"]},";
$_SQL_OCS = substr($_SQL_OCS, 0, -1);

//----------------------------------------------------------------------------------
$sql = " select t2.*, t4.supp_company
         from invoices_buy t2
         LEFT OUTER JOIN invoices_buy_contenedores t1 ON t1.invc_id = t2.id
         LEFT OUTER JOIN supplier t4   ON t2.invc_supplier_id = t4.id
         where
         (
            t1.cont_id = {$_REQUEST["id"]} ";
if($_SQL_OCS != "")
   $sql .= " or t2.id IN ({$_SQL_OCS}) ";
$sql .= " ) and
         t2.invc_status > 1
         order by t2.invc_date, t2.id";
$cont_ass = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col width="110">
   <col width="90">
   <col>
   <col width="90">
   <col width="90">
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Facturas asignadas</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Factura</td>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader">Monto/Neto</td>
   <td class="content_tbl_subheader">IVA</td>
   <td class="content_tbl_subheader">Monto/Bruto</td>
</tr>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x < count($cont_ass) && $cont_ass != false; $x++)
{
   if((int)$cont_ass[$x]["invc_importation"])
   {
      $cont_ass[$x]["invc_total_netto"]   = $cont_ass[$x]["invc_import_total"];
      $cont_ass[$x]["invc_total_brutto"]  = $cont_ass[$x]["invc_import_total"];
      $cont_ass[$x]["invc_total_taxes"]   = 0;
   }
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$cont_ass[$x]["invc_number"]?>&nbsp;</td>
      <td class="content_row"><?=$cont_ass[$x]["invc_docnumber"]?>&nbsp;</td>
      <td class="content_row"><?=date('d.m.Y',$cont_ass[$x]["invc_date"])?>&nbsp;</td>
      <td class="content_row"><?=$cont_ass[$x]["supp_company"]?>&nbsp;</td>
      <td class="content_row"><?=printPrice($cont_ass[$x]["invc_total_netto"])?></td>
      <td class="content_row"><?=printPrice($cont_ass[$x]["invc_total_taxes"])?></td>
      <td class="content_row"><?=printPrice($cont_ass[$x]["invc_total_brutto"])?></td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="7" align="center">
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