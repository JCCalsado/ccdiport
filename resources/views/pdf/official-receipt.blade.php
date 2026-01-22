<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Official Receipt - {{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .receipt-number {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .amount-box {
            border: 2px solid #000;
            padding: 10px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-words {
            font-style: italic;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>CAMARINES NORTE COLLEGE COMPUTER DEPT. INC.</h2>
        <p>Official Receipt</p>
    </div>

    <div class="receipt-number">
        Receipt No: {{ $receipt->receipt_number }}
    </div>

    <div class="info-row">
        <span><strong>Date:</strong> {{ $receipt->issued_at->format('F d, Y h:i A') }}</span>
        <span><strong>Account ID:</strong> {{ $student->account_id }}</span>
    </div>

    <div class="info-row">
        <span><strong>Received from:</strong> {{ $student->full_name }}</span>
    </div>

    <div class="info-row">
        <span><strong>Student ID:</strong> {{ $student->student_id }}</span>
        <span><strong>Course:</strong> {{ $student->course }}</span>
    </div>

    <div class="amount-box">
        <p><strong>Amount Paid:</strong></p>
        <h1>₱{{ number_format($receipt->amount, 2) }}</h1>
    </div>

    <div class="amount-words">
        <strong>Amount in Words:</strong> {{ $amountInWords }} Pesos Only
    </div>

    <div class="info-row">
        <span><strong>Payment Method:</strong> {{ strtoupper($payment->payment_method) }}</span>
        <span><strong>Reference:</strong> {{ $payment->reference_number }}</span>
    </div>

    <div style="margin-top: 50px;">
        <div class="info-row">
            <div>
                <p>_________________________</p>
                <p style="text-align: center;">Received by</p>
            </div>
            <div>
                <p>_________________________</p>
                <p style="text-align: center;">Student Signature</p>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px;">
        <p>This is an official receipt. Please keep for your records.</p>
        <p>For inquiries: accounting@ccdi.edu.ph</p>
    </div>
</body>
</html>