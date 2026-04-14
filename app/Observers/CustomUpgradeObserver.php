<?php

namespace App\Observers;

use App\Models\CustomUpgrade;
use Illuminate\Support\Str;

class CustomUpgradeObserver
{
    public function creating(CustomUpgrade $upgrade): void
    {
        $upgrade->display_name = $upgrade->name;

        $base = Str::slug($upgrade->display_name);
        $slug = $base;
        $i = 1;
        while (CustomUpgrade::where('slug', $slug)->where('user_id', $upgrade->user_id)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        $upgrade->slug = $slug;
    }

    public function updating(CustomUpgrade $upgrade): void
    {
        if ($upgrade->isDirty('name')) {
            $upgrade->display_name = $upgrade->name;

            $base = Str::slug($upgrade->display_name);
            $slug = $base;
            $i = 1;
            while (CustomUpgrade::where('slug', $slug)->where('user_id', $upgrade->user_id)->where('id', '!=', $upgrade->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $upgrade->slug = $slug;
        }
    }
}
