<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - Emailer</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #374151;
        }
        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .success .icon {
            background-color: #d1fae5;
            color: #059669;
        }
        .error .icon {
            background-color: #fee2e2;
            color: #dc2626;
        }
        h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .success h1 {
            color: #059669;
        }
        .error h1 {
            color: #dc2626;
        }
        p {
            margin: 1rem 0;
            color: #6b7280;
        }
        .email {
            font-weight: 600;
            color: #374151;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($success)
            <div class="success">
                <div class="header">
                    <div class="icon">✓</div>
                    <h1>Successfully Unsubscribed</h1>
                </div>

                <p>You have been successfully unsubscribed from our mailing list.</p>

                @if($email)
                    <p>Email address: <span class="email">{{ $email }}</span></p>
                @endif

                <p>You will no longer receive emails from us. If you change your mind, you can always subscribe again by contacting us.</p>
            </div>
        @else
            <div class="error">
                <div class="header">
                    <div class="icon">✗</div>
                    <h1>Unsubscribe Failed</h1>
                </div>

                <p>We were unable to process your unsubscribe request. This could be due to:</p>

                <ul>
                    <li>The unsubscribe link has expired</li>
                    <li>You may have already been unsubscribed</li>
                    <li>There was a technical error</li>
                </ul>

                <p>If you continue to receive unwanted emails, please contact our support team directly.</p>
            </div>
        @endif

        <div class="footer">
            <p>This is an automated unsubscribe page powered by Emailer.</p>
        </div>
    </div>
</body>
</html>
