<?php

/*
 * Plugin Name:       00 - Create Posts and Menus
 * Plugin URI:        https://github.com/wolozo/create-posts-menus
 * GitHub Plugin URI: https://github.com/wolozo/create-posts-menus
 * Description:       Create Posts and Menus within the theme code.
 * Version:           0.0.2
 * Author:            Wolozo
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cpam
 * Requires WP:       4.3
 * Requires PHP:      5.3
 */

// @fixme w_cpam_create_menu_items() Custom Links are not verified and will be duplicated.
// @todo Assign Menus To Theme Locations
// @todo Posts: post_excerpt, comment_status, post_password, Featured Image URL
// @todo Posts: Global: comment_status, ping_status, update_post_meta()

/**
 * Make sure our global variables are set.
 */
function w_cpam_get_values() {
  static $w_cpm_values;

  if ( is_array( $w_cpm_values ) ) : return $w_cpm_values; endif;

  if ( false === $w_cpm_values || ! function_exists( 'w_cpm_values' ) ) {
    $w_cpm_values = false;

    return false;
  }

  return $w_cpm_values = w_cpm_values();
}

add_action( 'init', 'w_cpam_get_values', 1 );

/**
 * Creates a navigation menu using $w_cpam_menuName
 *
 * @param $menuName
 *
 * @return bool
 */
function w_cpam_create_menu( $menuName ) {

  if ( ! is_nav_menu( $menuName ) ) {
    wp_create_nav_menu( $menuName );

    return true;
  }

  return false;
}

/**
 * Creates the navigation menu from w_cpm_values()
 *
 */
function w_cpam_create_menus() {
  if ( false === ( $w_cpm_values = w_cpam_get_values() ) || ! isset( $w_cpm_values[ 'menus' ] ) ) {
    return;
  }

  foreach ( $w_cpm_values[ 'menus' ] as $menuName => $menu ) {

    $is_nav_menu = is_nav_menu( $menuName );
    if ( ! $is_nav_menu || ( $is_nav_menu && $menu[ 'delete-existing' ] ) ) {
      wp_delete_nav_menu( $menuName );
      wp_create_nav_menu( $menuName );
    }

    unset( $menu[ 'delete-existing' ] );

    w_cpam_create_menu_items( $menuName, $menu );
  }
}

add_action( 'init', 'w_cpam_create_menus', 30 );

/**
 * @param $menuName
 * @param $menu
 *
 * @return bool
 */
function w_cpam_create_menu_items( $menuName, $menu ) {

  if ( ! isset( $menuName ) || ! isset( $menu ) //
       || false === $menuObject = wp_get_nav_menu_object( $menuName )
  ) {
    var_dump( 'something went wrong' );
    exit;

    return false;
  }

  $menuItems = wp_get_nav_menu_items( $menuName );

  foreach ( $menu as $index => $menuItem ) {
    if ( ! isset( $menuItem[ 'title' ] ) ) {
      var_dump( 'title required' );
      exit;
    }

    // Skip posts already within navigation menu
    if ( ! array_key_exists( 'url', $menuItem ) ) {

      if ( ! isset( $menuItem[ 'post-slug' ] ) ) {
        $menuItem[ 'post-slug' ] = sanitize_title( $menuItem[ 'title' ] );
      }

      $menuItem_objectID = get_page_by_path( $menuItem[ 'post-slug' ] )->ID;

      // Skip already created menu items by check of $menuItem_objectID exist within $menuItems as object_id
      $chk = false;

      foreach ( $menuItems as $index => $object ) {
        if ( $chk = $menuItem_objectID == (string) $object->object_id ) : break; endif;
      }

      if ( $chk ) : continue; endif;
    }

    $menu_item_data = array(
      'menu-item-title'  => $menuItem[ 'title' ],
      'menu-item-status' => 'publish',
    );

    if ( isset( $menuItem[ 'parent-slug' ] ) ) {
      $menuItems = wp_get_nav_menu_items( $menuName );

      foreach ( $menuItems as $index => $object ) {

        if ( get_page_by_path( $menuItem[ 'parent-slug' ] )->ID == (string) $object->object_id ) {
          $menu_item_data[ 'menu-item-parent-id' ] = $object->ID;

          break;
        }
      }
    }

    if ( isset( $menuItem[ 'url' ] ) ) {
      $menu_item_data[ 'menu-item-url' ] = $menuItem[ 'url' ];
    } else {
      $menu_item_data[ 'menu-item-object' ]    = 'page';
      $menu_item_data[ 'menu-item-object-id' ] = $menuItem_objectID;
      $menu_item_data[ 'menu-item-type' ]      = 'post_type';
    }

    if ( isset( $menuItem[ 'position' ] ) ) {
      $menu_item_data[ 'menu-item-position' ] = $menuItem[ 'position' ];
    }

    if ( isset( $menuItem[ 'classes' ] ) ) {
      $menu_item_data[ 'menu-item-classes' ] = $menuItem[ 'classes' ];
    }

    if ( isset( $menuItem[ 'target' ] ) ) {
      $menu_item_data[ 'menu-item-target' ] = $menuItem[ 'target' ];
    }

    if ( isset( $menuItem[ 'attr_title' ] ) ) {
      $menu_item_data[ 'menu-item-attr-title' ] = $menuItem[ 'attr_title' ];
    }

    wp_update_nav_menu_item( $menuObject->term_id, 0, $menu_item_data );
  }
}

//add_action( 'init', 'w_cpam_create_menu_items', 20 );

/**
 * Create posts on init
 *
 * @todo handle post_type
 */
function w_cpam_create_posts() {

  if ( false === ( $w_cpm_values = w_cpam_get_values() ) && ! is_array( $w_cpm_values[ 'posts' ] ) ) {
    return;
  }

  foreach ( $w_cpm_values[ 'posts' ] as $index => $newPage ) {

    if ( ! isset( $newPage[ 'title' ] ) ) {
      var_dump( '$newPage[ \'title\' ] REQUIRED' );
      var_dump( $newPage );
      die;
    }

    // add slug from title if empty.
    if ( ! array_key_exists( 'slug', $newPage ) ) : $newPage[ 'slug' ] = sanitize_title( $newPage[ 'title' ] ); endif;

    // bypass existing slugs
    if ( ! w_cpam_slug_exist( $newPage[ 'slug' ] ) ) {

      $postarr = array(
        'post_title'     => $newPage[ 'title' ],
        'post_type'      => 'page',
        'post_name'      => $newPage[ 'slug' ],
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'post_content'   => $newPage[ 'content' ],
        'post_status'    => 'publish',
        'post_author'    => 1,
        'menu_order'     => 0
      );

      // and create page
      $postID = wp_insert_post( $postarr );

      // Handle post_meta
      if ( 0 != $postID && isset( $newPage[ 'post_meta' ] ) ) {
        foreach ( $newPage[ 'post_meta' ] as $index => $post_meta ) {
          update_post_meta( $postID,
                            $post_meta[ 'meta_key' ],
                            $post_meta[ 'meta_value' ] );
        }
      }
    }
  }
}

add_action( 'init', 'w_cpam_create_posts', 10 );

/**
 * Check if slug exists
 *
 * @param $slug
 *
 * @return bool
 */
function w_cpam_slug_exist( $slug ) {
  global $wpdb;

  if ( $wpdb->get_row( "SELECT post_name FROM wp_posts WHERE post_name = '" . $slug . "'", 'ARRAY_A' ) ) {
    return true;
  }

  return false;
}