<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Menanam 2 lokasi yang dipantau (dengan data_id hasil resolve link Maps).
 *
 * Mode default `manual` agar TIDAK ada pengambilan otomatis saat pertama kali
 * (hemat kuota). Developer dapat mengubah ke `scheduled` dari UI.
 */
class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            [
                'name' => 'RM Handayani - Wisata Paiton',
                'maps_url' => env('MAPS_URL_RM_HANDAYANI', 'https://maps.app.goo.gl/8HeX848jxD6rHFFG9'),
                'latitude' => -7.718079,
                'longitude' => 113.5370401,
                'type' => Place::TYPE_RESTAURANT,
                'query' => 'RM Handayani Wisata Paiton',
                'serpapi_data_id' => env('SERPAPI_DATA_ID_RM_HANDAYANI'),
                'serpapi_place_id' => env('SERPAPI_PLACE_ID_RM_HANDAYANI'),
                'is_active' => true,
                'analysis_mode' => Place::MODE_MANUAL,
                'schedule_interval_days' => 1,
                'schedule_hour' => 2,
                'note' => 'Restoran — rating dipantau dari Google Maps.',
            ],
            [
                'name' => 'Wisata Paiton Cottage',
                'maps_url' => env('MAPS_URL_COTTAGE_PAITON', 'https://maps.app.goo.gl/khrSGPHrRpJrzUhC6'),
                'latitude' => -7.7171496,
                'longitude' => 113.5371635,
                'type' => Place::TYPE_COTTAGE,
                'query' => 'Wisata Paiton Cottage',
                'serpapi_data_id' => env('SERPAPI_DATA_ID_COTTAGE_PAITON'),
                'serpapi_place_id' => env('SERPAPI_PLACE_ID_COTTAGE_PAITON'),
                'is_active' => true,
                'analysis_mode' => Place::MODE_MANUAL,
                'schedule_interval_days' => 1,
                'schedule_hour' => 2,
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
