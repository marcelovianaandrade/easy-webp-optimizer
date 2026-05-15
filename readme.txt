=== Easy WebP Optimizer ===
Contributors: marceloandrade
Tags: webp, image optimization, image compression, page speed, bulk optimization
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk convert JPG/PNG to WebP, resize proportionally, and serve WebP automatically to compatible browsers. No API key, no paid plans.

== Description ==

**Easy WebP Optimizer** is a lightweight, no-nonsense WordPress plugin that converts your existing JPEG and PNG images to the modern WebP format, resizes them proportionally to a sensible web-friendly width, and automatically serves WebP files to browsers that support them — all without external APIs, subscriptions, or usage limits.

Built for site owners and developers who want a simple, transparent tool that just works.

= Key Features =

* **Bulk conversion** of your entire Media Library to WebP with one click
* **Proportional resizing** to a maximum width of 1280px (mobile-first, customizable via constant)
* **Original files preserved** — `.webp` is generated alongside the `.jpg`/`.png`, never replacing them
* **Idempotent processing** — already-converted images are skipped on subsequent runs
* **Two-layer automatic delivery**:
  * **.htaccess rewrite rule** (Apache/LiteSpeed) for server-level WebP delivery
  * **PHP `<picture>` filter** as fallback for Nginx and edge cases
* **Real-time progress bar** with detailed log and savings statistics
* **No API key required** — uses native PHP Imagick or GD libraries on your server
* **No paid plans, no usage limits, no "pro" upsells**
* **Clean uninstall** — removes all options, post meta, and .htaccess rules

= How It Works =

1. **Step 1 — Generate WebP files**: Click the "Start optimization" button. The plugin scans your Media Library, generates a `.webp` copy of each JPG/PNG, resizes it if wider than 1280px, and reports savings in real time.
2. **Step 2 — Enable automatic delivery**: Toggle the delivery switch. The plugin will:
   * Write a rewrite rule into your `.htaccess` so the server delivers `.webp` to browsers that accept it
   * Activate a PHP filter that wraps `<img>` tags in `<picture>` elements with WebP sources
3. **Reverse anytime**: Turning off the toggle removes the .htaccess rule. Deactivating the plugin cleans up automatically.

= Why Use Easy WebP Optimizer? =

Most WebP plugins on the market are excellent — but many require API keys, monthly quotas, account sign-ups, or paid plans for bulk operations. Easy WebP Optimizer is a deliberately minimal alternative for users who want:

* A free, fully-functional tool with no strings attached
* Local processing (your images never leave your server)
* Transparent, auditable code under GPLv2
* A clean uninstall path with no leftover settings or files

= Server Requirements =

* PHP 7.4 or higher
* One of:
  * **Imagick** extension with WebP support (preferred — uses the Lanczos filter and respects EXIF orientation)
  * **GD** extension with the `imagewebp()` function available

The plugin's diagnostic panel will tell you exactly what's available on your server.

= Important: .htaccess Modification =

When you enable automatic delivery, this plugin **will modify your site's `.htaccess` file** to add a server rewrite rule. The plugin:

* Adds a clearly-marked block between `# BEGIN Easy WebP` and `# END Easy WebP` comments
* Inserts the rule at the top of the file (does not touch existing WordPress rules)
* Requires explicit confirmation via a JavaScript dialog before making any change
* Removes the block cleanly when delivery is disabled or the plugin is deactivated

**Please back up your `.htaccess` file before enabling delivery.** If you are not comfortable with automatic `.htaccess` editing, you can leave the delivery toggle off and rely only on the PHP `<picture>` filter as a manual alternative.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Go to **Plugins → Add New**
3. Search for "Easy WebP Optimizer"
4. Click **Install Now** and then **Activate**
5. Go to **Media → WebP Optimizer** to configure

= Manual Installation =

1. Download the plugin `.zip` file
2. Upload the `easy-webp-optimizer` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **Media → WebP Optimizer**

= First Use =

1. Open **Media → WebP Optimizer**
2. Review the **Environment Diagnostic** panel
3. **Back up your `.htaccess` file** if you plan to enable automatic delivery
4. Click **Start optimization** to generate WebP files for your existing library
5. (Optional) Toggle **Enable automatic delivery** to serve WebP to compatible browsers
6. Clear your CDN/cache and test in an incognito browser window

