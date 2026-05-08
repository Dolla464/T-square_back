<?php

namespace App\Mail;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // 1. أضف هذا
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage; // 2. أضف هذا

class CertificateMail extends Mailable implements ShouldQueue // 3. طبق الواجهة هنا
{
    use Queueable, SerializesModels;

    public function __construct(
        public ExamAttempt $attempt,
        public string $pdfPath // 4. نمرر المسار وليس المحتوى الخام
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your course certificate',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate',
            with: [
                'studentName' => $this->attempt->student->full_name,
                'courseTitle' => $this->attempt->exam->course->title,
            ],
        );
    }

    public function attachments(): array
    {
        // 5. نقرأ الملف من التخزين وقت إرسال الإيميل فعلياً
        return [
            Attachment::fromStorageDisk('public', $this->pdfPath)
                ->as('Certificate.pdf')
                ->withMime('application/pdf'),
        ];
    }
}