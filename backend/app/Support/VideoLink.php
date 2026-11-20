<?php

namespace App\Support;

/**
 * Reads a lesson's video link. YouTube videos play through YouTube's player,
 * which reports how far a student watched. Google Drive files play in Drive's
 * own preview frame, which reports nothing, so those lessons are marked done
 * by the student.
 */
class VideoLink
{
    public const YOUTUBE = 'youtube';

    public const DRIVE = 'drive';

    /** @return array{provider: string, id: string}|null */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // youtu.be/ID, watch?v=ID, embed/ID, shorts/ID
        if (preg_match('~(?:youtube\.com|youtu\.be)/~i', $url) && preg_match('~(?:youtu\.be/|[?&]v=|embed/|shorts/|live/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $url, $m)) {
            return ['provider' => self::YOUTUBE, 'id' => $m[1]];
        }

        // drive.google.com/file/d/ID/view, /file/u/0/d/ID, open?id=ID, uc?id=ID
        if (preg_match('~^https?://(?:drive|docs)\.google\.com/~i', $url)
            && preg_match('~(?:/file/(?:u/\d+/)?d/|[?&]id=)([A-Za-z0-9_-]{20,})~', $url, $m)) {
            return ['provider' => self::DRIVE, 'id' => $m[1]];
        }

        return null;
    }

    public static function isDriveFolder(?string $url): bool
    {
        return (bool) preg_match('~drive\.google\.com/.*folders/~i', (string) $url);
    }
}