== Frequently Asked Questions ==

= Will this plugin delete or replace my original images? =

No. The plugin **never** modifies or deletes your original files. It generates a new `.webp` file alongside each `.jpg`/`.png`. Disabling delivery or uninstalling the plugin returns everything to its original state.

= Does it modify my .htaccess file? =

Yes, **only when you explicitly enable the delivery toggle and confirm the action via the dialog**. The rule is added between clearly-marked `# BEGIN Easy WebP` / `# END Easy WebP` comments, separate from WordPress's own rules. It's removed automatically when you disable delivery or deactivate the plugin. We strongly recommend backing up your `.htaccess` before enabling delivery.

= What if I'm on Nginx? =

The plugin detects Nginx and skips the `.htaccess` modification. The PHP `<picture>` filter handles delivery instead. For best performance on Nginx, you can also add a similar rewrite rule to your `nginx.conf` manually.

= Does it work with Cloudflare? =

Yes. After enabling delivery, purge your Cloudflare cache (**Caching → Configuration → Purge Everything**). If you have Cloudflare Polish (Pro+) with WebP enabled, you don't need this plugin's delivery layer — but the bulk conversion is still useful for storage savings.

= Does it work with Elementor / WooCommerce / page builders? =

Yes. The PHP `<picture>` filter operates on the final HTML output via `the_content` and `wp_get_attachment_image` filters, which covers most page builders and product galleries.

= What image formats does it support? =

The plugin converts **JPEG (.jpg, .jpeg)** and **PNG (.png)** files to WebP. GIF and SVG are not converted.

= What quality setting is used? =

WebP quality is set to **82** by default (the commonly-recommended balance between file size and visual quality). You can change this by editing the `EASY_WEBP_QUALITY` constant in the main plugin file.

= Can I change the maximum width (1280px)? =

Yes. Edit the `EASY_WEBP_MAX_WIDTH` constant in the main plugin file. The default of 1280px is mobile-first and works well for most modern websites.

= Will I see savings immediately? =

After enabling delivery, you need to clear all caches (CDN, plugin cache, browser cache) before WebP files are served. Test in an incognito window. Visitors with cached versions of your pages will continue to see the original images until their cache expires.

= How do I revert all changes? =

* Disable the delivery toggle — removes the `.htaccess` rule
* Deactivate the plugin — also removes the rule automatically
* Delete WebP files (optional): `find wp-content/uploads -name "*.webp" -delete` via SSH

= Does the plugin send data to external servers? =

No. All image processing happens locally on your server using PHP's Imagick or GD libraries. The plugin makes no external API calls and does not "phone home."

= Why does the plugin require user confirmation before editing .htaccess? =

Transparency and user control. Editing server configuration files is a sensitive operation, and you should always know — and explicitly agree — when a plugin makes such changes.

== Screenshots ==

1. Main dashboard with environment diagnostic and two-step workflow
2. Bulk conversion in progress with real-time progress bar and savings counter
3. Delivery toggle with .htaccess status indicator and warning message
4. Detailed conversion log with per-image savings percentages

== Changelog ==

= 1.1.0 =
* Added: Automatic WebP delivery via .htaccess rewrite rule (with user opt-in toggle and confirmation dialog)
* Added: PHP `<picture>` filter as delivery fallback for Nginx and edge cases
* Added: Environment diagnostic panel (Imagick/GD detection, server type, WebP support)
* Added: Confirmation prompt before `.htaccess` modification
* Added: Clean uninstall handler (removes options, post meta, and .htaccess rules)
* Improved: Cleaner admin interface with two-step workflow
* Improved: Detailed per-image conversion log with size savings

= 1.0.0 =
* Initial release
* Bulk conversion of Media Library JPG/PNG to WebP
* Proportional resizing to maximum 1280px width
* Imagick (preferred) and GD fallback support
* AJAX-based batch processing to avoid timeouts
* Real-time progress bar and statistics
* Idempotent processing (skips already-converted images)
* Preservation of original files

== Upgrade Notice ==

= 1.1.0 =
Adds automatic WebP delivery to compatible browsers via .htaccess rewrite and PHP filter. Backup your .htaccess before enabling delivery toggle.

= 1.0.0 =
Initial release.
