<?php

/*
|--------------------------------------------------------------------------
| MEETINGS CONFIG — canonical time standard + policy constants
|--------------------------------------------------------------------------
| UTC is the ONLY canonical time standard for the meeting system: storage,
| database datetime values, availability rules, conflict checks, the admin
| calendar and all API timestamps are UTC (ISO 8601, 'Z' suffix). There is
| deliberately NO configurable business timezone — the system must behave
| identically no matter where the server or the customer is located. NEVER
| use the browser's timezone or any geographic zone.
*/

return [
    // Date/time presentation formats (UTC) — display only.
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d\TH:i:s\Z',

    // Inquiry deletion policy (spec §7): a meeting can never outlive its
    // inquiry (FK RESTRICT). 'delete_meeting' (default) — deleting an inquiry
    // deletes its meeting row (any status) in the same transaction, freeing
    // the slot. 'prevent' — refuse deletion while the meeting is still BOOKED
    // (cancel first via the normal operational path).
    'inquiry_delete_policy' => env('MEETING_INQUIRY_DELETE_POLICY', 'delete_meeting'),
];
