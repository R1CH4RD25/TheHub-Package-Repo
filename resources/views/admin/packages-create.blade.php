@extends('layouts.admin')

@section('title', 'Package Creator')

@push('styles')
<link rel="stylesheet" href="/assets/css/package-wizard.css">
@endpush

@section('content')
<div class="admin-main-content">
    <div class="content-header">
        <div>
            <h1><i class="bi bi-magic"></i> Package Creator</h1>
            <p class="text-muted">Build a new package visually — no code required</p>
        </div>
        <a href="{{ route('admin.packages.available') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Packages
        </a>
    </div>

    <div class="content-body">
        <div id="package-wizard" class="package-wizard">
            <div class="text-muted text-center" style="padding: 3rem;">
                <i class="bi bi-hourglass-split"></i> Loading wizard...
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="/assets/js/package-wizard.js"></script>
@endpush
