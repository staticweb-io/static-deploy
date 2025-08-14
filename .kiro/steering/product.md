# Static Deploy Plugin Overview

Static Deploy is a WordPress plugin that generates static websites from WordPress sites and handles deployment to various hosting platforms.

## Core Functionality
- **Static Site Generation**: Crawls WordPress sites and converts dynamic content to static HTML/CSS/JS files
- **URL Detection**: Automatically discovers pages, posts, categories, authors, archives, and assets
- **File Processing**: Processes and optimizes static files for deployment
- **Deployment**: Supports multiple deployment targets and methods
- **WordPress Integration**: Provides admin interface and WP-CLI commands

## Key Features
- Multi-threaded crawling with configurable concurrency
- Comprehensive URL discovery (posts, pages, categories, authors, pagination, sitemaps)
- Asset detection (themes, plugins, WordPress core files)
- Post-processing and URL rewriting for static hosting
- Caching system for efficient re-crawls
- Job queue system for background processing
- Extensive logging and diagnostics

## Target Users
WordPress site owners who want to convert their dynamic sites to static for improved security, performance, and hosting flexibility.