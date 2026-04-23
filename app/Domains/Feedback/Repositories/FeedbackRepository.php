<?php

namespace App\Domains\Feedback\Repositories;

use App\Domains\Feedback\Models\Feedback;

class FeedbackRepository
{
    public function create(array $data): Feedback
    {
        return Feedback::create($data);
    }
}
