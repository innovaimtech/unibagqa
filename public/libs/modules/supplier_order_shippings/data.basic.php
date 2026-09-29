<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xsuppliercont"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xsuppliercont"]["fullcust"] = "";
   
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];

   //----------------------------------------------------------------------------------
   $sql = " insert into supplier_contenedor
            (sord_company_id, sord_shop_id, sord_crtdat, sord_crtusr)
            VALUES
            ({$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, {$currtme}, {$_SESSION["user_id"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_contenedor
               where
               sord_crtusr = {$_SESSION["user_id"]}";
      $sorder = $CON->select($sql);
      $_REQUEST["id"]   = $sorder[0]["thisid"];

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=1096&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
   
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["sord_desc"]              = trim(addslashes($_REQUEST["sord_desc"]));
   $_REQUEST["sord_buque"]             = trim(addslashes($_REQUEST["sord_buque"]));
   $_REQUEST["sord_forward"]           = trim(addslashes($_REQUEST["sord_forward"]));
   $_REQUEST["sord_incoterm"]          = trim(addslashes($_REQUEST["sord_incoterm"]));
   $_REQUEST["sord_billoflanding"]     = trim(addslashes($_REQUEST["sord_billoflanding"]));
   $_REQUEST["sord_eta_puerto"]        = trim(addslashes($_REQUEST["sord_eta_puerto"]));
   $_REQUEST["sord_eta_puertounibag"]  = trim(addslashes($_REQUEST["sord_eta_puertounibag"]));
   $_REQUEST["sord_contenedor"]        = trim(addslashes($_REQUEST["sord_contenedor"]));
   $_REQUEST["sord_diasviaje"]         = (int)trim(addslashes($_REQUEST["sord_diasviaje"]));
   $_REQUEST["sord_limit_paydate"]     = trim(addslashes($_REQUEST["sord_limit_paydate"]));

   $_REQUEST["sord_eta_puerto"]        = explode(".", $_REQUEST["sord_eta_puerto"]);
   $_REQUEST["sord_eta_puerto"]        = (int)mktime(15, 0, 0, $_REQUEST["sord_eta_puerto"][1], $_REQUEST["sord_eta_puerto"][0], $_REQUEST["sord_eta_puerto"][2]);
   $_REQUEST["sord_eta_puertounibag"]  = explode(".", $_REQUEST["sord_eta_puertounibag"]);
   $_REQUEST["sord_eta_puertounibag"]  = (int)mktime(15, 0, 0, $_REQUEST["sord_eta_puertounibag"][1], $_REQUEST["sord_eta_puertounibag"][0], $_REQUEST["sord_eta_puertounibag"][2]);
   $_REQUEST["sord_limit_paydate"]     = explode(".", $_REQUEST["sord_limit_paydate"]);
   $_REQUEST["sord_limit_paydate"]     = (int)mktime(15, 0, 0, $_REQUEST["sord_limit_paydate"][1], $_REQUEST["sord_limit_paydate"][0], $_REQUEST["sord_limit_paydate"][2]);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from supplier_contenedor
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $final   = false;
   $archiv  = false;
   $revert  = false;
   $revback = false;
   $recep   = false;
   if($headdata["sord_status"] == 1 && $_REQUEST["sord_status"] == 2)
      $final = true;
   if($headdata["sord_status"] == 3 && $_REQUEST["sord_status"] == 4)
      $archiv = true;
   if($headdata["sord_status"] == 3 && $_REQUEST["sord_status"] == 2)
      $revback = true;
   if($headdata["sord_status"] == 2 && $_REQUEST["sord_status"] == 3)
      $recep = true;
   if($headdata["sord_status"] == 2 && $_REQUEST["sord_status"] == 1)
      $revert = true;
   if($headdata["sord_status"] == 3 && $_REQUEST["sord_status"] == 1)
      $revback = true;
   if($headdata["sord_status"] == 4 && $_REQUEST["sord_status"] == 1)
      $recep = true;
      
   $sql = " update supplier_contenedor
            set
            sord_desc               = '{$_REQUEST["sord_desc"]}',
            sord_buque              = '{$_REQUEST["sord_buque"]}',
            sord_forward            = '{$_REQUEST["sord_forward"]}',
            sord_incoterm           = '{$_REQUEST["sord_incoterm"]}',
            sord_billoflanding      = '{$_REQUEST["sord_billoflanding"]}',
            sord_eta_puerto         = {$_REQUEST["sord_eta_puerto"]},
            sord_eta_puertounibag   = {$_REQUEST["sord_eta_puertounibag"]},
            sord_contenedor         = '{$_REQUEST["sord_contenedor"]}',
            sord_diasviaje          = {$_REQUEST["sord_diasviaje"]},
            sord_limit_paydate      = {$_REQUEST["sord_limit_paydate"]},
            sord_updusr             = {$_SESSION["user_id"]},
            sord_upddat             = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   if($final)
   {
      $sql = " update supplier_contenedor
               set
               sord_status    = 2,
               sord_updusr    = {$_SESSION["user_id"]},
               sord_upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($recep)
   {
      $sql = " update supplier_contenedor
               set
               sord_status    = 3,
               sord_updusr    = {$_SESSION["user_id"]},
               sord_upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   
   //----------------------------------------------------------------------------------
   if($revert)
   {
      $sql = " update supplier_contenedor
               set
               sord_status    = 1,
               sord_updusr    = {$_SESSION["user_id"]},
               sord_upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($archiv)
   {
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
   if($revback)
   {
      $sql = " update supplier_contenedor
               set
               sord_status    = 2,
               sord_updusr    = {$_SESSION["user_id"]},
               sord_upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "contamts_") !== false && strpos($reqkey, "contamts_") == 0)
      {
         $posid            = substr($reqkey, strrpos($reqkey, "_") +1);
         $sord_amount      = getPrice($_REQUEST[$reqkey],2);
         $sord_kgs_amount  = getPrice($_REQUEST["contkgs_{$posid}"],2);

         if(!$sord_amount && !$sord_kgs_amount)
         {
            $sql = " delete from supplier_contenedor_items
                     where
                     id = {$posid}";
            $CON->no_result($sql);
         }
         else
         {
            $sql = " update supplier_contenedor_items
                     set
                     sord_amount       = {$sord_amount},
                     sord_kgs_amount   = {$sord_kgs_amount}
                     where
                     id = {$posid}";
            $CON->no_result($sql);
         }
      }
   }

   getSupplierContenedorOCStr($CON, $_REQUEST["id"]);
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
   <td class="content_row">
      <input type="text" class="text" style="width:100%" name="sord_buque" value="<?=$headdata["sord_buque"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
   <td class="content_rowl">Forward</td>
   <td class="content_row">
      <input type="text" class="text" style="width:100%" name="sord_forward" value="<?=$headdata["sord_forward"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
</tr>
<tr>
   <td class="content_rowl">Incoterm</td>
   <td class="content_row">
      <input type="text" class="text" style="width:100%" name="sord_incoterm" value="<?=$headdata["sord_incoterm"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
   <td class="content_rowl">Bill of Landing</td>
   <td class="content_row">
      <input type="text" class="text" style="width:100%" name="sord_billoflanding" value="<?=$headdata["sord_billoflanding"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
</tr>
<tr>
   <td class="content_rowl">ETA Puerto</td>
   <td class="content_row">
      <input type="text" style="width:85px" id="sord_eta_puerto" name="sord_eta_puerto" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["sord_eta_puerto"]) echo date('d.m.Y', $headdata["sord_eta_puerto"])?>">
   </td>
   <td class="content_rowl">ETA Unibag</td>
   <td class="content_row">
      <input type="text" style="width:85px" id="sord_eta_puertounibag" name="sord_eta_puertounibag" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["sord_eta_puertounibag"]) echo date('d.m.Y', $headdata["sord_eta_puertounibag"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Contenedor(es)</td>
   <td class="content_row">
      <input type="text" class="text" style="width:100%" name="sord_contenedor" value="<?=$headdata["sord_contenedor"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
   <td class="content_rowl">Dias de viaje</td>
   <td class="content_row">
      <input type="text" class="text" style="width:80px" name="sord_diasviaje" value="<?=$headdata["sord_diasviaje"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
   </td>
</tr>
<tr>
   <td class="content_rowl">Fecha limite pago</td>
   <td class="content_row">
      <input type="text" style="width:85px" id="sord_limit_paydate" name="sord_limit_paydate" <?=$rdlo?>
      class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["sord_limit_paydate"]) echo date('d.m.Y', $headdata["sord_limit_paydate"])?>">
   </td>
   <td class="content_rowl">OC's asignados</td>
   <td class="content_row"><?=$headdata["sord_ocs"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row" colspan="3">
      <textarea class="text" style="width:100%; height:90px" name="sord_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["sord_desc"])?></textarea>
   </td>
</tr>
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
   <col width="90">
   <col width="80">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="10">Contenido</td>
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
   <td class="content_tbl_subheader content_row_os" align="center">En otros<br>Contenedores</td>
   <td class="content_tbl_subheader content_row_os" align="center">Cantidad<br>Pendiente</td>
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
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=$sorddata["sord_number"]?></td>
      <td class="content_row_os"><?=date('d.m.Y', $sorddata["sord_crtdat"])?></td>
      <td class="content_row_os"><?=$sorddata["supp_short"]?></td>
      <td class="content_row_os" align="center"><?=($supporderpos["item_pos"]+1)?></td>
      <td class="content_row_os"><?=$fullpos["item_title"]?></td>
      <td class="content_row_os" align="center">
         <input type="text" class="text" style="width:60px;text-align:center" value="<?=printPrice($posdata[$x]["sord_kgs_amount"], 2)?>"
         name="contkgs_<?=$posdata[$x]["id"]?>" id="contkgs_<?=$posdata[$x]["id"]?>" <?=$rdlo?>>
      </td>
      <td class="content_row_os" align="center"><?=printPrice($fullpos["item_amount"], 2)?></td>
      <td class="content_row_os" align="center">
         <input type="text" class="text" style="width:80px;text-align:center" value="<?=printPrice($posdata[$x]["sord_amount"], 2)?>"
         name="contamts_<?=$posdata[$x]["id"]?>" id="contamts_<?=$posdata[$x]["id"]?>" <?=$rdlo?>>
      </td>
      <td class="content_row_os" align="center"><?=printPrice($posstat, 2)?></td>
      <td class="content_row_os" align="center"><?=printPrice($fullpos["item_amount"] - $posdata[$x]["sord_amount"] - $posstat, 2)?></td>
   </tr>
   <?php
   $hasdata = true;
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="10" align="center">
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
   if($headdata["sord_status"] >= 2)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.sord_status.value='1';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   if($headdata["sord_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if($hasdata)
      {  ?>
         <td align="right" width="130" id="idx_fin_button">
            <?php
            printButton("Marcar en transito", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.sord_status.value = '2';document.form_shppos.submit(); }", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   if($headdata["sord_status"] >= 2)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.getElementById('idxifrsrc').src = './libs/modules/supplier_order_shippings/data.pdf.php?id={$_REQUEST["id"]}'", "script");
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