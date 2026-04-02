## Unreleased

- Fix attempting to send deployment emails when no email address is set. 
- Fix typo in deployment email log message.
- Remove warning when post-processing a path with no contents. This was
  triggering for paths with redirect_to targets, which are expected to
  have no contents.
- Fix checkboxes in admin always being unchecked in WP.org build.
- Enable Direct Deploy Post job by default. This has proven itself in
  production. It usually takes only a few seconds to run, but gets post
  updates live much faster compared to waiting for an entire site
  deploy.
- Fix location in crawler redirect debug message.
- Add more debug messages to crawler.

## 9.8.0 (2026-01-07)

- Require PHP 8.2 or later.

## 9.7.1 (2025-12-23)

- Update dependencies to fix CVE-2025-14761.
  This CVE is unlikely to affect any users of the plugin
  because the affected functionality is not used,
  but it can still get flagged by automated checks.

## 9.7.0 (2025-12-02)

- Don't load plugin code unless in the CLI or an admin.
  This should provide a small speed boost for non-admin
  users.
- Internal code changes to satisfy wordpress.org policies.
  This should not affect behavior.

## 9.6.0 (2025-11-05)

- (breaking) Change plugin name and slug from "Static Deploy"
  / "static-deploy" to "StaticWeb Deploy" / "staticweb-deploy".
  The wordpress.org plugin repository would not allow submission
  under the name "Static Deploy".
- Remove unused services from AWS SDK dependency. This
  reduces the plugin zip size from 6.5MB to 1.3MB.

## 9.5.1 (2025-11-03)

- Remove extra <script> tag from admin bar enqueue

## 9.5.0 (2025-10-28)

- (breaking) Remove setting of max_execution_time.
  This isn't allowed to be set on all hosts, and
  was set far too broadly. If your site is too large
  to crawl within your normal max_execution_time,
  it's recommended to use a cron job that calls
  `wp static-deploy process-queue`. This has an
  unlimited max_execution_time by default.
- Remove "Generated Static Site" page in admin.
  This provided basically the same data as the
  "Crawled Files" page with less detail.
- Improve consistency and screen reader text on
  admin cache pages.
- Add nonces for GET requests to cache pages in WP.org
  build. These don't serve any security purpose, but
  are required by WP.org.
- Remove memcached stats from the diagnostics and CLI.
  This has been moved to a new plugin at
  https://github.com/staticweb-io/snapcache

## 9.4.1 (2025-10-20)

- Fix broken links on settings pages when the server host
  is on a non-standard port.
- Sanitize all query args on admin pages.
- Use wp_add_inline_style on caches-page.php.
- Use wp_add_inline_script for admin bar and run-page.php.

## 9.4.0 (2025-10-02)

- Show job duration with more precision on the jobs
  page. E.g., "1 minute 20 seconds" instead of
  "1 minute".
- Fix a rare Run page error that happened when the
  views/ folder was symlinked to a different location.
- Add s3_awsEndpoint option for overriding the AWS
  endpoint.
- Fix an error when the S3 deployer could construct
  a blank key.
- Fix that crawl always reported 0 cached files even when
  there were cached files.
- Normalize the sitemap path when robots.txt reports a
  sitemap path with extra slashes.
- Check that crawled files are successfully written.
- Detect redirect URLs created by the "Redirection" and
  "Redirect Redirection" plugins.
- Fix a possible error when checking the correctness of
  a request to S3.
- Fix that redirects were being treated as if they were
  the page they redirect to. Now they will be treated
  and deployed as actual redirects.
- No longer grabs the body of 404 responses. These are
  normally removed from the crawl.
- Fix that the s3 prefix would get dropped if the path
  being deployed ended in a slash.
- Apply "Paths to Ignore" patterns to all paths.
  Previously, it only applied to files and URLs
  discovered while crawling.
- Rename the deploy_cache table to deployed_files
  and restructure it.
  - Make path_hash a persistent generated column.
  - Rename file_hash to data_hash to reflect that it
    can include metadata.
  - Add deployed_at column and an index on it.
- Batch queries to the deployed_files table for better
  performance.
- Add "extra_detected_files" filter that allows plugins to
  add additional files when the "detect" step is run.
- Fix text domain for "Settings" translation.
- Use WP_Filesystem methods by default instead of direct
  file access. These provide more control and can work
  over FTP and SSH. The previous behavior of direct file
  access can be enabled by setting
  `define('STATIC_DEPLOY_DIRECT_FILE_ACCESS', true);` in
  `wp-config.php`.
  - This was changed because direct file access is prohibited
    by the wordpress.org plugin guidelines.
