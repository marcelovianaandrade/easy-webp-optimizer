# Changelog

All notable changes to **Easy WebP Optimizer** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-05-15

### Added
- Automatic WebP delivery via `.htaccess` rewrite rule (with user opt-in toggle and confirmation dialog)
- PHP `<picture>` filter as delivery fallback for Nginx and edge cases
- Environment diagnostic panel (Imagick/GD detection, server type, WebP support indicator)
- Confirmation prompt before any `.htaccess` modification
- Clean uninstall handler (`uninstall.php`) — removes options, post meta, and `.htaccess` rules

### Changed
- Improved admin interface with two-step workflow (generate → enable delivery)
- More detailed per-image conversion log with size savings percentages

### Fixed
- Edge cases when WebP file already exists are now properly skipped (idempotent behavior)

---

## [1.0.0] - 2026-04-30

### Added
- Initial release
- Bulk conversion of Media Library JPG/PNG to WebP
- Proportional resizing to maximum 1280px width
- Imagick (preferred) and GD fallback support
- AJAX-based batch processing to avoid PHP timeouts
- Real-time progress bar and conversion statistics
- Idempotent processing (skips already-converted images)
- Preservation of original files

---

[1.1.0]: https://github.com/marcelovianaandrade/easy-webp-optimizer/releases/tag/v1.1.0
[1.0.0]: https://github.com/marcelovianaandrade/easy-webp-optimizer/releases/tag/v1.0.0
