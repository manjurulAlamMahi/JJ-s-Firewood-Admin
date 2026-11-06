<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchNotificationJob;
use App\Models\NotificationQueue;
use App\Models\NotificationRun;
use App\Models\User;
use App\Services\Brevo\BrevoTransactionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class NotificationCentreController extends Controller
{
    public function index()
    {
        return view('backend.notification-centre.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Allow both legacy and new channel values
            'channel'  => 'required|in:push,email,sms,email_transactional,email_campaign,email_marketing,sms_marketing,push_marketing',
            'title'    => 'nullable|string|max:255',
            'message'  => 'nullable|string',
            'audience' => 'nullable|string|max:50',
            'template_id' => 'nullable|integer',
            'csv_file' => 'nullable|file|mimes:csv,txt|max:2048',
            // ---- Centre rebuild additive fields ----
            'audience_spec' => 'nullable|array',
            'campaign_name' => 'nullable|string|max:255',
            'respects_marketing_optout' => 'nullable|boolean',
            'scheduled_at'  => 'nullable|date',
            'status'        => 'nullable|string|max:30',
        ]);

        $cleanTemplate = isset($data['template_id']) && !empty($data['template_id']) ? $data['template_id'] : null;
        $cleanMessage = isset($data['message']) && trim($data['message']) !== '' ? $data['message'] : null;

        if ($cleanTemplate === null && $cleanMessage === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Either a Brevo template or a custom message is required.'
            ], 422);
        }

        $recipientList = null;

        if ($request->hasFile('csv_file') && $data['channel'] === 'sms') {
            $recipientList = $this->processCsvUpload($request->file('csv_file'));
        }

        $createPayload = [
            'channel'       => $data['channel'],
            'title'         => $data['title'] ?? null,
            'message'       => $cleanMessage,
            'audience'      => $data['audience'] ?? 'all_users',
            'schedule_type' => 'one_time',
            'status'        => $data['status'] ?? 'draft',
            'test_mode'     => false,
            'created_by'    => auth()->id(),
            'sent_count'    => 0,
            'failed_count'  => 0,
            'template_id'   => $cleanTemplate,
            'recipient_list' => $recipientList ? json_encode($recipientList) : null,
        ];

        // Optional fields — only if columns exist (backward compat with old DB)
        if (\Schema::hasColumn('notifications_queue', 'audience_spec') && !empty($data['audience_spec'])) {
            $createPayload['audience_spec'] = $data['audience_spec'];
        }
        if (\Schema::hasColumn('notifications_queue', 'respects_marketing_optout') && isset($data['respects_marketing_optout'])) {
            $createPayload['respects_marketing_optout'] = (bool) $data['respects_marketing_optout'];
        }
        if (\Schema::hasColumn('notifications_queue', 'scheduled_at') && !empty($data['scheduled_at'])) {
            $createPayload['scheduled_at'] = $data['scheduled_at'];
        }

        $n = NotificationQueue::create($createPayload);

        return response()->json(['ok' => true, 'id' => $n->id, 'data' => ['id' => $n->id]]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'message' => 'required|string',
            'template_id' => 'nullable|integer',
            // Centre rebuild additive fields
            'channel'                   => 'nullable|string|max:30',
            'audience'                  => 'nullable|string|max:50',
            'audience_spec'             => 'nullable|array',
            'campaign_name'             => 'nullable|string|max:255',
            'respects_marketing_optout' => 'nullable|boolean',
            'scheduled_at'              => 'nullable|date',
            'status'                    => 'nullable|string|max:30',
        ]);

        $n = NotificationQueue::findOrFail($id);

        if (in_array($n->status, ['sent', 'failed', 'cancelled'], true)) {
            return response()->json(['ok' => false, 'error' => 'Cannot edit completed notification.'], 422);
        }

        $n->title = $data['title'] ?? null;
        $n->message = $data['message'];
        if (isset($data['template_id'])) {
            $n->template_id = $data['template_id'];
        }
        if (!empty($data['channel'])) {
            $n->channel = $data['channel'];
        }
        if (!empty($data['audience'])) {
            $n->audience = $data['audience'];
        }
        if (!empty($data['status'])) {
            $n->status = $data['status'];
        }
        if (Schema::hasColumn('notifications_queue', 'audience_spec') && array_key_exists('audience_spec', $data)) {
            $n->audience_spec = $data['audience_spec'];
        }
        if (Schema::hasColumn('notifications_queue', 'respects_marketing_optout') && array_key_exists('respects_marketing_optout', $data)) {
            $n->respects_marketing_optout = (bool) $data['respects_marketing_optout'];
        }
        if (!empty($data['scheduled_at'])) {
            $n->scheduled_at = $data['scheduled_at'];
        }
        $n->save();

        return response()->json(['ok' => true, 'id' => $n->id, 'data' => ['id' => $n->id]]);
    }

    public function schedule(Request $request, int $id)
    {
        $data = $request->validate([
            'schedule_type' => 'required|in:one_time,recurring,event_driven',
            'scheduled_at'  => 'nullable|date',
            'rrule'         => 'nullable|string|max:255',
            'event_key'     => 'nullable|string|max:255',
        ]);

        $n = NotificationQueue::findOrFail($id);

        $n->schedule_type = $data['schedule_type'];

        if (Schema::hasColumn('notifications_queue', 'rrule')) {
            $n->rrule = $data['rrule'] ?? null;
        }
        if (Schema::hasColumn('notifications_queue', 'event_key')) {
            $n->event_key = $data['event_key'] ?? null;
        }

        if (!empty($data['scheduled_at'])) {
            $n->scheduled_at = $data['scheduled_at'];
            $n->next_run_at  = $data['scheduled_at'];
        } else {
            $n->scheduled_at = now();
            $n->next_run_at  = now();
        }

        $n->status = 'scheduled';
        $n->save();

        return response()->json(['ok' => true]);
    }

    public function sendNow(int $id)
    {
        $n = NotificationQueue::findOrFail($id);

        if (in_array($n->status, ['cancelled', 'paused'], true)) {
            return response()->json(['ok' => false, 'error' => 'Notification is paused/cancelled.'], 422);
        }

        $n->status = 'scheduled';
        $n->scheduled_at = now();
        $n->next_run_at  = now();
        $n->save();

        DispatchNotificationJob::dispatch($n->id);

        return response()->json(['ok' => true]);
    }

    public function sendTest(int $id)
    {
        return $this->sendNow($id);
    }

    public function toggleTestMode(int $id)
    {
        $n = NotificationQueue::findOrFail($id);
        $n->test_mode = !((bool) $n->test_mode);
        $n->save();

        return response()->json(['ok' => true, 'test_mode' => (bool) $n->test_mode]);
    }


    public function testEmail(int $id, BrevoTransactionalService $brevo)
    {
        $n = NotificationQueue::findOrFail($id);

        if (!in_array($n->channel, ['email', 'email_transactional', 'email_campaign'], true)) {
            return response()->json(['ok' => false, 'error' => 'Not an email notification.'], 422);
        }

        $to = (string) env('BREVO_TEST_EMAIL', 'admin@jjsfirewoodperth.com.au');
        $subject = $n->title ?: "JJ's Firewood Perth (Test Email)";

        try {
            if ($n->template_id) {
                $templateParams = [];
                if (!empty($n->message)) {
                    $decoded = json_decode($n->message, true);
                    if (is_array($decoded)) {
                        $templateParams = $decoded;
                    }
                }

                $brevo->sendEmail($to, '', '', null, [
                    'templateId' => (int) $n->template_id,
                    'templateParams' => $templateParams,
                ]);
            } else {
                $html = $this->wrapEmailHtml($n->message ?? '');
                $brevo->sendEmail($to, $subject, $html, strip_tags($n->message ?? ''));
            }

            NotificationRun::create([
                'notification_id' => $n->id,
                'channel' => $n->channel,
                'status' => 'sent',
                'provider' => 'brevo',
                'user_id' => auth()->id(),
                'recipient' => $to,
                'error' => null,
            ]);

            return response()->json(['ok' => true, 'message' => "Test email sent to {$to}"]);
        } catch (Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);
            NotificationRun::create([
                'notification_id' => $n->id,
                'channel' => $n->channel,
                'status' => 'failed',
                'provider' => 'brevo',
                'user_id' => auth()->id(),
                'recipient' => $to,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function testSms(int $id, BrevoTransactionalService $brevo)
    {
        $n = NotificationQueue::findOrFail($id);

        if ($n->channel !== 'sms') {
            return response()->json(['ok' => false, 'error' => 'Not an SMS notification.'], 422);
        }

        $raw = (string) env('BREVO_TEST_SMS', '0402631537');
        $to = $brevo->normalizeAuToE164($raw);

        if (!$to) {
            return response()->json(['ok' => false, 'error' => 'BREVO_TEST_SMS invalid format.'], 422);
        }

        try {
            $brevo->sendSms($to, (string) ($n->message ?? ''));

            NotificationRun::create([
                'notification_id' => $n->id,
                'channel' => 'sms',
                'status' => 'sent',
                'provider' => 'brevo',
                'user_id' => auth()->id(),
                'recipient' => $to,
                'error' => null,
            ]);

            return response()->json(['ok' => true, 'message' => "Test SMS sent to {$to}"]);
        } catch (Throwable $e) {
            Log::warning('Test SMS failed', ['error' => $e->getMessage()]);
            NotificationRun::create([
                'notification_id' => $n->id,
                'channel' => 'sms',
                'status' => 'failed',
                'provider' => 'brevo',
                'user_id' => auth()->id(),
                'recipient' => $to,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function getTemplates(BrevoTransactionalService $brevo)
    {
        try {
            $templates = $brevo->getEmailTemplates(100);
            return response()->json(['ok' => true, 'templates' => $templates]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function countSmsCharacters(Request $request, BrevoTransactionalService $brevo)
    {
        $message = $request->input('message', '');
        $stats = $brevo->countSmsCharacters($message);

        return response()->json($stats);
    }


    public function uploadCsv(Request $request, BrevoTransactionalService $brevo)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $recipients = $this->processCsvUpload($request->file('csv_file'), $brevo);

            return response()->json([
                'ok' => true,
                'count' => count($recipients),
                'recipients' => $recipients,
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    private function processCsvUpload($file, ?BrevoTransactionalService $brevo = null)
    {
        if (!$brevo) {
            $brevo = app(BrevoTransactionalService::class);
        }

        $path = $file->getRealPath();
        $phones = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $firstRow = fgetcsv($handle);

            if ($firstRow && !preg_match('/^[0-9+\s-]+$/', $firstRow[0])) {
            } else {
                if ($firstRow && isset($firstRow[0])) {
                    $phones[] = trim($firstRow[0]);
                }
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[0]) && !empty(trim($row[0]))) {
                    $phones[] = trim($row[0]);
                }
            }
            fclose($handle);
        }

        $normalized = $brevo->normalizePhoneNumbers($phones);

        return $normalized;
    }

    public function queueData(Request $request)
    {
        $channel = $request->query('channel');
        $bucket  = $request->query('bucket');
        $status  = $request->query('status');

        $q = NotificationQueue::query()->orderByDesc('id');

        if ($channel && in_array($channel, ['push', 'email', 'sms', 'email_transactional', 'email_campaign'], true)) {
            $q->where('channel', $channel);
        }

        if ($bucket === 'scheduled') {
            $q->whereIn('status', ['draft', 'scheduled', 'running', 'paused']);
        } elseif ($bucket === 'sent') {
            $q->whereIn('status', ['sent', 'failed', 'cancelled']);
        }

        if ($status && in_array($status, ['draft', 'scheduled', 'running', 'paused', 'sent', 'failed', 'cancelled'], true)) {
            $q->where('status', $status);
        }

        $wanted = [
            'id',
            'channel',
            'title',
            'message',
            'audience',
            'schedule_type',
            'scheduled_at',
            'next_run_at',
            'sent_at',
            'status',
            'test_mode',
            'sent_count',
            'failed_count',
            'last_error',
            'total_targets',
            'processed_targets',
            'rrule',
            'event_key',
            'created_by',
            'created_at',
            'updated_at',
            'last_ran_at',
            'template_id',
            'recipient_list',
        ];

        $cols = [];
        foreach ($wanted as $col) {
            if (Schema::hasColumn('notifications_queue', $col)) {
                $cols[] = $col;
            }
        }

        foreach (['id', 'channel', 'title', 'message', 'status', 'created_at'] as $must) {
            if (!in_array($must, $cols, true) && Schema::hasColumn('notifications_queue', $must)) {
                $cols[] = $must;
            }
        }

        $rows = $q->limit(500)->get($cols);

        return response()->json(['rows' => $rows]);
    }


    public function queuePaginated(Request $request)
    {
        $perPage = 15;
        $channel = $request->get('channel', 'push');
        $bucket = $request->get('bucket', 'scheduled');
        $q = $request->get('q');
        $status = $request->get('status');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = NotificationQueue::query()->where('channel', $channel);

        if ($bucket === 'scheduled') {
            $query->whereIn('status', ['scheduled', 'paused', 'running', 'draft']);
        } else {
            $query->whereIn('status', ['sent', 'failed', 'cancelled']);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%")
                    ->orWhere('id', '=', $q);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $wanted = [
            'id',
            'channel',
            'title',
            'message',
            'audience',
            'schedule_type',
            'scheduled_at',
            'next_run_at',
            'sent_at',
            'status',
            'test_mode',
            'sent_count',
            'failed_count',
            'last_error',
            'total_targets',
            'processed_targets',
            'rrule',
            'event_key',
            'created_by',
            'created_at',
            'updated_at',
            'last_ran_at',
            'template_id',
            'recipient_list',
        ];

        $cols = [];
        foreach ($wanted as $col) {
            if (Schema::hasColumn('notifications_queue', $col)) {
                $cols[] = $col;
            }
        }

        if ($bucket === 'scheduled') {
            $query->orderBy('next_run_at', 'asc');
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $data = $query->paginate($perPage, $cols);

        return response()->json($data);
    }

    public function logs(int $id)
    {
        $rows = NotificationRun::query()
            ->where('notification_id', $id)
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['id', 'notification_id', 'user_id', 'channel', 'status', 'recipient', 'provider', 'error', 'created_at']);

        return response()->json(['rows' => $rows]);
    }

    public function cancel(int $id)
    {
        $n = NotificationQueue::findOrFail($id);
        $n->status = 'cancelled';
        $n->save();
        return response()->json(['ok' => true]);
    }

    public function pause(int $id)
    {
        $n = NotificationQueue::findOrFail($id);
        $n->status = 'paused';
        if (Schema::hasColumn('notifications_queue', 'paused_at')) {
            $n->paused_at = now();
        }
        $n->save();
        return response()->json(['ok' => true]);
    }

    public function resume(int $id)
    {
        $n = NotificationQueue::findOrFail($id);
        if ($n->status === 'paused') {
            $n->status = 'scheduled';
            $n->save();
        }
        return response()->json(['ok' => true]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:cancel,pause,resume,send_now',
        ]);

        $q = NotificationQueue::query()->whereIn('id', $data['ids']);

        if ($data['action'] === 'cancel') {
            $q->update(['status' => 'cancelled']);
        }

        if ($data['action'] === 'pause') {
            $update = ['status' => 'paused'];
            if (Schema::hasColumn('notifications_queue', 'paused_at')) {
                $update['paused_at'] = now();
            }
            $q->update($update);
        }

        if ($data['action'] === 'resume') {
            $q->where('status', 'paused')->update(['status' => 'scheduled']);
        }

        if ($data['action'] === 'send_now') {
            $ids = $data['ids'];
            foreach ($ids as $id) {
                try {
                    $this->sendNow((int) $id);
                } catch (\Throwable $e) {
                    // continue
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function wrapEmailHtml(string $content): string
    {
        $safe = nl2br(e($content));

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">
  <div style="max-width:680px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border-radius:12px;padding:20px;font-family:Arial,Helvetica,sans-serif;color:#111;">
      <h2 style="margin:0 0 12px 0;font-size:18px;">JJ's Firewood Perth</h2>
      <div style="font-size:14px;line-height:1.6;">{$safe}</div>
      <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">
      <div style="font-size:12px;color:#666;">
        Reply to this email to contact us: <b>admin@jjsfirewoodperth.com.au</b>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    // ============================================================
    // CENTRE REBUILD — new endpoints (additive; existing ones above unchanged)
    // ============================================================

    /**
     * Search users for the recipient hand-picker.
     * GET /backend/notification-centre/recipients/search?q=
     */
    public function searchRecipients(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(50, (int) $request->input('limit', 20));
        $channel = (string) $request->input('channel', 'email');

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $contactCol = match (true) {
            str_contains($channel, 'sms')   => 'phone',
            str_contains($channel, 'push')  => 'fcm_token',
            default                         => 'email',
        };

        // Show ALL matching users — don't filter by contact column. Mark each
        // result with a reachability flag so the UI can show ✓ / × icons.
        $users = \App\Models\User::query()
            ->where('is_admin', '!=', 1)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN $contactCol IS NULL THEN 1 ELSE 0 END") // reachable users first
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone', 'fcm_token'])
            ->map(function ($u) use ($contactCol, $channel) {
                $reachable = !empty($u->{$contactCol});
                $why = null;
                if (!$reachable) {
                    $why = match (true) {
                        str_contains($channel, 'sms')   => 'no phone on file',
                        str_contains($channel, 'push')  => 'no app installed (no FCM token)',
                        default                         => 'no email on file',
                    };
                }
                return [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'email'     => $u->email,
                    'phone'     => $u->phone,
                    'reachable' => $reachable,
                    'why'       => $why,
                    'label'     => trim($u->name . ' (' . ($u->email ?: $u->phone ?: 'no contact') . ')'),
                ];
            });

        return response()->json(['data' => $users]);
    }

    /**
     * Estimate audience size live for the composer panel.
     * POST /backend/notification-centre/audience/estimate
     */
    public function estimateAudience(Request $request)
    {
        $spec = $request->input('audience_spec', []);
        if (is_string($spec)) $spec = json_decode($spec, true) ?? [];

        $channel = (string) $request->input('channel', 'email');
        $respectsOptOut = $request->boolean('respects_marketing_optout', true);

        $resolver = new \App\Services\Notifications\AudienceResolver();
        $est = $resolver->estimate($spec, $channel, $respectsOptOut);

        // Estimated SMS cost in AUD — Brevo's SMS pricing is roughly $0.06/segment for AU
        $cost = null;
        $segments = null;
        $encoding = null;
        $charLength = null;
        if (str_contains($channel, 'sms')) {
            $message = (string) $request->input('message', '');
            $charLength = mb_strlen($message);
            try {
                $brevo = app(\App\Services\Brevo\BrevoTransactionalService::class);
                $segInfo = $brevo->countSmsCharacters($message);
                // The Brevo helper returns 'parts' (and 'encoding'), not 'segments'.
                $segments = (int) ($segInfo['parts'] ?? 1);
                $encoding = $segInfo['encoding'] ?? null;
            } catch (\Throwable $e) {
                // Fallback that matches the helper's logic if the service is unavailable
                $isGsm7 = (bool) preg_match('/^[\x20-\x7E\r\n£¥èéùìòÇØøÅåÆæßÉÄÖÑÜ§¿äöñüà]*$/u', $message);
                $encoding = $isGsm7 ? 'GSM-7' : 'UCS-2';
                $singleLimit = $isGsm7 ? 160 : 70;
                $multiLimit  = $isGsm7 ? 153 : 67;
                $segments = $charLength <= $singleLimit ? 1 : (int) ceil($charLength / $multiLimit);
            }
            $perSegmentCost = (float) env('SMS_COST_PER_SEGMENT_AUD', 0.06);
            $cost = round($est['total'] * $segments * $perSegmentCost, 4);
        }

        return response()->json(array_merge($est, [
            'sms_segments'   => $segments,
            'sms_encoding'   => $encoding,
            'sms_chars'      => $charLength,
            'sms_cost_per_segment' => str_contains($channel, 'sms') ? (float) env('SMS_COST_PER_SEGMENT_AUD', 0.06) : null,
            'estimated_cost' => $cost,
            'currency'       => 'AUD',
        ]));
    }

    /**
     * Suburb suggestions for the audience builder dropdown.
     * GET /backend/notification-centre/audience/suburbs
     */
    public function getSuburbs()
    {
        $resolver = new \App\Services\Notifications\AudienceResolver();
        return response()->json(['data' => $resolver->getSuburbList(200)]);
    }

    /**
     * Send a test of an existing campaign to specific user IDs and/or raw values.
     * POST /backend/notification-centre/{id}/send-test-targeted
     */
    public function sendTestTargeted(Request $request, int $id)
    {
        $notification = \App\Models\NotificationQueue::find($id);
        if (!$notification) return response()->json(['message' => 'Not found'], 404);

        $userIds      = array_filter(array_map('intval', $request->input('user_ids', [])));
        $rawEmails    = array_filter(array_map('trim', $request->input('emails', [])));
        $rawPhones    = array_filter(array_map('trim', $request->input('phones', [])));

        if (empty($userIds) && empty($rawEmails) && empty($rawPhones)) {
            return response()->json(['message' => 'Please supply at least one recipient.'], 422);
        }

        $sent = 0; $failed = 0; $errors = [];

        // Fan-out depends on channel
        $channel = $notification->channel;
        try {
            if ($channel === 'sms' || $channel === 'sms_marketing') {
                $brevo = app(\App\Services\Brevo\BrevoTransactionalService::class);
                $phones = $rawPhones;
                if (!empty($userIds)) {
                    $phones = array_merge($phones, \App\Models\User::whereIn('id', $userIds)->whereNotNull('phone')->pluck('phone')->all());
                }
                $phones = array_values(array_unique(array_filter($phones)));
                foreach ($phones as $phone) {
                    try {
                        $normalized = $brevo->normalizeAuToE164($phone) ?? $phone;
                        $brevo->sendSms($normalized, $notification->message);
                        $sent++;
                        $this->logRun($notification->id, null, 'sms', 'sent', 'brevo', $normalized, null);
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$phone}: " . $e->getMessage();
                        $this->logRun($notification->id, null, 'sms', 'failed', 'brevo', $phone, $e->getMessage());
                    }
                }
            } elseif (str_contains($channel, 'email')) {
                $brevo = app(\App\Services\Brevo\BrevoTransactionalService::class);
                $emails = $rawEmails;
                if (!empty($userIds)) {
                    $emails = array_merge($emails, \App\Models\User::whereIn('id', $userIds)->whereNotNull('email')->pluck('email')->all());
                }
                $emails = array_values(array_unique(array_filter($emails)));
                $subject = $notification->title ?: 'Notification from JJ\'s Firewood';
                $html = $this->wrapEmailHtml($notification->message);
                foreach ($emails as $email) {
                    try {
                        $brevo->sendEmail($email, $subject, $html);
                        $sent++;
                        $this->logRun($notification->id, null, 'email', 'sent', 'brevo', $email, null);
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$email}: " . $e->getMessage();
                        $this->logRun($notification->id, null, 'email', 'failed', 'brevo', $email, $e->getMessage());
                    }
                }
            } elseif ($channel === 'push' || $channel === 'push_marketing') {
                if (empty($userIds)) {
                    return response()->json(['message' => 'Push test requires at least one user (raw tokens not accepted).'], 422);
                }
                $users = \App\Models\User::whereIn('id', $userIds)->whereNotNull('fcm_token')->get();
                foreach ($users as $u) {
                    try {
                        \App\Helpers\FirebaseHelper::sendNotification(
                            $u->fcm_token,
                            $notification->title ?? 'JJ\'s Firewood',
                            $notification->message,
                            $notification->payload ?? []
                        );
                        $sent++;
                        $this->logRun($notification->id, $u->id, 'push', 'sent', 'firebase', $u->fcm_token, null);
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$u->name}: " . $e->getMessage();
                        $this->logRun($notification->id, $u->id, 'push', 'failed', 'firebase', $u->fcm_token, $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Test send failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'errors'  => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Send a test directly using the current composer state — no saved
     * campaign required. This is what the new Send Test button calls.
     * POST /backend/notification-centre/send-test-direct
     */
    public function sendTestDirect(Request $request)
    {
        $validated = $request->validate([
            'channel'   => 'required|string|max:30',
            'title'     => 'nullable|string|max:255',
            'message'   => 'required|string',
            'user_ids'  => 'nullable|array',
            'emails'    => 'nullable|array',
            'phones'    => 'nullable|array',
            'payload'   => 'nullable|array',
        ]);

        $channel = $validated['channel'];
        $title   = $validated['title'] ?? null;
        $message = $validated['message'];
        $userIds = array_filter(array_map('intval', $validated['user_ids'] ?? []));
        $rawEmails = array_filter(array_map('trim', $validated['emails'] ?? []));
        $rawPhones = array_filter(array_map('trim', $validated['phones'] ?? []));

        if (empty($userIds) && empty($rawEmails) && empty($rawPhones)) {
            return response()->json(['message' => 'Please pick at least one recipient or enter a phone/email.'], 422);
        }

        $sent = 0; $failed = 0; $errors = []; $skipped = [];

        try {
            if (str_contains($channel, 'sms')) {
                $brevo = app(\App\Services\Brevo\BrevoTransactionalService::class);
                $phones = $rawPhones;
                if (!empty($userIds)) {
                    $foundUsers = \App\Models\User::whereIn('id', $userIds)->get(['id','name','phone']);
                    foreach ($foundUsers as $u) {
                        if (empty($u->phone)) {
                            $skipped[] = $u->name . ' (no phone on file)';
                        } else {
                            $phones[] = $u->phone;
                        }
                    }
                }
                $phones = array_values(array_unique(array_filter($phones)));
                foreach ($phones as $phone) {
                    try {
                        $normalized = $brevo->normalizeAuToE164($phone) ?? $phone;
                        $brevo->sendSms($normalized, $message);
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$phone}: " . $e->getMessage();
                    }
                }
            } elseif (str_contains($channel, 'email')) {
                $brevo = app(\App\Services\Brevo\BrevoTransactionalService::class);
                $emails = $rawEmails;
                if (!empty($userIds)) {
                    $foundUsers = \App\Models\User::whereIn('id', $userIds)->get(['id','name','email']);
                    foreach ($foundUsers as $u) {
                        if (empty($u->email)) {
                            $skipped[] = $u->name . ' (no email on file)';
                        } else {
                            $emails[] = $u->email;
                        }
                    }
                }
                $emails = array_values(array_unique(array_filter($emails)));
                if (empty($emails)) {
                    return response()->json([
                        'success' => true,
                        'sent' => 0,
                        'failed' => 0,
                        'skipped' => $skipped,
                        'errors' => ['No deliverable email addresses found among the picked recipients. ' . (count($skipped) ? 'Picked users had no email on file.' : '')],
                    ]);
                }
                $subject = $title ?: 'Test from JJ\'s Firewood Notification Centre';
                $html = $this->wrapEmailHtml($message);
                $textContent = strip_tags($message);
                foreach ($emails as $email) {
                    try {
                        $brevo->sendEmail($email, $subject, $html, $textContent);
                        $sent++;
                        \Log::info('NC test email sent', ['to' => $email, 'subject' => $subject]);
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$email}: " . $e->getMessage();
                        \Log::warning('NC test email failed', ['to' => $email, 'err' => $e->getMessage()]);
                    }
                }
            } elseif (str_contains($channel, 'push')) {
                if (empty($userIds)) {
                    return response()->json(['message' => 'Push tests require picking at least one app user (raw FCM tokens not accepted).'], 422);
                }
                $foundUsers = \App\Models\User::whereIn('id', $userIds)->get(['id','name','fcm_token']);
                foreach ($foundUsers as $u) {
                    if (empty($u->fcm_token)) {
                        $skipped[] = $u->name . ' (no app installed / no FCM token)';
                        continue;
                    }
                    try {
                        \App\Helpers\FirebaseHelper::sendNotification(
                            $u->fcm_token,
                            $title ?: 'JJ\'s Firewood',
                            $message,
                            $validated['payload'] ?? []
                        );
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++; $errors[] = "{$u->name}: " . $e->getMessage();
                    }
                }
            } else {
                return response()->json(['message' => 'Unknown channel: ' . $channel], 422);
            }
        } catch (\Throwable $e) {
            \Log::error('sendTestDirect fatal', ['err' => $e->getMessage()]);
            return response()->json(['message' => 'Test send failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'skipped' => $skipped,
            'errors'  => array_slice($errors, 0, 10),
        ]);
    }


    public function listSuppressions(Request $request)
    {
        $q = $request->input('q');
        $channel = $request->input('channel');
        $query = \App\Models\NotificationSuppression::query();
        if ($channel) $query->where('channel', $channel);
        if ($q) $query->where('value', 'like', "%{$q}%");
        return response()->json([
            'data' => $query->orderByDesc('created_at')->limit(500)->get(),
        ]);
    }

    public function addSuppression(Request $request)
    {
        $validated = $request->validate([
            'channel' => 'required|string|max:30',
            'value'   => 'required|string|max:255',
            'reason'  => 'nullable|string|max:100',
            'notes'   => 'nullable|string|max:1000',
        ]);

        $row = \App\Models\NotificationSuppression::firstOrCreate(
            ['channel' => $validated['channel'], 'value' => $validated['value']],
            [
                'reason'        => $validated['reason'] ?? 'manual',
                'notes'         => $validated['notes'] ?? null,
                'added_by'      => auth()->id(),
                'suppressed_at' => now(),
            ]
        );

        return response()->json(['data' => $row, 'created' => $row->wasRecentlyCreated]);
    }

    public function deleteSuppression(int $id)
    {
        $s = \App\Models\NotificationSuppression::find($id);
        if (!$s) return response()->json(['message' => 'Not found'], 404);
        $s->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Templates CRUD.
     */
    public function listTemplates(Request $request)
    {
        $channel = $request->input('channel');
        $query = \App\Models\NotificationTemplate::query();
        if ($channel) $query->where('channel', $channel);
        return response()->json(['data' => $query->orderByDesc('updated_at')->get()]);
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'id'      => 'nullable|integer|exists:notification_templates,id',
            'name'    => 'required|string|max:255',
            'channel' => 'required|string|max:30',
            'title'   => 'nullable|string|max:255',
            'body'    => 'required|string',
            'payload' => 'nullable|array',
        ]);
        $payload = [
            'name'       => $validated['name'],
            'channel'    => $validated['channel'],
            'title'      => $validated['title'] ?? null,
            'body'       => $validated['body'],
            'payload'    => $validated['payload'] ?? null,
            'created_by' => auth()->id(),
        ];

        if (!empty($validated['id'])) {
            $tpl = \App\Models\NotificationTemplate::find($validated['id']);
            if ($tpl) {
                $tpl->update($payload);
                return response()->json(['data' => $tpl, 'updated' => true]);
            }
        }
        $tpl = \App\Models\NotificationTemplate::create($payload);
        return response()->json(['data' => $tpl, 'created' => true]);
    }

    public function deleteTemplate(int $id)
    {
        $t = \App\Models\NotificationTemplate::find($id);
        if (!$t) return response()->json(['message' => 'Not found'], 404);
        $t->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Internal helper — log a run row safely.
     */
    protected function logRun(int $notificationId, ?int $userId, string $channel, string $status, ?string $provider, ?string $recipient, ?string $error): void
    {
        try {
            \App\Models\NotificationRun::create([
                'notification_id' => $notificationId,
                'user_id'         => $userId,
                'channel'         => $channel,
                'status'          => $status,
                'provider'        => $provider,
                'recipient'       => $recipient,
                'error'           => $error ? mb_substr($error, 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('logRun failed: ' . $e->getMessage());
        }
    }

}
