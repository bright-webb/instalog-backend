<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #ffffff;">
    
    <!-- Email Container -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff;">
        <tr>
            <td style="padding: 40px 20px;">
                
                <!-- Main Email Card -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e5e5;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 0; text-align: center; border-bottom: 1px solid #f0f0f0;">
                            <h1 style="margin: 0 0 20px; color: #333333; font-size: 24px; font-weight: 600; letter-spacing: -0.5px;">Verify Your Email</h1>
                            <p style="margin: 0 0 30px; color: #666666; font-size: 16px;">
                                Enter the verification code below to complete your registration.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            
                            <!-- Verification Code Box -->
                            <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; padding: 30px; text-align: center; margin: 0 0 30px;">
                                <p style="margin: 0 0 10px; color: #666666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Verification Code</p>
                                <div style="font-family: 'Courier New', monospace; font-size: 28px; font-weight: bold; color: #333333; letter-spacing: 4px; margin: 0;">
                                    {{$verificationCode}}
                                </div>
                                <p style="margin: 10px 0 0; color: #999999; font-size: 12px;">
                                    Expires in 10 minutes
                                </p>
                            </div>
                            
                            <!-- Alternative Link -->
                            <div style="text-align: center; margin: 30px 0;">
                                <p style="margin: 0 0 20px; color: #666666; font-size: 14px;">
                                    Or click the button below to verify:
                                </p>
                                <a href="{{$verificationLink}}" style="display: inline-block; background-color: #333333; color: white; text-decoration: none; padding: 12px 24px; font-weight: 500; font-size: 14px; border: none; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Verify Email
                                </a>
                            </div>
                            
                            <!-- Instructions -->
                            <div style="border-top: 1px solid #f0f0f0; padding-top: 30px; margin-top: 30px;">
                                <h3 style="margin: 0 0 15px; color: #333333; font-size: 16px; font-weight: 600;">Instructions:</h3>
                                <ul style="margin: 0; padding-left: 20px; color: #666666; font-size: 14px; line-height: 1.6;">
                                    <li style="margin-bottom: 8px;">Return to the verification page</li>
                                    <li style="margin-bottom: 8px;">Enter the 6-digit code above</li>
                                    <li style="margin-bottom: 8px;">Click verify to complete setup</li>
                                </ul>
                            </div>
                            
                            <!-- Security Notice -->
                            <div style="border-top: 1px solid #f0f0f0; padding-top: 20px; margin-top: 30px;">
                                <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.5;">
                                    <strong>Security Notice:</strong> If you didn't request this verification, please ignore this email. The code will expire automatically.
                                </p>
                            </div>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8f9fa; border-top: 1px solid #e9ecef;">
                            <div style="text-align: center;">
                                <p style="margin: 0 0 10px; color: #666666; font-size: 14px;">
                                    Questions? Contact us at 
                                    <a href="mailto:support@walink.store" style="color: #333333; text-decoration: none;">support@walink.store</a>
                                </p>
                                
                                <div style="border-top: 1px solid #e9ecef; padding-top: 20px; margin-top: 20px;">
                                    <p style="margin: 0 0 5px; color: #999999; font-size: 12px;">
                                        © @php echo date('Y'); @endphp Walink
                                    </p>
                                    
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
    
</body>
</html>