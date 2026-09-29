<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["_MENU"] = new CMENU(  $CON,
                                 $_SESSION["user_id"],
                                 $_SESSION["user_type"],
                                 $_SESSION["user_groups"]);

$_SESSION["_MENU"]->getStruct();
$_SESSION["_MENU"]->enumFavorites($CON);

//----------------------------------------------------------------------------------
if($_REQUEST["deleteItem"] != "")
{
   $delids = $_SESSION["_MENU"]->getChilds($_REQUEST["deleteItem"]);

   foreach(array_keys($delids) AS $delid)
   {
      if((int)$delids[$delid] > 0)
      {
         $sql = " select doc_hash
                  from menu_docs
                  where
                  id = {$delids[$delid]}";
         $doc_hash = $CON->select($sql);
         $doc_hash = $doc_hash[0]["doc_hash"];

         unlink("./docs/{$delids[$delid]}.{$doc_hash}");

         $sql = " delete from menu_docs
                  where
                  id = {$delids[$delid]}";
         $CON->no_result($sql);
      }

      $sql = " delete from menu_items
               where
               id = {$delid}";
      $CON->no_result($sql);

      $sql = " delete from group_menu_items
               where
               menu_id = {$delid}";
      $CON->no_result($sql);

      $sql = " delete from user_menu_items
               where
               menu_id = {$delid}";
      $CON->no_result($sql);
   }
   ?>
   <script language="JavaScript">
      parent.location.href='index.php?redirectmid=<?=$_REQUEST["mid"]?>';
      location.href='index.php?mid=<?=$_REQUEST["mid"]?>'
   </script>
   <?php
}

