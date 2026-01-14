<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova izlaznica</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    @php
        $appName = config('app.name', 'Connect');
        $fullName = trim((string)($employee->firstName ?? '') . ' ' . (string)($employee->lastName ?? ''));
        $rawType = (string)($pass->type ?? '');
        $typeLabel = match ($rawType) {
            'privatni' => 'Privatna izlaznica',
            'službeni', 'sluzbeni' => 'Službena izlaznica',
            default => $rawType !== '' ? $rawType : 'Izlaznica',
        };
        $startAt = optional($pass->start_time)->timezone(config('app.timezone'))->format('d.m.Y H:i');
        $reason = trim((string)($pass->reason ?? ''));
    @endphp

    <!-- Preheader (hidden preview text) -->
    <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
        Prijavljena je nova izlaznica i čeka pregled.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.06);">
                    <tr>
                        <td style="background-color:#0f172a; padding:20px 24px;">
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#cbd5e1;">
                                {{ $appName }}
                            </div>
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:20px; line-height:26px; color:#ffffff; font-weight:700; margin-top:4px;">
                                Nova izlaznica
                            </div>
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#94a3b8; margin-top:6px;">
                                {{ $typeLabel }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px 8px 24px; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
                            <div style="font-size:14px; line-height:20px; margin:0 0 10px 0;">
                                Poštovani,
                            </div>
                            <div style="font-size:14px; line-height:22px; margin:0; color:#334155;">
                                Prijavljena je nova izlaznica (izlazak iz firme) i čeka pregled/odobrenje.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 16px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#475569;">
                                        Radnik
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0f172a;">
                                        {{ $fullName !== '' ? $fullName : '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="180" style="padding:10px 12px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#475569; border-top:1px solid #e5e7eb;">
                                        Vrijeme izlaska
                                    </td>
                                    <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                        {{ $startAt ?: '—' }}
                                    </td>
                                </tr>
                                @if($reason !== '')
                                    <tr>
                                        <td width="180" style="padding:10px 12px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#475569; border-top:1px solid #e5e7eb;">
                                            Razlog
                                        </td>
                                        <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                            {{ $reason }}
                                        </td>
                                    </tr>
                                @endif
                                @if(!empty($pass->id))
                                    <tr>
                                        <td width="180" style="padding:10px 12px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#475569; border-top:1px solid #e5e7eb;">
                                            ID izlaznice
                                        </td>
                                        <td style="padding:10px 12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0f172a; border-top:1px solid #e5e7eb;">
                                            #{{ $pass->id }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:0 24px 22px 24px;">
                            <!-- Bulletproof button (Outlook-safe) -->
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $passesUrl }}" style="height:44px;v-text-anchor:middle;width:280px;" arcsize="14%" strokecolor="#2563eb" fillcolor="#2563eb">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:14px;font-weight:700;">
                                    Otvori aktivne izlaznice
                                </center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <a href="{{ $passesUrl }}"
                               style="background-color:#2563eb; border:1px solid #2563eb; border-radius:10px; color:#ffffff; display:inline-block; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:700; line-height:44px; text-align:center; text-decoration:none; width:280px; -webkit-text-size-adjust:none;">
                                Otvori aktivne izlaznice
                            </a>
                            <!--<![endif]-->
                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#64748b; margin-top:10px;">
                                Ako dugme ne radi, otvorite link: <a href="{{ $passesUrl }}" style="color:#2563eb; text-decoration:underline;">{{ $passesUrl }}</a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#64748b;">
                            Ova poruka je automatski generisana. Ako imate pitanja, kontaktirajte administratora sistema.
                        </td>
                    </tr>
                </table>

                <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8; padding:14px 0 0 0;">
                    © {{ date('Y') }} {{ $appName }}
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
