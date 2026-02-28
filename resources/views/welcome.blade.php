<!doctype html>
<html lang="en" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-preloader="disable" data-theme="default" data-topbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    @php
        $store = \App\Models\StoreSetting::getSettings();
        $storeName = $store?->store_name ?? 'Frost Hub';
    @endphp
    <title>{{ $storeName }} - Precision Inventory Control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

    <!-- AOS Animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Theme CSS -->
    <script src="{{ asset('theme/layouts/assets/js/layout.js') }}"></script>
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/app.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/custom.min.css') }}" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; }
        .hero-bg { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); }
        .dark .hero-bg { background: linear-gradient(135deg, #0c4a6e 0%, #082f49 100%); }
        .brand-title { font-family: 'Playfair Display', serif; font-weight: 900; letter-spacing: -1px; }
        .feature-icon { font-size: 3rem; color: #0ea5e9; transition: transform 0.4s ease; }
        .feature-card:hover .feature-icon { transform: translateY(-10px) scale(1.1); }
        .orbit-container { position: relative; width: 320px; height: 320px; margin: 0 auto; }
        @media (max-width: 992px) { .orbit-container { width: 280px; height: 280px; } }
        @media (max-width: 768px) { .orbit-container { width: 240px; height: 240px; } }
        @keyframes orbit {
            from { transform: rotate(0deg) translateX(160px) rotate(0deg); }
            to { transform: rotate(360deg) translateX(160px) rotate(-360deg); }
        }
        .orbit-item {
            position: absolute; top: 50%; left: 50%; width: 70px; height: 70px;
            margin: -35px 0 0 -35px; animation: orbit 30s linear infinite;
        }
        .orbit-item:nth-child(2) { animation-delay: -6s; animation-direction: reverse; }
        .orbit-item:nth-child(3) { animation-delay: -12s; }
        .orbit-item:nth-child(4) { animation-delay: -18s; animation-direction: reverse; }
        .orbit-item:nth-child(5) { animation-delay: -24s; }
    </style>
</head>
<body class="hero-bg">

<section class="position-relative d-flex align-items-center min-vh-100 overflow-hidden">
    <div class="container position-relative z-2 py-5">
        <div class="row justify-content-center align-items-center g-5">
            <div class="col-lg-11">
                <div class="card overflow-hidden shadow-xl" data-aos="fade-up">

                    <!-- Mobile: Logo & Title First -->
                    <div class="text-center d-lg-none py-5" data-aos="fade-down">
                        @if($store?->logo)
                            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" class="img-fluid mb-4" style="max-height: 100px;">
                        @endif
                        <h1 class="brand-title fs-5xl mt-4 text-info">{{ $storeName }}</h1>
                        <p class="fs-lg text-muted px-4">Precision control. Zero chaos. Total confidence in your inventory.</p>
                    </div>

                    <div class="row g-0 align-items-center">

                        <!-- Left: Animated Hero -->
                        <div class="col-lg-6 d-none d-lg-block">
                            <div class="card auth-card bg-info h-100 border-0 shadow-none">
                                <div class="card-body py-5 d-flex flex-column justify-content-center h-100 text-white">
                                    <div class="text-center mb-5" data-aos="fade-down" data-aos-delay="200">
                                        @if($store?->logo)
                                            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" class="img-fluid mb-4" style="max-height: 120px;">
                                        @endif
                                        <h2 class="brand-title fs-5xl mb-3">{{ $storeName }}</h2>
                                        <p class="fs-lg opacity-90">Crystal-clear mastery over your stock, orders, and growth.</p>
                                    </div>

                                    <div class="orbit-container my-5" data-aos="zoom-in" data-aos-delay="400">
                                        <div class="effect-circle-1 mx-auto rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:340px;height:340px;">
                                            <h4 class="brand-title fs-3xl text-center">Cool Efficiency<br>In Every Detail</h4>
                                        </div>

                                        <div class="orbit-item"><div class="avatar-lg rounded-circle bg-white bg-opacity-20 shadow-lg d-flex align-items-center justify-content-center"><i class="ri-box-fill feature-icon"></i></div></div>
                                        <div class="orbit-item"><div class="avatar-lg rounded-circle bg-white bg-opacity-20 shadow-lg d-flex align-items-center justify-content-center"><i class="ri-truck-fill feature-icon"></i></div></div>
                                        <div class="orbit-item"><div class="avatar-lg rounded-circle bg-white bg-opacity-20 shadow-lg d-flex align-items-center justify-content-center"><i class="ri-bar-chart-fill feature-icon"></i></div></div>
                                        <div class="orbit-item"><div class="avatar-lg rounded-circle bg-white bg-opacity-20 shadow-lg d-flex align-items-center justify-content-center"><i class="ri-shopping-cart-2-fill feature-icon"></i></div></div>
                                        <div class="orbit-item"><div class="avatar-lg rounded-circle bg-white bg-opacity-20 shadow-lg d-flex align-items-center justify-content-center"><i class="ri-notification-3-fill feature-icon"></i></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Content -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-none">
                                <div class="card-body p-5 p-lg-8">
                                    <!-- Desktop Logo -->
                                    <div class="text-center mb-6 d-none d-lg-block" data-aos="fade-left">
                                        @if($store?->logo)
                                            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" class="img-fluid mb-4" style="max-height: 120px;">
                                        @endif
                                        <h1 class="brand-title fs-6xl mt-4 text-info">{{ $storeName }}</h1>
                                        <p class="fs-lg text-muted mt-3">Transform inventory chaos into crystal-clear control. Real-time insights, smart alerts, and seamless operations.</p>
                                    </div>

                                    <!-- Dashboard Previews (you can replace these with real screenshots) -->
                                    <h4 class="text-center mb-4 brand-title" data-aos="fade-up">Elegant Dashboards</h4>
                                    <div class="row g-4 justify-content-center" data-aos="fade-up" data-aos-delay="300">
                                        <div class="col-12 col-md-6">
                                            <img src="{{ asset('theme/layouts/assets/images/dashboard-preview-1.jpg') }}" alt="Dashboard" class="img-fluid rounded shadow">
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <img src="{{ asset('theme/layouts/assets/images/dashboard-preview-2.jpg') }}" alt="Analytics" class="img-fluid rounded shadow">
                                        </div>
                                        <div class="col-12">
                                            <img src="{{ asset('theme/layouts/assets/images/pos-preview.jpg') }}" alt="POS" class="img-fluid rounded shadow">
                                        </div>
                                    </div>

                                    <div class="text-center mt-6" data-aos="fade-up" data-aos-delay="500">
                                        @auth
                                            <a href="{{ url('/dashboard') }}" class="btn btn-info btn-lg px-6 shadow">Enter Dashboard</a>
                                        @else
                                            <a href="{{ route('login') }}" class="btn btn-outline-info btn-lg me-4 px-6">Sign In</a>
                                            {{-- @if (Route::has('register'))
                                                <a href="{{ route('register') }}" class="btn btn-info btn-lg px-6 shadow">Start Free Trial</a>
                                            @endif --}}
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Background Effects -->
    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10 pointer-events-none">
        <!-- You can add subtle SVG or PNG frost effects here -->
    </div>
</section>

<!-- Scripts -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1200, once: true, easing: 'ease-out-quart' });
</script>
<script src="{{ asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/js/plugins.js') }}"></script>

</body>
</html>
