<?php

namespace Rayzenai\LaravelSms\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource\Pages;
use Rayzenai\LaravelSms\Models\SentMessage;

class SentMessageResource extends Resource
{
    protected static ?string $model = SentMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Sent Messages';

    protected static ?string $modelLabel = 'Sent Message';

    protected static ?string $pluralModelLabel = 'Sent Messages';

    protected static ?string $navigationGroup = 'SMS Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('recipient')
                    ->label('Recipient')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sender')
                    ->label('Sender')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'delivered' => 'Delivered',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('provider')
                    ->label('Provider')
                    ->maxLength(255),
                Forms\Components\TextInput::make('provider_message_id')
                    ->label('Provider Message ID')
                    ->maxLength(255),
                Forms\Components\KeyValue::make('provider_response')
                    ->label('Provider Response')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Sent At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->tooltip(function (SentMessage $record): string {
                        return $record->message;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'sent' => 'success',
                        'delivered' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sender')
                    ->label('Sender')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'delivered' => 'Delivered',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListSentMessages::route('/'),
            'create' => Pages\CreateSentMessage::route('/create'),
            'view' => Pages\ViewSentMessage::route('/{record}'),
            'edit' => Pages\EditSentMessage::route('/{record}/edit'),
        ];
    }
}
