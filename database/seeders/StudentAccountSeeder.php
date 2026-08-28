<?php

namespace Database\Seeders;

use App\Models\UserAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentAccountSeeder extends Seeder
{
    private const EMAIL = 'mbmargas@up.edu.ph';

    public function run(): void
    {
        $student = UserAccount::withTrashed()
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->first();

        if ($student === null) {
            $student = UserAccount::create([
                'username' => 'mbmargas',
                'email' => self::EMAIL,
                'password' => Hash::make(Str::random(64)),
                'first_name' => 'MB',
                'last_name' => 'Margas',
                'user_type' => 'student',
                'roles' => [],
                'account_status' => 'active',
            ]);
        } else {
            if ($student->trashed()) {
                $student->restore();
            }

            $student->forceFill([
                'email' => self::EMAIL,
                'user_type' => 'student',
                'roles' => [],
                'account_status' => 'active',
            ])->save();
        }

        $this->command?->info("Student account ready: {$student->email}");
    }
}
