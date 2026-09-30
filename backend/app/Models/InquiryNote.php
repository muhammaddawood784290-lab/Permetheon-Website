<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** INQUIRY NOTE — internal-only notes; author joined from admin_users. */
class InquiryNote extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $table = 'inquiry_notes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'inquiry_id', 'admin_id', 'body', 'created_at'];

    public function admin()
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    protected $casts = [
        'created_at' => \App\Support\UtcDatetime::class,
    ];

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'inquiryId' => $this->inquiry_id,
            'adminId'   => $this->admin_id,
            'adminName' => optional($this->admin)->name ?? 'Unknown',
            'body'      => $this->body,
            'createdAt' => $this->created_at ? \App\Support\MeetingTime::toWire($this->created_at) : null,
        ];
    }
}
