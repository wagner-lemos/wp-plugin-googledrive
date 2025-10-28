# WPMUDEV Test Plugin #

This is a plugin that can be used for testing coding skills for WordPress and PHP.

# Development

## Used versions
- php: 8.2
- WP Verison: 6.8
- Node: 22.16
- NPM: 10.9

## Composer
Install composer packages
`composer install`

## Build Tasks (npm)
Everything should be handled by npm.

Install npm packages
`npm install`

| Command              | Action                                                |
|----------------------|-------------------------------------------------------|
| `npm run watch`      | Compiles and watch for changes.                       |
| `npm run compile`    | Compile production ready assets.                      |
| `npm run build`      | Build production ready bundle inside `/build/` folder |

## Changelog

## [1.1.0] - 2025-10-29

## Features
- Google Drive integration with OAuth 2.0 authentication
- React admin interface with file operations (upload, download, create folders)
- Posts maintenance system with background processing
- WP-CLI integration and automated daily scheduling
- Unit tests and REST API endpoints

## Performance & Security
- Package size reduced from 35MB to 682KB (98% reduction)
- Credential encryption with AES-256-CBC
- Direct HTTP API implementation
- Input validation and CSRF protection

## Technical Improvements
- WordPress Coding Standards compliance
- Modular React component architecture
- Comprehensive error handling
- Frontend pagination support
- Optimized build pipeline
- Simplified Gruntfile.js
- Streamlined build process
