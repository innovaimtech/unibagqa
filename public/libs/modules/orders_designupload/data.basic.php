<?php
//----------------------------------------------------------------------------------
if((int)$_REQUEST["setassign"])
{
   $sql = " update orders
            set
            req_design_blocked_uid = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["id"]} and
            req_design_blocked_uid = 0";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["fab_print_colordesc_1"]  = trim(addslashes($_REQUEST["fab_print_colordesc_1"]));
   $_REQUEST["fab_print_colordesc_2"]  = trim(addslashes($_REQUEST["fab_print_colordesc_2"]));
   $_REQUEST["fab_print_colordesc_3"]  = trim(addslashes($_REQUEST["fab_print_colordesc_3"]));
   $_REQUEST["fab_print_colordesc_4"]  = trim(addslashes($_REQUEST["fab_print_colordesc_4"]));
   $_REQUEST["fab_print_colordesc_5"]  = trim(addslashes($_REQUEST["fab_print_colordesc_5"]));
   $_REQUEST["newcomments"]            = trim(addslashes($_REQUEST["newcomments"]));
   $_REQUEST["designname"]             = trim(addslashes($_REQUEST["designname"]));

   $existing_id   = (int)$_REQUEST["existing_id_0"];
   $existing_pos  = (int)$_REQUEST["existing_pos_0"];

   $sql = " update orders_items
            set
            fab_print_colordesc_1      = '{$_REQUEST["fab_print_colordesc_1"]}',
            fab_print_colordesc_2      = '{$_REQUEST["fab_print_colordesc_2"]}',
            fab_print_colordesc_3      = '{$_REQUEST["fab_print_colordesc_3"]}',
            fab_print_colordesc_4      = '{$_REQUEST["fab_print_colordesc_4"]}',
            fab_print_colordesc_5      = '{$_REQUEST["fab_print_colordesc_5"]}',
            fab_design_name            = '{$_REQUEST["designname"]}'
            where
            req_id   = {$_REQUEST["id"]} and
            item_id  = {$existing_id} and
            item_pos = {$existing_pos}";
   $CON->no_result($sql);

   if($_REQUEST["newcomments"] != "")
   {
      $sql = " update orders_items
               set
               fab_design_desc = CONCAT(fab_design_desc, '\n\nRESPUESTA:\n{$_REQUEST["newcomments"]}')
               where
               req_id   = {$_REQUEST["id"]} and
               item_id  = {$existing_id} and
               item_pos = {$existing_pos}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $newitemimgdesign = "";
   if($_FILES["fab_design_imagehash"]["name"] != "" &&
      $_FILES["fab_design_imagehash"]["tmp_name"] != "" &&
      $_FILES["fab_design_imagehash"]["error"] == 0 &&
      $_FILES["fab_design_imagehash"]["size"] > 0)
   {
      $doc_type = substr($_FILES["fab_design_imagehash"]["name"], strrpos($_FILES["fab_design_imagehash"]["name"], ".") +1);
      if(strtoupper($doc_type) == "JPG" || strtoupper($doc_type) == "JPEG")
      {
         $doc_hash = md5(microtime());
         $doc_name = "{$_REQUEST["id"]}_{$doc_hash}.{$doc_type}";
         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.order/";
         $ftres    = move_uploaded_file($_FILES["fab_design_imagehash"]["tmp_name"], "{$doc_dir}{$doc_name}");
         if($ftres)
         {
            resizeImage("{$doc_dir}{$doc_name}", 2048, "", "{$doc_dir}{$doc_name}");
            $newitemimgdesign = $doc_name;
         }
      }
   }

   if($newitemimgdesign != "")
   {
      $existing_id   = (int)$_REQUEST["existing_id_0"];
      $existing_pos  = (int)$_REQUEST["existing_pos_0"];
      $sql = " update orders_items
               set
               fab_design_imagehash = '{$newitemimgdesign}'
               where
               req_id   = {$_REQUEST["id"]} and
               item_id  = {$existing_id} and
               item_pos = {$existing_pos}";
      $CON->no_result($sql);

      $sendexecmail = true;
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t2.cust_email, t3.company_short, t4.shop_name, t1.req_cust_id, t2.cust_notes, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.user_firstname 'seller_firstname', t8.user_lastname 'seller_lastname',
                t9.user_firstname 'cashing_firstname', t9.user_lastname 'cashing_lastname',
                t10.trans_name
         from orders t1
         LEFT OUTER JOIN customer t2         ON t1.req_cust_id          = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN user t8             ON t1.req_userid_seller    = t8.id
         LEFT OUTER JOIN user t9             ON t1.req_userid_cashing   = t9.id
         LEFT OUTER JOIN transports t10      ON t1.req_transportid      = t10.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
         where
         t1.id = {$headdata["req_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

$posdata  = getOrderPos($CON, $_REQUEST["id"]);
$thispos = $posdata[0];

//----------------------------------------------------------------------------------
if($sendexecmail)
{
   generateDesignMailResponse($CON, $headdata, $posdata);
}

//----------------------------------------------------------------------------------
$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_fabric_color"]}";
$fabric_color = $CON->select($sql);
$fabric_color = $fabric_color[0]["add_name"];

$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_manilla_color"]}";
$manilla_color = $CON->select($sql);
$manilla_color = $manilla_color[0]["add_name"];

//----------------------------------------------------------------------------------
$_ROWSPANLINES = 2;
for($x = 1; $x <= 5; $x++)
{
   if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
   {
      $_ROWSPANLINES++;
   }
}

//----------------------------------------------------------------------------------
if($thispos["fab_printtype"] == "FLEX")
   $colorscharactid = $_CONFIG["FLEX_TINTA_COLOR_CHARACTID"];
elseif($thispos["fab_printtype"] == "SERI")
   $colorscharactid = $_CONFIG["SERI_TINTA_COLOR_CHARACTID"];

//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$colorscharactid} and
         t2.add_status  = 1
         order by t2.add_name";
$paintcolors = $CON->select($sql);
?>
<form action="index.php" method="post" name="form_reqpos" id="form_reqpos" enctype="multipart/form-data"
<?php
if($thispos["fab_design_imagehash"] != "")
{  ?>
   onsubmit="return false;"
   <?php
}
else
{  ?>
   onsubmit="return checkform(new Array(this.designname));"
   <?php
}
?>>
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="350">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos: Solicitud</td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["req_number"]?></td>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$customer["cust_company"]?></td>
</tr>
<tr>
   <td class="content_rowl" style="border-top:3px double #CCCCCC">Material</td>
   <td class="content_row" style="border-top:3px double #CCCCCC"><?=$thispos["fab_type"]?></td>
   <td class="content_rowl" style="border-top:3px double #CCCCCC">Producto</td>
   <td class="content_row" style="border-top:3px double #CCCCCC"><?=$thispos["item_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Medidas</td>
   <td class="content_row">
      <?=(int)$thispos["fab_med_width"]?>x<?=(int)$thispos["fab_med_height"]?> cm
      <?php
      if((int)$thispos["fab_med_fuelle"])
         echo ", Fuelle ".(int)$thispos["fab_med_fuelle"]." cm";
      ?>
   </td>
   <td class="content_rowl">Area impresión</td>
   <td class="content_row"><?=(int)$thispos["fab_print_width"]?>x<?=(int)$thispos["fab_print_height"]?> cm</td>
</tr>
<tr>
   <td class="content_rowl">Tipo impresión</td>
   <td class="content_row">
      <?php
      if($thispos["fab_printtype"] == "FLEX")
         echo "Flexografia";
      elseif($thispos["fab_printtype"] == "SERI")
         echo "Serigrafia";
      ?>
   </td>
   <td class="content_rowl">Manillas</td>
   <td class="content_row">
      <?php
      if((int)$thispos["fab_manilla_length"])
         echo (int)$thispos["fab_manilla_length"]." cm";
      else
         echo "- - -";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Color tela</td>
   <td class="content_row"><?=$fabric_color?></td>
   <td class="content_rowl" valign="top" rowspan="<?=$_ROWSPANLINES?>">Descripción</td>
   <td class="content_row" valign="top" rowspan="<?=$_ROWSPANLINES?>"><?=$thispos["item_compdesc"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Color manillas</td>
   <td class="content_row"><?=$manilla_color?></td>
</tr>
<?php

for($x = 1; $x <= 5; $x++)
{
   if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
   {  ?>
      <tr>
         <td class="content_rowl" height="1">Color #<?=$x?></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="100">
               <col>
            </colgroup>
            <tr>
               <td class="content_row_clear" width="100">
                  <?php
                  if((int)$thispos["fab_print_colors_front_{$x}"] && !(int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Frente: ";
                  elseif(!(int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Dorso: ";
                  elseif((int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Frente/Dorso: ";
                  ?>
               </td>
               <td class="content_row_clear">
                  <input type="text" class="text" style="width:100%" name="fab_print_colordesc_<?=$x?>"
                  value="<?=$thispos["fab_print_colordesc_{$x}"]?>"
                  <?php if($thispos["fab_design_imagehash"] != "") echo "readonly"?>>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
   }
}
?>
<tr>
   <td class="content_rowl">Cantidad</td>
   <td class="content_row"><?=printPrice($thispos["item_amount"],2)?></td>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">$ / Unitario</td>
   <td class="content_row"><?=printPrice($thispos["item_sellprice_netto"])?></td>
   <td class="content_rowl">$ / Código de barra</td>
   <td class="content_row">
      <?=printPrice($thispos["item_sellprice_barcode"])?>
      <?php
      if($thispos["item_sellprice_barcodenumber"] != "")
      {  ?>
         <span style="float:right">
            Código: <?=$thispos["item_sellprice_barcodenumber"]?>
         </span>
         <?php
      }
      ?>&nbsp;
   </td>
</tr>
<tr>
   <td class="content_rowl" style="border-top:3px double #CCCCCC">Nombre Diseño</td>
   <td class="content_row" colspan="3" style="border-top:3px double #CCCCCC"><?=$thispos["fab_design_name"]?></td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Comentarios diseño</td>
   <td class="content_row" colspan="3"><?=nl2br($thispos["fab_design_desc"])?></td>
</tr>
<?php
if($thispos["fab_design_imagehash"] == "")
{  ?>
   <tr>
      <td class="content_rowl" style="border-top:3px double #CCCCCC">Nombre Diseño *</td>
      <td class="content_row" colspan="3" style="border-top:3px double #CCCCCC">
         <input type="text" class="text" style="width:100%;" name="designname">
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Agregar comentarios</td>
      <td class="content_row" colspan="3">
         <textarea class="text" style="width:100%;height:72px" name="newcomments"></textarea>
      </td>
   </tr>
   <?php
}
if($thispos["fab_design_imagehash"] == "")
{  ?>
   <tr>
      <td class="content_rowl">Imagen diseño</td>
      <td class="content_row" colspan="3">
         <input type="file" class="text" style="width:100%;" name="fab_design_imagehash">
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Imagen diseño</td>
      <td class="content_row" colspan="3">
         <img border="0" src="./docs.order/<?=$thispos["fab_design_imagehash"]?>" width="100%">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if($thispos["fab_design_imagehash"] == "")
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="right" width="130">
         <?php
         printButton("Subir imagen", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_reqpos);", "tick-circle-frame");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
elseif((int)$headdata["req_status"] == 1)
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="right" width="130">
         <?php
         printButton("Eliminar imagen", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { location.href = '/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&delimg=1';} ", "cross-circle-frame");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
<input type="hidden" name="existing_id_0" value="<?=$thispos["item_id"]?>">
<input type="hidden" name="existing_pos_0" value="<?=$thispos["item_pos"]?>">
<input type="hidden" name="item_id_0" value="<?=$thispos["item_id"]?>#<?=$thispos["item_type"]?>">
</form>
<?php
if(!(int)$headdata["req_design_blocked_uid"])
{  ?>
   <div style="top:0px;left:0px;position:fixed;background-color:rgba(0,0,0,0.3);width:100%;height:100%">
      <center>
         <div style="position:relative;">
            <div style="left:calc(50% - 200px);top:100px;position:absolute;width:400px;height:150px;background-color:#FFFFFF;box-shadow:0px 0px 10px 10px rgba(0,0,0,0.3);">
               <center>
               <?=Nifty_printH("boxopt_b", "403px")?>
               <table border="0" cellpadding="3" cellspacing="0" width="100%">
               <tr>
                  <td class="content_tbl_header" align="center">Asignar Solicitud</td>
               </tr>
               <tr>
                  <td class="content_row_clear" align="center">
                     <br>
                     Pinche el siguiente boton para asignar la solicitud.
                     <br><br>
                  </td>
               </tr>
               <tr>
                  <td align="center">
                     <?php
                     printButton("Asignar solicitud", "postnav_save", "/index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&setassign=1", "", "tick-circle-frame", 265);
                     ?>
                  </td>
               </tr>
               </table>
               <?=Nifty_printF()?>
               </center>
            </div>
         </div>
      </center>
   </div>
   <?php
}
elseif((int)$headdata["req_design_blocked_uid"] && $headdata["req_design_blocked_uid"] != $_SESSION["user_id"])
{  ?>
   <div style="top:0px;left:0px;position:fixed;background-color:rgba(0,0,0,0.3);width:100%;height:100%">
      <center>
         <div style="position:relative;">
            <div style="left:calc(50% - 200px);top:100px;position:absolute;width:400px;height:80px;background-color:#FFFFFF;box-shadow:0px 0px 10px 10px rgba(0,0,0,0.3);">
               <center>
               <?=Nifty_printH("boxopt_b", "403px")?>
               <table border="0" cellpadding="3" cellspacing="0" width="100%">
               <tr>
                  <td class="content_tbl_header" align="center">Estado Solicitud</td>
               </tr>
               <tr>
                  <td class="content_row_clear" align="center" style="color:red">
                     <br>
                     La solicitud ya fue asignada a otro usuario.
                     <br><br>
                  </td>
               </tr>
               </table>
               <?=Nifty_printF()?>
               </center>
            </div>
         </div>
      </center>
   </div>
   <?php
}
else
{
   if((int)$_REQUEST["delimg"])
   {
      @unlink("{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.order/{$thispos["fab_design_imagehash"]}");

      $sql = " update orders_items
               set
               fab_design_imagehash = ''
               where
               req_id   = {$thispos["req_id"]} and
               item_id  = {$thispos["item_id"]} and
               item_pos = {$thispos["item_pos"]}";
      $CON->no_result($sql);
      ?>
      <script language="JavaScript">
         location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
}
?>