<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SentMessageResource\Pages;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\QueryException;
use Rayzenai\LaravelSms\Facades\Sms;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;

/**
 * "Send SMS" — the create screen of the Sent Messages resource. Submitting the
 * form sends the message via the SMS service (which writes the SentMessage
 * record(s)), so there is no separate persistence step.
 */
class CreateSentMessage extends CreateRecord
{
    protected static string $resource = SentMessageResource::class;

    protected static ?string $title = 'Send SMS';

    public function getBreadcrumb(): string
    {
        return 'Send SMS';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            // The resource create form defaults to a 2-column grid; force a
            // single full-width column so sections stack instead of floating.
            ->columns(1)
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

                        Forms\Components\Select::make('selectedUsers')
                            ->label('Select Users')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Search and select users...')
                            ->hidden(fn (Get $get) => $get('isBulk') !== true || $get('useUsers') !== true)
                            ->options(fn () => $this->userOptions())
                            ->helperText(fn ($state) => $this->getUniquePhoneCount($state ?? []).' unique numbers selected')
                            ->afterStateUpdated(fn ($state, Set $set) => $set('selectAllUsers', count($state ?? []) === $this->getTotalUniqueUsersCount())),

                        Forms\Components\Checkbox::make('selectAllUsers')
                            ->label('Select all unique phone numbers')
                            ->helperText(fn () => 'Available: '.$this->getTotalUniqueUsersCount().' unique numbers from '.$this->getTotalUsersCount().' users')
                            ->live()
                            ->hidden(fn (Get $get) => $get('isBulk') !== true || $get('useUsers') !== true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('selectedUsers', $state ? $this->uniqueUserIds() : []);
                            }),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->placeholder('Type your SMS message here...')
                            ->required()
                            ->maxLength(160)
                            ->rows(4)
                            ->helperText(fn ($state) => strlen($state ?? '').'/160 characters')
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Send SMS')
            ->icon('heroicon-o-paper-airplane');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    /**
     * Sending IS the create step — the service persists the SentMessage row(s).
     */
    public function create(bool $another = false): void
    {
        $data = $this->form->getState();

        try {
            if (! empty($data['isBulk'])) {
                $recipients = $this->getRecipients($data);

                if (empty($recipients)) {
                    Notification::make()
                        ->title('No Recipients')
                        ->body('No valid phone numbers found for the selected recipients.')
                        ->warning()
                        ->send();

                    return;
                }

                $sent = Sms::to($recipients)->message($data['message'])->sendBulk();

                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('Bulk SMS sent to '.$sent->count().' recipients.')
                    ->success()
                    ->send();
            } else {
                Sms::to($data['recipient'])->message($data['message'])->send();

                Notification::make()
                    ->title('SMS Sent Successfully!')
                    ->body('SMS sent to '.$data['recipient'])
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('SMS Send Failed')
                ->body('Error: '.$e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getRecipients(array $data): array
    {
        if (! empty($data['useUsers']) && ! empty($data['selectedUsers'])) {
            return $this->getUserPhoneNumbers($data['selectedUsers']);
        }

        return $data['recipients'] ?? [];
    }

    /**
     * Build the "Select Users" options, collapsing users that share a number.
     *
     * @return array<int|string, string>
     */
    protected function userOptions(): array
    {
        $phoneField = $this->phoneField();
        $nameField = config('laravel-sms.user_model.name_field', 'name');

        $users = $this->usersWithPhone();

        if ($users === null) {
            return [];
        }

        $options = [];

        foreach ($this->groupByPhone($users, $phoneField) as $phone => $group) {
            $first = $group->first();

            $options[$first->id] = $group->count() > 1
                ? $group->pluck($nameField)->join(', ').' ('.$phone.') - '.$group->count().' users'
                : $first->$nameField.' ('.$phone.')';
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    protected function getUserPhoneNumbers(array $userIds): array
    {
        $phoneField = $this->phoneField();
        $userClass = config('laravel-sms.user_model.class');

        if (! $this->userModelUsable()) {
            return [];
        }

        return $this->wherePhoneNotEmpty($userClass::whereIn('id', $userIds), $phoneField)
            ->pluck($phoneField)
            ->map(fn ($phone) => str_starts_with($phone, '+977') ? $phone : '+977'.$phone)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, int|string>
     */
    protected function uniqueUserIds(): array
    {
        $users = $this->usersWithPhone();

        if ($users === null) {
            return [];
        }

        return $this->groupByPhone($users, $this->phoneField())
            ->map(fn ($group) => $group->first()->id)
            ->values()
            ->all();
    }

    protected function getTotalUsersCount(): int
    {
        return $this->usersWithPhone()?->count() ?? 0;
    }

    protected function getTotalUniqueUsersCount(): int
    {
        $users = $this->usersWithPhone();

        return $users === null ? 0 : $this->groupByPhone($users, $this->phoneField())->count();
    }

    protected function getUniquePhoneCount(array $userIds): int
    {
        if (empty($userIds) || ! $this->userModelUsable()) {
            return 0;
        }

        $userClass = config('laravel-sms.user_model.class');
        $users = $this->wherePhoneNotEmpty($userClass::whereIn('id', $userIds), $this->phoneField())->get();

        return $this->groupByPhone($users, $this->phoneField())->count();
    }

    protected function phoneField(): string
    {
        return config('laravel-sms.user_model.phone_field', 'phone');
    }

    protected function userModelUsable(): bool
    {
        return config('laravel-sms.user_model.enabled', false)
            && class_exists((string) config('laravel-sms.user_model.class'));
    }

    /**
     * All users that have a phone number, or null when the user model isn't usable.
     */
    protected function usersWithPhone(): ?\Illuminate\Support\Collection
    {
        if (! $this->userModelUsable()) {
            return null;
        }

        $userClass = config('laravel-sms.user_model.class');

        return $this->wherePhoneNotEmpty($userClass::query(), $this->phoneField())->get();
    }

    protected function groupByPhone(\Illuminate\Support\Collection $users, string $phoneField): \Illuminate\Support\Collection
    {
        return $users->groupBy(function ($user) use ($phoneField) {
            $phone = $user->$phoneField;

            return str_starts_with((string) $phone, '+977') ? $phone : '+977'.$phone;
        });
    }

    /**
     * Non-empty phone filter that works with both string and bigint columns.
     */
    protected function wherePhoneNotEmpty($query, string $phoneField)
    {
        $query = $query->whereNotNull($phoneField);

        try {
            (clone $query)->where($phoneField, '>', 0)->count();

            return $query->where($phoneField, '>', 0);
        } catch (QueryException $e) {
            return $query->where($phoneField, '!=', '')->where($phoneField, '!=', '0');
        }
    }
}
