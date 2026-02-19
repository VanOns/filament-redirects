<?php

namespace VanOns\FilamentRedirects\Filament\Resources;

use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Actions\OpenAction;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages;
use VanOns\FilamentRedirects\Models\Redirect;
use Filament\Forms\Components\Actions\Action as FormAction;


class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

     protected static ?string $navigationGroup = null;

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

    public static function getNavigationGroup(): ?string
    {
        if (config('filament-redirects.add_nav_group')) {
            return __('filament-redirects::general.navigation-group');
        }

        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament-redirects::general.redirect_details'))
                    ->columnSpan(1)
                    ->icon('heroicon-o-arrows-right-left')
                    ->columns()
                    ->schema([
                        TextInput::make('from')
                            ->columnSpanFull()
                            ->label(__('filament-redirects::general.from'))
                            ->prefix(config('app.url').'/')
                            ->reactive()
                            ->suffixAction(OpenAction::make())
                            ->required(),
                        TextInput::make('to')
                            ->columnSpanFull()
                            ->label(__('filament-redirects::general.to'))
                            ->prefix(config('app.url').'/')
                            ->reactive()
                            ->suffixAction(OpenAction::make()),
                        Select::make('type')
                            ->label(__('filament-redirects::general.type'))
                            ->options(self::getTypeOptions())
                            ->default(Type::Static)
                            ->selectablePlaceholder(false),
                    ])
                    ->columnSpan(1),
                Section::make()
                    ->columnSpan(1)
                    ->icon('heroicon-o-cog')
                    ->schema([
                        Toggle::make('active')
                            ->default(true),
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
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpan(1),
                Section::make()
                    ->columnSpan(1)
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextInput::make('hits')
                            ->label(__('filament-redirects::general.hits'))
                            ->disabled(),
                        DateTimePicker::make('last_hit')
                            ->label(__('filament-redirects::general.last_hit'))
                            ->placeholder('-')
                            ->disabled()
                            ->helperText(function ($state) {
                                if (!$state) {
                                    return '-';
                                }

                                $dt = $state instanceof Carbon ? $state : Carbon::parse($state);

                                return $dt->diffForHumans();
                            })
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from')
                    ->label(__('filament-redirects::general.from'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('to')
                    ->label(__('filament-redirects::general.to'))
                    ->state(fn ($record) => $record->to ?? '/')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament-redirects::general.type'))
                    ->state(fn ($record) => __("filament-redirects::models/redirect.types.{$record->type->value}"))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->sortable()
                    ->numeric()
                    ->badge()
                    ->sortable(),
                TextColumn::make('hits')
                    ->label(__('filament-redirects::general.hits'))
                    ->sortable()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit')
                    ->label(__('filament-redirects::general.last_hit'))
                    ->sortable()
                    ->dateTime()
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
                SelectFilter::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->options(self::statusCodeOptions()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('open')
                    ->label(__('filament-redirects::general.open'))
                    ->icon('heroicon-s-arrow-top-right-on-square')
                    ->url(fn (Redirect $record) => url($record->from ?? '/'), true)
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Redirect>
     */
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
}
