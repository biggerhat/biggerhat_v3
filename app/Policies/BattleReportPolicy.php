<?php

namespace App\Policies;

use App\Enums\BattleReportStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\BattleReport;
use App\Models\BattleReportComment;
use App\Models\User;

class BattleReportPolicy
{
    /**
     * An approved report is for anyone; otherwise only its writer, and a moderator for a report the writer asked to make public
     * (a private report stays private even from moderators).
     */
    public function view(?User $user, BattleReport $report): bool
    {
        if ($report->isPublic()) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        return $report->user_id === $user->id || ($report->status !== BattleReportStatusEnum::Private && $this->moderate($user, $report));
    }

    /** Approve or turn down a report: a user with the permission, or a super admin; never the writer's own (a super admin may). */
    public function moderate(User $user, BattleReport $report): bool
    {
        $superAdmin = $user->hasRole(RoleEnum::SuperAdmin->value);
        if (! $superAdmin && ! $user->can(PermissionEnum::ApproveBattleReports->value)) {
            return false;
        }

        return $report->user_id !== $user->id || $superAdmin;
    }

    /** A signed-in reader may report a public report that is not their own. */
    public function flag(User $user, BattleReport $report): bool
    {
        return $report->isPublic() && $report->user_id !== $user->id;
    }

    /** The writer may ask for a second look at a report that was turned down, once. */
    public function appeal(User $user, BattleReport $report): bool
    {
        return $report->user_id === $user->id && $report->status === BattleReportStatusEnum::Rejected && $report->appeal_note === null;
    }

    /** Any signed-in reader may comment on a public report whose writer has comments on. */
    public function comment(User $user, BattleReport $report): bool
    {
        return $report->isPublic() && $report->allow_comments;
    }

    /** A comment may be removed by whoever wrote it, by the report's writer, or by a moderator. */
    public function deleteComment(User $user, BattleReport $report, BattleReportComment $comment): bool
    {
        return $comment->user_id === $user->id || $report->user_id === $user->id || $this->moderate($user, $report);
    }

    public function update(User $user, BattleReport $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function delete(User $user, BattleReport $report): bool
    {
        return $this->update($user, $report);
    }
}
