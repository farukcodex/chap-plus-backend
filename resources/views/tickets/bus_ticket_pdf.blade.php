<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bus Boarding Pass — {{ $bookingId }}</title>
    <style>
        @page {
            margin: 18px 22px;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1E293B;
            line-height: 1.35;
            font-size: 11px;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }
        .container {
            width: 100%;
            max-width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table {
            border-bottom: 2px solid #557116;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-badge {
            background-color: #557116;
            color: #ffffff;
            font-weight: bold;
            font-size: 14px;
            padding: 5px 12px;
            border-radius: 5px;
            display: inline-block;
        }
        .ticket-title {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ticket-subtitle {
            font-size: 9px;
            color: #64748B;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .ref-box {
            text-align: right;
        }
        .ref-label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: bold;
        }
        .ref-value {
            font-size: 17px;
            font-weight: bold;
            color: #F86B17;
            letter-spacing: 0.5px;
        }
        .status-badge {
            display: inline-block;
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 3px;
        }
        /* Journey card */
        .card-journey {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            margin-bottom: 12px;
            padding: 12px 16px;
        }
        .city-label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .city-name {
            font-size: 15px;
            font-weight: bold;
            color: #0F172A;
            margin-top: 2px;
        }
        .terminal-name {
            font-size: 10px;
            color: #475569;
            margin-top: 1px;
        }
        .journey-arrow {
            text-align: center;
            vertical-align: middle;
            font-size: 16px;
            color: #557116;
            font-weight: bold;
        }
        .duration-pill {
            font-size: 9px;
            background-color: #FFFFFF;
            border: 1px solid #CBD5E1;
            padding: 1px 6px;
            border-radius: 8px;
            color: #475569;
            font-weight: bold;
            display: inline-block;
            margin-top: 2px;
        }
        /* Details Grid */
        .section-box {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            margin-bottom: 10px;
            padding: 10px 12px;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .info-label {
            font-size: 9px;
            color: #64748B;
            font-weight: normal;
            margin-bottom: 1px;
        }
        .info-val {
            font-size: 11px;
            font-weight: bold;
            color: #0F172A;
        }
        .seat-badge {
            background-color: #FFF7ED;
            color: #C2410C;
            border: 1px solid #FDBA74;
            font-size: 12px;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 3px;
            margin-bottom: 2px;
        }
        /* Perforated Divider */
        .perforated-line {
            border-top: 1.5px dashed #CBD5E1;
            margin: 12px 0;
        }
        .terms-box {
            background-color: #F8FAFC;
            border-left: 3px solid #557116;
            padding: 8px 12px;
            font-size: 9px;
            color: #475569;
            line-height: 1.45;
            border-radius: 0 4px 4px 0;
        }
        .footer-text {
            text-align: center;
            font-size: 8.5px;
            color: #94A3B8;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Header Table -->
    <table class="header-table">
        <tr>
            <td width="60%" style="vertical-align: middle;">
                <table>
                    <tr>
                        <td width="95" style="vertical-align: middle;">
                            <div class="brand-badge">ChapPlus</div>
                        </td>
                        <td style="vertical-align: middle;">
                            <h1 class="ticket-title">Official Boarding Pass</h1>
                            <div class="ticket-subtitle">Intercity Express Bus E-Ticket</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="40%" class="ref-box" style="vertical-align: middle;">
                <div class="ref-label">Booking Reference</div>
                <div class="ref-value">{{ $bookingId }}</div>
                <div><span class="status-badge">CONFIRMED &amp; PAID</span></div>
            </td>
        </tr>
    </table>

    <!-- Journey Overview Card -->
    <div class="card-journey">
        <table>
            <tr>
                <td width="42%" style="vertical-align: top;">
                    <div class="city-label">Departure From</div>
                    <div class="city-name">{{ $fromCity }}</div>
                    <div class="terminal-name">Station: {{ $departurePlace }}</div>
                    <div style="margin-top: 5px;">
                        <span class="info-label">Departure Time:</span>
                        <div style="font-size: 13px; font-weight: bold; color: #557116;">{{ $departureTime }}</div>
                    </div>
                </td>
                <td width="16%" class="journey-arrow">
                    <div style="font-size: 15px; font-weight: bold; color: #557116; letter-spacing: -1px;">&gt;&gt;&gt;</div>
                    <div><span class="duration-pill">{{ $journeyDuration }}</span></div>
                </td>
                <td width="42%" style="vertical-align: top; text-align: right;">
                    <div class="city-label">Destination To</div>
                    <div class="city-name">{{ $toCity }}</div>
                    <div class="terminal-name">Station: {{ $destinationPlace }}</div>
                    <div style="margin-top: 5px;">
                        <span class="info-label">Estimated Arrival:</span>
                        <div style="font-size: 13px; font-weight: bold; color: #0F172A;">{{ $destinationTime }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Travel Date & Bus Operator Info -->
    <table style="margin-bottom: 10px;">
        <tr>
            <td width="33%" style="padding-right: 4px;">
                <div class="section-box" style="margin-bottom: 0;">
                    <div class="info-label">Date of Travel</div>
                    <div class="info-val" style="font-size: 11px;">{{ $travelDateFormatted }}</div>
                </div>
            </td>
            <td width="33%" style="padding: 0 2px;">
                <div class="section-box" style="margin-bottom: 0;">
                    <div class="info-label">Bus Operator</div>
                    <div class="info-val">{{ $merchantName }}</div>
                </div>
            </td>
            <td width="34%" style="padding-left: 4px;">
                <div class="section-box" style="margin-bottom: 0;">
                    <div class="info-label">Coach / Service</div>
                    <div class="info-val">{{ $busName }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Passenger Manifest & Seats Allocated -->
    <div class="section-box">
        <div class="section-title">Passenger Manifest &amp; Seat Assignment</div>
        <table>
            <tr>
                <td width="55%" style="vertical-align: top;">
                    <table cellpadding="2">
                        <tr>
                            <td width="35%" class="info-label">Passenger:</td>
                            <td width="65%" class="info-val">{{ $passengerName }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Contact Phone:</td>
                            <td class="info-val">{{ $passengerPhone }}</td>
                        </tr>
                        @if(!empty($passengerEmail))
                        <tr>
                            <td class="info-label">Email:</td>
                            <td class="info-val" style="font-size: 10px;">{{ $passengerEmail }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
                <td width="45%" style="vertical-align: top; text-align: right;">
                    <div class="info-label" style="margin-bottom: 3px;">Reserved Seat(s):</div>
                    <div>
                        @foreach($seatNumbers as $seat)
                            <span class="seat-badge">Seat {{ $seat }}</span>
                        @endforeach
                    </div>
                    <div style="font-size: 9px; color: #64748B; margin-top: 3px;">
                        Total: <strong>{{ count($seatNumbers) }} Seat(s)</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Payment & Verification -->
    <div class="section-box" style="margin-bottom: 0;">
        <div class="section-title">Payment &amp; Audit Verification</div>
        <table cellpadding="4">
            <tr>
                <td width="25%" class="info-label">Total Fare Paid:</td>
                <td width="25%" style="font-size: 14px; font-weight: bold; color: #557116;">
                    KES {{ number_format($totalPrice, 2) }}
                </td>
                <td width="25%" class="info-label">Payment Method:</td>
                <td width="25%" class="info-val" style="text-transform: uppercase;">{{ $paymentMethod }}</td>
            </tr>
            <tr>
                <td class="info-label">Payment Status:</td>
                <td><span class="status-badge">PAID (CONFIRMED)</span></td>
                @if(!empty($mpesaReceipt))
                <td class="info-label">M-Pesa Receipt No:</td>
                <td class="info-val" style="font-family: monospace; color: #047857;">{{ $mpesaReceipt }}</td>
                @else
                <td class="info-label">Issued At:</td>
                <td class="info-val" style="font-size: 10px; color: #64748B;">{{ $issuedAt }}</td>
                @endif
            </tr>
            @if(!empty($mpesaReceipt))
            <tr>
                <td class="info-label">Issued At:</td>
                <td colspan="3" class="info-val" style="font-size: 10px; color: #64748B;">{{ $issuedAt }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Perforated Tear-off Line -->
    <div class="perforated-line"></div>

    <!-- Boarding Instructions -->
    <div class="terms-box">
        <strong style="color: #0F172A; text-transform: uppercase; font-size: 8.5px;">Important Boarding Instructions:</strong><br>
        1. <strong>Reporting Time:</strong> Please arrive at the departure terminal at least <strong>30 minutes</strong> before the scheduled departure time.<br>
        2. <strong>Identification:</strong> Carry an official Government National ID or Passport matching the passenger name on this ticket.<br>
        3. <strong>Baggage:</strong> Up to 20kg of standard luggage is allowed. Fragile or perishable items must be declared at the counter.<br>
        4. <strong>Conductor Verification:</strong> Present this digital boarding pass or a printed copy along with your M-Pesa receipt when boarding.
    </div>

    <!-- Footer Notice -->
    <div class="footer-text">
        Powered by <strong>ChapPlus Mobility Services</strong> &bull; 24/7 Support: support@chapplus.com | +254 700 000 000 &bull; Safe Travels!
    </div>

</div>
</body>
</html>
