<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\Pass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PassCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Pass $pass;
    public Employee $employee;
    public string $passesUrl;

    public function __construct(Pass $pass, Employee $employee)
    {
        $this->pass = $pass;
        $this->employee = $employee;
        // Explicitly requested link
        $this->passesUrl = 'http://127.0.0.1:8000/passes/active';
    }

    public function build()
    {
        $fullName = trim((string)($this->employee->firstName ?? '') . ' ' . (string)($this->employee->lastName ?? ''));
        $type = (string)($this->pass->type ?? '');

        return $this
            ->subject('Nova izlaznica: ' . ($fullName !== '' ? $fullName : 'Radnik') . ($type !== '' ? ' (' . $type . ')' : ''))
            ->view('emails.pass_created')
            ->with([
                'pass' => $this->pass,
                'employee' => $this->employee,
                'passesUrl' => $this->passesUrl,
            ]);
    }
}
