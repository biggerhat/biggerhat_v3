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
 * @property string $name
 * @property string $slug
 * @property string|null $suits
 * @property string|null $defensive_ability_type
 * @property int $costs_stone
 * @property string|null $description
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\AbilityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereCostsStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDefensiveAbilityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ability whereDescription($value)
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
 * @property string $name
 * @property string $type
 * @property int $is_signature
 * @property int $costs_stone
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\ActionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereCostsStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDamage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Action whereDescription($value)
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
 * @property string $image
 * @property string $sculpt_version
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @method static \Database\Factories\BlueprintFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereSculptVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Blueprint whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBlueprint {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $title
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
 * @property bool $is_beta
 * @property bool $is_hidden
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Characteristic> $characteristics
 * @property-read int|null $characteristics_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $crewUpgrades
 * @property-read int|null $crew_upgrades_count
 * @property-read string $faction_color
 * @property-read Character|null $isTotemFor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Marker> $markers
 * @property-read int|null $markers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $promotionalMiniatures
 * @property-read int|null $promotional_miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $standardMiniatures
 * @property-read int|null $standard_miniatures_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @property-read Character|null $totem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\CharacterFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character forStation(\App\Enums\CharacterStationEnum $station)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereCrewUpgradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDefense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDefenseSuit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Character whereFaction($value)
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
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $masters
 * @property-read int|null $masters_count
 * @method static \Database\Factories\KeywordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereDescription($value)
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
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $base
 * @property string|null $icon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
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
 * @property string $slug
 * @property string|null $description
 * @property int|null $msrp
 * @property string|null $sku
 * @property string|null $upc
 * @property string|null $distributor_description
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property string $sculpt_version
 * @property int $is_preassembled
 * @property string|null $released_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Miniature> $miniatures
 * @property-read int|null $miniatures_count
 * @method static \Database\Factories\PackageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDistributorDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereFrontImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsPreassembled($value)
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
 * @property string $name
 * @property string $slug
 * @property \App\Enums\PoolSeasonEnum $season
 * @property string|null $selector
 * @property string|null $prerequisite
 * @property string $reveal
 * @property string $scoring
 * @property string $additional
 * @property int|null $next_scheme_one_id
 * @property int|null $next_scheme_two_id
 * @property int|null $next_scheme_three_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $image
 * @method static \Database\Factories\SchemeFactory factory($count = null, $state = [])
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $image
 * @method static \Database\Factories\StrategyFactory factory($count = null, $state = [])
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Upgrade> $upgrades
 * @property-read int|null $upgrades_count
 * @method static \Database\Factories\TokenFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereName($value)
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
 * @property string $name
 * @property string $slug
 * @property int $costs_stone
 * @property string|null $suits
 * @property string|null $description
 * @property string|null $internal_notes
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @method static \Database\Factories\TriggerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereCostsStone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereInternalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trigger whereSlug($value)
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
 * @property string $name
 * @property string $slug
 * @property \App\Enums\UpgradeDomainTypeEnum $domain
 * @property string|null $description
 * @property int|null $power_bar_count
 * @property string|null $front_image
 * @property string|null $back_image
 * @property string|null $combination_image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $plentiful
 * @property \App\Enums\UpgradeLimitationEnum|null $limitations
 * @property \App\Enums\UpgradeTypeEnum|null $type
 * @property \App\Enums\FactionEnum|null $faction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 * @property-read int|null $actions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $characters
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Marker> $markers
 * @property-read int|null $markers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Character> $masters
 * @property-read int|null $masters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trigger> $triggers
 * @property-read int|null $triggers_count
 * @method static \Database\Factories\UpgradeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade forCharacters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade forCrews()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade toSelectOptions(string $column, $primaryKeyColumn = 'id')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereBackImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCombinationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereFaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upgrade whereFrontImage($value)
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
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

