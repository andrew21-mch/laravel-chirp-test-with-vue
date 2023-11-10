<?php

namespace Database\Seeders;

use App\Models\Chirp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

class ChirpSeeder extends Seeder
{
    public function run(): void
    {
        // Disable actual email sending
        Mail::pretend(true);

        $users = DB::table('users')->pluck('id');

        foreach ($users as $userId) {
            Chirp::factory(10)->create([
                'user_id' => $userId,
                'message' => $this->generateRandomMessage(),
            ]);
        }

        // Re-enable email sending if needed
        Mail::pretend(false);
    }

    private function generateRandomMessage(): string
    {
        $messages = [
            'Hello, world!',
            'I love Laravel!',
            'Chirping away...',
            'Random message here.',
            'Laravel is awesome!',
            'Chirp chirp!',
            'Seeding data...',
            'Building cool apps!',
        ];

        return $messages[array_rand($messages)];
    }
}
