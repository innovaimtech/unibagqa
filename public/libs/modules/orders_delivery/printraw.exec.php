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
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

if($_REQUEST["module"] == "")
   $_REQUEST["module"] = "orders_delivery";

$urladdparams = "";
if($_REQUEST["showDiff"] != "")
   $urladdparams = "&showDiff={$_REQUEST["showDiff"]}&diffMode={$_REQUEST["diffMode"]}&preCalc={$_REQUEST["preCalc"]}";
if($_REQUEST["printMode"] != "")
   $urladdparams = "&printMode={$_REQUEST["printMode"]}";
if($_REQUEST["useSubdetailid"] != "")
   $urladdparams .= "&useSubdetailid={$_REQUEST["useSubdetailid"]}";

$rowid = 0;
$header[$rowid++] = "HTTPDownload \"{$_SESSION["_CONF"]["conf_shopadmin_url"]}libs/modules/{$_REQUEST["module"]}/printraw.data.php?id={$_REQUEST["id"]}{$urladdparams}\", \"C:\\\\onebit\\\\printraw.txt\"";
$header[$rowid++] = "Sub HTTPDownload( myURL, myPath )";
$header[$rowid++] = "Dim i, objFile, objFSO, objHTTP, strFile, strMsg";
$header[$rowid++] = "Const ForReading = 1, ForWriting = 2, ForAppending = 8";
$header[$rowid++] = "Set objFSO = CreateObject( \"Scripting.FileSystemObject\" )";
$header[$rowid++] = "If objFSO.FolderExists( myPath ) Then";
$header[$rowid++] = "strFile = objFSO.BuildPath( myPath, Mid( myURL, InStrRev( myURL, \"/\" ) + 1 ) )";
$header[$rowid++] = "ElseIf objFSO.FolderExists( Left( myPath, InStrRev( myPath, \"\\\\\" ) - 1 ) ) Then";
$header[$rowid++] = "strFile = myPath";
$header[$rowid++] = "Else";
$header[$rowid++] = "WScript.Echo \"ERROR: Target folder not found.\"";
$header[$rowid++] = "Exit Sub";
$header[$rowid++] = "End If";
$header[$rowid++] = "Set objFile = objFSO.OpenTextFile( strFile, ForWriting, True )";
$header[$rowid++] = "Set objHTTP = CreateObject( \"WinHttp.WinHttpRequest.5.1\" )";
$header[$rowid++] = "objHTTP.Open \"GET\", myURL, False";
$header[$rowid++] = "objHTTP.Send";
$header[$rowid++] = "For i = 1 To LenB( objHTTP.ResponseBody )";
$header[$rowid++] = "objFile.Write Chr( AscB( MidB( objHTTP.ResponseBody, i, 1 ) ) )";
$header[$rowid++] = "Next";
$header[$rowid++] = "objFile.Close( )";
$header[$rowid++] = "Set WshShell = WScript.CreateObject(\"WScript.shell\")";
if((int)$_SESSION["user_printer_mode"])
{
   $header[$rowid++] = "Return = WshShell.Run(\"cmd /C copy C:\\\\onebit\\\\printraw.txt {$_SESSION["user_printer_port"]}\", 0, true)";
}
else
{
   $header[$rowid++] = "Return = WshShell.Run(\"rundll32 printui.dll,PrintUIEntry /Xs /n \"\"{$_SESSION["user_printer_name"]}\"\" Sharename \"\"oki1bit\"\" attributes +Shared\", 0, true)";
   $header[$rowid++] = "Return = WshShell.Run(\"cmd /C net use LPT1 /DELETE\", 0, true)";
   $header[$rowid++] = "Return = WshShell.Run(\"cmd /C net use LPT1 \\\\\\\\127.0.0.1\\\\oki1bit\", 0, true)";
   $header[$rowid++] = "Return = WshShell.Run(\"cmd /C copy C:\\\\onebit\\\\printraw.txt LPT1\", 0, true)";
}
$header[$rowid++] = "End Sub";

$execstr = "del C:\\\\onebit\\\\printraw.txt & del C:\\\\onebit\\\\printraw.vbs & mkdir C:\\\\onebit & ";

foreach($header AS $row)
   $execstr .= "echo {$row} >> C:\\\\onebit\\\\printraw.vbs & ";

$execstr .= "cscript C:\\\\onebit\\\\printraw.vbs";
?>
<html>
<body onload="checkJava()">
<script language="JavaScript">
   function checkJava()
   {
      if(!navigator.javaEnabled())
      {
         alert('JAVA NO INSTALADO\n');
         parent.location.href = 'http://www.oracle.com/technetwork/java/javase/downloads/jre7-downloads-1637588.html';
      }
   }
   
   var apploaded = 0;
   
   function appletloaded(estatus)
   {
      apploaded = estatus;
      exec_command();
   }

   function exec_command()
   {
      if(apploaded == '1')
      {
         document.getElementById('onebitjcmd').launchScript('cmd', '/C <?=$execstr?>');
      }
   }
</script>
<applet code="de.applet.onebit.class" name="onebitjcmd" id="onebitjcmd"
archive="/libs/thirdparty/1bitjavacmd/1bitjavacmd.jar" width=1 height=1>
</applet>
</body>
</html>
