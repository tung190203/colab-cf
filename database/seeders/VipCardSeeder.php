<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VipCard;

class VipCardSeeder extends Seeder
{
    public function run()
    {
        $lastCardNumber = VipCard::max('card_number') ?? 0;

        for ($i = 1; $i <= 100; $i++) {
            VipCard::create([
                'card_number' => $lastCardNumber + $i,
                'user_id' => null,
                'card_type' => null,
                'expiry_date' => null,
            ]);
        }
    }
}
