# Introduction

A Filament package to manage redirects in your application.

## Features

- **Three redirect types** — static exact match, regex-based match, and substring replace
- **Middleware-based handling** — `RedirectMiddleware` runs on every request and redirects automatically
- **Priority ordering** — drag-and-drop reordering in the admin panel controls evaluation order
- **Hit tracking** — records a hit count and timestamp each time a redirect is triggered
- **Soft deletes** — deleted redirects are recoverable from the admin panel
- **Circular redirect detection** — prevents saving a redirect that would cause an infinite loop
- **Configurable caching** — optionally cache the active redirect list to reduce database queries
