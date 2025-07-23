<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SuperSpot Wifi | Affordable Internet Vouchers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f9f9f9;
            color: #333;
            line-height: 1.6;
        }
        header {
            background: linear-gradient(135deg, #5327ef, #ff5f6d);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
        }
        header img {
            width: 100px;
            margin-bottom: 1rem;
        }
        header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        header p {
            font-size: 1.1rem;
        }

        .cta {
            margin-top: 1rem;
        }
        .cta a {
            background: white;
            color: #5327ef;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
        }

        .section {
            padding: 2rem 1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .section h2 {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        .steps {
            display: grid;
            gap: 1rem;
        }

        .step {
            background: #fff;
            border-left: 5px solid #ff5f6d;
            padding: 1rem;
            border-radius: 8px;
        }

        .plans {
            display: grid;
            gap: 1rem;
        }

        .plan {
            background: #fff;
            border: 1px solid #eee;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .plan h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .plan p {
            font-size: 1rem;
        }

        .btn-buy {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: #5327ef;
            color: white;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
        }

        footer {
            text-align: center;
            padding: 2rem 1rem;
            font-size: 0.9rem;
            color: #777;
        }

        footer a {
            color: #5327ef;
            text-decoration: none;
        }

        @media (min-width: 600px) {
            .steps {
                grid-template-columns: 1fr 1fr;
            }

            .plans {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<header>
    <img src="{{ asset('superspotwifi-logo.png') }}" alt="SuperSpot Wifi Logo">
    <h1>SuperSpot Wifi</h1>
    <p>Fast, Reliable, and Affordable Internet Access</p>
    <div class="cta">
        <a href="{{ url('/buy-voucher/0') }}">Connect Now</a>
    </div>
</header>

<div class="section">
    <h2>How It Works</h2>
    <div class="steps">
        <div class="step"><strong>Step 1:</strong> Connect to the <em>SuperSpot Wifi</em> network.</div>
        <div class="step"><strong>Step 2:</strong> Enter a valid voucher code or purchase one below.</div>
        <div class="step"><strong>Step 3:</strong> Enjoy uninterrupted internet access.</div>
        <div class="step"><strong>Need help?</strong><br/><br/>
            Call @foreach ($supportContacts as $item)
                @if ($item->type == 'Tel')
                    <a href="tel:{{ $item->contact }}" class="phone">{{ $item->phone_number }}</a> &nbsp;
                @endif

            @endforeach
            <hr/>
            <br/>
              WhatsApp
            @foreach ($supportContacts as $item)
                @if ($item->type == 'WhatsApp')
                    <a href="https://wa.me/{{ $item->formatted_phone_number }}" class="phone" target="_blank">{{ $item->phone_number }}</a> &nbsp;
                @endif
            @endforeach
            for support
        </div>
    </div>
</div>

<div class="section">
    <h2>Available Plans</h2>
    <div class="plans">
        @foreach ($plans as $plan)
        <div class="plan">
            <h3>{{ $plan->name }}</h3>
            <p><strong>UGX {{ number_format($plan->price, 0) }}</strong></p>
            <a class="btn-buy" href="{{ url('buy-voucher/' . $plan->id) }}">Buy Now</a>
        </div>
        @endforeach
    </div>
</div>

<footer>
    &copy; {{ date('Y') }} SuperSpot Wifi. Powered by <a href="#">Eng. Godwin</a>.
</footer>

    <script>
        function openWhatsAppLink(whatsappUrl){
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            if (/android/i.test(userAgent)) {
                window.location.href = whatsappUrl;
            } else if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                window.location.href = whatsappUrl;
            } else {
                window.open(whatsappUrl, '_blank');
            }
        }
    </script>

</body>
</html>
