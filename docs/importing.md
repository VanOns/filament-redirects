# Importing

The package includes a built-in CSV importer powered by
[Filament's Import Action](https://filamentphp.com/docs/actions/import).
The import button is available in the top-right corner of the redirects list.

## CSV columns

| Column            | Required | Values                            | Description                                                               |
|-------------------|----------|-----------------------------------|---------------------------------------------------------------------------|
| `from`            | yes      | string (max 255)                  | The source path (leading/trailing slashes are trimmed automatically)      |
| `to`              | yes      | string (max 255)                  | The destination path (leading/trailing slashes are trimmed automatically) |
| `type`            | yes      | `static`, `match`, `replace`      | The redirect type                                                         |
| `status_code`     | yes      | `301`, `302`, `303`, `307`, `308` | The HTTP status code to use for the redirect                              |
| `include_headers` | yes      | `true`/`false` (`1`/`0`)          | Whether to forward the original request headers                           |
| `include_query`   | yes      | `true`/`false` (`1`/`0`)          | Whether to forward the original query string                              |
| `category`        | no       | string (max 255)                  | Optional category used to group and filter redirects                      |
| `title`           | no       | string (max 255)                  | Optional human-readable label giving the redirect extra context           |

## Queue & notifications table

Imports are processed using Laravel queues. When your queue connection is not `sync`, they run in
the background. When an import finishes, Filament sends a database notification to the user who
triggered it. For this to work, your application must have the notifications table present.

If you haven't created it yet, run:

```bash
php artisan make:notifications-table
php artisan migrate
```

> **Note:** Without the notifications table, completed import notifications will fail silently.
> See the [Filament import documentation](https://filamentphp.com/docs/actions/import) for more details.
