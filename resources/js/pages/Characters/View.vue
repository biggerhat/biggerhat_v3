<script setup lang="ts">
import CharacterView from '@/components/CharacterView.vue';
import JsonLd from '@/components/JsonLd.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useFactionColor } from '@/composables/useFactionColor';
import { computed } from 'vue';

const props = defineProps({
    character: {
        type: Object,
        required: true,
    },
    miniature: {
        type: Object,
        required: true,
    },
    related_characters: {
        type: Array,
        required: false,
        default: () => [],
    },
});

const factionColor = props.character?.faction ? useFactionColor(props.character.faction) : '';

const seoDescription = computed(() => {
    const c = props.character;
    const station = c.station ? `${String(c.station).charAt(0).toUpperCase()}${String(c.station).slice(1)}` : '';
    const faction = c.faction ? String(c.faction).replace(/_/g, ' ') : '';
    const keywords = (c.keywords ?? []).map((k: { name: string }) => k.name).filter(Boolean).join(', ');
    const parts = [
        `${c.display_name}, ${station} ${faction} character for Malifaux`.trim(),
        keywords ? `Keywords: ${keywords}.` : '',
        c.cost ? `${c.cost}ss cost, ${c.health} health, ${c.defense} defense.` : '',
    ].filter(Boolean);
    return parts.join(' ').slice(0, 280);
});

const seoImage = computed(() => props.miniature?.front_image ?? null);
</script>

<template>
    <SeoHead :title="character.display_name" :description="seoDescription" :image="seoImage" />
    <JsonLd
        head-key="character-article"
        :data="{
            '@context': 'https://schema.org',
            '@type': 'Article',
            headline: character.display_name,
            description: seoDescription,
            image: seoImage ? (String(seoImage).startsWith('http') ? seoImage : `https://biggerhat.net/storage/${seoImage}`) : undefined,
            about: { '@type': 'Thing', name: 'Malifaux' },
            publisher: {
                '@type': 'Organization',
                name: 'BiggerHat',
                logo: { '@type': 'ImageObject', url: 'https://biggerhat.net/images/biggerhat-og.png' },
            },
        }"
    />
    <div class="relative h-full w-full">
        <div
            v-if="factionColor"
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${factionColor})) 0%, transparent 70%)` }"
        />
        <div class="animate-fade-in-up">
            <CharacterView :character="props.character" :miniature="props.miniature" :related-characters="props.related_characters" />
        </div>
    </div>
</template>
