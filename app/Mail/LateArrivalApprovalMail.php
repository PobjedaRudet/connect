<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\Pass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class LateArrivalApprovalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Pass $pass;
    public Employee $employee;
    public string $privatnaUrl;
    public string $sluzbenaUrl;

    public function __construct(Pass $pass, Employee $employee)
    {
        $this->pass = $pass;
        $this->employee = $employee;

        // Signed URLs valid for 7 days — no login needed
        $this->privatnaUrl = URL::temporarySignedRoute(
            'late.arrival.approval',
            now()->addDays(7),
            ['pass' => $pass->id, 'type' => 'privatni']
        );

        $this->sluzbenaUrl = URL::temporarySignedRoute(
            'late.arrival.approval',
            now()->addDays(7),
            ['pass' => $pass->id, 'type' => 'službeni']
        );
    }

    public function build(): self
    {
        $fullName = trim(($this->employee->firstName ?? '') . ' ' . ($this->employee->lastName ?? ''));

        return $this
            ->subject('Kašnjenje na posao: ' . ($fullName ?: 'Radnik') . ' — potrebno odobrenje')
            ->view('emails.late_arrival_approval')
            ->with([
                'pass'        => $this->pass,
                'employee'    => $this->employee,
                'privatnaUrl' => $this->privatnaUrl,
                'sluzbenaUrl' => $this->sluzbenaUrl,
            ]);
    }
}
