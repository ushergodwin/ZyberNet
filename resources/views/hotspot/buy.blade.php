<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buy Voucher - {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Buy your {{ config('app.name') }} internet voucher securely and instantly online.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
        .logo { width: 100px; display: block; margin: 0 auto 1rem; border-radius: 50%; }
        h5 { text-align: center; margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 6px; font-size: 0.9rem; font-weight: 500; }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: none;
            border-radius: 30px;
            outline: none;
            font-size: 1rem;
            color: #333;
        }
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: none;
            border-radius: 30px;
            outline: none;
            font-size: 0.95rem;
            color: #333;
            background: white;
            -webkit-appearance: auto;
        }
        .mb-3 { margin-bottom: 1rem; }
        .submit-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: linear-gradient(90deg, #00f0ff, #ff00c8);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.3s;
            margin-top: 10px;
        }
        .submit-button:hover { opacity: 0.9; }
        .submit-button:disabled { opacity: 0.6; cursor: not-allowed; }

        .status-section { text-align: center; margin-top: 1.5rem; }
        .spinner {
            width: 36px; height: 36px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .voucher-card {
            background: #1a4d2e;
            border-radius: 10px;
            padding: 1.2rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .voucher-code {
            font-size: 1.6em;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .voucher-expiry { font-size: 0.85rem; color: #ccc; margin-bottom: 12px; }
        .btn-row { display: flex; gap: 8px; justify-content: center; margin-top: 10px; }
        .btn-outline {
            padding: 8px 16px;
            border: 1px solid #00f0ff;
            border-radius: 6px;
            background: transparent;
            color: #00f0ff;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.3s, color 0.3s;
        }
        .btn-outline:hover { background: #00f0ff; color: #0d0c20; }

        .error-section { text-align: center; margin-top: 1.5rem; }
        .error-section .error-title { color: #ff6b6b; font-weight: bold; font-size: 1.1rem; }
        .error-section .timeout-title { color: #ffc107; font-weight: bold; font-size: 1.1rem; }
        .error-section p { font-size: 0.85rem; color: #aaa; margin-top: 8px; }

        .retry-button {
            padding: 10px 24px;
            border: 1px solid #fff;
            border-radius: 30px;
            background: transparent;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
            transition: background 0.3s, color 0.3s;
        }
        .retry-button:hover { background: #fff; color: #0d0c20; }

        hr { border: none; border-top: 1px solid #444; margin: 1rem 0; }
        .powered { font-size: 0.85rem; color: #aaa; text-align: center; }
        a.phone { color: #00f0ff; text-decoration: none; }
        a.phone:hover { text-decoration: underline; }

        .toast {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: #333; color: #fff; padding: 10px 20px;
            border-radius: 8px; font-size: 0.9rem; z-index: 999;
            opacity: 0; transition: opacity 0.3s;
        }
        .toast.show { opacity: 1; }

        .hidden { display: none; }

        @media (max-width: 400px) { .card-container { padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="card-container">
        <img src="{{ asset('superspotwifi-logo.png') }}" alt="{{ config('app.name') }} Logo" class="logo">
        <h5>{{ $wifi_name }}</h5>
        <p style="text-align:center; font-size:0.85rem; color:#aaa; margin-bottom:1rem;">Buy your internet voucher easily and securely.</p>

        <!-- Payment Form -->
        <div id="form-section">
            @if(!$package_id)
            <div class="mb-3">
                <label class="form-label">Select Package</label>
                <select id="package-select" class="form-select">
                    <option value="" disabled selected>Select a package</option>
                    @foreach($packages as $pkg)
                        <option value="{{ $pkg->id }}">{{ $pkg->name }} - UGX {{ number_format($pkg->price, 0) }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" id="phone-input" class="form-control" placeholder="+2567XXXXXXXX" />
            </div>

            <button id="pay-btn" class="submit-button" onclick="purchaseVoucher()">Pay Now</button>
        </div>

        <!-- Polling Status -->
        <div id="status-section" class="status-section hidden">
            <div class="spinner"></div>
            <p id="status-text" style="font-weight:600; margin-bottom:6px;">Please enter your PIN on your phone...</p>
            <p style="font-size:0.8rem; color:#aaa;">Do not close or refresh this page.</p>
        </div>

        <!-- Success: Voucher -->
        <div id="voucher-section" class="hidden">
            <div class="voucher-card">
                <div style="font-size:0.9rem; font-weight:600;">Your Voucher</div>
                <div id="voucher-code" class="voucher-code"></div>
                <div id="voucher-expiry" class="voucher-expiry"></div>
                <div class="btn-row">
                    <button class="btn-outline" onclick="copyVoucher()">Copy Voucher</button>
                    @if($link_login)
                    <button class="btn-outline" onclick="connectToWiFi()">Connect to WiFi</button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Failed -->
        <div id="failed-section" class="error-section hidden">
            <div class="error-title">Payment Failed</div>
            <p>The transaction could not be completed. Please try again.</p>
            <button class="retry-button" onclick="retryPayment()">Try Again</button>
        </div>

        <!-- Timed Out -->
        <div id="timeout-section" class="error-section hidden">
            <div class="timeout-title" id="timeout-text">Payment is taking too long.</div>
            <p>Please contact IT support for assistance. Your payment may still be processing.</p>
            @if(count($supportContacts) > 0)
            <div style="font-size:0.85rem; margin-top:8px;">
                @foreach($supportContacts as $contact)
                    @if($contact->type == 'Tel')
                        Call <a href="tel:{{ $contact->contact }}" class="phone">{{ $contact->phone_number }}</a>&nbsp;
                    @endif
                @endforeach
                <br/>
                @foreach($supportContacts as $contact)
                    @if($contact->type == 'WhatsApp')
                        WhatsApp <a href="https://wa.me/{{ $contact->formatted_phone_number }}" class="phone" target="_blank">{{ $contact->phone_number }}</a>&nbsp;
                    @endif
                @endforeach
            </div>
            @endif
            <button class="retry-button" onclick="retryPayment()">Try Again</button>
        </div>

        <hr>
        @if(count($supportContacts) > 0)
        <div class="powered">
            <strong>Need help?</strong><br>
            Call
            @foreach($supportContacts as $contact)
                @if($contact->type == 'Tel')
                    <a href="tel:{{ $contact->contact }}" class="phone">{{ $contact->phone_number }}</a>&nbsp;
                @endif
            @endforeach
            <br>
            WhatsApp
            @foreach($supportContacts as $contact)
                @if($contact->type == 'WhatsApp')
                    <a href="https://wa.me/{{ $contact->formatted_phone_number }}" class="phone" target="_blank">{{ $contact->phone_number }}</a>&nbsp;
                @endif
            @endforeach
        </div>
        <hr>
        @endif
        <div class="powered">Powered by <a href="javascript:void(0)" class="phone" onclick="openWhatsAppLink('https://wa.link/ogbnmg')">Eng. Godwin</a></div>
    </div>

    <!-- Hidden WiFi login form -->
    @if($link_login)
    <form method="POST" action="{{ $link_login }}" id="wifi-form" style="display:none;">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="username" id="wifi-username">
        <input type="hidden" name="password" id="wifi-password">
        <input type="hidden" name="dst" value="https://www.google.com">
    </form>
    @endif

    <div id="toast" class="toast"></div>

    <script>
        var packageId = {{ $package_id ? $package_id : 'null' }};
        var transactionId = null;
        var voucherCode = null;
        var pollTimer = null;
        var csrfToken = '{{ csrf_token() }}';

        var statusLabels = {
            'new': 'Payment initiated...',
            'pending': 'Waiting for confirmation...',
            'instructions_sent': 'Please enter your PIN on your phone...',
            'processing_started': 'Processing your payment...',
            'successful': 'Payment successful!',
            'failed': 'Payment failed.'
        };

        function show(id) { document.getElementById(id).classList.remove('hidden'); }
        function hide(id) { document.getElementById(id).classList.add('hidden'); }

        function showToast(msg) {
            var t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(function() { t.classList.remove('show'); }, 2500);
        }

        function formatPhoneNumber(number) {
            number = number.trim();
            if (number.startsWith('0')) return '+256' + number.slice(1);
            if (number.startsWith('256') && !number.startsWith('+256')) return '+256' + number.slice(3);
            if (!number.startsWith('+256') && !number.startsWith('256') && !number.startsWith('0') && number.length === 9) return '+256' + number;
            return number;
        }

        function purchaseVoucher() {
            var phone = document.getElementById('phone-input').value.trim();
            if (!phone) { alert('Please enter phone number'); return; }
            if (phone.replace(/[^0-9]/g, '').length < 9 || phone.replace(/[^0-9]/g, '').length > 13) {
                alert('Phone number must be between 9 and 13 digits');
                return;
            }

            var pkgId = packageId;
            if (!pkgId) {
                var sel = document.getElementById('package-select');
                if (sel) pkgId = sel.value;
            }
            if (!pkgId) {
                alert('Please select a package');
                return;
            }

            phone = formatPhoneNumber(phone);
            document.getElementById('phone-input').value = phone;

            var btn = document.getElementById('pay-btn');
            btn.disabled = true;
            btn.textContent = 'Sending...';

            fetch('/api/payments/voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ phone_number: phone, package_id: parseInt(pkgId) })
            })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(result) {
                if (result.ok && result.data.paymentData && result.data.paymentData.id) {
                    transactionId = result.data.paymentData.id;
                    hide('form-section');
                    show('status-section');
                    document.getElementById('status-text').textContent = statusLabels['instructions_sent'];
                    startPolling();
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Pay Now';
                    alert(result.data.message || 'Failed to initiate payment. Please try again.');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Pay Now';
                alert('Payment failed. Please try again.');
            });
        }

        function startPolling() {
            var pollingInterval = 5000;
            var timeoutDuration = 180000;
            var elapsed = 0;

            function pollOnce() {
                elapsed += pollingInterval;

                fetch('/api/payments/voucher/status/' + transactionId)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    var txn = data.transaction;
                    var voucher = data.voucher;

                    if (txn && txn.status) {
                        document.getElementById('status-text').textContent = statusLabels[txn.status] || 'Processing...';
                    }

                    if (voucher) {
                        stopPolling();
                        voucherCode = voucher.code;
                        hide('status-section');
                        show('voucher-section');
                        document.getElementById('voucher-code').textContent = voucher.code;
                        document.getElementById('voucher-expiry').textContent = 'Expires: ' + new Date(voucher.expires_at).toLocaleString();
                        return;
                    }

                    if (txn && txn.status === 'failed') {
                        stopPolling();
                        hide('status-section');
                        show('failed-section');
                        return;
                    }

                    if (elapsed >= timeoutDuration) {
                        stopPolling();
                        hide('status-section');
                        show('timeout-section');
                    }
                })
                .catch(function() {
                    if (elapsed >= timeoutDuration) {
                        stopPolling();
                        hide('status-section');
                        document.getElementById('timeout-text').textContent = 'Could not verify payment status.';
                        show('timeout-section');
                    }
                });
            }

            pollOnce();
            pollTimer = setInterval(pollOnce, pollingInterval);
        }

        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        function retryPayment() {
            hide('failed-section');
            hide('timeout-section');
            hide('voucher-section');
            show('form-section');
            transactionId = null;
            voucherCode = null;
            var btn = document.getElementById('pay-btn');
            btn.disabled = false;
            btn.textContent = 'Pay Now';
        }

        function copyVoucher() {
            if (!voucherCode) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(voucherCode)
                    .then(function() { showToast('Voucher copied to clipboard'); })
                    .catch(function() { fallbackCopy(); });
            } else {
                fallbackCopy();
            }
        }

        function fallbackCopy() {
            var ta = document.createElement('textarea');
            ta.value = voucherCode;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('Voucher copied to clipboard');
        }

        function connectToWiFi() {
            if (!voucherCode) return;
            var form = document.getElementById('wifi-form');
            if (form) {
                document.getElementById('wifi-username').value = voucherCode;
                document.getElementById('wifi-password').value = voucherCode;
                form.submit();
            } else {
                showToast('Connect to {{ $wifi_name }} and use your voucher to access the internet.');
            }
        }

        function openWhatsAppLink(url) {
            var ua = navigator.userAgent || navigator.vendor || window.opera;
            if (/android/i.test(ua) || /iPad|iPhone|iPod/.test(ua)) {
                window.location.href = url;
            } else {
                window.open(url, '_blank');
            }
        }
    </script>
</body>
</html>
