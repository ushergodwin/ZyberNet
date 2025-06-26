<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Connected - SuperSpot Wifi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
            color: white;
            font-family: 'Inter', sans-serif;
            text-align: center;
            padding-top: 10vh;
        }

        .logo {
            width: 100px;
            margin-bottom: 2rem;
        }

        .message-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 1rem;
            display: inline-block;
        }

        .message-box h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .message-box p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .btn-home {
            background: white;
            color: #5327ef;
            font-weight: bold;
        }

        .btn-home:hover {
            background: #e0e0e0;
            color: #5327ef;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo" class="logo">

        <div class="message-box mx-auto">
            <h1>You’re now connected! 🎉</h1>
            <p>Thank you for using SuperSpot Wifi.</p>
            <p>Your connection is active and your internet session has started.</p>

            <a href="https://www.google.com" class="btn btn-home mt-3">Go to Internet</a>
        </div>
    </div>
</body>
</html>
