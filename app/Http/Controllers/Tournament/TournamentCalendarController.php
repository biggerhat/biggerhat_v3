<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * "Add to calendar": a one-event iCalendar file for a tournament (an all-day event on its date — the tracker
 * records a day, not a start time), with the venue and a link back to the page. Public, like the page.
 */
class TournamentCalendarController extends Controller
{
    public function ics(Tournament $tournament): Response
    {
        $date = $tournament->event_date;
        $url = route('tournaments.view', $tournament);
        $description = trim(($tournament->description ? $tournament->description."\n\n" : '').$tournament->rulesetDefinition()->label."\n".$url);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BiggerHat//Tournament Tracker//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$tournament->uuid.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
            'DTEND;VALUE=DATE:'.$date->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape($tournament->name),
            ...($tournament->location ? ['LOCATION:'.$this->escape($tournament->location)] : []),
            'DESCRIPTION:'.$this->escape($description),
            'URL:'.$url,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", array_map($this->fold(...), $lines))."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($tournament->name).'.ics"',
        ]);
    }

    /** RFC 5545 text escaping. */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'], $text);
    }

    /** RFC 5545 line folding: lines longer than 75 octets continue on the next line after a space (never splitting a UTF-8 character). */
    private function fold(string $line): string
    {
        $out = '';
        $chunk = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($chunk.$char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$chunk;
                $chunk = '';
            }
            $chunk .= $char;
        }

        return $out === '' ? $chunk : $out."\r\n ".$chunk;
    }
}
