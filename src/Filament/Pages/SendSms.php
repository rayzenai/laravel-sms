<?php

namespace Rayzenai\LaravelSms\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Rayzenai\LaravelSms\Facades\Sms;
use Rayzenai\LaravelSms\Rules\NepaliPhoneNumber;

class SendSms extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static string $view = 'laravel-sms::filament.pages.send-sms';
    
    protected static ?string $navigationLabel = 'Send SMS';
    
    protected static ?string $navigationGroup = 'SMS Management';
    
    protected static ?int $navigationSort = 1;
    
    public ?string $recipient = '';
    
    public ?array $recipients = [];
    
    public ?string $message = '';
    
    public bool $isBulk = false;
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('isBulk')
                    ->label('Send Bulk SMS')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetForm()),
                    
                Forms\Components\TextInput::make('recipient')
                    ->label('Recipient Phone Number')
                    ->placeholder('+977 9801234567')
                    ->tel()
                    ->required()
                    ->rules([new NepaliPhoneNumber()])
                    ->visible(fn () => !$this->isBulk)
                    ->helperText('Enter a valid Nepali phone number starting with +977'),
                    
                Forms\Components\TagsInput::make('recipients')
                    ->label('Recipients Phone Numbers')
                    ->placeholder('Add phone numbers...')
                    ->required()
                    ->visible(fn () => $this->isBulk)
                    ->helperText('Enter valid Nepali phone numbers starting with +977')
                    ->nestedRecursiveRules([
                        'string',
                        new NepaliPhoneNumber(),
                    ]),
                    
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
                ->modalDescription(fn () => $this->isBulk 
                    ? 'Are you sure you want to send this SMS to ' . count($this->recipients) . ' recipients?' 
                    : 'Are you sure you want to send this SMS?')
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
            if ($this->isBulk) {
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
