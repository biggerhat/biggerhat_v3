export function useFactionColor(factionSlug: string): string {
    switch (factionSlug) {
        case 'explorers_society':
            return 'explorerssociety';
        case 'ten_thunders':
            return 'tenthunders';
        default:
            return factionSlug;
    }
}
