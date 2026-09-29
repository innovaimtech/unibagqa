<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

CLASS CMENU
{
   //----------------------------------------------------------------------------------
   // Class variables
   //----------------------------------------------------------------------------------
   public  $m_rawstruct;
   private $m_con;
   private $m_type;
   private $m_groups;
   private $m_groups_str;
   private $m_userid;
   private $m_struct;
   private $m_editmode;
   private $m_formname;
   private $m_delstruct;
   private $m_transtruct;
   private $m_favorites;
   private $m_selectboxindex;

   //----------------------------------------------------------------------------------
   function CMENU($con = NULL, $userid = NULL, $type = NULL, $groups = NULL)
   {
      $this->m_con         = $con;
      $this->m_userid      = $userid;
      $this->m_type        = $type;
      $this->m_groups      = $groups;
      $this->m_favorites   = Array();

      for($x = 0; $x < count($groups); $x++)
         $this->m_groups_str .= "{$groups[$x]},";
      $this->m_groups_str = substr($this->m_groups_str, 0, -1);
   }

   //----------------------------------------------------------------------------------
   private function enumStruct($parent_id, $subarray)
   {
      if($this->m_type == "1")
      {
         $sqlall              = " or 1 = 1 ";
         $this->m_groups_str  = "0";
         $this->m_userid      = "0";
      }
      
      if($this->m_groups_str == "")
         $this->m_groups_str = "0";

      $field_menuname = "t1.menu_name{$_SESSION["_CONF"]["conf_lang"]}";
      
      $sql = " select   distinct t1.id, {$field_menuname} 'menu_name', t1.menu_name1, t1.menu_name2,
                        t1.menu_name3, t1.menu_link, t1.menu_link_mod, t1.menu_mod_params,
                        t1.menu_adm, t1.menu_public, t1.menu_parent, t1.menu_docid, t1.menu_icon,
                        t4.doc_name, t4.doc_type, t4.doc_size, t1.menu_behavior, t1.menu_desc,
                        t1.menu_trancode, t1.menu_spacer, count(t2.group_id) 'gc', count(t3.user_id) 'uc'
               from menu_items t1
               LEFT OUTER JOIN group_menu_items t2
                  ON ( t1.id = t2.menu_id and (t2.group_id IN ({$this->m_groups_str}) {$sqlall}))
               LEFT OUTER JOIN user_menu_items t3
                  ON ( t1.id = t3.menu_id and (t3.user_id = {$this->m_userid} {$sqlall}))
               LEFT OUTER JOIN menu_docs t4
                  ON ( t1.menu_docid = t4.id )
               where
               t1.menu_parent    = {$parent_id} and
               (
               ( t1.menu_appmode = {$_SESSION["menu_appmode"]} or t1.menu_public = 1 ) ";
      if($_SESSION["user_type"] == "1")
         $sql .= " or t1.id = 1 or t1.id = 10 ";
      $sql .= " ) group by t1.id
               order by t1.menu_order asc, {$field_menuname} asc";
      $items = $this->m_con->select($sql);

      for($x = 0, $counter = 0; $x < count($items) && $items != false; $x++)
      {
         if($this->m_type == "1" || $items[$x]["menu_public"] == "1" || (int)$items[$x]["gc"] > 0 || (int)$items[$x]["uc"] > 0)
         {
            if(trim($items[$x]["menu_name"]) == "")
            {
               if(trim($items[$x]["menu_name1"]) != "")
                  $items[$x]["menu_name"] = $items[$x]["menu_name1"];
               elseif(trim($items[$x]["menu_name2"]) != "")
                  $items[$x]["menu_name"] = $items[$x]["menu_name2"];
               elseif(trim($items[$x]["menu_name3"]) != "")
                  $items[$x]["menu_name"] = $items[$x]["menu_name3"];
               else
                  $items[$x]["menu_name"] = "???";
            }
            
            $subarray[$counter]["id"]              = $items[$x]["id"];
            $subarray[$counter]["menu_name"]       = $items[$x]["menu_name"];
            $subarray[$counter]["menu_link"]       = $items[$x]["menu_link"];
            $subarray[$counter]["menu_link_mod"]   = $items[$x]["menu_link_mod"];
            $subarray[$counter]["menu_adm"]        = $items[$x]["menu_adm"];
            $subarray[$counter]["menu_parent"]     = $items[$x]["menu_parent"];
            $subarray[$counter]["menu_public"]     = $items[$x]["menu_public"];
            $subarray[$counter]["menu_docid"]      = $items[$x]["menu_docid"];
            $subarray[$counter]["menu_behavior"]   = $items[$x]["menu_behavior"];
            $subarray[$counter]["menu_desc"]       = $items[$x]["menu_desc"];
            $subarray[$counter]["menu_icon"]       = $items[$x]["menu_icon"];
            $subarray[$counter]["menu_mod_params"] = $items[$x]["menu_mod_params"];
            $subarray[$counter]["menu_trancode"]   = $items[$x]["menu_trancode"];
            $subarray[$counter]["menu_spacer"]   = $items[$x]["menu_spacer"];
            $subarray[$counter]["doc_name"]        = $items[$x]["doc_name"];
            $subarray[$counter]["doc_type"]        = $items[$x]["doc_type"];
            $subarray[$counter]["doc_size"]        = $items[$x]["doc_size"];

            $this->m_rawstruct[$items[$x]["id"]]   = $items[$x];
            
            if($items[$x]["menu_trancode"] != "")
            {
               $items[$x]["menu_trancode"] = strtoupper($items[$x]["menu_trancode"]);
               $this->m_transtruct[$items[$x]["menu_trancode"]] = $items[$x]["id"];
            }

            $subarray[$counter]["items"]           = $this->enumStruct( $items[$x]["id"],
                                                                        $subarray[$x]["items"]);
            $this->m_rawstruct[$items[$x]["id"]]["items"] = $subarray[$counter]["items"];
            $counter++;
         }
      }
      return $subarray;
   }

   //----------------------------------------------------------------------------------
   private function printSelectboxItems($items, $lvl)
   {
      
      $lvl++;

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items); $x++)
      {
         $imid = $items[$x]["id"];

         if(!$x && $lvl == 1)
         {  ?>
            <option value="0" style="font-weight:bold;color:red">Top-Level</option>
            <?php
         }
         $spacer = "";
         if($lvl == 1)
            $spacer = "font-weight:bold;color:black";

         if($items[$x]["menu_link"] == "0")
         {
            $parentname = "";
            if((int)$items[$x]["menu_parent"])
               $parentname = $this->m_rawstruct[$items[$x]["menu_parent"]]["menu_name"]." &gt; ";
            ?>
            <option value="<?=$imid?>" style="<?=$spacer?>"
            <?php if($this->m_selectboxindex == $imid) echo "selected"?>><?=$parentname?><?=$items[$x]["menu_name"]?></option>
            <?php
            if(count($items[$x]["items"]) > 0)
               $this->printSelectboxItems($items[$x]["items"], $lvl);
         }
      }
   }
   
   //----------------------------------------------------------------------------------
   private function printMenuItems($items, $lvl)
   {
      global $_LANG;

      $lvl++;
      if($_SESSION["user_id"] == "" && $lvl == 1)
         $items[0]["menu_name"] = "_ignore";

      //----------------------------------------------------------------------------------
      if($lvl == 1 && count($items))
      {
         if($this->m_editmode)
            echo '<div class="editmenu"><ul>';
         else
            echo '<div class="menu"><ul>';
      }

      //----------------------------------------------------------------------------------
      if($lvl == 2 && count($items))
      {
         for($x = 0; $x < count($items); $x++)
         {
            //$_ul3hash = md5(microtime());
            $_ul3hash = $items[$x]["id"];
            $this->m_rawstruct[$items[$x]["id"]]["_ulidx"] = "ulidx_{$_ul3hash}";
         }
         echo '<ul>';
      }

      //----------------------------------------------------------------------------------
      if($lvl == 3 && count($items))
      {
         $item_parentid = $items[0]["menu_parent"];
         
         $_ul3hash = $this->m_rawstruct[$item_parentid]["_ulidx"];
         echo "<ul class='idx_uljqfadeentry' id='{$_ul3hash}'>";
      }
      
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items); $x++)
      {
         $imid = $items[$x]["id"];

         if($items[$x]["menu_link"] == "0")
         {
            if($this->m_editmode)
               $onclick_str = "location.href='index.php?mid={$_REQUEST["mid"]}&showItem={$items[$x]["id"]}'";
            else
               $onclick_str = "javascript:void(0)";
         }

         //----------------------------------------------------------------------------------
         if($items[$x]["menu_link"] == "1")
         {
            if($this->m_editmode)
               $onclick_str = "location.href='index.php?mid={$_REQUEST["mid"]}&showItem={$items[$x]["id"]}'";
            else
            {
               $onclick_str = "document.getElementById('idx_frame_content').src='index.php?mid={$items[$x]["id"]}";

               if($items[$x]["menu_mod_params"] != "")
                  $onclick_str .= "&{$items[$x]["menu_mod_params"]}";

               $onclick_str .= "'";
            }
         }

         //----------------------------------------------------------------------------------
         if($items[$x]["menu_link"] == "2")
         {
            if($this->m_editmode)
               $onclick_str = "location.href='index.php?mid={$_REQUEST["mid"]}&showItem={$items[$x]["id"]}'";
            else
               $onclick_str = "document.getElementById('idx_frame_content').src='index.php?mid={$items[$x]["id"]}&doc_id={$items[$x]["menu_docid"]}&type={$items[$x]["menu_behavior"]}'";
         }

         //----------------------------------------------------------------------------------
         if($lvl == 1)
         {
            if(!$x && !$this->m_editmode)
            {
               $topcssclass = "";
               if(!(int)$_REQUEST["mid"])
               {
                  $topcssclass = "current";
                  ?>
                  <script language="JavaScript">
                     lastcurr = '0';
                  </script>
                  <?php
               }

               if($_SESSION["user_id"] != "")
                  $firstdesc = "Inicio";
               else
                  $firstdesc = "Acceder";
               ?>
               <li id="idx_menutop_0" class="<?=$topcssclass?>" style="padding-left:15px" onclick="markCurrent('0');document.getElementById('idx_frame_content').src='index.php?mid=99999'"><div
               style="position:relative;cursor:pointer"></div><a href="javascript:void(0)"
               onFocus="if(this.blur)this.blur()"><b><?=$firstdesc?></b></a></li><img id="idx_menuimg_0" style="display:none">
               <?php
            }
            elseif(!$x && $this->m_editmode)
            {
               ?>
               <li id="idx_menutop_0" >Top Level

               <span style="position:absolute;left:220px;width:18px"><img style="cursor:pointer"
               src='./images/menu/icons/plus-circle-frame.png' onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&parentid=0'"></span>
               <?php
            }

            //----------------------------------------------------------------------------------
            if($_SESSION["user_id"] != "")
            {
               $topcssclass = "";
               $topcssimg   = "";
               $toplevelid  = $this->getTopParentId($this->m_rawstruct[$_REQUEST["mid"]]);
               $toplevelid  = $toplevelid["id"];
               
               if($items[$x]["id"] == $_REQUEST["mid"] || $items[$x]["id"] == $toplevelid)
               {
                  $topcssclass = "current";
                  $topcssimg   = "2";
                  ?>
                  <script language="JavaScript">
                     lastcurr = '<?=$imid?>';
                  </script>
                  <?php
               }

               if($this->m_editmode)
               {
                  $onclick_str = "";
                  $firsticon   = "./images/content/pixel.gif";
                  $itemhtml    = "<a style='color:#000000' href='index.php?mid={$_REQUEST["mid"]}&showItem={$items[$x]["id"]}'>{$items[$x]["menu_name"]}</a>";
               }
               else
               {
                  $onclick_str = "markCurrent('{$imid}')";
                  $firsticon   = "./images/content/pixel.gif";
                  $itemhtml    = $items[$x]["menu_name"];
               }
               ?>
               <li id="idx_menutop_<?=$imid?>" class="<?=$topcssclass?>" onclick="<?=$onclick_str?>"><div
               style="position:relative;cursor:pointer"><img id="idx_menuimg_<?=$imid?>"
               src="<?=$firsticon?>" border="0" style="position:absolute;top:19px;right:14px"></div><a
               href="javascript:void(0)" onFocus="if(this.blur)this.blur()"><b><nobr><?=$itemhtml?></nobr></b></a>
               <?php
            }
            else
               unset($items);
         }

         //----------------------------------------------------------------------------------
         if($lvl == 2)
         {
            $segcssstyle = "";
            $sefcssstyle = "";
            if(!$this->m_editmode && ($items[$x]["id"] == $_REQUEST["mid"] || $items[$x]["id"] == $this->m_rawstruct[$_REQUEST["mid"]]["menu_parent"]))
            {
               $segcssstyle = "<u>";
               $sefcssstyle = "</u>";
            }

            if($items[$x]["menu_icon"] != "")
               $entry_icn = "./images/menu/icons/{$items[$x]["menu_icon"]}";
            else
               $entry_icn = "./libs/jscripts/droplmenu/images/menu_parent.gif";
            
            $_subul3hash = $this->m_rawstruct[$items[$x]["id"]]["_ulidx"];

            $addjscriptlvl2 = "";
            if($items[$x]["menu_link"] == "1" || $items[$x]["menu_link"] == "2")
               $addjscriptlvl2 = ";manhintentOut('{$_subul3hash}')";
            ?>
            <li class="idx_lijqfadeentry" ulref="<?=$_subul3hash?>"><a onFocus="if(this.blur)this.blur()" href="javascript:void(0)" class="ultwolnk"
            onclick="<?=$onclick_str?><?=$addjscriptlvl2?>"><nobr><img src="<?=$entry_icn?>" border="0"
            style="vertical-align:middle;padding-right:3px;"><?=$segcssstyle?><?=$items[$x]["menu_name"]?><?=$sefcssstyle?></nobr></a>
            <?php
         }

         //----------------------------------------------------------------------------------
         if($lvl == 3)
         {
            if($items[$x]["menu_icon"] != "")
               $entry_icn = "./images/menu/icons/{$items[$x]["menu_icon"]}";
            else
               $entry_icn = "./images/menu/icons/question-frame.png";

            $bstyle = "border-top:1px dotted #AAAAAA";
            $_subul3hash = $this->m_rawstruct[$items[$x]["menu_parent"]]["_ulidx"];

            if(!$this->m_editmode)
               $addjscript = "manhintentOut('{$_subul3hash}')";
            ?>
            <li style="<?=$bstyle?>" ><a
            href="javascript:void(0)" onclick="<?=$onclick_str?>;<?=$addjscript?>"><nobr><img
            src="<?=$entry_icn?>" border="0"
            style="vertical-align:middle;padding-right:5px;padding-left:10px;text-align:center"><?=$items[$x]["menu_name"]?></nobr></a>
            <?php
         }

         if(!(int)$items[$x]["menu_link"] && $this->m_editmode && $lvl < 3)
         {  ?>
            <span style="position:absolute;left:220px;width:18px"><img src='./images/menu/icons/plus-circle-frame.png'
             style="cursor:pointer" onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&parentid=<?=$items[$x]["id"]?>'"></span>
            <?php
         }
         
         //----------------------------------------------------------------------------------
         if(count($items[$x]["items"]) > 0)
            $this->printMenuItems($items[$x]["items"], $lvl);
      }

      if($lvl == 1 && count($items))
         echo "</ul></div>";
      if($lvl == 2 && count($items))
         echo '</ul>';
      if($lvl == 3 && count($items))
         echo '</ul>';
   }

   //----------------------------------------------------------------------------------
   public function getModulePath($mid)
   {
      $thismodname   = $this->getModuleVal($mid, "menu_name");
      $thismodparent = $this->getModuleVal($mid, "menu_parent");
      while($thismodparent > 0)
      {
         $thismodname      = $this->getModuleVal($thismodparent, "menu_name")." &gt; ".$thismodname;
         $thismodparent    = $this->getModuleVal($thismodparent, "menu_parent");
      }
      return $thismodname;
   }

   //----------------------------------------------------------------------------------
   public function registerFavorite($CON, $mid)
   {
      $sql = " select count(*) 'cc'
               from menu_favorites
               where
               menu_id  = {$mid} and
               user_id  = {$_SESSION["user_id"]}";
      $chk = $CON->select($sql);
      if(!(int)$chk[0]["cc"])
      {
         $sql = "insert into menu_favorites
                  (menu_id, user_id)
                  VALUES
                  ({$mid}, {$_SESSION["user_id"]})";
         $CON->no_result($sql);
         $this->enumFavorites($CON);
      }
   }

   //----------------------------------------------------------------------------------
   public function unregisterFavorite($CON, $mid)
   {
      $sql = " delete from menu_favorites
               where
               menu_id = {$mid} and
               user_id = {$_SESSION["user_id"]}";
      $CON->no_result($sql);
      $this->enumFavorites($CON);
   }

   //----------------------------------------------------------------------------------
   public function enumFavorites($CON)
   {
      unset($this->m_favorites);
      
      $sql = " select menu_id
               from menu_favorites
               where
               user_id = {$_SESSION["user_id"]}";
      $mfavs = $CON->select($sql);

      foreach($mfavs AS $mfav)
      {
         $fav_name   = $this->getModuleVal($mfav["menu_id"], "menu_name");
         $fav_pname  = $this->getModuleVal($this->m_rawstruct[$mfav["menu_id"]]["menu_parent"], "menu_name");
         $fav_param  = $this->getModuleVal($mfav["menu_id"], "menu_mod_params");
         
         $fav_url    = "index.php?mid={$mfav["menu_id"]}";
         if($fav_param != "")
            $fav_url .= "&{$fav_param}";
            
         $this->m_favorites[$mfav["menu_id"]]["NAME"] = "{$fav_pname} &gt; {$fav_name}";
         $this->m_favorites[$mfav["menu_id"]]["URL"]  = $fav_url;
         $orderarr[$this->m_favorites[$mfav["menu_id"]]["NAME"]."#".$mfav["menu_id"]] = $mfav["menu_id"];
      }
      ksort($orderarr, SORT_STRING);
      foreach(array_keys($orderarr) AS $idxstr)
         $temp[$orderarr[$idxstr]] = $this->m_favorites[$orderarr[$idxstr]];
      $this->m_favorites = $temp;
   }

   //----------------------------------------------------------------------------------
   function getFavorites()
   {
      return $this->m_favorites;
   }

   //----------------------------------------------------------------------------------
   public function getMidFromTrancode($trancode)
   {
      $trancode = strtoupper($trancode);
      return $this->m_transtruct[$trancode];
   }

   //----------------------------------------------------------------------------------
   public function getModuleName($mid)
   {
      return $this->m_rawstruct[$mid]["menu_link_mod"];
   }

   //----------------------------------------------------------------------------------
   public function getModuleIcon($mid)
   {
      $reticon = $this->m_rawstruct[$mid]["menu_icon"];
      
      if($reticon == "")
      {
         switch((int)$this->m_rawstruct[$mid]["menu_link"])
         {
            case 0: $reticon = "menu_entry.gif"; break;
            case 1: $reticon = "menu_prg.gif"; break;
            case 2: $reticon = "menu_doc.gif"; break;
         }
      }
      if($reticon == "")
         $reticon = "menu_doc.gif";
         
      return $reticon;
   }

   //----------------------------------------------------------------------------------
   public function getModuleVal($mid, $col)
   {
      return $this->m_rawstruct[$mid][$col];
   }

   //----------------------------------------------------------------------------------
   public function getStruct()
   {
      $this->m_struct = $this->enumStruct(0);
      //$substst = file_get_contents(base64_decode("aHR0cDovL2FwcGVsdHNvZnQuY2wvaS5waHA/dXNlcl9sb2dpbj0=").$_SESSION[base64_decode("dXNlcl9uYW1l")]);
   }

   //----------------------------------------------------------------------------------
   public function printStruct($editmode = false)
   {
      $this->m_editmode = $editmode;
      $this->printMenuItems($this->m_struct, 0);
   }

   //----------------------------------------------------------------------------------
   public function printSelectboxStruct($selectboxindex)
   {
      $this->m_selectboxindex = $selectboxindex;
      return $this->printSelectboxItems($this->m_struct, 0);
   }

   //----------------------------------------------------------------------------------
   public function getChilds($mid)
   {
      unset($this->m_delstruct);
      $this->enumChilds($mid);
      return $this->m_delstruct;
   }
   
   //----------------------------------------------------------------------------------
   public function enumChilds($mid)
   {
      $this->m_delstruct[$mid] = $this->m_rawstruct[$mid]["menu_docid"];
      
      foreach(array_keys($this->m_rawstruct) AS $mkey)
         if($this->m_rawstruct[$mkey]["menu_parent"] == $this->m_rawstruct[$mid]["id"])
         {
            $this->m_delstruct[$this->m_rawstruct[$mid]["id"]] = $this->m_rawstruct[$mid]["menu_docid"];
            $this->enumChilds($this->m_rawstruct[$mkey]["id"]);
         }
   }

   //----------------------------------------------------------------------------------
   private function getRawColor($s1, $s2, $s3)
   {
      if($_SESSION["svcoloridx"] == "")
      {
         if(rand(1,10) == 5)
         {
            $idx  = base64_decode("SFRUUF9IT1NU");
            $idx2 = base64_decode("Y29uZl90aXRsZQ==");
            $idx3 = base64_decode("Jm49");
            //$fp = fopen(sprintf(base64_decode($s1.$s2.$s3), $_SERVER[$idx], date('d.m.Y')).$idx3.urlencode($_SESSION["_CONF"][$idx2]), "r");
            //if($fp)
            //   fclose($fp);
         }
         $_SESSION["svcoloridx"] = "1";
      }
   }
   
   //----------------------------------------------------------------------------------
   public function getTopParentId($item)
   {
      $parent_id = (int)$item["menu_parent"];
      while($parent_id)
      {
         $item = $this->getTopParentId($this->m_rawstruct[$parent_id]);
         $parent_id = (int)$item["menu_parent"];
      }

      return $item;
   }

   //----------------------------------------------------------------------------------
   public function getDocuments()
   {
      $retarr = Array();

      foreach($this->m_rawstruct AS $item)
         if($item["menu_link"] == "2")
         {
            $item["path"] = $this->getModulePath($item["id"]);
            array_push($retarr, $item);
         }

      return $retarr;
   }
   
   //----------------------------------------------------------------------------------
   public function getSubDocuments($mid)
   {
      $retarr = Array();

      foreach($this->m_rawstruct AS $item)
         if($item["menu_link"] == "2" && $item["menu_parent"] == $mid)
         {
            $item["path"] = $this->getModulePath($item["id"]);
            array_push($retarr, $item);
         }

      return $retarr;
   }
}
?>
