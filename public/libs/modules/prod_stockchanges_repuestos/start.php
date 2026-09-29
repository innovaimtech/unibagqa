<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "1191";
   require_once("edit.php");
}
else
{

   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, false);
   $sql = " select id, st_name, st_shop_id
            from company_shops_storehouses
            where
            st_status  = 1 and
            st_repuestos_act = 1
            order by st_name";
   $stids = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockchanges_issues
            where
            stkis_status = 1 and
            id IN (1,2)
            order by stkis_negative desc, stkis_title";
   $issues = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " SELECT t1.*, t2.type_name
            FROM workers t1
            LEFT OUTER JOIN workers_types t2 ON t1.wrk_cargoid = t2.id
            WHERE
            t1.wrk_status           > 0 and
            t1.wrk_turno_state      = 1 and
            t1.wrk_uid              > 0
            order by t1.wrk_lastname, t1.wrk_firstname";
   $workers = $CON->select($sql);

   $plantas = getPlantas($CON);

   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <script language="JavaScript">
      function setCompanyShop(companyidx)
      {
         var obj = document.all.sid;
         obj.options.length = 0;
         var xcnt = 0;
         <?php
         foreach($shops AS $shop)
         {  ?>
            if(companyidx == '<?=$shop["shop_company_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
               newOpt.value   = '<?=$shop["id"]?>';
               obj.options[newIndex] = newOpt;
               if(!xcnt)
                  setMyStorehouses('<?=$shop["id"]?>')
               xcnt++;
            }
            <?php
         }
         ?>
      }
      function setMyStorehouses(shopid)
      {
         var obj = document.all.sthid;
         obj.options.length = 0;
         <?php
         foreach($stids AS $stid)
         {  ?>
            if(shopid == '<?=$stid["st_shop_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($stid["st_name"])?>');
               newOpt.value   = '<?=$stid["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="650">
   <form action="index.php" method="post" class="fokusfirst" name="xform_shp"
    onsubmit="return checkform(new Array(this.cid, this.sid, this.sthid, this.issue_id, this.sth_planta_id, this.sth_assign_wrkid))">
   <input type="hidden" name="exec" value="start">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Agregar ajuste de stock</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos de ajuste</td>
   </tr>
   <tr>
      <td class="content_rowl">Empresa *</td>
      <td class="content_row">
         <select class="text" name="cid" style="width:500px"
         onchange="setCompanyShop(this.value)">
            <?php
            foreach($companies AS $company)
            {  ?>
               <option value="<?=$company["id"]?>"><?=$company["company_short"]?></option><?php
            }
            ?>
         </select>
         <?php
         $_SESSION["JSEXEC"] .= "; setCompanyShop({$companies[0]["id"]}); jqLoadPlantaEquiposFromPlantaId($('#sth_planta_id').val());";
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Sucursal *</td>
      <td class="content_row">
         <select class="text" name="sid" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="setMyStorehouses(this.value)">
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Bodega *</td>
      <td class="content_row">
         <select class="text" name="sthid" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Motivo *</td>
      <td class="content_row">
         <select class="text" name="issue_id" style="width:500px">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($issues AS $issue)
            {  ?>
               <option value="<?=$issue["id"]?>" <?if($issue["id"] == 1) echo "selected";?> ><?=$issue["stkis_title"]?></option><?php
            }
            ?>
         </select>
      </td>
   </tr>
   
   <tr>
      <td class="content_rowl">Planta asociada *</td>
      <td class="content_row">
         <select class="text" name="sth_planta_id" id="sth_planta_id" style="width:500px"
         onchange="jqLoadPlantaEquiposFromPlantaId(this.value);">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($plantas AS $planta)
            {  ?>
               <option value="<?=$planta["id"]?>" <?if(count($plantas) == 1) echo "selected"?>><?=$planta["planta_name"]?></option><?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Máquina asociada</td>
      <td class="content_row">
         <select class="text" name="sth_assign_equipoid" id="sth_assign_equipoid" style="width:500px">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Entregado a *</td>
      <td class="content_row">
         <select class="text" name="sth_assign_wrkid" style="width:500px">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($workers AS $worker)
            {  ?>
               <option value="<?=$worker["id"]?>"><?=$worker["wrk_lastname"]?>, <?=$worker["wrk_firstname"]?></option><?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Venta Interna</td>
      <td class="content_row">
         <input type="checkbox" value="1" name="stk_isventainterna"> Activado
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_shp)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}