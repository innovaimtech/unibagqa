<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
$noerrors   = false;
$img_status = "status_red.gif";
$doc_dir    = "./images/layout/";

$sql = " select *
         from design_layouts
         where
         id = {$_REQUEST["id"]}";
$layout = $CON->select($sql);
$layout = $layout[0];
?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header">Activar Layout</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="85%">
   <col width="15%">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Proceso de activación</td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["DESIGN"][26]?></td>
   <td class="content_row" align="center">
      <?php
      deleteFilesInDir($doc_dir);
      $counter = countFilesInDir($doc_dir);
      if($counter == 0)
      {
         $img_status = "status_green.gif";
         $noerrors   = true;
      }
      ?>
      <img src="./images/content/<?=$img_status?>">
   </td>
</tr>
</table>
<?php
ob_flush();flush();

if($noerrors)
{  ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85%">
      <col width="15%">
   </colgroup>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DESIGN"][27]?></td>
      <td class="content_row" align="center">
         <?php
         $noerrors   = false;
         $img_status = "status_red.gif";
         
         copy("./layouts/{$layout["layout_filename"]}", "{$doc_dir}{$layout["layout_filename"]}");

         if(file_exists("{$doc_dir}{$layout["layout_filename"]}") && filesize("{$doc_dir}{$layout["layout_filename"]}") > 0)
         {
            require_once("./libs/thirdparty/archive.php");
            $gzfile = new gzip_file("{$layout["layout_filename"]}");
            $gzfile->set_options(array('basedir' => $doc_dir, 'overwrite' => 1));
            $gzfile->extract_files();
            unlink("{$doc_dir}{$layout["layout_filename"]}");
            setFilePrivileges($doc_dir,"777");
            $counter = countFilesInDir($doc_dir);
            if((int)$counter)
            {
               $noerrors   = true;
               $img_status = "status_green.gif";
            }
         }
         ?>
         <img src="./images/content/<?=$img_status?>">
      </td>
   </tr>
   </table>
   <?php
   ob_flush();flush();
}

if($noerrors)
{  ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85%">
      <col width="15%">
   </colgroup>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DESIGN"][28]?></td>
      <td class="content_row" align="center">
         <?php
         $noerrors   = false;
         $sqlerrors  = false;
         $img_status = "status_red.gif";

         $sqldata = file("{$doc_dir}import.sql");
         unlink("{$doc_dir}import.sql");
         if(count($sqldata) > 0)
         {
            foreach($sqldata AS $sqlcommand)
            {
               $res = $CON->no_result($sqlcommand);
               if(!$res)
                  $sqlerrors = true;
            }
         }

         if(!$sqlerrors)
         {
            $noerrors   = true;
            $img_status = "status_green.gif";
         }
         ?>
         <img src="./images/content/<?=$img_status?>">
      </td>
   </tr>
   </table>
   <?php
   ob_flush();flush();
}

if($noerrors)
{  ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85%">
      <col width="15%">
   </colgroup>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["DESIGN"][29]?></td>
      <td class="content_row" align="center">
         <?php
         $sql = " update design_layouts
                  set layout_active = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
         $sql = " update design_layouts
                  set layout_active = 0
                  where
                  id != {$_REQUEST["id"]}";
         $CON->no_result($sql);
         ?>
         <img src="./images/content/status_green.gif">
      </td>
   </tr>
   </table>
   <?php
   ob_flush();flush();
}
?>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "400")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="right" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&tstamp=<?=time()?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>