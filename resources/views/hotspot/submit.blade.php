<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging in... SuperSpot Wifi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
            color: white;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex-direction: column;
        }
        .logo {
            width: 100px;
            margin-bottom: 1.5rem;
        }
        .spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        p {
            margin-top: 1rem;
            font-size: 1rem;
        }
    </style>
</head>
<body onload="document.forms[0].submit()">
    <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo" class="logo">
    <div class="spinner" role="status" aria-label="Logging in..."></div>
    <p>Logging you in, please wait...</p>

    <form method="POST" action="{{ $link_login }}">
        @csrf
        <input type="hidden" name="username" value="{{ $voucher }}">
        <input type="hidden" name="password" value="{{ $voucher }}">
        <input type="hidden" name="dst" value="https://www.google.com" />
    </form>
</body>
</html>
