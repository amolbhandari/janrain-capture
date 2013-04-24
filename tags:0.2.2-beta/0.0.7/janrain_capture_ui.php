<?php

/**
 * @package Janrain Capture
 *
 * Methods for inserting UI elements
 *
 */
class JanrainCaptureUi {

  /**
   * Sets up actions, initializes plugin name.
   *
   * @param string $name
   *   The plugin name to use as a namespace
   */
  function __construct() {
    add_filter('logout_url', 'fix_logout_url');
    if (!is_admin()) {
      add_action('wp_head', array(&$this, 'head'));
      add_action('wp_enqueue_scripts', array(&$this, 'registerScripts'));
      if (JanrainCapture::get_option(JanrainCapture::$name . '_ui_native_links') != '0') {
        add_filter('loginout', array(&$this, 'loginout'));
        add_filter('logout_url', array(&$this, 'logout_url'), 10, 2);
        add_filter('admin_url', array(&$this, 'admin_url'), 10, 3);
      }
      if (JanrainCapture::share_enabled())
        add_action('wp_footer', array(&$this, 'share_js'));
      else
        echo JanrainCapture::share_enabled();
        //add_action('wp_footer', array(&$this, 'share_js'));
    }
  }

  /**
   * Adds javascript libraries to the page.
   */
  function registerScripts() {
    if (JanrainCapture::get_option(JanrainCapture::$name . '_ui_colorbox') != '0')
      wp_enqueue_script('colorbox', WP_PLUGIN_URL . '/janrain-capture/colorbox/jquery.colorbox.js', array('jquery'));
    if (JanrainCapture::get_option(JanrainCapture::$name . '_ui_capture_js') != '0')
      wp_enqueue_script(JanrainCapture::$name . '_main_script', WP_PLUGIN_URL . '/janrain-capture/janrain_capture_ui.js');
  }

  /**
   * Method bound to the wp_head action.
   */
  function head() {
    if (JanrainCapture::get_option(JanrainCapture::$name . '_ui_colorbox') != '0')
      wp_enqueue_style('colorbox', WP_PLUGIN_URL . '/janrain-capture/colorbox/colorbox.css');
    if (JanrainCapture::share_enabled())
      wp_enqueue_style('janrain_share', WP_PLUGIN_URL . '/janrain-capture/stylesheet.css');

    $bp_js_path = JanrainCapture::get_option(JanrainCapture::$name . '_bp_js_path');
    $bp_server_base_url = JanrainCapture::get_option(JanrainCapture::$name . '_bp_server_base_url');
    $bp_bus_name = JanrainCapture::get_option(JanrainCapture::$name . '_bp_bus_name');
    $sso_addr = JanrainCapture::get_option(JanrainCapture::$name . '_sso_address');
    $sso_enabled = JanrainCapture::get_option(JanrainCapture::$name . '_sso_enabled');
    $bp_enabled = JanrainCapture::get_option(JanrainCapture::$name . '_backplane_enabled');
    $capture_addr = JanrainCapture::get_option(JanrainCapture::$name . '_ui_address') ? JanrainCapture::get_option(JanrainCapture::$name . '_ui_address') : JanrainCapture::get_option(JanrainCapture::$name . '_address');
    echo '<script type="text/javascript" src="' . esc_url('https://' . $capture_addr . '/cdn/javascripts/capture_client.js') . '"></script>';
    if ($_GET['janrain_capture_action'] == 'password_recover') {
      $query_args = array('action' => JanrainCapture::$name . '_profile');
      if ($screen = JanrainCapture::get_option(JanrainCapture::$name . '_recover_password_screen')) {
        $method = preg_replace('/^profile/', '', $screen);
        $query_args['method'] = $method;
      }
      $recover_password_url = add_query_arg($query_args, admin_url('admin-ajax.php'));
      echo <<<RECOVER
        <script type="text/javascript">
          jQuery(function(){
            jQuery.colorbox({
              href: '$recover_password_url',
              iframe: true,
              width: 700,
              height: 700,
              scrolling: false,
              overlayClose: false,
              current: '',
              next: '',
              previous: ''
            });
          });
          function janrain_capture_on_profile_update() {
            document.location.href = document.location.href.replace(/[\?\&]janrain_capture_action\=password_recover/, '');
          }
        </script>
RECOVER;
    }
    if ($bp_enabled && $bp_js_path)
      echo '<script type="text/javascript" src="' . esc_url($bp_js_path) . '"></script>';
    if ($bp_enabled && $bp_server_base_url && $bp_bus_name) {
      $bp_server_base_url = esc_url($bp_server_base_url);
      $bp_bus_name = JanrainCapture::sanitize($bp_bus_name);
      echo <<<BACKPLANE
<script type="text/javascript">
jQuery(function(){
  Backplane(CAPTURE.bp_ready);
    Backplane.init({
      serverBaseURL: "$bp_server_base_url",
      busName: "$bp_bus_name"
    });
});
</script>
BACKPLANE;
    }
    if ($sso_enabled && $sso_addr) {
      $client_id = JanrainCapture::get_option(JanrainCapture::$name . '_client_id');
      $client_id = JanrainCapture::sanitize($client_id);
      $xdcomm = admin_url('admin-ajax.php') . '?action=' . JanrainCapture::$name . '_xdcomm';
      $redirect_uri = admin_url('admin-ajax.php') . '?action=' . JanrainCapture::$name . '_redirect_uri';
      $logout = wp_logout_url('/');
      $sso_addr = esc_url('https://' . $sso_addr);
      echo <<<SSOA
<script type="text/javascript" src="$sso_addr/sso.js"></script>
<script type="text/javascript">
var sso_login_obj = {
  sso_server: "$sso_addr",
  client_id: "$client_id",
  redirect_uri: "$redirect_uri",
  logout_uri: "$logout",
  xd_receiver: "$xdcomm",
  bp_channel: ""
};
</script>
SSOA;
      if(!$bp_enabled){
      echo <<<SSO
console.log(sso_login_obj);
<script type="text/javascript">
JANRAIN.SSO.CAPTURE.check_login(sso_login_obj);
function janrain_capture_logout() {
  JANRAIN.SSO.CAPTURE.logout({
    sso_server: "$sso_addr",
    logout_uri: "$logout"
  });
}
</script>
SSO;
      }
    }
    echo '<script type="text/javascript">
      if (typeof(ajaxurl) == "undefined") var ajaxurl = "' . admin_url('admin-ajax.php') . '";
</script>';
  }

