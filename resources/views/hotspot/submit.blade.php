<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging in... SuperSpot Wifi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
            color: white;
            font-family: 'Inter', sans-serif;
        }
        .spinner-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .logo {
            width: 100px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body onload="document.forms[0].submit()">
    <!-- document.forms[0].submit() -->
    <div class="spinner-container">
        <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo" class="logo">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Logging in...</span>
        </div>
        <p class="mt-3">Logging you in, please wait...</p>
    </div>

    <form method="POST" action="{{ $link_login }}">
        @csrf
        <input type="hidden" name="username" value="{{ $voucher }}">
        <input type="hidden" name="password" value="{{ $voucher }}">
          <input type="hidden" name="dst" value="{{ url('hotspot-login-successful')}}" />
    </form>
</body>
</html>
