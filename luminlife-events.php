<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.stagehand.app
 * @since             1.0.0
 * @package           Luminlife_Events
 *
 * @wordpress-plugin
 * Plugin Name:       Stagehand Events
 * Plugin URI:        https://github.com/luminlife/wordpress
 * Description:       Shortcodes to include Stagehand web widgets
 * Version:           1.0.5
 * Author:            Lumin Arts Inc.
 * Author URI:        https://www.stagehand.app
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       luminlife-events
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define("SHORTCODE_NAME", 'stagehand_artist_events');
define("LUMINEVENTS_URL",
  "https://www.stagehand.app/widgets/v2/artist_events.widget.min.js");
define("LUMINEVENTS_STYLE_URL",
  "https://www.stagehand.app/widgets/v2/css/lumin_widget_styles.min.css");

if (!function_exists('write_log')) {
  function write_log($log) {
    if (true === WP_DEBUG) {
      if (is_array($log) || is_object($log)) {
        error_log(print_r($log, true));
      } else {
        error_log($log);
      }
    }
  }
}

/**
 * Enqueue inline Javascript. @see wp_enqueue_script().
 *
 * KNOWN BUG: Inline scripts cannot be enqueued before
 *  any inline scripts it depends on, (unless they are
 *  placed in header, and the dependant in footer).
 *
 * @param string      $handle    Identifying name for script
 * @param string      $src       The JavaScript code
 * @param array       $deps      (optional) Array of script names on which this script depends
 * @param bool        $in_footer (optional) Whether to enqueue the script before </head> or before </body>
 *
 * @return null
 */
function enqueue_inline_script( $handle, $js, $deps = array(), $in_footer = false ) {
  // Callback for printing inline script.
  $cb = function() use( $handle, $js ) {
    // Ensure script is only included once.
    if( wp_script_is( $handle, 'done' ) )
      return;
    // Print script & mark it as included.
    echo "<script type=\"text/javascript\" id=\"js-$handle\">\n$js\n</script>\n";
    global $wp_scripts;
    $wp_scripts->done[] = $handle;
  };
  // (`wp_print_scripts` is called in header and footer, but $cb has re-inclusion protection.)
  $hook = $in_footer ? 'wp_print_footer_scripts' : 'wp_print_scripts';

  // If no dependencies, simply hook into header or footer.
  if( empty($deps)) {
    add_action( $hook, $cb );
    return;
  }

  // Delay printing script until all dependencies have been included.
  $cb_maybe = function() use( $deps, $in_footer, $cb, &$cb_maybe ) {
    foreach( $deps as &$dep ) {
      write_log("enqueue_inline_script: processing '${dep}'");
      if( !wp_script_is( $dep, 'done' ) ) {
        // Dependencies not included in head, try again in footer.
        if( $in_footer ) {
          add_action( 'wp_print_footer_scripts', $cb_maybe, 11 );
        }
        else {
          // Dependencies were not included in `wp_head` or `wp_footer`.
          write_log("enqueue_inline_script: '${dep}' not in head/footer");
        }
        return;
      }
    }
    call_user_func( $cb );
  };

  add_action( $hook, $cb_maybe, 0 );
}

function lumin_events_shortcode($attributes) {
  /*
   * We only do shortcodes on a page
   */
  if (!is_page()) {
    return "<p>'".SHORTCODE_NAME."' shortcode is only usable on a page</p>";
  }

  /*
   * Get any attributes for the shortcode
   */
  extract(shortcode_atts(array(
    'elem_id' => NULL,
    'artist_id'=> NULL,
    'limit' => NULL,
    'show_date_badge' => NULL,
    'disable_description' => NULL,
    'target' => NULL,
  ), $attributes));

  $divId = 'luminlife-events';
  /*
   * User can give a unique identifier if there are using multiple per page
   */
  if (isset($elem_id)) {
    $divId .= "-{$elem_id}";
  }

  /*
   * Set up the options for the event widget
   */
  $optionsStr = "{";
  if (isset($venue_id)) {
    $optionsStr .= "artistId: ${artist_id},";
  }
  if (isset($limit)) {
    $optionsStr .= "limit: ${limit},";
  }
  if (isset($show_date_badge)) {
    $optionsStr .= "showCalendarDateBadge: ${show_date_badge},";
  }
  if (isset($disable_description)) {
    $optionsStr .= "disableEventDescription: ${disable_description},";
  }
  if (isset($target)) {
    $optionsStr .= "target: ${target},";
  }
  $optionsStr .= "}";

  wp_enqueue_script('luminlife-events_scripts');

  $inlineScript = "  stagehandArtistEvents('${divId}', ${optionsStr});";
  if (function_exists('wp_add_inline_script')) {
    wp_add_inline_script('luminlife-events_scripts', $inlineScript);
  } else {
    enqueue_inline_script('luminlife-events_scripts_inline',
      $inlineScript,
      array('luminlife-events_scripts'),
      true);
  }

  $return_string = "<div id='${divId}'></div>";
  return $return_string;
}

function register_shortcodes() {
  add_shortcode(SHORTCODE_NAME, 'lumin_events_shortcode');
}

add_action('init', 'register_shortcodes');

function register_scripts() {
  /*
   * Prepare the javascript to be enqueued later
   */
  wp_register_script('luminlife-events_scripts',
    esc_url_raw(LUMINEVENTS_URL),
    '',
    null);

  if (is_page()) {
    global $post;

    /*
     * Look for the shortcode on the page. If we have one,
     * we enqueue our widget stylesheet.
     */
    if (has_shortcode($post->post_content, SHORTCODE_NAME)) {
      wp_enqueue_style('luminlife-events_style',
        esc_url_raw(LUMINEVENTS_STYLE_URL),
        '',
        null);
    }
  }
}

add_action('wp_enqueue_scripts', 'register_scripts');
