# Basic usage

Redirect rules are evaluated in priority order. The first matching rule wins.

There are three supported redirect types:

## Static

An exact URL match. The `from` value is compared literally against the current request path.
If it matches, the visitor is redirected to the `to` value.

| Field  | Value      |
|--------|------------|
| `from` | `old-page` |
| `to`   | `new-page` |

A request to `/old-page` redirects to `/new-page`.

## Match

The `from` value is used as a regular expression (PCRE, without delimiters) and tested against
the current request path. If it matches, the visitor is redirected to the static `to` value.
Captured groups are not interpolated — the destination is always a fixed URL.

| Field  | Value            |
|--------|------------------|
| `from` | `blog/[0-9]+/.*` |
| `to`   | `blog`           |

A request to `/blog/123/my-old-post` matches the pattern and redirects to `/blog`.

## Replace

The `from` value is matched as a literal substring of the request path. If found,
the `from` segment is replaced with the `to` segment in the current path.

| Field  | Value     |
|--------|-----------|
| `from` | `en/blog` |
| `to`   | `nl/blog` |

A request to `/en/blog/my-post` redirects to `/nl/blog/my-post`.
