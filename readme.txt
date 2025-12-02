=== StaticWeb Deploy ===
Contributors: staticwebio
Tags: performance, s3, security, speed, static site generator
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 9.7.0
Requires PHP: 8.1
License: Unlicense
License URI: https://github.com/staticweb-io/static-deploy/blob/develop/LICENSE

Generate static sites for deployment as files or S3-compatible storage.

== Description ==

Turns your WordPress site into a secure, lightning-fast static website.

= Features =
* Deploy website directly to S3 and CloudFront
* Export website to a directory that can be served by your web server
* Full WP-CLI support

= Speed =

Static websites are many times faster than a normal WordPress server.
A simple S3 + CloudFront setup can easily serve millions of users.
The greatly increased speed generally improves SEO performance.

= Security =

Making the public version of your website a static website allows you to restrict access to your WordPress server.
This dramatically reduces the attack surface of your website and makes expensive WAF services unnecessary.

== Changelog ==

Full changelog available at https://github.com/staticweb-io/static-deploy/blob/develop/CHANGELOG.md

= 9.7.0 =
Don't load plugin code unless in the CLI or an admin.
This should provide a small speed boost for non-admin
users.

= 9.6.0 =
Prune unused AWS dependencies.

= 9.5.1 =
Initial submission to WordPress.org.

== Upgrade Notice ==

= 9.7.0 =
Minor speed improvements.

= 9.6.0 =
Prune unused AWS dependencies.

= 9.5.1 =
Initial submission to WordPress.org.
