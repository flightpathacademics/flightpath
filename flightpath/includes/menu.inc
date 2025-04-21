<?php

/**
 * This file contains mostly menu-related all-purpose functions
 * for FlightPath
 */

 

/**
 * Look for modules implementing hook_menu_handle_replacement_pattern, and apply to str. 
 *
 * @param unknown_type $str
 */
function menu_convert_replacement_pattern($str) {  
  // Find modules which implement the hook_menu_handle_replacement_pattern function.
  $modules = modules_implement_hook("menu_handle_replacement_pattern");
  foreach($modules as $module) {
    $str = call_user_func($module . '_menu_handle_replacement_pattern', $str);
  }
  
  return $str;
}



 
/**
 * Go through all installed modules and rebuild the menu_router table,
 * based on each module's hook_menu function.
 */
function menu_rebuild_cache($bool_display_message = TRUE) {


  // Begin by wiping out the menu_router table
  db_query("TRUNCATE TABLE menu_router ");
  
  $modules = modules_implement_hook("menu");
    
  foreach ($modules as $module) {
    $items = call_user_func($module . "_menu");
    
    // Invoke hook_menu_alter() to see if we need to alter these items or not.
    invoke_hook('menu_alter', array(&$items));
      
    // Okay, now go through the $items array, and write the needed information
    // to the menu_router array.
    foreach ($items as $path => $item) {
      if (is_numeric($path)) continue; // problem, so skip.
      
      // Update our menu_router table.
      // Now, insert the new one.
      db_query("INSERT INTO menu_router
                  (path, access_callback, access_arguments, page_callback, page_arguments, title, description, type, weight, icon, page_settings, tab_family, tab_parent, file)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ", $path, @$item["access_callback"], @serialize($item["access_arguments"]), @$item["page_callback"],
                     @serialize($item['page_arguments']), @$item['title'], @$item['description'], @$item['type'], @$item['weight'], @$item['icon'],
                     @serialize($item['page_settings']), @$item['tab_family'], @$item['tab_parent'], @$item['file']);
    }
  }  
  
  if ($bool_display_message) {
    fp_add_message(t("The menu router has been rebuilt"));
  }
    
}


function menu_get_module_path($module, $bool_include_file_system_path = FALSE) {

  $system_path = "";
  if ($bool_include_file_system_path) {
    $system_path = $GLOBALS["fp_system_settings"]["file_system_path"] . "/";
  }
  
  if (isset($GLOBALS["fp_system_settings"]["modules"][$module]["path"])) {
    return $system_path . $GLOBALS["fp_system_settings"]["modules"][$module]["path"];
  }
  else {
    return FALSE;
  }
    
}



/**
 * Looks at the router item's details (from menu_get_item)
 * and returns TRUE or FALSE if the user can access this item. 
 */
function menu_check_user_access($router_item) {
  
  if (!$router_item || $router_item == NULL || $router_item == FALSE || $router_item == '') {
    return FALSE;  // No router item given, so return FALSE.
  }
      
  $access = FALSE;
  
  if ($router_item['access_callback'] === '1' || $router_item["access_callback"] === 1 || $router_item["access_callback"] === TRUE) {    
    $access = TRUE;    
  }
  
  if (isset($router_item["access_arguments"]) && is_array($router_item["access_arguments"])) {
    if (!isset($router_item['access_callback']) || $router_item["access_callback"] == "") {
      // If we specified access arguments, but not a callback, assume it
      // is the function user_has_permission().
      $router_item["access_callback"] = "user_has_permission";
    }
  }
  
  // Does the access_arguments have any wildcards?
  if (isset($router_item['access_arguments']) && is_array($router_item['access_arguments'])) {
    if (count($router_item['access_arguments']) == 1 && !$router_item['access_arguments'][0]) {
      // Meanng, access arguments has been created, but nothing there.  We likely need to populate it if there are wildcards in this item.      
      $router_item = menu_get_item($router_item['path']);      
    } 
  }
  
  
  
  if (!$access && $router_item["access_callback"] != "") {
    
    // We need to see if the user has the right permissions to use this item.  We will do this
    // by calling the access_callback, passing it the access_arguments
    $access = call_user_func_array($router_item["access_callback"], $router_item["access_arguments"]);
  }    
    
    
  return $access;
}



