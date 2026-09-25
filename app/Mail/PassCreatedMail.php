<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\Pass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PassCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Pass $pass;
    public Employee $employee;
    public string $passesUrl;
    public string $privatnaUrl;
    public string $sluzbenaUrl;

    public function __construct(Pass $pass, Employee $employee)
    {
        $this->pass = $pass;
        $this->employee = $employee;
        $this->passesUrl = route('passes.active');

        $expires = now()->addDays(7);
        $this->privatnaUrl = URL::temporarySignedRoute('pass.email.approval', $expires, [
            'pass' => $pass->id,
            'type' => 'privatni',
        ]);
        $this->sluzbenaUrl = URL::temporarySignedRoute('pass.email.approval', $expires, [
            'pass' => $pass->id,
            'type' => 'službeni',
        ]);
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
                'privatnaUrl' => $this->privatnaUrl,
                'sluzbenaUrl' => $this->sluzbenaUrl,
            ]);
    }
}
