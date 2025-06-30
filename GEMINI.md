# Gemini Project Guidelines

This document provides guidelines for the Gemini AI assistant to follow when working on this project.

## 1. Project Overview

- **Project Name:** portatec
- **Framework:** Laravel
- **Primary Language:** PHP

## 2. Development Environment

This project uses Laravel Sail for its local development environment. All commands that would normally be run locally (e.g., `php`, `artisan`, `composer`, `npm`, `phpunit`) **MUST** be prefixed with `sail`.

**Key Commands:**
- **Start the environment:** `sail up -d`
- **Stop the environment:** `sail down`
- **Run Artisan commands:** `sail artisan <command>`
- **Install Composer dependencies:** `sail composer install`
- **Install NPM dependencies:** `sail npm install`
- **Run frontend assets build:** `sail npm run dev`
- **Run tests:** `sail test`

## 3. Coding Standards & Best Practices

- **Filament:** This project uses Filament PHP version 3. Ensure all components and code adhere to this version's standards and practices.

- **Style Guide:** Adhere strictly to the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style guide and Laravel's own coding standards.
- **Object Calisthenics:** Follow the Object Calisthenics rules for object-oriented programming.
- **Testing:** All new features must be accompanied by relevant feature or unit tests. Bug fixes should include a regression test to prevent future issues.
- **Naming Conventions:** Follow Laravel's standard naming conventions for controllers, models, migrations, etc.
- **Security:** Be mindful of security best practices, especially regarding SQL injection (use Eloquent/Query Builder), Cross-Site Scripting (XSS), and Cross-Site Request Forgery (CSRF).
- **Commit Messages:** Write clear and concise commit messages, following the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification.
- Avoid use IF ELSE, use early return.
- During development of a commit, avoid to create migrations that changes the migrations created on same commit or branch.

## 4. Dependencies

- to use sail, use on ./vendor/bin/sail
- **PHP Packages:** Manage with `sail composer`.
- **JavaScript Packages:** Manage with `sail npm`.
- Before adding a new dependency, check if similar functionality already exists within the project or Laravel framework.
