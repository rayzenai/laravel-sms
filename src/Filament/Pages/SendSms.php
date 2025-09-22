<?php

namespace Rayzenai\LaravelSms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Rayzenai\LaravelSms\Facades\Sms;

class SendSms extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'laravel-sms::filament.pages.send-sms';

    protected static ?string $navigationLabel = 'Send SMS';

    protected static string|\UnitEnum|null $navigationGroup = 'SMS Management';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function mount(): void
    {
        $this->form->fill([
            'isBulk' => false,
            'useUsers' => false,
            'recipient' => '',
            'recipients' => [],
            'selectedUsers' => [],
            'selectAllUsers' => false,
            'message' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('isBulk')
                            ->label('Bulk SMS')
                            ->helperText('Send to multiple recipients')
                            ->live()
                            ->default(false)
                            ->columnSpan(1),
                        
                        Forms\Components\Toggle::make('useUsers')
                            ->label('Select from Users')
                            ->helperText('Choose from existing users')
                            ->live()
                            ->default(false)
                            ->columnSpan(1)
                            ->hidden(fn (Get $get) => $get('isBulk') !== true)
                            ->visible(fn () => config('laravel-sms.user_model.enabled', false)),
                    ]),

                Section::make()
                    ->schema([
                        // Single recipient field
                        Forms\Components\TextInput::make('recipient')
                            ->label('Phone Number')
                            ->prefix('+977')
                            ->placeholder('9801234567')
                            ->tel()
                            ->required()
                            ->maxLength(10)
                            ->minLength(10)
                            ->numeric()
                            ->rules(['regex:/^9[0-9]{9}$/'])
                            ->hidden(fn (Get $get) => $get('isBulk') === true)
                            ->helperText('Enter 10-digit mobile number without +977')
                            ->dehydrateStateUsing(fn ($state) => $state ? '+977'.$state : null),

                        // Manual phone number input for bulk
                        Forms\Components\TagsInput::make('recipients')
                            ->label('Phone Numbers')
                            ->placeholder('Type number and press Enter (e.g., 9801234567)')
                            ->required()
                            ->helperText('Add multiple 10-digit numbers without +977')
                            ->hidden(fn (Get $get) => $get('isBulk') !== true || $get('useUsers') === true)
                            ->nestedRecursiveRules([
                                'string',
                                'regex:/^9[0-9]{9}$/',
                            ])
                            ->dehydrateStateUsing(fn ($state) => array_map(fn ($number) => '+977'.$number, $state ?? [])),

                        // User selection for bulk
                        Forms\Components\Select::make('selectedUsers')
                            ->label('Select Users')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Search and select users...')
                            ->hidden(fn (Get $get) => $get('isBulk') !== true || $get('useUsers') !== true)
                            ->options(function () {
                                if (! config('laravel-sms.user_model.enabled', false)) {
                                    return [];
                                }

                                $userClass = config('laravel-sms.user_model.class');
                                $phoneField = config('laravel-sms.user_model.phone_field', 'phone');
                                $nameField = config('laravel-sms.user_model.name_field', 'name');

                                if (! class_exists($userClass)) {
                                    return [];
                                }

                                // Get all users with phone numbers
                                $users = $userClass::whereNotNull($phoneField)
                                    ->where($phoneField, '>', 0)
                                    ->get();

                                // Group users by phone number to find duplicates
                                $phoneGroups = $users->groupBy(function ($user) use ($phoneField) {
                                    $phone = $user->$phoneField;
                                    // Normalize phone number for grouping
                                    return str_starts_with($phone, '+977') ? $phone : '+977'.$phone;
                                });

                                $options = [];
                                $seenPhones = [];

                                foreach ($phoneGroups as $phone => $usersWithSamePhone) {
                                    if (count($usersWithSamePhone) > 1) {
                                        // Multiple users with same phone - show only first with count
                                        $firstUser = $usersWithSamePhone->first();
                                        $names = $usersWithSamePhone->pluck($nameField)->join(', ');
                                        $options[$firstUser->id] = $names . ' (' . $phone . ') - ' . count($usersWithSamePhone) . ' users';
                                    } else {
                                        // Single user with this phone
                                        $user = $usersWithSamePhone->first();
                                        $options[$user->id] = $user->$nameField . ' (' . $phone . ')';
                                    }
                                }

                                return $options;
                            })
                            ->helperText(fn ($state) => $this->getUniquePhoneCount($state ?? []) . ' unique numbers selected')
                            ->afterStateUpdated(fn ($state, Set $set) => $set('selectAllUsers', count($state ?? []) === $this->getTotalUniqueUsersCount())),

                        Forms\Components\Checkbox::make('selectAllUsers')
                            ->label('Select all unique phone numbers')
                            ->helperText(fn () => 'Available: '.$this->getTotalUniqueUsersCount().' unique numbers from '.$this->getTotalUsersCount().' users')
                            ->live()
                            ->hidden(fn (Get $get) => $get('isBulk') !== true || $get('useUsers') !== true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $userClass = config('laravel-sms.user_model.class');
                                    $phoneField = config('laravel-sms.user_model.phone_field', 'phone');

                                    // Get all users and group by phone to get only unique numbers
                                    $users = $userClass::whereNotNull($phoneField)
                                        ->where($phoneField, '>', 0)
                                        ->get();

                                    $phoneGroups = $users->groupBy(function ($user) use ($phoneField) {
                                        $phone = $user->$phoneField;
                                        return str_starts_with($phone, '+977') ? $phone : '+977'.$phone;
                                    });

                                    // Select only the first user ID for each unique phone number
                                    $uniqueUserIds = [];
                                    foreach ($phoneGroups as $phone => $usersWithSamePhone) {
                                        $uniqueUserIds[] = $usersWithSamePhone->first()->id;
                                    }

                                    $set('selectedUsers', $uniqueUserIds);
                                } else {
                                    $set('selectedUsers', []);
                                }
                            }),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->placeholder('Type your SMS message here...')
                            ->required()
                            ->maxLength(160)
                            ->rows(4)
                            ->helperText(fn ($state) => strlen($state ?? '').'/160 characters')
                            ->live(onBlur: true)
                            ->columnSpan('full'),
                    ])
                    ->columns(1),
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
                        ? 'Are you sure you want to send this SMS to '.count($data['recipients'] ?? []).' recipients?'
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
                // Get recipients based on selection method
                $recipients = $this->getRecipients($data);

                if (empty($recipients)) {
                    Notification::make()
                        ->title('No Recipients')
                        ->body('No valid phone numbers found for the selected recipients.')
                        ->warning()
                        ->send();

                    return;
                }

                $result = Sms::to($recipients)
                    ->message($data['message'])
                    ->sendBulk();

                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('Bulk SMS sent to '.count($recipients).' recipients.')
                    ->success()
                    ->send();
            } else {
                $result = Sms::to($data['recipient'])
                    ->message($data['message'])
                    ->send();

                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('SMS sent to '.$data['recipient'])
                    ->success()
                    ->send();
            }

            $this->resetForm();

        } catch (\Exception $e) {
            Notification::make()
                ->title('SMS Send Failed')
                ->body('Error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetForm(): void
    {
        $currentIsBulk = $this->data['isBulk'] ?? false;
        $currentUseUsers = $this->data['useUsers'] ?? false;

        $this->form->fill([
            'isBulk' => $currentIsBulk,
            'useUsers' => $currentUseUsers,
            'recipient' => '',
            'recipients' => [],
            'selectedUsers' => [],
            'selectAllUsers' => false,
            'message' => '',
        ]);
    }

    protected function getRecipients(array $data): array
    {
        if (isset($data['useUsers']) && $data['useUsers'] && ! empty($data['selectedUsers'])) {
            return $this->getUserPhoneNumbers($data['selectedUsers']);
        }

        return $data['recipients'] ?? [];
    }

    protected function getUserPhoneNumbers(array $userIds): array
    {
        if (! config('laravel-sms.user_model.enabled', false)) {
            return [];
        }

        $userClass = config('laravel-sms.user_model.class');
        $phoneField = config('laravel-sms.user_model.phone_field', 'phone');

        if (! class_exists($userClass)) {
            return [];
        }

        $phoneNumbers = $userClass::whereIn('id', $userIds)
            ->whereNotNull($phoneField)
            ->where($phoneField, '>', 0)
            ->pluck($phoneField)
            ->map(function ($phone) {
                // Add +977 prefix if not already present
                if (! str_starts_with($phone, '+977')) {
                    return '+977'.$phone;
                }

                return $phone;
            })
            ->unique() // Remove duplicate phone numbers
            ->values()
            ->toArray();

        return $phoneNumbers;
    }

    protected function getTotalUsersCount(): int
    {
        if (! config('laravel-sms.user_model.enabled', false)) {
            return 0;
        }

        $userClass = config('laravel-sms.user_model.class');
        $phoneField = config('laravel-sms.user_model.phone_field', 'phone');

        if (! class_exists($userClass)) {
            return 0;
        }

        return $userClass::whereNotNull($phoneField)
            ->where($phoneField, '>', 0)
            ->count();
    }

    protected function getTotalUniqueUsersCount(): int
    {
        if (! config('laravel-sms.user_model.enabled', false)) {
            return 0;
        }

        $userClass = config('laravel-sms.user_model.class');
        $phoneField = config('laravel-sms.user_model.phone_field', 'phone');

        if (! class_exists($userClass)) {
            return 0;
        }

        $users = $userClass::whereNotNull($phoneField)
            ->where($phoneField, '>', 0)
            ->get();

        // Normalize and count unique phone numbers
        $uniquePhones = $users->map(function ($user) use ($phoneField) {
            $phone = $user->$phoneField;
            return str_starts_with($phone, '+977') ? $phone : '+977'.$phone;
        })->unique();

        return $uniquePhones->count();
    }

    protected function getUniquePhoneCount(array $userIds): int
    {
        if (empty($userIds) || ! config('laravel-sms.user_model.enabled', false)) {
            return 0;
        }

        $userClass = config('laravel-sms.user_model.class');
        $phoneField = config('laravel-sms.user_model.phone_field', 'phone');

        if (! class_exists($userClass)) {
            return 0;
        }

        $users = $userClass::whereIn('id', $userIds)
            ->whereNotNull($phoneField)
            ->where($phoneField, '>', 0)
            ->get();

        // Normalize and count unique phone numbers
        $uniquePhones = $users->map(function ($user) use ($phoneField) {
            $phone = $user->$phoneField;
            return str_starts_with($phone, '+977') ? $phone : '+977'.$phone;
        })->unique();

        return $uniquePhones->count();
    }
}
