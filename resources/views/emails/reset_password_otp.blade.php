<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px;">
    <div style="max-width: 600px; background-color: #fff; padding: 30px; border-radius: 10px; margin: auto; border: 1px solid #e5e5e5;">
        <h2 style="color: #333;">Hello {{ $name }},</h2>
        <p style="color: #555; font-size: 16px;">
            You requested to reset your password. Use the following OTP to complete the process:
        </p>

        <h1 style="color: #000; background: #f3f3f3; display: inline-block; padding: 10px 20px; border-radius: 6px; letter-spacing: 4px;">
            {{ $otp }}
        </h1>

        <p style="color: #555; font-size: 16px;">
            This OTP is valid for <strong>{{ $ttlMinutes }} minutes</strong>.  
            If you did not request a password reset, you can safely ignore this email.
        </p>

        <p style="margin-top: 30px; color: #888;">
            Regards,<br>
            <strong>{{ config('app.name') }}</strong>
        </p>
    </div>
</body>
</html>
