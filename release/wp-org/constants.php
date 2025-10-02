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

// This is required to pass Plugin Check Plugin
define( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS', true );

// Force the use of functions that wordpress.org requires
// but we would not use when we aren't forced to.
// e.g. using wp_rand instead of mt_rand in a context
// where a CSPRNG adds no value.
// This is just to force useless behaviors, not
// for anything that could be useful in some context.
define( 'STATIC_DEPLOY_WP_ORG_MODE', true );
