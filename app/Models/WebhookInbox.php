<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'payload', 'status', 'attempts', 'last_attempted_at', 'error_log'])]
#[Hidden(['payload', 'error_log'])]
class WebhookInbox extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
