@extends('layouts.management')

@section('title', ($mgmtDisplayName ?? 'Management') . ' Console')

@section('content')
<div class="admin-main-content">

    {{-- Package Module Cards — Google Admin Console style --}}
    <div class="mgmt-modules-grid admin-responsive">
        @foreach($packageCards as $card)
        <div class="mgmt-module-card mgmt-rich-card">
            <div class="module-header">
                <div class="module-icon {{ $card['color'] }}">
                    <i class="{{ $card['icon'] }}"></i>
                </div>
                <div class="module-header-text">
                    <h3>{{ $card['name'] }}</h3>
                    <p class="module-subtitle">{{ $card['subtitle'] }}</p>
                </div>
                <a href="{{ $card['url'] }}" class="module-manage-link">Manage</a>
            </div>
            @if(!empty($card['quick_actions']))
            <div class="module-quick-actions">
                @foreach($card['quick_actions'] as $action)
                <a href="{{ $action['url'] }}" class="module-action-link">
                    @if($action['icon'])<i class="{{ $action['icon'] }}"></i> @endif{{ $action['title'] }}
                </a>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach

        {{-- Legacy sections as cards --}}
        @foreach($legacySections as $section)
        <div class="mgmt-module-card mgmt-rich-card">
            <div class="module-header">
                <div class="module-icon gray">
                    <i class="{{ $section['icon'] ?? 'bi-folder' }}"></i>
                </div>
                <div class="module-header-text">
                    <h3>{{ $section['name'] }}</h3>
                    <p class="module-subtitle">
                        {{ $section['submission_count'] ?? 0 }} submission{{ ($section['submission_count'] ?? 0) !== 1 ? 's' : '' }}
                        @if(($section['pending_count'] ?? 0) > 0)
                        &middot; <strong>{{ $section['pending_count'] }} pending</strong>
                        @endif
                    </p>
                </div>
                <a href="/management/section.php?slug={{ urlencode($section['slug']) }}" class="module-manage-link">Manage</a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Attention items (if any) --}}
    @if(!empty($attentionItems))
    <div class="attention-section">
        <h2><i class="bi bi-bell attention-icon-bell"></i> Needs Your Attention</h2>
        <div class="attention-list">
            @foreach($attentionItems as $item)
            <div class="attention-item type-{{ $item['type'] }}">
                <div class="attention-icon {{ $item['type'] }}">
                    <i class="{{ $item['icon'] }}"></i>
                </div>
                <div class="attention-body">
                    <p class="attention-title">{{ $item['title'] }}</p>
                    <p class="attention-subtitle">{{ $item['subtitle'] }}</p>
                </div>
                <a href="{{ $item['url'] }}" class="attention-action">
                    {{ $item['action'] }} <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
