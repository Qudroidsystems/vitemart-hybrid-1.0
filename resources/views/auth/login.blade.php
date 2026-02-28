<!doctype html>
<html lang="en" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-preloader="disable" data-theme="default" data-topbar="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    @php
        $store = \App\Models\StoreSetting::getSettings();
        $storeName = $store?->store_name ?? 'Frost Hub';
    @endphp
    <title>Sign In | {{ $storeName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Smart Inventory Management" name="description">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Theme CSS -->
    <script src="{{ asset('theme/layouts/assets/js/layout.js') }}"></script>
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/app.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/custom.min.css') }}" rel="stylesheet">

    <style>
        .auth-page-wrapper { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); }
        .dark .auth-page-wrapper { background: linear-gradient(135deg, #0c4a6e 0%, #082f49 100%); }
        .auth-card { transition: transform 0.3s ease; }
        .auth-card:hover { transform: translateY(-5px); }
        @media (max-width: 576px) {
            .auth-effect-main { width: 220px; height: 220px; }
            .auth-user-list li { width: 40px; height: 40px; }
            .orbit-adjust { transform: scale(0.8); }
        }
        .auth-user-list li { transition: transform 0.3s ease; }
    </style>
</head>
<body>

<section class="auth-page-wrapper position-relative d-flex align-items-center justify-content-center min-vh-100 overflow-hidden" data-aos="fade">
    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10 pointer-events-none">
        <!-- Subtle background effects -->
    </div>

    <div class="container position-relative z-2">
        <div class="row justify-content-center">
            <div class="col-lg-11" data-aos="fade-up">
                <div class="card mb-0 overflow-hidden shadow-xl auth-card">
                    <div class="row g-0 align-items-center">

                        <!-- Left: Hero -->
                        <div class="col-xxl-5 d-none d-lg-block">
                            <div class="card auth-card bg-info h-100 border-0 shadow-none mb-0">
                                <div class="card-body py-5 d-flex justify-content-center flex-column h-100 text-white">
                                    <div class="text-center mb-4" data-aos="fade-down" data-aos-delay="200">
                                        @if($store?->logo)
                                            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" class="img-fluid mb-4" style="max-height: 120px;">
                                        @endif
                                        <h3 class="mt-3 fw-bold">Welcome to {{ $storeName }}</h3>
                                        <p class="opacity-75">Precision inventory control starts here.</p>
                                    </div>

                                    <div class="auth-effect-main my-5 position-relative rounded-circle d-flex align-items-center justify-content-center mx-auto orbit-adjust" data-aos="zoom-in" data-aos-delay="400">
                                        <div class="effect-circle-1 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-10">
                                            <div class="effect-circle-2 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-10">
                                                <div class="effect-circle-3 mx-auto rounded-circle position-relative text-white fs-4xl d-flex align-items-center justify-content-center">
                                                    {{ $storeName }}
                                                </div>
                                            </div>
                                        </div>

                                        <ul class="auth-user-list list-unstyled">
                                            <li data-aos="fade" data-aos-delay="600"><div class="avatar-sm rounded-circle bg-white bg-opacity-30 d-flex align-items-center justify-content-center"><i class="ri-user-3-fill fs-3xl"></i></div></li>
                                            <li data-aos="fade" data-aos-delay="700"><div class="avatar-sm rounded-circle bg-white bg-opacity-30 d-flex align-items-center justify-content-center"><i class="ri-user-smile-fill fs-3xl"></i></div></li>
                                            <li data-aos="fade" data-aos-delay="800"><div class="avatar-sm rounded-circle bg-white bg-opacity-30 d-flex align-items-center justify-content-center"><i class="ri-user-star-fill fs-3xl"></i></div></li>
                                            <li data-aos="fade" data-aos-delay="900"><div class="avatar-sm rounded-circle bg-white bg-opacity-30 d-flex align-items-center justify-content-center"><i class="ri-user-heart-fill fs-3xl"></i></div></li>
                                            <li data-aos="fade" data-aos-delay="1000"><div class="avatar-sm rounded-circle bg-white bg-opacity-30 d-flex align-items-center justify-content-center"><i class="ri-user-settings-fill fs-3xl"></i></div></li>
                                        </ul>
                                    </div>

                                    <div class="text-center opacity-75" data-aos="fade-up" data-aos-delay="1200">
                                        <p class="mb-0">© {{ date('Y') }} {{ $storeName }}. Engineered for excellence.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Login Form -->
                        <div class="col-xxl-7">
                            <div class="card mb-0 border-0 shadow-none">
                                <div class="card-body p-4 p-sm-5 m-lg-4" data-aos="fade-up" data-aos-delay="300">
                                    <div class="text-center mt-2">
                                        <div class="d-lg-none mb-4">
                                            @if($store?->logo)
                                                <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" class="img-fluid" style="max-height: 100px;">
                                            @endif
                                        </div>
                                        <h5 class="fs-3xl fw-bold text-info">Sign In to {{ $storeName }}</h5>
                                        <p class="text-muted">Enter your credentials to access your account.</p>
                                    </div>

                                    <div class="p-2 mt-5">
                                        <form method="POST" action="{{ route('login') }}">
                                            @csrf

                                            <div class="mb-4" data-aos="fade-up" data-aos-delay="400">
                                                <label for="email" class="form-label fs-base fw-medium">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" id="email" name="email" placeholder="Enter your email" value="{{ old('email') }}" required autofocus>
                                                @error('email')
                                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>

                                            <div class="mb-4" data-aos="fade-up" data-aos-delay="500">
                                                <div class="float-end">
                                                    @if (Route::has('password.request'))
                                                        <a href="{{ route('password.request') }}" class="text-muted fs-sm">Forgot password?</a>
                                                    @endif
                                                </div>
                                                <label class="form-label fs-base fw-medium" for="password">Password <span class="text-danger">*</span></label>
                                                <div class="position-relative auth-pass-inputgroup">
                                                    <input type="password" class="form-control form-control-lg pe-5 @error('password') is-invalid @enderror" name="password" placeholder="Enter your password" id="password" required>
                                                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" id="password-addon"><i class="ri-eye-fill align-middle fs-lg"></i></button>
                                                </div>
                                                @error('password')
                                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>

                                            <div class="form-check mb-4" data-aos="fade-up" data-aos-delay="600">
                                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="remember">Remember me</label>
                                            </div>

                                            <div class="mt-4" data-aos="fade-up" data-aos-delay="700">
                                                <button class="btn btn-info w-100 btn-lg shadow" type="submit">Sign In</button>
                                            </div>
                                        </form>

                                        <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="800">
                                            <p class="mb-0 fs-base">Don't have an account? <a href="{{ route('register') }}" class="fw-semibold text-info text-decoration-underline">Sign Up</a></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 1000,
        easing: 'ease-out-quart',
        once: true
    });
</script>
<script src="{{ asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/js/plugins.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/js/pages/password-addon.init.js') }}"></script>

</body>
</html>
