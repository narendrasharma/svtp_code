<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\CrmNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared support-ticket domain. Product-neutral: optional links to
 * booking / lead / quotation plus a polymorphic overflow for refund,
 * withdrawal and future taxi/hotel records.
 */
class SupportService
{
    use NotifiesAdmins;

    /**
     * @param  array<string, mixed>  $data
     */
    public function open(array $data, User $requester): SupportTicket
    {
        return DB::transaction(function () use ($data, $requester): SupportTicket {
            $ticket = SupportTicket::create([
                'reference' => app(NumberSeriesService::class)->next('support_ticket'),
                'requester_user_id' => $requester->id,
                'vendor_profile_id' => $data['vendor_profile_id'] ?? $requester->vendorProfile?->id,
                'category_id' => $data['category_id'] ?? null,
                'subject' => mb_substr(trim((string) ($data['subject'] ?? '')), 0, 255),
                'priority' => $data['priority'] ?? 'normal',
                'status' => TicketStatus::Open->value,
                'assigned_to' => $data['assigned_to'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'last_reply_at' => now(),
            ]);

            $message = $ticket->messages()->create([
                'user_id' => $requester->id,
                'body' => trim((string) ($data['body'] ?? '')),
                'is_internal_note' => false,
            ]);

            if (! empty($data['attachments'])) {
                $this->addAttachments($ticket, $message, (array) $data['attachments'], $requester, false);
            }

            $priority = strtolower((string) ($ticket->priority ?? 'normal'));

            $this->notifyAdmins(new AdminAlert(in_array($priority, ['high', 'urgent'], true) ? 'support_ticket_high_priority' : 'support_ticket_opened', [
                'ticket_id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'requester' => $requester->name,
            ]));

            return $ticket->refresh();
        });
    }

    /**
     * Visible reply. Staff replies flip to pending_customer, requester
     * replies flip to pending_staff. Terminal tickets must reopen first.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    public function reply(SupportTicket $ticket, User $author, string $body, array $attachments = []): SupportTicketMessage
    {
        $this->assertCanParticipate($ticket, $author);

        if ($ticket->isTerminal()) {
            throw ValidationException::withMessages(['ticket' => 'This ticket is '.$ticket->status.'. Reopen it before replying.']);
        }

        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => 'A message is required.']);
        }

        return DB::transaction(function () use ($ticket, $author, $body, $attachments): SupportTicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'body' => mb_substr(trim($body), 0, 10000),
                'is_internal_note' => false,
            ]);

            if ($attachments !== []) {
                $this->addAttachments($ticket, $message, $attachments, $author, false);
            }

            $ticket->update([
                'status' => $author->isAdmin() ? TicketStatus::PendingCustomer->value : TicketStatus::PendingStaff->value,
                'last_reply_at' => now(),
            ]);

            $this->notifyReply($ticket, $author);

            return $message->refresh();
        });
    }

    /**
     * Staff-only private note. Never visible to the requester, never
     * mailed, never touches ticket status.
     */
    public function internalNote(SupportTicket $ticket, User $staff, string $body): SupportTicketMessage
    {
        $this->assertStaff($author = $staff);
        $this->assertVisible($ticket, $staff);

        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => 'A note is required.']);
        }

        return $ticket->messages()->create([
            'user_id' => $staff->id,
            'body' => mb_substr(trim($body), 0, 10000),
            'is_internal_note' => true,
        ]);
    }

    public function assign(SupportTicket $ticket, ?User $assignee, ?User $actor = null): SupportTicket
    {
        if ($assignee !== null && ! $assignee->isAdmin()) {
            throw ValidationException::withMessages(['assigned_to' => 'Tickets can only be assigned to internal staff.']);
        }

        $ticket->update(['assigned_to' => $assignee?->id]);

        if ($assignee && $assignee->id !== $actor?->id) {
            $assignee->notify(new CrmNotification('ticket_assigned', [
                'ticket_id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ]));
        }

        return $ticket->refresh();
    }

    public function resolve(SupportTicket $ticket, ?User $actor = null): SupportTicket
    {
        return $this->transition($ticket, TicketStatus::Resolved, $actor);
    }

    public function close(SupportTicket $ticket, ?User $actor = null): SupportTicket
    {
        return $this->transition($ticket, TicketStatus::Closed, $actor);
    }

    public function reopen(SupportTicket $ticket, ?User $actor = null): SupportTicket
    {
        if (! $ticket->isTerminal()) {
            throw ValidationException::withMessages(['ticket' => 'Only resolved or closed tickets can be reopened.']);
        }

        $ticket->update(['status' => TicketStatus::Open->value, 'closed_at' => null, 'last_reply_at' => now()]);

        return $ticket->refresh();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function addAttachments(
        SupportTicket $ticket,
        SupportTicketMessage $message,
        array $files,
        User $uploader,
        bool $internal,
    ): void {
        if (count($files) > 5) {
            throw ValidationException::withMessages(['attachments' => 'Attach at most 5 files per message.']);
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw ValidationException::withMessages(['attachments' => 'One of the files failed to upload.']);
            }

            $mime = (string) $file->getMimeType();

            if (in_array($mime, SupportTicketAttachment::BLOCKED_MIMES, true)
                || ! in_array($mime, SupportTicketAttachment::ALLOWED_MIMES, true)) {
                throw ValidationException::withMessages(['attachments' => 'File type not allowed ('.$file->getClientOriginalName().', PDF/JPG/PNG/WebP only).']);
            }

            if ($file->getSize() > SupportTicketAttachment::MAX_SIZE_KB * 1024) {
                throw ValidationException::withMessages(['attachments' => $file->getClientOriginalName().' exceeds 5 MB.']);
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = "tickets/{$ticket->id}/".Str::random(32).($extension !== '' ? ".{$extension}" : '');

            Storage::disk('support')->put($path, file_get_contents($file->getRealPath()));

            $message->attachments()->create([
                'disk' => 'support',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $mime,
                'size' => $file->getSize(),
                'is_internal' => $internal || $message->is_internal_note,
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    protected function transition(SupportTicket $ticket, TicketStatus $to, ?User $actor): SupportTicket
    {
        $ticket->update([
            'status' => $to->value,
            'closed_at' => $to === TicketStatus::Closed ? now() : $ticket->closed_at,
        ]);

        if ($to === TicketStatus::Resolved || $to === TicketStatus::Closed) {
            $ticket->requester->notify(new CrmNotification('support_reply', [
                'ticket_id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'portal' => $this->requesterPortal($ticket),
                'resolved' => true,
                'status' => $to->value,
            ]));
        }

        return $ticket->refresh();
    }

    protected function notifyReply(SupportTicket $ticket, User $author): void
    {
        if ($author->isAdmin()) {
            $ticket->requester->notify(new CrmNotification('support_reply', [
                'ticket_id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'portal' => $this->requesterPortal($ticket),
            ]));

            return;
        }

        // Requester replied: nudge the assignee, else all admins.
        $assignee = $ticket->assignee;

        if ($assignee) {
            $assignee->notify(new CrmNotification('support_reply', [
                'ticket_id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ]));

            return;
        }

        $this->notifyAdmins(new AdminAlert('support_ticket_replied', [
            'ticket_id' => $ticket->id,
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'requester' => $author->name,
        ]));
    }

    protected function requesterPortal(SupportTicket $ticket): string
    {
        $requester = $ticket->requester;

        if ($requester && $requester->isVendor()) {
            return 'vendor';
        }

        return $requester && $requester->isAdmin() ? 'admin' : 'account';
    }

    protected function assertCanParticipate(SupportTicket $ticket, User $user): void
    {
        if ($user->isAdmin()) {
            $this->assertVisible($ticket, $user);

            return;
        }

        if ((int) $ticket->requester_user_id !== (int) $user->id) {
            throw new AuthorizationException('You can only reply to your own tickets.');
        }
    }

    protected function assertVisible(SupportTicket $ticket, User $user): void
    {
        if ($user->isAdmin()) {
            if ($user->can('support.view_all') || (int) $ticket->assigned_to === (int) $user->id) {
                return;
            }

            throw new AuthorizationException('This ticket is not assigned to you.');
        }

        if ((int) $ticket->requester_user_id !== (int) $user->id) {
            throw new AuthorizationException('Ticket not found.');
        }
    }

    protected function assertStaff(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Staff only.');
        }
    }
}
