<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string $slug
 * @property string|null $suits
 * @property string|null $defensive_ability_type
 * @property int $costs_stone
 * @property string|null $description
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\AbilityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereCostsStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDefensiveAbilityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAbility {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string $type
 * @property bool $is_signature
 * @property int $stone_cost
 * @property string $slug
 * @property string|null $range
 * @property string|null $range_type
 * @property string|null $stat
 * @property string|null $stat_suits
 * @property string|null $stat_modifier
 * @property string|null $resisted_by
 * @property string|null $target_number
 * @property string|null $target_suits
 * @property string|null $description
 * @property string|null $damage
 * @property string|null $internal_notes
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $campaign_advancement_kind
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\ActionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereCampaignAdvancementKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDamage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereInternalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereIsSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereRangeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereResistedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereStat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereStatModifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereStatSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereStoneCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereTargetNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereTargetSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAction {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $message
 * @property string $level
 * @property string $audience
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_dismissable
 * @property string|null $link_url
 * @property string|null $link_label
 * @property int|null $created_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $createdBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereAudience($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereIsDismissable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereLinkLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereLinkUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAnnouncement {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_news
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlogPost> $posts
 * @property-read int|null $posts_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory excludingNews()
 * @method static \Database\Factories\BlogCategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory news()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereIsNews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBlogCategory {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property array<array-key, mixed>|null $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property \App\Enums\BlogPostStatusEnum $status
 * @property int|null $blog_category_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User $author
 * @property-read \App\Models\BlogCategory|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read array<string> $faction_tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost excludingNews()
 * @method static \Database\Factories\BlogPostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost news()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereBlogCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereFeaturedImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BlogPost withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBlogPost {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $source_url
 * @property string|null $wyrd_post_slug
 * @property string|null $image_path
 * @property \App\Enums\SculptVersionEnum $sculpt_version
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $packages
 * @property-read int|null $packages_count
 * @method static \Database\Factories\BlueprintFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereSculptVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereSourceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereWyrdPostSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint withImage()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBlueprint {}
}

namespace App\Models\Campaign{
/**
 * Tier 2 Ability advancement (pg 50–51). Adds a new ability to the leader.
 * 
 * A few "always available" rows are unconditionally selectable. The one
 * "Any Joker" row mirrors the Action table's free-pick mechanic.
 *
 * @property string|null $suits
 * @property string|null $defensive_ability_type
 * @property int|null $ability_id
 * @property-read Ability|null $ability
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_joker
 * @property bool $is_always_available
 * @property string $talent_name
 * @property string $effect_text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\AdvancementAbilityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereAbilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereDefensiveAbilityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereEffectText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereIsAlwaysAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereIsJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereTalentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAbility whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAdvancementAbility {}
}

namespace App\Models\Campaign{
/**
 * Tier 2 Action advancement (pg 44–49). Adds a new action to the leader.
 * 
 * A few "always available" rows are unconditionally selectable. The one
 * "Any Joker" row lets the player pick any action from a non-master/
 * non-totem model sharing a keyword (cost <= 10).
 *
 * @property array<string, mixed>|null $stat_block
 * @property int|null $action_id
 * @property bool $is_signature
 * @property-read Action|null $action
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_joker
 * @property bool $is_always_available
 * @property string $talent_name
 * @property string $effect_text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\AdvancementActionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereActionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereEffectText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereIsAlwaysAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereIsJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereIsSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereStatBlock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereTalentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAction whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAdvancementAction {}
}

namespace App\Models\Campaign{
/**
 * Tier 1 Attack Modification advancement (pg 38–40). Flip value gates
 * options <= value; mostly triggers, a few skl_boost rows, one signature
 * row. The two Joker rows (Cruel Lessons, Consult the Bones) are "Any
 * Joker" — both is_black_joker and is_red_joker are true, either color
 * qualifies.
 *
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property bool $is_always_available
 * @property string $modifier_type
 * @property string $name
 * @property string $effect_text
 * @property string|null $suit
 * @property int|null $skl_from
 * @property int|null $skl_from_max
 * @property int|null $skl_to
 * @property int|null $trigger_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Trigger|null $trigger
 * @method static \Database\Factories\Campaign\AdvancementAttackModFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereEffectText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereIsAlwaysAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereIsBlackJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereIsRedJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereModifierType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereSklFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereSklFromMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereSklTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereTriggerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementAttackMod whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAdvancementAttackMod {}
}

namespace App\Models\Campaign{
/**
 * Tier 1 Tactical Modification advancement (pg 41–43). Flip value gates
 * options <= value; mostly triggers, a few skl_boost rows, one signature
 * row. Unlike Attack Mod, the two Joker rows are color-specific — Red
 * Joker grants Illumination of Illios, Black Joker grants Darkness of
 * Delios — each with exactly one of is_black_joker/is_red_joker true.
 *
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property bool $is_always_available
 * @property string $modifier_type
 * @property string $name
 * @property string $effect_text
 * @property string|null $suit
 * @property int|null $skl_from
 * @property int|null $skl_from_max
 * @property int|null $skl_to
 * @property int|null $trigger_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Trigger|null $trigger
 * @method static \Database\Factories\Campaign\AdvancementTacticalModFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereEffectText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereIsAlwaysAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereIsBlackJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereIsRedJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereModifierType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereSklFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereSklFromMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereSklTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereTriggerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdvancementTacticalMod whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAdvancementTacticalMod {}
}

namespace App\Models\Campaign{
/**
 * Catalog entry for one of the Phase 5 Back-Alley Doctor outcomes (pg 33).
 * 
 * Range-based — rows cover BJ / 1-8 / 9 / 10 / 11 / 12-13 / RJ.
 *
 * @property int $id
 * @property string $name
 * @property string $body
 * @property int|null $flip_value_min
 * @property int|null $flip_value_max
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property \App\Enums\Campaign\BackAlleyDoctorOutcomeEnum $outcome_kind
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\BackAlleyDoctorResultFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereFlipValueMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereFlipValueMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereIsBlackJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereIsRedJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereOutcomeKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackAlleyDoctorResult whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBackAlleyDoctorResult {}
}

namespace App\Models\Campaign{
/**
 * A Malifaux 4E Campaign Mode game. One per group of players. The organizer
 * creates it and invites others; each invited user gets a `CampaignCrew` row
 * once they accept. Status transitions: planning → active → ended.
 *
 * @property int $id
 * @property string|null $uuid unguessable public join-link identifier (see CampaignController::joinPublic())
 * @property string $name
 * @property int $length_weeks
 * @property int $current_week
 * @property int $organizer_user_id
 * @property CampaignStatusEnum $status
 * @property array<string, bool>|null $optional_rules
 * @property bool $competitive
 * @property bool $weekly_event_active
 * @property bool $is_solo
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $ended_at
 * @property-read \App\Models\User|null $organizer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignPlayer> $players
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrew> $crews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignWeek> $weeks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $crews_count
 * @property-read int|null $invitations_count
 * @property-read int|null $players_count
 * @property-read int|null $weeks_count
 * @method static \Database\Factories\Campaign\CampaignFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereCompetitive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereCurrentWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereIsSolo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereLengthWeeks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereOptionalRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereOrganizerUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Campaign whereWeeklyEventActive($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaign {}
}

namespace App\Models\Campaign{
/**
 * One Aftermath flow per crew per game. The fate-deck-no-reshuffle invariant
 * is preserved by snapshotting the drawn hand at Phase 1 start and persisting
 * `hand_drawn` server-side — refreshing the wizard mid-flow resumes on the
 * same hand.
 * 
 * `hand_drawn` is the immutable snapshot of cards drawn in Phase 1:
 *     [{ value: int, suit: string, is_joker: bool }, ...]
 * 
 * `hand_used` is an append-only audit log of phase events for the history
 * panel — currently written by the skip-phase advance() endpoint:
 *     [{ phase: int, used_for: string, notes: string, at: ISO8601 }, ...]
 *
 * @property int $id
 * @property int $campaign_game_id
 * @property int $campaign_crew_id
 * @property int $current_phase
 * @property array<int, array{value: int, suit: string, is_joker: bool}>|null $hand_drawn
 * @property array<int, array{phase: int, used_for: string, notes: string, at: string}>|null $hand_used
 * @property int $scrip_earned
 * @property string $status
 * @property string|null $story_entry
 * @property-read CampaignGame $campaignGame
 * @property-read CampaignCrew $crew
 * @property int|null $xp_earned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\CampaignAftermathFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereCampaignGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereCurrentPhase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereHandDrawn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereHandUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereScripEarned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereStoryEntry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignAftermath whereXpEarned($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignAftermath {}
}

namespace App\Models\Campaign{
/**
 * One non-leader model owned by a campaign crew's arsenal. Annihilated
 * models stay around for history (annihilated_at != null) and are excluded
 * from active hiring/scoring queries via the `active()` scope.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $character_id
 * @property int|null $miniature_id
 * @property string|null $label
 * @property bool $is_peon
 * @property string|null $title_group_key
 * @property int|null $acquired_week
 * @property string $acquired_via
 * @property int|null $granted_keyword_id
 * @property \Carbon\CarbonImmutable|null $annihilated_at
 * @property \Carbon\CarbonImmutable|null $removed_at
 * @property bool $ignored_for_limits
 * @property array<array-key, mixed>|null $gained_characteristics
 * @property array<array-key, mixed>|null $gained_lucky_miss_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Character $character
 * @property-read \App\Models\Campaign\CampaignCrew $crew
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $gainedAbilities
 * @property-read int|null $gained_abilities_count
 * @property-read \App\Models\Keyword|null $grantedKeyword
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Campaign\CampaignArsenalModelInjury> $injuries
 * @property-read int|null $injuries_count
 * @property-read \App\Models\Miniature|null $miniature
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel active()
 * @method static \Database\Factories\Campaign\CampaignArsenalModelFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereAcquiredVia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereAcquiredWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereAnnihilatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereGainedCharacteristics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereGainedLuckyMissIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereGrantedKeywordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereIgnoredForLimits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereIsPeon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereMiniatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereRemovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereTitleGroupKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignArsenalModel {}
}

namespace App\Models\Campaign{
/**
 * Pivot row: one injury upgrade attached to one arsenal model, or to a
 * Leader/Totem (pg 34). A model may hold up to two injuries; the third
 * (distinct) injury annihilates it. The same injury is never stacked twice
 * on one model — a duplicate flip is ignored ("the model got lucky and
 * suffers no injury this game"). Exactly one of campaign_arsenal_model_id /
 * custom_character_id is set per row.
 *
 * @property int $id
 * @property int|null $campaign_arsenal_model_id
 * @property int|null $custom_character_id
 * @property int $injury_upgrade_id
 * @property int|null $acquired_aftermath_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read CampaignArsenalModel $arsenalModel
 * @property-read CustomCharacter|null $customCharacter
 * @property-read Upgrade|null $injury
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereAcquiredAftermathId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereCampaignArsenalModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereCustomCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereInjuryUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignArsenalModelInjury whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignArsenalModelInjury {}
}

namespace App\Models\Campaign{
/**
 * A player's arsenal entry inside a Campaign. Created automatically when the
 * user accepts an invitation. Initially stub — name + share_code only — and
 * the player completes it during Leader Build + Starting Arsenal flows
 * (Phases 4-5 of the plan).
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property string $name
 * @property string $share_code
 * @property FactionEnum|null $faction
 * @property int|null $keyword_1_id
 * @property int|null $keyword_2_id
 * @property int|null $crew_card_effect_id
 * @property array{type: string, id: int|string, name: string}|null $crew_card_choice
 * @property string|null $crew_card_front_image generated image combining the starter effect + every held Tier-4 borrow — see CombinedCrewCardEffects
 * @property int $scrip
 * @property int $total_wins
 * @property \Carbon\CarbonImmutable|null $retired_at
 * @property \Carbon\CarbonImmutable|null $starting_anew_at
 * @property-read Campaign $campaign
 * @property-read \App\Models\User|null $user
 * @property-read Keyword|null $keywordOne
 * @property-read Keyword|null $keywordTwo
 * @property-read CampaignCrewCard|null $crewCardEffect
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrewCardAdvancement> $crewCardAdvancements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignArsenalModel> $arsenalModels
 * @property-read int|null $arsenal_models_count
 * @property-read CustomCharacter|null $leader
 * @property-read CustomCharacter|null $totem
 * @method static \Database\Factories\Campaign\CampaignCrewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew query()
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $crew_card_advancements_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereCampaignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereCrewCardChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereCrewCardEffectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereCrewCardFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereKeyword1Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereKeyword2Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereRetiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereScrip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereStartingAnewAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereTotalWins($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrew whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignCrew {}
}

namespace App\Models\Campaign{
/**
 * A starting Crew Card option for the Starting Arsenal wizard (pg 15) —
 * also borrowable via Tier-4 Crew Card Advancement (pg 32, 54) as the
 * always-generic fallback pool alongside the real, keyword-matched
 * Crew Card Upgrade catalog (see AftermathCatalog::advancementCatalogs()).
 * 
 * These replace the interim approach of tagging Ability rows with
 * is_crew_card_effect=true — crew cards can have richer structure than
 * a single ability description allows.
 * 
 * Deliberately has no "master this is printed on" field — it's a shared
 * catalog row any crew can pick, so any master/faction theming is derived
 * live from the holding crew's own current Leader at display time instead.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $requires_token_choice
 * @property bool $requires_marker_choice
 * @property bool $requires_upgrade_type_choice
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Action> $actions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ability> $abilities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrew> $crews
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard query()
 * @property-read int|null $abilities_count
 * @property-read int|null $actions_count
 * @property-read int|null $crews_count
 * @method static \Database\Factories\Campaign\CampaignCrewCardFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereRequiresMarkerChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereRequiresTokenChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereRequiresUpgradeTypeChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignCrewCard {}
}

namespace App\Models\Campaign{
/**
 * A Tier-4 Crew Card advancement (pg 32, 54) — an extra effect borrowed
 * either from the generic pg 15-16 catalog (`CampaignCrewCard`) or from a
 * real, keyword-matched Crew Card Upgrade (`Upgrade::forCrews()`), added to
 * the crew's own crew card. Unlike the starter effect
 * (`CampaignCrew::crew_card_effect_id`, a single FK), these accumulate: a
 * crew can hold its starter effect plus any number of Tier-4 borrows.
 * 
 * `crew_card_effect_id`/`crew_card_effect_type` is polymorphic (no DB-level
 * FK) since a single column can't reference both source tables.
 * 
 * Deliberately has no "source master" attribution — the real catalog pool is
 * keyword-matched (no single associated master) and the generic pool is
 * always generic, so there's no single truth to attribute a borrow to
 * anymore. Display context (whose crew this belongs to) is derived live from
 * the holding crew's own current Leader instead.
 * 
 * `crew_card_item_type`/`crew_card_item_id` (pg 32: "'effect' refers to a
 * single ability, action, or trigger") pin down exactly which item on the
 * source card was picked — only ever set when crew_card_effect_type is
 * Upgrade::class. Null on that source means a legacy pre-granularity row
 * (treat as "holds every item this card ever granted"); always null for
 * CampaignCrewCard::class source rows, which stay whole-row.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $crew_card_effect_id
 * @property string $crew_card_effect_type CampaignCrewCard::class or Upgrade::class
 * @property string|null $crew_card_item_type 'action'|'ability'|'trigger', Upgrade source only
 * @property int|null $crew_card_item_id
 * @property array{type: string, id: int|string, name: string}|null $crew_card_choice
 * @property int|null $acquired_aftermath_id
 * @property-read CampaignCrew $crew
 * @property-read CampaignCrewCard|Upgrade $crewCardEffect
 * @property-read CampaignAftermath|null $sourceAftermath
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\CampaignCrewCardAdvancementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereAcquiredAftermathId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCrewCardChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCrewCardEffectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCrewCardEffectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCrewCardItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereCrewCardItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCardAdvancement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignCrewCardAdvancement {}
}

namespace App\Models\Campaign{
/**
 * One row per per-crew equipment instance — multiple copies of the same
 * underlying Equipment catalog row stack as separate rows. Annihilated
 * instances stay around for history; `active()` scope filters them out.
 * 
 * Post-Catalog-Consolidation: `equipment_upgrade_id` points at an upgrades
 * row with `game_mode_type=campaign` + `campaign_upgrade_kind=equipment`.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int|null $equipment_upgrade_id
 * @property string $source
 * @property int|null $acquired_aftermath_id
 * @property \Carbon\CarbonImmutable|null $annihilated_at
 * @property-read Upgrade|null $catalog
 * @property-read \App\Models\Campaign\CampaignCrew|null $crew
 * @method static Builder<static>|CampaignEquipment active()
 * @method static Builder<static>|CampaignEquipment countsTowardCr()
 * @method static \Database\Factories\Campaign\CampaignEquipmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|CampaignEquipment newModelQuery()
 * @method static Builder<static>|CampaignEquipment newQuery()
 * @method static Builder<static>|CampaignEquipment query()
 * @property int $excludes_from_cr
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereAcquiredAftermathId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereAnnihilatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereEquipmentUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereExcludesFromCr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignEquipment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignEquipment {}
}

namespace App\Models\Campaign{
/**
 * Campaign-context wrapper around a standard Game. Lives 1:1 with the base
 * `games` row (linked by `base_game_id`) and carries the campaign-only
 * fields: encounter math, CR snapshots taken at game-start time, ss-pool
 * bonus for the lower-rated crew, scoring fields populated during Aftermath.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $week_number
 * @property int $crew_a_id
 * @property int|null $crew_b_id
 * @property int|null $base_game_id
 * @property int $encounter_size
 * @property int $cr_a
 * @property int $cr_b
 * @property int $ss_bonus_to_lower
 * @property int|null $winner_crew_id
 * @property int|null $withdrew_crew_id
 * @property int|null $withdrew_turn
 * @property int $vp_a
 * @property int $vp_b
 * @property int $schemes_completed_a
 * @property int $schemes_completed_b
 * @property int|null $weekly_event_id
 * @property string $status
 * @property-read Campaign $campaign
 * @property-read CampaignCrew $crewA
 * @property-read CampaignCrew|null $crewB
 * @property-read \App\Models\Game|null $baseGame
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\CampaignGameFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereBaseGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCampaignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCrA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCrB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCrewAId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereCrewBId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereEncounterSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereSchemesCompletedA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereSchemesCompletedB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereSsBonusToLower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereVpA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereVpB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereWeekNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereWeeklyEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereWinnerCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereWithdrewCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignGame whereWithdrewTurn($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignGame {}
}

namespace App\Models\Campaign{
/**
 * Pending invitation. Either keyed to an existing user (typical) or by email
 * (for someone without an account yet). Token is the URL-safe identifier the
 * invitee uses to land on the accept page.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $token
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property-read Campaign $campaign
 * @property-read \App\Models\User|null $user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\CampaignInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereCampaignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignInvitation whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignInvitation {}
}

namespace App\Models\Campaign{
/**
 * One advancement gained by a Leader (or routed to its Totem). The renderer
 * walks these to compose the final action/ability list and any trigger / Skl
 * modifications applied to the underlying CustomCharacter actions.
 * 
 * `applied_skl_from` is set only for Skl Boost advancements — the action's
 * actual Skl at the moment the boost was applied, captured because the
 * catalog row's own skl_from is a qualifying range, not necessarily the
 * action's exact prior value, so removing the advancement needs this to
 * restore it correctly.
 * 
 * An Attack/Tactical Mod advancement (pg 38-43) targets exactly one of three
 * things: the Leader (default — `applied_to_custom_character_id` and
 * `from_equipment_id` both null, `applied_to_action_index` indexes the
 * leader's `actions[]`), the crew's current Totem (`applied_to_custom_character_id`
 * set, `applied_to_action_index` indexes the totem's own `actions[]` —
 * identical shape/mechanism to the leader), or a piece of owned Equipment
 * (`from_equipment_id` set, `applied_to_action_id` is the real `actions.id`
 * the equipment grants — equipment has no per-instance actions[] to index
 * into, so nothing is mutated; this record alone is the source of truth,
 * rendered as an overlay wherever that equipment is displayed). Equipment
 * targeting locks that equipment to the crew going forward (pg 31: "if the
 * action is from a piece of equipment, the leader must always take that
 * equipment if possible going forward").
 *
 * @property int $id
 * @property int $custom_character_id
 * @property int|null $source_aftermath_id
 * @property AdvancementTableEnum $source_table
 * @property int|null $advancement_catalog_id
 * @property int|null $catalog_core_id
 * @property int|null $from_equipment_id
 * @property int $applied_to_action_index
 * @property int|null $applied_to_action_id
 * @property int|null $applied_skl_from
 * @property int|null $applied_to_custom_character_id
 * @property int $position_in_xp_track
 * @property array<string, mixed>|null $free_choice
 * @property \Carbon\CarbonImmutable|null $acquired_at
 * @property-read CustomCharacter $leader
 * @property-read CampaignAftermath|null $sourceAftermath
 * @property-read CustomCharacter|null $appliedToCustomCharacter
 * @property-read CampaignEquipment|null $fromEquipment
 * @property-read Action|null $appliedToAction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\CampaignLeaderAdvancementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAcquiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAdvancementCatalogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAppliedSklFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAppliedToActionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAppliedToActionIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereAppliedToCustomCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereCatalogCoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereCustomCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereFreeChoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereFromEquipmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement wherePositionInXpTrack($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereSourceAftermathId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereSourceTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignLeaderAdvancement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignLeaderAdvancement {}
}

namespace App\Models\Campaign{
/**
 * Pivot for campaign membership. Distinct from `campaign_crews` (which is
 * one-per-player and carries arsenal data) so we can record co-organizers
 * who don't run a crew, and so role transitions don't touch crew rows.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property CampaignPlayerRoleEnum $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Campaign\Campaign $campaign
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\Campaign\CampaignPlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereCampaignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignPlayer whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignPlayer {}
}

namespace App\Models\Campaign{
/**
 * One row per calendar week of a campaign. Stores the rolled Weekly Event
 * (if optional rule enabled) and per-week organizer notes.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $week_number
 * @property \Carbon\CarbonImmutable|null $starts_at
 * @property int|null $weekly_event_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Campaign\Campaign $campaign
 * @property-read \App\Models\Campaign\WeeklyEvent|null $weeklyEvent
 * @method static \Database\Factories\Campaign\CampaignWeekFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereCampaignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereWeekNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignWeek whereWeeklyEventId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCampaignWeek {}
}

namespace App\Models\Campaign{
/**
 * Catalog entry for the Lucky Miss table (pg 36) — positive upgrades flipped
 * when an injury/doctor result is a red joker. Any-joker → Doppelganger
 * (free copy in the arsenal).
 *
 * @property int|null $ability_id
 * @property-read Ability|null $ability
 * @property int $id
 * @property string $name
 * @property string $body
 * @property int|null $flip_value
 * @property bool $is_doppelganger
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\LuckyMissFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereAbilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereIsDoppelganger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LuckyMiss whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLuckyMiss {}
}

namespace App\Models\Campaign{
/**
 * Catalog entry for a Weekly Event (pg 148–149). Rolled at the start of a
 * week when the campaign has the weekly_events optional rule enabled. Some
 * events place special terrain markers; "Bullet with Your Name on It" only
 * fires once per campaign (reflips on second occurrence).
 *
 * @property int $id
 * @property string $name
 * @property string $body
 * @property int|null $flip_value
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property array<array-key, mixed>|null $terrain_marker_def
 * @property bool $requires_placement
 * @property bool $is_one_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Campaign\WeeklyEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereIsBlackJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereIsOneTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereIsRedJoker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereRequiresPlacement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereTerrainMarkerDef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WeeklyEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWeeklyEvent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transmission> $transmissions
 * @property-read int|null $transmissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperChannel {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string|null $title
 * @property string|null $title_group_key
 * @property string $display_name
 * @property string $slug
 * @property string|null $nicknames
 * @property \App\Enums\FactionEnum $faction
 * @property \App\Enums\FactionEnum|null $second_faction
 * @property \App\Enums\CharacterStationEnum|null $station
 * @property int $station_sort_order
 * @property int|null $cost
 * @property int $health
 * @property int $size
 * @property \App\Enums\BaseSizeEnum $base
 * @property int $defense
 * @property \App\Enums\SuitEnum|null $defense_suit
 * @property int $willpower
 * @property \App\Enums\SuitEnum|null $willpower_suit
 * @property int $speed
 * @property int $count
 * @property int|null $summon_target_number
 * @property int|null $has_totem_id
 * @property int|null $crew_upgrade_id
 * @property bool $generates_stone
 * @property bool $is_unhirable
 * @property \App\Enums\CrewUpgradeModeEnum $crew_upgrade_mode
 * @property bool $is_beta
 * @property bool $is_hidden
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlogPost> $blogPosts
 * @property-read int|null $blog_posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $blueprints
 * @property-read int|null $blueprints_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Characteristic> $characteristics
 * @property-read int|null $characteristics_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read string $faction_color
 * @property-read Character|null $isTotemFor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lore> $lores
 * @property-read int|null $lores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Marker> $markers
 * @property-read int|null $markers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $packages
 * @property-read int|null $packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $promotionalMiniatures
 * @property-read int|null $promotional_miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Character> $replacedBy
 * @property-read int|null $replaced_by_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Character> $replacesInto
 * @property-read int|null $replaces_into_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Character> $replacesOnDeath
 * @property-read int|null $replaces_on_death_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $standardMiniatures
 * @property-read int|null $standard_miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Character> $summonedBy
 * @property-read int|null $summoned_by_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Character> $summons
 * @property-read int|null $summons_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @property-read Character|null $totem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transmission> $transmissions
 * @property-read int|null $transmissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\CharacterFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character forStation(\App\Enums\CharacterStationEnum $station)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCrewUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCrewUpgradeMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDefense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDefenseSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereGeneratesStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereHasTotemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereHealth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereIsBeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereIsHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereIsUnhirable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereNicknames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereSecondFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereSpeed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereStation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereStationSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereSummonTargetNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereTitleGroupKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereWillpower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereWillpowerSuit($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCharacter {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @method static \Database\Factories\CharacteristicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Characteristic whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCharacteristic {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property array<array-key, mixed>|null $description
 * @property string $share_code
 * @property int|null $user_id
 * @property int|null $copied_from_id
 * @property \App\Enums\FactionEnum $faction
 * @property int $master_id
 * @property int $encounter_size
 * @property array<array-key, mixed> $crew_data
 * @property array<array-key, mixed>|null $custom_crew_data
 * @property array<array-key, mixed>|null $miniature_selections
 * @property int|null $crew_upgrade_id
 * @property array<array-key, mixed>|null $references
 * @property array<array-key, mixed>|null $custom_references
 * @property bool $is_archived
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read CrewBuild|null $copiedFrom
 * @property-read \App\Models\Upgrade|null $crewUpgrade
 * @property-read \App\Models\Character $master
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\CrewBuildFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCopiedFromId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCrewData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCrewUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCustomCrewData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereCustomReferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereEncounterSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereMasterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereMiniatureSelections($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereReferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrewBuild withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCrewBuild {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $campaign_crew_id
 * @property bool $is_campaign_leader
 * @property bool $is_campaign_totem
 * @property bool $is_campaign_totem_template
 * @property int|null $campaign_totem_flip_value
 * @property bool $campaign_is_black_joker_totem
 * @property bool $campaign_is_red_joker_totem
 * @property bool $campaign_totem_special_replace
 * @property bool $campaign_is_mini_master
 * @property string|null $archetype
 * @property string|null $tag
 * @property int|null $campaign_size
 * @property int|null $campaign_health
 * @property int|null $campaign_df
 * @property int|null $campaign_wp
 * @property int|null $campaign_sp
 * @property bool $miraculous_recovery_used
 * @property \Illuminate\Support\Carbon|null $annihilated_at
 * @property \Illuminate\Support\Carbon|null $replaced_at
 * @property bool $current
 * @property array<array-key, mixed>|null $xp_track
 * @property string $share_code
 * @property bool $is_public
 * @property string $name
 * @property string|null $title
 * @property string $display_name
 * @property string $slug
 * @property \App\Enums\FactionEnum|null $faction
 * @property \App\Enums\FactionEnum|null $second_faction
 * @property \App\Enums\CharacterStationEnum|null $station
 * @property int|null $cost
 * @property int $health
 * @property int|null $size
 * @property \App\Enums\BaseSizeEnum|null $base
 * @property int $defense
 * @property \App\Enums\SuitEnum|null $defense_suit
 * @property int $willpower
 * @property \App\Enums\SuitEnum|null $willpower_suit
 * @property int $speed
 * @property int $count
 * @property int|null $summon_target_number
 * @property bool $generates_stone
 * @property bool $is_unhirable
 * @property array<array-key, mixed>|null $actions
 * @property array<array-key, mixed>|null $abilities
 * @property array<array-key, mixed>|null $keywords
 * @property array<array-key, mixed>|null $characteristics
 * @property array<array-key, mixed>|null $linked_crew_upgrades
 * @property array<array-key, mixed>|null $linked_totems
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $campaignTotemAbilities
 * @property-read int|null $campaign_totem_abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $campaignTotemActions
 * @property-read int|null $campaign_totem_actions_count
 * @property-read string $faction_color
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Campaign\CampaignArsenalModelInjury> $injuries
 * @property-read int|null $injuries_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereAbilities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereActions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereAnnihilatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereArchetype($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignDf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignHealth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignIsBlackJokerTotem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignIsMiniMaster($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignIsRedJokerTotem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignSp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignTotemFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignTotemSpecialReplace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCampaignWp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCharacteristics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereCurrent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereDefense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereDefenseSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereGeneratesStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereHealth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereIsCampaignLeader($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereIsCampaignTotem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereIsCampaignTotemTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereIsUnhirable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereLinkedCrewUpgrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereLinkedTotems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereMiraculousRecoveryUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereReplacedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereSecondFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereSpeed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereStation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereSummonTargetNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereWillpower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereWillpowerSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter whereXpTrack($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomCharacter withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCustomCharacter {}
}

namespace App\Models{
/**
 * 
 *
 * @property FactionEnum|null $faction
 * @property int $id
 * @property int $user_id
 * @property int|null $campaign_crew_id
 * @property string $share_code
 * @property bool $is_public
 * @property string $name
 * @property string $display_name
 * @property string $slug
 * @property \App\Enums\UpgradeDomainTypeEnum $domain
 * @property bool $is_campaign_crew_card
 * @property string|null $type
 * @property string|null $limitations
 * @property int|null $plentiful
 * @property string|null $master_name
 * @property string|null $keyword_name
 * @property array<array-key, mixed>|null $content_blocks
 * @property array<array-key, mixed>|null $back_tokens
 * @property array<array-key, mixed>|null $back_markers
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $faction_color
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereBackMarkers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereBackTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereCampaignCrewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereContentBlocks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereIsCampaignCrewCard($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereKeywordName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereLimitations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereMasterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade wherePlentiful($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomUpgrade withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCustomUpgrade {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property \App\Enums\FeedbackCategoryEnum $category
 * @property string|null $subject
 * @property string $message
 * @property string|null $url
 * @property \App\Enums\FeedbackStatusEnum $status
 * @property string|null $admin_notes
 * @property string|null $submitter_ip
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereAdminNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereSubmitterIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFeedback {}
}

namespace App\Models{
/**
 * A friend request/friendship between two users. Binary pending↔accepted
 * state via a nullable `accepted_at` (mirrors CampaignInvitation) — declining
 * a request or unfriending just deletes the row, no separate "declined"
 * state. Directional (requester/addressee) rather than a symmetric pivot, so
 * "who sent it" is always known while pending.
 *
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property-read User $requester
 * @property-read User $addressee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship accepted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship between(int $userIdA, int $userIdB)
 * @method static \Database\Factories\FriendshipFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereAddresseeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereRequesterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFriendship {}
}

namespace App\Models{
/**
 * 
 *
 * @property GameStatusEnum $status
 * @property DeploymentEnum|null $deployment
 * @property PoolSeasonEnum $season
 * @property GameFormatEnum $format
 * @property int $id
 * @property string $uuid
 * @property string|null $name
 * @property int $encounter_size
 * @property int|null $strategy_id
 * @property array<array-key, mixed>|null $scheme_pool
 * @property array<array-key, mixed>|null $loot_state
 * @property int $current_turn
 * @property int $max_turns
 * @property int $creator_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $winner_id
 * @property int|null $winner_slot
 * @property bool $is_tie
 * @property bool $is_solo
 * @property bool $is_observable
 * @property array<array-key, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Campaign\CampaignGame|null $campaignGame
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameCrewMember> $crewMembers
 * @property-read int|null $crew_members_count
 * @property-read string $season_label
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameLog> $logs
 * @property-read int|null $logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @property-read \App\Models\Strategy|null $strategy
 * @property-read \App\Models\TournamentGame|null $tournamentGame
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameTurn> $turns
 * @property-read int|null $turns_count
 * @property-read \App\Models\User|null $winner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game completed()
 * @method static \Database\Factories\GameFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game observable()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCurrentTurn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereDeployment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereEncounterSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereIsObservable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereIsSolo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereIsTie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereLootState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereMaxTurns($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereSchemePool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereStrategyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereWinnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereWinnerSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGame {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $game_id
 * @property int $game_player_id
 * @property int|null $character_id
 * @property int|null $custom_character_id
 * @property string $display_name
 * @property string|null $faction
 * @property int|null $current_health
 * @property int|null $max_health
 * @property int|null $defense
 * @property int|null $willpower
 * @property int|null $speed
 * @property int|null $size
 * @property array<array-key, mixed>|null $characteristics
 * @property bool $is_killed
 * @property bool $is_summoned
 * @property bool $is_activated
 * @property bool $is_custom
 * @property int|null $cost
 * @property \App\Enums\CharacterStationEnum|null $station
 * @property string|null $hiring_category
 * @property string|null $front_image
 * @property string|null $back_image
 * @property array<array-key, mixed>|null $attached_upgrades
 * @property array<array-key, mixed>|null $attached_tokens
 * @property array<array-key, mixed>|null $attached_markers
 * @property string|null $notes
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Character|null $character
 * @property-read \App\Models\CustomCharacter|null $customCharacter
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @method static \Database\Factories\GameCrewMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereAttachedMarkers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereAttachedTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereAttachedUpgrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCharacteristics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCurrentHealth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereCustomCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereDefense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereHiringCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereIsActivated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereIsCustom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereIsKilled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereIsSummoned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereMaxHealth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereSpeed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereStation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameCrewMember whereWillpower($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGameCrewMember {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $game_player_id
 * @property string $action
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer|null $gamePlayer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameLog wherePayload($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGameLog {}
}

namespace App\Models{
/**
 * 
 *
 * @property User $user
 * @property int $id
 * @property int $game_id
 * @property int|null $user_id
 * @property int $slot
 * @property string|null $opponent_name
 * @property \App\Enums\FactionEnum|null $faction
 * @property string|null $master_name
 * @property int|null $master_id
 * @property int|null $crew_build_id
 * @property int|null $active_crew_upgrade_id
 * @property array<array-key, mixed>|null $crew_upgrade_power_bars
 * @property bool $crew_skipped
 * @property \App\Enums\GameRoleEnum|null $role
 * @property int|null $current_scheme_id
 * @property int|null $next_scheme_id
 * @property array<array-key, mixed>|null $scheme_notes
 * @property array<array-key, mixed>|null $scheme_pool
 * @property int $total_points
 * @property int $soulstone_pool
 * @property bool $is_turn_complete
 * @property bool $is_game_complete
 * @property \Illuminate\Support\Carbon|null $hidden_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CrewBuild|null $crewBuild
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameCrewMember> $crewMembers
 * @property-read int|null $crew_members_count
 * @property-read \App\Models\Scheme|null $currentScheme
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Character|null $master
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameTurn> $turns
 * @property-read int|null $turns_count
 * @method static \Database\Factories\GamePlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereActiveCrewUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCrewBuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCrewSkipped($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCrewUpgradePowerBars($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCurrentSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereHiddenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereIsGameComplete($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereIsTurnComplete($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMasterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMasterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereNextSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereOpponentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSchemeNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSchemePool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSoulstonePool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTotalPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGamePlayer {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $game_id
 * @property int $turn_number
 * @property int $game_player_id
 * @property int|null $scheme_id
 * @property string|null $scheme_action
 * @property int|null $next_scheme_id
 * @property array<array-key, mixed>|null $scheme_notes
 * @property int $strategy_points
 * @property bool $strategy_bonus_used
 * @property int $scheme_points
 * @property array<array-key, mixed>|null $crew_snapshot
 * @property int $points_scored
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read \App\Models\Scheme|null $scheme
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereCrewSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereNextSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn wherePointsScored($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereSchemeAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereSchemeNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereSchemePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereStrategyBonusUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereStrategyPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereTurnNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameTurn whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGameTurn {}
}

namespace App\Models{
/**
 * 
 *
 * @property-read int|null $owned_characters_count
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlogPost> $blogPosts
 * @property-read int|null $blog_posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $masters
 * @property-read int|null $masters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $packages
 * @property-read int|null $packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PodLink> $podLinks
 * @property-read int|null $pod_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\KeywordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperKeyword {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $suit
 * @property int|null $value
 * @property string $value_label
 * @property string $name
 * @property string|null $title_a
 * @property string|null $effect_a
 * @property string|null $title_b
 * @property string|null $effect_b
 * @property string|null $image
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $sideAAbilities
 * @property-read int|null $side_a_abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $sideAActions
 * @property-read int|null $side_a_actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $sideATriggers
 * @property-read int|null $side_a_triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $sideBAbilities
 * @property-read int|null $side_b_abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $sideBActions
 * @property-read int|null $side_b_actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $sideBTriggers
 * @property-read int|null $side_b_triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereEffectA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereEffectB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereTitleA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereTitleB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LootCard whereValueLabel($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLootCard {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $file
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoreMedia> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Unit> $tosUnits
 * @property-read int|null $tos_units_count
 * @method static \Database\Factories\LoreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lore whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLore {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \App\Enums\LoreMediaTypeEnum $type
 * @property string|null $link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lore> $lores
 * @property-read int|null $lores_count
 * @method static \Database\Factories\LoreMediaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoreMedia whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLoreMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $base
 * @property string|null $icon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Terrain> $terrains
 * @property-read int|null $terrains_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\MarkerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Marker whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMarker {}
}

namespace App\Models{
/**
 * A "meta" — a regional / community grouping of players (e.g. "Boston",
 * "PNW", "FB Online"). Used for Round 1 same-meta-avoidance pairing and
 * for displaying community affiliation on player profiles.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentPlayer> $tournamentPlayers
 * @property-read int|null $tournament_players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\MetaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meta whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMeta {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $title
 * @property string $display_name
 * @property string $slug
 * @property int $character_id
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property string $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $blueprints
 * @property-read int|null $blueprints_count
 * @property-read \App\Models\Character $character
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $packages
 * @property-read int|null $packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PodLink> $podLinks
 * @property-read int|null $pod_links_count
 * @method static \Database\Factories\MiniatureFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Miniature whereVersion($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMiniature {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $legacy_m3e_name
 * @property string $slug
 * @property \App\Enums\GameSystemEnum $game_system
 * @property array<array-key, mixed>|null $factions
 * @property string|null $description
 * @property int|null $msrp
 * @property string|null $sku
 * @property string|null $upc
 * @property string|null $distributor_description
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property string $sculpt_version
 * @property \App\Enums\PackageCategoryEnum|null $category
 * @property bool $is_auto_generated
 * @property bool $is_preassembled
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $blueprints
 * @property-read int|null $blueprints_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PackageStoreLink> $storeLinks
 * @property-read int|null $store_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Unit> $tosUnits
 * @property-read int|null $tos_units_count
 * @method static \Database\Factories\PackageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDistributorDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereFactions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereGameSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsAutoGenerated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsPreassembled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereLegacyM3eName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereMsrp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereReleasedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSculptVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereUpc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPackage {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $package_id
 * @property string $store_name
 * @property string $url
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Package $package
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereStoreName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PackageStoreLink whereUrl($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPackageStoreLink {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \App\Enums\PodSourceEnum $source
 * @property string $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array<string> $faction_tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PodLink whereUrl($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPodLink {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $game_system
 * @property string $name
 * @property array<array-key, mixed> $query_params
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereGameSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereQueryParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedSearch whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSavedSearch {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \App\Enums\PoolSeasonEnum $season
 * @property string|null $selector
 * @property string|null $prerequisite
 * @property string $reveal
 * @property string $scoring
 * @property string $additional
 * @property array<array-key, mixed>|null $requirements
 * @property int|null $next_scheme_one_id
 * @property int|null $next_scheme_two_id
 * @property int|null $next_scheme_three_id
 * @property string|null $image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read string|null $image_url
 * @property-read Scheme|null $nextSchemeOne
 * @property-read Scheme|null $nextSchemeThree
 * @property-read Scheme|null $nextSchemeTwo
 * @method static \Database\Factories\SchemeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme forSeason(\App\Enums\PoolSeasonEnum $season)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereAdditional($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereNextSchemeOneId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereNextSchemeThreeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereNextSchemeTwoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme wherePrerequisite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereReveal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereScoring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereSelector($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scheme whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperScheme {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \App\Enums\PoolSeasonEnum $season
 * @property \App\Enums\SuitEnum|null $suit
 * @property string $setup
 * @property string $rules
 * @property string $scoring
 * @property string|null $additional_scoring
 * @property string|null $image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read string|null $image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\StrategyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy forSeason(\App\Enums\PoolSeasonEnum $season)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereAdditionalScoring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereScoring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereSetup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Strategy whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStrategy {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $body
 * @property \App\Enums\TOS\UsageLimitEnum|null $usage_limit
 * @property bool $is_general
 * @property int|null $allegiance_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Allegiance|null $allegiance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\UnitSide> $unitSides
 * @property-read int|null $unit_sides_count
 * @method static \Database\Factories\TOS\AbilityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability forAllegiance(\App\Models\TOS\Allegiance|int $allegiance)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability general()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereIsGeneral($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereUsageLimit($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAbility {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int|null $av
 * @property string|null $av_target
 * @property string|null $av_suits
 * @property int|null $tn
 * @property string|null $range
 * @property int|null $strength
 * @property bool $is_piercing
 * @property bool $is_accurate
 * @property bool $is_area
 * @property \App\Enums\TOS\UsageLimitEnum|null $usage_limit
 * @property string|null $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\Collection<int, ActionTypeEnum> $types
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\ActionTypeLink> $typeLinks
 * @property-read int|null $type_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\UnitSide> $unitSides
 * @property-read int|null $unit_sides_count
 * @method static \Database\Factories\TOS\ActionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereAv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereAvSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereAvTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereIsAccurate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereIsArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereIsPiercing($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereStrength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereTn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereUsageLimit($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAction {}
}

namespace App\Models\TOS{
/**
 * Junction row for tos_action_types. One row per (Action, ActionTypeEnum)
 * pair — supports rulebook p. 22's "some Actions are a combination of
 * several different Action Types."
 *
 * @property int $id
 * @property int $action_id
 * @property \App\Enums\TOS\ActionTypeEnum $type
 * @property int $sort_order
 * @property-read \App\Models\TOS\Action $action
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink whereActionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionTypeLink whereType($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperActionTypeLink {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $short_name
 * @property \App\Enums\TOS\AllegianceTypeEnum $type
 * @property \App\Enums\TOS\AllegianceTypeEnum|null $secondary_type
 * @property bool $is_syndicate
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $color_slug
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\AllegianceCard> $allegianceCards
 * @property-read int|null $allegiance_cards_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Stratagem> $stratagems
 * @property-read int|null $stratagems_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Unit> $units
 * @property-read int|null $units_count
 * @method static \Database\Factories\TOS\AllegianceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance mainAllegiances()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance ofType(\App\Enums\TOS\AllegianceTypeEnum|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance syndicates()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereColorSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereIsSyndicate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereSecondaryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allegiance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAllegiance {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property int $allegiance_id
 * @property string $slug
 * @property string $name
 * @property \App\Enums\TOS\AllegianceTypeEnum $type
 * @property \App\Enums\TOS\AllegianceTypeEnum|null $secondary_type
 * @property string|null $body
 * @property string|null $primary_body
 * @property string|null $image_path
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \App\Models\TOS\Allegiance $allegiance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Ability> $primaryAbilities
 * @property-read int|null $primary_abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Action> $primaryActions
 * @property-read int|null $primary_actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Trigger> $primaryTriggers
 * @property-read int|null $primary_triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @method static \Database\Factories\TOS\AllegianceCardFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard wherePrimaryBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereSecondaryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegianceCard whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAllegianceCard {}
}

namespace App\Models\TOS{
/**
 * Rule-evaluation helpers (`canAttachTo`, `slotLocations`, `isUnique`,
 * `hasSlotLimit`) all consult `$this->limits`. They each defensively call
 * `loadMissing('limits')`, which is idempotent — but production callers
 * (e.g. `CompanyController::attachAsset`) should still eager-load `limits`
 * up front so the rule walk doesn't trigger N+1 across a large picker list.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $scrip_cost
 * @property int|null $disable_count
 * @property int|null $scrap_count
 * @property string|null $body
 * @property string|null $image_path
 * @property string|null $back_image_path
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Allegiance> $allegiances
 * @property-read int|null $allegiances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\AssetLimit> $limits
 * @property-read int|null $limits_count
 * @method static \Database\Factories\TOS\AssetFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereBackImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereDisableCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereScrapCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereScripCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAsset {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property int $asset_id
 * @property \App\Enums\TOS\AssetLimitTypeEnum $limit_type
 * @property \App\Enums\TOS\AssetLimitParameterTypeEnum|null $parameter_type
 * @property string|null $parameter_value
 * @property int|null $parameter_unit_id
 * @property int|null $parameter_allegiance_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Asset $asset
 * @property-read \App\Models\TOS\Allegiance|null $parameterAllegiance
 * @property-read \App\Models\TOS\Unit|null $parameterUnit
 * @method static \Database\Factories\TOS\AssetLimitFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereLimitType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereParameterAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereParameterType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereParameterUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereParameterValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetLimit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAssetLimit {}
}

namespace App\Models\TOS{
/**
 * A roster of Units hired around a single Allegiance — the rulebook calls
 * this a "Company". The earlier Phase 1 scaffolding stood it up under the
 * Malifaux-flavoured "Crew" name; tables, models, routes, and pages were
 * renamed to "Company" once the rulebook nomenclature was confirmed.
 *
 * @property int $id
 * @property int $user_id
 * @property int $allegiance_id
 * @property \App\Enums\TOS\GarrisonFormatEnum|null $format
 * @property int|null $envoy_allegiance_id
 * @property int|null $garrison_id
 * @property string $slug
 * @property string|null $share_code
 * @property bool $is_public
 * @property string $name
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Allegiance $allegiance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\CompanyUnit> $commanderUnit
 * @property-read int|null $commander_unit_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\CompanyUnit> $companyUnits
 * @property-read int|null $company_units_count
 * @property-read \App\Models\TOS\Allegiance|null $envoyAllegiance
 * @property-read \App\Models\TOS\Garrison|null $garrison
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Stratagem> $stratagems
 * @property-read int|null $stratagems_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\TOS\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEnvoyAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereGarrisonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCompany {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property int $company_id
 * @property int $unit_id
 * @property int|null $sculpt_id
 * @property bool $is_commander
 * @property bool $is_combined_arms_child
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Asset> $assets
 * @property-read int|null $assets_count
 * @property-read \App\Models\TOS\Company $company
 * @property-read \App\Models\TOS\UnitSculpt|null $sculpt
 * @property-read \App\Models\TOS\Unit $unit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereIsCombinedArmsChild($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereIsCommander($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereSculptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyUnit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCompanyUnit {}
}

namespace App\Models\TOS{
/**
 * Tournament-level pool a player declares before a Fields-of-Glory event.
 * 
 * Distinct from Company: a Company is a single battlefield force assembled
 * around one Commander; a Garrison is the larger pool a player draws from
 * when building Companies between rounds. The validation profile (commander
 * cap, scrip ceiling, stratagem count, envoy count) is set by `format`.
 * 
 * Envoys are stored as a pivot to `tos_allegiance_cards` because the
 * codebase folded the old standalone Envoy entity into the Allegiance Card
 * Primary tier (see drop migration 2026_04_29_120000). The pivot keeps the
 * "envoys" name so the rules language survives in the schema.
 *
 * @property int $id
 * @property int $user_id
 * @property int $allegiance_id
 * @property string $slug
 * @property string|null $share_code
 * @property bool $is_public
 * @property string $name
 * @property \App\Enums\TOS\GarrisonFormatEnum $format
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Allegiance $allegiance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Asset> $assets
 * @property-read int|null $assets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\GarrisonUnit> $commanderUnits
 * @property-read int|null $commander_units_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\AllegianceCard> $envoys
 * @property-read int|null $envoys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\GarrisonUnit> $garrisonUnits
 * @property-read int|null $garrison_units_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Stratagem> $stratagems
 * @property-read int|null $stratagems_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\TOS\GarrisonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Garrison whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGarrison {}
}

namespace App\Models\TOS{
/**
 * One row per unit instance in a Garrison's pool. Mirrors `CompanyUnit` minus
 * the per-unit Asset pivot — Assets in a Garrison live at the Garrison level
 * (a pool with quantity), not attached to a specific unit row.
 *
 * @property int $id
 * @property int $garrison_id
 * @property int $unit_id
 * @property int|null $sculpt_id
 * @property bool $is_commander
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Garrison $garrison
 * @property-read \App\Models\TOS\UnitSculpt|null $sculpt
 * @property-read \App\Models\TOS\Unit $unit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereGarrisonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereIsCommander($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereSculptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GarrisonUnit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGarrisonUnit {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\UnitSpecialRulePivot|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Unit> $units
 * @property-read int|null $units_count
 * @method static \Database\Factories\TOS\SpecialUnitRuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpecialUnitRule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSpecialUnitRule {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int|null $allegiance_id
 * @property \App\Enums\TOS\AllegianceTypeEnum|null $allegiance_type
 * @property int $tactical_cost
 * @property string|null $effect
 * @property string|null $image_path
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Allegiance|null $allegiance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem availableTo(\App\Models\TOS\Allegiance $target)
 * @method static \Database\Factories\TOS\StratagemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereAllegianceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereAllegianceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereEffect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereTacticalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stratagem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStratagem {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $suits
 * @property int|null $margin_cost
 * @property \App\Enums\TOS\TriggerTimingEnum $timing
 * @property string|null $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Action> $actions
 * @property-read int|null $actions_count
 * @method static \Database\Factories\TOS\TriggerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereMarginCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereTiming($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTrigger {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $title
 * @property int $scrip
 * @property string|null $tactics
 * @property string|null $glory_tactics
 * @property string|null $description
 * @property string|null $lore_text
 * @property \App\Enums\TOS\AllegianceTypeEnum|null $restriction
 * @property int|null $combined_arms_child_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Allegiance> $allegiances
 * @property-read int|null $allegiances_count
 * @property-read Unit|null $combinedArmsChild
 * @property-read Unit|null $combinedArmsParent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lore> $lores
 * @property-read int|null $lores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $packages
 * @property-read int|null $packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\UnitSculpt> $sculpts
 * @property-read int|null $sculpts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\UnitSide> $sides
 * @property-read int|null $sides_count
 * @property-read \App\Models\TOS\UnitSpecialRulePivot|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\SpecialUnitRule> $specialUnitRules
 * @property-read int|null $special_unit_rules_count
 * @method static \Database\Factories\TOS\UnitFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit hireableFor(\App\Models\TOS\Allegiance $primary, ?\App\Models\TOS\Allegiance $envoy = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit hireableInto(\App\Models\TOS\Allegiance $allegiance)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit notCombinedArmsChild()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCombinedArmsChildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereGloryTactics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereLoreText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereRestriction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereScrip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereTactics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUnit {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property int $unit_id
 * @property string $slug
 * @property string|null $name
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property \Illuminate\Support\Carbon|null $release_date
 * @property string|null $box_reference
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TOS\Unit $unit
 * @method static \Database\Factories\TOS\UnitSculptFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereBoxReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSculpt whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUnitSculpt {}
}

namespace App\Models\TOS{
/**
 * 
 *
 * @property int $id
 * @property int $unit_id
 * @property \App\Enums\TOS\UnitSideEnum $side
 * @property int $speed
 * @property int $defense
 * @property int $willpower
 * @property int $armor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \App\Models\TOS\Unit $unit
 * @method static \Database\Factories\TOS\UnitSideFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereArmor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereDefense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereSide($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereSpeed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSide whereWillpower($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUnitSide {}
}

namespace App\Models\TOS{
/**
 * Pivot for tos_unit_special_rule. Custom because the `parameters` JSON column
 * needs casting on both reads AND writes (`withCasts` on the relation only
 * handles reads, so sync/attach calls would otherwise hit "Array to string
 * conversion" when persisting).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $special_unit_rule_id
 * @property array<array-key, mixed>|null $parameters
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot whereSpecialUnitRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitSpecialRulePivot whereUnitId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUnitSpecialRulePivot {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Marker> $markers
 * @property-read int|null $markers_count
 * @method static \Database\Factories\TerrainFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Terrain whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTerrain {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \App\Enums\TokenRemovalTimingEnum|null $removal_timing
 * @property bool $is_general
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $characterUpgrades
 * @property-read int|null $character_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Strategy> $strategies
 * @property-read int|null $strategies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\TokenFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token general()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereIsGeneral($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereRemovalTiming($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperToken {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property int $creator_id
 * @property int $encounter_size
 * @property \App\Enums\EncounterTypeEnum $encounter_type
 * @property int $planned_rounds
 * @property \App\Enums\PoolSeasonEnum $season
 * @property \App\Enums\TournamentStatusEnum $status
 * @property string|null $location
 * @property \Illuminate\Support\Carbon $event_date
 * @property int $round_time_limit
 * @property int $bye_tp
 * @property int $bye_diff
 * @property int $bye_vp
 * @property \App\Enums\TournamentTiebreakerEnum $tiebreaker_mode
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentGame> $games
 * @property-read int|null $games_count
 * @property-read string $season_label
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $organizers
 * @property-read int|null $organizers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentPlayer> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentRound> $rounds
 * @property-read int|null $rounds_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentRsvp> $rsvps
 * @property-read int|null $rsvps_count
 * @method static \Database\Factories\TournamentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament forOrganizer(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereByeDiff($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereByeTp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereByeVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereEncounterSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereEncounterType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereEventDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament wherePlannedRounds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereRoundTimeLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereTiebreakerMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTournament {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $tournament_round_id
 * @property int $player_one_id
 * @property int|null $player_two_id
 * @property string|null $player_one_faction
 * @property string|null $player_one_master
 * @property string|null $player_one_title
 * @property int|null $player_one_crew_build_id
 * @property int|null $player_one_vp
 * @property int|null $player_one_strategy_vp
 * @property int|null $player_one_scheme_vp
 * @property string|null $player_two_faction
 * @property string|null $player_two_master
 * @property string|null $player_two_title
 * @property int|null $player_two_crew_build_id
 * @property int|null $player_two_vp
 * @property int|null $player_two_strategy_vp
 * @property int|null $player_two_scheme_vp
 * @property bool $is_bye
 * @property int $is_manual
 * @property bool $is_forfeit
 * @property int|null $forfeit_player_id
 * @property \App\Enums\TournamentGameResultEnum $result
 * @property int|null $table_number
 * @property int|null $game_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TournamentPlayer|null $forfeitPlayer
 * @property-read \App\Models\TournamentPlayer $playerOne
 * @property-read \App\Models\CrewBuild|null $playerOneCrewBuild
 * @property-read \App\Models\TournamentPlayer|null $playerTwo
 * @property-read \App\Models\CrewBuild|null $playerTwoCrewBuild
 * @property-read \App\Models\TournamentRound $round
 * @property-read \App\Models\Game|null $trackerGame
 * @method static \Database\Factories\TournamentGameFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereForfeitPlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereIsBye($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereIsForfeit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereIsManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneCrewBuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneMaster($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneSchemeVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneStrategyVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerOneVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoCrewBuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoMaster($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoSchemeVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoStrategyVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame wherePlayerTwoVp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereTableNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereTournamentRoundId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentGame whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTournamentGame {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $tournament_id
 * @property int|null $user_id
 * @property int|null $meta_id
 * @property string $display_name
 * @property \App\Enums\FactionEnum|null $faction
 * @property bool $is_ringer
 * @property bool $is_disqualified
 * @property \Illuminate\Support\Carbon|null $disqualified_at
 * @property int|null $dropped_after_round
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentGame> $gamesAsPlayerOne
 * @property-read int|null $games_as_player_one_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentGame> $gamesAsPlayerTwo
 * @property-read int|null $games_as_player_two_count
 * @property-read \App\Models\Meta|null $meta
 * @property-read \App\Models\Tournament $tournament
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\TournamentPlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereDisqualifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereDroppedAfterRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereIsDisqualified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereIsRinger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereMetaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPlayer whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTournamentPlayer {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $tournament_id
 * @property int $round_number
 * @property \App\Enums\DeploymentEnum|null $deployment
 * @property int|null $strategy_id
 * @property array<array-key, mixed>|null $scheme_pool
 * @property \App\Enums\TournamentRoundStatusEnum $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentGame> $games
 * @property-read int|null $games_count
 * @property-read \App\Models\Strategy|null $strategy
 * @property-read \App\Models\Tournament $tournament
 * @method static \Database\Factories\TournamentRoundFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereDeployment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereRoundNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereSchemePool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereStrategyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRound whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTournamentRound {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property \App\Enums\FactionEnum|null $faction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tournament $tournament
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRsvp whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTournamentRsvp {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $channel_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $url
 * @property \App\Enums\TransmissionTypeEnum $transmission_type
 * @property \App\Enums\ContentTypeEnum $content_type
 * @property array<array-key, mixed>|null $factions
 * @property \Illuminate\Support\Carbon|null $release_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Channel $channel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereChannelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereFactions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereTransmissionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transmission whereUrl($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTransmission {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string $slug
 * @property int $stone_cost
 * @property string|null $suits
 * @property string|null $description
 * @property string|null $internal_notes
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @method static \Database\Factories\TriggerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereInternalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereStoneCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereSuits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTrigger {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Enums\GameModeTypeEnum $game_mode_type
 * @property string $name
 * @property string $slug
 * @property \App\Enums\UpgradeDomainTypeEnum $domain
 * @property string|null $description
 * @property array<array-key, mixed>|null $hiring_rules
 * @property int|null $power_bar_count
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $plentiful
 * @property \App\Enums\UpgradeLimitationEnum|null $limitations
 * @property \App\Enums\FactionEnum|null $faction
 * @property \App\Enums\UpgradeTypeEnum|null $type
 * @property string|null $campaign_upgrade_kind
 * @property int|null $campaign_br
 * @property int|null $campaign_cc
 * @property string|null $campaign_pool_suit_a
 * @property string|null $campaign_pool_suit_b
 * @property bool $campaign_is_always_available
 * @property bool $campaign_ttw_only
 * @property bool $campaign_is_omens_mark
 * @property bool $campaign_is_unique
 * @property bool $campaign_leader_only
 * @property bool $campaign_non_unique_only
 * @property bool $campaign_annihilate_after_game
 * @property bool $campaign_is_red_joker_entry
 * @property int|null $campaign_flip_value
 * @property string|null $campaign_suit_pool
 * @property bool $campaign_is_traitor
 * @property bool $campaign_is_close_call
 * @property bool $campaign_annihilates_model
 * @property bool $campaign_reflip_if_no_triggers
 * @property bool $campaign_reflip_if_master_or_totem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Marker> $markers
 * @property-read int|null $markers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $masters
 * @property-read int|null $masters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PodLink> $podLinks
 * @property-read int|null $pod_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @method static \Database\Factories\UpgradeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade forCharacters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade forCrews()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade forGameMode(\App\Enums\GameModeTypeEnum $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade standard()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignAnnihilateAfterGame($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignAnnihilatesModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignBr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignCc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignFlipValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsAlwaysAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsCloseCall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsOmensMark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsRedJokerEntry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsTraitor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignIsUnique($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignLeaderOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignNonUniqueOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignPoolSuitA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignPoolSuitB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignReflipIfMasterOrTotem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignReflipIfNoTriggers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignSuitPool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignTtwOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCampaignUpgradeKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereGameModeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereHiringRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereLimitations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade wherePlentiful($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade wherePowerBarCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUpgrade {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $meta_id
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $collection_share_code
 * @property int $collection_is_public
 * @property string|null $tos_collection_share_code
 * @property int $tos_collection_is_public
 * @property \Illuminate\Support\Carbon|null $supporter_since
 * @property bool $show_on_supporters_page
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlogPost> $blogPosts
 * @property-read int|null $blog_posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Channel> $channels
 * @property-read int|null $channels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\Asset> $collectionAssets
 * @property-read int|null $collection_assets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $collectionMiniatures
 * @property-read int|null $collection_miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package> $collectionPackages
 * @property-read int|null $collection_packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TOS\UnitSculpt> $collectionUnitSculpts
 * @property-read int|null $collection_unit_sculpts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CrewBuild> $crewBuilds
 * @property-read int|null $crew_builds_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomCharacter> $customCharacters
 * @property-read int|null $custom_characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Friendship> $friendshipsReceived
 * @property-read int|null $friendships_received_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Friendship> $friendshipsSent
 * @property-read int|null $friendships_sent_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game> $games
 * @property-read int|null $games_count
 * @property-read bool $is_supporter
 * @property-read \App\Models\Meta|null $meta
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SavedSearch> $savedSearches
 * @property-read int|null $saved_searches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Wishlist> $wishlists
 * @property-read int|null $wishlists_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCollectionIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCollectionShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMetaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShowOnSupportersPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSupporterSince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTosCollectionIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTosCollectionShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $share_code
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WishlistItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereShareCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wishlist whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWishlist {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $wishlist_id
 * @property string $wishlistable_type
 * @property int $wishlistable_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Wishlist $wishlist
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $wishlistable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereWishlistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereWishlistableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WishlistItem whereWishlistableType($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWishlistItem {}
}

