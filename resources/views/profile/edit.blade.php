@extends('layouts.admin.master')

@section('content')
    <h2 class="text-center mb-4">{{ __('Profile') }}</h2>

    {{-- Nav Tabs --}}
    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
                type="button" role="tab" aria-controls="profile" aria-selected="false">
                {{ __('Update Profile') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password"
                type="button" role="tab" aria-controls="password" aria-selected="true">
                {{ __('Update Password') }}
            </button>
        </li>
    </ul>

    {{-- Tab Contents --}}
    <div class="tab-content" id="profileTabsContent">
        {{-- Profile Info Tab --}}
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
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
    </div>

    {{-- JavaScript to manage tab state --}}
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const passwordTab = document.getElementById('password-tab');
            const profileTab = document.getElementById('profile-tab');
            const activeTab = localStorage.getItem('activeTab');

            // On page load, set the active tab based on what's saved in localStorage
            if (!activeTab) {
                // If no activeTab is stored, default to "profile-tab"
                new bootstrap.Tab(profileTab).show();
            } else {
                // Otherwise, show the saved active tab
                if (activeTab === 'password') {
                    new bootstrap.Tab(passwordTab).show();
                } else {
                    new bootstrap.Tab(profileTab).show();
                }
            }

            // When the form is submitted, save the active tab
            document.getElementById('password-update-form').addEventListener('submit', function() {
                localStorage.setItem('activeTab', 'password');
            });

            // Save the active tab when the user clicks on any tab
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.addEventListener('click', function() {
                    localStorage.setItem('activeTab', tab.id);
                });
            });
        });
    </script>
@endsection
