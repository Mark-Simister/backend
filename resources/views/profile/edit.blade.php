@extends('layouts.admin.master')

@section('content')
    <h2 class="text-center mb-4">{{ __('Profile') }}</h2>

    {{-- Nav Tabs --}}
    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
                type="button" role="tab" aria-controls="profile" aria-selected="true">
                {{ __('Update Profile') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password"
                type="button" role="tab" aria-controls="password" aria-selected="false">
                {{ __('Update Password') }}
            </button>
        </li>
        {{-- <li class="nav-item" role="presentation">
            <button class="nav-link text-danger" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete"
                type="button" role="tab" aria-controls="delete" aria-selected="false">
                {{ __('Delete Account') }}
            </button>
        </li> --}}
    </ul>

    {{-- Tab Contents --}}
    <div class="tab-content" id="profileTabsContent">
        {{-- Profile Info Tab --}}
        <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Update Profile Information') }}</h5>
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
        </div>

        {{-- Password Tab --}}
        <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Update Password') }}</h5>
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        {{-- Delete Tab --}}
        <div class="tab-pane fade" id="delete" role="tabpanel" aria-labelledby="delete-tab">
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">{{ __('Delete Account') }}</h5>
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection