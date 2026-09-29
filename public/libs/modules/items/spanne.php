<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

require_once("../../classes/menu.php");
require_once("../../classes/page.php");
require_once("../../functions.php");
include ("../../thirdparty/jpgraph-1.26/src/jpgraph.php");
include ("../../thirdparty/jpgraph-1.26/src/jpgraph_bar.php");

//----------------------------------------------------------------------------------
session_start();

$graph = new Graph(455  ,116,"auto");
$graph->SetScale("textlin");
//$graph->img->SetAntiAliasing(1); 
$graph->SetFrameBevel(0,false,'white');
$graph->ygrid->SetColor('#AAAAAA');

$colorcode = "#EFEFEF";

$graph->SetBackgroundGradient($colorcode, $colorcode, GRAD_HOR, BGRAD_PLOT);
$graph->SetColor($colorcode);
$graph->SetMarginColor($colorcode);

if((float)$_REQUEST["buyval"] == 0.00)
   $_REQUEST["sellval"] = 0;
if((float)$_REQUEST["sellval"] == 0.00)
   $_REQUEST["buyval"] = 0;
   
$datay1[0] = $_REQUEST["buyval"];
$datay2[0] = $_REQUEST["sellval"] - $_REQUEST["buyval"];
$margin = $datay2[0] / $datay1[0] * 100;

$graph->title->SetFont(FF_FONT1, FS_BOLD);
$graph->title->Set("Venta/F: $".printPrice($_REQUEST["sellval"])." - Compra/F: $".printPrice($_REQUEST["buyval"])."\nMargen: $".printPrice($datay2[0])." (".printPrice($margin)."%)\n");

                              
$b1plot = new BarPlot($datay1);
$b1plot->SetFillColor("#E05555");
$b1plot->value->Show();
$b1plot->value->SetColor('#000000');
$b1plot->value->SetFormat('%.0f');
$b1plot->value->SetFont(FF_FONT1);
$b1plot->SetValuePos('center');
$b1plot->SetFillgradient('#C8FFCB','#46B555',GRAD_VER);

$b2plot = new BarPlot($datay2);
$b2plot->SetFillColor("#39CC39");
$b2plot->value->Show();
$b2plot->value->SetColor('#000000');
$b2plot->value->SetFormat('%.0f');
$b2plot->value->SetFont(FF_FONT1);
$b2plot->SetValuePos('center');
$b2plot->SetFillgradient('#FFD2D2','#D6757B',GRAD_VER);

$gbplot = new AccBarPlot(array($b1plot,$b2plot));
$graph->Add($gbplot);
$graph->Set90AndMargin(10,15,40,10);
$graph->xaxis->HideLabels();
$graph->yaxis->HideLabels();
$graph->Stroke();
?>