<?php

namespace VanOns\FilamentRedirects\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Operation;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Actions\OpenAction;
use VanOns\FilamentRedirects\Filament\Exports\RedirectExporter;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\EditRedirect;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\ListRedirects;
use VanOns\FilamentRedirects\Models\Redirect;
use VanOns\FilamentRedirects\Rules\NoCircularRedirect;
use VanOns\FilamentRedirects\Rules\NotSelfRedirect;
use VanOns\FilamentRedirects\Rules\ValidGroupReferences;
use VanOns\FilamentRedirects\Rules\ValidRegex;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string | \UnitEnum | null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'from';

    private const TYPE_ICONS = [
        'static' => 'heroicon-m-arrow-long-right',
        'match' => 'heroicon-m-code-bracket',
        'replace' => 'heroicon-m-arrows-right-left',
    ];

    // Filament's fi-font-mono only styles text entries, so inputs get its font stack, fallbacks included.
    private const MONOSPACE = ['style' => 'font-family: var(--mono-font-family), ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'];

    // Filament gives inline code no more than a monospace font, so the examples in helper text get a chip of their own.
    private const CODE_STYLE = 'padding: 0 0.25rem; border-radius: 0.25rem; font-size: 0.9em; background-color: color-mix(in oklab, var(--gray-500) 15%, transparent)';

    public static function getModelLabel(): string
    {
        return trans_choice('filament-redirects::models/redirect.label', 1);
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('filament-redirects::models/redirect.label', 2);
    }

    public static function getNavigationLabel(): string
    {
        return trans_choice('filament-redirects::models/redirect.label', 2);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 3])
            ->components([
                Group::make([
                    self::redirectSection(),
                    self::testSection(),
                ])
                    ->columnSpan(['lg' => 2]),

                Group::make([
                    self::settingsSection(),
                    self::labelsSection(),
                    self::statisticsSection(),
                ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from')
                    ->label(trans_choice('filament-redirects::models/redirect.label', 1))
                    ->formatStateUsing(fn (Redirect $record): string => self::displayFrom($record))
                    ->description(fn (Redirect $record): ?string => self::displayLabels($record), position: 'above')
                    ->description(fn (Redirect $record): string => '→ '.self::displayTo($record))
                    ->fontFamily(FontFamily::Mono)
                    ->wrap()
                    ->sortable()
                    ->searchable(['from', 'to', 'title', 'category']),
                TextColumn::make('type')
                    ->label(__('filament-redirects::general.type'))
                    ->state(fn (Redirect $record) => __("filament-redirects::models/redirect.short_types.{$record->type->value}"))
                    ->icon(fn (Redirect $record): string => self::TYPE_ICONS[$record->type->value])
                    ->color('gray')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->numeric()
                    ->color('gray')
                    ->badge()
                    ->sortable(),
                TextColumn::make('hits')
                    ->label(__('filament-redirects::general.hits'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_hit')
                    ->label(__('filament-redirects::general.last_hit'))
                    ->placeholder('-')
                    ->dateTime()
                    ->tooltip(fn (?string $state) => $state)
                    ->since()
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('active')
                    ->label(__('filament-redirects::general.active'))
                    ->alignCenter()
                    ->sortable()
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderable('priority')
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('filament-redirects::general.active'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('active', '=', true),
                        false: fn (Builder $query) => $query->where('active', '=', false),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('type')
                    ->label(__('filament-redirects::general.type'))
                    ->options(self::getTypeOptions()),
                SelectFilter::make('category')
                    ->label(__('filament-redirects::general.category'))
                    ->options(fn () => self::categoryOptions())
                    ->searchable(),
                SelectFilter::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->options(self::statusCodeOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('filament-redirects::general.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Redirect $record) => url($record->from), true)
                    ->visible(fn (Redirect $record) => $record->type === Type::Static),
                EditAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(RedirectExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The `from` of a redirect as the list shows it: a static path starts with a slash, a pattern or a part
     * of a path stays as written.
     */
    public static function displayFrom(Redirect $redirect): string
    {
        return $redirect->type === Type::Static ? '/'.ltrim($redirect->from, '/') : $redirect->from;
    }

    /**
     * The `to` of a redirect as the list shows it: see displayUrl(), except for the part a replace redirect
     * puts in, which stays as written.
     */
    public static function displayTo(Redirect $redirect): string
    {
        return $redirect->type === Type::Replace ? (string) $redirect->to : self::displayUrl((string) $redirect->to);
    }

    /**
     * A destination as the panel shows it: a URL on this site becomes its path, starting with a slash, and a
     * URL on another domain is marked with ↗.
     */
    public static function displayUrl(string $url): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && ($url === $appUrl || Str::startsWith($url, $appUrl.'/'))) {
            $url = Str::after($url, $appUrl);
        }

        if (self::isUrl($url)) {
            return '↗ '.Str::after($url, '//');
        }

        return '/'.ltrim($url, '/');
    }

    private static function redirectSection(): Section
    {
        // The prefix of the field already shows the leading slash, so a path is shown and stored without one. A static
        // From loses its trailing slash as well, as a request path has none; in To it is part of where the visitor goes.
        $fromPath = fn (?string $state, Get $get): ?string => self::typeOf($get) === Type::Static ? self::withoutSlashes($state, trailing: true) : $state;
        $toPath = fn (?string $state, Get $get): ?string => self::typeOf($get) !== Type::Replace && !self::isUrl($state) ? self::withoutSlashes($state, trailing: false) : $state;

        return Section::make(__('filament-redirects::general.redirect_details'))
            ->description(__('filament-redirects::general.paths_on', ['host' => parse_url((string) config('app.url'), PHP_URL_HOST)]))
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                ToggleButtons::make('type')
                    ->label(__('filament-redirects::general.type'))
                    ->options(self::getTypeOptions())
                    ->icons(self::TYPE_ICONS)
                    ->default(Type::Static->value)
                    ->inline()
                    ->grouped()
                    ->live()
                    ->required(),

                TextInput::make('from')
                    ->label(__('filament-redirects::general.from'))
                    ->prefix(fn (Get $get, ?string $state) => self::typeOf($get) !== Type::Replace && $state !== '/' ? '/' : null)
                    ->formatStateUsing($fromPath)
                    ->dehydrateStateUsing($fromPath)
                    ->extraInputAttributes(self::MONOSPACE)
                    ->helperText(fn (Get $get) => self::helperText('filament-redirects::general.from_help.'.self::typeOf($get)->value))
                    ->reactive()
                    ->rules(fn (Get $get) => self::typeOf($get) === Type::Match ? [new ValidRegex()] : [])
                    ->suffixAction(OpenAction::make()->visible(fn (Get $get) => self::typeOf($get) === Type::Static))
                    ->required(),

                TextInput::make('to')
                    ->label(__('filament-redirects::general.to'))
                    ->prefix(fn (Get $get, ?string $state) => self::typeOf($get) !== Type::Replace && $state !== '/' && !self::isUrl($state) ? '/' : null)
                    ->formatStateUsing($toPath)
                    ->dehydrateStateUsing($toPath)
                    ->extraInputAttributes(self::MONOSPACE)
                    ->helperText(fn (Get $get) => self::helperText('filament-redirects::general.to_help.'.self::typeOf($get)->value))
                    ->reactive()
                    ->rule(fn (Get $get) => new NotSelfRedirect($get('from')))
                    ->rule(fn (Get $get) => new NoCircularRedirect($get('from')))
                    ->rules(fn (Get $get) => self::typeOf($get) === Type::Match ? [new ValidGroupReferences($get('from'))] : [])
                    ->suffixAction(OpenAction::make()->visible(fn (Get $get) => self::typeOf($get) !== Type::Replace)),
            ]);
    }

    private static function testSection(): Section
    {
        return Section::make(__('filament-redirects::general.test'))
            ->description(__('filament-redirects::general.test_description'))
            ->icon('heroicon-o-beaker')
            ->columns()
            ->schema([
                TextInput::make('test_url')
                    ->label(__('filament-redirects::general.test_url'))
                    ->prefix('/')
                    ->extraInputAttributes(self::MONOSPACE)
                    ->dehydrated(false)
                    ->live(debounce: 300)
                    ->columnSpanFull(),

                TextEntry::make('test_result')
                    ->label(__('filament-redirects::general.test_result'))
                    ->state(fn (Get $get): string => self::testResult($get))
                    ->formatStateUsing(fn (string $state) => __("filament-redirects::general.test_results.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'match' => 'success',
                        'invalid' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'match' => 'heroicon-m-check-circle',
                        'invalid' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-x-circle',
                    })
                    ->visible(fn (Get $get): bool => filled($get('test_url'))),

                TextEntry::make('test_destination')
                    ->label(__('filament-redirects::general.test_destination'))
                    ->state(fn (Get $get): string => self::displayUrl((string) self::filledInRedirect($get)?->resolve((string) $get('test_url'))))
                    ->fontFamily(FontFamily::Mono)
                    ->visible(fn (Get $get): bool => filled($get('test_url')) && self::testResult($get) === 'match'),
            ]);
    }

    private static function settingsSection(): Section
    {
        return Section::make(__('filament-redirects::general.settings'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Toggle::make('active')
                    ->label(__('filament-redirects::general.active'))
                    ->default(true)
                    ->required(),

                Select::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->options(self::statusCodeOptions())
                    ->searchable()
                    ->required()
                    ->default(config('filament-redirects.default_status_code', 301)),

                Toggle::make('include_query')
                    ->label(__('filament-redirects::general.include_query'))
                    ->helperText(self::helperText('filament-redirects::general.include_query_help'))
                    ->default(true)
                    ->reactive()
                    ->required(),

                Toggle::make('include_headers')
                    ->label(__('filament-redirects::general.include_headers'))
                    ->default(false)
                    ->required(),
            ]);
    }

    private static function labelsSection(): Section
    {
        return Section::make(__('filament-redirects::general.labels'))
            ->icon('heroicon-o-tag')
            ->schema([
                TextInput::make('title')
                    ->label(__('filament-redirects::general.title'))
                    ->maxLength(255),

                TextInput::make('category')
                    ->label(__('filament-redirects::general.category'))
                    ->datalist(fn () => array_keys(self::categoryOptions()))
                    ->maxLength(255),
            ]);
    }

    private static function statisticsSection(): Section
    {
        return Section::make(__('filament-redirects::general.statistics'))
            ->icon('heroicon-o-chart-bar')
            ->schema([
                TextInput::make('hits')
                    ->label(__('filament-redirects::general.hits'))
                    ->disabled(),

                DateTimePicker::make('last_hit')
                    ->label(__('filament-redirects::general.last_hit'))
                    ->disabled(),
            ])
            ->visibleOn([Operation::View, Operation::Edit]);
    }

    /**
     * The translation as helper text, with the examples between backticks shown as inline code.
     */
    private static function helperText(string $key): HtmlString
    {
        $text = __($key);
        $html = Str::inlineMarkdown(is_string($text) ? $text : $key, ['html_input' => 'escape']);

        return new HtmlString(str_replace('<code>', '<code style="'.self::CODE_STYLE.'">', $html));
    }

    // The home page keeps its slash: its path is `/`, and an empty field fails `required` or never matches.
    private static function withoutSlashes(?string $path, bool $trailing): ?string
    {
        if ($path === null || trim($path, '/') === '') {
            return $path;
        }

        return $trailing ? trim($path, '/') : ltrim($path, '/');
    }

    /**
     * Whether the redirector sends a visitor to this value as it is, rather than to a path on this site.
     */
    private static function isUrl(?string $value): bool
    {
        return $value !== null && app(UrlGenerator::class)->isValidUrl($value);
    }

    private static function typeOf(Get $get): Type
    {
        $type = $get('type');

        return $type instanceof Type ? $type : (Type::tryFrom((string) $type) ?? Type::Static);
    }

    /**
     * The redirect as it is filled in, or null when its pattern does not compile.
     */
    private static function filledInRedirect(Get $get): ?Redirect
    {
        $redirect = new Redirect([
            'from' => (string) $get('from'),
            'to' => $get('to'),
            'type' => self::typeOf($get),
            'include_query' => (bool) $get('include_query'),
        ]);

        if ($redirect->type === Type::Match && @preg_match(Redirect::pattern($redirect->from), '') === false) {
            return null;
        }

        return $redirect;
    }

    private static function testResult(Get $get): string
    {
        $redirect = self::filledInRedirect($get);

        return match (true) {
            $redirect === null => 'invalid',
            $redirect->resolve((string) $get('test_url')) === null => 'no_match',
            default => 'match',
        };
    }

    private static function displayLabels(Redirect $redirect): ?string
    {
        $labels = array_filter([$redirect->category, $redirect->title]);

        return $labels === [] ? null : implode(' · ', $labels);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function statusCodeOptions(): array
    {
        /** @var array<int, int> $statusCodes */
        $statusCodes = config('filament-redirects.status_codes', []);

        return collect($statusCodes)
            ->mapWithKeys(fn ($statusCode) => [
                $statusCode => __("filament-redirects::general.status_codes.{$statusCode}"),
            ])
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getTypeOptions(): array
    {
        return collect(Type::cases())
            ->mapWithKeys(fn ($type) => [
                $type->value => __("filament-redirects::models/redirect.types.{$type->value}"),
            ])
            ->toArray();
    }

    /**
     * Distinct, non-empty categories currently stored on redirects.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return Redirect::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category', 'category')
            ->all();
    }
}
