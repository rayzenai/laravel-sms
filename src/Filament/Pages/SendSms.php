<?php

namespace Rayzenai\LaravelSms\Filament\Pages;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Rayzenai\LaravelSms\Facades\Sms;
use Rayzenai\LaravelSms\Rules\NepaliPhoneNumber;

class SendSms extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected string $view = 'laravel-sms::filament.pages.send-sms';
    
    protected static ?string $navigationLabel = 'Send SMS';
    
    protected static string | \UnitEnum | null $navigationGroup = 'SMS Management';
    
    protected static ?int $navigationSort = 1;
    
    public ?array $data = [];
    
    protected function getFormStatePath(): ?string
    {
        return 'data';
    }
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Toggle::make('isBulk')
                    ->label('Send Bulk SMS')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetForm()),
                    
                Forms\Components\TextInput::make('recipient')
                    ->label('Recipient Phone Number')
                    ->prefix('+977')
                    ->placeholder('9801234567')
                    ->tel()
                    ->required()
                    ->maxLength(10)
                    ->minLength(10)
                    ->numeric()
                    ->rules(['regex:/^9[0-9]{9}$/'])
                    ->visible(fn ($get) => !$get('isBulk'))
                    ->helperText('Enter 10-digit mobile number (without +977)')
                    ->dehydrateStateUsing(fn ($state) => $state ? '+977' . $state : null),
                    
                Forms\Components\TagsInput::make('recipients')
                    ->label('Recipients Phone Numbers')
                    ->placeholder('Add phone numbers (e.g., 9801234567)...')
                    ->required()
                    ->visible(fn ($get) => $get('isBulk'))
                    ->helperText('Enter 10-digit mobile numbers (without +977)')
                    ->nestedRecursiveRules([
                        'string',
                        'regex:/^9[0-9]{9}$/',
                    ])
                    ->dehydrateStateUsing(fn ($state) => array_map(fn ($number) => '+977' . $number, $state ?? [])),
                    
                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->maxLength(160)
                    ->rows(4)
                    ->helperText(fn ($state) => (160 - strlen($state ?? '')) . ' characters remaining')
                    ->live(onBlur: true),
            ])
            ->statePath('data');
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send SMS')
                ->icon('heroicon-o-paper-airplane')
                ->action('sendSms')
                ->requiresConfirmation()
                ->modalHeading('Confirm SMS Send')
                ->modalDescription(function () {
                    $data = $this->form->getState();
                    return isset($data['isBulk']) && $data['isBulk'] 
                        ? 'Are you sure you want to send this SMS to ' . count($data['recipients'] ?? []) . ' recipients?' 
                        : 'Are you sure you want to send this SMS?';
                })
                ->modalSubmitActionLabel('Yes, send it'),
                
            Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->action('resetForm'),
        ];
    }
    
    public function sendSms(): void
    {
        $data = $this->form->getState();
        
        try {
            if (isset($data['isBulk']) && $data['isBulk']) {
                $result = Sms::to($data['recipients'])
                    ->message($data['message'])
                    ->sendBulk();
                    
                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('Bulk SMS sent to ' . count($data['recipients']) . ' recipients.')
                    ->success()
                    ->send();
            } else {
                $result = Sms::to($data['recipient'])
                    ->message($data['message'])
                    ->send();
                    
                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('SMS sent to ' . $data['recipient'])
                    ->success()
                    ->send();
            }
            
            $this->resetForm();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('SMS Send Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function resetForm(): void
    {
        $this->form->fill([
            'recipient' => '',
            'recipients' => [],
            'message' => '',
        ]);
    }
}
