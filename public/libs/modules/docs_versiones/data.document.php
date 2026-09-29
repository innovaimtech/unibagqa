<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from docs_versiones_types t1
         where
         t1.docto_status = 1
         order by t1.docto_title";
$types = $CON->select($sql);

//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "file")
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["delTranDocFile"] != "")
   {
      $sql = " select doc_file
               from docs_versiones
               where
               id = {$_REQUEST["doc_id"]}";
      $orgfile = $CON->select($sql);
      
      $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/{$_REQUEST["mode"]}/";
      unlink("{$doc_dir}{$orgfile[0]["doc_file"]}");

      $sql = " update docs_versiones
               set
               doc_file = '',
               doc_name = ''
               where
               id = {$_REQUEST["doc_id"]}";
      $res = $CON->no_result($sql);

      if($_REQUEST["mode"] == "orders_offers")
      {  ?>
         <script language="JavaScript">
            parent.reloadSendAnex();
         </script>
         <?php
      }

      $savemsg = getSaveMessage($res);
   }
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "save")
   {
      $currtme = time();
      $_REQUEST["doc_desc"]   = trim(addslashes($_REQUEST["doc_desc"]));
      $_REQUEST["doc_typeid"] = (int)$_REQUEST["doc_typeid"];

      //----------------------------------------------------------------------------------
      if($_REQUEST["doc_id"] == "")
      {
         $sql = " insert into docs_versiones
                  (doc_tran_id, doc_tran_type, doc_name, doc_desc, doc_typeid, doc_crtdat, doc_crtusr)
                  VALUES
                  ({$_REQUEST["id"]}, '{$_REQUEST["mode"]}', '{$doc_name}', '{$_REQUEST["doc_desc"]}', {$_REQUEST["doc_typeid"]},
                   {$currtme}, {$_SESSION["user_id"]})";
         $res = $CON->no_result($sql);

         if($res)
         {
            $sql = " select MAX(id) 'thisid'
                     from docs_versiones
                     where
                     doc_tran_id    = {$_REQUEST["id"]} and
                     doc_tran_type  = '{$_REQUEST["mode"]}' and
                     doc_crtusr     = {$_SESSION["user_id"]}";
            $docdata = $CON->select($sql);
            $_REQUEST["doc_id"] = $docdata[0]["thisid"];
         }
      }
      //----------------------------------------------------------------------------------
      else
      {
         $sql = " update docs_versiones
                  set
                  doc_desc   = '{$_REQUEST["doc_desc"]}',
                  doc_typeid = {$_REQUEST["doc_typeid"]},
                  doc_updusr = {$_SESSION["user_id"]},
                  doc_upddat = {$currtme}
                  where
                  id = {$_REQUEST["doc_id"]}";
         $res = $CON->no_result($sql);

         $savemsg = getSaveMessage($res);
      }

      //----------------------------------------------------------------------------------
      if($_REQUEST["doc_id"] != "" &&
         $_FILES["upldfile"]["name"] != "" &&
         $_FILES["upldfile"]["tmp_name"] != "" &&
         $_FILES["upldfile"]["error"] == 0 &&
         $_FILES["upldfile"]["size"] > 0)
      {
         $doc_type = substr($_FILES["upldfile"]["name"], strrpos($_FILES["upldfile"]["name"], ".") +1);
         $doc_hash = md5(microtime());
         $doc_name = "{$_REQUEST["doc_id"]}_{$doc_hash}.{$doc_type}";
         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.tran/{$_REQUEST["mode"]}/";
         $sql_name = trim(addslashes($_FILES["upldfile"]["name"]));

         $res = move_uploaded_file($_FILES["upldfile"]["tmp_name"], "{$doc_dir}{$doc_name}");

         if($res)
         {
            $savemsg = getSaveMessage($res);
            $sql = " update docs_versiones
                     set
                     doc_file   = '{$doc_name}',
                     doc_name   = '{$sql_name}'
                     where
                     id = {$_REQUEST["doc_id"]}";
            $res = $CON->no_result($sql);
         }
         else
            $savemsg = getSaveMessage(false);

         if($_REQUEST["mode"] == "orders_offers")
         {  ?>
            <script language="JavaScript">
               parent.reloadSendAnex();
            </script>
            <?php
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from docs_versiones t1
            LEFT OUTER JOIN user t2 ON t1.doc_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.doc_crtusr = t3.id
            where
            t1.id = {$_REQUEST["doc_id"]}";
   $docdata = $CON->select($sql);
   $docdata = $docdata[0];

   //----------------------------------------------------------------------------------
   ?>
   <form action="overview.php" method="post" class="fokusfirst" enctype="multipart/form-data" name="xform_doc"
   <?php
   if($_REQUEST["doc_id"] == "")
      echo ' onsubmit="return checkform(new Array(this.upldfile))" '
   ?>>
   <input type="hidden" name="exec" value="save">
   <input type="hidden" name="subexec" value="edit">
   <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
   <input type="hidden" name="doc_id" value="<?=$_REQUEST["doc_id"]?>">
   <input type="hidden" name="delTranDocFile" value="">
   <?=Nifty_printH("box1", "830")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos del archivo</td>
   </tr>
   <?php
   if($_REQUEST["doc_id"] == "" || $docdata["doc_file"] == "")
   {  ?>
      <tr>
         <td class="content_rowl">Archivo *</td>
         <td class="content_row">
            <input class="text" type="file" name="upldfile" id="upldfile" size="50" style="width:440px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" onchange="document.xform_doc.submit();">
            <script language="JavaScript">
               $(document).ready(function()
               {
                  $('#upldfile').click();
               });
            </script>
         </td>
      </tr>
      <?php
   }
   else
   {  ?>
      <tr>
         <td class="content_rowl">Nombre</td>
         <td class="content_row"><?=$docdata["doc_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Archivo</td>
         <td class="content_row">
            <table border="0" cellspacing="0" cellpadding="0" width="240">
            <tr>
               <td style="padding-right:5px" width="120">
                  <?php
                  $docext = strtoupper(substr($docdata["doc_file"], strrpos($docdata["doc_file"], ".")+1));
                  if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                     printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/{$_REQUEST["mode"]}/{$docdata["doc_file"]}')", "image");
                  else
                     printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$docdata["doc_file"]}&name={$docdata["doc_name"]}&path=../../../docs.tran/{$_REQUEST["mode"]}/'", "navigation-270-white");
                  ?>
               </td>
               <td width="120">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) document.xform_doc.delTranDocFile.value='1';submitForm(document.xform_doc) ", "cross-circle-frame");
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
   }
   ?>
   <tr>
      <td class="content_rowl">Tipo</td>
      <td class="content_row">
          <select class="text" style="width:440px" name="doc_typeid" id="doc_typeid"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($types as $type)
            {  ?>
               <option value="<?=$type["id"]?>"
               <?php if($type["id"] == $docdata["doc_typeid"]) echo "selected"?>><?=$type["docto_title"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Descripci�n</td>
      <td class="content_row">
         <textarea class="text" style="width:680px; height:45px" name="doc_desc"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($docdata["doc_desc"])?></textarea>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if($docdata["doc_crtusr"] != "") echo "{$docdata["crt_firstname"]} {$docdata["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if($docdata["doc_crtusr"] != "") echo displayDate($docdata["doc_crtdat"])?>&nbsp;</td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if($docdata["doc_updusr"] != "") echo "{$docdata["upd_firstname"]} {$docdata["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if($docdata["doc_updusr"] != "") echo displayDate($docdata["doc_upddat"])?>&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("boxopt_b", "830")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "overview.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if($_REQUEST["doc_id"] != "") 
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('overview.php?mode={$_REQUEST["mode"]}&id={$_REQUEST["id"]}&doc_id={$_REQUEST["doc_id"]}&subexec=delTranDoc')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_doc)", "disk-black");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.docto_title,
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from docs_versiones t1
            LEFT OUTER JOIN docs_versiones_types t2 ON t1.doc_typeid = t2.id
            LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
            where
            t1.doc_tran_id    = {$_REQUEST["id"]} and
            t1.doc_tran_type  = '{$_REQUEST["mode"]}'
            order by t1.id";
   $docs = $CON->select($sql);
   if(count($docs) && $docs !== false)
   {  ?>
      <br>
      <?=Nifty_printH("box1", "830")?>
      <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="350">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Documentos subidos</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Documento</td>
         <td class="content_tbl_subheader">Tipo</td>
      </tr>
      <?php
      for($x = 0; $x < count($docs) && $docs != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><nobr><?=$docs[$x]["doc_name"]?>&nbsp;</nobr></td>
            <td class="content_row"><?=$docs[$x]["docto_title"]?>&nbsp;</td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <?php
   }
   ?>
   </form>
   <?php
}
else
{
   require_once("../../thirdparty/browser.php");
   $browser = new Browser();
   $system_platform = $browser->getPlatform();
   ?>
   <script language="JavaScript">
   function createAppletCode(systype)
   {
      var divcont = document.getElementById('idx_scanplugin');

      if(systype == 'linux')
         var appletmode = 'sane';
      else
         var appletmode = 'twain';

      divcont.innerHTML = '<applet code=\'uk.co.mmscomputing.device.' +appletmode +'.applet.coplan.class\' archive=\'/libs/thirdparty/scannerplugin/scannerplugin.jar\' width=370 height=30><param name=\'docID\' value=\'<?=$_REQUEST["id"]?>\'><param name=\'conf_ftp_ip\' value=\'<?=$_CONFIG["FTP"]["conf_ftp_ip"]?>\'><param name=\'conf_ftp_user\' value=\'<?=$_CONFIG["FTP"]["conf_ftp_user"]?>\'><param name=\'conf_ftp_pass\' value=\'<?=$_CONFIG["FTP"]["conf_ftp_pass"]?>\'><param name=\'conf_ftp_dir\' value=\'<?=$_CONFIG["FTP"]["conf_ftp_dir"]?>\'></applet>';
   }

   function setScanOutput(s)
   {
      document.getElementById('idx_scanoutput').value = s + '\n' +document.getElementById('idx_scanoutput').value;
   }

   function setScanStep(idx)
   {
      if(idx == '1')
      {
         document.getElementById('idx_scanstep1').src = '/images/content/green_active.gif';
         document.getElementById('idx_scanstep2').src = '/images/content/red_active.gif';
         document.getElementById('idx_scanstep3').src = '/images/content/red_active.gif';
         document.getElementById('idx_scanstep4').src = '/images/content/red_active.gif';
         setScanOutput('Escaneando Imagen');
      }
      else if(idx == '2')
      {
         document.getElementById('idx_scanstep1').src = '/images/content/green_active.gif';
         document.getElementById('idx_scanstep2').src = '/images/content/green_active.gif';
         setScanOutput('Imagen guardado');
      }
      else
      {
         document.getElementById('idx_scanstep3').src = '/images/content/green_active.gif';
         setScanOutput('Imagen transferido');
         recalcImage(idx);
      }
   }

   //--------------------------------------------------------------------------------
   function recalcImage(idx)
   {
      $(document).ready(function()
      {
         $.get("/libs/modules/docs_management/data.document.ftpcreate.php?mode=<?=$_REQUEST["mode"]?>&id=<?=$_REQUEST["id"]?>&img=" +idx, "", function(data)
         {
            $("#idx_scanimage").html(data);
            document.getElementById('idx_scanstep4').src = '/images/content/green_active.gif';
            setScanOutput('Imagen recalculado');
         });
      });
   }

   </script>
   <?=Nifty_printH("box1", "830")?>
   <table border="0" class="content_table" cellpadding="1" cellspacing="0" width="100%">
   <colgroup>
      <col width="120">
      <col width="130">
      <col width="130">
      <col width="0">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Esc�ner local</td>
   </tr>
   <tr>
      <td class="content_rowl">Sistema operativo</td>
      <td class="content_row" align="center">
         <input type="radio" name="ostype" id="ostype2" value="windows"
         <?php if($system_platform != "Linux") echo "checked" ?>
         onclick="createAppletCode('windows')">
         <img src="/images/content/windows.png" style="cursor:pointer"
         onclick="document.getElementById('ostype2').checked = true;createAppletCode('windows')">
         Windows
      </td>
      <td class="content_row" align="center">
         <input type="radio" name="ostype" id="ostype1" value="linux"
         <?php if($system_platform == "Linux") echo "checked" ?>
         onclick="createAppletCode('linux')">
         <img src="/images/content/linux.png" style="cursor:pointer"
         onclick="document.getElementById('ostype1').checked = true;createAppletCode('linux')">
         Linux
      </td>
      <td class="content_row_clear" rowspan="3">&nbsp;</td>
      <td class="content_row_clear" rowspan="3">
         <?php
         $height = 23;
         ?>
         <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
         <colgroup>
            <col width="8">
            <col width="30">
            <col>
            <col width="150">
         </colgroup>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_clear" height="<?=$height?>">&nbsp;</td>
            <td class="content_row_clear" height="<?=$height?>"><img class="select" id="idx_scanstep1" src="/images/content/red_active.gif"></td>
            <td class="content_row_clear">Escanear Imagen</td>
            <td class="content_row_clear" rowspan="5" align="center">
               <div id="idx_scanimage"><img height="100" src="/images/content/image.png"></div>
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_clear" height="<?=$height?>">&nbsp;</td>
            <td class="content_row" height="<?=$height?>"><img class="select" id="idx_scanstep2" src="/images/content/red_active.gif"></td>
            <td class="content_row">Guardar Imagen</td>
         </tr>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_clear" height="<?=$height?>">&nbsp;</td>
            <td class="content_row" height="<?=$height?>"><img class="select" id="idx_scanstep3" src="/images/content/red_active.gif"></td>
            <td class="content_row">Tranferir Imagen</td>
         </tr>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_clear" height="<?=$height?>">&nbsp;</td>
            <td class="content_row" height="<?=$height?>"><img class="select" id="idx_scanstep4" src="/images/content/red_active.gif"></td>
            <td class="content_row">Recalcular Imagen</td>
         </tr>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_clear" height="<?=$height?>">&nbsp;</td>
            <td class="content_row" colspan="2">
               <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
               <tr>
                  <td width="20" valign="top"><img src="/images/menu/icons/information-frame.png"></td>
                  <td class="content_row_clear">
                     No puedes escanear?<br>
                     <a class="link" href="twain_driver.exe"><u>Descarga los drivers</u></a>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <tr>
      <td class="content_row" colspan="3"><div id="idx_scanplugin"></div></td>
   </tr>
   <tr>
      <td class="content_row" colspan="3">
         <textarea id="idx_scanoutput" class="text" style="width:370px;height:60px;background-color:#EEEEEE" readonly></textarea>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "830")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "overview.php?id={$_REQUEST["id"]}&mode={$_REQUEST["mode"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
   </tr>
   </table>
   <script language="JavaScript">
   <?php
   if($system_platform != "Linux")
      echo "createAppletCode('windows');";
   else
      echo "createAppletCode('linux');";
   ?>
   </script>
   <?php

}
?>