- Escape exception messages when
  `STATIC_DEPLOY_ESCAPE_EXCEPTIONS` is true.
- Fix a security issue where the nonce was not verified
  for some actions until after the action had been performed.
- Cache option values. BLOB values are not cached.
- Fix an issue where uninstall failed.

## 9.3.2 (2025-07-29)

- Fix wrong calculation for Content-MD5 in S3 deployer.

## 9.3.1 (2025-07-29)

- Fix "direct-deploy --no-detect" gave the message
  "Unknown option: detect".

## 9.3.0 (2025-07-29)

- Remove "debugLogging" option. Instead, debug logs are enabled
  when WP_DEBUG is true or when the "--debug" flag is passed to
  the WP CLI. This improves performance when debug logs are
  disabled by allowing us to skip the debug calls entirely.
- Remove unused duration column from jobs table.
- Fix that the "Process Queue Immediately" option could not
  have the "Using WordPress CLI" value set.
- Don't register hooks in the non-admin part of the
  site. This avoids some unnecessary load.
- Add a "--path-hash-prefix=&lt;prefix&gt;" option
  to the crawl and direct_deploy CLI commands.
  This allows restricting processing to a stable
  subset of paths.
  Intended for dev use and benchmarking.
- Add a "--no-detect" option to the direct_deploy
  CLI command that skips the detect step.
- Add a status_updated_at column to the jobs table.
- Remove unused microtime_diff functions.
- Show "x time ago" for job creation time
  and status update time on jobs page.
- Show job duration on jobs page.
- Fix error with "Delete All Jobs from Queue"
  button submission.
- Remove unused functions countURLs and detectURLs from
  URLDetector
- Fix that very long wpdb prefixes could result in invalid
  MySQL lock names.
- Allow both hyphens and underscores in CLI command names.
- Rename "process_queue" command to "jobs process". The
  "process_queue" command still works as an alias.
- Fix that a job could block a job of another database on
  the same server.
- Add jobs run from the CLI to the jobs table after
  completion.

## 9.2.1 (2025-07-18)

- Fix an issue where 404s encountered during crawling
  did not cause removal of the URL from the database.

## 9.2.0 (2025-07-18)

- (breaking) Use PHP 8.1+ features. Although the
  plugin previously said that it required PHP 8.1, it
  did run successfully on PHP 8.0. It will no longer
  work on PHP 8.0 at all. Note that PHP 8.0 has been
  EOL since 2023-11-26.
- (breaking) Fix that "--reveal-sensitive-values" did nothing
  with options CLI commands and that passwords were always
  shown. Password values will now be hidden by default.
- Prevent an error that could occur if no AWS
  credentials were set when the admin bar checked
  for invalidation status.
- Add "addons enable &lt;addon&gt;" and "addons disable &lt;addon&gt;"
  commands.
- Print resulting state when using the "addons toggle &lt;addon&gt;"
  command.
- Add a deployer that writes files to a local directory.
  This is useful for cases where you are serving files
  directly from your web server or when you want full
  control over the upload process.
- Fix an error in the "options list" command.
- Fix an error that occurred when running "direct_deploy"
  with no deployers enabled.
- Remove unused function Controller->resetDefaultSettings.
- Show addon options in the "options list" command.
- Show a message if an "options" subcommand is invalid
  instead of doing nothing.
- Support addon options in "options get" and "options set"
- Specify a minimum value of one for the s3_concurrency
  option.
- Print current option value when using "options set".
  This makes it obvious when a validation rule prohibits
  the value being set and causes warning messages to be
  shown.
- Support setting options with blob_values via "options set".
  This includes the hostsToRewrite and pathsToIgnore options.
  Previously, CLI commands had no effect on these options.
- Support blob_value options in "options get". Previously,
  these always showed a meaningless "1" for the value.
- Show line count of BLOB values in "options list" instead
  of always showing "1".
- Show total deployed files after S3 deployer finishes.

## 9.1.0 (2025-07-17)

- Add admin bar that displays job status and links.
- Fix an error when applying an option's default blob_value.
- Fix a missing import on the logs-page that was breaking the
  "Delete Logs" button.
- Add an "object" option type.
  This is used for the new "adminBarMenuItems" option.
- Add a new "adminBarMenuItems" option.
  This is a JSON object that defines the menu items to display in the
  admin bar.
