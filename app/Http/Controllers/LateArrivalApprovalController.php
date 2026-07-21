<?php

namespace App\Http\Controllers;

use App\Models\Pass;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LateArrivalApprovalController extends Controller
{
    /**
     * Handles supervisor clicking "Privatna" or "Službena" from the email link.
     * The route is protected by a temporary signed URL (7 days).
     */
    public function choose(Request $request, Pass $pass): Response|\Illuminate\Http\RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return response(view('emails.late_arrival_result', [
                'success' => false,
                'message' => 'Link je nevažeći ili je istekao. Kontaktirajte administratora.',
                'pass'    => null,
            ]), 403);
        }

        if (!$pass->late_pass) {
            return response(view('emails.late_arrival_result', [
                'success' => false,
                'message' => 'Ova izlaznica nije vezana za kašnjenje.',
                'pass'    => $pass,
            ]), 400);
        }

        if ($pass->approved) {
            $employee = $pass->employee()->select(['id', 'firstName', 'lastName'])->first();
            $fullName = trim(($employee->firstName ?? '') . ' ' . ($employee->lastName ?? ''));

            return response(view('emails.late_arrival_result', [
                'success'  => true,
                'message'  => "Izlaznica #{$pass->id} ({$fullName}) je već odobrena kao „{$pass->type}".",
                'pass'     => $pass,
                'employee' => $employee,
            ]));
        }

        $type = $request->query('type');
        if (!in_array($type, ['privatni', 'službeni'], true)) {
            return response(view('emails.late_arrival_result', [
                'success' => false,
                'message' => 'Nepoznat tip izlaznice.',
                'pass'    => $pass,
            ]), 400);
        }

        $pass->update([
            'type'    => $type,
            'approved' => true,
        ]);

        $employee = $pass->employee()->select(['id', 'firstName', 'lastName'])->first();
        $fullName  = trim(($employee->firstName ?? '') . ' ' . ($employee->lastName ?? ''));
        $typeLabel = $type === 'privatni' ? 'Privatna izlaznica' : 'Službena izlaznica';

        return response(view('emails.late_arrival_result', [
            'success'   => true,
            'message'   => "Izlaznica #{$pass->id} za radnika {$fullName} je odobrena kao „{$typeLabel}".",
            'pass'      => $pass,
            'employee'  => $employee,
            'typeLabel' => $typeLabel,
        ]));
    }
}
