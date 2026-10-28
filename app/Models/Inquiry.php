<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'message', 'source', 'status', 'admin_notes', 'notification_status', 'emailed_at'])]
class Inquiry extends Model
{
    use HasFactory;

    public const STATUSES = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'];

    public const NOTIFICATION_STATUSES = ['pending' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed', 'not_configured' => 'Mail not configured'];

    protected function casts(): array
    {
        return ['emailed_at' => 'datetime'];
    }
}
