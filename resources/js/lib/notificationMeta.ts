import { Award, Bell, CalendarCheck, Flag, Swords, Trophy, UserCheck, UserPlus, Users, type LucideIcon } from 'lucide-vue-next';

/** Icon shown next to a notification, keyed by the `type` each Notification class stores. */
const ICONS: Record<string, LucideIcon> = {
    friend_request_received: UserPlus,
    friend_request_accepted: UserCheck,
    game_opponent_joined: Swords,
    tournament_organizer_added: Trophy,
    tournament_player_registered: Users,
    tournament_round_paired: Flag,
    campaign_invitation_received: CalendarCheck,
    campaign_game_started: Swords,
    custom_card_moderated: Award,
};

export function notificationIcon(type: string): LucideIcon {
    return ICONS[type] ?? Bell;
}
