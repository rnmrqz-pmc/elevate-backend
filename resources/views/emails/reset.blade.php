<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Power Mac Center Institute for Excellence</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 60px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .title{
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 40px;
            color: #1a1a1a;
            text-align: center;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .logo {
            height: 60px;
            width: auto;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 40px;
            color: #1a1a1a;
            text-align: left;
        }

        .end-caption{
            margin-top: 30px;
            font-size: 14px;
            color: #555;
            text-align: center;
        }

        .content {
            font-size: 16px;
            color: #333;
            line-height: 1.8;
            padding: 0px 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;

        }

        .content p {
            margin-bottom: 20px;
        }

        .credentials {
            margin: 25px 0;
        }

        .credentials p {
            margin-bottom: 0px;
            /* font-family: 'Courier New', monospace; */
        }

        .credential-label {
            font-weight: 600;
            color: #3d3d3d;
        }

        .link {
            display: inline-block;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            margin: 10px 0;
        }

        .link:hover {
            text-decoration: underline;
        }
/* 
        .note {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
        } */

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 20px;
            }

            h1 {
                font-size: 24px;
            }

            .logo {
                height: 45px;
            }

            .logo-container {
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="{{ asset('images/logo1.png') }}" alt="Logo 1" class="logo">
            <img src="{{ asset('images/power-mac-center-logo.png') }}" alt="Power Mac Center" class="logo">
            <img src="{{ asset('images/logo3.png') }}" alt="Logo 3" class="logo">
        </div>

        <h1 class="title">Greetings {{ $name }},</h1>

        <div class="content">
            <p>
                This email has been sent to you because you requested a password reset on Power Mac Center Institute for Excellence.
            </p>

            <p>
                Please click the link below to reset your password:
            </p>

            <p>
                <a href="{{ $resetLink }}" class="link">Click here to reset your password</a>
            </p>

            <p>
                If you did not request a password reset, please disregard this email and your password will remain unchanged.
            </p>

        </div>
        <p class="end-caption">Let us Build Tomorrow Today!</p>
    </div>
</body>
</html>