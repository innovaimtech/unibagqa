<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

// get document data
$sql = " select *
         from menu_docs
         where
         id = {$_REQUEST["doc_id"]}";
$doc = $CON->select($sql);

//----------------------------------------------------------------------------------
// show document informations and download button / autostart download
//----------------------------------------------------------------------------------
if($_REQUEST["type"] == "0")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="650">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["DOCS"][0]?></b></td>
      <td></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="150">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["DOCS"][1]?></td>
   </tr>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DOCS"][2]?></td>
      <td class="content_row"><?=$doc[0]["doc_name"]?></td>
   </tr>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DOCS"][3]?></td>
      <td class="content_row"><?=displaySize($doc[0]["doc_size"])?></td>
   </tr>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DOCS"][4]?></td>
      <td class="content_row"><?=$doc[0]["doc_mimetype"]?></td>
   </tr>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DOCS"][5]?></td>
      <td class="content_row">
         <table cellpadding="0" cellspacing="0" width="130" border="0">
         <tr>
            <td>
               <ul class="postnav">
                  <a href="javascript: deactivateFormChange()"
                  onclick="document.all.docfrm.src = document.all.docfrm.src"><?=$_LANG["MODULE"]["DOCS"][6]?></a>
               </ul>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <iframe id="docfrm" width="1" height="1" frameborder="0"
   src="./libs/modules/structure/document_file.php?type=<?=$_REQUEST["type"]?>&id=<?=$doc[0]["id"]?>&hash=<?=$doc[0]["doc_hash"]?>&mime=<?=$doc[0]["doc_mimetype"]?>&name=<?=$doc[0]["doc_name"]?>">
   </iframe>
   <?php
}

//----------------------------------------------------------------------------------
// display file embedded in iframe
//----------------------------------------------------------------------------------
else
{  ?>
   <iframe id="docfrm" width="100%" height="1" frameborder="0" scrolling="auto" marginheight="5" marginwidth="0"
   src="./libs/modules/structure/document_file.php?type=<?=$_REQUEST["type"]?>&id=<?=$doc[0]["id"]?>&hash=<?=$doc[0]["doc_hash"]?>&mime=<?=$doc[0]["doc_mimetype"]?>&name=<?=$doc[0]["doc_name"]?>">
   </iframe>
   <?php
   $_SESSION["JSEXEC"] .= "var height = document.all.content_td.offsetHeight;";
   $_SESSION["JSEXEC"] .= "document.all.docfrm.style.height = height +'px';";
}
?>