<!-- partial:partials/_sidebar.html -->
<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link" href="{{ url('/dashboard') }}">
                <i class="icon-grid menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        @can('region.view')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.regions.index') }}">
                    <i class="ti-map-alt menu-icon"></i>
                    <span class="menu-title">Regions</span>
                </a>
            </li>
        @endcan
        @can('channel.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.channels.index') }}">
                <i class="ti-video-camera menu-icon"></i>
                <span class="menu-title">Channels</span>
            </a>
        </li>
        @endcan
        @can('category.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.categories.index') }}">
                <i class="icon-layout menu-icon"></i>
                <span class="menu-title">Categories</span>
            </a>
        </li>
        @endcan
        @can('character_tag.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.character_tags.index') }}">
                <i class="icon-layout menu-icon"></i>
                <span class="menu-title">Character Tags</span>
            </a>
        </li>
        @endcan
        @can('character_role.view')
        <li class="nav-item {{ request()->routeIs('admin.character_roles.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.character_roles.index') }}">
                <i class="icon-layout menu-icon"></i>
                <span class="menu-title">Character Roles</span>
            </a>
        </li>
        @endcan
        @can('character.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.characters.index') }}">
                <i class="ti-user menu-icon"></i>
                <span class="menu-title">Characters</span>
            </a>
        </li>
        @endcan
        @can('highlight_tag.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.highlight_tags.index') }}">
                <i class="ti-tag menu-icon"></i>
                <span class="menu-title">Highlight Tags</span>
            </a>
        </li>
        @endcan
        @can('video.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.videos.index') }}">
                <i class="ti-video-clapper menu-icon"></i>
                <span class="menu-title">Videos</span>
            </a>
        </li>
        @endcan
        @can('video.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.vimeo.index') }}">
                <i class="ti-video-clapper menu-icon"></i>
                <span class="menu-title">Vimeo Videos</span>
            </a>
        </li>
        @endcan
        @can('rating_review.view')
        <li class="nav-item {{ request()->is('admin/reviews*') ? 'active' : '' }}">
            <a class="nav-link {{ request()->is('admin/reviews*') ? 'active' : '' }}"
                href="{{ route('admin.reviews.index') }}">
                <i class="ti-star menu-icon"></i>
                <span class="menu-title">Ratings &amp; Reviews</span>
            </a>
        </li>
        @endcan
        @can('users.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.users.index') }}">
                <i class="icon-head menu-icon"></i>
                <span class="menu-title">Users</span>
            </a>
        </li>
        @endcan
        @can('subscription_list.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.subscription_listing.index') }}">
                <i class="ti-package menu-icon"></i>
                <span class="menu-title">Subscription Listings</span>
            </a>
        </li>
        @endcan
        @can('subscription.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.subscriptions.index') }}">
                <i class="ti-credit-card menu-icon"></i>
                <span class="menu-title">Subscriptions</span>
            </a>
        </li>
        @endcan
        @role('super_admin')
        <li class="nav-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.roles.index') }}">
                <i class="ti-lock menu-icon"></i>
                <span class="menu-title">Role Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.sub_admins.index') }}">
                <i class="ti-user menu-icon"></i>
                <span class="menu-title">Sub-admin Management</span>
            </a>
        </li>
        @endrole
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#form-elements" aria-expanded="false"
                aria-controls="form-elements">
                <i class="icon-columns menu-icon"></i>
                <span class="menu-title">Form elements</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="form-elements">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"><a class="nav-link" href="pages/forms/basic_elements.html">Basic
                            Elements</a></li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#charts" aria-expanded="false" aria-controls="charts">
                <i class="icon-bar-graph menu-icon"></i>
                <span class="menu-title">Charts</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="charts">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"> <a class="nav-link" href="pages/charts/chartjs.html">ChartJs</a></li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#tables" aria-expanded="false" aria-controls="tables">
                <i class="icon-grid-2 menu-icon"></i>
                <span class="menu-title">Tables</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="tables">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"> <a class="nav-link" href="{{ route('pm.maker') }}">PM Maker</a></li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#icons" aria-expanded="false" aria-controls="icons">
                <i class="icon-contract menu-icon"></i>
                <span class="menu-title">Icons</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="icons">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"> <a class="nav-link" href="pages/icons/mdi.html">Mdi icons</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#error" aria-expanded="false"
                aria-controls="error">
                <i class="icon-ban menu-icon"></i>
                <span class="menu-title">Error pages</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="error">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"> <a class="nav-link" href="pages/samples/error-404.html"> 404
                        </a></li>
                    <li class="nav-item"> <a class="nav-link" href="pages/samples/error-500.html"> 500
                        </a></li>
                </ul>
            </div>
        </li>
        {{-- @role('super_admin')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.permissions.index') }}">
                <i class="ti-lock menu-icon"></i>
                <span class="menu-title">Permissions</span>
            </a>
        </li>
        @endrole --}}

    </ul>
</nav>
<!-- partial -->
