<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SuperSpot Wifi Voucher Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon & Metadata -->
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Buy your SuperSpot Wifi internet voucher securely and instantly online.">
    <meta name="keywords" content="SuperSpot Wifi, internet voucher, Uganda internet, hotspot">
    <meta name="author" content="SuperSpot Wifi">

    <!-- OG & Twitter Cards -->
    <meta property="og:title" content="Buy Internet Voucher - SuperSpot Wifi">
    <meta property="og:description" content="Buy your SuperSpot Wifi internet voucher securely and instantly online.">
    <meta property="og:image" content="{{ asset('superspotwifi-logo.png') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Buy Internet Voucher - SuperSpot Wifi">
    <meta name="twitter:description" content="Buy your SuperSpot Wifi internet voucher securely and instantly online.">
    <meta name="twitter:image" content="{{ asset('superspotwifi-logo.png') }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-container {
            background: #0d0c20;
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .logo {
            width: 100px;
            display: block;
            margin: 0 auto 1rem;
        }
        h5 {
            text-align: center;
            margin-bottom: 1rem;
        }
        .tabs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .tab {
            flex: 1;
            text-align: center;
            font-weight: bold;
            padding: 10px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: border-color 0.3s;
        }
        .tab.active {
            border-bottom: 3px solid #ff5f6d;
        }
        form {
            position: relative;
        }
        .form-control {
            width: 100%;
            padding: 10px 50px 10px 15px;
            border: none;
            border-radius: 30px;
            outline: none;
            font-size: 1rem;
        }
        .position-relative {
            position: relative;
        }
        .ok-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            height: 38px;
            width: 38px;
            border-radius: 50%;
            background: white;
            color: #5327ef;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }
        .alert {
            margin-top: 1rem;
            background: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.9rem;
        }
        .plans {
            margin-top: 1rem;
        }
        .plans .btn {
            display: block;
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 6px;
            background: linear-gradient(90deg, #00f0ff, #ff00c8);
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s;
        }
        .plans .btn:hover {
            opacity: 0.9;
        }
        hr {
            border: none;
            border-top: 1px solid #444;
            margin: 1rem 0;
        }
        .powered {
            font-size: 0.85rem;
            color: #aaa;
            text-align: center;
        }
        .powered span {
            color: #00f0ff;
        }

        @media (max-width: 400px) {
            .card-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="card-container">
        <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo" class="logo">
        <h5>Sign in to SuperSpot Wifi</h5>

        <div class="tabs">
            <div class="tab active">VOUCHER</div>
            {{-- <div class="tab">VIP</div> --}}
        </div>

        <form method="POST" action="/hotspot-login" class="position-relative">
            @csrf
            <input type="hidden" name="link_login" value="{{ $link_login }}">
            <div class="position-relative mb-3">
                <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" required>
                <button type="submit" class="ok-btn" @disabled(!$link_login)>GO</button>
            </div>
        </form>

        @if(session('error') || $error)
            <div class="alert">
                {{ session('error')}} {{ $error }}
            </div>
        @endif

        <div class="plans">
            @foreach ($plans as $item)
                <a href="{{ url('buy-voucher/' . $item->id )}}" class="btn">{{ $item->name }} - UGX {{ number_format($item->price, 0) }}</a>
            @endforeach
        </div>

        <hr>
        <div class="powered">
            Get a Voucher at SHOP or select one of the plans above to Pay via Mobile Money
            <hr/>
            Powered by <span>Eng. Godwin</span>
        </div>
    </div>
</body>
</html>
