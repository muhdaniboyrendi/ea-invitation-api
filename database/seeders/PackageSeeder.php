<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Package::create([
            'name' => 'Economy',
            'price' => 100000,
            'features' => [
                '200 tamu undangan + grup',
                '4 foto galeri (max)',
                'Informasi acara',
                'Background musik (list)',
                'Timer countdown',
                'Maps lokasi',
                'Story',
                'RSVP',
                'Ucapan tamu',
                '1 bulan masa aktif'
            ]
        ]);

        Package::create([
            'name' => 'Premium',
            'price' => 150000,
            'features' => [
                '500 tamu undangan + grup',
                '10 foto galeri (max)',
                '1 video',
                'Informasi acara',
                'Background musik custom',
                'Timer countdown',
                'Maps lokasi',
                'Tambah ke kalender',
                'Story',
                'RSVP',
                'Ucapan tamu',
                'Kirim hadiah',
                '6 bulan masa aktif'
            ]
        ]);

        Package::create([
            'name' => 'Business',
            'price' => 200000,
            'features' => [
                '1000 tamu undangan + grup',
                '20 foto galeri (max)',
                '5 video (max)',
                'Informasi acara',
                'Background musik custom',
                'Timer countdown',
                'Maps lokasi',
                'Tambah ke kalender',
                'Story',
                'RSVP',
                'Ucapan tamu',
                'Kirim hadiah',
                '6 bulan masa aktif'
            ]
        ]);
        
        Package::create([
            'name' => 'First Class',
            'price' => 300000,
            'features' => [
                'Unlimited tamu undangan + grup',
                'Unlimited foto galeri (max)',
                'Unlimited video (max)',
                'Informasi acara',
                'Background musik custom',
                'Timer countdown',
                'Maps lokasi',
                'Tambah ke kalender',
                'Story',
                'RSVP',
                'Ucapan tamu',
                'Kirim hadiah',
                '1 bulan masa aktif',
                'Custom domain',
            ]
        ]);
    }
}
