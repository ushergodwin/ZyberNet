<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Include Bootstrap CSS or your custom CSS here -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <style>
        /* Your scoped login page styles here */
        .login-bg {
            background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .login-card-wrapper {
            max-width: 400px;
            width: 100%;
            padding: 2rem 0;
        }

        .login-card {
            background: #0d0c20;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            color: #fff;
        }

        .logo {
            width: 100px;
            cursor: pointer;
        }

        .input-rounded {
            border-radius: 30px;
            padding: 0.5rem 1rem;
        }

        .btn-gradient {
            background: linear-gradient(90deg, #00f0ff, #ff00c8);
            border: none;
            border-radius: 30px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            opacity: 0.9;
        }

        @media (max-width: 600px) {
            .login-card-wrapper {
                padding: 1rem 0;
            }

            .login-card {
                padding: 1.25rem 1rem;
            }
        }
    </style>
</head>
<body class="login-bg d-flex align-items-center justify-content-center">

    <section class="login-card-wrapper w-100">
        <div class="login-card card card-body shadow-lg border-0 mx-auto">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Logo -->
                <div class="text-center mb-4" onclick="window.location='/'" role="button">
                    <img src="{{ asset('build/assets/superspotwifi-logo-qdoREh9c.png') }}" alt="SuperSpot Wifi Logo" class="img-fluid rounded-circle logo" />
                </div>

                <!-- Title -->
                <h2 class="text-center mb-4">Admin Panel</h2>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="form-control input-rounded @error('email') is-invalid @enderror" autocomplete="username" />
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" required
                        class="form-control input-rounded @error('password') is-invalid @enderror" autocomplete="current-password" />
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Remember Me</label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn-gradient w-100 py-2 fw-semibold">
                    <i class="fas fa-lock me-2"></i> Log in
                </button>

            </form>
        </div>
    </section>

    <!-- Optional: include Bootstrap JS and FontAwesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
