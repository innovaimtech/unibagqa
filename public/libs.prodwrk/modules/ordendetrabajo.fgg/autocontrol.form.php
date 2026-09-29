<?php
$_REQUEST["agid"] = (int)$_REQUEST["agid"];
/*?><script> alert("autocontrol <?php echo $_REQUEST["agid"]; ?>");</script><?php */
//----------------------------------------------------------------------------------
$sql = " select distinct t0.*,
                t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                t3x.id 'prdid', t0.ag_amount
         from prod_agenda t0
         INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
         INNER JOIN orders t1             ON t0.ag_reqid = t1.id
         LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
         LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
         LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
         INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
         INNER JOIN item t2x              ON t1x.item_id = t2x.id
         LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
         LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
         where
         t0.id = {$hasopenot["wok_ag_id"]}";
$agenda = $CON->select($sql);
$agenda = $agenda[0];

$printcolors   = "";
$entrega_vals  = "";

//----------------------------------------------------------------------------------
for($xx = 1; $xx <= 5; $xx++)
{
   if((int)$agenda["fab_print_colors_front_{$xx}"] || (int)$agenda["fab_print_colors_back_{$xx}"])
   {
      if((int)$agenda["fab_print_colors_front_{$xx}"] && !(int)$agenda["fab_print_colors_back_{$xx}"])
         $printcolors .= "Frente: {$agenda["fab_print_colordesc_{$xx}"]}, ";
      elseif(!(int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
         $printcolors .= "Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
      elseif((int)$agenda["fab_print_colors_front_{$xx}"] && (int)$agenda["fab_print_colors_back_{$xx}"])
         $printcolors .= "Frente/Dorso: {$agenda["fab_print_colordesc_{$xx}"]}, ";
   }
}
$printcolors = substr($printcolors, 0, -2);
$currtme = time();

//----------------------------------------------------------------------------------
if($_REQUEST["submode"] == "save")
{
   $sql = " delete from prod_worker_ot_autocontrol
            where
            ctr_init_id = {$hasopenot["id"]} and
            ctr_type    = 'worker'";
   $CON->no_result($sql);

   foreach($_REQUEST["baseacids"] AS $baseacid)
   {
      $ctr_res = (int)$_REQUEST["acids_{$baseacid}"];
      
      $sql = " insert into prod_worker_ot_autocontrol
               (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat)
               VALUES
               ({$hasopenot["id"]}, 'worker', {$baseacid}, {$ctr_res}, {$_SESSION["user_id"]}, {$currtme})";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["submode"] == "close")
{
   $sql = " delete from prod_worker_ot_autocontrol
            where
            ctr_init_id = {$hasopenot["id"]} and
            ctr_type    = 'supervisor'";
   $CON->no_result($sql);

   foreach($_REQUEST["baseacids"] AS $baseacid)
   {
      $sql = " insert into prod_worker_ot_autocontrol
               (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr)
               VALUES
               ({$hasopenot["id"]}, 'supervisor', {$baseacid}, 0, 0)";
      $CON->no_result($sql);
   }

   sendV2PordSupervisorNotify($CON, $hasopenot, $hasopeninit, $agenda);
   ?>
   <script language="Javascript">
      location.href = 'prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>';
   </script>
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["submode"] == "supersave")
{
   $_REQUEST["axx_username"]  = trim(addslashes($_REQUEST["axx_username"]));
   $_REQUEST["axx_pass"]      = md5(trim($_REQUEST["axx_pass"]));

   $sql = " select t1.*
            from user t1
            where
            t1.user_login  = '{$_REQUEST["axx_username"]}' and
            t1.user_pass   = '{$_REQUEST["axx_pass"]}' and
            t1.user_status = 1 and
            (
               t1.user_type = 1 or
               (
                  select count(*) 'cc'
                  from user_group t2
                  where
                  t2.user_id  = t1.id and
                  t2.group_id IN ({$_CONFIG["_PROD_SUPERVISOR_ROLEID"]})
               ) > 0
            ) ";
   $checkuser = $CON->select($sql);
   $checkuser = $checkuser[0];

   if((int)$checkuser["id"])
   {
      $sql = " update prod_worker_ot_autocontrol
               set
               ctr_res     = 1,
               ctr_ctrusr  = {$checkuser["id"]},
               ctr_ctrdat  = {$currtme}
               where
               ctr_init_id = {$hasopenot["id"]} and
               ctr_type    = 'supervisor'";
      $CON->no_result($sql);
      ?>
      <script language="Javascript">
         location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';
      </script>
      <?php
   }
   else
   {  ?>
      <script language="Javascript">
         alert("Usuario no valido para aprobaciones");
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$_CAN_SUPER_SEND = true;
$sql = " select *
         from prod_worker_ot_autocontrol
         where
         ctr_init_id = {$hasopenot["id"]} and
         ctr_type    = 'worker'";
$acrows = $CON->select($sql);
if(!$acrows || count($acrows) == 0)
   $_CAN_SUPER_SEND = false;
   
foreach($acrows AS $acrow)
{
   $_SELACROWS_WORKER[$acrow["ctr_acid"]] = (int)$acrow["ctr_res"];
   if(!$acrow["ctr_res"])
      $_CAN_SUPER_SEND = false;
}
?>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="6" cellspacing="0">
      <colgroup>
         <col width="120">
         <col width="40%">
         <col width="120">
         <col width="40%">
      </colgroup>
      <tr>
         <td class="tdleft">Nº OT</td>
         <td class="tdnrm"><?=$agenda["prd_number"]?></td>
         <td class="tdleft">Nº CC</td>
         <td class="tdnrm"><?=$agenda["req_number"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Cliente</td>
         <td class="tdnrm"><?=$agenda["cust_name"]?></td>
         <td class="tdleft">Producto</td>
         <td class="tdnrm"><?=$agenda["item_number_prod"]?> | <?=$agenda["item_title"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Medidas</td>
         <td class="tdnrm"><?=(int)$agenda["fab_med_width"]?> x <?=(int)$agenda["fab_med_height"]?></td>
         <td class="tdleft">Fuelle</td>
         <td class="tdnrm"><?=(int)$agenda["fab_med_fuelle"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Area</td>
         <td class="tdnrm"><?=(int)$agenda["fab_print_width"]?> x <?=(int)$agenda["fab_print_height"]?></td>
         <td class="tdleft">Tela</td>
         <td class="tdnrm"><?=$agenda["fabric_color"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Manillas</td>
         <td class="tdnrm"><?=$agenda["manilla_color"]?></td>
         <td class="tdleft">Colores</td>
         <td class="tdnrm"><?=$printcolors?></td>
      </tr>
      <tr>
         <td class="tdleft">Cantidad</td>
         <td class="tdnrm"><?=printPrice($agenda["ag_amount"])?></td>
         <td class="tdleft">&nbsp;</td>
         <td class="tdnrm">&nbsp;</td>
      </tr>
      </table>
   </td>
</tr>
</table>
<div style="clear:both;height:10px"></div>
<?php
$sql = " select count(*) 'cc'
         from prod_worker_ot_autocontrol
         where
         ctr_init_id = {$hasopenot["id"]} and
         ctr_type    = 'supervisor'";
$hasautocontrol_super_inputs = $CON->select($sql);
$hasautocontrol_super_inputs = (int)$hasautocontrol_super_inputs[0]["cc"];
if((int)$hasautocontrol_super_inputs)
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="25">
            <col width="25%">
            <col width="25">
            <col width="25%">
         </colgroup>
         <tr>
            <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:5px;color:black" align="center">AUTOCONTROL</td>
         </tr>
         <tr>
         <?php
         $sql = " select t1.*
                  from equipo_puntos_autocontrol t1
                  where
                  t1.ac_equipotype_id  = {$hasopeninit["equipo_type_id"]} and
                  t1.ac_status         > 0
                  order by t1.ac_order, t1.ac_name";
         $acpoints = $CON->select($sql);
         $px = 0;
         for($x = 0; $x < count($acpoints) && $acpoints != false; $x++)
         {  ?>
            <td class="tdnrm">
               <input type="checkbox" value="1" name="xdummy" checked
               onclick="return false"><?=$acpoints[$x]["ac_name"]?>
            </td>
            <?php
            $px++;
            if($px >= 4)
            {  ?>
               </tr>
               <tr>
               <?php
               $px = 0;
            }
         }
         ?>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <script language="JavaScript">
   function checkSuperAprob()
   {
      $('#idx_btnsuperaprob').hide(0);

      var canaprob = true;
      $('.superaprobchk').each(function()
      {
         if(!$(this).attr('checked'))
            canaprob = false;
      });

      if(canaprob)
         $('#idx_btnsuperaprob').fadeIn(300, function() { $('#axx_username').focus(); });
   }
   </script>
   <div style="clear:both;height:10px"></div>
   <form action="prodwrk.php" method="post" name="xform_autoctrl" id="xform_autoctrl">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="mode" value="superautoctrlsave">
   <input type="hidden" name="submode" value="supersave">
   <input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="25">
            <col width="25%">
            <col width="25">
            <col width="25%">
         </colgroup>
         <tr>
            <td class="tdleft" colspan="4" style="background-color:#92D050;border:0px;border-radius:5px;color:black" align="center">APROBACIÓN PARTIDA</td>
         </tr>
         <tr>
         <?php
         $sql = " select t1.*
                  from equipo_puntos_autocontrol t1
                  where
                  t1.ac_equipotype_id  = {$hasopeninit["equipo_type_id"]} and
                  t1.ac_status         > 0
                  order by t1.ac_order, t1.ac_name";
         $acpoints = $CON->select($sql);
         $px = 0;
         for($x = 0; $x < count($acpoints) && $acpoints != false; $x++)
         {  ?>
            <td class="tdnrm">
               <input type="hidden" name="superbaseacids[]" value="<?=$acpoints[$x]["id"]?>">
               <input type="checkbox" value="1" name="superacids_<?=$acpoints[$x]["id"]?>"
               onclick="checkSuperAprob()" class="superaprobchk"
               <?if((int)$_SELACROWS_SUPER[$acpoints[$x]["id"]]) echo "checked"?>>
               <?=$acpoints[$x]["ac_name"]?>
            </td>
            <?php
            $px++;
            if($px >= 4)
            {  ?>
               </tr>
               <tr>
               <?php
               $px = 0;
            }
         }
         ?>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <div style="clear:both;height:10px"></div>
   <table border="0" width="100%" cellpadding="0" cellspacing="0" id="idx_btnsuperaprob" style="display:none">
   <tr>
      <td width="205">
         <input type="text" class="inptxt" name="axx_username" id="axx_username" placeholder="Usuario Supervisor"
         style="width:200px;" autocomplete="off">
      </td>
      <td width="205">
         <input type="password" class="inptxt" name="axx_pass" placeholder="Contraseña Supervisor"
         style="width:200px;" autocomplete="off">
      </td>
      <td>
         <div class="btngreen" onclick="submitForm(document.xform_autoctrl)">
            <i class="fa fa-fw fa-save" style="color:white;"></i> Aprobar partida&nbsp;
         </div>
      </td>
   </tr>
   </table>
   </form>
   <?php

}
else
{  ?>
   <form action="prodwrk.php" method="post" name="xform_autoctrl" id="xform_autoctrl">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="mode" value="autoctrlsave">
   <input type="hidden" name="submode" value="save">
   <input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="25">
            <col width="25%">
            <col width="25">
            <col width="25%">
         </colgroup>
         <tr>
            <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:5px;color:black" align="center">AUTOCONTROL</td>
         </tr>
         <tr>
         <?php
         $sql = " select t1.*
                  from equipo_puntos_autocontrol t1
                  where
                  t1.ac_equipotype_id  = {$hasopeninit["equipo_type_id"]} and
                  t1.ac_status         > 0
                  order by t1.ac_order, t1.ac_name";
         $acpoints = $CON->select($sql);
         $px = 0;
         for($x = 0; $x < count($acpoints) && $acpoints != false; $x++)
         {  ?>
            <td class="tdnrm">
               <input type="hidden" name="baseacids[]" value="<?=$acpoints[$x]["id"]?>">
               <input type="checkbox" value="1" name="acids_<?=$acpoints[$x]["id"]?>"
               onclick="$('#idx_supervisor_btn').hide(0);submitForm(document.xform_autoctrl);"
               <?if((int)$_SELACROWS_WORKER[$acpoints[$x]["id"]]) echo "checked"?>>
               <?=$acpoints[$x]["ac_name"]?>
            </td>
            <?php
            $px++;
            if($px >= 4)
            {  ?>
               </tr>
               <tr>
               <?php
               $px = 0;
            }
         }
         ?>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <!--
   <div style="clear:both;height:10px"></div>
   <div class="btngreen" onclick="submitForm(document.xform_autoctrl)">
      <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
   </div>
   -->
   <?php
   if($_CAN_SUPER_SEND)
   {  ?>
      <div style="clear:both;height:10px"></div>
      <div class="btngreen" style="background-color:#4597C2" id="idx_supervisor_btn"
      onclick="document.xform_autoctrl.submode.value='close';submitForm(document.xform_autoctrl);">
         <i class="fa fa-fw fa-envelope" style="color:white;"></i> Informa supervisor&nbsp;
      </div>
      <?php
   }
   ?>
   </form>
   <?php
}
?>   
