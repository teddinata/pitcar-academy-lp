<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseOffer;
use Illuminate\Database\Seeder;

/**
 * The two online classes sold on the landing page, and the bundle offered at
 * checkout: buying one recommends the other for Rp79.000 more.
 *
 * Slugs match the page URLs, tracking ids match the content_ids the sales
 * pages already send to the ad pixels, and prices match src/content.config.ts.
 *
 * Safe to run again: rows that already exist keep any price or text an admin
 * changed; only a missing tracking id is filled in.
 */
class LmsCourseSeeder extends Seeder
{
    public const ADDON_PRICE = 79000;

    public function run(): void
    {
        $courses = [
            'ac' => ['slug' => 'kelas-online-ac', 'tracking_id' => 'kelas-online-ac', 'title' => 'Kelas Dasar Pemeriksaan & Perawatan AC Mobil', 'price' => 99000, 'strike_price' => 499000],
            'inspeksi' => ['slug' => 'kelas-online-inspeksi', 'tracking_id' => 'kelas-inspeksi-mobil-bekas', 'title' => 'Kelas Inspeksi Mobil Bekas', 'price' => 99000, 'strike_price' => 499000],
        ];

        $saved = [];

        foreach ($courses as $key => $data) {
            $course = Course::firstOrCreate(['slug' => $data['slug']], $data + ['status' => CourseStatus::Published]);

            if ($course->tracking_id === null) {
                $course->update(['tracking_id' => $data['tracking_id']]);
            }

            $saved[$key] = $course;
        }

        $offers = [
            ['from' => 'ac', 'to' => 'inspeksi', 'headline' => 'Mau beli mobil bekas? Pelajari cara memeriksanya sebelum membayar.'],
            ['from' => 'inspeksi', 'to' => 'ac', 'headline' => 'AC mobil kurang dingin? Pelajari cara memeriksanya sendiri.'],
        ];

        foreach ($offers as $offer) {
            CourseOffer::firstOrCreate(
                ['course_id' => $saved[$offer['from']]->id, 'offer_course_id' => $saved[$offer['to']]->id],
                ['price' => self::ADDON_PRICE, 'headline' => $offer['headline'], 'is_active' => true, 'sort' => 0],
            );
        }
    }
}
