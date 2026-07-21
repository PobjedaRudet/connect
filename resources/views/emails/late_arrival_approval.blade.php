<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kašnjenje — odobrenje izlaznice</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">

    @php
        $appName  = config('app.name', 'Connect');
        $fullName = trim(($employee->firstName ?? '') . ' ' . ($employee->lastName ?? ''));
        $tz       = config('app.timezone');
        $shiftStart = optional($pass->start_time)->timezone($tz)->format('d.m.Y H:i');
        $arrival    = optional($pass->end_time)->timezone($tz)->format('H:i');
        $lateMin    = $pass->late_minutes ?? $pass->duration_minutes ?? 0;
    @endphp

    <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        Radnik {{ $fullName ?: 'Nepoznat' }} zakasnio je {{ $lateMin }} min — potrebno je odabrati tip izlaznice.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
                       style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px;
                              overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.06);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#7c3aed; padding:20px 24px;">
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#ddd6fe;">
                                {{ $appName }}
                            </div>
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:20px; font-weight:700;
                                        color:#ffffff; margin-top:4px;">
                                Kašnjenje na posao
                            </div>
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#c4b5fd;
                                        margin-top:6px;">
                                Automatski kreirana izlaznica — odaberite tip
                            </div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:20px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif; color:#0f172a;">
                            <div style="font-size:14px; line-height:22px; color:#334155;">
                                Poštovani,<br><br>
                                Radnik <strong>{{ $fullName ?: '—' }}</strong> je zakasnio na posao.
                                Automatski je kreirana izlaznica za period kašnjenja.
                                Molimo odaberite da li je kašnjenje <strong>privatne</strong> ili
                                <strong>službene</strong> prirode.
                            </div>
                        </td>
                    </tr>

                    {{-- Details table --}}
                    <tr>
                        <td style="padding:12px 24px 16px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                   style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc;
                                                font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#475569;">
                                        Radnik
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial,Helvetica,sans-serif;
                                               font-size:14px; color:#0f172a;">
                                        {{ $fullName ?: '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc;
                                                font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#475569;
                                                border-top:1px solid #e5e7eb;">
                                        Početak smjene
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial,Helvetica,sans-serif;
                                               font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                        {{ $shiftStart ?: '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc;
                                                font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#475569;
                                                border-top:1px solid #e5e7eb;">
                                        Stvarni dolazak
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial,Helvetica,sans-serif;
                                               font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                        {{ $arrival ?: '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#fef3c7;
                                                font-family:Arial,Helvetica,sans-serif; font-size:13px;
                                                color:#92400e; border-top:1px solid #e5e7eb; font-weight:700;">
                                        Kašnjenje
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial,Helvetica,sans-serif;
                                               font-size:14px; font-weight:700; color:#92400e;
                                               border-top:1px solid #e5e7eb; background-color:#fef3c7;">
                                        {{ $lateMin }} min
                                    </td>
                                </tr>
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc;
                                                font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#475569;
                                                border-top:1px solid #e5e7eb;">
                                        ID izlaznice
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial,Helvetica,sans-serif;
                                               font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                        #{{ $pass->id }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA buttons --}}
                    <tr>
                        <td style="padding:0 24px 8px 24px;">
                            <p style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#475569;
                                      margin:0 0 12px 0;">
                                Odaberite tip kašnjenja klikom na jedno od dugmadi:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px 24px;" align="center">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    {{-- Privatna --}}
                                    <td style="padding-right:12px;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                                            xmlns:w="urn:schemas-microsoft-com:office:word"
                                            href="{{ $privatnaUrl }}"
                                            style="height:44px;v-text-anchor:middle;width:200px;"
                                            arcsize="14%" strokecolor="#dc2626" fillcolor="#dc2626">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;
                                                           font-size:14px;font-weight:700;">
                                                Privatna izlaznica
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-- -->
                                        <a href="{{ $privatnaUrl }}"
                                           style="background-color:#dc2626; border:1px solid #dc2626;
                                                  border-radius:10px; color:#ffffff; display:inline-block;
                                                  font-family:Arial,Helvetica,sans-serif; font-size:14px;
                                                  font-weight:700; line-height:44px; text-align:center;
                                                  text-decoration:none; width:200px; -webkit-text-size-adjust:none;">
                                            Privatna izlaznica
                                        </a>
                                        <!--<![endif]-->
                                    </td>

                                    {{-- Službena --}}
                                    <td>
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                                            xmlns:w="urn:schemas-microsoft-com:office:word"
                                            href="{{ $sluzbenaUrl }}"
                                            style="height:44px;v-text-anchor:middle;width:200px;"
                                            arcsize="14%" strokecolor="#2563eb" fillcolor="#2563eb">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;
                                                           font-size:14px;font-weight:700;">
                                                Službena izlaznica
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-- -->
                                        <a href="{{ $sluzbenaUrl }}"
                                           style="background-color:#2563eb; border:1px solid #2563eb;
                                                  border-radius:10px; color:#ffffff; display:inline-block;
                                                  font-family:Arial,Helvetica,sans-serif; font-size:14px;
                                                  font-weight:700; line-height:44px; text-align:center;
                                                  text-decoration:none; width:200px; -webkit-text-size-adjust:none;">
                                            Službena izlaznica
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>

                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px;
                                        line-height:18px; color:#64748b; margin-top:14px; text-align:center;">
                                Linkovi su važeći 7 dana. Ako dugmad ne rade, kopirajte sljedeće linkove:<br>
                                <strong>Privatna:</strong>
                                <a href="{{ $privatnaUrl }}" style="color:#dc2626; word-break:break-all;">
                                    {{ $privatnaUrl }}
                                </a><br>
                                <strong>Službena:</strong>
                                <a href="{{ $sluzbenaUrl }}" style="color:#2563eb; word-break:break-all;">
                                    {{ $sluzbenaUrl }}
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:14px 24px; background-color:#f8fafc; font-family:Arial,Helvetica,sans-serif;
                                   font-size:12px; line-height:18px; color:#64748b;">
                            Ova poruka je automatski generisana sistemom {{ $appName }}.
                            Kontaktirajte administratora ako imate pitanja.
                        </td>
                    </tr>
                </table>

                <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#94a3b8; padding:14px 0 0 0;">
                    © {{ date('Y') }} {{ $appName }}
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
