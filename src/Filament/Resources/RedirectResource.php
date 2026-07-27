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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Actions\OpenAction;
use VanOns\FilamentRedirects\Filament\Exports\RedirectExporter;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\EditRedirect;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages\ListRedirects;
use VanOns\FilamentRedirects\Models\Redirect;
use VanOns\FilamentRedirects\Rules\NoCircularRedirect;
use VanOns\FilamentRedirects\Rules\NotSelfRedirect;
use VanOns\FilamentRedirects\Rules\ValidRegex;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string | \UnitEnum | null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'from';

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
            ->components([
                Section::make(__('filament-redirects::general.redirect_details'))
                    ->columnSpan(1)
                    ->icon('heroicon-o-arrows-right-left')
                    ->columns()
                    ->afterHeader(
                        Toggle::make('active')
                            ->label(__('filament-redirects::general.active'))
                            ->inline()
                            ->default(true)
                            ->columnSpan(2)
                            ->required(),
                    )
                    ->schema([
                        TextInput::make('from')
                            ->columnSpanFull()
                            ->label(__('filament-redirects::general.from'))
                            ->prefix(config('app.url').'/')
                            ->reactive()
                            ->rules(fn (Get $get) => $get('type') === Type::Match->value ? [new ValidRegex()] : [])
                            ->suffixAction(OpenAction::make())
                            ->required(),
                        TextInput::make('to')
                            ->columnSpanFull()
                            ->label(__('filament-redirects::general.to'))
                            ->prefix(config('app.url').'/')
                            ->reactive()
                            ->rule(fn (Get $get) => new NotSelfRedirect($get('from')))
                            ->rule(fn (Get $get) => new NoCircularRedirect($get('from')))
                            ->suffixAction(OpenAction::make()),
                        Select::make('type')
                            ->label(__('filament-redirects::general.type'))
                            ->options(self::getTypeOptions())
                            ->default(Type::Static)
                            ->live()
                            ->selectablePlaceholder(false),
                    ])
                    ->columnSpanFull(),
                Section::make()
                    ->columnSpan(1)
                    ->icon('heroicon-o-tag')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament-redirects::general.title'))
                            ->maxLength(255),
                        TextInput::make('category')
                            ->label(__('filament-redirects::general.category'))
                            ->datalist(fn () => array_keys(self::categoryOptions()))
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),
                Section::make()
                    ->columnSpan(1)
                    ->icon('heroicon-o-cog')
                    ->schema([
                        Select::make('status_code')
                            ->label(__('filament-redirects::general.status_code'))
                            ->options(self::statusCodeOptions())
                            ->searchable()
                            ->required()
                            ->default(config('filament-redirects.default_status_code', 301)),
                        Toggle::make('include_query')
                            ->label(__('filament-redirects::general.include_query'))
                            ->default(true)
                            ->required(),
                        Toggle::make('include_headers')
                            ->label(__('filament-redirects::general.include_headers'))
                            ->default(false)
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Section::make()
                    ->columnSpan(1)
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextInput::make('hits')
                            ->label(__('filament-redirects::general.hits'))
                            ->disabled(),
                        DateTimePicker::make('last_hit')
                            ->label(__('filament-redirects::general.last_hit'))
                            ->disabled(),
                    ])
                    ->visibleOn([Operation::View, Operation::Edit])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from')
                    ->label(__('filament-redirects::general.from'))
                    ->description(fn (Redirect $record) => $record->title)
                    ->sortable()
                    ->searchable(['from', 'title']),
                TextColumn::make('to')
                    ->label(__('filament-redirects::general.to'))
                    ->state(fn (Redirect $record) => $record->to ?? '/')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament-redirects::general.type'))
                    ->state(fn ($record) => __("filament-redirects::models/redirect.types.{$record->type->value}"))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->numeric()
                    ->badge()
                    ->sortable(),
                TextColumn::make('hits')
                    ->label(__('filament-redirects::general.hits'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit')
                    ->label(__('filament-redirects::general.last_hit'))
                    ->placeholder('-')
                    ->dateTime()
                    ->tooltip(fn (?string $state) => $state)
                    ->since()
                    ->sortable(),
                IconColumn::make('active')
                    ->label(__('filament-redirects::general.active'))
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
                EditAction::make(),
                Action::make('open')
                    ->label(__('filament-redirects::general.open'))
                    ->icon('heroicon-s-arrow-top-right-on-square')
                    ->url(fn (Redirect $record) => url($record->from ?? '/'), true),
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
