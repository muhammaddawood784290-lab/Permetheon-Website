<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BUSINESS INQUIRY — implements the business_inquiries API contract 1:1.
 * Columns carry the same CHECK semantics via migrations; E.164 shape is
 * enforced by the shared validator contract.
 */
class BusinessInquiry extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'business_inquiries';

    public $incrementing = true;

    protected $keyType = 'integer';

    protected $fillable = [
        'id', 'name', 'company', 'email', 'contact_number', 'project_type',
        'budget', 'timeline', 'message', 'status', 'priority',
        'created_at', 'updated_at',
    ];

    /** Millisecond-precision ISO 8601 UTC wire format via MeetingTime. */
    protected $casts = [
        'created_at' => \App\Support\UtcDatetime::class,
        'updated_at' => \App\Support\UtcDatetime::class,
    ];

    /** camelCase serialization to match the JSON contract exactly. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'contactNumber' => $this->contact_number,
            'projectType' => $this->project_type,
            'budget' => $this->budget,
            'timeline' => $this->timeline,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'createdAt' => $this->created_at ? \App\Support\MeetingTime::toWire($this->created_at) : null,
            'updatedAt' => $this->updated_at ? \App\Support\MeetingTime::toWire($this->updated_at) : null,
        ];
    }

    /** camelCase for notes author join reuse. */
    public function scopeSearch($query, string $term)
    {
        $like = '%' . $term . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('contact_number', 'like', $like)
                ->orWhere('message', 'like', $like);
        });
    }
}
