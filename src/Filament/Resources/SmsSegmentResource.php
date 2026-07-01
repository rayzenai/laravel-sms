<?php

namespace Rayzenai\LaravelSms\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Rayzenai\LaravelSms\Filament\Forms\Components\SegmentBuilder;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages\CreateSmsSegment;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages\EditSmsSegment;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages\ListSmsSegments;
use Rayzenai\LaravelSms\Models\SmsSegment;

class SmsSegmentResource extends Resource
{
    protected static ?string $model = SmsSegment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Segments';

    protected static ?string $modelLabel = 'Segment';

    protected static ?string $pluralModelLabel = 'Segments';

    protected static string|\UnitEnum|null $navigationGroup = 'SMS Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Segment name')
                    ->placeholder('e.g. Active Nepal users')
                    ->required()
                    ->maxLength(255),

                SegmentBuilder::make('conditions')
                    ->label('Who should this segment include?'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('previous_count')
                    ->label('Last count')
                    ->badge()
                    ->placeholder('—')
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('send')
                    ->label('Send SMS')
                    ->icon('heroicon-o-paper-airplane')
                    ->url(fn (SmsSegment $record) => SentMessageResource::getUrl('create', ['segment' => $record->getKey()])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsSegments::route('/'),
            'create' => CreateSmsSegment::route('/create'),
            'edit' => EditSmsSegment::route('/{record}/edit'),
        ];
    }
}
