<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       30.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

CLASS CPAGE
{
   private $p_con;
   private $p_style;
   private $p_stylearr;
   private $p_effects;

   //----------------------------------------------------------------------------------
   // constructor
   //----------------------------------------------------------------------------------
   function CPAGE($con = NULL)
   {
      $this->p_con = $con;
   }

   //----------------------------------------------------------------------------------
   // get the stylesheet data of the page
   //----------------------------------------------------------------------------------
   public function getStyle()
   {
      $this->p_style = "";

      // select active groups
      $sql = " select id
               from config_style_group
               where
               style_group_status > 0";
      $stylegroups = $this->p_con->select($sql);

      // loop through groups
      for($x = 0; $x < count($stylegroups) && $stylegroups != false; $x++)
      {
         // select active items
         $sql = " select id, style_item_name, style_item_type
                  from config_style_item
                  where
                  style_group_id = {$stylegroups[$x]["id"]} and
                  style_item_status > 0";
         $styleitems = $this->p_con->select($sql);

         // loop through items
         for($y = 0; $y < count($styleitems) && $styleitems != false; $y++)
         {

            // select active values
            $sql = " select style_item_col, style_item_val
                     from config_style_item_val
                     where
                     style_item_id = {$styleitems[$y]["id"]} and
                     style_item_status > 0";
            $styleitemvals = $this->p_con->select($sql);
 
            // add item header
            $this->p_style .= "{$styleitems[$y]["style_item_type"]}.{$styleitems[$y]["style_item_name"]} { ";

            // add item values
            for($z = 0; $z < count($styleitemvals) && $styleitemvals != false; $z++)
            {
               $this->p_style .= "{$styleitemvals[$z]["style_item_col"]}:{$styleitemvals[$z]["style_item_val"]};";
               $this->p_stylearr[$styleitems[$y]["style_item_type"]][$styleitems[$y]["style_item_name"]][$styleitemvals[$z]["style_item_col"]] = $styleitemvals[$z]["style_item_val"];
            }

            // add item footer
            $this->p_style .= " } ";
         }
      }
   }

   //----------------------------------------------------------------------------------
   // print the item style
   //----------------------------------------------------------------------------------
   public function printStyle()
   {
      echo $this->p_style;
   }

   //----------------------------------------------------------------------------------
   // return the item style
   //----------------------------------------------------------------------------------
   public function returnStyle()
   {
      return $this->p_style;
   }

   //----------------------------------------------------------------------------------
   // return the item style
   //----------------------------------------------------------------------------------
   public function returnStyleVal($style_item_type, $style_item_name, $style_item_col)
   {
      return $this->p_stylearr[$style_item_type][$style_item_name][$style_item_col];
   }

   //----------------------------------------------------------------------------------
   // get effect values
   //----------------------------------------------------------------------------------
   public function getEffects()
   {
      unset($this->p_effects);

      // select effects
      $sql = " select *
               from config_style_effects
               where
               style_effect_status = 1";
      $effects = $this->p_con->select($sql);

      // save effects to array
      for($x = 0; $x < count($effects) && $effects != false; $x++)
         $this->p_effects[$effects[$x]["style_effect_name"]] = $effects[$x]["style_effect_val"];
   }

   //----------------------------------------------------------------------------------
   // get value of an effect
   //----------------------------------------------------------------------------------
   public function getEffectVal($idx)
   {
      return $this->p_effects[$idx];
   }
}