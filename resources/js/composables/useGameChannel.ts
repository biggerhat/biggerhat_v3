import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

interface PresenceMember {
    id: number;
    name: string;
}

export function useGameChannel(gameUuid: string, isObserver: boolean = false) {
    const onlineMembers = ref<PresenceMember[]>([]);
    const opponentOnline = ref(false);
    let channel: any = null;
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingOnly: Set<string> = new Set();

    // Debounced reload — merges multiple rapid events into one request
    const reload = (only: string[]) => {
        only.forEach((k) => pendingOnly.add(k));
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            router.reload({ only: [...pendingOnly], preserveScroll: true });
            pendingOnly = new Set();
            reloadTimer = null;
        }, 150);
    };

    const addListeners = (ch: any) => {
        ch.listen('.GamePlayerJoined', (e: any) => {
            if (import.meta.env.DEV) console.log('[GameChannel] Event: GamePlayerJoined', e);
            reload(['game']);
        })
            .listen('.GameStatusChanged', (e: any) => {
                if (import.meta.env.DEV) console.log('[GameChannel] Event: GameStatusChanged', e);
                const status = e.status;
                // Targeted reload based on what the new status needs
                const base = ['game'];
                if (status === 'faction_select' || status === 'master_select') {
                    reload([...base, 'masters', 'my_crews']);
                } else if (status === 'crew_select') {
                    reload([...base, 'masters', 'my_crews']);
                } else if (status === 'scheme_select') {
                    reload([...base, 'schemes', 'deployment', 'current_schemes']);
                } else if (status === 'in_progress') {
                    reload([
                        ...base,
                        'schemes',
                        'deployment',
                        'current_schemes',
                        'next_schemes',
                        'opponent_next_schemes',
                        'opponent_scheme_intel',
                        'observer_scheme_intel',
                        'tokens',
                        'character_upgrades',
                        'all_markers',
                    ]);
                } else if (status === 'completed' || status === 'abandoned') {
                    reload([...base, 'current_schemes', 'starting_crews']);
                } else {
                    reload(base);
                }
            })
            .listen('.GameSetupStepCompleted', (e: any) => {
                if (import.meta.env.DEV) console.log('[GameChannel] Event: GameSetupStepCompleted', e);
                // Setup steps can trigger status changes (both factions done → MasterSelect, etc.)
                // Reload masters and crews since they depend on game status
                reload(['game', 'masters', 'my_crews']);
            })
            .listen('.GameCrewMemberUpdated', (e: any) => {
                if (import.meta.env.DEV) console.log('[GameChannel] Event: GameCrewMemberUpdated', e);
                // turn_scored and mark_complete can change schemes and game state
                if (e.action === 'turn_scored' || e.action === 'mark_complete' || e.action === 'cancel_complete') {
                    reload(['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel', 'observer_scheme_intel']);
                } else {
                    reload(['game']);
                }
            })
            .listen('.GameTurnAdvanced', (e: any) => {
                if (import.meta.env.DEV) console.log('[GameChannel] Event: GameTurnAdvanced', e);
                // Turn advancement changes current schemes, next scheme options, and opponent intel
                reload(['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel', 'observer_scheme_intel']);
            });

        // Log subscription errors for presence channels
        if (ch.subscription) {
            ch.subscription.bind('pusher:subscription_error', (err: any) => {
                if (import.meta.env.DEV) console.error('[GameChannel] Subscription error:', err);
            });
            ch.subscription.bind('pusher:subscription_succeeded', () => {
                if (import.meta.env.DEV) console.log('[GameChannel] Subscription succeeded');
            });
        }
    };

    const joinChannel = () => {
        if (!window.Echo) {
            if (import.meta.env.DEV) console.warn('[GameChannel] Echo not available');
            return;
        }
        if (!gameUuid) {
            if (import.meta.env.DEV) console.log('[GameChannel] No UUID, skipping');
            return;
        }

        if (isObserver) {
            if (import.meta.env.DEV) console.log(`[GameChannel] Observer joining public channel: game-observe.${gameUuid}`);
            channel = window.Echo.channel(`game-observe.${gameUuid}`);
            addListeners(channel);
        } else {
            if (import.meta.env.DEV) console.log(`[GameChannel] Participant joining presence channel: game.${gameUuid}`);
            channel = window.Echo.join(`game.${gameUuid}`)
                .here((members: PresenceMember[]) => {
                    onlineMembers.value = members;
                    opponentOnline.value = members.length > 1;
                })
                .joining((member: PresenceMember) => {
                    onlineMembers.value.push(member);
                    opponentOnline.value = onlineMembers.value.length > 1;
                })
                .leaving((member: PresenceMember) => {
                    onlineMembers.value = onlineMembers.value.filter((m) => m.id !== member.id);
                    opponentOnline.value = onlineMembers.value.length > 1;
                });
            addListeners(channel);
        }
    };

    const leaveChannel = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        if (channel) {
            const channelName = isObserver ? `game-observe.${gameUuid}` : `game.${gameUuid}`;
            window.Echo?.leave(channelName);
            channel = null;
        }
    };

    onMounted(() => {
        joinChannel();
    });

    onUnmounted(() => {
        leaveChannel();
    });

    return {
        onlineMembers,
        opponentOnline,
        channel,
    };
}
