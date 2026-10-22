<?php
/**
 * Proposed Spreaker metadata from the existing YouTube sermon catalog.
 * No external calls, uploads, or publishing are performed by this module.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_podcast_episode_preview($youtube_title, $sermon_date, $youtube_id) {
    $original = trim((string) $youtube_title);
    $without_date = preg_replace(
        '/\s+[-–—]\s+\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December),?\s+\d{2,4}$/iu',
        '', $original
    );
    $base = trim($without_date);
    $week = '';
    if (preg_match('/^(.*?)\s+[-–—]\s+Week\s+(\d{1,2})$/iu', $base, $match)) {
        $base = trim($match[1]);
        $week = (string) ((int) $match[2]);
    }
    $parts = preg_split('/\s+[-–—]\s+/u', $base);
    $message = trim((string) ($parts[0] ?? ''));
    $series_parts = count($parts) > 1 ? array_slice($parts, 1) : array();
    $series = count($series_parts) > 1
        ? trim(array_shift($series_parts)) . ': ' . implode(' - ', $series_parts)
        : trim((string) ($series_parts[0] ?? ''));
    $recognized = $week !== '' && $message !== '' && $series !== '';
    $title = $recognized ? $message . ' | ' . $series . ' (Week ' . $week . ')' : $original;

    $date_text = '';
    $date = is_string($sermon_date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $sermon_date) : false;
    if ($date && $date->format('Y-m-d') === $sermon_date) {
        $date_text = $date->format('F j, Y');
    }
    $valid_id = is_string($youtube_id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $youtube_id);
    $description = '"' . ($recognized ? $message : $original) . '" — a sermon from Surfside Community Fellowship.';
    if ($series !== '') $description .= "\nSeries: " . $series;
    if ($week !== '') $description .= "\nWeek: " . $week;
    if ($date_text !== '') $description .= "\nSermon date: " . $date_text;
    if ($valid_id) $description .= "\n\nWatch on YouTube: https://www.youtube.com/watch?v=" . $youtube_id;
    $description .= "\nLearn more: https://surfsidefellowship.org";

    return array(
        'title' => $title,
        'description' => $description,
        'needs_review' => !$recognized || $date_text === '' || !$valid_id || strlen($title) > 140,
    );
}
