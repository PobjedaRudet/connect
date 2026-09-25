<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\Pass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PassCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Pass $pass;
    public Employee $employee;
    public string $passesUrl;

    public function __construct(Pass $pass, Employee $employee)
    {
        $this->pass = $pass;
        $this->employee = $employee;
        $this->passesUrl = route('passes.active');
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