function menu_execute_page_request($path = "") {
      
  if ($path == "") {  
    $path = @$_GET["q"];
  }
  
  //If the path is blank, figure out what the "font page" is, and use that path.
  if ($path == "") {
    $path = variable_get("front_page", "main");
  }
  
  if ($router_item = menu_get_item($path)) {    
    // Let's save the router item in the GLOBALS array, so we can use information from it
    // throughout FlightPath, if we need to.
    $GLOBALS["fp_current_menu_router_item"] = $router_item;
    
    // If the menu item contains a "redirect", then we should perform
    // an fp_goto to that immediately.
    if (isset($router_item["page_settings"]["redirect"])) {
      $p = $router_item["page_settings"]["redirect"]["path"];
      $q = $router_item["page_settings"]["redirect"]["query"];
      fp_goto($p, $q);
      return;
    }
       
    // Let's figure out if the user has access to this menu item or not.  
    if (menu_check_user_access($router_item)) {
          
        
          
      if ($router_item['file'] != "") {      
        require_once($router_item['file']);
      }
      $page = array();
      $page["content"] = call_user_func_array($router_item['page_callback'], $router_item['page_arguments']);      
      $page["path"] = $path;
      $page["title"] = $router_item["title"];
      $page["router_item"] = $router_item;

      
      return $page;    
           
    }
    else {      
      return MENU_ACCESS_DENIED;
    }
  }
  return MENU_NOT_FOUND;  
}


/**
 * Return menu_items whose path begins with the menu_root.
 * Ex:   "tools"  would return tools/fun and tools/here/there
 */
