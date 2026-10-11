<!-- Shared JS for the upgraded modules -->
<script src="{{ asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/js/plugins.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/js/app.js') }}"></script>
@include('layouts.partials.vm-helpers')
@stack('page-scripts')
