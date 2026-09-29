<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       30.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

class CMYSQL
{
   var $CMYSQL_IP;
   var $CMYSQL_USER;
   var $CMYSQL_PASS;
   var $CMYSQL_DB;
   var $CMYSQL_CON=false;
   var $CMYSQL_ROW;
   var $CMYSQL_QUID;
   var $CMYSQL_QUAR = array();
   var $CMYSQL_COUNTER;
   
   //----------------------------------------------------------------------------------
   // constructor
   //----------------------------------------------------------------------------------
   function CMYSQL($DB, $IP, $USER, $PASS)
   {
      $this->CMYSQL_IP   = $IP;
      $this->CMYSQL_USER = $USER;
      $this->CMYSQL_PASS = $PASS;
      $this->CMYSQL_DB   = $DB;
   }

   //----------------------------------------------------------------------------------
   // connect to database
   //----------------------------------------------------------------------------------
   function connect()
   {
      $this->CMYSQL_CON = mysql_connect($this->CMYSQL_IP, $this->CMYSQL_USER, $this->CMYSQL_PASS)
      or DIE("Verbindung zum Datenbankserver fehlgeschlagen");
      mysql_select_db($this->CMYSQL_DB,$this->CMYSQL_CON)
      or DIE("Die Datenbank ".$this->CMYSQL_DB." ist nicht selektierbar");
   }

   //----------------------------------------------------------------------------------
   // diconnect from database
   //----------------------------------------------------------------------------------
   function disconnect()
   {
      if($this->CMYSQL_CON != false)
      return mysql_close($this->CMYSQL_CON);
   }

   //----------------------------------------------------------------------------------
   // change to other database
   //----------------------------------------------------------------------------------
   function change_db($DB)
   {
      $this->CMYSQL_DB   = $DB;
      mysql_select_db($this->CMYSQL_DB,$this->CMYSQL_CON)
      or DIE("Die Datenbank ".$this->CMYSQL_DB." ist nicht selektierbar");
   }

   //----------------------------------------------------------------------------------
   // select data from database
   //----------------------------------------------------------------------------------
   function select($query)
   {
      unset($this->CMYSQL_QUAR);
      $this->CMYSQL_QUID = mysql_query($query,$this->CMYSQL_CON);
      if($this->CMYSQL_QUID != false)
      {
         for($this->CMYSQL_COUNTER=0;
             $this->CMYSQL_ROW = mysql_fetch_array($this->CMYSQL_QUID, MYSQL_ASSOC);
             $this->CMYSQL_COUNTER++)
         {
            $this->CMYSQL_QUAR[$this->CMYSQL_COUNTER] = $this->CMYSQL_ROW;
         }
         if($this->CMYSQL_COUNTER != 0)
            return $this->CMYSQL_QUAR;
      }
      /*
      if(mysql_error() != "")
         echo "QUERY FALSE: ".$query."<br>ERROR = ".mysql_error()."<hr>";
      */
      return false;
   }
   
   //----------------------------------------------------------------------------------
   // execute updates or inserts
   //----------------------------------------------------------------------------------
   function no_result($query)
   {
      $this->CMYSQL_QUID = mysql_query($query,$this->CMYSQL_CON);
      if($this->CMYSQL_QUID != false)
         return true;
      else
      {
         //echo "QUERY FALSE: ".$query."<hr>";
         return false;
      }
   }

   //--------------------------------------
   // UTILITARIOS
   //---------------------------------------
   function cuenta($query) {
      $rs = mysql_query($query,$this->CMYSQL_CON);
      $rf = mysql_fetch_assoc($rs);
      return($rf['count']);
   }

   function mq($query) {
      $rs = mysql_query($query,$this->CMYSQL_CON) or die($query);
      return($rs);
   }
}
?>
