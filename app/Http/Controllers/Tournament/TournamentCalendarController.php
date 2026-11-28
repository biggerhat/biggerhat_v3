<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * "Add to calendar": a one-event iCalendar file for a tournament (all-day on its date, or timed when the organizer set a start time and zone), with the venue and a link back to the page. Public, like the page.
 */
class TournamentCalendarController extends Controller
{
    public function ics(Tournament $tournament): Response
    {
        $date = $tournament->event_date;
        $url = route('tournaments.view', $tournament);
        $description = trim(($tournament->description ? $tournament->description."\n\n" : '').$tournament->rulesetDefinition()->label."\n".$url);

        // With a start time and zone the event is timed (the length is a guess: a day of Swiss rounds runs long); otherwise all-day.
        $timed = $tournament->start_time && $tournament->timezone;
        $start = $timed ? Carbon::parse($date->format('Y-m-d').' '.$tournament->start_time, $tournament->timezone) : null;
        $when = $start
            ? ['DTSTART;TZID='.$tournament->timezone.':'.$start->format('Ymd\THis'), 'DTEND;TZID='.$tournament->timezone.':'.$start->copy()->addHours(8)->format('Ymd\THis')]
            : ['DTSTART;VALUE=DATE:'.$date->format('Ymd'), 'DTEND;VALUE=DATE:'.$date->copy()->addDay()->format('Ymd')];

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BiggerHat//Tournament Tracker//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$tournament->uuid.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            ...$when,
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
