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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
            color: white;
            min-height: 100vh;
            margin: 0;
        }
        .card-container {
            background: #0d0c20;
            border-radius: 15px;
            padding: 2rem;
            max-width: 360px;
            width: 90%;
            margin: auto;
            margin-top: 5vh;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .tabs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .tab {
            flex: 1;
            padding: 0.6rem;
            text-align: center;
            font-weight: bold;
            cursor: pointer;
            background: transparent;
            color: white;
            border-bottom: 3px solid transparent;
        }
        .tab.active {
            border-bottom: 3px solid #ff5f6d;
        }
        .form-control {
            border-radius: 30px;
            padding-right: 60px;
        }
        .ok-btn {
            position: absolute;
            right: 10px;
            top: 5px;
            height: 38px;
            border-radius: 50%;
            background: white;
            color: #5327ef;
            border: none;
            font-weight: bold;
        }
        .plans .btn {
            width: 100%;
            margin-bottom: 10px;
            background: linear-gradient(90deg, #00f0ff, #ff00c8);
            color: white;
            font-weight: 600;
            border: none;
        }
        .logo {
            width: 100px;
            margin: 0 auto 1rem;
            display: block;
        }
        .powered {
            font-size: 0.85rem;
            color: #aaa;
            text-align: center;
            margin-top: 1rem;
        }
        .powered span {
            color: #00f0ff;
        }
    </style>
</head>
<body>
    <div class="card-container">
        <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo" class="logo">
        <h5 class="text-center mb-3">Sign in to SuperSpot Wifi</h5>
        
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
        <!-- show error message if exists -->
        @if(session('error') || $error)
            <div class="alert alert-danger text-center">
                {{ session('error')}} {{ $error }}
            </div>
        @endif
        <div class="plans">
            @foreach ($plans as $item)
                
                <a href="{{ url('buy-voucher/' . $item->id )}}" class="btn">{{ $item->name }} - UGX {{ number_format($item->price, 0) }}</a>
            @endforeach
        </div>
        <hr/>
        <div class="powered">
            Get a Voucher at SHOP or select one of the plans above to Pay via Mobile Money<br>
            <hr/>
            Powered by <span>Eng. Godwin</span>
        </div>
    </div>
</body>
</html>
