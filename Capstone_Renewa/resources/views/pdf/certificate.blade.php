<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>REC Certificate</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #222; }
        .container { padding: 24px; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { font-size: 18px; font-weight: 700; color: #0ea5a4; }
        .title { font-size: 22px; margin-top: 8px; }
        .meta { margin-top: 12px; font-size: 12px; color: #555; }
        .box { border: 1px solid #e5e7eb; padding: 14px; border-radius: 6px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px 6px; vertical-align: top; }
        .label { color: #374151; font-weight: 600; width: 180px; }
        .value { color: #111827; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
        .signature { margin-top: 28px; display:flex; justify-content:space-between; }
        .sig-block { width: 48%; text-align:center; }
        .sig-line { margin-top: 40px; border-top:1px solid #ddd; padding-top:6px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <div style="text-align:left">
                    <div class="logo">Renewable Energy Certificate (REC)</div>
                    <div class="title">Certificate Details</div>
                    <div class="meta">Generated: {{ now()->format('d M Y, H:i') }}</div>
                </div>
                <div style="text-align:right">
                    {{-- Placeholder for organization logo; replace with <img src="/path/to/logo.png"> if available --}} 
                    <div style="width:140px; height:60px; border-radius:6px; background:#0ea5a4; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">REC</div>
                </div>
            </div>
        </div>

        <div class="box">
            <table>
                <tr>
                    <td class="label">Certificate ID</td>
                    <td class="value">{{ $certInfo['certificateId'] ?? ($certificate->blockchain_cert_id ?? $certificate->certificate_uid) }}</td>
                </tr>
                <tr>
                    <td class="label">Type</td>
                    <td class="value">{{ $certInfo['type'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Issuance Standard</td>
                    <td class="value">{{ $certInfo['issuanceStandard'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value">{{ $certInfo['status'] ?? ($certificate->blockchain_status ?? '-') }}</td>
                </tr>
                <tr>
                    <td class="label">Status Description</td>
                    <td class="value">{{ $certInfo['statusDescription'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Owner</td>
                    <td class="value">{{ $owner->name ?? '-' }} ({{ $owner->email ?? '-' }})</td>
                </tr>
                <tr>
                    <td class="label">Issuer</td>
                    <td class="value">{{ $issuer->name ?? '-' }} ({{ $issuer->email ?? '-' }})</td>
                </tr>
                <tr>
                    <td class="label">Amount</td>
                    <td class="value">{{ number_format($certificate->amount_mwh ?? 0, 2) }} MWh</td>
                </tr>
                <tr>
                    <td class="label">Generation Period</td>
                    <td class="value">{{ optional($certificate->generation_start_date)->format('d M Y') ?? '-' }} — {{ optional($certificate->generation_end_date)->format('d M Y') ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="box">
            <div style="display:flex; align-items:flex-start; gap:18px;">
                <div style="flex:1;">
                    <strong>Certificate Fingerprint</strong>
                    <div style="margin-top:6px; font-family:monospace; font-size:12px;">{{ $certInfo['fingerprint'] ?? ($certificate->blockchain_cert_id ? hash('sha256', $certificate->blockchain_cert_id) : '') }}</div>
                </div>

                <div style="width:160px; text-align:center;">
                    {{-- QR code for quick verification (uses Google Chart API) --}}
                    @php
                        $verifyUrl = url('/view-certificate?cert_id=' . ($certInfo['certificateId'] ?? ($certificate->blockchain_cert_id ?? $certificate->certificate_uid)));
                        $qrUrl = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($verifyUrl) . '&chld=L|1';
                    @endphp
                    <img src="{{ $qrUrl }}" alt="QR code" style="width:120px; height:120px; border:1px solid #eee;" />
                    <div style="font-size:10px; margin-top:6px;">Scan to verify</div>
                </div>
            </div>
        </div>

        <div class="signature">
            <div class="sig-block">
                <div class="sig-line">Issuer Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Owner / Buyer Signature</div>
            </div>
        </div>

        <div class="footer">This certificate is recorded on the REC blockchain and can be verified via the platform.</div>
    </div>

    {{-- Page number script for dompdf --}}
    <script type="text/php">
        if (isset(\$pdf)) {
            @\$font = Font_Metrics::get_font("DejaVu Sans", "normal");
            @\$pdf->page_text(520, 820, "Page {PAGE_NUM} of {PAGE_COUNT}", @\$font, 9, array(0,0,0));
        }
    </script>
</body>
</html>