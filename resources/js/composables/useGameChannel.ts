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

    const reload = (only: string[]) => {
        router.reload({ only, preserveScroll: true });
    };

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
                reload(['game']);
            })
            .listen('.GameStatusChanged', () => {
                reload(['game', 'schemes', 'deployment', 'masters', 'my_crews']);
            })
            .listen('.GameSetupStepCompleted', () => {
                reload(['game']);
            })
            .listen('.GameCrewMemberUpdated', () => {
                reload(['game']);
            })
            .listen('.GameTurnAdvanced', () => {
                reload(['game', 'next_schemes']);
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
