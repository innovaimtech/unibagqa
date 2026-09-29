<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme       = time();
   $noerrors      = true;
   $table_counter = 0;
   $sql           = " show tables";
   $tables        = $CON->select($sql);
   
   foreach($tables AS $table)
   {
      if($key == "")
      {
         $key = array_keys($table);
         $key = $key[0];
      }
      $table_name    = $table[$key];
      $table_prefix  = substr($table_name, 0, strpos($table_name, "_") +1);
      if(strpos($table_name, "config_style_") !== false)
      {
         $pre_line = "";
         $suf_line = "";
         $sqlsave[$table_counter] = "truncate {$table_name}\ninsert into {$table_name} VALUES ";

         $sql = " select *
                  from {$table_name}";
         $data = $CON->select($sql);

         foreach($data AS $row)
         {
            $suf_line .= "(";
            foreach($row AS $col)
               $suf_line .= "'".addslashes($col)."',";
            $suf_line = substr($suf_line, 0, -1);
            $suf_line .= "), ";
         }
         $suf_line = substr($suf_line,0 ,-2);
         $sqlsave[$table_counter] .= "{$suf_line}";
         $table_counter++;
      }
   }

   $_REQUEST["layout_name"]  = trim(addslashes($_REQUEST["layout_name"]));
   
   $sql = " insert into design_layouts
            (layout_name, layout_active, layout_crtusr, layout_crtdat)
            VALUES
            ('{$_REQUEST["layout_name"]}', 1, {$_SESSION["user_id"]}, {$currtme})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from design_layouts";
      $thisid = $CON->select($sql);
      $thisid = $thisid[0]["thisid"];
   
      $doc_dir = "./images/layout/";
      
      $fp = fopen("{$doc_dir}import.sql", "w");
      if($fp)
      {
         foreach($sqlsave AS $sqlline)
            fwrite($fp, $sqlline."\n");
         fclose($fp);
   
   
         $gzfilename = "{$thisid}.".md5(microtime()).".tgz";
   
         require_once("./libs/thirdparty/archive.php");
         $gzfile = new gzip_file($gzfilename);
         $gzfile->set_options(array('basedir' => $doc_dir, 'overwrite' => 1, 'level' => 1));
         $gzfile->add_files(array("*"));
         $gzfile->create_archive();
         if(count($gzfile->errors) > 0)
            $noerrors = false;
         
         copy("{$doc_dir}{$gzfilename}", "./layouts/{$gzfilename}");
         unlink("{$doc_dir}import.sql");
         unlink("{$doc_dir}{$gzfilename}");
   
         if(file_exists("./layouts/{$gzfilename}") && filesize("./layouts/{$gzfilename}") > 0)
         {
            $sql = " update design_layouts
                     set
                     layout_filename = '{$gzfilename}',
                     layout_filesize = ".filesize("./layouts/{$gzfilename}")."
                     where
                     id = {$thisid}";
            $res = $CON->no_result($sql);
            if(!$res)
               $noerrors = false;
         }
         else
            $noerrors = false;
      }
      else
         $noerrors = false;

      if(!$noerrors)
      {
         $sql = " delete from design_layouts
                  where
                  id = {$thisid}";
         $CON->no_result($sql);
      }
      else
      {
         $sql = " update design_layouts
                  set
                  layout_active = 0
                  where
                  id != {$thisid}";
         $CON->no_result($sql);
      }
   }
   else
      $noerrors = false;

   $savemsg = getSaveMessage($noerrors);

   if($noerrors)
   {  ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&savemsg=1';
      </script>
      <?php
   }
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header">Guardar Layout</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" class="fokusfirst" name="xform_layoutcreate"
 onsubmit="return checkform(new Array(this.layout_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="createLayout">
<input type="hidden" name="subexec" value="save">
<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de Layout</td>
</tr>
<tr>
   <td class="content_row">Nombre</td>
   <td class="content_row">
      <input name="layout_name" type="text" class="text" style="width:300px" value=""
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_layoutcreate)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
