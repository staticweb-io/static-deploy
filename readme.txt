=== StaticWeb Deploy ===
Contributors: staticwebio
Tags: performance, s3, security, speed, static site generator
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 9.9.3
Requires PHP: 8.2
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

= 9.9.3 =
Fix multiline options in admin did not save properly.
Fix an issue where the crawler could pass along a directory path
instead of passing the index.html filename for a crawled page.

= 9.9.0 =
Enable Direct Deploy Post by default in new installs.
Show hashes and deployed at times on deploy cache pages.
Numerous fixes.

= 9.8.0 =
Require PHP 8.2 or later.

== Upgrade Notice ==

= 9.9.3 =
Fix multiline options in admin did not save properly.
Fix an issue where the crawler could pass along a directory path
instead of passing the index.html filename for a crawled page.

= 9.9.0 =
Enable Direct Deploy Post by default in new installs.
Show hashes and deployed at times on deploy cache pages.
Numerous fixes.

= 9.8.0 =
Require PHP 8.2 or later.
