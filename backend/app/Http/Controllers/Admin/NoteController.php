<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\BusinessInquiry;
use App\Models\InquiryNote;
use App\Services\ApiResponse;

/**
 * ADMIN NOTES. Internal-only; author recorded; append-only —
 * notes never overwrite the inquiry (spec §39).
 */
class NoteController extends Controller
{
    public const MAX_NOTE_LENGTH = 2000;

    public function index(Request $request, string $id): JsonResponse
    {
        if (! BusinessInquiry::find($id)) {
            return ApiResponse::notFound('Inquiry not found.');
        }

        $notes = InquiryNote::with('admin')
            ->where('inquiry_id', $id)
            ->orderBy('created_at')
            ->get();

        return ApiResponse::ok(['notes' => $notes->map(fn ($n) => $n->toArray())->all()]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        if (! BusinessInquiry::find($id)) {
            return ApiResponse::notFound('Inquiry not found.');
        }

        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $text = is_string($body['body'] ?? null) ? trim($body['body']) : '';
        if ($text === '') {
            return ApiResponse::validation(['body' => 'Note text is required.']);
        }
        if (mb_strlen($text) > self::MAX_NOTE_LENGTH) {
            return ApiResponse::validation(['body' => 'Note must be at most ' . self::MAX_NOTE_LENGTH . ' characters.']);
        }

        $admin = $request->attributes->get('admin');
        $now = \App\Support\Clock::now();

        $note = InquiryNote::create([
            'id'         => (string) Str::uuid(),
            'inquiry_id' => $id,
            'admin_id'   => $admin->id,
            'body'       => $text,
            'created_at' => $now,
        ]);

        \App\Services\AuditLog::record($request, 'inquiry.note.create', 'inquiry_note', $note->id, [
            'inquiryId' => $id, 'length' => mb_strlen($text),
        ]);

        return ApiResponse::ok(['note' => $note->fresh(['admin'])->toArray()], 201);
    }
}
