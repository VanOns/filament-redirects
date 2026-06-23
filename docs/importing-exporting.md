# Importing & exporting

The package ships with built-in CSV import and export, powered by
[Filament's Import Action](https://filamentphp.com/docs/actions/import) and
[Export Action](https://filamentphp.com/docs/actions/export).

## Importing

The import button is available in the top-right corner of the redirects list.

### CSV columns

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

## Exporting

Exporting is available as a bulk action: select one or more redirects in the list, then choose
**Export** from the bulk actions dropdown.

### CSV columns

The export contains the following columns:

| Column            | Description                                                     |
|-------------------|-----------------------------------------------------------------|
| `from`            | The source path                                                 |
| `to`              | The destination path                                            |
| `type`            | The redirect type (`static`, `match`, `replace`)                |
| `status_code`     | The HTTP status code used for the redirect                      |
| `include_headers` | Whether the original request headers are forwarded              |
| `include_query`   | Whether the original query string is forwarded                  |
| `category`        | Optional category used to group and filter redirects            |
| `title`           | Optional human-readable label giving the redirect extra context |
| `active`          | Whether the redirect is active                                  |
| `hits`            | Number of times the redirect has been triggered                 |
| `last_hit`        | Timestamp of the most recent hit                                |
| `priority`        | The evaluation priority                                         |
| `created_at`      | When the redirect was created                                   |
| `updated_at`      | When the redirect was last updated                              |

> **Note:** Only the columns the importer accepts (see [Importing](#importing) above) are read back
> when an exported file is re-imported. The remaining columns are ignored.

## Database tables

Both features rely on dedicated database tables to track jobs: `imports` and `failed_import_rows`
for importing, and `exports` for exporting. These are created by the package migrations, so make
sure you have published and run them:

```sh
php artisan vendor:publish --tag=vanons-filament-redirects-migrations
php artisan migrate
```

## Queue & notifications table

Imports and exports are both processed using Laravel queues. When your queue connection is not
`sync`, they run in the background. When a job finishes, Filament sends a database notification to
the user who triggered it. For this to work, your application must have the notifications table
present.

If you haven't created it yet, run:

```bash
php artisan make:notifications-table
php artisan migrate
```

> **Note:** Without the notifications table, completed notifications will fail silently. See the
> Filament [import](https://filamentphp.com/docs/actions/import) and
> [export](https://filamentphp.com/docs/actions/export) documentation for more details.
</content>
