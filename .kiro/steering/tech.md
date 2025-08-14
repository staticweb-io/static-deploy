---
inclusion: always
---

# Technology Stack & Development Guidelines

## Required Code Standards
- **PHP 8.1+** with `declare(strict_types=1)` in ALL files
- **Namespace**: Use `StaticDeploy\` for all classes
- **PHPStan Level Max**: Code must pass strict static analysis
- **WordPress Coding Standards**: Follow PHPCS rules with zero violations

## Mandatory Dependencies
- **guzzlehttp/guzzle**: Use for ALL HTTP requests (not native cURL/file_get_contents) and URL manipulation (not parse_url/http_build_query)
- **masterminds/html5**: Use for HTML parsing (not DOMDocument)
- **symfony/finder**: Use for file operations (not glob/scandir/DirectoryIterator)

## Critical Architecture Rules
- **Controller Pattern**: Use singleton `Controller.php` for plugin lifecycle
- **Exception Handling**: Throw `StaticDeployException` for all plugin errors
- **Logging**: Use `WsLog::l()` with appropriate levels (never error_log/var_dump)
- **Options**: Use `Options.php` class (never direct get_option/update_option)
- **Input Sanitization**: Use WordPress sanitization functions for ALL user input

## File & Class Conventions
- **Class Files**: PascalCase matching class name (e.g., `URLDetector.php`)
- **Method Names**: camelCase with descriptive verbs (e.g., `detectPageURLs()`)
- **Constants**: `STATIC_DEPLOY_` prefix with UPPER_SNAKE_CASE
- **Traits**: Use for shared functionality (e.g., `DeployerTrait`, `OptionsControllerTrait`)

## Performance Requirements
- **Memory**: Process large datasets in chunks to avoid memory limits
- **Concurrency**: Use configurable threading for crawling operations
- **Caching**: Use `DeployCache.php` for expensive operations
- **Database**: Use WordPress WPDB abstraction (never raw SQL)

## Quality Assurance Commands
```bash
composer phpcs      # Code style check (must pass)
composer phpstan    # Static analysis (must pass)
composer test       # Full test suite (must pass)
```

## WordPress Integration Rules
- Use WordPress hooks/filters for lifecycle events
- Sanitize ALL user input with WordPress functions
- Use WordPress database abstraction layer
- Follow WordPress plugin architecture patterns
- Store configuration via `Options.php` class