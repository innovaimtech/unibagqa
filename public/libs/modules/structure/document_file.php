<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

// create filename
if($_REQUEST["path"] == "")
   $_REQUEST["path"] = "../../../docs/";

if((int)$_REQUEST["suo"])
   $_REQUEST["path"] = "../../../docs.tran/supplier_order/";

if((int)$_REQUEST["loadtemppath"])
{
   $_REQUEST["path"] = "/tmp/";
   $_REQUEST["type"] = 1;
}
   
if($_REQUEST["id"] != "")
   $filename = "{$_REQUEST["path"]}{$_REQUEST["id"]}.{$_REQUEST["hash"]}";
else
   $filename = "{$_REQUEST["path"]}{$_REQUEST["hash"]}";

//----------------------------------------------------------------------------------
// force file download
//----------------------------------------------------------------------------------
if($_REQUEST["type"] == "0")
{
   header("Content-Type: {$_REQUEST["mime"]}");
   header("Content-disposition: attachment; filename=\"{$_REQUEST["name"]}\"");
   header('Expires: 0');
   header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
   header('Pragma: no-cache');
   header('Content-Length: ' . filesize($filename));
   
   ob_clean();
   flush();
   if(file_exists($filename))
      readfile($filename);
}

//----------------------------------------------------------------------------------
// ouput directly to browser
//----------------------------------------------------------------------------------
else
{
   header("Content-type: {$_REQUEST["mime"]}");
   header("Content-disposition: inline; filename=\"{$_REQUEST["name"]}\"");
   header('Content-Length: ' . filesize($filename));
   
   ob_clean();
   flush();

   if(file_exists($filename))
      readfile($filename);
}
?>