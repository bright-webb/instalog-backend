<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        /* Include the CSS from the previous HTML template here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #1e90ff;
            border-radius: 2px;
        }
        
        .logo {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        
        .content {
            padding: 50px 40px;
        }
        
        .title {
            font-size: 24px;
            font-weight: 600;
            color: #000000;
            text-align: center;
            margin-bottom: 16px;
            letter-spacing: -0.3px;
        }
        
        .description {
            font-size: 16px;
            color: #666666;
            text-align: center;
            margin-bottom: 40px;
            line-height: 1.7;
        }
        
        .reset-button {
            display: inline-block;
            background: #1e90ff;
            color: #ffffff;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(30, 144, 255, 0.3);
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .alternative {
            background: #f8f9fa;
            padding: 24px;
            border-radius: 8px;
            border-left: 4px solid #1e90ff;
        }
        
        .alternative-title {
            font-size: 14px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 8px;
        }
        
        .alternative-link {
            font-size: 14px;
            color: #1e90ff;
            word-break: break-all;
            text-decoration: none;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        }
        
        .security-notice {
            margin-top: 40px;
            padding: 20px;
            background: #fafafa;
            border-radius: 6px;
            border: 1px solid #e8e8e8;
        }
        
        .security-notice p {
            font-size: 13px;
            color: #666666;
            line-height: 1.6;
        }
        
        .footer {
            background: #000000;
            padding: 30px 40px;
            text-align: center;
        }
        
        .footer-text {
            color: #999999;
            font-size: 14px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ $data['app_name'] }}</div>
        </div>
        
        <div class="content">
            <h1 class="title">Reset Your Password</h1>
            
            <p class="description">
                Hi {{ $data['user_name'] }},<br><br>
                We received a request to reset your password. Click the button below to create a new password for your account. This link will expire on {{ $data['expires_at'] }} for your security.
            </p>
            
            <div class="button-container">
                <a href="{{ $data['reset_url'] }}" class="reset-button">Reset Password</a>
            </div>
            
            <div class="alternative">
                <div class="alternative-title">Can't click the button?</div>
                <a href="{{ $data['reset_url'] }}" class="alternative-link">{{ $data['reset_url'] }}</a>
            </div>
            
            <div class="security-notice">
                <p><strong>Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
            </div>
        </div>
        
        <div class="footer">
            <p class="footer-text">This email was sent because a password reset was requested for your account.</p>
        </div>
    </div>
</body>
</html>