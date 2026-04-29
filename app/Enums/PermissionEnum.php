<?php

namespace App\Enums;

use App\Attributes\PermissionGroup;
use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;
use ReflectionEnum;

enum PermissionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    #[PermissionGroup(PermissionGroupEnum::User)]
    case ViewUser = 'view_user';
    #[PermissionGroup(PermissionGroupEnum::User)]
    case EditUser = 'edit_user';
    #[PermissionGroup(PermissionGroupEnum::User)]
    case DeleteUser = 'delete_user';

    #[PermissionGroup(PermissionGroupEnum::Role)]
    case ViewRole = 'view_role';
    #[PermissionGroup(PermissionGroupEnum::Role)]
    case AddRole = 'add_role';
    #[PermissionGroup(PermissionGroupEnum::Role)]
    case EditRole = 'edit_role';
    #[PermissionGroup(PermissionGroupEnum::Role)]
    case DeleteRole = 'delete_role';

    #[PermissionGroup(PermissionGroupEnum::Blog)]
    case CreatePosts = 'create_posts';
    #[PermissionGroup(PermissionGroupEnum::Blog)]
    case EditPosts = 'edit_posts';
    #[PermissionGroup(PermissionGroupEnum::Blog)]
    case PublishPosts = 'publish_posts';
    #[PermissionGroup(PermissionGroupEnum::Blog)]
    case DeletePosts = 'delete_posts';
    #[PermissionGroup(PermissionGroupEnum::Blog)]
    case ManageAllPosts = 'manage_all_posts';

    #[PermissionGroup(PermissionGroupEnum::Keyword)]
    case ViewKeyword = 'view_keyword';
    #[PermissionGroup(PermissionGroupEnum::Keyword)]
    case EditKeyword = 'edit_keyword';
    #[PermissionGroup(PermissionGroupEnum::Keyword)]
    case DeleteKeyword = 'delete_keyword';

    #[PermissionGroup(PermissionGroupEnum::Characteristic)]
    case ViewCharacteristic = 'view_characteristic';
    #[PermissionGroup(PermissionGroupEnum::Characteristic)]
    case EditCharacteristic = 'edit_characteristic';
    #[PermissionGroup(PermissionGroupEnum::Characteristic)]
    case DeleteCharacteristic = 'delete_characteristic';

    #[PermissionGroup(PermissionGroupEnum::Character)]
    case ViewCharacter = 'view_character';
    #[PermissionGroup(PermissionGroupEnum::Character)]
    case EditCharacter = 'edit_character';
    #[PermissionGroup(PermissionGroupEnum::Character)]
    case DeleteCharacter = 'delete_character';

    #[PermissionGroup(PermissionGroupEnum::Action)]
    case ViewAction = 'view_action';
    #[PermissionGroup(PermissionGroupEnum::Action)]
    case EditAction = 'edit_action';
    #[PermissionGroup(PermissionGroupEnum::Action)]
    case DeleteAction = 'delete_action';

    #[PermissionGroup(PermissionGroupEnum::Ability)]
    case ViewAbility = 'view_ability';
    #[PermissionGroup(PermissionGroupEnum::Ability)]
    case EditAbility = 'edit_ability';
    #[PermissionGroup(PermissionGroupEnum::Ability)]
    case DeleteAbility = 'delete_ability';

    #[PermissionGroup(PermissionGroupEnum::Trigger)]
    case ViewTrigger = 'view_trigger';
    #[PermissionGroup(PermissionGroupEnum::Trigger)]
    case EditTrigger = 'edit_trigger';
    #[PermissionGroup(PermissionGroupEnum::Trigger)]
    case DeleteTrigger = 'delete_trigger';

    #[PermissionGroup(PermissionGroupEnum::Miniature)]
    case ViewMiniature = 'view_miniature';
    #[PermissionGroup(PermissionGroupEnum::Miniature)]
    case EditMiniature = 'edit_miniature';
    #[PermissionGroup(PermissionGroupEnum::Miniature)]
    case DeleteMiniature = 'delete_miniature';

    #[PermissionGroup(PermissionGroupEnum::Upgrade)]
    case ViewUpgrade = 'view_upgrade';
    #[PermissionGroup(PermissionGroupEnum::Upgrade)]
    case EditUpgrade = 'edit_upgrade';
    #[PermissionGroup(PermissionGroupEnum::Upgrade)]
    case DeleteUpgrade = 'delete_upgrade';

    #[PermissionGroup(PermissionGroupEnum::Crew)]
    case ViewCrew = 'view_crew';
    #[PermissionGroup(PermissionGroupEnum::Crew)]
    case EditCrew = 'edit_crew';
    #[PermissionGroup(PermissionGroupEnum::Crew)]
    case DeleteCrew = 'delete_crew';

    #[PermissionGroup(PermissionGroupEnum::Token)]
    case ViewToken = 'view_token';
    #[PermissionGroup(PermissionGroupEnum::Token)]
    case EditToken = 'edit_token';
    #[PermissionGroup(PermissionGroupEnum::Token)]
    case DeleteToken = 'delete_token';

    #[PermissionGroup(PermissionGroupEnum::Marker)]
    case ViewMarker = 'view_marker';
    #[PermissionGroup(PermissionGroupEnum::Marker)]
    case EditMarker = 'edit_marker';
    #[PermissionGroup(PermissionGroupEnum::Marker)]
    case DeleteMarker = 'delete_marker';

    #[PermissionGroup(PermissionGroupEnum::Scheme)]
    case ViewScheme = 'view_scheme';
    #[PermissionGroup(PermissionGroupEnum::Scheme)]
    case EditScheme = 'edit_scheme';
    #[PermissionGroup(PermissionGroupEnum::Scheme)]
    case DeleteScheme = 'delete_scheme';

    #[PermissionGroup(PermissionGroupEnum::Strategy)]
    case ViewStrategy = 'view_strategy';
    #[PermissionGroup(PermissionGroupEnum::Strategy)]
    case EditStrategy = 'edit_strategy';
    #[PermissionGroup(PermissionGroupEnum::Strategy)]
    case DeleteStrategy = 'delete_strategy';

    #[PermissionGroup(PermissionGroupEnum::Package)]
    case ViewPackage = 'view_package';
    #[PermissionGroup(PermissionGroupEnum::Package)]
    case EditPackage = 'edit_package';
    #[PermissionGroup(PermissionGroupEnum::Package)]
    case DeletePackage = 'delete_package';

    #[PermissionGroup(PermissionGroupEnum::Lore)]
    case ViewLore = 'view_lore';
    #[PermissionGroup(PermissionGroupEnum::Lore)]
    case EditLore = 'edit_lore';
    #[PermissionGroup(PermissionGroupEnum::Lore)]
    case DeleteLore = 'delete_lore';

    #[PermissionGroup(PermissionGroupEnum::Blueprint)]
    case ViewBlueprint = 'view_blueprint';
    #[PermissionGroup(PermissionGroupEnum::Blueprint)]
    case EditBlueprint = 'edit_blueprint';
    #[PermissionGroup(PermissionGroupEnum::Blueprint)]
    case DeleteBlueprint = 'delete_blueprint';

    #[PermissionGroup(PermissionGroupEnum::Channel)]
    case ViewChannel = 'view_channel';
    #[PermissionGroup(PermissionGroupEnum::Channel)]
    case EditChannel = 'edit_channel';
    #[PermissionGroup(PermissionGroupEnum::Channel)]
    case DeleteChannel = 'delete_channel';
    #[PermissionGroup(PermissionGroupEnum::Channel)]
    case ManageOwnTransmissions = 'manage_own_transmissions';

    #[PermissionGroup(PermissionGroupEnum::PodLink)]
    case ViewPodLink = 'view_pod_link';
    #[PermissionGroup(PermissionGroupEnum::PodLink)]
    case EditPodLink = 'edit_pod_link';
    #[PermissionGroup(PermissionGroupEnum::PodLink)]
    case DeletePodLink = 'delete_pod_link';

    #[PermissionGroup(PermissionGroupEnum::Tournament)]
    case CreateTournaments = 'create_tournaments';
    #[PermissionGroup(PermissionGroupEnum::Tournament)]
    case ManageTournaments = 'manage_tournaments';

    #[PermissionGroup(PermissionGroupEnum::Feedback)]
    case ViewFeedback = 'view_feedback';
    #[PermissionGroup(PermissionGroupEnum::Feedback)]
    case ManageFeedback = 'manage_feedback';

    #[PermissionGroup(PermissionGroupEnum::Tos)]
    case ViewTos = 'view_tos';

    #[PermissionGroup(PermissionGroupEnum::TosAllegiance)]
    case ViewTosAllegiance = 'view_tos_allegiance';
    #[PermissionGroup(PermissionGroupEnum::TosAllegiance)]
    case EditTosAllegiance = 'edit_tos_allegiance';
    #[PermissionGroup(PermissionGroupEnum::TosAllegiance)]
    case DeleteTosAllegiance = 'delete_tos_allegiance';

    #[PermissionGroup(PermissionGroupEnum::TosAllegianceCard)]
    case ViewTosAllegianceCard = 'view_tos_allegiance_card';
    #[PermissionGroup(PermissionGroupEnum::TosAllegianceCard)]
    case EditTosAllegianceCard = 'edit_tos_allegiance_card';
    #[PermissionGroup(PermissionGroupEnum::TosAllegianceCard)]
    case DeleteTosAllegianceCard = 'delete_tos_allegiance_card';

    #[PermissionGroup(PermissionGroupEnum::TosUnit)]
    case ViewTosUnit = 'view_tos_unit';
    #[PermissionGroup(PermissionGroupEnum::TosUnit)]
    case EditTosUnit = 'edit_tos_unit';
    #[PermissionGroup(PermissionGroupEnum::TosUnit)]
    case DeleteTosUnit = 'delete_tos_unit';

    #[PermissionGroup(PermissionGroupEnum::TosSculpt)]
    case ViewTosSculpt = 'view_tos_sculpt';
    #[PermissionGroup(PermissionGroupEnum::TosSculpt)]
    case EditTosSculpt = 'edit_tos_sculpt';
    #[PermissionGroup(PermissionGroupEnum::TosSculpt)]
    case DeleteTosSculpt = 'delete_tos_sculpt';

    #[PermissionGroup(PermissionGroupEnum::TosSpecialUnitRule)]
    case ViewTosSpecialUnitRule = 'view_tos_special_unit_rule';
    #[PermissionGroup(PermissionGroupEnum::TosSpecialUnitRule)]
    case EditTosSpecialUnitRule = 'edit_tos_special_unit_rule';
    #[PermissionGroup(PermissionGroupEnum::TosSpecialUnitRule)]
    case DeleteTosSpecialUnitRule = 'delete_tos_special_unit_rule';

    #[PermissionGroup(PermissionGroupEnum::TosAbility)]
    case ViewTosAbility = 'view_tos_ability';
    #[PermissionGroup(PermissionGroupEnum::TosAbility)]
    case EditTosAbility = 'edit_tos_ability';
    #[PermissionGroup(PermissionGroupEnum::TosAbility)]
    case DeleteTosAbility = 'delete_tos_ability';

    #[PermissionGroup(PermissionGroupEnum::TosAction)]
    case ViewTosAction = 'view_tos_action';
    #[PermissionGroup(PermissionGroupEnum::TosAction)]
    case EditTosAction = 'edit_tos_action';
    #[PermissionGroup(PermissionGroupEnum::TosAction)]
    case DeleteTosAction = 'delete_tos_action';

    #[PermissionGroup(PermissionGroupEnum::TosTrigger)]
    case ViewTosTrigger = 'view_tos_trigger';
    #[PermissionGroup(PermissionGroupEnum::TosTrigger)]
    case EditTosTrigger = 'edit_tos_trigger';
    #[PermissionGroup(PermissionGroupEnum::TosTrigger)]
    case DeleteTosTrigger = 'delete_tos_trigger';

    #[PermissionGroup(PermissionGroupEnum::TosAsset)]
    case ViewTosAsset = 'view_tos_asset';
    #[PermissionGroup(PermissionGroupEnum::TosAsset)]
    case EditTosAsset = 'edit_tos_asset';
    #[PermissionGroup(PermissionGroupEnum::TosAsset)]
    case DeleteTosAsset = 'delete_tos_asset';

    #[PermissionGroup(PermissionGroupEnum::TosStratagem)]
    case ViewTosStratagem = 'view_tos_stratagem';
    #[PermissionGroup(PermissionGroupEnum::TosStratagem)]
    case EditTosStratagem = 'edit_tos_stratagem';
    #[PermissionGroup(PermissionGroupEnum::TosStratagem)]
    case DeleteTosStratagem = 'delete_tos_stratagem';

    /**
     * @return list<array{name: string, value: string}>
     */
    public static function getPermissionsByGroup(PermissionGroupEnum $targetGroup): array
    {
        $result = [];

        $reflection = new ReflectionEnum(self::class);

        foreach ($reflection->getCases() as $case) {
            foreach ($case->getAttributes(PermissionGroup::class) as $attribute) {
                /** @var PermissionGroup $permissionGroup */
                $permissionGroup = $attribute->newInstance();
                if ($permissionGroup->permissionGroup === $targetGroup) {
                    /** @var PermissionEnum $permission */
                    $permission = $case->getValue();
                    $result[] = ['name' => $permission->label(), 'value' => $permission->value];
                }
            }
        }

        return $result;
    }
}
