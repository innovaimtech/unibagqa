<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// if a document is requested, load library
//----------------------------------------------------------------------------------
if($_REQUEST["doc_id"] != "")
{
   // load library
   require_once("document.php");

   // show back button, if doc is no embedded
   if($_REQUEST["type"] == "0")
   {
      ?>
      <br><br>
      <?=Nifty_printH("boxopt_b", "650")?>
      <table border="0" cellpadding="0" cellspacing="0" width="100">
      <tr>
         <td>
            <table cellpadding="0" cellspacing="0" width="130" border="0">
            <tr>
               <td>
                  <ul class="postnav">
                     <a href="index.php?showDocs=container&mid=<?=$_REQUEST["mid"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
                  </ul>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <?php
   }
}

//----------------------------------------------------------------------------------
// show list of documents
//----------------------------------------------------------------------------------
else
{
   // get documents from menu class
   $docs = $_SESSION["_MENU"]->getSubDocuments($_REQUEST["mid"]);
   
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["DOCS"][7]?> <?=$_SESSION["_MENU"]->getModulePath($_REQUEST["mid"])?></b></td>
      <td></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="150">
      <col width="150">
      <col width="40">
      <col width="70">
      <col>
      <col width="80" align="center">
      <col width="80" align="center">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7"><?=$_LANG["MODULE"]["DOCS"][8]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DOCS"][9]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DOCS"][10]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DOCS"][11]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DOCS"][12]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DOCS"][13]?></td>
      <td class="content_tbl_subheader" colspan="2" align="center"><?=$_LANG["MODULE"]["DOCS"][14]?></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   // loop through documents
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($docs); $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$docs[$x]["menu_name"]?></td>
         <td class="content_row"><?=$docs[$x]["doc_name"]?></td>
         <td class="content_row"><?=$docs[$x]["doc_type"]?></td>
         <td class="content_row"><nobr><?=displaySize($docs[$x]["doc_size"])?><nobr></td>
         <td class="content_row"><?=stripslashes($docs[$x]["menu_desc"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <ul class="postnav">
               <a href="index.php?showDocs=container&mid=<?=$_REQUEST["mid"]?>&doc_id=<?=$docs[$x]["menu_docid"]?>&type=0"><?=$_LANG["MODULE"]["DOCS"][15]?></a>
            </ul>
         </td>
         <td class="content_row" align="center">
            <?php
            if($docs[$x]["menu_behavior"] == "1")
            {  ?>
               <ul class="postnav">
                  <a href="index.php?showDocs=container&mid=<?=$_REQUEST["mid"]?>&doc_id=<?=$docs[$x]["menu_docid"]?>&type=1"><?=$_LANG["FORM"]["BUTTON"][5]?></a>
               </ul>
               <?php
            }
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
   <?php
}