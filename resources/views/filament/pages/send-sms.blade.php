<x-filament-panels::page>
    <x-filament-panels::form wire:submit="sendSms">
        {{ $this->form }}
        
        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>
    
    <x-filament-panels::section class="mt-6">
        <x-slot name="heading">
            SMS Sending Guidelines
        </x-slot>
        
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <p>• Messages are limited to 160 characters</p>
            <p>• Phone numbers must be in Nepali format (+977 followed by 10 digits)</p>
            <p>• Bulk SMS allows sending to multiple recipients at once</p>
            <p>• All SMS activity is logged for tracking purposes</p>
        </div>
    </x-filament-panels::section>
</x-filament-panels::page>
