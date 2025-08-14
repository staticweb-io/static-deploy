# Project Structure & Organization

## Root Directory Layout
```
static-deploy/
├── src/                    # Main plugin source code (PSR-4: StaticDeploy\)
├── views/                  # WordPress admin interface templates
├── tests/                  # Test suites (unit, integration, phpstan)
├── dev/                    # Development environment (Nix-based)
├── vendor/                 # Composer dependencies
├── bin/                    # Executable scripts
├── static-deploy.php          # Main plugin file
└── composer.json          # Dependencies and scripts
```

## Source Code Organization (`src/`)

### Core Components
- **Controller.php**: Main plugin controller (singleton pattern)
- **CLI.php**: WP-CLI command interface
- **WordPressAdmin.php**: WordPress admin integration

### URL Detection & Processing
- **URLDetector.php**: Main URL discovery orchestrator
- **Detect*.php**: Specialized URL detectors (Pages, Posts, Categories, etc.)
- **URLHelper.php**, **URLParser.php**: URL manipulation utilities
- **SitemapParser.php**: XML sitemap processing

### Crawling & File Processing
- **Crawler.php**: Multi-threaded site crawling engine
- **FileProcessor.php**: Static file processing and optimization
- **PostProcessor.php**: Content post-processing and URL rewriting
- **SimpleRewriter.php**: URL rewriting for static hosting

### Data Management
- **DetectedFiles.php**, **CrawledFiles.php**: File tracking
- **DeployCache.php**: Deployment caching system
- **JobQueue.php**: Background job management
- **CoreOptions.php**: Plugin configuration management

### Utilities
- **Utils.php**: General utility functions
- **FilesHelper.php**: File system operations
- **WsLog.php**: Logging system
- **SiteInfo.php**: WordPress site information

## Views Directory (`views/`)
WordPress admin interface templates:
- **options-page.php**: Main settings interface
- **run-page.php**: Crawling execution interface
- **logs-page.php**: Log viewing interface
- ***-page.php**: Various admin pages for different features

## Testing Structure (`tests/`)
```
tests/
├── unit/                   # Unit tests (PHPUnit)
├── integration/           # End-to-end tests
├── phpstan/              # PHPStan configuration
└── bootstrap.php         # Test bootstrap
```

## Development Environment (`dev/`)
- **flake.nix**: Nix development environment
- **data/**: Development data (MySQL, WordPress, Nginx configs)

## Naming Conventions
- **Classes**: PascalCase (e.g., `URLDetector`, `FileProcessor`)
- **Files**: Match class names (e.g., `URLDetector.php`)
- **Methods**: camelCase with descriptive names
- **Constants**: UPPER_SNAKE_CASE (e.g., `STATIC_DEPLOY_VERSION`)
- **Namespaces**: PSR-4 compliant (`StaticDeploy\`)
