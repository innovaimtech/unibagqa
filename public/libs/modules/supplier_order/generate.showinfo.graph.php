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

//----------------------------------------------------------------------------------
session_start();

$sql = " select t1.invc_date, t2.item_amount
         from invoices_buy t1
         INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
         where
         t1.invc_status       > 1 and
         t1.invc_supplier_id  = {$_REQUEST["suppid"]} and
         t2.item_id           = {$_REQUEST["item_id"]} and
         t2.item_type         = '{$_REQUEST["item_type"]}'
         order by t1.invc_date desc
         LIMIT 0,12";
$lastbuys = $CON->select($sql);
$data    = Array();
$labels  = Array();
foreach($lastbuys AS $lastbuy)
{
   $data[] = $lastbuy["item_amount"];
   $labels[] = date('d/m', $lastbuy["invc_date"]);

}
$data    = array_reverse($data);
$labels  = array_reverse($labels);

if($lastbuys == false || count($lastbuys) == 0)
{
   include  "../../thirdparty/jpgraph-1.26/src/jpgraph.php";
   include "../../thirdparty/jpgraph-1.26/src/jpgraph_canvas.php";


   $g = new CanvasGraph( 590,$_REQUEST["grheight"],'auto' );
   $g->SetMargin(0,0,0,0);
   $g->SetMarginColor( "white");
   $g->SetFrameBevel(0,false,'white');

   $g->InitFrame();
   $txt="No hay datos disponibles.";
   $t = new Text($txt,290,20);
   $t->SetFont(FF_FONT1, FS_BOLD,40);
   $t->SetColor("red");
   $t->Align('center','top');
   $t->ParagraphAlign( 'center');
   $t->SetBox("white", "white","white");
   $t->Stroke($g->img);
   $g->Stroke(); 
}
else
{
   if(count($lastbuys) == 1)
   {
      $data[] = 0;
      $labels[] = date('d.m');
   }
   
   //----------------------------------------------------------------------------------
   include ("../../thirdparty/jpgraph-1.26/src/jpgraph.php");
   include ("../../thirdparty/jpgraph-1.26/src/jpgraph_line.php");

   $top     = 15;
   $bottom  = 20;
   $left    = 40;
   $right   = 20;

   $colorcode = "#FFFFFF";
   $graph = new Graph(590  ,$_REQUEST["grheight"],"auto");
   $graph->SetScale("textlin");
   $graph->SetFrameBevel(0,false,'white');
   $graph->ygrid->SetColor('#AAAAAA');
   $graph->SetMargin($left,$right,$top,$bottom);
   $graph->SetBackgroundGradient($colorcode, $colorcode, GRAD_HOR, BGRAD_PLOT);
   $graph->SetColor($colorcode);
   $graph->SetMarginColor($colorcode);
   $graph->img->SetAntiAliasing(); 
   $graph->title->SetFont(FF_FONT1,FS_BOLD);
   $graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
   $graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);
   $graph->yaxis->title->SetColor("#666666");
   $graph->yaxis->title->Set("Compras");
   $graph->yaxis->SetColor("red");
   $graph->yaxis->SetWeight(2);
   $graph->xaxis->SetTickLabels($labels);

   $lineplot = new LinePlot($data);
   $lineplot->value->SetColor("red");
   $lineplot->value->SetFont( FF_FONT1, FS_BOLD);
   $lineplot->value->SetFormat("%d");
   $lineplot->mark->SetType(MARK_UTRIANGLE);
   $lineplot->value->show();

   $lineplot->SetColor("blue");
   $lineplot->SetWeight(1);
   $graph->Add($lineplot);

   $graph->Stroke();
}
?>