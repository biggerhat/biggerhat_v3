<?php

namespace App\Models;

use App\Enums\FeedbackCategoryEnum;
use App\Enums\FeedbackStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFeedback
 */
class Feedback extends Model
{
    // Laravel's inflector pluralises "feedback" as "feedbacks"; this app
    // uses the singular word as the table name.
    protected $table = 'feedback';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'status' => FeedbackStatusEnum::class,
            'category' => FeedbackCategoryEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
