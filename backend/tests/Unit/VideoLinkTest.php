<?php

namespace Tests\Unit;

use App\Support\VideoLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoLinkTest extends TestCase
{
    public static function links(): array
    {
        return [
            'youtu.be' => ['https://youtu.be/abcdefghijk', 'youtube', 'abcdefghijk'],
            'watch' => ['https://www.youtube.com/watch?v=abcdefghijk&t=30s', 'youtube', 'abcdefghijk'],
            'shorts' => ['https://youtube.com/shorts/abcdefghijk', 'youtube', 'abcdefghijk'],
            'drive view' => ['https://drive.google.com/file/d/1PfYC-E3CFt20HXzr8SSabcdEFGH/view?usp=sharing', 'drive', '1PfYC-E3CFt20HXzr8SSabcdEFGH'],
            'drive user path' => ['https://drive.google.com/file/u/0/d/19EqHWgDKG2s6_iORipCabcd/view', 'drive', '19EqHWgDKG2s6_iORipCabcd'],
            'drive open' => ['https://drive.google.com/open?id=19EqHWgDKG2s6_iORipCabcd', 'drive', '19EqHWgDKG2s6_iORipCabcd'],
        ];
    }

    #[DataProvider('links')]
    public function test_it_reads_youtube_and_drive_links(string $url, string $provider, string $id): void
    {
        $this->assertSame(['provider' => $provider, 'id' => $id], VideoLink::parse($url));
    }

    public function test_other_links_are_refused(): void
    {
        $this->assertNull(VideoLink::parse('https://vimeo.com/123'));
        $this->assertNull(VideoLink::parse('https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv'));
        $this->assertNull(VideoLink::parse('https://example.com/?v=abcdefghijk'));
        $this->assertNull(VideoLink::parse(''));
        $this->assertTrue(VideoLink::isDriveFolder('https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv'));
    }
}
