<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: buscador de Versiones.
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "add")
{
   require_once("data.detalle.php");
}
else
{

   if((int)$_REQUEST["deleteAmts"])
   {
      $sql = "delete from pro_dis_detalle where pro_dis_detalle_id = {$_REQUEST["deleteAmts"]}";
      $res = $CON->no_result($sql); 
      $savemsg = getSaveMessage($res);
   }

   // Buscador de Detalles
   $sql = "select t1.*
                 ,user_firstname
                 ,user_lastname
                 ,tran_docs.doc_file 
                 ,tran_docs.doc_name
           from pro_dis_detalle t1
               left join user user on pro_dis_detalle_user_cr = id
               left join tran_docs on doc_tran_id = pro_dis_detalle_id and doc_tran_type = 'versiones'
            where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
   $detalles = $CON->select($sql);


   //--------------------------------------------------------------------------------------------------------------------
if((int)$_REQUEST["senddesignmail"])
{
   $title   = "Asunto...";
   $body    = $_REQUEST["msg_body"];
   
   $attachments = [];

   if (!empty($_REQUEST['input_adjunto'])) 
   {
      foreach ($_REQUEST['input_adjunto'] as $item) {
         list($archivo, $nombre) = explode('|', $item);
         $ruta = $_SERVER['DOCUMENT_ROOT']."/docs.tran/versiones/" . $archivo;
         /* $_SERVER['DOCUMENT_ROOT'] .  */
         if (file_exists($ruta)) {
            $attachments[] = [
               "FILE" => $ruta,
               "NAME" => $nombre
            ];
         }
      }
   }
                 
   if (!empty($_REQUEST["correo_cliente"])) 
   {
      $correos = explode(";", $_REQUEST["correo_cliente"]);
      foreach ($correos as $correo) 
      { 
         $correo = trim($correo);
         if (filter_var($correo, FILTER_VALIDATE_EMAIL))
         {
            sendExternalMail($title, $body, $correo, $correo, "", "", $attachments);
         } 
         else
         {
            echo "Correo inválido: " . $correo . "<br>";
         }
      }
   } 
   else 
   {
      echo "No se ingresaron correos.";
   }
}
   


   /*
   $sql = "select t1.*
               ,tran_docs.id
               ,tran_docs.doc_crtdat
               ,tran_docs.doc_name
               ,tran_docs.doc_file
               ,tran_docs.doc_tran_id
            from pro_dis_detalle t1
               left join tran_docs on doc_tran_id = pro_dis_detalle_id and doc_tran_type = 'versiones'
            where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
   $detalle = $CON->select($sql);
   */

   // busca items_A
   
   $sql = "select * from pro_dis_items
            where pro_dis_items_pro_id = {$_REQUEST["id"]}
            order by pro_dis_items_id";
   $posdata = $CON->select($sql);

   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>

   <form action="index.php" method="post" name="xform_itemsearch" id="xform_itemsearch">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="id_item" value="<?=$_REQUEST["id_item"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="deldesignimg" value="">
   <input type="hidden" name="senddesignmail" value="">
   <input type="hidden" name="autoopensendmail" value="">
   <input type="hidden" name="autoopenpdf" value="">
   <input type="hidden" name="saveAmts" value="">
   <input type="hidden" name="deleteAmts" value="">
   <input type="hidden" name="openfancymode" value="">
   

   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <?=Nifty_printH("box2", "980", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="50">
               <col width="100">
               <col width="70">
               <col width="150">
               <col width="100">
               <col width="100">
               <col width="60">
            </colgroup>
         </table>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="50">
               <col width="100">
               <col width="70">
               <col width="150">
               <col width="100">
               <col width="100">
               <col width="60">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="7">Versiones Ingresadas</td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Id</td>
               <td class="content_tbl_subheader content_row_os">Codigo</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Diseñador</td>
               <td class="content_tbl_subheader content_row_os">Nombre</td>
               <td class="content_tbl_subheader content_row_os">Imagen</td>
               <td class="content_tbl_subheader content_row_os">Seleccionar</td>
            </tr>
            <?php
               for($x = 0; $x < count($detalles) && $detalles != false; $x++)
               {
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=$detalles[$x]["pro_dis_detalle_id"]?></td>
                     <td class="content_row_os"><nobr><?=$detalles[$x]["pro_dis_detalle_codigo"]?></nobr></td>
                     <td class="content_row_os"><?=date('d.m.Y', $detalles[$x]["pro_dis_detalle_fecha_cr"])?></td>
                     <td class="content_row_os"><?=$detalles[$x]["user_firstname"].' '.$detalles[$x]["user_lastname"]?></td>
                     <td class="content_row_os"><?=$detalles[$x]["doc_name"]?></td>
                     <td class="content_row_os" width="50" align="center">
                        <a href="/docs.tran/versiones/<?=$detalles[$x]["doc_file"]?>" target="_blank">
                            <img src="/docs.tran/versiones/<?=$detalles[$x]["doc_file"]?>" height="100" style="float:center">
                        </a>
                     </td>
                     <td class="content_row_os" align="center">
                         <?php
                           $valor_actual = $detalles[$x]["doc_file"] . "|" . $detalles[$x]["doc_name"];
                           $seleccionado = false;
                           if (isset($_REQUEST['img_to_send']) && is_array($_REQUEST['img_to_send'])) 
                           {
                              $seleccionado = in_array($valor_actual, $_REQUEST['img_to_send']);
                           }
                         ?>
                         <input type="checkbox" name="img_to_send[]" value="<?=$detalles[$x]["doc_file"]?>|<?=$detalles[$x]["doc_name"]?>" <?= $seleccionado ? 'checked' : '' ?> >
                     </td>
                  </tr>
                  <?php
                  $hasdata = true;
               }
               if(!$x)
               {  ?>
                  <tr bgcolor="<?=getRowColor(0)?>">
                     <td class="content_row" colspan="10" align="center">
                        <br>
                        <br class="msg_save_err">No hay datos disponibles.</b>
                        <br><br>
                     </td>
                  </tr>
                  <?php
               }
            ?>
         </table>
         <?=Nifty_printF(false)?>
         <br>
      </td>
   </tr>
   <?=Nifty_printH("boxopt_t", "980", 0)?>
   <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <td width="130">
         <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign&id={$_REQUEST["id"]}&id_item={$posdata[$x]["pro_dis_items_id"]}", "", "arrow-180");
         ?>
      </td> 
      <td></td>
      <td></td>
      <td></td>      
      <td width="130" align="right" style="padding-right:5px">
         <?php
            printButton("Preparar Mail", "postnav_save", "javascript: deactivateFormChange()", "document.xform_itemsearch.openfancymode.value='gotosend';submitForm(document.xform_itemsearch);", "mail");
         ?>
      </td>   
   </table>
   <?=Nifty_printF(false)?>

      <?php
      /* RUTINA DE CORREO ELECTRONICO */

      if($_REQUEST["openfancymode"] == "gotosend")
      {  ?>
         <script language="JavaScript">
         $(document).ready(function()
         {
            document.getElementById('idx_mail').style.display='';
            setTimeout(function()
            {
               $('html,body').animate({scrollTop:$('#idx_mail').offset().top}, 600);
            }, 600);
         });
         </script>
         <?php
      }
      ?>
      <br>


      <script>
      document.addEventListener('DOMContentLoaded', function () {
         const form = document.getElementById('xform_itemsearch');
         const checkboxes = form.querySelectorAll('input[name="img_to_send[]"]');

         // Limpiar campos ocultos previos
         function limpiarAdjuntos() {
            const existentes = form.querySelectorAll('input[name="input_adjunto[]"]');
            existentes.forEach(e => e.remove());
         }

         // Actualizar campos ocultos según checkboxes marcados
         function actualizarAdjuntos() {
            limpiarAdjuntos();
            checkboxes.forEach(cb => {
               if (cb.checked) {
                  const hidden = document.createElement('input');
                  hidden.type = 'hidden';
                  hidden.name = 'input_adjunto[]';
                  hidden.value = cb.value;
                  form.appendChild(hidden);
               }
            });
         }

         // Escuchar cambios en cada checkbox
         checkboxes.forEach(cb => {
            cb.addEventListener('change', actualizarAdjuntos);
         });

         // Ejecutar una vez al cargar por si hay checkboxes ya marcados
         actualizarAdjuntos();
      });
      </script>

      <div id="idx_mail" style="display:none">
         <script type="text/javascript" src="./libs/jscripts/tinymce_3_2_2_3/jscripts/tiny_mce/tiny_mce.js"></script>
         <script type="text/javascript">
            tinyMCE.init({
               mode : "specific_textareas",
               editor_selector : "mceEditor",
               theme : "advanced",
               plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
               theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,outdent,indent,blockquote,|,forecolor,backcolor,tablecontrols",
               theme_advanced_buttons2 : "", theme_advanced_buttons3 : "", theme_advanced_buttons4 : "",
               theme_advanced_toolbar_location : "top", theme_advanced_toolbar_align : "left",
               content_css : "css/content.css", template_external_list_url : "lists/template_list.js", external_link_list_url : "lists/link_list.js", external_image_list_url : "lists/image_list.js", media_external_list_url : "lists/media_list.js",
               width: "810px", height: "150px", force_br_newlines: true, forced_root_block: ''
            });
         </script>
         <form action="index.php" method="post" name="xform_docsend">
         <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
         <input type="hidden" name="subexec" value="send">
         <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <?=Nifty_printH("box2", "980", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="150">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="2">Enviar correo</td>
         </tr>
         <tr>
            <td class="content_rowl">Tipo Envio</td>
            <td class="content_row">
               <input type="radio" name="sendtype" value="0" checked
               onclick="$('#idx_custmails_tr').fadeIn(300);"> A correos del cliente
               <!--
               <input type="radio" name="sendtype" value="1"
               onclick="$('.clscustmails').each(function() { $(this).attr('checked', false); });$('#idx_custmails_tr').fadeOut(300);"> A usuarios
               -->
            </td>
         </tr>
         <tr id="idx_custmails_tr">
            <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["MSG"][19]?></td>
            <td class="content_row">
               <input type="email" name="correo_cliente" id="correo_cliente" placeholder="Ingrese correo" style="width:810px;"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
               >
               <br>
               <?php
               $msg_header = "Propuesta de Diseño de Unibag a {$headdata["req_cust_company"]}: Nº {$headdata["req_number"]}";
               $msg_header = str_replace("'", "", str_replace('"', "", $msg_header));

               $sql = " select user_mail_signature_html
                        from user
                        where
                        id = {$_SESSION["user_id"]}";
               $mailsig = $CON->select($sql);
               $mailsig = $mailsig[0]["user_mail_signature_html"];
               if(trim($mailsig) != "")
                  $msg_body = "<br><br>".$mailsig;
               ?>
            </td>
         </tr>
         <tr>
            <td class="content_rowl" valign="top">CC</td>
            <td class="content_row">
               <input type="email" name="cc_correo_cliente" id="cc_correo_cliente" placeholder="Ingrese correo" style="width:810px;"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <br>
            </td>
         </tr>
         <tr>
            <td class="content_rowl"><?=$_LANG["MODULE"]["MSG"][29]?> *</td>
            <td class="content_row">
               <input type="text" class="text" style="width:810px" maxlength="254" name="msg_header" value="<?=$msg_header?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["MSG"][30]?> *</td>
            <td class="content_row">
               <textarea class="text mceEditor" style="width:810px; height:150px" name="msg_body"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">Estimado/a Cliente;<br><br>
                        Junto con saludar envío adjunto propuesta de diseño solicitada.<br><br>
                        Quedo atento/a a sus consultas.<br>
               <?=stripslashes($msg_body)?></textarea>
            </td>
         </tr>
         <tr>
            <?php
               if (!empty($_POST['img_to_send'])) //  && is_array($_REQUEST['img_to_send'])) 
               {
                  foreach ($_POST['img_to_send'] as $item)
                  {
                     echo '<input type="hidden" name="input_adjunto[]" value="' . htmlspecialchars($item) . '">';
                  }
               }
            ?>
            <td class="content_rowl">Adjuntos</td>
            <td class="content_row" id="idx_anexos_inner">
            <?php
               if (!empty($_REQUEST['input_adjunto'])) 
               {
                  foreach ($_REQUEST['input_adjunto'] as $archivo) {
                     list($file, $name) = explode('|', $archivo);

                     echo "* " . htmlspecialchars($name) . "<br>";
                     $ruta = $_SERVER['DOCUMENT_ROOT'] . "/docs.tran/versiones/" . $file;

                     $adjuntos[] = [
                        "FILE" => $ruta,
                        "NAME" => $name
                     ];
                  }
               }
               /*
               $sql = " select t1.*, t2.docto_title, t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                        from tran_docs t1
                        LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                        LEFT OUTER JOIN user t3 ON t1.doc_crtusr = t3.id
                        where
                        t1.doc_tran_id    = {$headdata["id"]} and
                        t1.doc_tran_type  = 'orders_offers'  and
                        t1.doc_name      != ''
                        order by t1.id";
               $anexos = $CON->select($sql);
               foreach($anexos AS $anexo)
               {  
                  ?>
                  <span style="float:left;background-color:#00A9A6;color:white;text-shadow:none;padding:3px;padding-left:6px;padding-right:6px;margin-right:3px;border-radius:3px"><?=$anexo["doc_name"]?></span>
                  <?php
               }
               */
            ?>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <table border="0" cellspacing="0" cellpadding="0" width="980">
            <tr>
               <td>&nbsp;</td>
               <td width="130" style="padding-right:5px">
                  <button type="button" onclick="document.xform_itemsearch.senddesignmail.value = '1'; form.submit();" 
                        style="color: black;
                        font-weight: bold;
                        padding: 10px 20px;
                        font-size: 12px;
                        border: groove;
                        border-radius: 4px;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        cursor: pointer;
                        width: 170px;
                     ">
                     Enviar Mail a Cliente
                  </button>
               </td>
            </tr>
         </form>
         </table>
         <br><br><br>
      </div>
   </table>
   <?=Nifty_printF(false)?>
   <iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>
   </form>
   <?php

}