import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

interface PresenceMember {
    id: number;
    name: string;
}

export function useGameChannel(gameUuid: string) {
    const onlineMembers = ref<PresenceMember[]>([]);
    const opponentOnline = ref(false);
    let channel: any = null;

    const joinChannel = () => {
        if (!window.Echo || !gameUuid) return;

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
            })
            .listen('.GamePlayerJoined', () => {
                router.reload({ only: ['game', 'schemes', 'deployment', 'factions', 'masters', 'my_crews'] });
            })
            .listen('.GameStatusChanged', () => {
                router.reload({ only: ['game', 'schemes', 'deployment', 'factions', 'masters', 'my_crews'] });
            })
            .listen('.GameSetupStepCompleted', () => {
                router.reload({ only: ['game', 'masters', 'my_crews'] });
            })
            .listen('.GameCrewMemberUpdated', () => {
                router.reload({ only: ['game', 'next_schemes'] });
            })
            .listen('.GameTurnAdvanced', () => {
                router.reload({ only: ['game', 'schemes', 'next_schemes'] });
            });
    };

    const leaveChannel = () => {
        if (channel) {
            window.Echo?.leave(`game.${gameUuid}`);
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
