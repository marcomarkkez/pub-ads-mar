<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * UC-8 · design.md §10/§15 — a polymorphic object attached to a chat
 * (Ad|Adset|Campaign|Space|Payment|Booking). Attaching is OWNERSHIP-CHECKED in the
 * controller (R2/§16): a chat never exposes an object its attacher couldn't see.
 */
class ChatObject extends Model
{
    protected $fillable = [
        'chat_id',
        'objectable_type',
        'objectable_id',
        'attached_by_user_id',
    ];

    // UC-8 · design.md §17 — the API contract exposes the SHORT type alias + a human
    // label + a compact preview, not the raw polymorphic columns (FQCN + numeric id).
    protected $appends = ['object_type', 'object_id', 'label', 'object'];

    /** "App\Models\Space" → "space" (matches ChatObjectAuthorizer's short keys). */
    public function getObjectTypeAttribute(): string
    {
        return Str::snake(class_basename((string) $this->objectable_type));
    }

    public function getObjectIdAttribute(): int
    {
        return (int) $this->objectable_id;
    }

    /** Human label: prefer the object's own name/title, else "<Type> #<id>". */
    public function getLabelAttribute(): string
    {
        $obj = $this->objectable;
        $name = $obj?->name ?? $obj?->title ?? null;
        $type = Str::headline($this->object_type);

        return $name ? "{$type}: {$name}" : "{$type} #{$this->objectable_id}";
    }

    /**
     * UC-7/UC-8 · design.md §7/§10 — compact preview so chat members (esp. Support in a
     * dispute) can SEE the object without leaving the chat: name + the related PROOF media
     * (image/video/link) when the object is a Booking (or a Payment's booking). Access is
     * already gated by chat membership, so no extra leak — the proof is the dispute subject.
     */
    public function getObjectAttribute(): array
    {
        $obj = $this->objectable;
        $data = [
            'id' => (int) $this->objectable_id,
            'name' => $obj?->name ?? $obj?->title ?? null,
        ];

        $proof = $this->resolveProof($obj);
        if ($proof) {
            $data['proof'] = [
                'id' => $proof->id,
                'media_type' => $proof->media_type,
                'status' => $proof->status,
                'file_url' => $proof->file_url,
            ];
        }

        return $data;
    }

    /** The latest proof reachable from the attached object (Booking direct, Payment→booking). */
    private function resolveProof(?Model $obj): ?Proof
    {
        if ($obj instanceof Booking) {
            return $obj->proofs()->latest('id')->first();
        }
        if ($obj instanceof Payment) {
            return $obj->booking?->proofs()->latest('id')->first();
        }

        return null;
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function objectable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by_user_id');
    }
}
