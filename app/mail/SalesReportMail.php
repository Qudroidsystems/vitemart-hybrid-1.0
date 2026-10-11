<?php

namespace App\Mail;

use App\Services\Reports\ReportMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalesReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $report, public ?array $file = null)
    {
    }

    public function envelope(): Envelope
    {
        $s = $this->report['summary'];
        return new Envelope(
            subject: sprintf('%s – %s: %s (%d sale%s)',
                $this->report['title'] ?? 'Sales report',
                ReportMailer::periodLabel($this->report['from'], $this->report['to']),
                money($s['net_after_refunds']),
                $s['transactions'], $s['transactions'] === 1 ? '' : 's'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sales-report', with: [
            'r' => $this->report,
            'period' => ReportMailer::periodLabel($this->report['from'], $this->report['to']),
            'fileName' => $this->file['name'] ?? null,
        ]);
    }

    public function attachments(): array
    {
        if (!$this->file) {
            return [];
        }
        $f = $this->file;
        return [Attachment::fromData(fn () => $f['data'], $f['name'])->withMime($f['mime'])];
    }
}
