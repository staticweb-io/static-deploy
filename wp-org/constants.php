<?php
/**
 * This file can be added to in order to force
 * rector to use certain values for constants.
 * This is mainly used in statements like
 * if ( FEATURE_ENABLED ) { ... }
 * in order to remove code entirely at build-time if
 * a feature is disabled.
 *
 * These constants are used for the WordPress.org
 * release of the plugin in order to remove code
 * that is not allowed in that repository.
 */

// Require use of wp_* functions and WP_Filesystem
// rather than direct file access.
define( 'STATIC_DEPLOY_DIRECT_FILE_ACCESS', false );
