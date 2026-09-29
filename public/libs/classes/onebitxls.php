<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       30.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if(!(int)$_REQUEST["_NOINCLUDE_XLSLIBS"])
{
   require_once("./libs/thirdparty/PHPExcel_1.8.0_doc/Classes/PHPExcel.php");
   require_once("./libs/thirdparty/biffwriter.extended/onebitformate.inc");
}

//----------------------------------------------------------------------------------
CLASS ONEBITXLS
{
   public $xlsfilename;
   public $objPHPExcel;
   public $objWriter;
   public $myWorkSheet;

   //----------------------------------------------------------------------------------
   function ONEBITXLS($filename)
   {
      $this->xlsfilename = $filename;
      $this->objPHPExcel = new PHPExcel();
      $sheetIndex = $this->objPHPExcel->getIndex($this->objPHPExcel->getSheetByName('Worksheet'));
      $this->objPHPExcel->removeSheetByIndex($sheetIndex);
   }

   //----------------------------------------------------------------------------------
   public function setcolumn($x, $x, $width)
   {
      $width = $width + 0.72;
      $this->myWorkSheet->getColumnDimensionByColumn($x)->setWidth($width);
   }

   //----------------------------------------------------------------------------------
   public function setrow($y, $height)
   {
      $y++;
      $this->myWorkSheet->getRowDimension($y)->setRowHeight($height);
   }

   //----------------------------------------------------------------------------------
   public function setwrap($y, $x)
   {
      $this->myWorkSheet->getStyleByColumnAndRow($x, $y)->getAlignment()->setWrapText(true);
   }

   //----------------------------------------------------------------------------------
   public function setautosize($y, $text, $width)
   {
      //$this->myWorkSheet->getColumnDimensionByColumn($x)->setAutoSize(true);
      $this->myWorkSheet->getRowDimension($y+1)->setRowHeight(ceil(strlen($text)/$width)*12);
   }
   

   //----------------------------------------------------------------------------------
   public function mergecells($y1, $x1, $y2, $x2)
   {
      $y1++;
      $y2++;
      //error_reporting(E_ALL);
      $this->myWorkSheet->mergeCellsByColumnAndRow($x1, $y1, $x2, $y2);
      //$this->myWorkSheet->getRowDimension($x)->setRowHeight($height);
   }

   //----------------------------------------------------------------------------------
   public function writestring($y, $x, $val, $format)
   {
      $val = utf8_encode($val);
      $val = str_replace("=","",$val);
      //$val = substr($val,0,254);
      $y++;
      $val = $val."";
      $this->myWorkSheet->setCellValueByColumnAndRow($x, $y, $val);
      $this->myWorkSheet->getRowDimension($y)->setRowHeight(12.75);
      if(is_array($format))
      {
         $this->myWorkSheet->getStyleByColumnAndRow($x, $y)->applyFromArray($format);
      }
   }

   //----------------------------------------------------------------------------------
   public function writestringexp($y, $x, $val, $format)
   {
      $val = utf8_encode($val);
      $val = str_replace("=","",$val);
      $val = substr($val,0,254);
      $y++;
      $val = $val."";
      $this->myWorkSheet->setCellValueExplicitByColumnAndRow($x, $y, $val, PHPExcel_Cell_DataType::TYPE_STRING);
      $this->myWorkSheet->getRowDimension($y)->setRowHeight(12.75);
      if(is_array($format))
      {
         $this->myWorkSheet->getStyleByColumnAndRow($x, $y)->applyFromArray($format);
      }
   }

   //----------------------------------------------------------------------------------
   public function writenumber($y, $x, $val, $format)
   {
      $y++;
      $val = utf8_encode($val);
      $val = str_replace("=","",$val);
      $val = substr($val,0,254);
      $val = (float)$val;
      $this->myWorkSheet->setCellValueByColumnAndRow($x, $y, $val);
      $this->myWorkSheet->getRowDimension($y)->setRowHeight(12.75);
      if(is_array($format))
      {
         $this->myWorkSheet->getStyleByColumnAndRow($x, $y)->applyFromArray($format);
      }
   }

   //----------------------------------------------------------------------------------
   public function addWorksheet($title)
   {
      $title = utf8_encode($title);
      $this->myWorkSheet = $this->objPHPExcel->createSheet();
      $this->myWorkSheet->setTitle($title);
      return $this;
   }

   public function close()
   {
      $this->objPHPExcel->setActiveSheetIndex(0);
      $this->objWriter = PHPExcel_IOFactory::createWriter($this->objPHPExcel, "Excel2007");
      $this->objWriter->save($this->xlsfilename);
   }

   //----------------------------------------------------------------------------------
   public function setVersion($version) { }
   public function set_landscape() { }
}