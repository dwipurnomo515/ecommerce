<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Admin rights are granted from the server only, never through a web form,
 * so a compromised account cannot promote itself.
 */
#[Signature('app:make-admin {email : Email of an existing user} {--revoke : Remove admin rights instead}')]
#[Description('Grant (or revoke) marketplace admin rights for a user')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $user->is_admin = ! $this->option('revoke');
        $user->save();

        $this->info($user->is_admin
            ? "{$user->email} is now an admin."
            : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
