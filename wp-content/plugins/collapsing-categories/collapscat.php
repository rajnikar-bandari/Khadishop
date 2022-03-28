<?php
/*
Plugin Name: Collapsing Categories
Plugin URI: https://robfelty.com/plugins
Description: Adds a new categories widget which uses javascript to expand and collapse categories to show the posts that belong to the category <a href='https://wordpress.org/plugins/collapsing-categories/other_notes'>Manual</a> | <a href='https://wordpress.org/plugins/collapsing-categories/faq'>FAQ</a>
Author: Robert Felty
Version: 2.2.2
Author URI: http://robfelty.com
Tags: sidebar, widget, categories, menu, navigation, posts

Copyright 2007-2017 Robert Felty

This file is part of Collapsing Categories

		Collapsing Categories is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License as published by 
    the Free Software Foundation; either version 2 of the License, or (at your
    option) any later version.

    Collapsing Categories is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Collapsing Categories; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/ 


$url = get_option('siteurl');
global $collapsCatVersion;
$collapsCatVersion = '2.0.5';

if (!is_admin()) {
  add_action('wp_enqueue_scripts', array('collapsCat', 'add_scripts')); 
  add_action( 'wp_head', array('collapsCat','get_head'));
} else {
  // call upgrade function if current version is lower than actual version
  $dbversion = get_option('collapsCatVersion');
  if (!$dbversion || $collapsCatVersion != $dbversion)
    collapscat::init();
}
add_action('init', array('collapsCat','init_textdomain'));
register_activation_hook(__FILE__, array('collapsCat','init'));

class collapsCat {
  public static function add_scripts() {
    wp_enqueue_script('jquery');
  }
	public static function init_textdomain() {
	  $plugin_dir = basename(dirname(__FILE__)) . '/languages/';
	  load_plugin_textdomain( 'collapsing-categories', WP_PLUGIN_DIR . $plugin_dir, $plugin_dir );
	}

	public static function init() {
    global $collapsCatVersion;
	  include('collapsCatStyles.php');
		$defaultStyles=compact('selected','default','block','noArrows','custom');
    $dbversion = get_option('collapsCatVersion');
    if ($collapsCatVersion != $dbversion && $selected!='custom') {
      $style = $defaultStyles[$selected];
      update_option( 'collapsCatStyle', $style);
      update_option( 'collapsCatVersion', $collapsCatVersion);
    }
    if( function_exists('add_option') ) {
      update_option( 'collapsCatOrigStyle', $style);
      update_option( 'collapsCatDefaultStyles', $defaultStyles);
    }
    if (!get_option('collapsCatOptions')) {
      include('defaults.php');
      update_option('collapsCatOptions', $defaults);
    }
    if (!get_option('collapsCatStyle')) {
      add_option( 'collapsCatStyle', $style);
		}
    if (!get_option('collapsCatSidebarId')) {
      add_option( 'collapsCatSidebarId', 'sidebar');
		}
    if (!get_option('collapsCatVersion')) {
      add_option( 'collapsCatVersion', $collapsCatVersion);
		}

	}


	public static function get_head() {
    echo "<style type='text/css'>\n";
    echo collapsCat::set_styles();
    echo "</style>\n";
	}
  public static function phpArrayToJS($array,$name) {
    /* generates javscript code to create an array from a php array */
    $script = "try { $name" . 
        "['catTest'] = 'test'; } catch (err) { $name = new Object(); }\n";
    foreach ($array as $key => $value){
      $script .= $name . "['$key'] = '" . 
          addslashes(str_replace("\n", '', $value)) . "';\n";
    }
    return($script);
  }
  public static function set_styles() {
    $widget_options = get_option('widget_collapscat');
    include('collapsCatStyles.php');
    $css = '';
    $oldStyle=true;
    foreach ($widget_options as $key=>$value) {
      $id = "widget-collapscat-$key-top";
      if (isset($value['style'])) {
        $oldStyle=false;
        $style = $defaultStyles[$value['style']];
        $css .= str_replace('{ID}', '#' . $id, $style);
      }
    }
    if ($oldStyle)
      $css=stripslashes(get_option('collapsCatStyle'));
    return($css);
  }
}


include_once( 'collapscatlist.php' );
function collapsCat($args='', $print=true) {
  global $collapsCatItems; 
  if (!is_admin()) {
    list($posts, $categories, $parents, $options) = 
        get_collapscat_fromdb($args);
    list($collapsCatText, $postsInCat) = list_categories($posts, $categories,
        $parents, $options);
    $url = get_option('siteurl');
    extract($options);
    include('symbols.php');
    if ($print) {
      include_once('collapsFunctions.js');
      print($collapsCatText);
      echo "<li style='display:none'><script type=\"text/javascript\">\n";
      echo "// <![CDATA[\n";
      echo '/* These variables are part of the Collapsing Categories Plugin 
      *  Version: 2.2.2
      *  $Id: collapscat.php 2273322 2020-04-01 19:31:19Z robfelty $
      * Copyright 2007-2020 Robert Felty (robfelty.com)
      */' . "\n";
      echo "var expandSym='$expandSym';\n";
      echo "var collapseSym='$collapseSym';\n";
      // now we create an array indexed by the id of the ul for posts
      echo collapsCat::phpArrayToJS($collapsCatItems, 'collapsItems');
      echo "addExpandCollapse('widget-collapscat-$number-top'," . 
          "'$expandSym', '$collapseSym', " . $accordion . ")";
      echo "// ]]>\n</script></li>\n";
    } else {
      return(array($collapsCatText, $postsInCat, $collapsCatItems ) );
    }
  }
}
function collapsing_categories_rest( WP_REST_Request $request ) {
  $parameters = $request->get_params();
  return collapsCat( $parameters, false );
}
add_action( 'rest_api_init', function () {
  register_rest_route( 'collapsing-categories/v1', '/get/', array(
    'methods' => 'GET',
    'callback' => 'collapsing_categories_rest',
  ) );
} );
include('collapscatwidget.php');
?>