//----------------------------------------------------------------------------------
if(($_REQUEST["exec"] == "add" && $_REQUEST["subexec"] == "save") ||
   ($_REQUEST["showItem"] != "" && $_REQUEST["subexec"] == "save"))
{
   $currtme = time();

   $_REQUEST["menu_name1"]       = trim(addslashes($_REQUEST["menu_name1"]));
   $_REQUEST["menu_name2"]       = trim(addslashes($_REQUEST["menu_name2"]));
   $_REQUEST["menu_name3"]       = trim(addslashes($_REQUEST["menu_name3"]));
   $_REQUEST["menu_desc"]        = trim(addslashes($_REQUEST["menu_desc"]));
   $_REQUEST["menu_mod_params"]  = trim(addslashes($_REQUEST["menu_mod_params"]));
   $_REQUEST["menu_trancode"]    = trim(addslashes($_REQUEST["menu_trancode"]));
   $_REQUEST["menu_link_mod"]    = trim($_REQUEST["menu_link_mod"]);
   $_REQUEST["menu_link"]        = (int)$_REQUEST["menu_link"];
   $_REQUEST["menu_adm"]         = (int)$_REQUEST["menu_adm"];
   $_REQUEST["menu_public"]      = (int)$_REQUEST["menu_public"];
   $_REQUEST["parentid"]         = (int)$_REQUEST["parentid"];
   $_REQUEST["menu_order"]       = (int)$_REQUEST["menu_order"];
   $_REQUEST["menu_behavior"]    = (int)$_REQUEST["menu_behavior"];
   $_REQUEST["menu_spacer"]      = (int)$_REQUEST["menu_spacer"];
   

   if($_REQUEST["showItem"] != "")
   {
      $sql = " delete from group_menu_items
               where
               menu_id = {$_REQUEST["showItem"]}";
      $CON->no_result($sql);

      $sql = " delete from user_menu_items
               where
               menu_id = {$_REQUEST["showItem"]}";
      $CON->no_result($sql);

      if(is_array($_REQUEST["groupids"]))
      {
         foreach($_REQUEST["groupids"] AS $groupid)
         {
            $sql = " insert into group_menu_items
                     (group_id, menu_id)
                     VALUES
                     ({$groupid}, {$_REQUEST["showItem"]})";
            $CON->no_result($sql);
         }
      }

      if(is_array($_REQUEST["userids"]))
      {
         foreach($_REQUEST["userids"] AS $userid)
         {
            $sql = " insert into user_menu_items
                     (user_id, menu_id)
                     VALUES
                     ({$userid}, {$_REQUEST["showItem"]})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "add" && $_REQUEST["subexec"] == "save")
{
   if($_REQUEST["menu_link"] == 2 &&
      $_FILES["menu_doc_file"]["name"] != "" &&
      $_FILES["menu_doc_file"]["tmp_name"] != "" &&
      $_FILES["menu_doc_file"]["error"] == 0 &&
      $_FILES["menu_doc_file"]["size"] > 0)
   {

      $_REQUEST["menu_link_mod"] = "structure/document.php";

      $_FILES["menu_doc_file"]["name"] = addslashes($_FILES["menu_doc_file"]["name"]);

      $doc_type = substr($_FILES["menu_doc_file"]["name"], strrpos($_FILES["menu_doc_file"]["name"], ".") +1);
      $doc_hash = md5(microtime());

      $sql = " insert into menu_docs
               (doc_name, doc_type, doc_size, doc_hash, doc_mimetype)
               VALUES
               ('{$_FILES["menu_doc_file"]["name"]}', '{$doc_type}',
                 {$_FILES["menu_doc_file"]["size"]}, '{$doc_hash}',
                '{$_FILES["menu_doc_file"]["type"]}')";
      $res = $CON->no_result($sql);
      
      if($res)
      {
         $sql = " select MAX(id) 'doc_id'
                  from menu_docs";
         $doc_id = $CON->select($sql);
         $doc_id = $doc_id[0]["doc_id"];

         $res = move_uploaded_file($_FILES["menu_doc_file"]["tmp_name"], "./docs/{$doc_id}.{$doc_hash}");
         
         if(!$res)
            $error = true;
      }
      else
         $error = true;
   }

   if(!$error)
   {
      $doc_id = (int)$doc_id;

      $sql = " insert into menu_items
               (menu_name1, menu_name2, menu_name3, menu_link, menu_link_mod, menu_adm,
                menu_public, menu_parent, menu_order, menu_behavior, menu_icon, 
                menu_docid, menu_desc, menu_crtusr, menu_crtdat, menu_mod_params, menu_trancode,
                menu_spacer, menu_appmode)
               VALUES
               ('{$_REQUEST["menu_name1"]}', '{$_REQUEST["menu_name2"]}', '{$_REQUEST["menu_name3"]}',
                 {$_REQUEST["menu_link"]}, '{$_REQUEST["menu_link_mod"]}',
                 {$_REQUEST["menu_adm"]}, {$_REQUEST["menu_public"]}, {$_REQUEST["parentid"]},
                 {$_REQUEST["menu_order"]}, {$_REQUEST["menu_behavior"]}, '{$_REQUEST["menu_icon"]}',
                 {$doc_id}, '{$_REQUEST["menu_desc"]}', {$_SESSION["user_id"]}, {$currtme},
                 '{$_REQUEST["menu_mod_params"]}', '{$_REQUEST["menu_trancode"]}', {$_REQUEST["menu_spacer"]}, {$_SESSION["menu_appmode"]})";
      $res = $CON->no_result($sql);

      $sql = " select MAX(id) 'newid'
               from menu_items";
      $newid = $CON->select($sql);
      $newid = $newid[0]["newid"];

      if(is_array($_REQUEST["groupids"]))
      {
         foreach($_REQUEST["groupids"] AS $groupid)
         {
            $sql = " insert into group_menu_items
                     (group_id, menu_id)
                     VALUES
                     ({$groupid}, {$newid})";
            $CON->no_result($sql);
         }
      }

      if(is_array($_REQUEST["userids"]))
      {
         foreach($_REQUEST["userids"] AS $userid)
         {
            $sql = " insert into user_menu_items
                     (user_id, menu_id)
                     VALUES
                     ({$userid}, {$newid})";
            $CON->no_result($sql);
         }
      }

      if($res)
      {  ?>
         <script language="JavaScript">
            parent.location.href='index.php?redirectmid=<?=$_REQUEST["mid"]?>&exec=add&parentid=<?=$_REQUEST["parentid"]?>&throwSavemsg=1';
            location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&parentid=<?=$_REQUEST["parentid"]?>&throwSavemsg=1'
         </script>
         <?php
      }
   }
   else
      $savemsg = getSaveMessage(false);
}

//----------------------------------------------------------------------------------
elseif($_REQUEST["showItem"] != "" && $_REQUEST["subexec"] == "save")
{
   if($_REQUEST["menu_link"] == 2 &&
      $_FILES["menu_doc_file"]["name"] != "" &&
      $_FILES["menu_doc_file"]["tmp_name"] != "" &&
      $_FILES["menu_doc_file"]["error"] == 0 &&
      $_FILES["menu_doc_file"]["size"] > 0)
   {
      $doc_type = substr($_FILES["menu_doc_file"]["name"], strrpos($_FILES["menu_doc_file"]["name"], ".") +1);

      $_FILES["menu_doc_file"]["name"] = addslashes($_FILES["menu_doc_file"]["name"]);

      $sql = " select t1.menu_docid, t2.doc_hash
               from menu_items t1, menu_docs t2
               where
               t1.menu_docid = t2.id and
               t1.id = {$_REQUEST["showItem"]}";
      $doc_upd = $CON->select($sql);
      
      $menu_docid    = (int)$doc_upd[0]["menu_docid"];
      $menu_dochash  = $doc_upd[0]["doc_hash"];

      $sql = " update menu_docs
               set
               doc_name       = '{$_FILES["menu_doc_file"]["name"]}',
               doc_type       = '{$doc_type}',
               doc_size       =  {$_FILES["menu_doc_file"]["size"]},
               doc_mimetype   = '{$_FILES["menu_doc_file"]["type"]}'
               where
               id = {$menu_docid}";
      $res = $CON->no_result($sql);
      
      if($res)
      {
         unlink("./docs/{$menu_docid}.{$menu_dochash}");

         $res = move_uploaded_file($_FILES["menu_doc_file"]["tmp_name"], "./docs/{$menu_docid}.{$menu_dochash}");
         
         if(!$res)
            $error = true;
      }
      else
         $error = true;
   }

   if(!$error)
   {
      if($_REQUEST["menu_link"] == 2)
         $_REQUEST["menu_link_mod"] = "structure/document.php";

      $sql = " update menu_items
               set
               menu_name1        = '{$_REQUEST["menu_name1"]}',
               menu_name2        = '{$_REQUEST["menu_name2"]}',
               menu_name3        = '{$_REQUEST["menu_name3"]}',
               menu_adm          =  {$_REQUEST["menu_adm"]},
               menu_public       =  {$_REQUEST["menu_public"]},
               menu_order        =  {$_REQUEST["menu_order"]},
               menu_behavior     =  {$_REQUEST["menu_behavior"]},
               menu_link_mod     = '{$_REQUEST["menu_link_mod"]}',
               menu_desc         = '{$_REQUEST["menu_desc"]}',
               menu_icon         = '{$_REQUEST["menu_icon"]}',
               menu_parent       =  {$_REQUEST["parentid"]},
               menu_updusr       =  {$_SESSION["user_id"]},
               menu_upddat       =  {$currtme},
               menu_mod_params   = '{$_REQUEST["menu_mod_params"]}',
               menu_trancode     = '{$_REQUEST["menu_trancode"]}',
               menu_spacer       = {$_REQUEST["menu_spacer"]}
               where
               id = {$_REQUEST["showItem"]}";
      $res = $CON->no_result($sql);

      if($res)
      {  ?>
         <script language="JavaScript">
            parent.location.href='index.php?redirectmid=<?=$_REQUEST["mid"]?>&showItem=<?=$_REQUEST["showItem"]?>';
            location.href='index.php?mid=<?=$_REQUEST["mid"]?>&showItem=<?=$_REQUEST["showItem"]?>&throwSavemsg=1'
         </script>
         <?php
      }
   }
   else
      $savemsg = getSaveMessage(false);
}
if($_REQUEST["throwSavemsg"] == "1")
   $savemsg = getSaveMessage(true);
?>
<table cellpadding="0" cellspacing="0" width="822" border="0" style="table-layout:fixed;margin-left:-5px">
<colgroup>
   <col width="235" valign="top">
   <col width="15">
   <col width="650" valign="top">
</colgroup>
<tr>
   <td valign="top" class="content_rowl">
      <br>
      <?php
      $_SESSION["_MENU"]->printStruct(true);
      ?>
   </td>
   <td></td>
   <td valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <form action="index.php" method="post" class="fokusfirst" enctype="multipart/form-data" name="xform_manage"
      onsubmit="return checkstructform(new Array(<?=$chkfieldname?>))">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="showItem" value="<?=$_REQUEST["showItem"]?>">
      <input type="hidden" name="subexec" value="save">
      <tr>
         <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["STRUCT"][0]?></b></td>
         <td align="right"><?=$savemsg?></td>
      </tr>
      <tr>
         <td class="content_headerline" colspan="2">&nbsp;</td>
      </tr>
      </table>
      <?php
      //----------------------------------------------------------------------------------
      if($_REQUEST["exec"] == "add" || $_REQUEST["showItem"] != "")
      {
         if($_REQUEST["showItem"] != "")
         {
            $sql = " select t1.*,
                     t2.user_lastname 'menu_crtusr_name',
                     t3.user_lastname 'menu_updusr_name'
                     from menu_items t1
                     LEFT OUTER JOIN user t2 ON t1.menu_crtusr = t2.id
                     LEFT OUTER JOIN user t3 ON t1.menu_updusr = t3.id
                     where
                     t1.id = {$_REQUEST["showItem"]}";
            $itemdata = $CON->select($sql);
            $itemdata = $itemdata[0];
         }

         $chkfieldname = "this.menu_name{$_SESSION["_CONF"]["conf_lang"]}";
         ?>
         <?=Nifty_printH("box1", "100%")?>
         <table cellpadding="2" cellspacing="0" class="content_table" width="100%">
         
         <colgroup>
            <col width="150">
            <col>
         </colgroup>
         <tr>
            <td colspan="2" class="content_tbl_header">
               <b>
               <?php
               if($_REQUEST["exec"] == "add")
                  echo $_LANG["MODULE"]["STRUCT"][1];
               else
                  echo $_LANG["MODULE"]["STRUCT"][2];
               ?>
               </b>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][3]?></td>
            <td class="content_row">
               <select class="text" name="parentid" style="width:450px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               if($_REQUEST["exec"] == "add")
                  $_SESSION["_MENU"]->printSelectboxStruct($_REQUEST["parentid"]);
               else
                  $_SESSION["_MENU"]->printSelectboxStruct($itemdata["menu_parent"]);
               ?>
               </select>
            </td>
         </tr>
         <tr <?php if($_SESSION["_CONF"]["conf_lang"] != "1") echo "style='display:none'"?>>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][5]?></td>
            <td class="content_row">
               <input name="menu_name1" type="text" class="text" style="width:450px" value="<?=$itemdata["menu_name1"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr <?php if($_SESSION["_CONF"]["conf_lang"] != "2") echo "style='display:none'"?>>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][5]?></td>
            <td class="content_row">
               <input name="menu_name2" type="text" class="text" style="width:450px" value="<?=$itemdata["menu_name2"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr <?php if($_SESSION["_CONF"]["conf_lang"] != "3") echo "style='display:none'"?>>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][5]?></td>
            <td class="content_row">
               <input name="menu_name3" type="text" class="text" style="width:450px" value="<?=$itemdata["menu_name3"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][6]?></td>
            <td class="content_row">
               <?php
               $style_doc_upload    = "display:none";
               $style_doc_behavior  = "display:none";
               $style_doc_desc      = "display:none";
               $style_mod_path      = "";
               $style_menu_symbol   = "";
               $style_menu_spacer   = "display:none";
               
               if($_REQUEST["exec"] == "add")
               {  ?>
                  <input type="radio" name="menu_link" value="1" onclick="setEntryType(1)" checked> <?=$_LANG["MODULE"]["STRUCT"][9]?>
                  <input type="radio" name="menu_link" value="0" onclick="setEntryType(0)"> <?=$_LANG["MODULE"]["STRUCT"][7]?>
                  <input type="radio" name="menu_link" value="2" onclick="setEntryType(2)"> <?=$_LANG["MODULE"]["STRUCT"][8]?>
                  <?php
               }
               elseif($_REQUEST["showItem"] != "" && $itemdata["menu_link"] == "0")
               {  ?>
                  <input type="radio" name="menu_link" value="0" onclick="setEntryType(0)" checked> <?=$_LANG["MODULE"]["STRUCT"][7]?>
                  <?php
               }
               elseif($_REQUEST["showItem"] != "" && $itemdata["menu_link"] == "2")
               {  ?>
                  <input type="radio" name="menu_link" value="2" onclick="setEntryType(2)" checked> <?=$_LANG["MODULE"]["STRUCT"][8]?>
                  <?php
                  $style_doc_upload    = "";
                  $style_doc_behavior  = "";
                  $style_doc_desc      = "";
                  $style_menu_symbol   = "";
                  $style_menu_spacer   = "";
               }
               elseif($_REQUEST["showItem"] != "" && $itemdata["menu_link"] == "1")
               {  ?>
                  <input type="radio" name="menu_link" value="1" onclick="setEntryType(1)" checked> <?=$_LANG["MODULE"]["STRUCT"][9]?>
                  <?php
                  $style_mod_path      = "";
                  $style_menu_symbol   = "";
                  $style_menu_spacer   = "";
               }
               ?>
            </td>
         </tr>
         <?php
         if($_REQUEST["showItem"] != "" && $itemdata["menu_link"] == "2")
         {

            // select document for menu item
            $sql = " select *
                     from menu_docs
                     where
                     id = {$itemdata["menu_docid"]}";
            $currdoc = $CON->select($sql);
            $currdoc = $currdoc[0];
            ?>
            <tr>
               <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][10]?></td>
               <td class="content_row">
                  <iframe id="docfrm" src="" width="1" height="1" frameborder="0" marginheight="0" marginwidth="0"></iframe>
                  <input type="button" class="button" value="<?=$currdoc["doc_name"]?>"
                  onclick="document.all.docfrm.src='./libs/modules/structure/document_file.php?type=0&id=<?=$currdoc["id"]?>&hash=<?=$currdoc["doc_hash"]?>&mime=<?=$currdoc["doc_mimetype"]?>&name=<?=$currdoc["doc_name"]?>'"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
               </td>
            </tr>
            <?php
         }
         ?>
         <tr style="<?=$style_doc_upload?>" id="doc_upload">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][11]?></td>
            <td class="content_row">
               <input class="text" type="file" name="menu_doc_file" style="width:450px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr style="<?=$style_doc_behavior?>" id="doc_behavior">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][12]?></td>
            <td class="content_row">
               <input type="radio" name="menu_behavior" value="0" <?php if($_REQUEST["exec"] == "add" || ($_REQUEST["showItem"] != "" && $itemdata["menu_behavior"] == "0")) echo "checked" ?>> Download
               <input type="radio" name="menu_behavior" value="1" <?php if($_REQUEST["showItem"] != "" && $itemdata["menu_behavior"] == "1") echo "checked" ?>> Embedded
            </td>
         </tr>
         <tr style="<?=$style_doc_desc?>" id="doc_desc">
            <td class="content_row" valign="top">Descripcion</td>
            <td class="content_row">
               <textarea class="text" name="menu_desc" style="width:450px; height:80px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$itemdata["menu_desc"]?></textarea>
            </td>
         </tr>
         <tr style="<?=$style_mod_path?>" id="mod_path">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][13]?></td>
            <td class="content_row"><nobr>
               <table border="0" cellpadding="0" cellspacing="0" width="450">
               <tr>
                  <td>
                     <input class="text" type="text" name="menu_link_mod" style="width:355px" value="<?=$itemdata["menu_link_mod"]?>"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  </td>
                  <td width="90">
                     <ul class="postnav">
                        <a href="javascript: openDirectoryWindow()"><?=$_LANG["FORM"]["BUTTON"][8]?></a>
                     </ul>
                  </td>
               </tr>
               </table>
               </nobr>
            </td>
         </tr>
         <tr style="<?=$style_mod_path?>" id="mod_params">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][41]?></td>
            <td class="content_row">
               <input class="text" type="text" name="menu_mod_params" style="width:450px" value="<?=$itemdata["menu_mod_params"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr style="<?=$style_mod_path?>" id="mod_trancode">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][42]?></td>
            <td class="content_row">
               <input name="menu_trancode" type="text" class="text" style="width:100px" value="<?=$itemdata["menu_trancode"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <?php
         $previmg = "./images/menu/menu_prg.gif";

         if($_REQUEST["showItem"] != "" && $itemdata["menu_link"] == "2")
            $previmg = "./images/menu/menu_doc.gif";
         
         if(trim($itemdata["menu_icon"]) != "")
            $previmg = "./images/menu/icons/{$itemdata["menu_icon"]}";
         ?>
         <tr style="<?=$style_menu_symbol?>" id="menu_symbol">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][32]?></td>
            <td class="content_row">
               <table border="0" cellpadding="0" cellspacing="0">
               <tr>
                  <td>
                     <table border="0" cellpadding="2" cellspacing="0" class="content_table">
                     <tr>
                        <td width="20" align="center" valign="middle">
                           <img id="idx_img_icon" src="<?=$previmg?>">
                        </td>
                     </tr>
                     </table>
                  </td>
                  <td width="450">
                     <table border="0" cellpadding="0" cellspacing="0" width="100%">
                     <tr>
                        <td width="100">
                           <ul class="postnav">
                              <a href="javascript: openSymbolWindow()"><?=$_LANG["FORM"]["BUTTON"][8]?></a>
                           </ul>
                        </td>
                        <td style="padding-left:5px">
                           <input name="menu_icon" type="text" class="text" style="width:320px" value="<?=$itemdata["menu_icon"]?>">
                        </td>
                     </tr>
                     </table>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][14]?></td>
            <td class="content_row">
               <input type="radio" name="menu_adm" value="0" <?php if($_REQUEST["exec"] == "add" || ($_REQUEST["showItem"] != "" && $itemdata["menu_adm"] == "0")) echo "checked" ?>> <?=$_LANG["FORM"]["RADIO"][1]?>
               <input type="radio" name="menu_adm" value="1" <?php if($_REQUEST["showItem"] != "" && $itemdata["menu_adm"] == "1") echo "checked" ?>> <?=$_LANG["FORM"]["RADIO"][0]?>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][15]?></td>
            <td class="content_row">
               <input type="radio" name="menu_public" value="0" <?php if($_REQUEST["exec"] == "add" || ($_REQUEST["showItem"] != "" && $itemdata["menu_public"] == "0")) echo "checked" ?>> <?=$_LANG["FORM"]["RADIO"][1]?>
               <input type="radio" name="menu_public" value="1" <?php if($_REQUEST["showItem"] != "" && $itemdata["menu_public"] == "1") echo "checked" ?>> <?=$_LANG["FORM"]["RADIO"][0]?>
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][16]?></td>
            <td class="content_row">
               <input name="menu_order" type="text" class="text" style="width:40px" value="<?=$itemdata["menu_order"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr style="display:none" id="id_menu_spacer">
            <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][45]?></td>
            <td class="content_row">
               <input name="menu_spacer" type="checkbox" value="1"
               <?php if((int)$itemdata["menu_spacer"]) echo "checked" ?>>
            </td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         if($_REQUEST["showItem"] != "")
         {  ?>
            <tr>
               <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][17]?></td>
               <td class="content_row"><?=$itemdata["menu_crtusr_name"]?></td>
            </tr>
            <tr>
               <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][18]?></td>
               <td class="content_row"><?=displayDate($itemdata["menu_crtdat"], true)?></td>
            </tr>
            <?php
            
            if($itemdata["menu_updusr_name"] != "")
            {  ?>
               <tr>
                  <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][19]?></td>
                  <td class="content_row"><?=$itemdata["menu_updusr_name"]?></td>
               </tr>
               <tr>
                  <td class="content_row"><?=$_LANG["MODULE"]["STRUCT"][20]?></td>
                  <td class="content_row"><?=displayDate($itemdata["menu_upddat"], true)?></td>
               </tr>
               <?php
            }
         }
         //----------------------------------------------------------------------------------
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?=Nifty_printH("boxopt_b", "100%")?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <?php
            if($_REQUEST["showItem"] != "")
            {  ?>
               <td width="130">
                  <ul class="postnav_del">
                     <a href="javascript: deactivateFormChange()"
                     onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&deleteItem=<?=$_REQUEST["showItem"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
                  </ul>
               </td>
               <?php
            }
            ?>
            <td>&nbsp;</td>
            <td align="right" width="130">
               <ul class="postnav_save">
                  <a href="javascript: submitForm(document.xform_manage)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
               </ul>
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         <br>
         <?php
         //----------------------------------------------------------------------------------
         ?>
         <?=Nifty_printH("box2", "100%")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td style="cursor:pointer" class="content_tbl_header"
            onclick="showObject(document.all.idx_tbl_grp); showObject(document.all.idx_tbl_grp_btn)">
               <img src="./images/content/content_plus.gif">
               <b><?=$_LANG["MODULE"]["STRUCT"][21]?></b></td>
         </tr>
         </table>
         <table id="idx_tbl_grp" border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="30">
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][22]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][23]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][24]?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         if($_REQUEST["showItem"] != "")
            $sql = " select t1.id, t1.group_name, count(t2.user_id) 'usercount', count(t3.group_id) 'group_active'
                     from `group` t1
                     LEFT OUTER JOIN user_group t2 ON t1.id = t2.group_id
                     LEFT OUTER JOIN group_menu_items t3 ON (t1.id = t3.group_id and t3.menu_id = {$_REQUEST["showItem"]})
                     where
                     t1.group_status = 1
                     group by t1.id, t1.group_name
                     order by t1.group_name";
                     
         //----------------------------------------------------------------------------------
         else
            $sql = " select t1.id, t1.group_name, count(t2.user_id) 'usercount'
                     from `group` t1
                     LEFT OUTER JOIN user_group t2 ON t1.id = t2.group_id
                     where
                     t1.group_status = 1
                     group by t1.id, t1.group_name
                     order by t1.group_name";

         $groups = $CON->select($sql);

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($groups) && $groups != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" align="center">
                  <input type="checkbox" name="groupids[]" value="<?=$groups[$x]["id"]?>"
                  <?php if((int)$groups[$x]["group_active"] > 0) echo "checked"?>>
               </td>
               <td class="content_row"><?=$groups[$x]["group_name"]?></td>
               <td class="content_row"><?=(int)$groups[$x]["usercount"]?></td>
            </tr>
            <?php
         }
         //----------------------------------------------------------------------------------
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <table id="idx_tbl_grp_btn" border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td>&nbsp;</td>
            <td align="right" width="130" style="padding-right:10px">
               <ul class="postnav_save">
                  <a href="javascript: submitForm(document.xform_manage)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
               </ul>
            </td>
         </tr>
         </table>
         <br>
         <?php
         //----------------------------------------------------------------------------------
         ?>
         <?=Nifty_printH("box3", "100%")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td style="cursor:pointer" class="content_tbl_header"
            onclick="showObject(document.all.idx_tbl_usr); showObject(document.all.idx_tbl_usr_btn)">
               <img src="./images/content/content_plus.gif">
               <b><?=$_LANG["MODULE"]["STRUCT"][29]?></b>
            </td>
         </tr>
         </table>
         <table id="idx_tbl_usr" border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="30">
            <col>
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][22]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][25]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][26]?></td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][27]?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         if($_REQUEST["showItem"] != "")
            $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_type,
                     count(t2.group_id) 'groupcount', count(t3.user_id) 'user_active'
                     from user t1
                     LEFT OUTER JOIN user_group t2 ON t1.id = t2.user_id
                     LEFT OUTER JOIN user_menu_items t3 ON (t1.id = t3.user_id and t3.menu_id = {$_REQUEST["showItem"]})
                     where
                     t1.user_status = 1
                     group by t1.id, t1.user_firstname, t1.user_lastname, t1.user_type
                     order by t1.user_firstname, t1.user_lastname";
         
         //----------------------------------------------------------------------------------
         else
            $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t1.user_type,
                     count(t2.group_id) 'groupcount'
                     from user t1
                     LEFT OUTER JOIN user_group t2 ON t1.id = t2.user_id
                     where
                     t1.user_status = 1
                     group by t1.id, t1.user_firstname, t1.user_lastname, t1.user_type
                     order by t1.user_firstname, t1.user_lastname";

         $users = $CON->select($sql);

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($users) && $users != false; $x++)
         {
            if((int)$users[$x]["user_type"] == 1)
               $utype = $_LANG["MODULE"]["STRUCT"][30];
            else
               $utype = $_LANG["MODULE"]["STRUCT"][31];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" align="center">
                  <input type="checkbox" name="userids[]" value="<?=$users[$x]["id"]?>"
                  <?php if((int)$users[$x]["user_active"] > 0) echo "checked"?>>
               </td>
               <td class="content_row"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
               <td class="content_row"><?=$utype?></td>
               <td class="content_row"><?=(int)$users[$x]["groupcount"]?></td>
            </tr>
            <?php
         }

         //----------------------------------------------------------------------------------
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <table id="idx_tbl_usr_btn" border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td>&nbsp;</td>
            <td align="right" width="130" style="padding-right:10px">
               <ul class="postnav_save">
                  <a href="javascript: submitForm(document.xform_manage)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
               </ul>
            </td>
         </tr>
         </form>
         </table>
         <br>
         <?php
      }
      else
         echo "<b class='content_message'>{$_LANG["MODULE"]["STRUCT"][28]}</b>";
      ?>
   </td>
</tr>
</table>