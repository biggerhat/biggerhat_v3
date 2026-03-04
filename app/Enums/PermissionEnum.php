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
