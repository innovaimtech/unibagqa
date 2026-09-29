<?php
if((int)$_REQUEST["setarchive"])
{
   $currtme = time();
   $sql = " update supplier_contenedor
            set
            sord_status    = 4,
            sord_updusr    = {$_SESSION["user_id"]},
            sord_upddat    = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from supplier_contenedor t1
         LEFT OUTER JOIN company_data t3  ON t1.sord_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.sord_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.sord_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.sord_crtusr       = t6.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($headdata["sord_status"] == 4)
{
   $rdlo = " readonly ";
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from supplier_contenedor_items t1
         INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
         where
         t1.sord_id = {$_REQUEST["id"]}
         order by t1.id asc";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
   function detectEvent (event, rowcount, sordid)
   {
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="xform_itemprices" enctype="multipart/form-data"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
   echo "onsubmit='return checkform(new Array())'";
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="sord_status" value="">
<input type="hidden" name="printpdf" id="printpdf" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="350">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=sprintf("%05s", $headdata["id"])?></td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <?php
      $statimg = "";
      switch((int)$headdata["sord_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "purple_active.gif"; break;
         case 3: $statimg = "gray_active.gif"; break;
         case 4: $statimg = "green_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
      <?=getSupplierContentdorStatus($headdata["sord_status"], true)?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Buque</td>
   <td class="content_row"><?=$headdata["sord_buque"]?></td>
   <td class="content_rowl">Forward</td>
   <td class="content_row"><?=$headdata["sord_forward"]?></td>
</tr>
<tr>
   <td class="content_rowl">Incoterm</td>
   <td class="content_row"><?=$headdata["sord_incoterm"]?></td>
   <td class="content_rowl">Bill of Landing</td>
   <td class="content_row"><?=$headdata["sord_billoflanding"]?></td>
</tr>
<tr>
   <td class="content_rowl">ETA Puerto</td>
   <td class="content_row"><?php if((int)$headdata["sord_eta_puerto"]) echo date('d.m.Y', $headdata["sord_eta_puerto"])?></td>
   <td class="content_rowl">ETA Unibag</td>
   <td class="content_row"><?php if((int)$headdata["sord_eta_puertounibag"]) echo date('d.m.Y', $headdata["sord_eta_puertounibag"])?></td>
</tr>
<tr>
   <td class="content_rowl">Contenedor(es)</td>
   <td class="content_row"><?=$headdata["sord_contenedor"]?></td>
   <td class="content_rowl">Dias de viaje</td>
   <td class="content_row"><?=$headdata["sord_diasviaje"]?></td>
</tr>
<tr>
   <td class="content_rowl">Fecha limite pago</td>
   <td class="content_row"><?php if((int)$headdata["sord_limit_paydate"]) echo date('d.m.Y', $headdata["sord_limit_paydate"])?></td>
   <td class="content_rowl">OC's asignados</td>
   <td class="content_row"><?=$headdata["sord_ocs"]?>&nbsp;</td>
</tr>
<?php
if($headdata["sord_desc"] != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Observaciones</td>
      <td class="content_row" colspan="3"><?=$headdata["sord_desc"]?></td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=date('d.m.Y', $headdata["sord_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["sord_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col width="75">
   <col>
   <col width="30">
   <col>
   <col width="70">
   <col width="70">
   <col width="80">
   <col width="80">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="9">Contenido</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os">OC</td>
   <td class="content_tbl_subheader content_row_os">Fecha OC</td>
   <td class="content_tbl_subheader content_row_os">Proveedor</td>
   <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
   <td class="content_tbl_subheader content_row_os">Artículo</td>
   <td class="content_tbl_subheader content_row_os" align="center">Kg<br>Total</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cantidad<br>OC</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cantidad<br>Contenedor</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cantidad<br>Recibido</td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from supplier_order_items
            where
            id = {$posdata[$x]["sord_pos_id"]}";
   $supporderpos = $CON->select($sql);
   $supporderpos = $supporderpos[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_short
            from supplier_order t1
            LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id  = t2.id
            where
            t1.id = {$supporderpos["sord_id"]}";
   $sorddata = $CON->select($sql);
   $sorddata = $sorddata[0];

   $fullpos = getSupplierOrderPos($CON, $supporderpos["sord_id"], "", $posdata[$x]["sord_pos_id"]);
   $fullpos = $fullpos[0];

   $posstat = getSupplierContenedorItemState($CON, $supporderpos["sord_id"], $posdata[$x]["sord_pos_id"], $_REQUEST["id"]);

   $sql = " select SUM(t3.item_amount) 'cfmamt'
            from supplier_contenedor_items t1
            INNER JOIN supplier_order_items t2        ON t1.sord_pos_id = t2.id
            INNER JOIN stockchanges ta                ON ta.sth_supporder_contenedorid = t1.sord_id
            INNER JOIN stockchanges_items t3          ON t3.stk_id = ta.id and t3.item_id = t2.item_id and t3.item_contenedor_refid = t1.id
            INNER JOIN company_shops_storehouses t4   ON t3.item_st_id = t4.id
            where
            t1.id          = {$posdata[$x]["id"]} and
            t1.sord_id     = {$_REQUEST["id"]} and
            ta.stk_status  > 1";
   $rcvsamt = $CON->select($sql);
   $_RECEIVEAMT = (float)$rcvsamt[0]["cfmamt"];

   $cssstyle = "";
   if($_RECEIVEAMT == 0.00)
      $cssstyle = "text-shadow:none;background-color:#DA474E;color:#FFFFFF";
   elseif($_RECEIVEAMT != $posdata[$x]["sord_amount"])
      $cssstyle = "text-shadow:none;background-color:#E79D22;color:#FFFFFF";
   elseif($_RECEIVEAMT == $posdata[$x]["sord_amount"])
      $cssstyle = "text-shadow:none;background-color:#49A94D;color:#FFFFFF";

   if($_RECEIVEAMT > 0.00)
      $_CANCLOSE = true;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=$sorddata["sord_number"]?></td>
      <td class="content_row_os"><?=date('d.m.Y', $sorddata["sord_crtdat"])?></td>
      <td class="content_row_os"><?=$sorddata["supp_short"]?></td>
      <td class="content_row_os" align="center"><?=($supporderpos["item_pos"]+1)?></td>
      <td class="content_row_os"><?=$fullpos["item_title"]?></td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["sord_kgs_amount"], 2)?></td>
      <td class="content_row_os" align="center"><?=printPrice($fullpos["item_amount"], 2)?></td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["sord_amount"], 2)?></td>
      <td class="content_row_os" align="center" style="<?=$cssstyle?>"><?=printPrice($_RECEIVEAMT, 2)?></td>
   </tr>
   <?php
   $hasdata = true;
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="8" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php

   if($headdata["sord_status"] == 2 && $_CANCLOSE)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("Archivar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { location.href = 'index.php?mid=1212&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}&setarchive=1';  }", "database");
         ?>
      </td>
      <?php
   }

   /*
   if($headdata["sord_status"] == 2)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Marcar recibido", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.sord_status.value = '3';document.form_shppos.submit(); }", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   if($headdata["sord_status"] == 3)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("Archivar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.sord_status.value = '4';document.form_shppos.submit(); }", "database");
         ?>
      </td>
      <?php
   }
   */
   ?>
</tr>
</table>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>