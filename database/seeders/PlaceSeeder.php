<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Menanam 2 lokasi yang dipantau.
 *
 * Catatan: `serpapi_data_id` / `serpapi_place_id` sengaja dibiarkan kosong
 * (diisi via UI setelah Anda punya ID dari Google Maps/SerpApi). Selama kosong,
 * tempat ini BELUM bisa diambil otomatis — tapi tetap tampil di UI.
 */
class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            [
                'name' => 'Rumah Makan Handayani Paiton',
                'type' => Place::TYPE_RESTAURANT,
                'query' => 'Rumah Makan Handayani Paiton',
                'serpapi_data_id' => env('SERPAPI_DATA_ID_RM_HANDAYANI'),
                'serpapi_place_id' => env('SERPAPI_PLACE_ID_RM_HANDAYANI'),
                'is_active' => true,
                'note' => 'Restoran — rating dipantau dari Google Maps.',
            ],
            [
                'name' => 'Cottage Wisata Paiton',
                'type' => Place::TYPE_COTTAGE,
                'query' => 'Cottage Wisata Paiton',
                'serpapi_data_id' => env('SERPAPI_DATA_ID_COTTAGE_PAITON'),
                'serpapi_place_id' => env('SERPAPI_PLACE_ID_COTTAGE_PAITON'),
                'is_active' => true,
                'note' => 'Cottage/penginapan — rating dipantau dari Google Maps.',
            ],
        ];

        foreach ($places as $data) {
            Place::updateOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
