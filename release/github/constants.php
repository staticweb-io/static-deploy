<?php
/**
 * This file can be added to in order to force
 * rector to use certain values for constants.
 * This is mainly used in statements like
 * if ( FEATURE_ENABLED ) { ... }
 * in order to remove code entirely at build-time if
 * a feature is disabled.
 */

 // See https://github.com/WordPress/WordPress-Coding-Standards/issues/2374
 define( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS', false );

// Disable the use of functions that wordpress.org requires
// but we would not use when we aren't forced to.
// e.g. using wp_rand instead of mt_rand in a context
// where a CSPRNG adds no value.
// This is just to enable useless functions, not WP
// functions that do serve some purpose.
define( 'STATIC_DEPLOY_WP_ORG_MODE', false );
