<?php
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["ag_date"]          = trim(addslashes($_REQUEST["ag_date"]));
   $_REQUEST["ag_notes"]         = trim(addslashes($_REQUEST["ag_notes"]));
   $_REQUEST["ag_equipotype_id"] = (int)$_REQUEST["ag_equipotype_id"];
   $_REQUEST["sql_equipoid"]     = (int)$_REQUEST["sql_equipoid"];
   $_REQUEST["ag_equipo_mantid"] = (int)$_REQUEST["ag_equipo_mantid"];

   $ag_date_stamp = explode(".", $_REQUEST["ag_date"]);
   $ag_date_stamp = mktime(15, 0, 0, $ag_date_stamp[1], $ag_date_stamp[0], $ag_date_stamp[2]);
   if((int)$_REQUEST["id"])
   {
      $sql = " update prod_agenda_mantencion
               set
               ag_date           = '{$_REQUEST["ag_date"]}', 
               ag_date_stamp     = {$ag_date_stamp},
               ag_equipo_mantid  = {$_REQUEST["ag_equipo_mantid"]}, 
               ag_notes          = '{$_REQUEST["ag_notes"]}'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   else
   {
      $sql = " insert into prod_agenda_mantencion
               (ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_plantaid,
                ag_crtdat, ag_crtusr, ag_equipo_mantid, ag_notes)
               VALUES
               ('{$_REQUEST["ag_date"]}', {$ag_date_stamp}, {$_REQUEST["sql_equipoid"]},
                {$_REQUEST["ag_equipotype_id"]}, {$_REQUEST["sql_plantaid"]}, {$currtme},
                {$_SESSION["user_id"]}, {$_REQUEST["ag_equipo_mantid"]}, '{$_REQUEST["ag_notes"]}')";
      $CON->no_result($sql);
   }
   ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         parent.document.xform_itemsearch.submit();
      });
   </script>
   <?php
}

//----------------------------------------------------------------------------------
if(!(int)$_REQUEST["id"])
{
   $data["ag_date"] = date("d.m.Y");
}
else
{
   $sql = " select t1.*, t2.type_ant_title, t3.equipo_name
            from prod_agenda_mantencion t1
            LEFT OUTER JOIN equipo_type t2   ON t1.ag_equipotype_id = t2.id
            LEFT OUTER JOIN equipo t3        ON t1.ag_equipo_id = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}

//----------------------------------------------------------------------------------
$sql = " select *
         from equipo_type
         where
         type_ant_status > 0
         order by type_ant_title";
$equipotypes = $CON->select($sql);
for($x = 0; $x < count($equipotypes) && $equipotypes != false; $x++)
{
   $sql = " select *
            from equipo
            where
            equipo_status     > 0 and
            equipo_type_id    = {$equipotypes[$x]["id"]} and
            equipo_planta_id  = {$_REQUEST["sql_plantaid"]}
            order by equipo_name";
   $equipos = $CON->select($sql);
   if(count($equipos) && $equipos != false)
   {
      $_SELEQUIPOTYPES[$equipotypes[$x]["id"]] = $equipotypes[$x]["type_ant_title"];
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from equipo_manttype
         where
         mant_status > 0
         order by mant_title";
$manttypes = $CON->select($sql);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="iframe.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst"
onsubmit="return checkform(new Array(this.ag_date, this.ag_equipotype_id, this.sql_equipoid, this.ag_equipo_mantid))">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="execsave" value="">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="sql_plantaid" id="sql_plantaid" value="<?=$_REQUEST["sql_plantaid"]?>">
<input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box2", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col width="">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de la mantención</td>
</tr>
<tr>
   <td class="content_rowl">Fecha</td>
   <td class="content_row">
      <input type="text" style="width:75px" id="ag_date" name="ag_date" readonly
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=$data["ag_date"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Tipo máquina</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="ag_equipotype_id" id="ag_equipotype_id"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="jqLoadPlantaEquipos(this.value)">
         <?php
         if((int)$_REQUEST["id"])
         {  ?>
            <option value="<?=$data["ag_equipotype_id"]?>"><?=$data["type_ant_title"]?></option>
            <?php
         }
         else
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach(array_keys($_SELEQUIPOTYPES) AS $etypeid)
            {  ?>
               <option value="<?=$etypeid?>"><?=$_SELEQUIPOTYPES[$etypeid]?></option>
               <?php
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Máquina</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="sql_equipoid" id="sql_equipoid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if((int)$_REQUEST["id"])
         {  ?>
            <option value="<?=$data["ag_equipo_id"]?>"><?=$data["equipo_name"]?></option>
            <?php
         }
         else
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="ag_equipo_mantid" id="ag_equipo_mantid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($manttypes AS $manttype)
         {  ?>
            <option value="<?=$manttype["id"]?>"
            <?if($data["ag_equipo_mantid"] == $manttype["id"]) echo "selected"?>>
               <?=$manttype["mant_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Comentarios</td>
   <td class="content_row">
      <textarea name="ag_notes" class="text" style="width:100%;height:90px"><?=$data["ag_notes"]?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "100%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>