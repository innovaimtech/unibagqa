<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$sql = " select *
         from customer t1
         where
         t1.id = {$_REQUEST["custid"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

$sql_rut = str_replace(".", "", $customer["cust_rut"]);
$sql = " select *
         from offers t1
         where
         t1.req_status > 1 and
         REPLACE(t1.req_cust_rut,'.','') = '{$sql_rut}'
         order by t1.id desc
         LIMIT 0,1";
$lastoffer = $CON->select($sql);
$lastoffer = $lastoffer[0];

//----------------------------------------------------------------------------------
if(trim($sql_rut) != "")
{
   $sql = " select t2.*
            from customer t1
            INNER JOIN user t2 ON t1.cust_sellerid = t2.id
            where
            t1.cust_status > 0 and
            REPLACE(t1.cust_rut,'.','') like '{$sql_rut}' and
            t1.cust_rut != ''";
   $custvendedor = $CON->select($sql);
   $custvendedor = $custvendedor[0];
}

//----------------------------------------------------------------------
$sql = " select t2.id
             , ifnull(t2.add_email,'x') as add_email
             , t2.add_firstname
             , t2.add_lastname
             , case when t2.add_id_crm = '' then '0' else ifnull(t2.add_id_crm,'0') end as add_id_crm
             , ifnull(t2.add_cellphone,'0') as add_cellphone
         from customer t1
         INNER JOIN customer_contacts t2 ON t1.id = t2.add_cust_id
         where t1.id = {$_REQUEST["custid"]}        
         order by t2.add_pos asc";
$suppcontacts = $CON->select($sql);

?>
<script language="JavaScript">
var xform = parent.document.form_reqpos;

xform.req_cust_company.value='<?=str_replace("'","",$customer["cust_company"])?>';
xform.req_cust_rut.value='<?=str_replace("'","",$customer["cust_rut"])?>';
xform.req_cust_street.value='<?=str_replace("'","",$customer["cust_street"])?>';
xform.req_cust_phone.value='<?=str_replace("'","",$customer["cust_phone"])?>';
xform.country.value='<?=$customer["cust_countryid"]?>';
xform.regions.value='<?=$customer["cust_regionid"]?>';
parent.setProvincias('<?=$customer["cust_regionid"]?>');
xform.provincias.value='<?=$customer["cust_provinciaid"]?>';
parent.setComunas('<?=$customer["cust_provinciaid"]?>');
xform.req_cust_email.value='<?=str_replace("'","",$customer["cust_email"])?>';
xform.comunas.value='<?=$customer["cust_comunaid"]?>';
xform.req_paymentid.value='<?=$customer["cust_paymentid"]?>';

let select = parent.document.getElementById("id_contacto");
select.innerHTML = '<option value="">< Por favor seleccione ></option>';

<?php 
   foreach($suppcontacts AS $contacto)
   {  
      ?>
         var regobj = parent.document.getElementById('id_contacto');
         var newIndex = regobj.options.length;
         var newOpt = new Option('<?=$contacto["add_firstname"] . ' ' . $contacto["add_lastname"]?>', '<?=$contacto["id"]?>');
         newOpt.setAttribute("data-idcrm", '<?=$contacto["add_id_crm"]?>'); 
         newOpt.setAttribute("data-mail", '<?=$contacto["add_email"]?>'); 
         newOpt.setAttribute("data-fono", '<?=$contacto["add_cellphone"]?>'); 
         regobj.options.add(newOpt); 
      <?php
   }
?>

<?php
if((int)$customer["cust_plfabid"])
{  ?>
   xform.req_plid_fab.value = '<?=$customer["cust_plfabid"]?>';
   <?php
}
else
{  ?>
   xform.req_plid_fab.options.selectedIndex = 1;
   <?php
}
?>
xform.req_cust_catid.value='<?=$customer["cust_catid"]?>';
<?php
if((int)$lastoffer["id"])
{  ?>
   xform.req_cust_email.value    = '<?=str_replace("'","",$lastoffer["req_cust_email"])?>';
   xform.req_cust_phone.value    = '<?=str_replace("'","",$lastoffer["req_cust_phone"])?>';
   xform.req_cust_fax.value      = '<?=str_replace("'","",$lastoffer["req_cust_fax"])?>';
   <?php
}
?>

parent.document.getElementById('idx_vendedor_customer').innerHTML = '<?=str_replace("'", "", $custvendedor["user_firstname"])?>&nbsp;<?=str_replace("'", "", $custvendedor["user_lastname"])?>';
</script>
