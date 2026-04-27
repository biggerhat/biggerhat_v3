<?php

use App\Http\Controllers\Admin\AbilityAdminController;
use App\Http\Controllers\Admin\ActionAdminController;
use App\Http\Controllers\Admin\ActivityAdminController;
use App\Http\Controllers\Admin\AnnouncementsAdminController;
use App\Http\Controllers\Admin\ApiTokensAdminController;
use App\Http\Controllers\Admin\BlogCategoryAdminController;
use App\Http\Controllers\Admin\BlogPostAdminController;
use App\Http\Controllers\Admin\BlueprintAdminController;
use App\Http\Controllers\Admin\CacheAdminController;
use App\Http\Controllers\Admin\ChannelAdminController;
use App\Http\Controllers\Admin\CharacterAdminController;
use App\Http\Controllers\Admin\CharacteristicAdminController;
use App\Http\Controllers\Admin\CrewAdminController;
use App\Http\Controllers\Admin\CustomCardModerationAdminController;
use App\Http\Controllers\Admin\DashboardAdminController;
use App\Http\Controllers\Admin\FailedJobsAdminController;
use App\Http\Controllers\Admin\FeatureFlagsAdminController;
use App\Http\Controllers\Admin\FeedbackAdminController;
use App\Http\Controllers\Admin\ImageHealthAdminController;
use App\Http\Controllers\Admin\KeywordAdminController;
use App\Http\Controllers\Admin\LoreAdminController;
use App\Http\Controllers\Admin\LoreMediaAdminController;
use App\Http\Controllers\Admin\MaintenanceAdminController;
use App\Http\Controllers\Admin\MarkerAdminController;
use App\Http\Controllers\Admin\MiniatureAdminController;
use App\Http\Controllers\Admin\PackageAdminController;
use App\Http\Controllers\Admin\PodLinkAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\ScheduleAdminController;
use App\Http\Controllers\Admin\SchemeAdminController;
use App\Http\Controllers\Admin\SessionsAdminController;
use App\Http\Controllers\Admin\StrategyAdminController;
use App\Http\Controllers\Admin\TokenAdminController;
use App\Http\Controllers\Admin\TournamentOverrideAdminController;
use App\Http\Controllers\Admin\TransmissionAdminController;
use App\Http\Controllers\Admin\TrashAdminController;
use App\Http\Controllers\Admin\TriggerAdminController;
use App\Http\Controllers\Admin\UpgradeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\TOS\Admin\AbilityAdminController as TosAbilityAdminController;
use App\Http\Controllers\TOS\Admin\ActionAdminController as TosActionAdminController;
use App\Http\Controllers\TOS\Admin\AllegianceAdminController as TosAllegianceAdminController;
use App\Http\Controllers\TOS\Admin\AllegianceCardAdminController as TosAllegianceCardAdminController;
use App\Http\Controllers\TOS\Admin\AssetAdminController as TosAssetAdminController;
use App\Http\Controllers\TOS\Admin\EnvoyAdminController as TosEnvoyAdminController;
use App\Http\Controllers\TOS\Admin\SculptAdminController as TosSculptAdminController;
use App\Http\Controllers\TOS\Admin\SpecialUnitRuleAdminController as TosSpecialUnitRuleAdminController;
use App\Http\Controllers\TOS\Admin\StratagemAdminController as TosStratagemAdminController;
use App\Http\Controllers\TOS\Admin\TriggerAdminController as TosTriggerAdminController;
use App\Http\Controllers\TOS\Admin\UnitAdminController as TosUnitAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified', 'admin.any'])->name('admin.')->group(function () {
    Route::get('/', [DashboardAdminController::class, 'index'])->name('dashboard');

    // Super-admin-only diagnostics + tooling.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/activity', [ActivityAdminController::class, 'index'])->name('activity.index');

        Route::controller(FailedJobsAdminController::class)->prefix('failed-jobs')->name('failed_jobs.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/retry-all', 'retryAll')->name('retry_all');
            Route::post('/flush', 'flush')->name('flush');
            Route::post('/{uuid}/retry', 'retry')->name('retry');
            Route::post('/{uuid}/delete', 'destroy')->name('delete');
        });

        Route::controller(FeatureFlagsAdminController::class)->prefix('features')->name('features.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{name}', 'update')->name('update');
        });

        Route::controller(ApiTokensAdminController::class)->prefix('api-tokens')->name('api_tokens.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/{id}/delete', 'destroy')->name('delete');
        });

        Route::controller(SessionsAdminController::class)->prefix('sessions')->name('sessions.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{id}/delete', 'destroy')->name('delete');
            Route::post('/user/{userId}/delete-all', 'destroyAllForUser')->name('delete_all_for_user');
        });

        Route::controller(CustomCardModerationAdminController::class)->prefix('custom-cards')->name('custom_cards.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{kind}/{id}/unpublish', 'unpublish')->name('unpublish');
            Route::post('/{kind}/{id}/delete', 'destroy')->name('delete');
        });

        Route::controller(ImageHealthAdminController::class)->prefix('image-health')->name('image_health.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/scan', 'scan')->name('scan');
        });

        Route::controller(TournamentOverrideAdminController::class)->prefix('tournaments')->name('tournaments.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{tournament}', 'show')->name('show');
            Route::post('/{tournament}/force-status', 'forceTournamentStatus')->name('force_status');
            Route::post('/{tournament}/rounds/{round}/force-status', 'forceRoundStatus')->name('rounds.force_status');
            Route::post('/{tournament}/delete', 'destroyTournament')->name('delete');
        });

        Route::controller(TrashAdminController::class)->prefix('trash')->name('trash.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{kind}/{id}/restore', 'restore')->name('restore');
            Route::post('/{kind}/{id}/force-delete', 'forceDestroy')->name('force_delete');
        });

        Route::controller(MaintenanceAdminController::class)->prefix('maintenance')->name('maintenance.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/down', 'down')->name('down');
            Route::post('/up', 'up')->name('up');
        });

        Route::controller(ScheduleAdminController::class)->prefix('schedule')->name('schedule.')->group(function () {
            Route::get('/', 'index')->name('index');
        });

        Route::controller(CacheAdminController::class)->prefix('cache')->name('cache.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{key}/clear', 'clear')->name('clear');
        });

        Route::controller(AnnouncementsAdminController::class)->prefix('announcements')->name('announcements.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/{announcement}', 'update')->name('update');
            Route::delete('/{announcement}', 'destroy')->name('delete');
        });
    });

    Route::controller(KeywordAdminController::class)->prefix('keywords')->name('keywords.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_keyword');
        Route::get('/edit/{keyword}', 'edit')->name('edit')->middleware('permission:view_keyword');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_keyword');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_keyword');
        Route::post('/update/{keyword}', 'update')->name('update')->middleware('permission:edit_keyword');
        Route::post('/delete/{keyword}', 'delete')->name('delete')->middleware('permission:delete_keyword');
    });

    Route::controller(CharacteristicAdminController::class)->prefix('characteristics')->name('characteristics.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_characteristic');
        Route::get('/edit/{characteristic}', 'edit')->name('edit')->middleware('permission:view_characteristic');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_characteristic');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_characteristic');
        Route::post('/update/{characteristic}', 'update')->name('update')->middleware('permission:edit_characteristic');
        Route::post('/delete/{characteristic}', 'delete')->name('delete')->middleware('permission:delete_characteristic');
    });

    Route::controller(CharacterAdminController::class)->prefix('characters')->name('characters.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_character');
        Route::get('/edit/{character}', 'edit')->name('edit')->middleware('permission:view_character');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_character');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_character');
        Route::post('/update/{character}', 'update')->name('update')->middleware('permission:edit_character');
        Route::post('/delete/{character}', 'delete')->name('delete')->middleware('permission:delete_character');
    });

    Route::controller(ActionAdminController::class)->prefix('actions')->name('actions.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_action');
        Route::get('/edit/{action}', 'edit')->name('edit')->middleware('permission:view_action');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_action');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_action');
        Route::post('/update/{action}', 'update')->name('update')->middleware('permission:edit_action');
        Route::post('/delete/{action}', 'delete')->name('delete')->middleware('permission:delete_action');
    });

    Route::controller(AbilityAdminController::class)->prefix('abilities')->name('abilities.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_ability');
        Route::get('/edit/{ability}', 'edit')->name('edit')->middleware('permission:view_ability');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_ability');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_ability');
        Route::post('/update/{ability}', 'update')->name('update')->middleware('permission:edit_ability');
        Route::post('/delete/{ability}', 'delete')->name('delete')->middleware('permission:delete_ability');
    });

    Route::controller(TriggerAdminController::class)->prefix('triggers')->name('triggers.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_trigger');
        Route::get('/edit/{trigger}', 'edit')->name('edit')->middleware('permission:view_trigger');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_trigger');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_trigger');
        Route::post('/update/{trigger}', 'update')->name('update')->middleware('permission:edit_trigger');
        Route::post('/delete/{trigger}', 'delete')->name('delete')->middleware('permission:delete_trigger');
    });

    Route::controller(MiniatureAdminController::class)->prefix('miniatures')->name('miniatures.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_miniature');
        Route::get('/edit/{miniature}', 'edit')->name('edit')->middleware('permission:view_miniature');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_miniature');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_miniature');
        Route::post('/update/{miniature}', 'update')->name('update')->middleware('permission:edit_miniature');
        Route::post('/delete/{miniature}', 'delete')->name('delete')->middleware('permission:delete_miniature');
    });

    Route::controller(UpgradeAdminController::class)->prefix('upgrades')->name('upgrades.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_upgrade');
        Route::get('/edit/{upgrade}', 'edit')->name('edit')->middleware('permission:view_upgrade');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_upgrade');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_upgrade');
        Route::post('/update/{upgrade}', 'update')->name('update')->middleware('permission:edit_upgrade');
        Route::post('/delete/{upgrade}', 'delete')->name('delete')->middleware('permission:delete_upgrade');
    });

    Route::controller(CrewAdminController::class)->prefix('crews')->name('crews.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_crew');
        Route::get('/edit/{upgrade}', 'edit')->name('edit')->middleware('permission:view_crew');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_crew');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_crew');
        Route::post('/update/{upgrade}', 'update')->name('update')->middleware('permission:edit_crew');
        Route::post('/delete/{upgrade}', 'delete')->name('delete')->middleware('permission:delete_crew');
    });

    Route::controller(TokenAdminController::class)->prefix('tokens')->name('tokens.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_token');
        Route::get('/edit/{token}', 'edit')->name('edit')->middleware('permission:view_token');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_token');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_token');
        Route::post('/update/{token}', 'update')->name('update')->middleware('permission:edit_token');
        Route::post('/delete/{token}', 'delete')->name('delete')->middleware('permission:delete_token');
    });

    Route::controller(MarkerAdminController::class)->prefix('markers')->name('markers.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_marker');
        Route::get('/edit/{marker}', 'edit')->name('edit')->middleware('permission:view_marker');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_marker');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_marker');
        Route::post('/update/{marker}', 'update')->name('update')->middleware('permission:edit_marker');
        Route::post('/delete/{marker}', 'delete')->name('delete')->middleware('permission:delete_marker');
    });

    Route::controller(SchemeAdminController::class)->prefix('schemes')->name('schemes.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_scheme');
        Route::get('/edit/{scheme}', 'edit')->name('edit')->middleware('permission:view_scheme');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_scheme');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_scheme');
        Route::post('/update/{scheme}', 'update')->name('update')->middleware('permission:edit_scheme');
        Route::post('/delete/{scheme}', 'delete')->name('delete')->middleware('permission:delete_scheme');
    });

    Route::controller(StrategyAdminController::class)->prefix('strategies')->name('strategies.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_strategy');
        Route::get('/edit/{strategy}', 'edit')->name('edit')->middleware('permission:view_strategy');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_strategy');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_strategy');
        Route::post('/update/{strategy}', 'update')->name('update')->middleware('permission:edit_strategy');
        Route::post('/delete/{strategy}', 'delete')->name('delete')->middleware('permission:delete_strategy');
    });

    Route::controller(RoleAdminController::class)->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_role');
        Route::get('/edit/{role}', 'edit')->name('edit')->middleware('permission:view_role');
        Route::get('/create', 'create')->name('create')->middleware('permission:add_role');
        Route::post('/store', 'store')->name('store')->middleware('permission:add_role');
        Route::post('/update/{role}', 'update')->name('update')->middleware('permission:edit_role');
        Route::post('/delete/{role}', 'delete')->name('delete')->middleware('permission:delete_role');
    });

    Route::controller(UserAdminController::class)->prefix('users')->name('users.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_user');
        Route::get('/edit/{user}', 'edit')->name('edit')->middleware('permission:view_user');
        Route::post('/update/{user}', 'update')->name('update')->middleware('permission:edit_user');
        Route::post('/{user}/password-reset-link', 'generatePasswordResetLink')->name('password_reset_link')->middleware('permission:edit_user');
        Route::post('/delete/{user}', 'delete')->name('delete')->middleware('permission:delete_user');
    });

    Route::controller(PackageAdminController::class)->prefix('packages')->name('packages.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_package');
        Route::get('/edit/{package}', 'edit')->name('edit')->middleware('permission:view_package');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_package');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_package');
        Route::post('/update/{package}', 'update')->name('update')->middleware('permission:edit_package');
        Route::post('/delete/{package}', 'delete')->name('delete')->middleware('permission:delete_package');
    });

    Route::controller(LoreMediaAdminController::class)->prefix('lore-media')->name('lore_media.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_lore');
        Route::get('/edit/{loreMedia:id}', 'edit')->name('edit')->middleware('permission:view_lore');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_lore');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_lore');
        Route::post('/update/{loreMedia:id}', 'update')->name('update')->middleware('permission:edit_lore');
        Route::post('/delete/{loreMedia:id}', 'delete')->name('delete')->middleware('permission:delete_lore');
    });

    Route::controller(LoreAdminController::class)->prefix('lores')->name('lores.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_lore');
        Route::get('/edit/{lore:id}', 'edit')->name('edit')->middleware('permission:view_lore');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_lore');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_lore');
        Route::post('/update/{lore:id}', 'update')->name('update')->middleware('permission:edit_lore');
        Route::post('/delete/{lore:id}', 'delete')->name('delete')->middleware('permission:delete_lore');
    });

    Route::controller(BlueprintAdminController::class)->prefix('blueprints')->name('blueprints.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_blueprint');
        Route::get('/edit/{blueprint}', 'edit')->name('edit')->middleware('permission:view_blueprint');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_blueprint');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_blueprint');
        Route::post('/update/{blueprint}', 'update')->name('update')->middleware('permission:edit_blueprint');
        Route::post('/delete/{blueprint}', 'delete')->name('delete')->middleware('permission:delete_blueprint');
    });

    Route::controller(ChannelAdminController::class)->prefix('channels')->name('channels.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_channel');
        Route::get('/edit/{channel}', 'edit')->name('edit')->middleware('permission:view_channel');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_channel');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_channel');
        Route::post('/update/{channel}', 'update')->name('update')->middleware('permission:edit_channel');
        Route::post('/delete/{channel}', 'delete')->name('delete')->middleware('permission:delete_channel');
    });

    Route::controller(TransmissionAdminController::class)->prefix('transmissions')->name('transmissions.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_channel');
        Route::get('/edit/{transmission}', 'edit')->name('edit')->middleware('permission:view_channel');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_channel');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_channel');
        Route::post('/update/{transmission}', 'update')->name('update')->middleware('permission:edit_channel');
        Route::post('/delete/{transmission}', 'delete')->name('delete')->middleware('permission:delete_channel');
    });

    // Blog admin routes — permission-based so content_creator role can also access
    Route::prefix('blog')->middleware(['permission:create_posts|edit_posts'])->name('blog.')->group(function () {
        Route::controller(BlogCategoryAdminController::class)->prefix('categories')->name('categories.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/edit/{blogCategory}', 'edit')->name('edit');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::post('/update/{blogCategory}', 'update')->name('update');
            Route::post('/delete/{blogCategory}', 'delete')->name('delete')->middleware('permission:delete_posts');
        });

        Route::controller(BlogPostAdminController::class)->prefix('posts')->name('posts.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/preview/{blogPost}', 'preview')->name('preview');
            Route::get('/edit/{blogPost}', 'edit')->name('edit');
            Route::get('/create', 'create')->name('create')->middleware('permission:create_posts');
            Route::post('/store', 'store')->name('store')->middleware('permission:create_posts');
            Route::post('/update/{blogPost}', 'update')->name('update')->middleware('permission:edit_posts');
            Route::post('/delete/{blogPost}', 'delete')->name('delete')->middleware('permission:delete_posts');
            Route::post('/upload-image', 'uploadImage')->name('upload-image');
        });
    });

    // Feedback inbox
    Route::controller(FeedbackAdminController::class)->prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_feedback');
        Route::post('/update/{feedback}', 'update')->name('update')->middleware('permission:manage_feedback');
        Route::post('/delete/{feedback}', 'destroy')->name('delete')->middleware('permission:manage_feedback');
    });

    // POD Links
    Route::controller(PodLinkAdminController::class)->prefix('pod-links')->name('pod_links.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_pod_link');
        Route::get('/edit/{podLink}', 'edit')->name('edit')->middleware('permission:view_pod_link');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_pod_link');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_pod_link');
        Route::post('/update/{podLink}', 'update')->name('update')->middleware('permission:edit_pod_link');
        Route::post('/delete/{podLink}', 'delete')->name('delete')->middleware('permission:delete_pod_link');
    });

    // The Other Side (TOS)
    Route::prefix('tos')->name('tos.')->group(function () {
        Route::controller(TosAllegianceAdminController::class)->prefix('allegiances')->name('allegiances.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_allegiance');
            Route::get('/edit/{allegiance}', 'edit')->name('edit')->middleware('permission:view_tos_allegiance');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_allegiance');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_allegiance');
            Route::post('/update/{allegiance}', 'update')->name('update')->middleware('permission:edit_tos_allegiance');
            Route::post('/delete/{allegiance}', 'delete')->name('delete')->middleware('permission:delete_tos_allegiance');
        });

        Route::controller(TosUnitAdminController::class)->prefix('units')->name('units.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_unit');
            Route::get('/edit/{unit}', 'edit')->name('edit')->middleware('permission:view_tos_unit');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_unit');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_unit');
            Route::post('/update/{unit}', 'update')->name('update')->middleware('permission:edit_tos_unit');
            Route::post('/delete/{unit}', 'delete')->name('delete')->middleware('permission:delete_tos_unit');
        });

        Route::controller(TosSculptAdminController::class)->prefix('sculpts')->name('sculpts.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_sculpt');
            Route::get('/edit/{sculpt}', 'edit')->name('edit')->middleware('permission:view_tos_sculpt');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_sculpt');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_sculpt');
            Route::post('/update/{sculpt}', 'update')->name('update')->middleware('permission:edit_tos_sculpt');
            Route::post('/delete/{sculpt}', 'delete')->name('delete')->middleware('permission:delete_tos_sculpt');
        });

        Route::controller(TosAbilityAdminController::class)->prefix('abilities')->name('abilities.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_ability');
            Route::get('/edit/{ability}', 'edit')->name('edit')->middleware('permission:view_tos_ability');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_ability');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_ability');
            Route::post('/update/{ability}', 'update')->name('update')->middleware('permission:edit_tos_ability');
            Route::post('/delete/{ability}', 'delete')->name('delete')->middleware('permission:delete_tos_ability');
        });

        Route::controller(TosActionAdminController::class)->prefix('actions')->name('actions.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_action');
            Route::get('/edit/{action}', 'edit')->name('edit')->middleware('permission:view_tos_action');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_action');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_action');
            Route::post('/update/{action}', 'update')->name('update')->middleware('permission:edit_tos_action');
            Route::post('/delete/{action}', 'delete')->name('delete')->middleware('permission:delete_tos_action');
        });

        Route::controller(TosTriggerAdminController::class)->prefix('triggers')->name('triggers.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_trigger');
            Route::get('/edit/{trigger}', 'edit')->name('edit')->middleware('permission:view_tos_trigger');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_trigger');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_trigger');
            Route::post('/update/{trigger}', 'update')->name('update')->middleware('permission:edit_tos_trigger');
            Route::post('/delete/{trigger}', 'delete')->name('delete')->middleware('permission:delete_tos_trigger');
        });

        Route::controller(TosSpecialUnitRuleAdminController::class)->prefix('special-rules')->name('special_rules.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_special_unit_rule');
            Route::get('/edit/{rule}', 'edit')->name('edit')->middleware('permission:view_tos_special_unit_rule');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_special_unit_rule');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_special_unit_rule');
            Route::post('/update/{rule}', 'update')->name('update')->middleware('permission:edit_tos_special_unit_rule');
            Route::post('/delete/{rule}', 'delete')->name('delete')->middleware('permission:delete_tos_special_unit_rule');
        });

        Route::controller(TosAllegianceCardAdminController::class)->prefix('allegiance-cards')->name('allegiance_cards.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_allegiance_card');
            Route::get('/edit/{card}', 'edit')->name('edit')->middleware('permission:view_tos_allegiance_card');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_allegiance_card');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_allegiance_card');
            Route::post('/update/{card}', 'update')->name('update')->middleware('permission:edit_tos_allegiance_card');
            Route::post('/delete/{card}', 'delete')->name('delete')->middleware('permission:delete_tos_allegiance_card');
        });

        Route::controller(TosEnvoyAdminController::class)->prefix('envoys')->name('envoys.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_envoy');
            Route::get('/edit/{envoy}', 'edit')->name('edit')->middleware('permission:view_tos_envoy');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_envoy');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_envoy');
            Route::post('/update/{envoy}', 'update')->name('update')->middleware('permission:edit_tos_envoy');
            Route::post('/delete/{envoy}', 'delete')->name('delete')->middleware('permission:delete_tos_envoy');
        });

        Route::controller(TosAssetAdminController::class)->prefix('assets')->name('assets.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_asset');
            Route::get('/edit/{asset}', 'edit')->name('edit')->middleware('permission:view_tos_asset');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_asset');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_asset');
            Route::post('/update/{asset}', 'update')->name('update')->middleware('permission:edit_tos_asset');
            Route::post('/delete/{asset}', 'delete')->name('delete')->middleware('permission:delete_tos_asset');
        });

        Route::controller(TosStratagemAdminController::class)->prefix('stratagems')->name('stratagems.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('permission:view_tos_stratagem');
            Route::get('/edit/{stratagem}', 'edit')->name('edit')->middleware('permission:view_tos_stratagem');
            Route::get('/create', 'create')->name('create')->middleware('permission:edit_tos_stratagem');
            Route::post('/store', 'store')->name('store')->middleware('permission:edit_tos_stratagem');
            Route::post('/update/{stratagem}', 'update')->name('update')->middleware('permission:edit_tos_stratagem');
            Route::post('/delete/{stratagem}', 'delete')->name('delete')->middleware('permission:delete_tos_stratagem');
        });
    });
});
