<?php

namespace App\Models\TOS;

use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A TOS Deployment (where Companies set up), keyed by the Fate suit the Defender flips.
 *
 * @mixin IdeHelperDeployment
 */
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use GeneratesTosSlug, HasFactory;

    /** Fate suits that can select a Deployment (rulebook: Ram, Crow, Tome, Mask). */
    public const SUITS = ['R', 'C', 'T', 'M'];

    protected $table = 'tos_deployments';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): DeploymentFactory
    {
        return DeploymentFactory::new();
    }

    protected static function slugNeedsRandomSuffix(): bool
    {
        return false;
    }
}
