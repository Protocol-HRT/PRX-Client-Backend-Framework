<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Quick "send password reset" button — generates a fresh
            // temporary password, copies it to the admin via toast. Use
            // when the official mailer isn't wired or for one-off resets
            // on accounts the admin has no email channel for.
            Action::make('resetPassword')
                ->label('Reset password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()
                ->modalDescription('A new temporary password will be generated and shown once. Copy it before closing.')
                ->action(function (User $record) {
                    $temp = Str::password(16, symbols: false);
                    $record->forceFill(['password' => Hash::make($temp)])->save();

                    Notification::make()
                        ->title('Temporary password set for '.$record->email)
                        ->body('Copy now — it will not be shown again: '.$temp)
                        ->warning()
                        ->persistent()
                        ->send();
                }),

            // Self-deletion guard — admin can't delete their own account
            // by accident.
            DeleteAction::make()
                ->visible(fn (User $record) => $record->id !== auth()->id()),
        ];
    }
}
