# Usage

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
the current request path, which has no leading slash. If it matches, the visitor is redirected
to the `to` value.

| Field  | Value            |
|--------|------------------|
| `from` | `blog/[0-9]+/.*` |
| `to`   | `blog`           |

A request to `/blog/123/my-old-post` matches the pattern and redirects to `/blog`.

The `to` value can use what the groups in the pattern captured: `$1` for the first group, `$2` for
the second, and `${1}` when a digit follows the reference. A group that captured nothing leaves
an empty string. A reference to a group the pattern does not have is rejected when saving or
importing; one that was stored before stays as written.

| Field  | Value                                  |
|--------|----------------------------------------|
| `from` | `^locations/([^/]+)/faqs/.*$`          |
| `to`   | `locations/$1#faq`                     |

A request to `/locations/amsterdam/faqs/opening-hours` redirects to `/locations/amsterdam#faq`.
With `include_query` on, the query string goes in front of the `#`.

> **Note:** the pattern is unanchored, so it matches anywhere in the path. A `from` of `admin`
> also matches `/public/administrator/login`. Regex metacharacters are interpreted rather than
> taken literally. Anchor the pattern with `^` and `$` when you mean an exact path, for example
> `^blog/[0-9]+/.*$`. Patterns that do not compile are rejected when saving or importing, and an
> existing pattern that does not compile is skipped.

## Replace

The `from` value is matched as a literal substring of the request path. If found,
the `from` segment is replaced with the `to` segment in the current path.

| Field  | Value     |
|--------|-----------|
| `from` | `en/blog` |
| `to`   | `nl/blog` |

A request to `/en/blog/my-post` redirects to `/nl/blog/my-post`.
