<?php
//----------------------------------------------------------------------------------
require_once("../../classes/page.php");
require_once("../../classes/mysql.php");
require_once("../../config.php");


//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../lang/es.php");
require_once("../../functions.php");

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
         from reservas_header
         where
         id = {$_REQUEST["id"]}";
$resv = $CON->select($sql);
$resv = $resv[0];



//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="document.all.xuser_login.focus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   $_REQUEST["res_comments"] = trim(addslashes($_REQUEST["res_comments"]));
   
   $sql = " update reservas_header
            set
            res_comments = '{$_REQUEST["res_comments"]}'
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   if((int)$_REQUEST["sendmail"] && $_REQUEST["res_comments"] != "")
   {
      $currtme = time();

      //----------------------------------------------------------------------------------
      $sql = " select t1.*
               from reservas_header t1
               where
               t1.id = {$_REQUEST["id"]}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];
      
      //----------------------------------------------------------------------------------
      $linestyle = "font-size:12px;font-family:Arial;color:#333333;border-right:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;";
      $body  = "<html><head><style></style></head><body><center>";
      $body .= "<table width=100% cellpadding=0 cellspacing=0 style='background-color:white'>";
      $body .= "<tr><td align='center'><img src='{$_SESSION["_CONF"]["conf_shopadmin_url"]}images/layout/logo.png' height=110></td></tr>";
      $body .= "<tr><td>";
      $body .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="height:5px;margin-top:10px">';
      $body .= '<tr>';
      $body .= '<td style="background-color:#453746;"><div style="height:5px;font-size:2px;font-family:Arial">&nbsp;</div></td>';
      $body .= '</tr>';
      $body .= '</table>';
      $body .= "</td></tr>";
      $body .= "<tr><td style='font-size:8px;height:18px'>&nbsp;</td></tr>";
      $body .= "<tr><td style='font-size:14px;font-family:Arial;color:#333333;' align='center'>";
      $body .= "<b>Estimada(o) {$headdata["res_custname"]},<br><br>su pedido fue despachado.</b><br><br>";
      $body .= "Por favor pinche el siguiente link para ver el estado del envio:<br><br>";
      $body .= "<a href='{$headdata["res_comments"]}'>{$headdata["res_comments"]}</a><br><br>";

      $body .= "Saludos Cordiales<br>";
      $body .= "Logística y Transporte CPPS International Beauty<br><br>";


      $body .= "<br><br><font style='color:#AAAAAA;font-size:12px'>No responda este correo, es automático.</font>";
      $body .= "</td></tr>";
      $body .= "</table>";

      $title      = "Su Pedido / CPPS International";
      $rcpt_addr  = $headdata["res_mail"];
      $rcpt_name  = $headdata["res_custname"];

      $_MAILS[0]["MAIL"] = $rcpt_addr;
      $_MAILS[0]["NAME"] = $rcpt_name;
      avisoSendExternalMail($title, $body);
   }
   ?>
   <script language="JavaScript">
   parent.document.xform_itemsearch.submit();
   </script>
   <?php
}
?>

<form action="fancycomments.php" method="post" class="fokusfirst" name="xform_log" autocomplete="off">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="sendmail" value="">
<?=Nifty_printH("box1", "100%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header">Comentarios / direccion de despacho</td>
</tr>
<tr>
   <td class="content_row">
      <textarea name="res_comments" class="text" style="width:100%;height:210px"><?=stripslashes($resv["res_comments"])?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "100%")?>
<tr>
   <td class="content_row_clear" align="left">
      <?php
      printButton("Solo guardar", "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_log)", "disk-black", 160);
      ?>
   </td>
   <td class="content_row_clear" align="right">
      <?php
      if($resv["res_mail"] != "")
         printButton("Guardar y enviar aviso al cliente", "postnav_save", "javascript: deactivateFormChange()", "document.xform_log.sendmail.value='1';submitForm(document.xform_log)", "mail", 250);
      ?>
   </td>
</tr>
<?=Nifty_printF(false)?>
</form>

</body>
</html>
<?php
//----------------------------------------------------------------------------------
function avisoSendExternalMail($title, $body)
{
   global $_MAILS;
   $conf_mail_accountname  = "sistema-cpps@appeltsoft.cl";
   $conf_mail_password     = "mhCQ5v%1GU~3";
   $conf_mailserver        = "appeltsoft.cl";
   
   $from_addr = $conf_mail_accountname;
   $from_name = "SISTEMA CPPS";
   try
   {
      require_once("../../thirdparty/swift-3.3.3-php5/lib/Swift.php");
      require_once("../../thirdparty/swift-3.3.3-php5/lib/Swift/Connection/SMTP.php");

      $smtp    = new Swift_Connection_SMTP($conf_mailserver, Swift_Connection_SMTP::PORT_SECURE, Swift_Connection_SMTP::ENC_TLS);

      $smtp->setUsername($conf_mail_accountname);
      $smtp->setpassword($conf_mail_password);
      $sender  = new Swift_Address($from_addr, $from_name);

      $message = new Swift_Message($title, $body, "text/html");
      $recipients = new Swift_RecipientList();

      foreach($_MAILS AS $bccmail)
         $recipients->addTo($bccmail["MAIL"], $bccmail["NAME"]);

      $swift   = new Swift($smtp);
      $numsent = $swift->send($message, $recipients, $sender);
      
      return true;
   }
   catch (Swift_ConnectionException $e) { return false; }
   catch (Swift_Message_MimeException $e) {  return false; }
   catch (Exception $e) { return false; }
   
   if ($numsent > 0)
      return true;
   else
      return false;
}
