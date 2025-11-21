<?php

namespace App\Policies;

use App\Models\Website;

class WebsitePolicy
{
    public function preview($user, Website $website): bool
    {
        return true;
    }
}