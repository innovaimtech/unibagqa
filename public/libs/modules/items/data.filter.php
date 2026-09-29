<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $sql = " delete from item_filter
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "fil_id_") !== false && strpos($reqkey, "fil_id_") == 0)
      {
         $idx   = substr($reqkey, strpos($reqkey, "_") +1);
         $idx2  = explode("_", $idx);
         $filid = $idx2[1];
         $posid = $idx2[2];
         $idx = $filid."_".$posid;

         $_REQUEST["fil_id_{$idx}"] = (int)$_REQUEST["fil_id_{$idx}"];

         //----------------------------------------------------------------------------------
         if($_REQUEST["fil_id_{$idx}"])
         {
            $sql = " insert into item_filter
                     (item_id, fil_id, pos_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$filid}, {$posid})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from filters
         where
         fil_status = 1
         order by fil_name";
$filters = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from item_filter t1
         where
         t1.item_id = {$_REQUEST["id"]}
         order by t1.id";
$tmpitempos = $CON->select($sql);

foreach($tmpitempos AS $pos)
   $itempos[$pos["pos_id"]] = $pos;
?>
<form action="index.php" method="post" class="fokusfirst" enctype="multipart/form-data" name="xform_pix">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?php
foreach($filters AS $filter)
{
   $sql = " select *
            from filters_pos
            where
            fil_id = {$filter["id"]}
            order by pos_filter";
   $posdata = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="6" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" colspan="5"><?=$filter["fil_name"]?></td>
   </tr>
   <?php
   $ucounter = 0;
   for($x = 0; $x < count($posdata); $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($ucounter)?>">
      <?php
      $next = $x + 5;
      for($y = $x; $y < $next; $y++)
      {
         $posfil = $posdata[$y];
         ?>
         <td class="content_row_os" align="left">
            <?php
            if((int)$posfil["id"])
            {  ?>
               <input type="checkbox" name="fil_id_<?=$filter["id"]?>_<?=$posfil["id"]?>" value="1" style="vertical-align:middle;"
               <?php if((int)$itempos[$posfil["id"]]["id"]) echo "checked"?>>&nbsp;<?=$posfil["pos_filter"]?>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <?php
      }
      $x = $y-1;
      ?>
      </tr>
      <?php
      $ucounter++;
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pix)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>