function menu_get_items_beginning_with($menu_root) {
  $rtn = array();
  
  $res = db_query("SELECT path FROM menu_router WHERE path LIKE ? 
                    ORDER BY weight, title", $menu_root . "%");
  while ($cur = db_fetch_array($res)) {
    $path = $cur["path"];
    $item = menu_get_item($path);
    if ($item) {
      $rtn[] = $item;
    }
  }
  
  
  return $rtn;
}


function menu_get_items_in_tab_family($tab_family) {
  $rtn = array();
  
  $res = db_query("SELECT path FROM menu_router WHERE tab_family = ?
                   ORDER BY weight, title", $tab_family);
  while ($cur = db_fetch_array($res)) {
    $path = $cur["path"];
    $item = menu_get_item($path);
    if ($item) {
      $rtn[] = $item;
    }    
  }

  return $rtn;
}



/**
 * Figure out which menu_router path matches the supplied path and return it.
 *
 */
function menu_get_item($path) {
  // Since the menu item may be using wildcards, we must 
  // try to figure out exactly which defined path is the best one
  // to take.
  
  $menu_items = array();

  /*  
  if ($bool_reset) {
    unset($GLOBALS["fp_menu_items"]);
  }
  */
  
  // To cut down on the amount we need to query, we will use a global variable.
  if (!isset($GLOBALS["fp_menu_items"]) || !is_array($GLOBALS["fp_menu_items"]) || count($GLOBALS["fp_menu_items"]) == 0) {
    // The global array is not set.  Let's populate it from the database.
    $res = db_query("SELECT path FROM menu_router");
    while ($cur = db_fetch_array($res)) {
      $GLOBALS["fp_menu_items"][] = $cur["path"];
    }
  }
  
  $menu_items = $GLOBALS["fp_menu_items"];
  
  // Okay, first things first, does the path *exactly* match anything in our GLOBALS array?
  if (in_array($path, $menu_items)) {    
    return menu_get_menu_router_item_from_db($path);
  }
  
  // If we are here, we didn't see an exact match.  That may mean we are using wildcards,
  // and therefore we should pick only the best match.
  // For example, if the path is "node/5/edit", we should return the
  // menu_router item "node/%/edit", but not "node/%/%".  We will figure this out
  // by breaking up our paths into pieces, and then comparing each piece, and scoring URLs
  // as we do on how many levels they match on.  The URL with the higest match wins.
  
  $defined_score = array();
  
  
  // Now, go through and find the best URL definition that matches the given URL.
  
  $given_pieces = explode("/", $path);
  foreach ($menu_items as $defined_url) {
    
    $defined_pieces = explode("/", $defined_url);
    
    // First, make only look for the ones with the exact same count as us...
    if (count($defined_pieces) != count($given_pieces)) continue;
    
    if (!isset($defined_score[$defined_url])) $defined_score[$defined_url] = 0;
    
    $defined_score[$defined_url]++;
    
    // Okay, now go through the pieces and compare.
    foreach ($defined_pieces as $c => $defined_piece) {
      
      // If it's exactly a match, then inc its score.
      if ($defined_piece == $given_pieces[$c]) {
        $defined_score[$defined_url]++;
      }    
      
      // If it's totally off, then kick it out!
      if ($defined_piece != "%" && $defined_piece != $given_pieces[$c]) {      
        unset($defined_score[$defined_url]);
        break;
      }
      
    }
    
  }
  
  arsort($defined_score);
  
  $best_match_path = "";
  if (count($defined_score) > 0) {
    $best_match_path = key($defined_score);
  }
  
  // Okay, the best_match_path is the one that best fits our path.  Let's return the details
  // for it from menu_router.
  if ($best_match_path) {
    return menu_get_menu_router_item_from_db($best_match_path);
  }
  
  // Else, nothing was found!
  return NULL;
  
  
}


/**
 * Return array from menu_router for this item. * 
 */
function menu_get_menu_router_item_from_db($path) {
  $res = db_query("SELECT * FROM menu_router WHERE path = ?", array($path));
  $cur = db_fetch_array($res);

  if ($cur["path"] != $path) {
    // It was not found!
    return NULL;
  }  
  
  // Unserialize the things which are supposed to be unserialized.
  $cur["page_arguments"] = unserialize($cur["page_arguments"]);
  if (!is_array($cur["page_arguments"])) {
    $cur["page_arguments"] = array();
  }

  // If any of the page_arguments is a number by itself, we will instead
  // use the number of the URL args.
  // For example, if the URL path is "node/edit/apple"
  // and there is a page_argument for the number 2, we will replace
  // it with "apple".  This is how the menu system can use wildcards from the url
  // for the page arguments.
  foreach ($cur["page_arguments"] as $c => $arg) {
    if (is_numeric($arg)) {
      $temp = explode("/", $_REQUEST["q"]);
      $cur["page_arguments"][$c] = @$temp[$arg];
    }
  }
  
  
  $cur["access_arguments"] = unserialize($cur["access_arguments"]);
  if (!is_array($cur["access_arguments"])) {
    $cur["access_arguments"] = array();
  }

  
  // Same situation for access_arguments as the page_arguments above.
  foreach ($cur["access_arguments"] as $c => $arg) {
    if (is_numeric($arg)) {      
      $temp = explode("/", $_REQUEST["q"]);      
      $cur["access_arguments"][$c] = @$temp[$arg];      
    }
  }
  
  //fpm($_REQUEST);
  //fpm($cur);
  
  $cur["page_settings"] = unserialize($cur["page_settings"]);
  if (!is_array($cur["page_settings"])) {
    $cur["page_settings"] = array();
  }
  
  // If there is a title property, run it through t()
  if ($cur["title"] != "") {
    $cur["title"] = t($cur["title"]);
  }
  
  // If there is a description, run through t()
  if ($cur["description"] != "") {
    $cur["description"] = t($cur["description"]);
  }
  
  // If the page_settings has menu links, run their texts through t().
  foreach ($cur["page_settings"] as $key => $val) {
    if ($key == "menu_links" && is_array($val)) {
      foreach ($val as $c => $mitems) {
        if ($mitems["text"] != "") {
          $cur["page_settings"]["menu_links"][$c]["text"] = t($mitems["text"]);
        }
      }
    }
  }
  
  
  return $cur;    
      
}
 
