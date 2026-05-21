<?php
/**
 * Plugin Name: Easy WebP Optimizer
 * Plugin URI:  https://github.com/marcelovianaandrade/easy-webp-optimizer
 * Description: Bulk convert JPG/PNG to WebP, resize proportionally, and serve WebP automatically to compatible browsers. No API key, no paid plans.
 * Version:     1.1.1
 * Author:      Marcelo Andrade
 * Author URI:  https://github.com/marcelovianaandrade
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-webp-optimizer
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EASY_WEBP_MAX_WIDTH', 1280 );
define( 'EASY_WEBP_QUALITY', 82 );
define( 'EASY_WEBP_BATCH_SIZE', 5 );
define( 'EASY_WEBP_VERSION', '1.1.1' );
define( 'EASY_WEBP_PLUGIN_FILE', __FILE__ );

class Easy_WebP_Optimizer {

    public static function init() {
        add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        add_action( 'wp_ajax_easy_webp_get_queue', [ __CLASS__, 'ajax_get_queue' ] );
        add_action( 'wp_ajax_easy_webp_process_batch', [ __CLASS__, 'ajax_process_batch' ] );
        add_action( 'wp_ajax_easy_webp_toggle_delivery', [ __CLASS__, 'ajax_toggle_delivery' ] );

        if ( get_option( 'easy_webp_delivery_enabled', false ) ) {
            add_filter( 'wp_get_attachment_image', [ __CLASS__, 'filter_attachment_image' ], 10, 5 );
            add_filter( 'the_content', [ __CLASS__, 'filter_content_images' ], 99 );
        }

        register_deactivation_hook( EASY_WEBP_PLUGIN_FILE, [ __CLASS__, 'on_deactivation' ] );
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'easy-webp-optimizer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public static function register_admin_page() {
        add_media_page(
            __( 'WebP Optimizer', 'easy-webp-optimizer' ),
            __( 'WebP Optimizer', 'easy-webp-optimizer' ),
            'manage_options',
            'easy-webp-optimizer',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( 'media_page_easy-webp-optimizer' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'easy-webp-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.js',
            [ 'jquery' ],
            EASY_WEBP_VERSION,
            true
        );

        wp_localize_script( 'easy-webp-admin', 'easyWebP', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'easy_webp_nonce' ),
            'batchSize' => EASY_WEBP_BATCH_SIZE,
            'i18n'      => [
                'scanning'         => __( 'Scanning images...', 'easy-webp-optimizer' ),
                'processing'       => __( 'Processing', 'easy-webp-optimizer' ),
                'of'               => __( 'of', 'easy-webp-optimizer' ),
                'done'             => __( 'Done!', 'easy-webp-optimizer' ),
                'noImages'         => __( 'No images to process. Everything is already optimized!', 'easy-webp-optimizer' ),
                'error'            => __( 'Error:', 'easy-webp-optimizer' ),
                'confirmStart'     => __( 'Start bulk optimization? The process may take several minutes.', 'easy-webp-optimizer' ),
                'confirmEnable'    => __( "WARNING: Enabling delivery will modify your .htaccess file.\n\nThe plugin will add a rewrite rule inside clearly marked # BEGIN Easy WebP / # END Easy WebP comments.\n\nWe strongly recommend backing up your .htaccess file first.\n\nDo you want to continue?", 'easy-webp-optimizer' ),
                'deliveryOn'       => __( 'WebP delivery ENABLED. Clear your CDN/browser cache to test.', 'easy-webp-optimizer' ),
                'deliveryOff'      => __( 'WebP delivery DISABLED. Original files will be served again.', 'easy-webp-optimizer' ),
                'deliveryError'    => __( 'Error changing delivery status.', 'easy-webp-optimizer' ),
            ],
        ] );

        wp_enqueue_style(
            'easy-webp-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.css',
            [],
            EASY_WEBP_VERSION
        );
    }

    public static function render_admin_page() {
        $engine            = self::get_image_engine();
        $delivery_enabled  = (bool) get_option( 'easy_webp_delivery_enabled', false );
        $htaccess_status   = self::get_htaccess_status();
        ?>
        <div class="wrap easy-webp-wrap">
            <div class="easy-webp-header">
                <h1>
                    <span class="dashicons dashicons-images-alt2"></span>
                    <?php esc_html_e( 'Easy WebP Optimizer', 'easy-webp-optimizer' ); ?>
                </h1>
                <p class="easy-webp-subtitle">
                    <?php esc_html_e( 'Compress, convert to WebP (max 1280px wide), and automatically deliver to compatible browsers. Originals are always preserved.', 'easy-webp-optimizer' ); ?>
                </p>
            </div>

            <div class="easy-webp-card">
                <h2><?php esc_html_e( 'Environment Diagnostic', 'easy-webp-optimizer' ); ?></h2>
                <table class="easy-webp-diag">
                    <tr>
                        <th><?php esc_html_e( 'Image engine', 'easy-webp-optimizer' ); ?></th>
                        <td>
                            <?php if ( $engine ) : ?>
                                <span class="easy-webp-ok">&#10003; <?php echo esc_html( $engine ); ?></span>
                            <?php else : ?>
                                <span class="easy-webp-err">&#10007; <?php esc_html_e( 'No compatible engine found (Imagick or GD)', 'easy-webp-optimizer' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'WebP support', 'easy-webp-optimizer' ); ?></th>
                        <td>
                            <?php if ( self::supports_webp() ) : ?>
                                <span class="easy-webp-ok">&#10003; <?php esc_html_e( 'Available', 'easy-webp-optimizer' ); ?></span>
                            <?php else : ?>
                                <span class="easy-webp-err">&#10007; <?php esc_html_e( 'Not available on this server', 'easy-webp-optimizer' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Server', 'easy-webp-optimizer' ); ?></th>
                        <td><?php echo esc_html( self::detect_server() ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Max width / Quality', 'easy-webp-optimizer' ); ?></th>
                        <td><?php echo esc_html( EASY_WEBP_MAX_WIDTH ); ?>px / <?php echo esc_html( EASY_WEBP_QUALITY ); ?></td>
                    </tr>
                </table>
            </div>

            <?php if ( self::supports_webp() ) : ?>
                <div class="easy-webp-card">
                    <h2>
                        <span class="easy-webp-step">1</span>
                        <?php esc_html_e( 'Generate WebP files', 'easy-webp-optimizer' ); ?>
                    </h2>
                    <p>
                        <?php esc_html_e( 'Creates a .webp file alongside each JPG/PNG in your Media Library, resizing to 1280px width when needed. Original files are preserved.', 'easy-webp-optimizer' ); ?>
                    </p>

                    <button id="easy-webp-start" class="button button-primary button-hero">
                        <?php esc_html_e( 'Start optimization', 'easy-webp-optimizer' ); ?>
                    </button>

                    <div id="easy-webp-progress" style="display:none;">
                        <div class="easy-webp-bar">
                            <div class="easy-webp-bar-fill" style="width:0%"></div>
                        </div>
                        <div class="easy-webp-status"></div>
                        <div class="easy-webp-stats">
                            <div><strong><?php esc_html_e( 'Processed:', 'easy-webp-optimizer' ); ?></strong> <span class="easy-webp-done">0</span></div>
                            <div><strong><?php esc_html_e( 'Converted:', 'easy-webp-optimizer' ); ?></strong> <span class="easy-webp-converted">0</span></div>
                            <div><strong><?php esc_html_e( 'Skipped:', 'easy-webp-optimizer' ); ?></strong> <span class="easy-webp-skipped">0</span></div>
                            <div><strong><?php esc_html_e( 'Errors:', 'easy-webp-optimizer' ); ?></strong> <span class="easy-webp-errors">0</span></div>
                            <div><strong><?php esc_html_e( 'Saved:', 'easy-webp-optimizer' ); ?></strong> <span class="easy-webp-saved">0 KB</span></div>
                        </div>
                        <div class="easy-webp-log"></div>
                    </div>
                </div>

                <div class="easy-webp-card">
                    <h2>
                        <span class="easy-webp-step">2</span>
                        <?php esc_html_e( 'Automatic delivery to browsers', 'easy-webp-optimizer' ); ?>
                    </h2>
                    <p>
                        <?php esc_html_e( 'When enabled, the plugin delivers .webp files to browsers that accept the format, and original .jpg/.png to those that don\'t. Uses two parallel layers:', 'easy-webp-optimizer' ); ?>
                    </p>
                    <ul class="easy-webp-bullets">
                        <li><strong>.htaccess:</strong> <?php esc_html_e( 'server-level rewrite (fastest)', 'easy-webp-optimizer' ); ?> &mdash; <em><?php echo esc_html( $htaccess_status ); ?></em></li>
                        <li><strong>PHP filter:</strong> <?php esc_html_e( 'replaces <img> tags with <picture> elements (robust fallback)', 'easy-webp-optimizer' ); ?></li>
                    </ul>

                    <div class="easy-webp-warning">
                        <strong>&#9888; <?php esc_html_e( 'Warning:', 'easy-webp-optimizer' ); ?></strong>
                        <?php esc_html_e( 'Enabling this option will modify your .htaccess file (Apache/LiteSpeed servers). The rule is added between clearly-marked # BEGIN Easy WebP / # END Easy WebP comments and is removed automatically when you disable the toggle or deactivate the plugin. Please back up your .htaccess file before enabling.', 'easy-webp-optimizer' ); ?>
                    </div>

                    <div class="easy-webp-toggle-row">
                        <label class="easy-webp-switch">
                            <input type="checkbox" id="easy-webp-delivery-toggle" <?php checked( $delivery_enabled ); ?>>
                            <span class="easy-webp-slider"></span>
                        </label>
                        <span class="easy-webp-toggle-label">
                            <?php echo $delivery_enabled
                                ? '<strong style="color:#2e7d32">' . esc_html__( 'Delivery ENABLED', 'easy-webp-optimizer' ) . '</strong>'
                                : '<strong style="color:#888">' . esc_html__( 'Delivery DISABLED', 'easy-webp-optimizer' ) . '</strong>'; ?>
                        </span>
                    </div>

                    <div id="easy-webp-delivery-msg" class="easy-webp-msg" style="display:none;"></div>

                    <div class="easy-webp-tip">
                        <strong>&#128161; <?php esc_html_e( 'Tip:', 'easy-webp-optimizer' ); ?></strong>
                        <?php esc_html_e( 'After enabling, clear your CDN cache (Cloudflare, etc.) and browser cache. Test in an incognito window: F12 → Network → filter by "Img" → the Type column should show "webp".', 'easy-webp-optimizer' ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="easy-webp-footer">
                <p>Easy WebP Optimizer v<?php echo esc_html( EASY_WEBP_VERSION ); ?></p>
            </div>
        </div>
        <?php
    }

    private static function get_image_engine() {
        if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
            return 'Imagick';
        }
        if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
            return 'GD';
        }
        return false;
    }

    private static function supports_webp() {
        if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
            $formats = Imagick::queryFormats( 'WEBP' );
            if ( ! empty( $formats ) ) {
                return true;
            }
        }
        if ( function_exists( 'imagewebp' ) ) {
            return true;
        }
        return false;
    }

    private static function detect_server() {
        $sig = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown';
        if ( stripos( $sig, 'apache' ) !== false )    return 'Apache (.htaccess supported)';
        if ( stripos( $sig, 'nginx' )  !== false )    return 'Nginx (use PHP filter only)';
        if ( stripos( $sig, 'litespeed' ) !== false ) return 'LiteSpeed (.htaccess supported)';
        return $sig;
    }

    private static function get_htaccess_status() {
        $htaccess = ABSPATH . '.htaccess';
        if ( ! file_exists( $htaccess ) ) return __( 'file not found', 'easy-webp-optimizer' );
        if ( ! is_writable( $htaccess ) ) return __( 'not writable', 'easy-webp-optimizer' );

        $content = file_get_contents( $htaccess );
        if ( strpos( $content, '# BEGIN Easy WebP' ) !== false ) {
            return __( 'rule installed &#10003;', 'easy-webp-optimizer' );
        }
        return __( 'ready to receive rule', 'easy-webp-optimizer' );
    }

    public static function ajax_get_queue() {
        check_ajax_referer( 'easy_webp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'easy-webp-optimizer' ) ] );
        }

        $query = new WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => [ 'image/jpeg', 'image/png' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        wp_send_json_success( [
            'ids'   => $query->posts,
            'total' => count( $query->posts ),
        ] );
    }

    public static function ajax_process_batch() {
        check_ajax_referer( 'easy_webp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'easy-webp-optimizer' ) ] );
        }

        $ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
        $results = [];

        foreach ( $ids as $id ) {
            $results[] = self::process_attachment( $id );
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    public static function ajax_toggle_delivery() {
        check_ajax_referer( 'easy_webp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'easy-webp-optimizer' ) ] );
        }

        $enable = isset( $_POST['enable'] ) && $_POST['enable'] === '1';

        if ( $enable ) {
            update_option( 'easy_webp_delivery_enabled', true );
            $htaccess_result = self::add_htaccess_rules();
            wp_send_json_success( [
                'enabled'  => true,
                'htaccess' => $htaccess_result,
            ] );
        } else {
            update_option( 'easy_webp_delivery_enabled', false );
            self::remove_htaccess_rules();
            wp_send_json_success( [
                'enabled'  => false,
                'htaccess' => __( 'removed', 'easy-webp-optimizer' ),
            ] );
        }
    }

    public static function process_attachment( $attachment_id ) {
        $file  = get_attached_file( $attachment_id );
        $title = get_the_title( $attachment_id );

        if ( ! $file || ! file_exists( $file ) ) {
            return [ 'id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => __( 'File not found.', 'easy-webp-optimizer' ), 'saved' => 0 ];
        }

        $info = pathinfo( $file );
        $ext  = strtolower( $info['extension'] ?? '' );

        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
            return [ 'id' => $attachment_id, 'title' => $title, 'status' => 'skipped', 'message' => __( 'Unsupported format.', 'easy-webp-optimizer' ), 'saved' => 0 ];
        }

        $webp_file = $info['dirname'] . '/' . $info['filename'] . '.webp';

        if ( file_exists( $webp_file ) ) {
            return [ 'id' => $attachment_id, 'title' => $title, 'status' => 'skipped', 'message' => __( 'WebP already exists.', 'easy-webp-optimizer' ), 'saved' => 0 ];
        }

        $original_size = filesize( $file );

        try {
            $result = self::convert_to_webp( $file, $webp_file );
            if ( ! $result ) {
                return [ 'id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => __( 'Conversion failed.', 'easy-webp-optimizer' ), 'saved' => 0 ];
            }

            $webp_size = filesize( $webp_file );
            $saved     = max( 0, $original_size - $webp_size );

            update_post_meta( $attachment_id, '_easy_webp_generated', time() );
            update_post_meta( $attachment_id, '_easy_webp_original_size', $original_size );
            update_post_meta( $attachment_id, '_easy_webp_size', $webp_size );

            return [
                'id'      => $attachment_id,
                'title'   => $title,
                'status'  => 'converted',
                'message' => sprintf( '%s &rarr; %s (-%s%%)',
                    size_format( $original_size ),
                    size_format( $webp_size ),
                    $original_size > 0 ? round( ( $saved / $original_size ) * 100 ) : 0
                ),
                'saved'   => $saved,
            ];

        } catch ( Exception $e ) {
            return [ 'id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => $e->getMessage(), 'saved' => 0 ];
        }
    }

    private static function convert_to_webp( $source_file, $dest_file ) {
        if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
            return self::convert_with_imagick( $source_file, $dest_file );
        }
        if ( function_exists( 'imagewebp' ) ) {
            return self::convert_with_gd( $source_file, $dest_file );
        }
        return false;
    }

    private static function convert_with_imagick( $source_file, $dest_file ) {
        $img = new Imagick( $source_file );

        $orientation = $img->getImageOrientation();
        $img->stripImage();
        if ( $orientation ) {
            $img->setImageOrientation( $orientation );
            switch ( $orientation ) {
                case Imagick::ORIENTATION_BOTTOMRIGHT: $img->rotateImage( 'transparent', 180 ); break;
                case Imagick::ORIENTATION_RIGHTTOP:    $img->rotateImage( 'transparent', 90 ); break;
                case Imagick::ORIENTATION_LEFTBOTTOM:  $img->rotateImage( 'transparent', -90 ); break;
            }
            $img->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
        }

        $width = $img->getImageWidth();
        if ( $width > EASY_WEBP_MAX_WIDTH ) {
            $img->resizeImage( EASY_WEBP_MAX_WIDTH, 0, Imagick::FILTER_LANCZOS, 1 );
        }

        $img->setImageFormat( 'webp' );
        $img->setImageCompressionQuality( EASY_WEBP_QUALITY );
        $img->setOption( 'webp:method', '6' );
        $img->setOption( 'webp:low-memory', 'true' );

        $success = $img->writeImage( $dest_file );
        $img->clear();
        $img->destroy();

        return $success;
    }

    private static function convert_with_gd( $source_file, $dest_file ) {
        $info = getimagesize( $source_file );
        if ( ! $info ) return false;

        $mime = $info['mime'];

        switch ( $mime ) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg( $source_file );
                break;
            case 'image/png':
                $img = imagecreatefrompng( $source_file );
                imagepalettetotruecolor( $img );
                imagealphablending( $img, true );
                imagesavealpha( $img, true );
                break;
            default:
                return false;
        }

        if ( ! $img ) return false;

        $orig_w = imagesx( $img );
        $orig_h = imagesy( $img );

        if ( $orig_w > EASY_WEBP_MAX_WIDTH ) {
            $new_w = EASY_WEBP_MAX_WIDTH;
            $new_h = (int) round( ( $orig_h / $orig_w ) * $new_w );
            $resized = imagecreatetruecolor( $new_w, $new_h );

            if ( $mime === 'image/png' ) {
                imagealphablending( $resized, false );
                imagesavealpha( $resized, true );
                $transparent = imagecolorallocatealpha( $resized, 255, 255, 255, 127 );
                imagefilledrectangle( $resized, 0, 0, $new_w, $new_h, $transparent );
            }

            imagecopyresampled( $resized, $img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h );
            imagedestroy( $img );
            $img = $resized;
        }

        $success = imagewebp( $img, $dest_file, EASY_WEBP_QUALITY );
        imagedestroy( $img );

        return $success;
    }

    private static function add_htaccess_rules() {
        $htaccess = ABSPATH . '.htaccess';
        if ( ! file_exists( $htaccess ) || ! is_writable( $htaccess ) ) {
            return __( 'file not writable (configure manually)', 'easy-webp-optimizer' );
        }

        $content = file_get_contents( $htaccess );
        $content = preg_replace( '/# BEGIN Easy WebP.*?# END Easy WebP\s*/s', '', $content );

        $rule  = "# BEGIN Easy WebP\n";
        $rule .= "<IfModule mod_rewrite.c>\n";
        $rule .= "    RewriteEngine On\n";
        $rule .= "    RewriteCond %{HTTP_ACCEPT} image/webp\n";
        $rule .= "    RewriteCond %{REQUEST_FILENAME} (.+)\\.(jpe?g|png)$\n";
        $rule .= "    RewriteCond %1.webp -f\n";
        $rule .= "    RewriteRule (.+)\\.(jpe?g|png)$ $1.webp [T=image/webp,E=accept:1,L]\n";
        $rule .= "</IfModule>\n";
        $rule .= "<IfModule mod_headers.c>\n";
        $rule .= "    Header append Vary Accept env=REDIRECT_accept\n";
        $rule .= "</IfModule>\n";
        $rule .= "AddType image/webp .webp\n";
        $rule .= "# END Easy WebP\n\n";

        $new_content = $rule . $content;
        $written = file_put_contents( $htaccess, $new_content );
        return $written ? __( 'installed &#10003;', 'easy-webp-optimizer' ) : __( 'write failed', 'easy-webp-optimizer' );
    }

    private static function remove_htaccess_rules() {
        $htaccess = ABSPATH . '.htaccess';
        if ( ! file_exists( $htaccess ) || ! is_writable( $htaccess ) ) return;

        $content = file_get_contents( $htaccess );
        $new = preg_replace( '/# BEGIN Easy WebP.*?# END Easy WebP\s*/s', '', $content );
        file_put_contents( $htaccess, $new );
    }

    public static function filter_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
        if ( empty( $html ) ) return $html;
        return self::wrap_img_with_picture( $html );
    }

    public static function filter_content_images( $content ) {
        if ( is_admin() || empty( $content ) ) return $content;
        if ( strpos( $content, '<img' ) === false ) return $content;

        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            function ( $m ) { return self::wrap_img_with_picture( $m[0] ); },
            $content
        );
    }

    private static function wrap_img_with_picture( $html ) {
        if ( strpos( $html, '<picture' ) !== false ) return $html;

        if ( ! preg_match( '/<img[^>]+src=["\']([^"\']+\.(jpe?g|png))["\'][^>]*>/i', $html, $matches ) ) {
            return $html;
        }

        $img_tag = $matches[0];
        $src_url = $matches[1];

        $webp_url  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src_url );
        $webp_path = self::url_to_path( $webp_url );

        if ( ! $webp_path || ! file_exists( $webp_path ) ) {
            return $html;
        }

        $srcset_attr = '';
        if ( preg_match( '/srcset=["\']([^"\']+)["\']/i', $img_tag, $srcset_match ) ) {
            $webp_srcset = preg_replace_callback(
                '/(\S+)\.(jpe?g|png)(\s+\S+)?/i',
                function ( $m ) {
                    $webp = $m[1] . '.webp';
                    $webp_p = self::url_to_path( $webp );
                    if ( $webp_p && file_exists( $webp_p ) ) {
                        return $webp . ( isset( $m[3] ) ? $m[3] : '' );
                    }
                    return $m[0];
                },
                $srcset_match[1]
            );
            $srcset_attr = ' srcset="' . esc_attr( $webp_srcset ) . '"';
        } else {
            $srcset_attr = ' srcset="' . esc_attr( $webp_url ) . '"';
        }

        return '<picture><source type="image/webp"' . $srcset_attr . '>' . $img_tag . '</picture>';
    }

    private static function url_to_path( $url ) {
        $uploads = wp_get_upload_dir();
        if ( strpos( $url, $uploads['baseurl'] ) === 0 ) {
            return $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
        }
        $site_url = site_url();
        if ( strpos( $url, $site_url ) === 0 ) {
            return ABSPATH . ltrim( substr( $url, strlen( $site_url ) ), '/' );
        }
        return false;
    }

    public static function on_deactivation() {
        self::remove_htaccess_rules();
        update_option( 'easy_webp_delivery_enabled', false );
    }
}

Easy_WebP_Optimizer::init();
