/**
 * The following are all of the settings that can be set in wp-config.php
 * to globally configure  wp-varnish. You can insert this file into your
 * wp-config.php file and uncomment the settings you wish to use.
 */

/**
 * Varnish Server Settings
 *
 * The $varnish_servers array is a list of all varnish servers to apply
 * purge commands to. The value is an array of strings with the format:
 *
 *   host:port:secret
 *
 * Host is required. Secret is optional. Port is optional if secret is
 * not needed. Port defaults to 80 and secret defaults to the empty
 * string. The secret is used to authenticate the request when using
 * the admin console rather than PURGE method.
 *
 * Example:
 * 
 * global $varnish_servers;
 * $varnish_servers = array(
 *     'host1.example.com:80:secret',
 *     'host2.example.com:8080:secret2'
 *     'host3.example.com:8080'
 *     'host4.example.com'
 * );
 */
#global $varnish_servers;
#$varnish_servers = array(
#    'host.example.com:80:secret'
#);

/**
 * Hide the varnish config from the admin menu entirely.
 */
#define('VARNISH_HIDE_ADMINMENU',1);

/**
 * Show these globally defined values in the WordPress admin interface.
 * Useful for debugging.
 */
#define('VARNISH_SHOWCFG',1);

/**
 * Timeout value to use when contacting Varnish servers. Must be an integer.
 */
#define('VARNISH_TIMEOUT',5);

/**
 * Varnish version. Valid options are 2 and 3
 */
#define('VARNISH_VERSION',3);

/**
 * If this value is true connect to the varnish admin console rather
 * than sending an HTTP PURGE request.
 */
#define('VARNISH_USE_ADMIN_PORT',1);