  /**
   * Method bound to the loginout filter.
   *
   * @param string $link
   *   The Login/Logout link html string.
   *
   * @return string $link
   *   The html to output to the page.
   */
  function loginout($link) {
    if (!is_user_logged_in()) {
      $href = do_shortcode('[' . JanrainCapture::$name . ' href_only="true"]');
      $classes = JanrainCapture::$name . '_anchor ' . JanrainCapture::$name . '_signin';
      if (strpos($link, ' class='))
        $link = preg_replace("/(\sclass=[\"'][^\"']+)([\"'])/i", "$1 $classes$2", $link);
      else
        $link = str_replace("href=", "class=\"$classes\" href=", $link);
      $link = preg_replace("/(href=[\"'])[^\"']+([\"'])/i", "$1$href$2", $link);
    } else {
      $sso_addr = JanrainCapture::get_option(JanrainCapture::$name . '_sso_address');
      $sso_enabled = JanrainCapture::get_option(JanrainCapture::$name . '_sso_enabled');
      if($sso_enabled && $sso_addr) {
        //TODO: shorthand function
        $logout = wp_logout_url($this->current_page_url());
        $href = "javascript:JANRAIN.SSO.CAPTURE.logout({ sso_server: 'https://$sso_addr', logout_uri: '$logout' });";
      }
      else { $href = wp_logout_url($this->current_page_url()); }
      $link = preg_replace("/href=[\"'][^\"']+[\"']/i", "href=\"$href\"", $link);
    }
    return $link;
  }

  /**
   * Method bound to the logout_url filter.
   *
   * @param string $logout_url
   *   The logout url as generated by the wp_logout_url method.
   *
   * @param string $redirect
   *   The redirect string passed in to the function.
   *
   * @return string $logout_url
   *   The modified logout URL.
   */
  function logout_url($logout_url, $redirect) {
    if (empty($redirect))
      return wp_logout_url($this->current_page_url());
    else
      return $logout_url;
  }

  /**
   * Method bound to the admin_url filter.
   *
   * @param string $url
   *   The URL generated by the admin_url method.
   *
   * @param string $path
   *   The path passed in to the admin_url method.
   *
   * @param int $blog_id
   *   The ID of the current blog.
   *
   * @return string $link
   *   The html to output to the page.
   */
  function admin_url($url, $path, $blog_id) {
    $current_user = wp_get_current_user();
    if ($path == 'profile.php' && $current_user->ID)
      return admin_url('admin-ajax.php') . "?action=" . JanrainCapture::$name . "_profile";
    else
      return $url;
  }

  /**
   * Returns the current page URL
   *
   * @return string
   *   Page URL
   */
  function current_page_url() {
    $pageURL = 'http';
    if ( isset($_SERVER["HTTPS"]) ) {
      if ($_SERVER["HTTPS"] == "on") $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
  }

  /**
   * Outputs Engage Share widget js to the footer.
   */
  function share_js() {
    $realm = JanrainCapture::get_option(JanrainCapture::$name . '_rpx_realm');
    echo <<<SHARE
<script type="text/javascript">
(function() {
  if (typeof window.janrain !== 'object') window.janrain = {};
  if (typeof window.janrain.settings !== 'object') window.janrain.settings = {};
  if (typeof window.janrain.settings.share !== 'object') window.janrain.settings.share = {};
  if (typeof window.janrain.settings.packages !== 'object') janrain.settings.packages = ['share'];
  else janrain.settings.packages.push('share');

  janrain.settings.share.message = "";

  function isReady() { janrain.ready = true; };
  if (document.addEventListener) {    document.addEventListener("DOMContentLoaded", isReady, false);
  } else {
    window.attachEvent('onload', isReady);
  }

  var e = document.createElement('script');
  e.type = 'text/javascript';
  e.id = 'janrainWidgets';

  if (document.location.protocol === 'https:') {
    e.src = 'https://rpxnow.com/js/lib/$realm/widget.js';
  } else {
    e.src = 'http://widget-cdn.rpxnow.com/js/lib/$realm/widget.js';
  }

  var s = document.getElementsByTagName('script')[0];
  s.parentNode.insertBefore(e, s);
})();
function setShare(url, title, desc, img, provider) {
  janrain.engage.share.setUrl(url);
  janrain.engage.share.setTitle(title);
  janrain.engage.share.setDescription(desc);
  janrain.engage.share.setImage(img);
  janrain.engage.share.showProvider(provider);
  janrain.engage.share.show();
}
</script>
SHARE;
  }
}

