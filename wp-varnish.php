<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://github.com/pkhamre/wp-varnish
Version: 0.3
Author: <a href="http://github.com/pkhamre/">Pål-Kristian Hamre</a>
Description: A plugin for purging Varnish cache when content is published or edited.

Copyright 2010 Pål-Kristian Hamre  (email : post_at_pkhamre_dot_com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPVarnish {
  public $wpv_addr_optname;
  public $wpv_port_optname;
  public $wpv_secret_optname;
  public $wpv_timeout_optname;
  public $wpv_update_pagenavi_optname;
  public $wpv_update_commentnavi_optname;

  function WPVarnish() {
    global $post;

    $this->wpv_addr_optname = "wpvarnish_addr";
    $this->wpv_port_optname = "wpvarnish_port";
    $this->wpv_secret_optname = "wpvarnish_secret";
    $this->wpv_timeout_optname = "wpvarnish_timeout";
    $this->wpvarnish_purge_url_optname = "wpvarnish_purge_url";
    $this->wpv_update_pagenavi_optname = "wpvarnish_update_pagenavi";
    $this->wpv_update_commentnavi_optname = "wpvarnish_update_commentnavi";
    $this->wpv_use_adminport_optname = "wpvarnish_use_adminport";
    $this->wpv_vversion_optname = "wpvarnish_vversion";

    // Names for wp-config.php options
    $this->wpv_timeout_cfgname = "VARNISH_TIMEOUT";
    $this->wpv_use_adminport_cfgname = "VARNISH_USE_ADMIN_PORT";
    $this->wpv_vversion_cfgname = "VARNISH_VERSION";
    $this->wpv_showcfg_cfgname = "VARNISH_SHOWCFG";
    $this->wpv_hide_adminmenu_cfgname = "VARNISH_HIDE_ADMINMENU";

    // Default values for server options
    $this->wpv_addr_default = "127.0.0.1";
    $this->wpv_port_default = 80;
    $this->wpv_secret_default = "";

    $wpv_addr_optval = array ($this->wpv_addr_default);
    $wpv_port_optval = array ($this->wpv_port_default);
    $wpv_secret_optval = array ($this->wpv_secret_default);
    $wpv_timeout_optval = 5;
    $wpv_update_pagenavi_optval = 0;
    $wpv_update_commentnavi_optval = 0;
    $wpv_use_adminport_optval = 0;
    $wpv_vversion_optval = 2;

    if ( (get_option($this->wpv_addr_optname) == FALSE) ) {
      add_option($this->wpv_addr_optname, $wpv_addr_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_port_optname) == FALSE) ) {
      add_option($this->wpv_port_optname, $wpv_port_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_secret_optname) == FALSE) ) {
      add_option($this->wpv_secret_optname, $wpv_secret_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_timeout_optname) == FALSE) ) {
      add_option($this->wpv_timeout_optname, $wpv_timeout_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_update_pagenavi_optname) == FALSE) ) {
      add_option($this->wpv_update_pagenavi_optname, $wpv_update_pagenavi_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_update_commentnavi_optname) == FALSE) ) {
      add_option($this->wpv_update_commentnavi_optname, $wpv_update_commentnavi_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_use_adminport_optname) == FALSE) ) {
      add_option($this->wpv_use_adminport_optname, $wpv_use_adminport_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_vversion_optname) == FALSE) ) {
      add_option($this->wpv_vversion_optname, $wpv_vversion_optval, '', 'yes');
    }

    // Localization init
    add_action('init', array($this, 'WPVarnishLocalization'));

    // Add Administration Interface
    add_action('admin_menu', array($this, 'WPVarnishAdminMenu'));

    // When posts/pages are published, edited or deleted
    add_action('edit_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('edit_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    add_action('transition_post_status', array($this, 'WPVarnishPurgePostStatus'), 99, 3);
    add_action('transition_post_status', array($this, 'WPVarnishPurgeCommonObjectsStatus'), 99, 3);

    // When comments are made, edited or deleted
    add_action('comment_post', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('edit_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('trashed_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('untrashed_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('deleted_comment', array($this, 'WPVarnishPurgePostComments'),99);

    // When posts or pages are deleted
    add_action('deleted_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('deleted_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);

    // When xmlRPC call is made
    add_action('xmlrpc_call',array($this, 'WPVarnishPurgeAll'), 99);
  }

  function WPVarnishLocalization() {
    load_plugin_textdomain('wp-varnish', false, dirname(plugin_basename( __FILE__ ) ) . '/lang/');
  }

  //wrapper on WPVarnishPurgeCommonObjects for transition_post_status
  function WPVarnishPurgeCommonObjectsStatus($old, $new, $p) {
	  $this->WPVarnishPurgeCommonObjects($p->ID);
  }
  function WPVarnishPurgeCommonObjects() {
    $this->WPVarnishPurgeObject("/");
    $this->WPVarnishPurgeObject("/feed/");
    $this->WPVarnishPurgeObject("/feed/atom/");
    $this->WPVarnishPurgeObject("/category/(.*)");

    // Also purges page navigation
    if (get_option($this->wpv_update_pagenavi_optname) == 1) {
       $this->WPVarnishPurgeObject("/page/(.*)");
    }
  }

  // WPVarnishPurgeAll - Using a regex, clear all blog cache. Use carefully.
  function WPVarnishPurgeAll() {
    $this->WPVarnishPurgeObject('/(.*)');
  }

  // WPVarnishPurgeURL - Using a URL, clear the cache
  function WPVarnishPurgeURL($wpv_purl) {
    $wpv_purl = str_replace(get_bloginfo('url'),"",$wpv_purl);
    $this->WPVarnishPurgeObject($wpv_purl);
  }

  //wrapper on WPVarnishPurgePost for transition_post_status
  function WPVarnishPurgePostStatus($old, $new, $p) {
	  $this->WPVarnishPurgePost($p->ID);
  }
  // WPVarnishPurgePost - Takes a post id (number) as an argument and generates
  // the location path to the object that will be purged based on the permalink.
  function WPVarnishPurgePost($wpv_postid) {
    $wpv_url = get_permalink($wpv_postid);
    $wpv_permalink = str_replace(get_bloginfo('url'),"",$wpv_url);

    $this->WPVarnishPurgeObject($wpv_permalink);
  }

  // WPVarnishPurgePostComments - Purge all comments pages from a post
  function WPVarnishPurgePostComments($wpv_commentid) {
    $comment = get_comment($wpv_commentid);
    $wpv_commentapproved = $comment->comment_approved;

    // If approved or deleting...
    if ($wpv_commentapproved == 1 || $wpv_commentapproved == 'trash') {
       $wpv_postid = $comment->comment_post_ID;

       // Popup comments
       $this->WPVarnishPurgeObject('/\\\?comments_popup=' . $wpv_postid);

       // Also purges comments navigation
       if (get_option($this->wpv_update_commentnavi_optname) == 1) {
          $this->WPVarnishPurgeObject('/\\\?comments_popup=' . $wpv_postid . '&(.*)');
       }

    }
  }

  function WPVarnishAdminMenu() {
    if (!defined($this->wpv_hide_adminmenu_cfgname)) {
      add_options_page(__('WP-Varnish Configuration','wp-varnish'), 'WP-Varnish', 1, 'WPVarnish', array($this, 'WPVarnishAdmin'));
    }
  }

  // WpVarnishAdmin - Draw the administration interface.
  function WPVarnishAdmin() {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
       if (current_user_can('administrator')) {
          if (isset($_POST['wpvarnish_admin'])) {
             if (!empty($_POST["$this->wpv_addr_optname"])) {
                $wpv_addr_optval = $_POST["$this->wpv_addr_optname"];
                update_option($this->wpv_addr_optname, $wpv_addr_optval);
             }

             if (!empty($_POST["$this->wpv_port_optname"])) {
                $wpv_port_optval = $_POST["$this->wpv_port_optname"];
                update_option($this->wpv_port_optname, $wpv_port_optval);
             }

             if (!empty($_POST["$this->wpv_secret_optname"])) {
                $wpv_secret_optval = $_POST["$this->wpv_secret_optname"];
                update_option($this->wpv_secret_optname, $wpv_secret_optval);
             }

             if (!empty($_POST["$this->wpv_timeout_optname"])) {
                $wpv_timeout_optval = $_POST["$this->wpv_timeout_optname"];
                update_option($this->wpv_timeout_optname, $wpv_timeout_optval);
             }

             if (!empty($_POST["$this->wpv_update_pagenavi_optname"])) {
                update_option($this->wpv_update_pagenavi_optname, 1);
             } else {
                update_option($this->wpv_update_pagenavi_optname, 0);
             }

             if (!empty($_POST["$this->wpv_update_commentnavi_optname"])) {
                update_option($this->wpv_update_commentnavi_optname, 1);
             } else {
                update_option($this->wpv_update_commentnavi_optname, 0);
             }

             if (!empty($_POST["$this->wpv_use_adminport_optname"])) {
                update_option($this->wpv_use_adminport_optname, 1);
             } else {
                update_option($this->wpv_use_adminport_optname, 0);
             }

             if (!empty($_POST["$this->wpv_vversion_optname"])) {
                $wpv_vversion_optval = $_POST["$this->wpv_vversion_optname"];
                update_option($this->wpv_vversion_optname, $wpv_vversion_optval);
             }

          }

          if (isset($_POST['wpvarnish_purge_url_submit'])) {
              $this->WPVarnishPurgeURL($_POST["$this->wpvarnish_purge_url_optname"]);
          }

          if (isset($_POST['wpvarnish_clear_blog_cache']))
             $this->WPVarnishPurgeAll();

          ?><div class="updated"><p><?php echo __('Settings Saved!','wp-varnish' ); ?></p></div><?php
       } else {
          ?><div class="updated"><p><?php echo __('You do not have the privileges.','wp-varnish' ); ?></p></div><?php
       }
    }

         $wpv_update_pagenavi_optval = get_option($this->wpv_update_pagenavi_optname);
         $wpv_update_commentnavi_optval = get_option($this->wpv_update_commentnavi_optname);
    ?>
    <div class="wrap">
      <script type="text/javascript" src="<?php echo plugins_url('wp-varnish.js', __FILE__ ); ?>"></script>
      <h2><?php echo __("WordPress Varnish Administration",'wp-varnish'); ?></h2>
      <h3><?php echo __("IP address and port configuration",'wp-varnish'); ?></h3>
      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
    <?php
       // Get setting values and determine which settings are globally set 
       $global_settings = array();

       // load server information
       global $varnish_servers;
       if (is_array($varnish_servers)) {
          $global_settings[] = "servers";

          foreach ($varnish_servers as $server) {
             $server = explode(':', $server);
          
             $addrs[] = $server[0];
             $ports[] = (array_key_exists(1,$server) ? $server[1] : $this->wpv_port_default);
             $secrets[] = (array_key_exists(2,$server) ? $server[2] : $this->wpv_secret_default);
          }
       } else {
          $addrs = get_option($this->wpv_addr_optname);
          $ports = get_option($this->wpv_port_optname);
          $secrets = get_option($this->wpv_secret_optname);
       }
       
       // load timeout information
       if (defined($this->wpv_timeout_cfgname)) {
          $timeout = constant($this->wpv_timeout_cfgname);

          if (!is_int($timeout)) {
             $timeout = __("Invalid Value",'wp-varnish');
          }

          $global_settings[] = "timeout";
       } else {
          $timeout = $wpv_timeout_optval;
       }

       // load adminport information
       if (defined($this->wpv_use_adminport_cfgname)) {
          $use_adminport = constant($this->wpv_use_adminport_cfgname);
          $global_settings[] = "use_adminport";
       } else {
          $use_adminport = $wpv_use_adminport_optval;
       }

       // load varnish version information
       global $varnish_version;
       if (defined($this->wpv_vversion_cfgname)) {
          $vversion = constant($this->wpv_vversion_cfgname);

          if (!in_array($vversion, array(2,3))) {
             $vversion = __("Invalid Value",'wp-varnish');
          }
          
          $global_settings[] = "vversion";
       } else if (isset($varnish_version)) { 
          if (!in_array($varnish_version, array(2,3))) {
             $vversion = $varnish_version;
          } else {
             $vversion = __("Invalid Value",'wp-varnish');
          }
          $global_settings[] = "vversion";
       } else {
          $vversion = $wpv_vversion_optval;
       }
       
       // if SHOWCFG is set, draw all the stuff that is globally set
       if (count($global_settings) > 0 && defined($this->wpv_showcfg_cfgname) && constant($this->wpv_showcfg_cfgname)) {
          echo "<p>" . __("These values can't be edited since there's a global configuration located in <em>wp-config.php</em>. If you want to change these settings, please update the file or contact the administrator.",'wp-varnish') . "</p>\n";

          if (in_array('servers',$global_settings)) {
             ?>
             <table class="form-table" id="form-table">
                <tr valign="top">
                   <th scope="row"><?php echo __("Varnish Administration IP Address",'wp-varnish'); ?></th>
                   <th scope="row"><?php echo __("Varnish Administration Port",'wp-varnish'); ?></th>
                   <th scope="row"><?php echo __("Varnish Secret",'wp-varnish'); ?></th>
                </tr>
                <?php
                for ($i = 0; $i < count ($addrs); $i++) {
                   echo "<tr><td>{$addrs[$i]}</td><td>{$ports[$i]}</td><td>{$secrets[$i]}</td></tr>"; 
                }
             echo "</table>";
          }

          if (in_array("timeout",$global_settings)) {
             echo "<p>" . __("Timeout",'wp-varnish') . ": " . $timeout . " " . __("seconds",'wp-varnish') . "</p>";
          }

          if (in_array("use_adminport",$global_settings)) {
             echo '<p><input type="checkbox" disabled '.($use_adminport == 1 ? "checked":"").' /> ' . __("Use admin port instead of PURGE method.",'wp-varnish'). "</p>";
          }

          if (in_array("vversion",$global_settings)) {
             echo "<p>" . __("Varnish Version",'wp-varnish') . ": " . $vversion . "</p>";
          }
       }

       echo "<hr />";

       // draw all of the settings that are not globally set
       if (!in_array('servers',$global_settings)) {
          ?>
          <!-- <table class="form-table" id="form-table" width=""> -->
          <table class="form-table" id="form-table">
           <tr valign="top">
              <th scope="row"><?php echo __("Varnish Administration IP Address",'wp-varnish'); ?></th>
              <th scope="row"><?php echo __("Varnish Administration Port",'wp-varnish'); ?></th>
              <th scope="row"><?php echo __("Varnish Secret",'wp-varnish'); ?></th>
           </tr>
           <script>
           <?php
              echo "rowCount = $i\n";
              for ($i = 0; $i < count ($addrs); $i++) {
                 // let's center the row creation in one spot, in javascript
                 echo "addRow('form-table', $i, '$addrs[$i]', $ports[$i], '$secrets[$i]');\n";
              }
           ?>
           </script>
	      </table>
          <br/>
          <table>
           <tr>
              <td colspan="3"><input type="button" class="" name="wpvarnish_admin" value="+" onclick="addRow ('form-table', rowCount)" /> <?php echo __("Add one more server",'wp-varnish'); ?></td>
           </tr>
          </table>
       <?php
       }

       if (!in_array('timeout',$global_settings)) {
          echo "<p>" . __ ("Timeout",'wp-varnish') . ": <input class=\"small-text\" type=\"text\" name=\"wpvarnish_timeout\" value=\"$timeout\" /> " . __("seconds",'wp-varnish') . "</p>";
       }

       if (!in_array('use_adminport',$global_settings)) {
          echo '<p><input type="checkbox" name="wpvarnish_use_adminport" value="1" '.($use_adminport == 1 ? 'checked' : '').'  /> ' . __("Use admin port instead of PURGE method.",'wp-varnish') . "</p>";
       }
       ?>

      <p><input type="checkbox" name="wpvarnish_update_pagenavi" value="1" <?php if ($wpv_update_pagenavi_optval == 1) echo 'checked '?>/> <?php echo __("Also purge all page navigation (experimental, use carefully, it will include a bit more load on varnish servers.)",'wp-varnish'); ?></p>

      <p><input type="checkbox" name="wpvarnish_update_commentnavi" value="1" <?php if ($wpv_update_commentnavi_optval == 1) echo 'checked '?>/> <?php echo __("Also purge all comment navigation (experimental, use carefully, it will include a bit more load on varnish servers.)",'wp-varnish'); ?></p>
<?php
      if (!in_array('vversion',$global_settings)) {
         echo "<p>" . __("Varnish Version",'wp-varnish') . ": ";
         echo '<select name="wpvarnish_vversion">';
         echo '<option value="2" '.($vversion == 2 ? "selected":"").'>2.x</option>';
         echo '<option value="3" '.($vversion == 3 ? "selected":"").'>3.x</option>';
         echo '</select></p>';
      }
?>
      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __("Save Changes",'wp-varnish'); ?>" /></p>

      <p>
        Purge a URL:<input class="text" type="text" name="wpvarnish_purge_url" value="<?php echo get_bloginfo('url'); ?>" />
        <input type="submit" class="button-primary" name="wpvarnish_purge_url_submit" value="<?php echo __("Purge",'wp-varnish'); ?>" />
      </p>

      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_clear_blog_cache" value="<?php echo __("Purge All Blog Cache",'wp-varnish'); ?>" /> <?php echo __("Use only if necessary, and carefully as this will include a bit more load on varnish servers.",'wp-varnish'); ?></p>
      </form>
    </div>
  <?php
  }

  // WPVarnishPurgeObject - Takes a location as an argument and purges this object
  // from the varnish cache.
  function WPVarnishPurgeObject($wpv_url) {
    // look up server values
    global $varnish_servers;
    if (is_array($varnish_servers)) {
       foreach ($varnish_servers as $server) {
          $server = explode(':', $server);
          
          $wpv_purgeaddr[] = $server[0];
          $wpv_purgeport[] = (array_key_exists(1,$server) ? $server[1] : $this->wpv_port_default);
          $wpv_secret[] = (array_key_exists(2,$server) ? $server[2] : $this->wpv_secret_default);
       }
    } else {
       $wpv_purgeaddr = get_option($this->wpv_addr_optname);
       $wpv_purgeport = get_option($this->wpv_port_optname);
       $wpv_secret = get_option($this->wpv_secret_optname);
    }

    // Look up timeout value
    if (defined($this->wpv_timeout_cfgname) && is_int(constant($this->wpv_timeout_cfgname)))
       $wpv_timeout = constant($this->wpv_timeout_cfgname);
    else 
       $wpv_timeout = get_option($this->wpv_timeout_optname);

    // Look up use_adminport value
    if (defined($this->wpv_use_adminport_cfgname) && in_array(constant($this->wpv_use_adminport_cfgname), array(0,1)))
       $wpv_use_adminport = constant($this->wpv_use_adminport_cfgname);
    else 
       $wpv_use_adminport = get_option($this->wpv_use_adminport_optname);
    
    // Look up varnish version
    global $varnish_version;
    if (isset($varnish_version) && in_array($varnish_version, array(2,3)) )
       $wpv_vversion_optval = $varnish_version;
    else if (defined($this->wpv_vversion_cfgname) && in_array(constant($this->wpv_vversion_cfgname), array(2,3)))
       $wpv_vversion_optval = constant($this->wpv_vversion_cfgname);
    else
       $wpv_vversion_optval = get_option($this->wpv_vversion_optname);

    $wpv_wpurl = get_bloginfo('url');
    $wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
    $wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
    $wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
    $wpv_url = $wpv_blogaddr . $wpv_url;

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, $wpv_timeout);
      if (!$varnish_sock) {
        error_log("wp-varnish error: $errstr ($errno)");
        return;
      }

      if($wpv_use_adminport) {
        $buf = fread($varnish_sock, 1024);
        if(preg_match('/(\w+)\s+Authentication required./', $buf, $matches)) {
          # get the secret
          $secret = $wpv_secret[$i];
          fwrite($varnish_sock, "auth " . $this->WPAuth($matches[1], $secret) . "\n");
	  $buf = fread($varnish_sock, 1024);
          if(!preg_match('/^200/', $buf)) {
            error_log("wp-varnish error: authentication failed using admin port");
	    fclose($varnish_sock);
	    return;
	  }
        }
        if ($wpv_vversion_optval == 3) {
            $out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
          } else {
            $out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
          }
      } else {
        $out = "PURGE $wpv_url HTTP/1.0\r\n";
        $out .= "Host: $wpv_host\r\n";
        $out .= "Connection: Close\r\n\r\n";
      }
      fwrite($varnish_sock, $out);
      fclose($varnish_sock);
    }
  }

  function WPAuth($challenge, $secret) {
    $ctx = hash_init('sha256');
    hash_update($ctx, $challenge);
    hash_update($ctx, "\n");
    hash_update($ctx, $secret . "\n");
    hash_update($ctx, $challenge);
    hash_update($ctx, "\n");
    $sha256 = hash_final($ctx);

    return $sha256;
  }
}

$wpvarnish = new WPVarnish();

?>
