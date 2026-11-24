<?php

namespace App\Support\BattleReports;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\BattleReport;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Tells the people who moderate battle reports that something needs them: a bell notification for each, and a line to the
 * Discord alert webhook if one is set (BATTLE_REPORT_ALERT_WEBHOOK, falling back to BOT_ALERT_WEBHOOK).
 */
class ModeratorAlerts
{
    /** Everyone who may moderate: users with the permission, and super admins. The permission and role rows come from seeders, so they may not exist yet. */
    public static function moderators(): \Illuminate\Support\Collection
    {
        $withPermission = Permission::where('name', PermissionEnum::ApproveBattleReports->value)->exists()
            ? User::permission(PermissionEnum::ApproveBattleReports->value)->get()
            : collect();
        $superAdmins = Role::where('name', RoleEnum::SuperAdmin->value)->exists() ? User::role(RoleEnum::SuperAdmin->value)->get() : collect();

        return $withPermission->merge($superAdmins)->unique('id')->values();
    }

    /** Notify every moderator except the report's own writer, and post the line to the webhook. */
    public static function send(BattleReport $report, Notification $notification, string $line): void
    {
        foreach (self::moderators()->reject(fn (User $u) => $u->id === $report->user_id) as $moderator) {
            $moderator->notify($notification);
        }
        self::ping($line);
    }

    private static function ping(string $line): void
    {
        $webhook = config('services.battle_reports.alert_webhook') ?: config('services.bot.alert_webhook');
        if (! $webhook) {
            return;
        }
        $content = Str::limit($line.' '.route('admin.battle_reports.index'), 1500, '');
        // After the response, so a slow webhook never slows the writer's save; no mentions, since the line holds a writer's own words.
        dispatch(function () use ($webhook, $content) {
            try {
                Http::timeout(5)->post($webhook, ['content' => $content, 'allowed_mentions' => ['parse' => []]]);
            } catch (Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}
