<?php

use App\Http\Controllers\Admin\AbilityAdminController;
use App\Http\Controllers\Admin\ActionAdminController;
use App\Http\Controllers\Admin\BlogCategoryAdminController;
use App\Http\Controllers\Admin\BlogPostAdminController;
use App\Http\Controllers\Admin\BlueprintAdminController;
use App\Http\Controllers\Admin\ChannelAdminController;
use App\Http\Controllers\Admin\CharacterAdminController;
use App\Http\Controllers\Admin\CharacteristicAdminController;
use App\Http\Controllers\Admin\CrewAdminController;
use App\Http\Controllers\Admin\KeywordAdminController;
use App\Http\Controllers\Admin\LoreAdminController;
use App\Http\Controllers\Admin\LoreMediaAdminController;
use App\Http\Controllers\Admin\MarkerAdminController;
use App\Http\Controllers\Admin\MiniatureAdminController;
use App\Http\Controllers\Admin\PackageAdminController;
use App\Http\Controllers\Admin\PodLinkAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\SchemeAdminController;
use App\Http\Controllers\Admin\StrategyAdminController;
use App\Http\Controllers\Admin\TokenAdminController;
use App\Http\Controllers\Admin\TransmissionAdminController;
use App\Http\Controllers\Admin\TriggerAdminController;
use App\Http\Controllers\Admin\UpgradeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified'])->name('admin.')->group(function () {
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

    // POD Links
    Route::controller(PodLinkAdminController::class)->prefix('pod-links')->name('pod_links.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('permission:view_pod_link');
        Route::get('/edit/{podLink}', 'edit')->name('edit')->middleware('permission:view_pod_link');
        Route::get('/create', 'create')->name('create')->middleware('permission:edit_pod_link');
        Route::post('/store', 'store')->name('store')->middleware('permission:edit_pod_link');
        Route::post('/update/{podLink}', 'update')->name('update')->middleware('permission:edit_pod_link');
        Route::post('/delete/{podLink}', 'delete')->name('delete')->middleware('permission:delete_pod_link');
    });
});
