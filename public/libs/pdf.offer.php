<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createOffer($CON, $orderid, $execsave = 1)
{
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.offer/";
   if(!$execsave)
      $filedir = "/tmp/";
      
   $filename   = "{$filedir}{$orderid}.{$hash}.pdf";

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from offers t1
            where
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];
   
   //----------------------------------------------------------------------------------
   if($execsave)
   {
      if($orderheader["req_hash"] != "")
         unlink("{$filedir}{$orderid}.{$orderheader["req_hash"]}.pdf");

      //----------------------------------------------------------------------------------
      $sql = " update offers
               set
               req_hash   = '{$hash}',
               req_upddat = {$currtme},
               req_updusr = {$_SESSION["user_id"]}
               where
               id = {$orderid}";
      $CON->no_result($sql);
   }

   define('FPDF_FONTPATH','./libs/thirdparty/fpdf17/font/');
   require('./libs/thirdparty/fpdf17/lib/pdftable.inc.php');

   $html = file_get_contents("{$_SESSION["_CONF"]["conf_shopadmin_url"]}/pdfgenerate.offer.php?orderid={$orderid}");

   //----------------------------------------------------------------------------------
   $p = new PDFTable();
   $p->setfont('Helvetica','',10);
   $p->AddFont('comesinhandy','','comesinhandy.php');
   $p->SetPadding(2);
   $p->SetSpacing(2);
   $p->SetMargins(10,10,10,10);
   $p->AddPage("P", array(216,356));
   $p->SetPadding(2);
   $p->SetSpacing(1);
   $p->SetMargins(0,0,0,0);
   $p->htmltable($html);
   $buffer     = $p->output('','S');
   file_put_contents($filename, $buffer);

   if(!$execsave)
      return $filename;
}
?>