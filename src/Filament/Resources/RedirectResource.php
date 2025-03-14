<?php

namespace VanOns\FilamentRedirects\Filament\Resources;

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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages;
use VanOns\Redirector\Models\Redirect;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Section::make()
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        TextInput::make('from')
                            ->prefix(config('app.url').'/')
                            ->label(__('filament-redirects::general.from'))
                            ->required(),
                        TextInput::make('to')
                            ->prefix(config('app.url').'/')
                            ->label(__('filament-redirects::general.to'))
                            ->required(),
                        Toggle::make('active')
                            ->label(__('filament-redirects::general.active'))
                            ->inline()
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpan(1),
                Section::make()
                    ->icon('heroicon-o-cog')
                    ->schema([
                        Select::make('status_code')
                            ->label(__('filament-redirects::general.status_code'))
                            ->options([
                                301 => __('filament-redirects::general.status_codes.301'),
                                302 => __('filament-redirects::general.status_codes.302'),
                                303 => __('filament-redirects::general.status_codes.303'),
                                307 => __('filament-redirects::general.status_codes.307'),
                                308 => __('filament-redirects::general.status_codes.308'),
                            ])
                            ->searchable()
                            ->required()
                            ->default(301),
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
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextInput::make('hits')
                            ->label(__('filament-redirects::general.hits'))
                            ->disabled(),
                        DateTimePicker::make('last_hit')
                            ->label(__('filament-redirects::general.last_hit'))
                            ->disabled(),
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
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status_code')
                    ->label(__('filament-redirects::general.status_code'))
                    ->sortable()
                    ->numeric()
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
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
}