- Add a new options page rendering system.
- Fix an optimization check where if the post-processor doesn't
  need to make any changes to a file, the original file content
  would be hashed again unnecessarily.
- Change awsRegion default to blank from "us-east-1". This allows
  the region to be inherited from the environment variables.
- Add a dropdown selector for the AWS region.

## Static Deploy 9.0.1 (2025-07-16)

- Fix max_execution_time being set to 30 instead of 0.
- Fix Diagnostics page showing 0 max_execution_time as too low
  rather than unlimited.

## Static Deploy 9.0.0 (2025-07-16)

This is the first release under the name Static Deploy.
The main changes include:

- Crawling and processing static assets (such as images and CSS) is
  now much faster.
- `import_wp2static_options` command added for migrating options from WP2Static.
- A `direct_deploy` command was added that does detection, crawling,
  processing, and deployment in parallel. It avoids writing files to
  disk, which makes it faster than the `full_workflow` command.
- `direct_deploy` can take a post ID as an argument for quickly
  deploying a single page.
- New settings were added for enqueuing "Direct Deploy" and
  "Direct Deploy Post" jobs when a post is updated.
- URLs can now be detected while crawling the website. This is
  helpful if you have addons that add custom URLs which aren't
  found by the `detect` step.
- The S3 addon was merged into the core plugin.
- A new "pathsToIgnore" option was added which supports glob patterns
  like * and **.
  See [Splat](https://github.com/PHLAK/Splat?tab=readme-ov-file#patterns)
  for syntax.
  This option replaces the old filenamesToIgnore and
  fileExtensionsToIgnore options.
- Added log levels to the logs table. The levels are "debug",
  "info", "warning", and "error".
- Debug logging was added. This can be enabled all the time with
  an option, or on a per-invocation basis with the WP CLI
  `--debug` flag.
- Updates pull directly from GitHub.
- The build and testing process were improved.
- Various bugfixes and small improvements.
- Some rarely used code was removed.

## 8.x series and 7.x forks

After Elementor ceased development of WP2Static and shuttered
the website, we continued work on a fork.
We made several releases with 8.x and 7.x version numbers.
[These releases](https://github.com/staticweb-io/wp2static/releases)
were unstable releases that were only used internally.
The combined changes are detailed in the 9.0.0 release notes.

## WP2Static 7.2 (2023-01-31)

 - [#876](https://github.com/WP2Static/wp2static/pull/876): Fix #240: ignore SSL errors when fetching sitemap from local site with self-signed certificate. @timothylcooke
 - [d3977eab](d3977eab6be24c4985d998a7f4bf07409ef4a71b): Create an index on `wp2static_jobs.status`. @john-shaffer
 - [#785](https://github.com/leonstafford/wp2static/issues/785): Accept self-signed certs during sitemap crawling. @working-name, @john-shaffer
 - [#806](https://github.com/leonstafford/wp2static/pull/806): Detect dead jobs and mark as failed. @john-shaffer
 - [#806](https://github.com/leonstafford/wp2static/pull/806): Mark duplicated waiting jobs as skipped on jobs page. @john-shaffer
 - [#794](https://github.com/leonstafford/wp2static/issues/794): Add an option to process the queue immediately. @john-shaffer
 - [#809](https://github.com/leonstafford/wp2static/pull/809): Add ability to rewrite hosts specified on a new advanced options page. @john-shaffer
   - As part of this, changed the host replacement function to use strtr instead of str_replace to avoid replacing things that we just replaced.
 - [#809](https://github.com/leonstafford/wp2static/pull/809): Add advanced option to skip URL rewriting. @john-shaffer
 - [#812](https://github.com/leonstafford/wp2static/pull/812): Add .editorconfig. @bookwyrm
 - [#816](https://github.com/leonstafford/wp2static/pull/816): Add wp2static_siteinfo filter. @palmiak
 - [bbc8abba](https://github.com/leonstafford/wp2static/commit/bbc8abba9103d097a62a6bbbd8d7a4229e788f4b): Fix error when a sitemap path starts with `//`. @jhatmaker, @john-shaffer
 - [#829](https://github.com/leonstafford/wp2static/pull/829): Move options labels and definitions out of the db and into code. @john-shaffer
 - [#826](https://github.com/leonstafford/wp2static/pull/826): Allow multiple redirects and report on redirects in wp-cli. @bookwyrm, @jhatmaker
 - [28fc58e5](https://github.com/leonstafford/wp2static/commit/28fc58e5f7694129e5919530adcd6c57435391fb): Add warning-level log messages. @john-shaffer
 - [#834](https://github.com/leonstafford/wp2static/pull/834): Implement concurrent crawling. @palmiak
   - Deprecate Crawler::crawlURL.
 - [#836](https://github.com/leonstafford/wp2static/pull/835): Add wp2static_option_\* filters and option types
 - [#833](https://github.com/leonstafford/wp2static/pull/833): Add advanced options for specifying directories, files, and file extensions to ignore @john-shaffer
 - [#837](https://github.com/leonstafford/wp2static/pull/837): Require PHP 7.4 or later; bump dependencies @leonstafford
 - [#811](https://github.com/leonstafford/wp2static/issues/811): Optimize FilesHelper::getListOfLocalFilesByDir @bookwyrm
 - [#805](https://github.com/leonstafford/wp2static/issues/805): Fix warning on cache page when there are no deployment namespaces @john-shaffer
 - [#843](https://github.com/leonstafford/wp2static/issues/843): Always fire post-deployment action from the CLI, matching the normal behavior @michaelfig
 - [#848](https://github.com/leonstafford/wp2static/issues/848): Fix error from IF EXISTS syntax when the db user can't see the schema. @utchy
 - [#849](https://github.com/leonstafford/wp2static/issues/849): Make job locking work with multisite. @utchy
 - [#844](https://github.com/leonstafford/wp2static/issues/844): Fix crawling of basic auth sites. @thecodeassassin, @vladstanca
 - [#855](https://github.com/leonstafford/wp2static/issues/855): Allow setting options to empty values from the CLI. @john-shaffer
 - [#850](https://github.com/leonstafford/wp2static/issues/850): Fix to write content to disk, even for cache hits when "Use CrawlCache" is ON. @utchy
 - [#877](https://github.com/leonstafford/wp2static/issues/877): Detect from web UI was not adding any URLs. @timothylcooke
 - [#878](https://github.com/leonstafford/wp2static/issues/878): Fix deletion of old pages when crawl returns 404. @timothylcooke
 - [#868](https://github.com/WP2Static/wp2static/pull/868): Detect files in Divi et-cache/ directory when present. @dunklerfox

## WP2Static 7.1.7 (2021-09-04)

 - logging and fixes for Sitemap detection @palmiak, @john-shaffer
 - fix #793 properly dequeue + deregister scripts @mrwweb
 - fix diagnostics uploadsWritable description @yilinjuang
 - fix #730 detect network-wide enabled plugins @stefanullinger
 - `INSERT IGNORE` to silence add-on duplicate insert warnings
 - add filters to deployment webhook:
  - `wp2static_deploy_webhook_user_agent`
  - `wp2static_deploy_webhook_body`
  - `wp2static_deploy_webhook_headers`
 - improved unit test coverage for Detection classes
 - add trailing slash to detected category pagination URLs @john-shaffer
 - rm `autoload-dev` from composer.json @szepeviktor
 - extend PHPStan coverage to view/template files
 - allow toggling an add-on via WP-CLI
 - new `wp2static_detect` hook fires at URL detection start @john-shaffer
 - diagnostics checks for trailing slash in permalinks @john-shaffer, @jonmhutch7
 - use sfely namespaced Guzzle to avoid conflicts with other plugins
 - default to showing DeployCache paths across all namespaces #745
 - allow setting deploy webhook headers/body/user-agent via filter
 - fix PostsPaginationURL detection #758 @petewilcock, @john-shaffer
 - use custom request options for sitemap crawling
 - move from cURL to Guzzle for requests
 - fix incompatibilities with PHP8
 - import SitemapParser as internal class
 - fix MySQL issue preventing Add-on activations @john-shaffer, @TheLQ

## WP2Static 7.1.6 (2020-12-04)

 - code quality improvements (thanks @szepeviktor)

## WP2Static 7.1.5 (2020-12-04)

 - fix PHP version check to >=7.3
 - fix errors during sitemap detection (thanks @fromcouch)
 - fix errors during cache table initialisation
 - fix pagination URLs not using correct schema
 - fix CLI command registration issue

## WP2Static 7.1.2 (2020-11-03)

 - update dependencies
 - add CHANGELOG
 - #682 only toggle other deploy addons, not other types when enabling a deployer
 - rm redundant Composer workaround
 - quieten build output
 - code quality improvements (thanks @szepeviktor!)

## WP2Static &lt; 7.1.2

 - didn't maintain Changelog or use tags, please review version control if curious

